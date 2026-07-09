<?php
/**
 * Minimal regex-based Markdown → HTML converter.
 * Handles the subset needed for artifact previews.
 */
function markdown_to_html(string $md): string
{
    $html = $md;

    // Code blocks (```...```) — preserve
    $html = preg_replace_callback('/```(\w*)\n([\s\S]*?)```/', function ($m) {
        $lang = htmlspecialchars($m[1]);
        $code = htmlspecialchars($m[2]);
        return $lang ? "<pre><code class=\"language-$lang\">$code</code></pre>" : "<pre><code>$code</code></pre>";
    }, $html);

    // Inline code
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

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

    // Links
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);

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
        $p = trim($p);
        if ($p === '') continue;
        if (preg_match('/^<(h[1-3]|ul|ol|li|table|pre|hr|blockquote)/', $p)) {
            $html .= $p . "\n";
        } else {
            $html .= '<p>' . $p . "</p>\n";
        }
    }

    return $html;
}
