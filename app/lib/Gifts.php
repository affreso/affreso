<?php
declare(strict_types=1);

/**
 * Refunds for gifts. A gift's amount_cents is always what the church kept (the gift less any refunds), so
 * giving statements, reports and campaign totals stay right without changing how they add up; refunded_cents
 * records how much went back. A gift refunded in full has the status "refunded".
 */
final class Gifts
{
    /** The amount originally given. */
    public static function gross(array $d): int
    {
        return (int)$d['amount_cents'] + (int)($d['refunded_cents'] ?? 0);
    }

    /** Can this gift go back to the card through Stripe? */
    public static function cardRefundable(array $d): bool
    {
        return $d['method'] === 'stripe' && $d['status'] === 'paid' && (int)$d['amount_cents'] > 0
            && (!empty($d['stripe_pi']) || str_starts_with((string)$d['stripe_session_id'], 'cs_') || str_starts_with((string)$d['external_ref'], 'stripe:in_'));
    }

    /** The PaymentIntent behind an online gift, looked up in Stripe if it wasn't stored when the gift was made. */
    private static function paymentIntent(array $d): string
    {
        if (!empty($d['stripe_pi'])) {
            return (string)$d['stripe_pi'];
        }
        $ref = str_starts_with((string)$d['external_ref'], 'stripe:') ? substr((string)$d['external_ref'], 7) : (string)$d['stripe_session_id'];
        $obj = str_starts_with($ref, 'in_') ? Stripe::api('GET', 'invoices/' . rawurlencode($ref) . '?expand[]=payments') : Stripe::api('GET', 'checkout/sessions/' . rawurlencode($ref));
        $pi = $obj['payment_intent'] ?? ($obj['payments']['data'][0]['payment']['payment_intent'] ?? null);
        $pi = is_array($pi) ? ($pi['id'] ?? null) : $pi;
        if (!$pi) {
            throw new RuntimeException(__('Stripe has no payment to refund for %s.', $ref));
        }
        DB::update('donations', ['stripe_pi' => $pi], 'id = ?', [$d['id']]);
        return (string)$pi;
    }

    /**
     * Refund part or all of a gift. $how is 'card' (through Stripe) or 'record' (money given back another way,
     * or a correction to a gift recorded by mistake). Returns the updated gift.
     */
    public static function refund(array $d, int $cents, string $how, string $reason = ''): array
    {
        if ($cents <= 0 || $cents > (int)$d['amount_cents']) {
            throw new RuntimeException(__('Enter an amount up to %s.', fmt_money((int)$d['amount_cents'], $d['currency'])));
        }
        if ($how === 'card') {
            if (!self::cardRefundable($d)) {
                throw new RuntimeException(__('This gift wasn\'t made online, so it can\'t be refunded to a card.'));
            }
            if (!Demo::on()) {
                Stripe::api('POST', 'refunds', ['payment_intent' => self::paymentIntent($d), 'amount' => $cents, 'metadata[donation_id]' => $d['id'], 'metadata[source]' => 'affreso']);
            }
        }
        $d = self::apply($d, (int)($d['refunded_cents'] ?? 0) + $cents, $reason);
        audit('giving.refunded', 'donation', (int)$d['id'], ['cents' => $cents, 'how' => $how]);
        $email = $d['donor_email'] ?: ($d['person_id'] ? DB::val('SELECT email FROM {p}people WHERE id = ?', [$d['person_id']]) : null);
        if ($email) {
            Notify::emailAddress((string)$email, __('Refund of your gift'), '<p>' . e($how === 'card'
                ? __('We have refunded %s of your gift (receipt %s) to your card. It usually reaches your account within 5–10 business days.', fmt_money($cents, $d['currency']), $d['receipt_no'] ?: '—')
                : __('We have recorded a refund of %s of your gift (receipt %s).', fmt_money($cents, $d['currency']), $d['receipt_no'] ?: '—'))
                . '</p><p>' . e(__('Your giving statement will show the reduced amount.')) . '</p>');
        }
        return $d;
    }

    /** Set the total refunded on a gift (never lower than before) and update its amount and status. */
    private static function apply(array $d, int $refunded, string $reason = ''): array
    {
        $gross = self::gross($d);
        $refunded = min($gross, max((int)($d['refunded_cents'] ?? 0), $refunded));
        $upd = ['refunded_cents' => $refunded, 'amount_cents' => $gross - $refunded, 'status' => $gross - $refunded <= 0 ? 'refunded' : $d['status']];
        $added = $refunded - (int)($d['refunded_cents'] ?? 0);
        if ($added > 0) {
            $note = __('Refunded %s on %s', fmt_money($added, $d['currency']), fmt_date(date('Y-m-d'))) . ($reason !== '' ? ' (' . $reason . ')' : '') . '.';
            $upd['notes'] = mb_substr(trim((string)$d['notes'] . ' ' . $note), 0, 255);
        }
        DB::update('donations', $upd, 'id = ?', [$d['id']]);
        return array_merge($d, $upd);
    }

    /**
     * Stripe reports a refund (made in Affreso or in the Stripe dashboard). Uses the charge's running refund total,
     * so the same webhook arriving twice changes nothing. Returns true if the charge was a gift.
     */
    public static function refundFromStripe(array $charge): bool
    {
        $d = null;
        if (!empty($charge['payment_intent'])) {
            $d = DB::row('SELECT * FROM {p}donations WHERE stripe_pi = ?', [(string)$charge['payment_intent']]);
        }
        if (!$d && !empty($charge['metadata']['donation_id'])) {
            $d = DB::row('SELECT * FROM {p}donations WHERE id = ?', [(int)$charge['metadata']['donation_id']]);
        }
        if (!$d) {
            return false;
        }
        $was = (int)$d['refunded_cents'];
        $d = self::apply($d, (int)($charge['amount_refunded'] ?? self::gross($d)), __('through Stripe'));
        if ((int)$d['refunded_cents'] > $was) {
            audit('giving.refunded', 'donation', (int)$d['id'], ['cents' => (int)$d['refunded_cents'] - $was, 'how' => 'stripe']);
        }
        return true;
    }
}
