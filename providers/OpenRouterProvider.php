<?php
require_once __DIR__ . '/BaseProvider.php';

class OpenRouterProvider extends BaseProvider {
    public function getName(): string { return 'openrouter'; }
    public function getDefaultModel(): string { return 'meta-llama/llama-3.3-70b-instruct:free'; }

    public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string {
        if (!$this->hasApiKey()) return null;
        $result = $this->curlPost(
            'https://openrouter.ai/api/v1/chat/completions',
            ['model' => $model ?: $this->getDefaultModel(), 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens],
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json', 'HTTP-Referer: https://smartchashi.local', 'X-Title: SmartChashi']
        );
        if (!$result) return null;
        $data = json_decode($result, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
