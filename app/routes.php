<?php
declare(strict_types=1);

/** @var Router $router */
$pub = ['public' => true];

// Authentication -------------------------------------------------------------
$router->get('/', ['DashboardController', 'index']);
$router->get('/login', ['AuthController', 'loginForm'], $pub);
$router->post('/login', ['AuthController', 'login'], $pub);
$router->get('/login/mfa', ['AuthController', 'mfaForm'], $pub);
$router->post('/login/mfa', ['AuthController', 'mfaVerify'], $pub);
$router->post('/logout', ['AuthController', 'logout'], $pub);
$router->post('/demo/login', ['AuthController', 'demoLogin'], $pub);
$router->get('/password/forgot', ['AuthController', 'forgotForm'], $pub);
$router->post('/password/forgot', ['AuthController', 'forgot'], $pub);
$router->get('/password/reset', ['AuthController', 'resetForm'], $pub);
$router->post('/password/reset', ['AuthController', 'reset'], $pub);

// Public pages (embed links on the church website) ---------------------------
$router->get('/public/events', ['PublicController', 'events'], $pub);
$router->get('/public/events/{id}', ['PublicController', 'event'], $pub);
$router->post('/public/events/{id}/register', ['PublicController', 'register'], $pub);
$router->get('/connect', ['PublicController', 'connectForm'], $pub);
$router->post('/connect', ['PublicController', 'connect'], $pub);
$router->get('/f/{id}', ['FormsController', 'show'], $pub);
$router->post('/f/{id}', ['FormsController', 'submit'], $pub);
$router->get('/f/{id}/done', ['FormsController', 'done'], $pub);
$router->get('/campaign/{id}', ['CampaignsController', 'publicPage'], $pub);
$router->post('/campaign/{id}/pledge', ['CampaignsController', 'publicPledge'], $pub);
$router->get('/give', ['GivingController', 'giveForm'], $pub);
$router->post('/give', ['GivingController', 'give'], $pub);
$router->get('/give/thanks', ['GivingController', 'thanks'], $pub);
$router->post('/stripe/webhook', ['GivingController', 'webhook'], ['public' => true, 'no_csrf' => true]);
$router->get('/ical/{token}', ['PublicController', 'ical'], $pub);
$router->get('/verify/{code}', ['PublicController', 'verifyCertificate'], $pub);
$router->get('/privacy', ['PublicController', 'privacy'], $pub);

$router->post('/campus', ['AccountController', 'switchCampus']);
$router->post('/account/language', ['AccountController', 'language']);

// Installable app ------------------------------------------------------------------
$router->get('/manifest', ['AppController', 'manifest'], $pub);
$router->get('/offline', ['AppController', 'offline'], $pub);
$router->get('/notifications/feed', ['AppController', 'feed']);
$router->post('/push/subscribe', ['AppController', 'subscribe']);
$router->post('/push/unsubscribe', ['AppController', 'unsubscribe']);

// Account & notifications ----------------------------------------------------
$router->get('/account', ['AccountController', 'index']);
$router->post('/account/password', ['AccountController', 'password']);
$router->get('/account/mfa', ['AccountController', 'mfa']);
$router->post('/account/mfa/enable', ['AccountController', 'mfaEnable']);
$router->post('/account/mfa/disable', ['AccountController', 'mfaDisable']);
$router->post('/account/ical', ['AccountController', 'icalReset']);
$router->get('/account/calendar/callback', ['AccountController', 'calendarCallback']);
$router->post('/account/calendar/{id}/connect', ['AccountController', 'calendarConnect']);
$router->post('/account/calendar/{id}/sync', ['AccountController', 'calendarSyncNow']);
$router->post('/account/calendar/{id}/disconnect', ['AccountController', 'calendarDisconnect']);
$router->post('/account/calendar/{id}', ['AccountController', 'calendarSave']);
$router->get('/notifications', ['AccountController', 'notifications']);
$router->post('/notifications/read', ['AccountController', 'markRead']);

// My Affreso (member & volunteer self-service) -------------------------------
$router->get('/me/profile', ['MeController', 'profile']);
$router->post('/me/profile', ['MeController', 'saveProfile']);
$router->get('/me/rosters', ['MeController', 'rosters']);
$router->post('/me/rosters/{id}/respond', ['MeController', 'respond']);
$router->post('/me/rosters/{id}/swap', ['MeController', 'swap']);
$router->post('/me/swaps/{id}/take', ['MeController', 'takeSwap']);
$router->get('/me/availability', ['MeController', 'availability']);
$router->post('/me/availability', ['MeController', 'addUnavailable']);
$router->post('/me/availability/{id}/delete', ['MeController', 'deleteUnavailable']);
$router->get('/me/training', ['MeController', 'training']);
$router->post('/me/training/enrol/{id}', ['MeController', 'selfEnrol']);
$router->get('/me/training/{id}', ['MeController', 'course']);
$router->post('/me/training/{id}/lesson/{lesson}', ['MeController', 'completeLesson']);
$router->post('/me/training/{id}/quiz', ['MeController', 'submitQuiz']);
$router->get('/me/certificate/{id}', ['MeController', 'certificate']);
$router->get('/me/groups', ['MeController', 'groups']);
$router->post('/me/groups/{id}/join', ['MeController', 'joinGroup']);
$router->get('/me/events', ['MeController', 'events']);
$router->post('/me/events/{id}/register', ['MeController', 'registerEvent']);
$router->post('/me/events/{id}/cancel', ['MeController', 'cancelEvent']);
$router->get('/me/giving', ['MeController', 'giving']);
$router->get('/me/privacy', ['MeController', 'privacy']);
$router->post('/me/privacy', ['MeController', 'savePrivacy']);
$router->get('/me/export', ['MeController', 'export']);
$router->get('/me/volunteer', ['MeController', 'volunteerForm']);
$router->post('/me/volunteer', ['MeController', 'volunteerApply']);
$router->get('/directory', ['MeController', 'directory'], ['perm' => 'directory.view']);

// Members & families ---------------------------------------------------------
$router->get('/people', ['PeopleController', 'index'], ['perm' => 'people.view']);
$router->get('/people/new', ['PeopleController', 'create'], ['perm' => 'people.edit']);
$router->post('/people', ['PeopleController', 'store'], ['perm' => 'people.edit']);
$router->get('/people/import', ['PeopleController', 'importForm'], ['perm' => 'people.export']);
$router->post('/people/import', ['PeopleController', 'import'], ['perm' => 'people.export']);
$router->post('/people/import/api', ['PeopleController', 'importApi'], ['perm' => 'people.export']);
$router->get('/people/export', ['PeopleController', 'exportCsv'], ['perm' => 'people.export']);
$router->get('/people/{id}', ['PeopleController', 'show'], ['perm' => 'people.view']);
$router->get('/people/{id}/edit', ['PeopleController', 'edit'], ['perm' => 'people.edit']);
$router->post('/people/{id}', ['PeopleController', 'update'], ['perm' => 'people.edit']);
$router->post('/people/{id}/archive', ['PeopleController', 'archive'], ['perm' => 'people.delete']);
$router->post('/people/{id}/restore', ['PeopleController', 'restore'], ['perm' => 'people.delete']);
$router->post('/people/{id}/erase', ['PeopleController', 'erase'], ['perm' => 'people.delete']);
$router->post('/people/{id}/invite', ['PeopleController', 'invite'], ['perm' => 'admin.users']);
$router->post('/people/{id}/notes', ['PeopleController', 'addNote'], ['perm' => 'pastoral.edit']);
$router->post('/people/{id}/consent', ['PeopleController', 'consent'], ['perm' => 'people.edit']);
$router->get('/people/{id}/export', ['PeopleController', 'exportPerson'], ['perm' => 'privacy.manage']);
$router->get('/households', ['PeopleController', 'households'], ['perm' => 'people.view']);
$router->post('/households', ['PeopleController', 'storeHousehold'], ['perm' => 'people.edit']);
$router->get('/households/{id}', ['PeopleController', 'household'], ['perm' => 'people.view']);
$router->post('/households/{id}', ['PeopleController', 'updateHousehold'], ['perm' => 'people.edit']);
$router->get('/care', ['CareController', 'index'], ['perm' => 'care.manage']);
$router->post('/care', ['CareController', 'store'], ['perm' => 'care.manage']);
$router->post('/care/{id}', ['CareController', 'update'], ['perm' => 'care.manage']);

// Children's check-in -------------------------------------------------------------
$ci = ['perm' => 'checkin.operate'];
$router->get('/checkin', ['CheckinController', 'index'], $ci);
$router->post('/checkin/sessions', ['CheckinController', 'storeSession'], $ci);
$router->post('/checkin/classes', ['CheckinController', 'saveClass'], $ci);
$router->get('/checkin/sessions/{id}', ['CheckinController', 'show'], $ci);
$router->post('/checkin/sessions/{id}/in', ['CheckinController', 'checkIn'], $ci);
$router->post('/checkin/sessions/{id}/leaders', ['CheckinController', 'leaderIn'], $ci);
$router->post('/checkin/sessions/{id}/close', ['CheckinController', 'close'], $ci);
$router->get('/checkin/sessions/{id}/room/{class}', ['CheckinController', 'room'], $ci);
$router->get('/checkin/labels', ['CheckinController', 'labels'], $ci);
$ck = ['perm' => 'checkin.kiosk'];
$router->get('/checkin/kiosks', ['KioskController', 'index'], $ck);
$router->post('/checkin/kiosks', ['KioskController', 'store'], $ck);
$router->post('/checkin/kiosks/{id}', ['KioskController', 'update'], $ck);
$router->post('/checkin/kiosks/{id}/pair', ['KioskController', 'pairCode'], $ck);
$router->post('/checkin/kiosks/{id}/revoke', ['KioskController', 'revoke'], $ck);
$router->get('/kiosk', ['KioskController', 'start'], $pub);
$router->get('/kiosk/pair', ['KioskController', 'pairForm'], $pub);
$router->post('/kiosk/pair', ['KioskController', 'pair'], $pub);
$router->post('/kiosk/find', ['KioskController', 'find'], $pub);
$router->get('/kiosk/family', ['KioskController', 'familyForm'], $pub);
$router->post('/kiosk/checkin', ['KioskController', 'checkIn'], $pub);
$router->get('/kiosk/done', ['KioskController', 'done'], $pub);
$router->get('/kiosk/exit', ['KioskController', 'exitForm'], $pub);
$router->post('/kiosk/exit', ['KioskController', 'exit'], $pub);
$router->post('/me/children/precheck', ['CheckinController', 'precheck']);
$router->post('/me/children/precheck/cancel', ['CheckinController', 'precheckCancel']);
$router->get('/checkin/labels/pdf', ['CheckinController', 'labelsPdf'], ['perm' => 'checkin.operate']);
$router->post('/checkin/sessions/{id}/arrive', ['CheckinController', 'arriveDesk'], ['perm' => 'checkin.operate']);
$router->post('/checkin/out/{id}', ['CheckinController', 'checkOut'], $ci);
$router->post('/people/{id}/child-profile', ['CheckinController', 'saveChildProfile']);
$router->post('/people/{id}/collectors', ['CheckinController', 'addCollector']);
$router->post('/collectors/{id}/delete', ['CheckinController', 'deleteCollector']);
$router->get('/me/children', ['CheckinController', 'myChildren']);

// Incidents ----------------------------------------------------------------------
$router->get('/incidents', ['IncidentsController', 'index']);
$router->get('/incidents/new', ['IncidentsController', 'create']);
$router->post('/incidents', ['IncidentsController', 'store']);
$router->get('/incidents/{id}', ['IncidentsController', 'show']);
$router->post('/incidents/{id}', ['IncidentsController', 'update']);

// Prayer -------------------------------------------------------------------------
$router->get('/prayer', ['PrayerController', 'index']);
$router->post('/prayer', ['PrayerController', 'store']);
$router->post('/prayer/{id}/prayed', ['PrayerController', 'prayed']);
$router->post('/prayer/{id}', ['PrayerController', 'update']);
$router->get('/pray', ['PrayerController', 'publicForm'], $pub);
$router->post('/pray', ['PrayerController', 'publicStore'], $pub);

// Resource library -----------------------------------------------------------------
$lm = ['perm' => 'library.manage'];
$router->get('/library', ['LibraryController', 'index']);
$router->get('/library/new', ['LibraryController', 'create'], $lm);
$router->post('/library', ['LibraryController', 'store'], $lm);
$router->get('/library/ack/{id}', ['LibraryController', 'ackRecord']);
$router->get('/library/file/{id}', ['LibraryController', 'file']);
$router->get('/library/{id}', ['LibraryController', 'show']);
$router->post('/library/{id}', ['LibraryController', 'update'], $lm);
$router->post('/library/{id}/version', ['LibraryController', 'newVersion'], $lm);
$router->post('/library/{id}/remind', ['LibraryController', 'requestSignatures'], $lm);
$router->post('/library/{id}/sign', ['LibraryController', 'sign']);

// Service planning -----------------------------------------------------------------
$sp = ['perm' => 'services.plan'];
$router->get('/services', ['ServicesController', 'index']);
$router->post('/services', ['ServicesController', 'store'], $sp);
$router->get('/services/songs', ['ServicesController', 'songs'], $sp);
$router->post('/services/songs', ['ServicesController', 'saveSong'], $sp);
$router->get('/services/ccli', ['ServicesController', 'ccli'], $sp);
$router->get('/services/{id}', ['ServicesController', 'show']);
$router->post('/services/{id}', ['ServicesController', 'update'], $sp);
$router->post('/services/{id}/items', ['ServicesController', 'addItem'], $sp);
$router->post('/services/{id}/copy', ['ServicesController', 'copy'], $sp);
$router->post('/plan-items/{id}', ['ServicesController', 'updateItem'], $sp);

// Reimbursements -------------------------------------------------------------------
$router->get('/reimbursements', ['ReimbursementsController', 'index']);
$router->post('/reimbursements', ['ReimbursementsController', 'store']);
$router->post('/reimbursements/pay', ['ReimbursementsController', 'pay'], ['perm' => 'reimbursements.pay']);
$router->post('/reimbursements/export-selected', ['ReimbursementsController', 'exportSelected'], ['perm' => 'reimbursements.pay']);
$router->post('/reimbursements/{id}/decide', ['ReimbursementsController', 'decide']);
$router->get('/reimbursements/{id}/receipt', ['ReimbursementsController', 'receipt']);

// Ministries & groups --------------------------------------------------------
$router->get('/groups', ['GroupsController', 'index']);
$router->get('/groups/new', ['GroupsController', 'create'], ['perm' => 'groups.manage']);
$router->post('/groups', ['GroupsController', 'store'], ['perm' => 'groups.manage']);
$router->get('/groups/{id}', ['GroupsController', 'show']);
$router->post('/groups/{id}', ['GroupsController', 'update']);
$router->post('/groups/{id}/members', ['GroupsController', 'addMember']);
$router->post('/groups/{id}/members/{pid}/remove', ['GroupsController', 'removeMember']);
$router->post('/groups/{id}/meetings', ['GroupsController', 'addMeeting']);
$router->get('/groups/{id}/meetings/{mid}', ['GroupsController', 'meeting']);
$router->post('/groups/{id}/meetings/{mid}', ['GroupsController', 'saveAttendance']);
$router->post('/groups/{id}/positions', ['GroupsController', 'addPosition']);
$router->post('/positions/{id}', ['GroupsController', 'updatePosition']);
$router->post('/positions/{id}/delete', ['GroupsController', 'deletePosition']);

// Volunteers & safeguarding --------------------------------------------------
$router->get('/volunteers', ['VolunteersController', 'index']);
$router->get('/volunteers/applications/{id}', ['VolunteersController', 'application']);
$router->post('/volunteers/applications/{id}', ['VolunteersController', 'decide']);
$router->post('/volunteers/positions', ['VolunteersController', 'approvePosition']);
$router->post('/volunteers/positions/remove', ['VolunteersController', 'removePosition']);
$router->get('/safeguarding', ['SafeguardingController', 'index'], ['perm' => 'safeguarding.view']);
$router->post('/safeguarding', ['SafeguardingController', 'store'], ['perm' => 'safeguarding.manage']);
$router->post('/safeguarding/{id}', ['SafeguardingController', 'update'], ['perm' => 'safeguarding.manage']);

// Rosters ----------------------------------------------------------------------
$router->get('/rosters', ['RostersController', 'index']);
$router->get('/rosters/new', ['RostersController', 'create']);
$router->post('/rosters', ['RostersController', 'store']);
$router->get('/rosters/swaps', ['RostersController', 'swaps']);
$router->post('/rosters/swaps/{id}', ['RostersController', 'decideSwap']);
$router->get('/rosters/{id}', ['RostersController', 'show']);
$router->post('/rosters/{id}', ['RostersController', 'update']);
$router->post('/rosters/{id}/slots', ['RostersController', 'addSlots']);
$router->post('/rosters/{id}/assign', ['RostersController', 'assign']);
$router->post('/rosters/{id}/autofill', ['RostersController', 'autofill']);
$router->post('/rosters/{id}/publish', ['RostersController', 'publish']);
$router->post('/rosters/{id}/copy', ['RostersController', 'copy']);
$router->post('/rosters/{id}/delete', ['RostersController', 'delete']);
$router->post('/rosters/assignments/{id}/clear', ['RostersController', 'clearAssignment']);
$router->post('/rosters/assignments/{id}/remove', ['RostersController', 'removeSlot']);

// Events, rooms & bookings ---------------------------------------------------
$fm = ['perm' => 'forms.manage'];
$router->get('/me/forms', ['FormsController', 'mine']);
$router->get('/forms', ['FormsController', 'index'], $fm);
$router->post('/forms', ['FormsController', 'store'], $fm);
$router->get('/forms/{id}/edit', ['FormsController', 'edit'], $fm);
$router->post('/forms/{id}', ['FormsController', 'update'], $fm);
$router->post('/forms/{id}/fields', ['FormsController', 'addField'], $fm);
$router->post('/forms/{id}/fields/{key}', ['FormsController', 'updateField'], $fm);
$router->post('/forms/{id}/fields/{key}/move', ['FormsController', 'moveField'], $fm);
$router->post('/forms/{id}/fields/{key}/delete', ['FormsController', 'deleteField'], $fm);
$router->post('/forms/{id}/duplicate', ['FormsController', 'duplicate'], $fm);
$router->post('/forms/{id}/delete', ['FormsController', 'delete'], $fm);
$router->get('/forms/{id}/responses', ['FormsController', 'responses'], $fm);
$router->post('/forms/{id}/responses/delete', ['FormsController', 'deleteAllResponses'], $fm);
$router->get('/forms/{id}/responses/{sid}', ['FormsController', 'response'], $fm);
$router->post('/forms/{id}/responses/{sid}', ['FormsController', 'updateResponse'], $fm);
$router->post('/forms/{id}/responses/{sid}/delete', ['FormsController', 'deleteResponse'], $fm);
$router->post('/forms/{id}/responses/{sid}/payment', ['FormsController', 'recordPayment'], $fm);
$router->post('/forms/{id}/responses/{sid}/refund', ['FormsController', 'refund'], $fm);
$router->post('/forms/{id}/responses/{sid}/stop-instalments', ['FormsController', 'stopInstalments'], $fm);
$router->post('/forms/{id}/responses/{sid}/profile', ['FormsController', 'applyProfile'], $fm);
$router->get('/attendance', ['AttendanceController', 'index'], ['perm' => 'attendance.record']);
$router->post('/attendance', ['AttendanceController', 'store'], ['perm' => 'attendance.record']);
$router->get('/attendance/report', ['AttendanceController', 'report']);
$router->post('/attendance/{id}', ['AttendanceController', 'update'], ['perm' => 'attendance.record']);
$router->post('/attendance/{id}/delete', ['AttendanceController', 'delete'], ['perm' => 'attendance.record']);
$router->get('/events', ['EventsController', 'index']);
$router->get('/events/new', ['EventsController', 'create'], ['perm' => 'events.manage']);
$router->post('/events', ['EventsController', 'store'], ['perm' => 'events.manage']);
$router->get('/events/{id}', ['EventsController', 'show']);
$router->get('/events/{id}/edit', ['EventsController', 'edit'], ['perm' => 'events.manage']);
$router->post('/events/{id}', ['EventsController', 'update'], ['perm' => 'events.manage']);
$router->post('/events/{id}/remove-date', ['EventsController', 'removeOccurrence'], ['perm' => 'events.manage']);
$router->post('/events/{id}/registrations', ['EventsController', 'addRegistration'], ['perm' => 'events.manage']);
$router->post('/events/registrations/{id}', ['EventsController', 'updateRegistration'], ['perm' => 'events.manage']);
$router->get('/events/{id}/export', ['EventsController', 'export'], ['perm' => 'events.manage']);
$router->get('/rooms', ['RoomsController', 'index']);
$router->post('/rooms', ['RoomsController', 'store'], ['perm' => 'rooms.manage']);
$router->post('/rooms/{id}', ['RoomsController', 'update'], ['perm' => 'rooms.manage']);
$router->post('/bookings', ['RoomsController', 'book']);
$router->post('/bookings/{id}', ['RoomsController', 'decide']);

// Courses & training ---------------------------------------------------------
$router->get('/courses', ['CoursesController', 'index']);
$router->get('/courses/new', ['CoursesController', 'create'], ['perm' => 'courses.create']);
$router->post('/courses', ['CoursesController', 'store'], ['perm' => 'courses.create']);
$router->get('/courses/{id}', ['CoursesController', 'show']);
$router->post('/courses/{id}', ['CoursesController', 'update']);
$router->post('/courses/{id}/lessons', ['CoursesController', 'addLesson']);
$router->post('/lessons/{id}', ['CoursesController', 'updateLesson']);
$router->post('/lessons/{id}/delete', ['CoursesController', 'deleteLesson']);
$router->post('/courses/{id}/questions', ['CoursesController', 'addQuestion']);
$router->post('/questions/{id}/delete', ['CoursesController', 'deleteQuestion']);
$router->post('/courses/{id}/editors', ['CoursesController', 'editors']);
$router->post('/courses/{id}/submit', ['CoursesController', 'submit']);
$router->post('/courses/{id}/review', ['CoursesController', 'review'], ['perm' => 'courses.approve']);
$router->post('/courses/{id}/assign', ['CoursesController', 'assign'], ['perm' => 'courses.assign']);
$router->get('/courses/{id}/progress', ['CoursesController', 'progress']);
$router->get('/files/{id}', ['FilesController', 'show']);

// Communications ---------------------------------------------------------------
$router->get('/comms', ['CommsController', 'index'], ['perm' => 'comms.send']);
$router->get('/comms/new', ['CommsController', 'create'], ['perm' => 'comms.send']);
$router->post('/comms', ['CommsController', 'send'], ['perm' => 'comms.send']);
$router->get('/comms/{id}', ['CommsController', 'show'], ['perm' => 'comms.send']);
$router->get('/announcements', ['CommsController', 'announcements']);
$router->post('/announcements', ['CommsController', 'storeAnnouncement'], ['perm' => 'announcements.manage']);
$router->post('/announcements/{id}/delete', ['CommsController', 'deleteAnnouncement'], ['perm' => 'announcements.manage']);

// Giving -----------------------------------------------------------------------
$cm = ['perm' => 'campaigns.manage'];
$router->get('/campaigns', ['CampaignsController', 'index'], $cm);
$router->post('/campaigns', ['CampaignsController', 'store'], $cm);
$router->get('/campaigns/{id}', ['CampaignsController', 'show'], $cm);
$router->post('/campaigns/{id}', ['CampaignsController', 'update'], $cm);
$router->post('/campaigns/{id}/pledges', ['CampaignsController', 'addPledge'], $cm);
$router->post('/campaigns/{id}/pledges/{pid}', ['CampaignsController', 'updatePledge'], $cm);
$router->post('/campaigns/{id}/remind', ['CampaignsController', 'remind'], $cm);
$router->get('/giving', ['GivingController', 'index'], ['perm' => 'giving.view']);
$router->post('/giving', ['GivingController', 'store'], ['perm' => 'giving.manage']);
$router->get('/giving/export', ['GivingController', 'export'], ['perm' => 'giving.view']);
$router->get('/giving/statements', ['GivingController', 'statements'], ['perm' => 'giving.view']);
$router->post('/giving/statements/email', ['GivingController', 'emailStatements'], ['perm' => 'giving.manage']);
$router->get('/giving/statement/{id}', ['GivingController', 'statement']);
$router->get('/giving/import', ['GivingController', 'importForm'], ['perm' => 'giving.manage']);
$router->post('/giving/import', ['GivingController', 'importUpload'], ['perm' => 'giving.manage']);
$router->post('/giving/import/confirm', ['GivingController', 'importConfirm'], ['perm' => 'giving.manage']);
$router->post('/giving/recurring/{id}/cancel', ['GivingController', 'cancelRecurring']);
$router->post('/giving/{id}/receipt', ['GivingController', 'sendReceipt'], ['perm' => 'giving.manage']);
$router->get('/giving/{id}/refund', ['GivingController', 'refundForm'], ['perm' => 'giving.manage']);
$router->post('/giving/{id}/refund', ['GivingController', 'refund'], ['perm' => 'giving.manage']);

// Reports ----------------------------------------------------------------------
$router->get('/reports', ['ReportsController', 'index']);
$router->get('/reports/{name}', ['ReportsController', 'show']);

// Administration -------------------------------------------------------------
$router->get('/admin', ['AdminController', 'index']);
$router->get('/admin/users', ['AdminController', 'users'], ['perm' => 'admin.users']);
$router->post('/admin/users', ['AdminController', 'storeUser'], ['perm' => 'admin.users']);
$router->get('/admin/users/{id}', ['AdminController', 'user'], ['perm' => 'admin.users']);
$router->post('/admin/users/{id}', ['AdminController', 'updateUser'], ['perm' => 'admin.users']);
$router->post('/admin/users/{id}/reset-mfa', ['AdminController', 'resetMfa'], ['perm' => 'admin.users']);
$router->post('/admin/users/{id}/send-reset', ['AdminController', 'sendReset'], ['perm' => 'admin.users']);
$router->get('/admin/roles', ['AdminController', 'roles'], ['perm' => 'admin.roles']);
$router->post('/admin/roles', ['AdminController', 'storeRole'], ['perm' => 'admin.roles']);
$router->post('/admin/roles/{id}', ['AdminController', 'updateRole'], ['perm' => 'admin.roles']);
$router->get('/admin/settings', ['AdminController', 'settings'], ['perm' => 'admin.settings']);
$router->post('/admin/settings', ['AdminController', 'saveSettings'], ['perm' => 'admin.settings']);
$router->post('/admin/settings/test-email', ['AdminController', 'testEmail'], ['perm' => 'admin.settings']);
$router->post('/admin/settings/test-sms', ['AdminController', 'testSms'], ['perm' => 'admin.settings']);
$router->post('/admin/fields', ['AdminController', 'storeField'], ['perm' => 'admin.settings']);
$router->post('/admin/fields/{id}/delete', ['AdminController', 'deleteField'], ['perm' => 'admin.settings']);
$router->post('/admin/campuses', ['AdminController', 'storeCampus'], ['perm' => 'admin.settings']);
$router->post('/admin/funds', ['AdminController', 'storeFund'], ['perm' => 'admin.settings']);
$router->get('/admin/audit', ['AdminController', 'audit'], ['perm' => 'audit.view']);
$router->post('/admin/demo/reset', ['AdminController', 'resetDemo'], ['perm' => 'admin.settings']);
$router->post('/admin/label-font', ['AdminController', 'labelFont'], ['perm' => 'admin.settings']);
$router->get('/admin/test-label', ['AdminController', 'testLabel'], ['perm' => 'admin.settings']);
$router->post('/admin/test-label', ['AdminController', 'testLabel'], ['perm' => 'admin.settings']);
$router->post('/admin/mailchimp/sync', ['AdminController', 'mailchimpSync'], ['perm' => 'admin.settings']);
$router->post('/admin/jobs/run', ['AdminController', 'runJobs'], ['perm' => 'admin.settings']);
$router->get('/admin/privacy', ['PrivacyController', 'index'], ['perm' => 'privacy.manage']);
$router->post('/admin/privacy/requests', ['PrivacyController', 'storeRequest'], ['perm' => 'privacy.manage']);
$router->post('/admin/privacy/requests/{id}', ['PrivacyController', 'updateRequest'], ['perm' => 'privacy.manage']);
$router->post('/admin/breaches', ['PrivacyController', 'storeBreach'], ['perm' => 'privacy.manage']);
$router->get('/admin/breaches/{id}', ['PrivacyController', 'breach'], ['perm' => 'privacy.manage']);
$router->post('/admin/breaches/{id}', ['PrivacyController', 'updateBreach'], ['perm' => 'privacy.manage']);
$router->post('/admin/retention/run', ['PrivacyController', 'runRetention'], ['perm' => 'privacy.manage']);
