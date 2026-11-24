<?php
declare(strict_types=1);

final class Rostering
{
    /** Move an assignment to the replacement volunteer and tell everyone involved. */
    public static function completeSwap(array $s): void
    {
        DB::update('roster_assignments', ['person_id' => $s['replacement_id'], 'status' => 'accepted', 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['assignment_id']]);
        DB::update('swap_requests', ['status' => 'approved'], 'id = ?', [$s['id']]);
        $pos = (string)DB::val('SELECT name FROM {p}positions WHERE id = ?', [$s['position_id']]);
        $when = fmt_date($s['service_date'], 'l j F');
        Notify::person((int)$s['requested_by'], __('Your swap is confirmed'), '<p>' . e(__('%s will now serve as %s on %s. Thank you for arranging cover.', person_name(DB::row('SELECT * FROM {p}people WHERE id = ?', [$s['replacement_id']])), $pos, $when)) . '</p>');
        Notify::person((int)$s['replacement_id'], __('You are rostered: %s, %s', $pos, $when), '<p>' . e(__('Thanks for covering! You are now rostered as %s for %s on %s.', $pos, $s['title'], $when)) . '</p>');
        foreach (DB::col("SELECT person_id FROM {p}group_members WHERE group_id = ? AND member_role IN ('leader','assistant')", [$s['group_id']]) as $lp) {
            notify_user(user_id_for_person((int)$lp), __('Swap completed: %s on %s', $pos, $when), url('/rosters/' . $s['roster_id']));
        }
    }

    public static function notifyLeaders(int $groupId, string $subject, string $html, string $link): void
    {
        $uids = [];
        foreach (DB::col("SELECT person_id FROM {p}group_members WHERE group_id = ? AND member_role IN ('leader','assistant')", [$groupId]) as $lp) {
            if ($uid = user_id_for_person((int)$lp)) {
                $uids[] = $uid;
            }
        }
        if (!$uids) {
            $uids = users_with_permission('rosters.manage');
        }
        Notify::users($uids, $subject, $html, $link);
    }
}
