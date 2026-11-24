<?php
declare(strict_types=1);

/** Serves uploaded files from outside the public web path, after a permission check. */
final class FilesController
{
    public function show(string $id): void
    {
        $f = DB::row('SELECT * FROM {p}files WHERE id = ?', [(int)$id]) ?? abort(404);
        $courseIds = array_map('intval', DB::col('SELECT course_id FROM {p}lessons WHERE file_id = ?', [$f['id']]));
        $allowed = can('courses.approve') || can('training.reports') || (int)$f['uploaded_by'] === Auth::id();
        if (!$allowed && $courseIds) {
            $pid = person_id();
            $allowed = ($pid && DB::val('SELECT 1 FROM {p}enrolments WHERE person_id = ? AND course_id IN (' . DB::in($courseIds) . ')', array_merge([$pid], $courseIds)))
                || DB::val('SELECT 1 FROM {p}course_editors WHERE user_id = ? AND course_id IN (' . DB::in($courseIds) . ')', array_merge([Auth::id()], $courseIds));
        }
        if (!$allowed) {
            abort(403);
        }
        self::send($f);
    }

    public static function send(array $f): never
    {
        $path = STORAGE_DIR . '/uploads/' . basename($f['stored_name']);
        if (!is_file($path)) {
            abort(404);
        }
        $inline = in_array($f['mime'], ['application/pdf', 'image/png', 'image/jpeg'], true);
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9_\-. ]/', '_', $f['original_name']) . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
