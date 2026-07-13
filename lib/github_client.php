<?php
require_once __DIR__ . '/../config/config.php';

const GITHUB_CACHE_TTL = 600; // 10 minutes

/**
 * Fetch all skill folders from the GitHub repo.
 * Returns array of ['slug' => 'folder-name', 'name' => '...', 'description' => '...', 'download_url' => '...']
 *
 * Results are cached on disk: listing skills costs 1 + N GitHub API calls, and
 * without a GITHUB_TOKEN the unauthenticated limit (60/hr) is hit after a few
 * page loads. On rate limit / network failure the stale cache is served.
 */
function github_list_skills(): array
{
    $cached = github_cache_read('skills_list', GITHUB_CACHE_TTL);
    if ($cached !== null) {
        return json_decode($cached, true) ?: [];
    }

    // Currently hardcoded to fetch from the 'ba' role folder as requested
    $url = GITHUB_API_BASE . '/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . '/contents/ba/.copilot/skills';
    $items = github_get($url);

    $skills = [];
    foreach ($items ?? [] as $item) {
        if (($item['type'] ?? '') !== 'dir') continue;
        $slug = $item['name'];

        $meta = github_fetch_skill_meta($slug);
        if ($meta) {
            $skills[] = $meta;
        }
    }

    if (!empty($skills)) {
        github_cache_write('skills_list', json_encode($skills));
        return $skills;
    }

    // Fetch failed (likely rate limited) — fall back to stale cache if we have one
    $stale = github_cache_read('skills_list', 0);
    return $stale !== null ? (json_decode($stale, true) ?: []) : [];
}

/**
 * Fetch metadata + download_url for a single skill.
 */
function github_fetch_skill_meta(string $slug): ?array
{
    $base = GITHUB_API_BASE . '/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . '/contents/ba/.copilot/skills/' . rawurlencode($slug);
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
 * Fetch full SKILL.md content for a given slug (disk-cached, stale on failure).
 */
function github_fetch_skill_content(string $slug): ?string
{
    $cacheKey = 'skill_content_' . $slug;
    $cached = github_cache_read($cacheKey, GITHUB_CACHE_TTL);
    if ($cached !== null) {
        return $cached;
    }

    $meta = github_fetch_skill_meta($slug);
    $content = $meta ? github_fetch_raw($meta['download_url']) : null;

    if ($content !== null) {
        github_cache_write($cacheKey, $content);
        return $content;
    }

    return github_cache_read($cacheKey, 0);
}

/* ── Simple file cache (survives GitHub rate limits between requests) ── */

function github_cache_dir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'skillapp_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Read a cache entry. $maxAge in seconds; 0 = ignore age (used as stale fallback).
 */
function github_cache_read(string $key, int $maxAge): ?string
{
    $file = github_cache_dir() . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    if (!is_file($file)) return null;
    if ($maxAge > 0 && (time() - (int)filemtime($file)) > $maxAge) return null;

    $data = @file_get_contents($file);
    return $data === false ? null : $data;
}

function github_cache_write(string $key, string $value): void
{
    @file_put_contents(github_cache_dir() . DIRECTORY_SEPARATOR . md5($key) . '.cache', $value);
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
