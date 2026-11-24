<?php
// Grant version 4 permissions.
return function (): void {
    $super = (int)DB::val("SELECT id FROM {p}roles WHERE slug = 'super_admin'");
    foreach (all_permissions() as $perm) {
        DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$super, $perm]);
    }
    foreach (phase4_role_grants() as $slug => $perms) {
        $rid = (int)DB::val('SELECT id FROM {p}roles WHERE slug = ?', [$slug]);
        foreach ($perms as $p) {
            if ($rid) {
                DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$rid, $p]);
            }
        }
    }
};
