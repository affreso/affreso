<?php
declare(strict_types=1);

final class Training
{
    public static function prerequisiteMet(array $course, int $personId): bool
    {
        return !$course['prerequisite_id'] || Volunteering::hasCompleted($personId, (int)$course['prerequisite_id']);
    }

    /** Mark an enrolment complete if every lesson is done and the quiz (if any) is passed. */
    public static function evaluate(int $enrolmentId): bool
    {
        $e = DB::row('SELECT e.*, c.validity_months, c.certificate_enabled, c.title FROM {p}enrolments e JOIN {p}courses c ON c.id = e.course_id WHERE e.id = ?', [$enrolmentId]);
        if (!$e || $e['status'] === 'completed') {
            return false;
        }
        $lessons = (int)DB::val('SELECT COUNT(*) FROM {p}lessons WHERE course_id = ?', [$e['course_id']]);
        $done = (int)DB::val('SELECT COUNT(*) FROM {p}lesson_progress lp JOIN {p}lessons l ON l.id = lp.lesson_id WHERE lp.enrolment_id = ? AND l.course_id = ?', [$e['id'], $e['course_id']]);
        $hasQuiz = (int)DB::val('SELECT COUNT(*) FROM {p}quiz_questions WHERE course_id = ?', [$e['course_id']]) > 0;
        $passed = !$hasQuiz || ($e['status'] !== 'failed' && $e['score'] !== null && (int)$e['score'] >= (int)DB::val('SELECT pass_mark FROM {p}courses WHERE id = ?', [$e['course_id']]));
        if ($done >= $lessons && $passed) {
            DB::update('enrolments', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'expires_at' => $e['validity_months'] ? date('Y-m-d', strtotime('+' . (int)$e['validity_months'] . ' months')) : null,
                'certificate_code' => (int)$e['certificate_enabled'] ? strtoupper(bin2hex(random_bytes(5))) : null,
            ], 'id = ?', [$e['id']]);
            audit('training.completed', 'enrolment', (int)$e['id'], ['course' => $e['title']]);
            Volunteering::onTrainingCompleted((int)$e['person_id']);
            return true;
        }
        if ($e['status'] === 'assigned' && $done > 0) {
            DB::update('enrolments', ['status' => 'in_progress'], 'id = ?', [$e['id']]);
        }
        return false;
    }

    public static function embedUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_\-]{6,20})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }
}
