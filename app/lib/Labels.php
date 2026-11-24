<?php
declare(strict_types=1);

/**
 * Check-in labels as PDF (62 × 100 mm), drawn with GD so any script the chosen font covers prints,
 * and silent printing through PrintNode (printnode.com), which runs a small client on the computer
 * the label printer is plugged into.
 */
final class Labels
{
    public const W_MM = 100;
    public const H_MM = 62;
    private const DPI = 300;

    public static function canRender(): bool
    {
        return function_exists('imagettftext') && function_exists('imagejpeg');
    }

    /** The church's own label font if uploaded (e.g. Noto Sans CJK for Chinese/Korean names), else DejaVu Sans. */
    public static function font(bool $bold = true): string
    {
        foreach (['ttf', 'otf', 'ttc'] as $ext) {
            if (is_file($f = STORAGE_DIR . '/fonts/label-font.' . $ext)) {
                return $f;
            }
        }
        return APP_DIR . '/fonts/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf';
    }

    /**
     * The font to draw this text with. Arabic and Persian need a font with Arabic presentation forms, which the
     * bundled DejaVu Sans has; an uploaded CJK font usually doesn't, so Arabic-script text always uses DejaVu.
     */
    private static function pick(string $font, string $text): string
    {
        if (Shaper::hasArabic($text) && !str_starts_with($font, APP_DIR)) {
            return APP_DIR . '/fonts/DejaVuSans' . (str_contains($font, 'label-font') ? '-Bold' : '') . '.ttf';
        }
        return $font;
    }

    private static function width(string $text, string $font, float $size): int
    {
        $b = imagettfbbox($size, 0, self::pick($font, $text), Shaper::visual($text));
        return $b ? $b[2] - $b[0] : 0;
    }

    /** Largest font size (px) up to $max at which $text fits in $width. */
    private static function fit(string $text, string $font, float $max, int $width): float
    {
        $font = self::pick($font, $text);
        $text = Shaper::visual($text);
        for ($size = $max; $size > 10; $size -= 2) {
            $b = imagettfbbox($size, 0, $font, $text);
            if ($b && ($b[2] - $b[0]) <= $width) {
                return $size;
            }
        }
        return 10;
    }

    /** Split text into two lines of roughly equal length at a space. */
    private static function halves(string $text): array
    {
        $words = preg_split('/\s+/u', trim($text));
        if (count($words) < 2) {
            return [$text, ''];
        }
        $best = [$text, ''];
        $bestDiff = PHP_INT_MAX;
        for ($i = 1; $i < count($words); $i++) {
            $a = implode(' ', array_slice($words, 0, $i));
            $b = implode(' ', array_slice($words, $i));
            if (abs(mb_strlen($a) - mb_strlen($b)) < $bestDiff) {
                $bestDiff = abs(mb_strlen($a) - mb_strlen($b));
                $best = [$a, $b];
            }
        }
        return $best;
    }

    /**
     * Draw text starting at $x. Right-to-left text (Arabic, Persian) is shaped and, when $right is given,
     * aligned to end at $right instead.
     */
    private static function text($im, string $text, float $size, int $x, int $y, string $font, int $color, ?int $right = null): void
    {
        $font = self::pick($font, $text);
        if ($right !== null && Shaper::isRtl($text)) {
            $x = max($x, $right - self::width($text, $font, $size));
        }
        imagettftext($im, $size, 0, $x, $y, $color, $font, Shaper::visual($text));
    }

    /**
     * One label as a JPEG. $kind: 'child' or 'parent'.
     * $l = ['code', 'name', 'class', 'date', 'alert' (bool), 'allergies' (string, printed only if allowed), 'church']
     */
    public static function image(string $kind, array $l): string
    {
        $w = (int)round(self::W_MM / 25.4 * self::DPI);
        $h = (int)round(self::H_MM / 25.4 * self::DPI);
        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);
        $bold = self::font(true);
        $reg = self::font(false);
        $pad = 40;

        // Security code, boxed, top right.
        $code = (string)$l['code'];
        $cs = 120;
        $cb = imagettfbbox($cs, 0, $bold, $code);
        $cw = $cb[2] - $cb[0] + 40;
        imagefilledrectangle($im, $w - $pad - $cw, $pad - 10, $w - $pad, $pad + 160, $black);
        self::text($im, $code, $cs, $w - $pad - $cw + 20, $pad + 135, $bold, $white);

        if ($kind === 'parent') {
            $beside = $w - $pad - $cw - 30; // right edge of the space left of the code box
            self::text($im, mb_strtoupper(__('Parent tag')), 34, $pad, $pad + 40, $bold, $black, $beside);
            self::text($im, __('Show this at pick-up'), 30, $pad, $pad + 95, $reg, $black, $beside);
            $names = (string)$l['name'];
            self::text($im, $names, self::fit($names, $bold, 64, $w - 2 * $pad), $pad, $h - 190, $bold, $black, $w - $pad);
            self::text($im, trim(($l['church'] ?? '') . ' · ' . ($l['date'] ?? ''), ' ·'), 30, $pad, $h - $pad - 20, $reg, $black, $w - $pad);
        } else {
            $name = (string)$l['name'];
            // Arabic script has tall ascenders (lam, alef), so start a little smaller to stay clear of the code box.
            $size = self::fit($name, $bold, Shaper::hasArabic($name) ? 96 : 110, $w - 2 * $pad);
            if ($size < 80 && ([$a, $b] = self::halves($name)) && $b !== '') {
                $size = min(self::fit($a, $bold, 90, $w - 2 * $pad), self::fit($b, $bold, 90, $w - 2 * $pad));
                self::text($im, $a, $size, $pad, $pad + 290, $bold, $black, $w - $pad);
                self::text($im, $b, $size, $pad, $pad + 290 + (int)($size * 1.35), $bold, $black, $w - $pad);
                $classY = $pad + 290 + (int)($size * 1.35) + 80;
            } else {
                self::text($im, $name, $size, $pad, $pad + 300, $bold, $black, $w - $pad);
                $classY = $pad + 390;
            }
            $line = trim(($l['class'] ?? '') . ' · ' . ($l['date'] ?? ''), ' ·');
            self::text($im, $line, self::fit($line, $reg, 46, $w - 2 * $pad), $pad, $classY, $reg, $black, $w - $pad);
            if (!empty($l['alert'])) {
                $txt = (str_starts_with($bold, APP_DIR) ? '⚠ ' : '! ') . (!empty($l['allergies']) ? __('Allergy: %s', $l['allergies']) : __('Care alert — see leader'));
                $size = self::fit($txt, $bold, 46, $w - 2 * $pad - 40);
                if ($size < 36 && ([$a, $b] = self::halves($txt)) && $b !== '') {
                    $size = min(self::fit($a, $bold, 44, $w - 2 * $pad - 40), self::fit($b, $bold, 44, $w - 2 * $pad - 40));
                    imagefilledrectangle($im, $pad, $h - $pad - 160, $w - $pad, $h - $pad, $black);
                    self::text($im, $a, $size, $pad + 20, $h - $pad - 95, $bold, $white, $w - $pad - 20);
                    self::text($im, $b, $size, $pad + 20, $h - $pad - 30, $bold, $white, $w - $pad - 20);
                } else {
                    imagefilledrectangle($im, $pad, $h - $pad - 110, $w - $pad, $h - $pad, $black);
                    self::text($im, $txt, $size, $pad + 20, $h - $pad - 35, $bold, $white, $w - $pad - 20);
                }
            } else {
                self::text($im, (string)($l['church'] ?? ''), 30, $pad, $h - $pad - 20, $reg, $black, $w - $pad);
            }
        }
        ob_start();
        imagejpeg($im, null, 92);
        imagedestroy($im);
        return (string)ob_get_clean();
    }

    /** A PDF with one full-page JPEG per label (DCTDecode, so no compression library is needed). */
    public static function pdf(array $jpegs): string
    {
        $pw = round(self::W_MM / 25.4 * 72, 2);
        $ph = round(self::H_MM / 25.4 * 72, 2);
        $objs = [];
        $n = count($jpegs);
        // 1: catalog, 2: pages, then per label: page, content, image.
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (3 + $i * 3) . ' 0 R';
        }
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        foreach (array_values($jpegs) as $i => $jpg) {
            [$iw, $ih] = getimagesizefromstring($jpg) ?: [1, 1];
            $p = 3 + $i * 3;
            $content = "q $pw 0 0 $ph 0 0 cm /Im0 Do Q";
            $objs[$p] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pw . ' ' . $ph . '] /Resources << /XObject << /Im0 ' . ($p + 2) . ' 0 R >> >> /Contents ' . ($p + 1) . ' 0 R >>';
            $objs[$p + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objs[$p + 2] = '<< /Type /XObject /Subtype /Image /Width ' . $iw . ' /Height ' . $ih . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpg) . " >>\nstream\n" . $jpg . "\nendstream";
        }
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($objs);
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    /** Labels for check-ins: one per child plus one parent tag per security code. */
    public static function forCheckins(array $rows, bool $showAllergies = false): string
    {
        $jpegs = [];
        $church = (string)setting('church_name', '');
        $byCode = [];
        foreach ($rows as $r) {
            $jpegs[] = self::image('child', ['code' => $r['security_code'], 'name' => person_name($r), 'class' => (string)($r['class'] ?? ''), 'date' => fmt_date($r['session_date'], 'D j M'),
                'alert' => !empty($r['alert']), 'allergies' => $showAllergies ? mb_strimwidth(trim((string)($r['allergies'] ?? '')), 0, 60, '…') : '', 'church' => $church]);
            $byCode[$r['security_code']][] = trim((string)($r['preferred_name'] ?: $r['first_name']));
        }
        foreach ($byCode as $code => $names) {
            $jpegs[] = self::image('parent', ['code' => $code, 'name' => implode(', ', $names), 'date' => fmt_date($rows[0]['session_date'], 'D j M'), 'church' => $church]);
        }
        return self::pdf($jpegs);
    }

    // ------------------------------------------------------------------ PrintNode

    public static function printNodeKey(): string
    {
        return (string)Crypto::decrypt((string)setting('printnode_api_key', ''));
    }

    public static function printNodeOn(): bool
    {
        return self::printNodeKey() !== '' && self::canRender() && !Demo::on();
    }

    private static function api(string $method, string $path, ?array $json = null): array
    {
        $base = (string)(config('printnode_endpoint') ?? 'https://api.printnode.com');
        [$code, $body] = http_request($method, $base . $path, $json !== null ? json_encode($json) : null, $json !== null ? ['Content-Type: application/json'] : [], self::printNodeKey() . ':');
        return [$code, json_decode((string)$body, true)];
    }

    /** Printers visible to the PrintNode account: id => "Name (computer)". */
    public static function printers(): array
    {
        [$code, $list] = self::api('GET', '/printers');
        if ($code !== 200 || !is_array($list)) {
            throw new RuntimeException($code === 401 ? __('PrintNode did not accept the API key.') : __('Could not reach PrintNode (HTTP %d).', $code));
        }
        $out = [];
        foreach ($list as $p) {
            $out[(string)$p['id']] = $p['name'] . (isset($p['computer']['name']) ? ' (' . $p['computer']['name'] . ')' : '') . (($p['state'] ?? '') !== 'online' ? ' — ' . __('offline') : '');
        }
        return $out;
    }

    /** Send a PDF to a printer. Returns null on success or an error message. */
    public static function send(string $printerId, string $pdf, string $title): ?string
    {
        if (!self::printNodeOn() || $printerId === '') {
            return __('Silent printing is not set up.');
        }
        try {
            [$code, $res] = self::api('POST', '/printjobs', ['printerId' => (int)$printerId, 'title' => mb_substr($title, 0, 100), 'contentType' => 'pdf_base64', 'content' => base64_encode($pdf), 'source' => 'Affreso']);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return ($code === 201 || $code === 200) ? null : __('PrintNode refused the job (HTTP %d).', $code);
    }

    /** Print labels for these check-ins on a printer, if silent printing is set up. Returns null or error. */
    public static function printCheckins(array $rows, string $printerId, bool $showAllergies = false): ?string
    {
        if (!$rows) {
            return null;
        }
        return self::send($printerId, self::forCheckins($rows, $showAllergies), __('Check-in labels'));
    }
}
