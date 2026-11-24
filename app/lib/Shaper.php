<?php
declare(strict_types=1);

/**
 * Prepares Arabic-script text (Arabic, Persian, Urdu letters) for drawing with GD, which places glyphs
 * one after another from left to right and knows nothing about joining or direction.
 *
 * 1. Shaping: each letter is replaced by its contextual presentation form (isolated, final, initial, medial),
 *    including the lam-alef ligatures. Fonts such as DejaVu Sans include these forms.
 * 2. Ordering: a simplified Unicode bidi pass turns the logical string into visual left-to-right order.
 *    Right-to-left runs are reversed by cluster (a letter keeps its vowel marks), while Latin words and
 *    numbers inside them stay readable.
 *
 * It is deliberately small: it is good enough for names, class names and short notes on labels, not a full
 * implementation of UAX #9.
 */
final class Shaper
{
    /** Letter => [isolated, final, initial, medial]; null initial/medial means the letter joins only to the previous one. */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, null, null],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // Persian and Urdu letters
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], // peh
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], // tcheh
        0x0698 => [0xFB8A, 0xFB8B, null, null],     // jeh
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], // keheh
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], // gaf
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], // farsi yeh
        0x06C0 => [0xFBA4, 0xFBA5, null, null],     // heh with yeh above
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD], // heh doachashmee
        0x06D2 => [0xFBAE, 0xFBAF, null, null],     // yeh barree
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69], // tteh
        0x0688 => [0xFB88, 0xFB89, null, null],     // ddal
        0x0691 => [0xFB8C, 0xFB8D, null, null],     // rreh
        0x06BA => [0xFB9E, 0xFB9F, null, null],     // noon ghunna
    ];

    /** Lam + alef variant => [isolated ligature, final ligature]. */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«'];

    /** True if the text contains any Arabic-script letter. */
    public static function hasArabic(string $text): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /** True if the text reads right to left (its first strong letter is Arabic script or Hebrew). */
    public static function isRtl(string $text): bool
    {
        foreach (self::chars($text) as $c) {
            $t = self::type($c);
            if ($t === 'R') {
                return true;
            }
            if ($t === 'L') {
                return false;
            }
        }
        return false;
    }

    /** Shaped text in visual order, ready for imagettftext(). Text without Arabic script or Hebrew is returned unchanged. */
    public static function visual(string $text): string
    {
        if (!preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return $text;
        }
        return self::reorder(self::shape(self::chars($text)), self::isRtl($text));
    }

    /** @return int[] code points */
    private static function chars(string $text): array
    {
        return array_map('mb_ord', mb_str_split($text));
    }

    private static function isMark(int $c): bool
    {
        return ($c >= 0x064B && $c <= 0x065F) || $c === 0x0670 || ($c >= 0x06D6 && $c <= 0x06ED) || ($c >= 0x0610 && $c <= 0x061A);
    }

    /** Does this letter connect to the letter after it? */
    private static function joinsForward(int $c): bool
    {
        return $c === 0x0640 || (isset(self::FORMS[$c]) && self::FORMS[$c][2] !== null);
    }

    /** Can this letter connect to the letter before it? */
    private static function joinsBack(int $c): bool
    {
        return $c === 0x0640 || (isset(self::FORMS[$c]) && self::FORMS[$c][1] !== null);
    }

    /**
     * Replace letters with contextual forms (logical order is kept).
     * @param int[] $cs
     * @return int[]
     */
    private static function shape(array $cs): array
    {
        $n = count($cs);
        $prev = static function (int $i) use ($cs): ?int {
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!self::isMark($cs[$j])) {
                    return $cs[$j];
                }
            }
            return null;
        };
        $next = static function (int $i) use ($cs, $n): ?int {
            for ($j = $i + 1; $j < $n; $j++) {
                if (!self::isMark($cs[$j])) {
                    return $cs[$j];
                }
            }
            return null;
        };
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $cs[$i];
            if (!isset(self::FORMS[$c])) {
                if ($c !== 0x200C && $c !== 0x200D) { // zero-width (non-)joiners only steer joining
                    $out[] = $c;
                }
                continue;
            }
            $p = $prev($i);
            $joinPrev = $p !== null && self::joinsForward($p);
            // Lam followed by alef: one ligature.
            if ($c === 0x0644) {
                $nx = $next($i);
                if ($nx !== null && isset(self::LAM_ALEF[$nx])) {
                    $out[] = self::LAM_ALEF[$nx][$joinPrev ? 1 : 0];
                    // Keep any marks on the lam, then skip to after the alef (its own marks follow).
                    for ($j = $i + 1; $j < $n && $cs[$j] !== $nx; $j++) {
                        $out[] = $cs[$j];
                    }
                    $i = $j;
                    continue;
                }
            }
            $nx = $next($i);
            $joinNext = $nx !== null && self::joinsForward($c) && self::joinsBack($nx);
            [$iso, $fin, $ini, $med] = self::FORMS[$c];
            if ($joinPrev && $joinNext) {
                $out[] = $med ?? $fin ?? $iso;
            } elseif ($joinPrev) {
                $out[] = $fin ?? $iso;
            } elseif ($joinNext) {
                $out[] = $ini ?? $iso;
            } else {
                $out[] = $iso;
            }
        }
        return $out;
    }

    /** Bidi class, simplified: R (right to left), L (left to right), N (digits) or W (spaces and punctuation). */
    private static function type(int $c): string
    {
        if (($c >= 0x0590 && $c <= 0x08FF && !($c >= 0x0660 && $c <= 0x0669) && !($c >= 0x06F0 && $c <= 0x06F9))
            || ($c >= 0xFB1D && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF)) {
            return 'R';
        }
        if (($c >= 0x30 && $c <= 0x39) || ($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9)) {
            return 'N';
        }
        $ch = mb_chr($c);
        if (preg_match('/\p{L}/u', $ch)) {
            return 'L';
        }
        return 'W';
    }

    /**
     * Logical => visual order, following the main rules of UAX #9 for one paragraph without explicit embeddings:
     * numbers keep left-to-right order inside right-to-left text, neutrals (spaces, punctuation) take the
     * direction of the text around them, and runs are reversed by level.
     * @param int[] $cs
     */
    private static function reorder(array $cs, bool $rtl): string
    {
        // Clusters: a base character with any marks that follow it.
        $cl = [];
        foreach ($cs as $c) {
            if (self::isMark($c) && $cl) {
                $cl[count($cl) - 1]['s'] .= mb_chr($c);
                continue;
            }
            $cl[] = ['s' => mb_chr($c), 't' => self::type($c)];
        }
        $n = count($cl);
        $base = $rtl ? 'R' : 'L';
        // W7: numbers after left-to-right text (or at the start of a left-to-right paragraph) are simply L.
        $strong = $base;
        for ($i = 0; $i < $n; $i++) {
            $t = $cl[$i]['t'];
            if ($t === 'R' || $t === 'L') {
                $strong = $t;
            } elseif ($t === 'N' && $strong === 'L') {
                $cl[$i]['t'] = 'L';
            }
        }
        // N1/N2: a run of neutrals between two letters of the same direction takes it (numbers count as R);
        // otherwise it takes the paragraph direction.
        $strongOf = static fn (string $t): string => $t === 'N' ? 'R' : $t;
        for ($i = 0; $i < $n; $i++) {
            if ($cl[$i]['t'] !== 'W') {
                continue;
            }
            $j = $i;
            while ($j < $n && $cl[$j]['t'] === 'W') {
                $j++;
            }
            $before = $i > 0 ? $strongOf($cl[$i - 1]['t']) : $base;
            $after = $j < $n ? $strongOf($cl[$j]['t']) : $base;
            $dir = $before === $after ? $before : $base;
            for ($k = $i; $k < $j; $k++) {
                $cl[$k]['t'] = $dir;
            }
            $i = $j - 1;
        }
        // I1/I2: embedding levels.
        $levels = [];
        foreach ($cl as $c) {
            if ($rtl) {
                $levels[] = $c['t'] === 'R' ? 1 : 2;
            } else {
                $levels[] = $c['t'] === 'L' ? 0 : ($c['t'] === 'R' ? 1 : 2);
            }
        }
        // L2: from the highest level down to the lowest odd level, reverse every run at that level or higher.
        $order = range(0, $n - 1);
        $max = $levels ? max($levels) : 0;
        for ($lv = $max; $lv >= 1; $lv--) {
            for ($i = 0; $i < $n; $i++) {
                if ($levels[$order[$i]] < $lv) {
                    continue;
                }
                $j = $i;
                while ($j < $n && $levels[$order[$j]] >= $lv) {
                    $j++;
                }
                array_splice($order, $i, $j - $i, array_reverse(array_slice($order, $i, $j - $i)));
                $i = $j;
            }
        }
        $out = '';
        foreach ($order as $k) {
            $s = $cl[$k]['s'];
            $out .= $levels[$k] % 2 === 1 ? (self::MIRROR[$s] ?? $s) : $s;
        }
        return $out;
    }
}
