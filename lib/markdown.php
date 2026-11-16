<?php
/**
 * Minimal regex-based Markdown → HTML converter.
 * Handles the subset needed for artifact previews.
 */
function markdown_to_html(string $md): string
{
    // Treat all raw HTML as text. Only this renderer may introduce tags.
    $html = htmlspecialchars(str_replace(["\0", "\x1A"], '', $md), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fragments = [];
    $protect = function (string $html) use (&$fragments): string {
        $index = count($fragments);
        // Resolve earlier code fragments inside a link label before protecting it.
        $html = preg_replace_callback('/\x1A(\d+)\x1A/', function ($m) use (&$fragments) {
            return $fragments[(int)$m[1]] ?? '';
        }, $html);
        $fragments[] = $html;
        return "\x1A" . $index . "\x1A";
    };

    // Protect code from later Markdown substitutions (including links).
    $html = preg_replace_callback('/```(\w*)\n([\s\S]*?)```/', function ($m) use ($protect) {
        $lang = $m[1];
        $class = $lang !== '' ? ' class="language-' . $lang . '"' : '';
        return "\n\n" . $protect('<pre><code' . $class . '>' . $m[2] . '</code></pre>') . "\n\n";
    }, $html);
    $html = preg_replace_callback('/`([^`\n]+)`/', function ($m) use ($protect) {
        return $protect('<code>' . $m[1] . '</code>');
    }, $html);

    // Explicitly allow only absolute HTTP(S) and mailto links, never script/data URLs.
    $html = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', function ($m) use ($protect) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/[\x00-\x20\x7f]/', $url) ||
            !preg_match('~^(?:https?://[^/]+|mailto:[^@]+@[^@]+)~i', $url)) {
            return $m[1];
        }
        return $protect('<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $m[1] . '</a>');
    }, $html);

    // Headings
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

    // Horizontal rules
    $html = preg_replace('/^---$/m', '<hr>', $html);

    // Bold and italic
    $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

    // Unordered lists
    $html = preg_replace_callback('/(?:^- .+\n?)+/m', function ($m) {
        $items = preg_split('/\n- /', $m[0]);
        $lis = '';
        foreach ($items as $i => $item) {
            if ($i === 0) $item = ltrim(substr($item, 2));
            $lis .= '<li>' . trim($item) . '</li>';
        }
        return '<ul>' . $lis . '</ul>';
    }, $html);

    // Tables (simple pipe-based)
    $html = preg_replace_callback('/^(\|.+\|)\n\|[-| ]+\|\n((?:\|.+\|\n?)*)/m', function ($m) {
        $header = explode('|', trim($m[1], '|'));
        $rows = explode("\n", trim($m[2]));
        $thead = '<tr><th>' . implode('</th><th>', array_map('trim', $header)) . '</th></tr>';
        $tbody = '';
        foreach ($rows as $row) {
            $cells = explode('|', trim($row, '|'));
            $tbody .= '<tr><td>' . implode('</td><td>', array_map('trim', $cells)) . '</td></tr>';
        }
        return '<table><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table>';
    }, $html);

    // Paragraphs (double newlines)
    $paragraphs = preg_split('/\n\n+/', $html);
    $html = '';
    foreach ($paragraphs as $p) {
        $p = trim($p, " \n\r\t\v");
        if ($p === '') continue;
        if (preg_match('/^<(h[1-3]|ul|ol|li|table|pre|hr|blockquote)/', $p) || preg_match('/^\x1A\d+\x1A$/', $p)) {
            $html .= $p . "\n";
        } else {
            $html .= '<p>' . $p . "</p>\n";
        }
    }

    return preg_replace_callback('/\x1A(\d+)\x1A/', function ($m) use ($fragments) {
        return $fragments[(int)$m[1]] ?? '';
    }, $html);
}
