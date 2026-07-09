<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Fetch all skill folders from the GitHub repo.
 * Returns array of ['slug' => 'folder-name', 'name' => '...', 'description' => '...', 'download_url' => '...']
 */
function github_list_skills(): array
{
    $url = GITHUB_API_BASE . '/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . '/contents/.copilot/skills';
    $items = github_get($url);

    $skills = [];
    foreach ($items as $item) {
        if (($item['type'] ?? '') !== 'dir') continue;
        $slug = $item['name'];

        $meta = github_fetch_skill_meta($slug);
        if ($meta) {
            $skills[] = $meta;
        }
    }

    return $skills;
}

/**
 * Fetch metadata + download_url for a single skill.
 */
function github_fetch_skill_meta(string $slug): ?array
{
    $base = GITHUB_API_BASE . '/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . '/contents/.copilot/skills/' . rawurlencode($slug);
    $files = github_get($base);

    foreach ($files as $file) {
        $fname = basename($file['name'] ?? '');
        if (strtoupper($fname) === 'SKILL.MD') {
            $content = github_fetch_raw($file['download_url']);

            $name = $slug;
            $description = '';

            if (preg_match('/^---\s*\n(.+?)\n---/s', $content, $m)) {
                $fm = $m[1];
                if (preg_match('/^name:\s*(.+)$/m', $fm, $nm)) {
                    $name = trim($nm[1]);
                }
                if (preg_match('/^description:\s*(.+)$/m', $fm, $dm)) {
                    $description = trim($dm[1]);
                }
            }

            return [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'download_url' => $file['download_url'],
            ];
        }
    }

    return null;
}

/**
 * Fetch full SKILL.md content for a given slug.
 */
function github_fetch_skill_content(string $slug): ?string
{
    $meta = github_fetch_skill_meta($slug);
    if (!$meta) return null;

    return github_fetch_raw($meta['download_url']);
}

/**
 * GET a GitHub API URL, return decoded JSON.
 */
function github_get(string $url): ?array
{
    $ch = curl_init($url);
    $headers = ['User-Agent: SkillApp/1.0', 'Accept: application/vnd.github.v3+json'];

    if (defined('GITHUB_TOKEN') && GITHUB_TOKEN !== '') {
        $headers[] = 'Authorization: Bearer ' . GITHUB_TOKEN;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Fetch raw content from a download URL.
 */
function github_fetch_raw(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200 && $response !== false) ? $response : null;
}
