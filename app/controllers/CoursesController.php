<?php
declare(strict_types=1);

/** Church Learning Management System: draft → review → publish, lessons, quizzes, assignment and progress. */
final class CoursesController
{
    private function find(int|string $id): array
    {
        return DB::row('SELECT * FROM {p}courses WHERE id = ?', [(int)$id]) ?? abort(404);
    }

    private function isEditor(array $c): bool
    {
        return (int)$c['created_by'] === Auth::id() || (bool)DB::val('SELECT 1 FROM {p}course_editors WHERE course_id = ? AND user_id = ?', [$c['id'], Auth::id()]);
    }

    private function canEdit(array $c): bool
    {
        if (can('courses.approve')) {
            return $c['status'] !== 'archived';
        }
        return can('courses.create') && $this->isEditor($c) && $c['status'] === 'draft';
    }

    private function authorizeEdit(array $c): void
    {
        if (!$this->canEdit($c)) {
            abort(403, __('This course can only be edited by its assigned editors while it is a draft.'));
        }
    }

    private function canSee(array $c): bool
    {
        return can('courses.approve') || can('courses.assign') || can('training.reports') || (can('courses.create') && $this->isEditor($c));
    }

    public function index(): void
    {
        if (!can('courses.create') && !can('courses.approve') && !can('courses.assign') && !can('training.reports')) {
            redirect('/me/training');
        }
        $status = (string)input('status', '');
        $where = '1=1';
        $params = [];
        if (in_array($status, ['draft', 'review', 'published', 'archived'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }
        if (!can('courses.approve') && !can('courses.assign') && !can('training.reports')) {
            $where .= ' AND (c.created_by = ? OR c.id IN (SELECT course_id FROM {p}course_editors WHERE user_id = ?))';
            array_push($params, Auth::id(), Auth::id());
        }
        $rows = DB::all("SELECT c.*, (SELECT COUNT(*) FROM {p}lessons l WHERE l.course_id = c.id) AS lessons, (SELECT COUNT(*) FROM {p}enrolments e WHERE e.course_id = c.id) AS enrolled, (SELECT COUNT(*) FROM {p}enrolments e WHERE e.course_id = c.id AND e.status = 'completed') AS completed FROM {p}courses c WHERE $where ORDER BY FIELD(c.status, 'review', 'draft', 'published', 'archived'), c.title", $params);
        view('courses/index', ['title' => __('Courses & training'), 'rows' => $rows, 'status' => $status]);
    }

    public function create(): void
    {
        view('courses/create', ['title' => __('New course'), 'courses' => array_column(DB::all("SELECT id, title FROM {p}courses WHERE status = 'published' ORDER BY title"), 'title', 'id')]);
    }

    private function data(): array
    {
        return ['title' => mb_substr((string)input('title', ''), 0, 200), 'summary' => input('summary') ?: null, 'category' => mb_substr((string)input('category', ''), 0, 80) ?: null, 'prerequisite_id' => input_int('prerequisite_id'), 'pass_mark' => max(0, min(100, (int)input_int('pass_mark', 80))), 'certificate_enabled' => input_bool('certificate_enabled'), 'validity_months' => input_int('validity_months') ?: null, 'self_enrol' => input_bool('self_enrol')];
    }

    public function store(): void
    {
        $d = $this->data();
        if ($d['title'] === '') {
            flash('error', __('Title is required.'));
            back('/courses/new');
        }
        $d['created_by'] = Auth::id();
        $id = DB::insert('courses', $d);
        DB::insert('course_editors', ['course_id' => $id, 'user_id' => Auth::id()]);
        audit('course.created', 'course', $id);
        flash('success', __('Draft course created. Add lessons, then submit it for review.'));
        redirect('/courses/' . $id);
    }

    public function show(string $id): void
    {
        $c = $this->find($id);
        if (!$this->canSee($c)) {
            abort(403);
        }
        $lessons = DB::all('SELECT l.*, f.original_name FROM {p}lessons l LEFT JOIN {p}files f ON f.id = l.file_id WHERE l.course_id = ? ORDER BY l.sort, l.id', [$c['id']]);
        $questions = DB::all('SELECT * FROM {p}quiz_questions WHERE course_id = ? ORDER BY sort, id', [$c['id']]);
        $reviews = DB::all('SELECT r.*, p.first_name, p.last_name, p.preferred_name FROM {p}course_reviews r LEFT JOIN {p}users u ON u.id = r.reviewer_id LEFT JOIN {p}people p ON p.id = u.person_id WHERE r.course_id = ? ORDER BY r.id DESC', [$c['id']]);
        $editors = DB::all('SELECT u.id, u.email, p.first_name, p.last_name, p.preferred_name FROM {p}course_editors ce JOIN {p}users u ON u.id = ce.user_id LEFT JOIN {p}people p ON p.id = u.person_id WHERE ce.course_id = ?', [$c['id']]);
        $creators = [];
        foreach (DB::all("SELECT DISTINCT u.id, u.email, p.first_name, p.last_name, p.preferred_name FROM {p}users u LEFT JOIN {p}people p ON p.id = u.person_id JOIN {p}user_roles ur ON ur.user_id = u.id JOIN {p}role_permissions rp ON rp.role_id = ur.role_id WHERE rp.permission = 'courses.create' AND u.status = 'active'") as $u) {
            $creators[$u['id']] = person_name($u) ?: $u['email'];
        }
        $stats = DB::row("SELECT COUNT(*) AS enrolled, SUM(status = 'completed') AS completed, SUM(status IN ('assigned','in_progress')) AS active FROM {p}enrolments WHERE course_id = ?", [$c['id']]);
        $groups = array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name'), 'name', 'id');
        $courses = array_column(DB::all("SELECT id, title FROM {p}courses WHERE status = 'published' AND id <> ? ORDER BY title", [$c['id']]), 'title', 'id');
        view('courses/show', ['title' => $c['title'], 'c' => $c, 'lessons' => $lessons, 'questions' => $questions, 'reviews' => $reviews, 'editors' => $editors, 'creators' => $creators, 'canEdit' => $this->canEdit($c), 'stats' => $stats, 'groups' => $groups, 'courses' => $courses]);
    }

    public function update(string $id): void
    {
        $c = $this->find($id);
        if (input('action') === 'unpublish' && can('courses.approve')) {
            DB::update('courses', ['status' => 'draft'], 'id = ?', [$c['id']]);
            audit('course.unpublished', 'course', (int)$c['id']);
            flash('success', __('Course returned to draft for editing. Learners cannot open it until it is published again.'));
            redirect('/courses/' . $c['id']);
        }
        if (input('action') === 'archive' && can('courses.approve')) {
            DB::update('courses', ['status' => 'archived'], 'id = ?', [$c['id']]);
            audit('course.archived', 'course', (int)$c['id']);
            redirect('/courses');
        }
        $this->authorizeEdit($c);
        $d = $this->data();
        if ($d['prerequisite_id'] === (int)$c['id']) {
            $d['prerequisite_id'] = null;
        }
        $d['updated_at'] = date('Y-m-d H:i:s');
        DB::update('courses', $d, 'id = ?', [$c['id']]);
        audit('course.updated', 'course', (int)$c['id']);
        flash('success', __('Course details saved.'));
        redirect('/courses/' . $c['id']);
    }

    private function storeUpload(string $field): ?int
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (Demo::on()) {
            flash('warning', __('File uploads are switched off on the demo site. Link a video instead.'));
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) {
            flash('error', __('Upload failed. Files must be under 20 MB.'));
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx'];
        if (!isset($allowed[$mime])) {
            flash('error', __('Only PDF, Word, PowerPoint, PNG and JPEG files can be uploaded.'));
            return null;
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/uploads/' . $stored)) {
            flash('error', __('Could not save the file. Check that storage/uploads is writable.'));
            return null;
        }
        return DB::insert('files', ['original_name' => mb_substr(basename((string)$f['name']), 0, 255), 'stored_name' => $stored, 'mime' => $mime, 'size' => (int)$f['size'], 'uploaded_by' => Auth::id()]);
    }

    private function lessonData(): array
    {
        $t = (string)input('lesson_type', 'text');
        $url = trim((string)input('video_url', ''));
        return ['title' => mb_substr((string)input('title', ''), 0, 200), 'lesson_type' => in_array($t, ['text', 'video', 'pdf', 'scripture'], true) ? $t : 'text', 'body' => input('body') ?: null, 'video_url' => preg_match('#^https?://#', $url) ? mb_substr($url, 0, 500) : null, 'sort' => (int)input_int('sort', 0)];
    }

    public function addLesson(string $id): void
    {
        $c = $this->find($id);
        $this->authorizeEdit($c);
        $d = $this->lessonData() + ['course_id' => $c['id']];
        if ($d['title'] === '') {
            back('/courses/' . $c['id']);
        }
        if (!$d['sort']) {
            $d['sort'] = (int)DB::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM {p}lessons WHERE course_id = ?', [$c['id']]);
        }
        $d['file_id'] = $this->storeUpload('file');
        DB::insert('lessons', $d);
        redirect('/courses/' . $c['id'] . '#lessons');
    }

    public function updateLesson(string $id): void
    {
        $l = DB::row('SELECT * FROM {p}lessons WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->authorizeEdit($this->find($l['course_id']));
        $d = $this->lessonData();
        if ($fid = $this->storeUpload('file')) {
            $d['file_id'] = $fid;
        }
        DB::update('lessons', $d, 'id = ?', [$l['id']]);
        redirect('/courses/' . $l['course_id'] . '#lessons');
    }

    public function deleteLesson(string $id): void
    {
        $l = DB::row('SELECT * FROM {p}lessons WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->authorizeEdit($this->find($l['course_id']));
        DB::run('DELETE FROM {p}lesson_progress WHERE lesson_id = ?', [$l['id']]);
        DB::run('DELETE FROM {p}lessons WHERE id = ?', [$l['id']]);
        redirect('/courses/' . $l['course_id'] . '#lessons');
    }

    public function addQuestion(string $id): void
    {
        $c = $this->find($id);
        $this->authorizeEdit($c);
        $opts = array_values(array_filter(array_map('trim', explode("\n", (string)input('options', ''))), 'strlen'));
        $correct = (int)input_int('correct', 1) - 1;
        if (trim((string)input('question', '')) === '' || count($opts) < 2 || !isset($opts[$correct])) {
            flash('error', __('Enter a question, at least two options (one per line) and the number of the correct option.'));
            redirect('/courses/' . $c['id'] . '#quiz');
        }
        DB::insert('quiz_questions', ['course_id' => $c['id'], 'question' => (string)input('question'), 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE), 'correct_index' => $correct, 'sort' => (int)DB::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM {p}quiz_questions WHERE course_id = ?', [$c['id']])]);
        redirect('/courses/' . $c['id'] . '#quiz');
    }

    public function deleteQuestion(string $id): void
    {
        $q = DB::row('SELECT * FROM {p}quiz_questions WHERE id = ?', [(int)$id]) ?? abort(404);
        $this->authorizeEdit($this->find($q['course_id']));
        DB::run('DELETE FROM {p}quiz_questions WHERE id = ?', [$q['id']]);
        redirect('/courses/' . $q['course_id'] . '#quiz');
    }

    public function editors(string $id): void
    {
        $c = $this->find($id);
        if (!can('courses.approve')) {
            abort(403);
        }
        DB::run('DELETE FROM {p}course_editors WHERE course_id = ?', [$c['id']]);
        foreach (input_ids('user_ids') as $uid) {
            DB::insert('course_editors', ['course_id' => $c['id'], 'user_id' => $uid]);
        }
        audit('course.editors_changed', 'course', (int)$c['id']);
        redirect('/courses/' . $c['id']);
    }

    public function submit(string $id): void
    {
        $c = $this->find($id);
        $this->authorizeEdit($c);
        if (!DB::val('SELECT COUNT(*) FROM {p}lessons WHERE course_id = ?', [$c['id']])) {
            flash('error', __('Add at least one lesson before submitting.'));
            redirect('/courses/' . $c['id']);
        }
        DB::update('courses', ['status' => 'review'], 'id = ?', [$c['id']]);
        DB::insert('course_reviews', ['course_id' => $c['id'], 'reviewer_id' => Auth::id(), 'decision' => 'submitted', 'comment' => input('comment') ?: null]);
        Notify::users(array_diff(users_with_permission('courses.approve'), [Auth::id()]), __('Course ready for review: %s', $c['title']), '<p>' . e(__('A training course has been submitted for review and approval.')) . '</p>', url('/courses/' . $c['id']));
        audit('course.submitted', 'course', (int)$c['id']);
        flash('success', __('Submitted for review. A course approver has been notified.'));
        redirect('/courses/' . $c['id']);
    }

    public function review(string $id): void
    {
        $c = $this->find($id);
        $approve = input('decision') === 'approved';
        $othersCanApprove = count(array_diff(users_with_permission('courses.approve'), [Auth::id()])) > 0;
        if ($approve && (int)$c['created_by'] === Auth::id() && $othersCanApprove && !can('admin.settings')) {
            flash('error', __('Someone other than the author must approve this course.'));
            redirect('/courses/' . $c['id']);
        }
        DB::insert('course_reviews', ['course_id' => $c['id'], 'reviewer_id' => Auth::id(), 'decision' => $approve ? 'approved' : 'changes_requested', 'comment' => input('comment') ?: null]);
        DB::update('courses', $approve ? ['status' => 'published', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')] : ['status' => 'draft'], 'id = ?', [$c['id']]);
        $editorIds = array_map('intval', DB::col('SELECT user_id FROM {p}course_editors WHERE course_id = ?', [$c['id']]));
        Notify::users(array_diff(array_merge($editorIds, [(int)$c['created_by']]), [Auth::id()]), $approve ? __('Course published: %s', $c['title']) : __('Changes requested: %s', $c['title']), '<p>' . e((string)input('comment', '')) . '</p>', url('/courses/' . $c['id']));
        audit($approve ? 'course.published' : 'course.changes_requested', 'course', (int)$c['id']);
        flash('success', $approve ? __('Course published.') : __('Returned to the author with your comments.'));
        redirect('/courses/' . $c['id']);
    }

    public function assign(string $id): void
    {
        $c = $this->find($id);
        if ($c['status'] !== 'published') {
            flash('error', __('Only published courses can be assigned.'));
            redirect('/courses/' . $c['id']);
        }
        $people = input_ids('person_ids');
        if ($gid = input_int('group_id')) {
            $people = array_merge($people, array_map('intval', DB::col('SELECT person_id FROM {p}group_members WHERE group_id = ?', [$gid])));
        }
        $due = input_date('due_date');
        $n = 0;
        foreach (array_unique($people) as $pid) {
            $existing = DB::row('SELECT * FROM {p}enrolments WHERE course_id = ? AND person_id = ?', [$c['id'], $pid]);
            if ($existing && $existing['status'] === 'completed' && (!$existing['expires_at'] || $existing['expires_at'] >= date('Y-m-d')) && !input_bool('reassign')) {
                continue;
            }
            if ($existing) {
                DB::update('enrolments', ['status' => 'assigned', 'due_date' => $due, 'assigned_by' => Auth::id(), 'completed_at' => null, 'score' => null], 'id = ?', [$existing['id']]);
                DB::run('DELETE FROM {p}lesson_progress WHERE enrolment_id = ?', [$existing['id']]);
            } else {
                DB::insert('enrolments', ['course_id' => $c['id'], 'person_id' => $pid, 'assigned_by' => Auth::id(), 'due_date' => $due]);
            }
            Notify::person($pid, __('Training assigned: %s', $c['title']), '<p>' . e(__('You have been asked to complete "%s"%s.', $c['title'], $due ? ' ' . __('by %s', fmt_date($due)) : '')) . '</p><p><a href="' . e(url('/me/training/' . $c['id'], [], true)) . '">' . e(__('Start the course')) . '</a></p>', null, url('/me/training/' . $c['id']));
            $n++;
        }
        Notify::processQueue(20);
        audit('course.assigned', 'course', (int)$c['id'], ['count' => $n]);
        flash('success', __('Assigned to %d people.', $n));
        redirect('/courses/' . $c['id']);
    }

    public function progress(string $id): void
    {
        $c = $this->find($id);
        if (!can('training.reports') && !can('courses.approve') && !can('courses.assign')) {
            abort(403);
        }
        $rows = DB::all('SELECT e.*, p.first_name, p.last_name, p.preferred_name, (SELECT COUNT(*) FROM {p}lesson_progress lp WHERE lp.enrolment_id = e.id) AS done FROM {p}enrolments e JOIN {p}people p ON p.id = e.person_id WHERE e.course_id = ? ORDER BY e.status, p.last_name', [$c['id']]);
        if (input('export')) {
            csv_download('course-' . $c['id'] . '-progress.csv', ['name', 'status', 'lessons_done', 'score', 'due', 'completed', 'expires', 'certificate'], array_map(fn($r) => [person_name($r), $r['status'], $r['done'], $r['score'], $r['due_date'], $r['completed_at'], $r['expires_at'], $r['certificate_code']], $rows));
        }
        $lessons = (int)DB::val('SELECT COUNT(*) FROM {p}lessons WHERE course_id = ?', [$c['id']]);
        view('courses/progress', ['title' => __('Progress: %s', $c['title']), 'c' => $c, 'rows' => $rows, 'lessons' => $lessons]);
    }
}
