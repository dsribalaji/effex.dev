<?php
require_once __DIR__ . '/../config/config.php';

function groq_stream_chat(array $messages, callable $onChunk, string $apiKey, string $baseUrl, string $model, float $temperature = 0.7, int $maxTokens = 2048): string
{
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'stream' => true,
    ]);

    $fullReply = '';

    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
    ]);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullReply, $onChunk) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line === 'data: [DONE]') continue;
            if (!str_starts_with($line, 'data: ')) continue;

            $json = substr($line, 6);
            $chunk = json_decode($json, true);
            if (!$chunk) continue;

            $delta = $chunk['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                $fullReply .= $delta;
                $onChunk($delta);
            }
        }
        return strlen($data);
    });

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errMsg = 'Error: API returned HTTP ' . $httpCode . ($error ? " ($error)" : '');
        $onChunk("\n\n" . $errMsg);
        return $errMsg;
    }

    return $fullReply;
}

function groq_chat(array $messages, string $apiKey, string $baseUrl, string $model, float $temperature = 0.7, int $maxTokens = 2048): string
{
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'stream' => false,
    ]);

    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return 'Error: API returned HTTP ' . $httpCode;
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'No response.';
}
