<?php
abstract class BaseProvider {
    protected string $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    abstract public function chat(array $messages, string $model, float $temperature, int $maxTokens): ?string;
    abstract public function getName(): string;
    abstract public function getDefaultModel(): string;

    public function hasApiKey(): bool { return !empty($this->apiKey); }

    protected function curlPost(string $url, array $payload, array $headers, int $timeout = 30): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        if ($err) { error_log('[' . $this->getName() . '] cURL: ' . $err); return null; }
        if ($httpCode < 200 || $httpCode >= 300) { error_log('[' . $this->getName() . "] HTTP {$httpCode}: " . substr($result, 0, 300)); return null; }
        return $result ?: null;
    }
}
