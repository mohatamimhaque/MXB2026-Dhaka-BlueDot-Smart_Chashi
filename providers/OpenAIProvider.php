<?php
require_once __DIR__ . '/BaseProvider.php';

class OpenAIProvider extends BaseProvider {
    public function getName(): string { return 'openai'; }
    public function getDefaultModel(): string { return 'gpt-4o-mini'; }

    public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string {
        if (!$this->hasApiKey()) return null;
        $result = $this->curlPost(
            'https://api.openai.com/v1/chat/completions',
            ['model' => $model ?: $this->getDefaultModel(), 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens],
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json']
        );
        if (!$result) return null;
        $data = json_decode($result, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
