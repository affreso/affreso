<?php
declare(strict_types=1);

/** Every permission Affreso understands, grouped for the role editor. */
function permission_catalogue(): array
{
    return [
        'People & pastoral care' => [
            'people.view' => 'View member records',
            'people.edit' => 'Add and edit member records',
            'people.delete' => 'Archive or permanently remove records',
            'people.export' => 'Export and import member data',
            'directory.view' => 'View the church directory (opted-in members only)',
            'care.manage' => 'Manage follow-up and care tasks',
            'pastoral.view' => 'Read confidential pastoral notes',
            'pastoral.edit' => 'Write confidential pastoral notes',
        ],
        'Ministries & groups' => [
            'groups.view' => 'View ministries, teams and small groups',
            'groups.manage' => 'Create and manage all groups',
        ],
        'Events & bookings' => [
            'events.manage' => 'Create and manage events and registrations',
            'forms.manage' => 'Build online forms and see their responses',
            'rooms.manage' => 'Manage rooms and equipment and approve bookings',
        ],
        'Volunteers & rosters' => [
            'volunteers.manage' => 'Review volunteer applications and approve positions',
            'rosters.view' => 'View all rosters',
            'rosters.manage' => 'Build and publish rosters for every team',
            'safeguarding.view' => 'View safeguarding check status (WWCC)',
            'safeguarding.manage' => 'Record and verify safeguarding checks',
        ],
        'Children & safety' => [
            'checkin.operate' => 'Run children\'s check-in and check-out',
            'checkin.kiosk' => 'Set up and manage self check-in kiosks',
            'children.medical' => 'See children\'s allergy, medical and care information',
            'incidents.manage' => 'Review and manage incident reports',
        ],
        'Worship & services' => [
            'services.plan' => 'Plan services, run sheets and songs',
            'attendance.record' => 'Record service attendance headcounts',
        ],
        'Prayer' => [
            'prayer.team' => 'See and pray for team and church prayer requests',
        ],
        'Resource library' => [
            'library.manage' => 'Publish documents and policies and see acknowledgement records',
        ],
        'Courses & training' => [
            'courses.create' => 'Draft and edit assigned courses',
            'courses.approve' => 'Review and publish courses',
            'courses.assign' => 'Assign training to people',
            'training.reports' => 'View training compliance',
        ],
        'Communications' => [
            'comms.send' => 'Send email and SMS messages',
            'announcements.manage' => 'Publish announcements',
        ],
        'Giving' => [
            'giving.view' => 'View donations and giving reports',
            'giving.manage' => 'Record and edit donations',
            'campaigns.manage' => 'Run giving campaigns and record pledges',
            'reimbursements.approve' => 'Approve reimbursement requests for any ministry',
            'reimbursements.pay' => 'Pay reimbursements and export bank files',
        ],
        'Reports' => [
            'reports.view' => 'View reports and insights',
        ],
        'Administration' => [
            'admin.users' => 'Manage user accounts',
            'admin.roles' => 'Edit roles and permissions',
            'admin.settings' => 'Change system settings and integrations',
            'audit.view' => 'View the audit log',
            'privacy.manage' => 'Handle privacy requests, retention and breach register',
        ],
    ];
}

function all_permissions(): array
{
    $all = [];
    foreach (permission_catalogue() as $perms) {
        $all = array_merge($all, array_keys($perms));
    }
    return $all;
}

/** Permissions added in later versions, granted to existing system roles when upgrading. */
function phase2_role_grants(): array
{
    return [
        'pastor' => ['incidents.manage', 'prayer.team', 'library.manage', 'services.plan', 'reimbursements.approve', 'checkin.operate'],
        'church_admin' => ['checkin.operate', 'library.manage', 'services.plan', 'prayer.team'],
        'safeguarding_officer' => ['checkin.operate', 'children.medical', 'incidents.manage'],
        'finance' => ['reimbursements.approve', 'reimbursements.pay'],
        'ministry_leader' => ['services.plan', 'checkin.operate'],
        'checkin_volunteer' => ['checkin.operate'],
        'prayer_team' => ['prayer.team', 'directory.view'],
    ];
}

/** Permissions added in version 3. */
function phase3_role_grants(): array
{
    return [
        'pastor' => ['attendance.record'],
        'church_admin' => ['attendance.record', 'checkin.kiosk'],
        'safeguarding_officer' => ['checkin.kiosk'],
        'ministry_leader' => ['attendance.record'],
    ];
}

/** Permissions added in version 4. */
function phase4_role_grants(): array
{
    return [
        'pastor' => ['forms.manage'],
        'church_admin' => ['forms.manage'],
        'finance' => ['campaigns.manage'],
    ];
}

/** Permissions considered sensitive: holders must use multi-factor authentication when enforced. */
function sensitive_permissions(): array
{
    return ['people.edit', 'people.export', 'pastoral.view', 'safeguarding.view', 'safeguarding.manage', 'giving.view', 'admin.users', 'admin.roles', 'admin.settings', 'privacy.manage', 'children.medical', 'incidents.manage', 'reimbursements.pay'];
}

function default_roles(): array
{
    $roles = [
        'super_admin' => ['Super Administrator', 'System configuration, access management and audit oversight', all_permissions()],
        'pastor' => ['Pastor / Elder', 'Ministry oversight, pastoral workflows and approved teaching', ['people.view', 'people.edit', 'directory.view', 'care.manage', 'pastoral.view', 'pastoral.edit', 'groups.view', 'groups.manage', 'events.manage', 'volunteers.manage', 'rosters.view', 'safeguarding.view', 'courses.approve', 'courses.assign', 'training.reports', 'comms.send', 'announcements.manage', 'reports.view']],
        'church_admin' => ['Church Administrator', 'Members, events, communications and general administration', ['people.view', 'people.edit', 'people.export', 'directory.view', 'care.manage', 'groups.view', 'groups.manage', 'events.manage', 'rooms.manage', 'volunteers.manage', 'rosters.view', 'rosters.manage', 'safeguarding.view', 'courses.assign', 'training.reports', 'comms.send', 'announcements.manage', 'reports.view']],
        'safeguarding_officer' => ['Safeguarding Officer', 'Child safety checks, volunteer suitability and compliance', ['people.view', 'directory.view', 'volunteers.manage', 'rosters.view', 'safeguarding.view', 'safeguarding.manage', 'training.reports', 'courses.assign', 'reports.view']],
        'finance' => ['Finance Officer', 'Donations, receipts and giving reports', ['giving.view', 'giving.manage', 'directory.view']],
        'ministry_leader' => ['Ministry Leader', 'Own team, rosters, volunteer onboarding and team resources (scoped to teams they lead)', ['directory.view', 'groups.view', 'rosters.view']],
        'course_creator' => ['Course Creator', 'Draft and edit assigned training courses', ['courses.create', 'directory.view']],
        'course_approver' => ['Course Approver', 'Review and publish courses', ['courses.create', 'courses.approve', 'directory.view']],
        'checkin_volunteer' => ['Check-in Volunteer', 'Checks children in and out at services', ['checkin.operate', 'directory.view']],
        'prayer_team' => ['Prayer Team', 'Prays for and updates prayer requests', ['prayer.team', 'directory.view']],
        'volunteer' => ['Volunteer', 'Own profile, availability, assigned shifts and training', ['directory.view']],
        'member' => ['Member', 'Own profile, event registration, groups and permitted resources', ['directory.view']],
        'visitor' => ['Visitor', 'Public events, information and optional registration', []],
    ];
    foreach (array_merge_recursive(phase2_role_grants(), phase3_role_grants(), phase4_role_grants()) as $slug => $perms) {
        if (isset($roles[$slug])) {
            $roles[$slug][2] = array_values(array_unique(array_merge($roles[$slug][2], $perms)));
        }
    }
    return $roles;
}
