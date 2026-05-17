<?php
require_once __DIR__ . '/BaseProvider.php';

class GeminiProvider extends BaseProvider {
    public function getName(): string { return 'gemini'; }
    public function getDefaultModel(): string { return 'gemini-2.0-flash'; }

    public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string {
        if (!$this->hasApiKey()) return null;
        $model = $model ?: $this->getDefaultModel();

        // Convert OpenAI-style messages to Gemini format
        $contents = [];
        $systemInstructions = null;
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstructions = $msg['content'];
                continue;
            }
            $contents[] = ['role' => $msg['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $msg['content']]]];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
        ];
        if ($systemInstructions) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstructions]]];
        }

        $url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
        $result = $this->curlPost($url, $payload, ['Content-Type: application/json']);
        if (!$result) return null;
        $data = json_decode($result, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
