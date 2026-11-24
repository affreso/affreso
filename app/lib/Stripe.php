<?php
declare(strict_types=1);

/** Stripe Checkout for online giving (no SDK needed). */
final class Stripe
{
    public static function enabled(): bool
    {
        return !Demo::on() && setting('stripe_secret_key', '') !== '';
    }

    private static function key(): string
    {
        return (string)Crypto::decrypt((string)setting('stripe_secret_key', ''));
    }

    public static function api(string $method, string $path, array $fields = []): array
    {
        // config('stripe_endpoint') points tests at a local mock; it is never set on a real site.
        $base = rtrim((string)(config('stripe_endpoint') ?? 'https://api.stripe.com/v1'), '/') . '/';
        [$code, $resp] = http_request($method, $base . ltrim($path, '/'), $fields ? http_build_query($fields) : null, ['Content-Type: application/x-www-form-urlencoded'], self::key() . ':');
        $j = json_decode((string)$resp, true) ?: [];
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('Stripe: ' . ($j['error']['message'] ?? "HTTP $code"));
        }
        return $j;
    }

    public static function createCheckout(int $donationId, int $cents, string $currency, string $label, ?string $email, string $successUrl, string $cancelUrl): array
    {
        $f = [
            'mode' => 'payment',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $cents,
            'line_items[0][price_data][product_data][name]' => $label,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata[donation_id]' => $donationId,
            'payment_intent_data[metadata][donation_id]' => $donationId,
            'submit_type' => 'donate',
        ];
        if ($email) {
            $f['customer_email'] = $email;
        }
        return self::api('POST', 'checkout/sessions', $f);
    }

    /** A one-off payment (e.g. a camp fee) tagged with metadata for the webhook. */
    public static function createPaymentCheckout(array $metadata, int $cents, string $currency, string $label, ?string $email, string $successUrl, string $cancelUrl): array
    {
        $f = [
            'mode' => 'payment',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $cents,
            'line_items[0][price_data][product_data][name]' => mb_substr($label, 0, 200),
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'submit_type' => 'pay',
        ];
        foreach ($metadata as $k => $v) {
            $f['metadata[' . $k . ']'] = $v;
            $f['payment_intent_data[metadata][' . $k . ']'] = $v;
        }
        if ($email) {
            $f['customer_email'] = $email;
        }
        return self::api('POST', 'checkout/sessions', $f);
    }

    /**
     * A fixed number of monthly payments (e.g. a camp fee in three parts). Stripe takes the first payment now; Affreso
     * counts the paid invoices and cancels the subscription after the last one.
     */
    public static function createInstalmentCheckout(array $metadata, int $centsEach, int $count, string $currency, string $label, string $email, string $successUrl, string $cancelUrl): array
    {
        $f = [
            'mode' => 'subscription',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $centsEach,
            'line_items[0][price_data][recurring][interval]' => 'month',
            'line_items[0][price_data][recurring][interval_count]' => 1,
            'line_items[0][price_data][product_data][name]' => mb_substr($label, 0, 200),
            'customer_email' => $email,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'subscription_data[description]' => mb_substr($label, 0, 200),
            'subscription_data[metadata][instalments]' => $count,
        ];
        foreach ($metadata as $k => $v) {
            $f['metadata[' . $k . ']'] = $v;
            $f['subscription_data[metadata][' . $k . ']'] = $v;
        }
        return self::api('POST', 'checkout/sessions', $f);
    }

    public static function createSubscriptionCheckout(int $recurringId, int $cents, string $currency, string $label, string $frequency, string $email, string $successUrl, string $cancelUrl): array
    {
        [$interval, $count] = match ($frequency) {
            'week' => ['week', 1],
            'fortnight' => ['week', 2],
            default => ['month', 1],
        };
        return self::api('POST', 'checkout/sessions', [
            'mode' => 'subscription',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $cents,
            'line_items[0][price_data][recurring][interval]' => $interval,
            'line_items[0][price_data][recurring][interval_count]' => $count,
            'line_items[0][price_data][product_data][name]' => $label,
            'customer_email' => $email,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata[recurring_id]' => $recurringId,
            'subscription_data[metadata][recurring_id]' => $recurringId,
        ]);
    }

    /** Verify the Stripe-Signature header (v1 HMAC-SHA256, 5 minute tolerance). */
    public static function verifyWebhook(string $payload, string $header, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = (int)($parts['t'][0] ?? 0);
        if (!$t || abs(time() - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }
}
