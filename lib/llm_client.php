<?php
require_once __DIR__ . '/../config/config.php';

function llm_stream_chat(array $messages, callable $onChunk, string $provider, string $apiKey, string $baseUrl, string $model, float $temperature = 0.4, int $maxTokens = 8192): string
{
    $fullReply = '';

    if ($provider === 'anthropic') {
        // Convert messages format for Anthropic (system prompt goes top level)
        $systemPrompt = '';
        $anthropicMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
            } else {
                $anthropicMessages[] = $msg;
            }
        }

        $payloadArr = [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => $maxTokens,
            'stream' => true,
        ];
        if ($systemPrompt !== '') {
            $payloadArr['system'] = $systemPrompt;
        }
        $payload = json_encode($payloadArr);

        $url = $baseUrl . '/messages';
        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ];
    } else {
        // OpenAI / Groq compatible
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => true,
        ]);
        $url = $baseUrl . '/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
    ]);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullReply, $onChunk, $provider) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line === 'data: [DONE]') continue;

            // Anthropic has event types like event: message_delta, event: content_block_delta
            if (str_starts_with($line, 'event:')) continue;
            if (!str_starts_with($line, 'data: ')) continue;

            $json = substr($line, 6);
            $chunk = json_decode($json, true);
            if (!$chunk) continue;

            $delta = '';
            if ($provider === 'anthropic') {
                if (($chunk['type'] ?? '') === 'content_block_delta') {
                    $delta = $chunk['delta']['text'] ?? '';
                }
            } else {
                // OpenAI compatible
                $delta = $chunk['choices'][0]['delta']['content'] ?? '';
            }

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

function llm_chat(array $messages, string $provider, string $apiKey, string $baseUrl, string $model, float $temperature = 0.4, int $maxTokens = 8192): string
{
    if ($provider === 'anthropic') {
        $systemPrompt = '';
        $anthropicMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
            } else {
                $anthropicMessages[] = $msg;
            }
        }

        $payloadArr = [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];
        if ($systemPrompt !== '') {
            $payloadArr['system'] = $systemPrompt;
        }
        $payload = json_encode($payloadArr);

        $url = $baseUrl . '/messages';
        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ];
    } else {
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ]);

        $url = $baseUrl . '/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return 'Error: API returned HTTP ' . $httpCode . ' : ' . $response;
    }

    $data = json_decode($response, true);

    if ($provider === 'anthropic') {
        return $data['content'][0]['text'] ?? 'No response.';
    } else {
        return $data['choices'][0]['message']['content'] ?? 'No response.';
    }
}
