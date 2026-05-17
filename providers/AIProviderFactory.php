<?php
require_once __DIR__ . '/GroqProvider.php';
require_once __DIR__ . '/OpenAIProvider.php';
require_once __DIR__ . '/GeminiProvider.php';
require_once __DIR__ . '/ClaudeProvider.php';
require_once __DIR__ . '/DeepSeekProvider.php';
require_once __DIR__ . '/OpenRouterProvider.php';

class AIProviderFactory {

    /**
     * Load AI settings from admin_settings table with defaults.
     */
    public static function getSettings(Database $db): array {
        try {
            $rows = $db->resultSet(
                "SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('ai_provider','ai_model','ai_temperature','ai_max_tokens','ai_api_key_groq','ai_api_key_openai','ai_api_key_gemini','ai_api_key_claude','ai_api_key_deepseek','ai_api_key_openrouter')"
            );
        } catch (Exception $e) {
            $rows = [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }

        $provider = $map['ai_provider'] ?? 'groq';

        return [
            'provider'    => $provider,
            'model'       => $map['ai_model'] ?? '',
            'temperature' => (float)($map['ai_temperature'] ?? 0.7),
            'max_tokens'  => (int)($map['ai_max_tokens'] ?? 1200),
            'api_key'     => $map['ai_api_key_' . $provider] ?? '',
        ];
    }

    /**
     * Create and return the configured AI provider, falling back to Groq.
     */
    public static function create(Database $db): BaseProvider {
        $s       = self::getSettings($db);
        $apiKey  = $s['api_key'];

        // For Groq, fall back to the config constant if no DB key
        if ($s['provider'] === 'groq' && empty($apiKey) && defined('GROQ_API_KEY')) {
            $apiKey = GROQ_API_KEY;
        }

        $provider = match($s['provider']) {
            'openai'     => new OpenAIProvider($apiKey),
            'gemini'     => new GeminiProvider($apiKey),
            'claude'     => new ClaudeProvider($apiKey),
            'deepseek'   => new DeepSeekProvider($apiKey),
            'openrouter' => new OpenRouterProvider($apiKey),
            default      => new GroqProvider($apiKey),
        };

        return $provider;
    }

    /**
     * Get a fast provider for short tasks (title gen, etc.) — prefers Groq for speed.
     */
    public static function createFast(Database $db): BaseProvider {
        try {
            $row    = $db->single("SELECT setting_value FROM admin_settings WHERE setting_key = 'ai_api_key_groq'");
            $apiKey = $row['setting_value'] ?? '';
        } catch (Exception $e) {
            $apiKey = '';
        }

        if (empty($apiKey) && defined('GROQ_API_KEY')) {
            $apiKey = GROQ_API_KEY;
        }

        return new GroqProvider($apiKey);
    }

    /**
     * Return the list of all supported providers with metadata.
     */
    public static function providers(): array {
        return [
            'groq'       => ['name' => 'Groq',       'models' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768', 'gemma2-9b-it'],       'color' => '#f55036'],
            'openai'     => ['name' => 'OpenAI',     'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],                                        'color' => '#10a37f'],
            'gemini'     => ['name' => 'Gemini',     'models' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],                     'color' => '#4285f4'],
            'claude'     => ['name' => 'Claude',     'models' => ['claude-sonnet-4-6', 'claude-opus-4-7', 'claude-haiku-4-5-20251001'],                             'color' => '#b07040'],
            'deepseek'   => ['name' => 'DeepSeek',   'models' => ['deepseek-chat', 'deepseek-reasoner'],                                                             'color' => '#2c6fad'],
            'openrouter' => ['name' => 'OpenRouter', 'models' => ['meta-llama/llama-3.3-70b-instruct:free', 'google/gemma-3-27b-it:free', 'mistralai/mistral-7b-instruct:free'], 'color' => '#6c5ce7'],
        ];
    }
}
