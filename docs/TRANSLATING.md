# Translating Affreso

Affreso ships with **Simplified Chinese (`zh-CN`)**, **Traditional Chinese (`zh-TW`)**, **Korean (`ko`)**, **Vietnamese (`vi`)**, **Arabic (`ar`)** and **Persian (`fa`)**. Each person chooses their own language in **Account & security**, and the church default is set in **Settings → General**. Corrections to these files are welcome: edit the value next to the English text.

Every piece of interface text is written in English inside `__('…')`. English is the lookup key, so a language file only needs translations, not code changes.

1. Generate an up-to-date template:
   ```
   php tools/extract-strings.php
   ```
   This writes `lang/TEMPLATE.php`, which lists every string.
2. Copy it to a file named after the language code, for example `lang/zh-CN.php`, `lang/ko.php` or `lang/fa.php`.
3. Fill in each value:
   ```php
   'My rosters' => '我的服事表',
   'Due %s' => '截止日期：%s',
   ```
   - Keep every `%s` and `%d` placeholder. If your language needs a different word order, number them: `%2$s` is the second value and `%1$s` is the first, for example `'%s declined %s on %s' => '%1$s 拒绝了 %3$s 的 %2$s 岗位'`.
   - Any string you leave as `''` falls back to English.
4. Choose the language in **Administration → Settings → General → Interface language**.

Notes:
- Each person can pick their own language in **Account & security → Language**. The church default applies otherwise.
- Dates use each language's own order (for example 11月29日周日 or 11월 29일 (일)) and its day and month names automatically (when the PHP intl extension is available, which it usually is).
- Arabic, Persian, Hebrew, Urdu, Pashto and Kurdish switch the whole layout to right-to-left.
- Emails, notifications and public pages use the same language files.
- Dates in Arabic and Persian use the Gregorian calendar and Western digits so they match times, amounts and codes elsewhere on the page.
- Samoan (`sm`) and Tongan (`to`) are recognised by name but no file is included yet. A native speaker who knows church usage is the best person to write one — start from `lang/TEMPLATE.php`.
