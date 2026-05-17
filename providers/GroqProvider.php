<?php
require_once __DIR__ . '/BaseProvider.php';

class GroqProvider extends BaseProvider {
    public function getName(): string { return 'groq'; }
    public function getDefaultModel(): string { return 'llama-3.3-70b-versatile'; }

    public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string {
        if (!$this->hasApiKey()) return null;
        $result = $this->curlPost(
            'https://api.groq.com/openai/v1/chat/completions',
            ['model' => $model ?: $this->getDefaultModel(), 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens],
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json']
        );
        if (!$result) return null;
        $data = json_decode($result, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
