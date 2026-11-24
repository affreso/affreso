<?php
// Grant Phase 2 permissions to existing roles and add the new system roles.
return function (): void {
    $super = (int)DB::val("SELECT id FROM {p}roles WHERE slug = 'super_admin'");
    foreach (all_permissions() as $perm) {
        DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$super, $perm]);
    }
    foreach (default_roles() as $slug => [$name, $desc, $perms]) {
        if (!DB::val('SELECT 1 FROM {p}roles WHERE slug = ?', [$slug])) {
            $rid = DB::insert('roles', ['slug' => $slug, 'name' => $name, 'description' => $desc, 'is_system' => 1]);
            foreach ($perms as $p) {
                DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$rid, $p]);
            }
        }
    }
    foreach (phase2_role_grants() as $slug => $perms) {
        $rid = (int)DB::val('SELECT id FROM {p}roles WHERE slug = ?', [$slug]);
        foreach ($perms as $p) {
            if ($rid) {
                DB::run('INSERT IGNORE INTO {p}role_permissions (role_id, permission) VALUES (?, ?)', [$rid, $p]);
            }
        }
    }
    if (!DB::val('SELECT COUNT(*) FROM {p}campuses')) {
        DB::insert('campuses', ['name' => (string)setting('church_name', 'Main campus')]);
    }
    $cid = (int)DB::val('SELECT MIN(id) FROM {p}campuses');
    foreach (['people', 'groups', 'events', 'rosters', 'rooms'] as $t) {
        DB::run("UPDATE {p}$t SET campus_id = ? WHERE campus_id IS NULL", [$cid]);
    }
};
