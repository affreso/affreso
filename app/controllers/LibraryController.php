<?php
declare(strict_types=1);

/**
 * Church resource library: handbooks, policies, study materials and procedures, with version
 * history, permission-controlled access, and policy acknowledgements with electronic signatures.
 */
final class LibraryController
{
    public static function visibilities(): array
    {
        return ['everyone' => __('Everyone with an account'), 'members' => __('Members and regular attenders'), 'volunteers' => __('Approved volunteers'), 'staff' => __('Staff and leaders'), 'groups' => __('Specific groups or teams')];
    }

    /** Can the current user open this document? */
    public static function canView(array $d): bool
    {
        if (can('library.manage')) {
            return true;
        }
        if ((int)$d['archived']) {
            return false;
        }
        $pid = person_id();
        if (DB::val('SELECT 1 FROM {p}position_documents pd JOIN {p}volunteer_positions vp ON vp.position_id = pd.position_id WHERE pd.document_id = ? AND vp.person_id = ?', [$d['id'], $pid ?? 0])) {
            return true;
        }
        return match ($d['visibility']) {
            'everyone' => true,
            'members' => $pid && in_array(DB::val('SELECT membership_status FROM {p}people WHERE id = ?', [$pid]), ['member', 'regular'], true),
            'volunteers' => $pid && (bool)DB::val('SELECT 1 FROM {p}volunteer_positions WHERE person_id = ? AND approved_at IS NOT NULL', [$pid]),
            'staff' => can('people.view') || can('rosters.manage') || (bool)led_group_ids(),
            'groups' => $pid && (bool)array_intersect(array_map('intval', explode(',', (string)$d['group_ids'])), array_map('intval', DB::col('SELECT group_id FROM {p}group_members WHERE person_id = ?', [$pid]))),
            default => false,
        };
    }

    /** The person's acknowledgement of the document's current version, if still valid. */
    public static function validAck(array $d, int $personId): ?array
    {
        $a = DB::row('SELECT * FROM {p}acknowledgements WHERE document_id = ? AND person_id = ? AND version = ? ORDER BY signed_at DESC LIMIT 1', [$d['id'], $personId, $d['current_version']]);
        if ($a && $d['ack_renew_months'] && strtotime($a['signed_at'] . ' +' . (int)$d['ack_renew_months'] . ' months') < time()) {
            return null;
        }
        return $a;
    }

    /** Documents a person must still sign because of a serving position. */
    public static function outstandingFor(int $personId): array
    {
        $docs = DB::all('SELECT DISTINCT d.* FROM {p}documents d JOIN {p}position_documents pd ON pd.document_id = d.id JOIN {p}volunteer_positions vp ON vp.position_id = pd.position_id WHERE vp.person_id = ? AND d.archived = 0 AND d.requires_ack = 1', [$personId]);
        return array_values(array_filter($docs, fn($d) => !self::validAck($d, $personId)));
    }

    private function find(int|string $id): array
    {
        $d = DB::row('SELECT * FROM {p}documents WHERE id = ?', [(int)$id]) ?? abort(404);
        if (!self::canView($d)) {
            abort(403);
        }
        return $d;
    }

    public function index(): void
    {
        $q = (string)input('q', '');
        $cat = (string)input('category', '');
        $all = DB::all('SELECT d.*, v.created_at AS version_date FROM {p}documents d LEFT JOIN {p}document_versions v ON v.document_id = d.id AND v.version = d.current_version WHERE (d.archived = 0 OR ? = 1) ORDER BY d.category, d.title', [can('library.manage') ? 1 : 0]);
        $pid = person_id();
        $docs = [];
        foreach ($all as $d) {
            if (!self::canView($d)) {
                continue;
            }
            if ($q !== '' && stripos($d['title'] . ' ' . $d['description'] . ' ' . $d['category'], $q) === false) {
                $body = (string)DB::val('SELECT body FROM {p}document_versions WHERE document_id = ? AND version = ?', [$d['id'], $d['current_version']]);
                if (stripos($body, $q) === false) {
                    continue;
                }
            }
            if ($cat !== '' && $d['category'] !== $cat) {
                continue;
            }
            $d['signed'] = (int)$d['requires_ack'] && $pid ? self::validAck($d, $pid) : null;
            $docs[] = $d;
        }
        $categories = DB::col("SELECT DISTINCT category FROM {p}documents WHERE category IS NOT NULL AND category <> '' ORDER BY category");
        $todo = $pid ? self::outstandingFor($pid) : [];
        view('library/index', ['title' => __('Resource library'), 'docs' => $docs, 'q' => $q, 'cat' => $cat, 'categories' => $categories, 'todo' => $todo]);
    }

    public function show(string $id): void
    {
        $d = $this->find($id);
        $versions = DB::all('SELECT v.*, f.original_name FROM {p}document_versions v LEFT JOIN {p}files f ON f.id = v.file_id WHERE v.document_id = ? ORDER BY v.version DESC', [$d['id']]);
        $current = $versions[0] ?? null;
        foreach ($versions as $v) {
            if ((int)$v['version'] === (int)$d['current_version']) {
                $current = $v;
            }
        }
        $ack = person_id() && (int)$d['requires_ack'] ? self::validAck($d, person_id()) : null;
        $signers = can('library.manage') && (int)$d['requires_ack'] ? $this->signers($d) : [];
        $groups = array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name'), 'name', 'id');
        audit('library.viewed', 'document', (int)$d['id']);
        view('library/show', ['title' => $d['title'], 'd' => $d, 'current' => $current, 'versions' => $versions, 'ack' => $ack, 'signers' => $signers, 'groups' => $groups]);
    }

    /** Who should sign: volunteers in positions that require it plus anyone who has signed any version. */
    private function signers(array $d): array
    {
        $ids = array_map('intval', DB::col('SELECT DISTINCT vp.person_id FROM {p}position_documents pd JOIN {p}volunteer_positions vp ON vp.position_id = pd.position_id AND vp.approved_at IS NOT NULL WHERE pd.document_id = ?', [$d['id']]));
        $ids = array_unique(array_merge($ids, array_map('intval', DB::col('SELECT DISTINCT person_id FROM {p}acknowledgements WHERE document_id = ?', [$d['id']]))));
        $out = [];
        foreach ($ids as $pid) {
            $p = DB::row('SELECT id, first_name, last_name, preferred_name FROM {p}people WHERE id = ? AND archived_at IS NULL', [$pid]);
            if ($p) {
                $p['ack'] = self::validAck($d, $pid);
                $p['last'] = DB::row('SELECT version, signed_at FROM {p}acknowledgements WHERE document_id = ? AND person_id = ? ORDER BY signed_at DESC LIMIT 1', [$d['id'], $pid]);
                $out[] = $p;
            }
        }
        usort($out, fn($a, $b) => ($a['ack'] ? 1 : 0) <=> ($b['ack'] ? 1 : 0) ?: strcmp(person_name($a), person_name($b)));
        return $out;
    }

    public function sign(string $id): void
    {
        $d = $this->find($id);
        $pid = person_id() ?? abort(403);
        $v = DB::row('SELECT * FROM {p}document_versions WHERE document_id = ? AND version = ?', [$d['id'], $d['current_version']]) ?? abort(404);
        $name = trim((string)input('signed_name', ''));
        $me = DB::row('SELECT * FROM {p}people WHERE id = ?', [$pid]);
        if (!input_bool('agree') || mb_strlen($name) < 3) {
            flash('error', __('Type your full name and tick the box to sign.'));
            redirect('/library/' . $d['id']);
        }
        $sig = (string)input('signature', '');
        if ($sig !== '' && (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $sig) || strlen($sig) > 300000)) {
            $sig = '';
        }
        $aid = DB::insert('acknowledgements', ['document_id' => $d['id'], 'version' => $d['current_version'], 'person_id' => $pid, 'signed_name' => mb_substr($name, 0, 160), 'signature_png' => $sig ?: null, 'content_hash' => $v['content_hash'], 'ip' => client_ip(), 'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        audit('library.signed', 'document', (int)$d['id'], ['acknowledgement' => $aid, 'version' => $d['current_version'], 'name_matches' => mb_strtolower($name) === mb_strtolower(trim($me['first_name'] . ' ' . $me['last_name']))]);
        Volunteering::onTrainingCompleted($pid);
        flash('success', __('Signed. A signing record has been kept.'));
        redirect('/library/ack/' . $aid);
    }

    public function ackRecord(string $id): void
    {
        $a = DB::row('SELECT a.*, d.title, p.first_name, p.last_name FROM {p}acknowledgements a JOIN {p}documents d ON d.id = a.document_id JOIN {p}people p ON p.id = a.person_id WHERE a.id = ?', [(int)$id]) ?? abort(404);
        if ((int)$a['person_id'] !== person_id() && !can('library.manage')) {
            abort(403);
        }
        view('library/ack', ['title' => __('Signing record'), 'a' => $a]);
    }

    // Management ---------------------------------------------------------------
    private function storeFile(): ?int
    {
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (Demo::on()) {
            flash('warning', __('File uploads are switched off on the demo site. Paste the text instead.'));
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) {
            flash('error', __('Upload failed. Files must be under 20 MB.'));
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $allowed = ['application/pdf' => 'pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'audio/mpeg' => 'mp3'];
        if (!isset($allowed[$mime])) {
            flash('error', __('That file type is not allowed.'));
            return null;
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/uploads/' . $stored)) {
            return null;
        }
        return DB::insert('files', ['original_name' => mb_substr(basename((string)$f['name']), 0, 255), 'stored_name' => $stored, 'mime' => $mime, 'size' => (int)$f['size'], 'uploaded_by' => Auth::id()]);
    }

    private function addVersion(int $docId, int $version, ?string $notes): bool
    {
        $fileId = $this->storeFile();
        $body = trim((string)input('body', ''));
        if (!$fileId && $body === '') {
            return false;
        }
        $hash = $fileId ? hash_file('sha256', STORAGE_DIR . '/uploads/' . DB::val('SELECT stored_name FROM {p}files WHERE id = ?', [$fileId])) : hash('sha256', $body);
        DB::insert('document_versions', ['document_id' => $docId, 'version' => $version, 'file_id' => $fileId, 'body' => $body !== '' ? $body : null, 'content_hash' => $hash, 'notes' => $notes, 'created_by' => Auth::id()]);
        return true;
    }

    private function meta(): array
    {
        $vis = isset(self::visibilities()[input('visibility')]) ? input('visibility') : 'members';
        return ['title' => mb_substr((string)input('title', ''), 0, 200), 'category' => mb_substr((string)input('category', ''), 0, 80) ?: null, 'description' => input('description') ?: null, 'visibility' => $vis, 'group_ids' => $vis === 'groups' ? implode(',', input_ids('group_ids')) : null, 'requires_ack' => input_bool('requires_ack'), 'ack_renew_months' => input_int('ack_renew_months') ?: null];
    }

    public function create(): void
    {
        view('library/form', ['title' => __('Add a document'), 'd' => ['visibility' => 'members'], 'groups' => array_column(DB::all('SELECT id, name FROM {p}groups WHERE active = 1 ORDER BY name'), 'name', 'id')]);
    }

    public function store(): void
    {
        $m = $this->meta();
        if ($m['title'] === '') {
            flash('error', __('A title is required.'));
            back('/library/new');
        }
        $id = DB::insert('documents', $m + ['created_by' => Auth::id()]);
        if (!$this->addVersion($id, 1, __('First version'))) {
            DB::run('DELETE FROM {p}documents WHERE id = ?', [$id]);
            flash('error', __('Upload a file or paste the document text.'));
            back('/library/new');
        }
        audit('library.created', 'document', $id);
        redirect('/library/' . $id);
    }

    public function update(string $id): void
    {
        $d = DB::row('SELECT * FROM {p}documents WHERE id = ?', [(int)$id]) ?? abort(404);
        $m = $this->meta();
        $m['archived'] = input_bool('archived');
        $m['updated_at'] = date('Y-m-d H:i:s');
        if ($m['title'] === '') {
            $m['title'] = $d['title'];
        }
        DB::update('documents', $m, 'id = ?', [$d['id']]);
        audit('library.updated', 'document', (int)$d['id']);
        flash('success', __('Saved.'));
        redirect('/library/' . $d['id']);
    }

    public function newVersion(string $id): void
    {
        $d = DB::row('SELECT * FROM {p}documents WHERE id = ?', [(int)$id]) ?? abort(404);
        $next = (int)$d['current_version'] + 1;
        if (!$this->addVersion((int)$d['id'], $next, mb_substr((string)input('notes', ''), 0, 255) ?: null)) {
            flash('error', __('Upload a file or paste the new text.'));
            redirect('/library/' . $d['id']);
        }
        DB::update('documents', ['current_version' => $next, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$d['id']]);
        audit('library.new_version', 'document', (int)$d['id'], ['version' => $next]);
        flash('success', (int)$d['requires_ack'] ? __('Version %d published. Everyone must sign the new version — use "Request signatures" to notify them.', $next) : __('Version %d published.', $next));
        redirect('/library/' . $d['id']);
    }

    public function requestSignatures(string $id): void
    {
        $d = DB::row('SELECT * FROM {p}documents WHERE id = ?', [(int)$id]) ?? abort(404);
        $n = 0;
        foreach ($this->signers($d) as $p) {
            if (!$p['ack']) {
                Notify::person((int)$p['id'], __('Please read and sign: %s', $d['title']), '<p>' . e(__('Please read and sign "%s". It is required for your serving role.', $d['title'])) . '</p><p><a href="' . e(url('/library/' . $d['id'], [], true)) . '">' . e(__('Read and sign')) . '</a></p>', null, url('/library/' . $d['id']));
                $n++;
            }
        }
        Notify::processQueue(20);
        audit('library.signatures_requested', 'document', (int)$d['id'], ['count' => $n]);
        flash('success', __('Reminder sent to %d people.', $n));
        redirect('/library/' . $d['id']);
    }

    public function file(string $versionId): void
    {
        $v = DB::row('SELECT * FROM {p}document_versions WHERE id = ?', [(int)$versionId]) ?? abort(404);
        $this->find($v['document_id']);
        $f = DB::row('SELECT * FROM {p}files WHERE id = ?', [$v['file_id']]) ?? abort(404);
        FilesController::send($f);
    }
}
