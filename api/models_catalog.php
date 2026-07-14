<?php
session_start();
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/github_client.php'; // reuse the disk cache helpers

header('Content-Type: application/json');
require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$provider = $input['provider'] ?? 'openai-compatible';
$baseUrl = rtrim(trim($input['base_url'] ?? ''), '/');
$apiKey = trim($input['api_key'] ?? '');

if ($provider === 'openrouter') {
    // OpenRouter's catalogue is public — no key required. Cache it: it's ~400 models.
    $cached = github_cache_read('models_openrouter', 3600);
    if ($cached !== null) {
        echo $cached;
        exit;
    }
    $result = fetch_models('https://openrouter.ai/api/v1/models', []);
    if ($result !== null) {
        $json = json_encode(['models' => normalize_models($result)]);
        github_cache_write('models_openrouter', $json);
        echo $json;
        exit;
    }
    // Stale fallback
    $stale = github_cache_read('models_openrouter', 0);
    if ($stale !== null) { echo $stale; exit; }
    echo json_encode(['error' => 'Could not reach openrouter.ai']);
    exit;
}

if ($provider === 'anthropic') {
    if ($apiKey === '') {
        echo json_encode(['error' => 'Enter your Anthropic API key first — the model list requires it.']);
        exit;
    }
    $result = fetch_models('https://api.anthropic.com/v1/models?limit=100', [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ]);
    if ($result === null) {
        echo json_encode(['error' => 'Could not fetch models — check your Anthropic API key.']);
        exit;
    }
    echo json_encode(['models' => normalize_models($result)]);
    exit;
}

// OpenAI-compatible (Groq, OpenAI, Together, local servers, ...)
if ($baseUrl === '') {
    echo json_encode(['error' => 'Enter a Base URL first.']);
    exit;
}
if (!preg_match('#^https?://#i', $baseUrl)) {
    echo json_encode(['error' => 'Base URL must start with http(s)://']);
    exit;
}
// Local servers (Ollama, LM Studio) list models without a key; hosted providers need one
$headers = $apiKey !== '' ? ['Authorization: Bearer ' . $apiKey] : [];
$result = fetch_models($baseUrl . '/models', $headers);
if ($result === null) {
    $hint = $apiKey === '' ? ' Most hosted providers require an API key to list models — enter it first.' : ' Check the Base URL and key.';
    echo json_encode(['error' => 'Could not fetch models from ' . $baseUrl . '/models.' . $hint]);
    exit;
}
echo json_encode(['models' => normalize_models($result)]);
exit;

/* ── Helpers ── */

function fetch_models(string $url, array $headers): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge(['User-Agent: SkillApp/1.0', 'Accept: application/json'], $headers),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Normalize OpenAI-style ({data:[{id,...}]}), OpenRouter ({data:[{id,name,...}]})
 * and Anthropic ({data:[{id,display_name,...}]}) responses to [{id, name}].
 */
function normalize_models(array $payload): array
{
    $rows = $payload['data'] ?? $payload['models'] ?? [];
    $models = [];
    foreach ($rows as $row) {
        $id = $row['id'] ?? null;
        if (!is_string($id) || $id === '') continue;
        $models[] = [
            'id' => $id,
            'name' => $row['name'] ?? $row['display_name'] ?? $id,
        ];
    }
    usort($models, fn($a, $b) => strcasecmp($a['id'], $b['id']));
    return $models;
}
