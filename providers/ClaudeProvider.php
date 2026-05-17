<?php
require_once __DIR__ . '/BaseProvider.php';

class ClaudeProvider extends BaseProvider {
    public function getName(): string { return 'claude'; }
    public function getDefaultModel(): string { return 'claude-haiku-4-5-20251001'; }

    public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string {
        if (!$this->hasApiKey()) return null;

        // Extract system message
        $system   = '';
        $filtered = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') { $system = $msg['content']; continue; }
            $filtered[] = $msg;
        }

        $payload = [
            'model'      => $model ?: $this->getDefaultModel(),
            'max_tokens' => $maxTokens,
            'messages'   => $filtered,
        ];
        if ($system) $payload['system'] = $system;

        $result = $this->curlPost(
            'https://api.anthropic.com/v1/messages',
            $payload,
            ['x-api-key: ' . $this->apiKey, 'anthropic-version: 2023-06-01', 'Content-Type: application/json']
        );
        if (!$result) return null;
        $data = json_decode($result, true);
        return $data['content'][0]['text'] ?? null;
    }
}
