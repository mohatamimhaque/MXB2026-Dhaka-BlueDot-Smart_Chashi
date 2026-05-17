# AI Providers

> Smart Chashi uses a pluggable AI provider system. The primary chat provider, disease detection model, and fast/utility model are independently configurable. Admins can switch providers from the admin panel with zero code changes.

---

## Table of Contents

1. [Overview](#overview)
2. [Provider Architecture](#provider-architecture)
3. [Available Providers](#available-providers)
4. [Factory & Selection Logic](#factory--selection-logic)
5. [BaseProvider Interface](#baseprovider-interface)
6. [Adding a New Provider](#adding-a-new-provider)
7. [Provider Setup Guides](#provider-setup-guides)
8. [Switching the Primary Provider](#switching-the-primary-provider)
9. [AI Usage Tracking](#ai-usage-tracking)
10. [What Each Provider Is Used For](#what-each-provider-is-used-for)
11. [Cost & Rate Limit Reference](#cost--rate-limit-reference)

---

## Overview

### Design Goals

| Goal | Implementation |
|------|---------------|
| Swap AI backend without code changes | `AIProviderFactory::create($db)` reads active provider from DB |
| Fallback for critical paths | Fast model always on GROQ regardless of primary |
| Token usage visibility | Every AI call logged to `ai_usage_logs` |
| Developer-extensible | Abstract `BaseProvider` — add a provider in ~50 lines |
| Admin-controllable | No config file edit needed — change from admin panel UI |

### Provider Roles

| Role | Provider | Configurable |
|------|----------|-------------|
| **Primary chat** (Chashi Bhai agent) | GROQ (default) | Yes — admin panel |
| **Fast / utility** (title gen, follow-ups) | GROQ llama-3.1-8b-instant | No — always GROQ |
| **Disease detection** (vision) | Google Gemini | No — always Gemini |
| **Crop photo backup** (vision) | Plant.id API | No — always Plant.id |

---

## Provider Architecture

```
┌─────────────────────────────────────────────┐
│              AIProviderFactory              │
│                                             │
│  create($db)     → reads ai_providers table │
│                    returns active provider  │
│                                             │
│  createFast($db) → always GroqProvider      │
│                    (llama-3.1-8b-instant)   │
└──────────────────────┬──────────────────────┘
                       │ instantiates
                       ▼
┌─────────────────────────────────────────────┐
│              BaseProvider (abstract)        │
│                                             │
│  + chat(messages[], options): string        │
│  + getModelName(): string                   │
│  # callAPI(url, payload, headers): array    │
│  # $apiKey: string                          │
└──────────────────────┬──────────────────────┘
                       │ extends
          ┌────────────┼──────────────────────┐
          ▼            ▼                      ▼
  ┌──────────────┐ ┌──────────────┐  ┌──────────────────┐
  │ GroqProvider │ │GeminiProvider│  │ ClaudeProvider   │
  │ OpenAI-compat│ │Google Vision │  │ Anthropic API    │
  └──────────────┘ └──────────────┘  └──────────────────┘
          ▼            ▼                      ▼
  ┌──────────────┐ ┌──────────────┐  ┌──────────────────┐
  │OpenAIProvider│ │DeepSeekProv. │  │OpenRouterProvider│
  │ GPT-4o-mini  │ │ deepseek-chat│  │ 100+ models      │
  └──────────────┘ └──────────────┘  └──────────────────┘
```

---

## Available Providers

| File | Provider | Default Model | API Endpoint |
|------|----------|--------------|-------------|
| `GroqProvider.php` | GROQ | `llama-3.3-70b-versatile` | `api.groq.com` |
| `GeminiProvider.php` | Google Gemini | `gemini-1.5-flash` | `generativelanguage.googleapis.com` |
| `ClaudeProvider.php` | Anthropic Claude | `claude-3-5-haiku-20241022` | `api.anthropic.com` |
| `OpenAIProvider.php` | OpenAI | `gpt-4o-mini` | `api.openai.com` |
| `DeepSeekProvider.php` | DeepSeek | `deepseek-chat` | `api.deepseek.com` |
| `OpenRouterProvider.php` | OpenRouter | Configurable | `openrouter.ai` |

---

## Factory & Selection Logic

```php
// In agent/api/send.php
require_once __DIR__ . '/../../providers/AIProviderFactory.php';

// Primary provider — reads ai_providers table WHERE is_active = 1
$provider = AIProviderFactory::create($db);

// Fast provider — always GROQ llama-3.1-8b-instant (ignores DB setting)
$fastProvider = AIProviderFactory::createFast($db);

// Both expose the same interface
$response = $provider->chat($messages, ['max_tokens' => 1024]);
$modelName = $provider->getModelName();
```

### Factory Logic (simplified)

```
AIProviderFactory::create($db)
  1. SELECT provider_name, model_name FROM ai_providers WHERE is_active = 1
  2. If no result → fallback to GroqProvider with default model
  3. Match provider_name to class:
       'groq'       → new GroqProvider(GROQ_API_KEY, model)
       'gemini'     → new GeminiProvider(GEMINI_API_KEY, model)
       'claude'     → new ClaudeProvider(CLAUDE_API_KEY, model)
       'openai'     → new OpenAIProvider(OPENAI_API_KEY, model)
       'deepseek'   → new DeepSeekProvider(DEEPSEEK_API_KEY, model)
       'openrouter' → new OpenRouterProvider(OPENROUTER_API_KEY, model)
  4. Return provider instance
```

---

## BaseProvider Interface

```php
abstract class BaseProvider {

    protected string $apiKey;
    protected string $model;

    public function __construct(string $apiKey, string $model) {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    /**
     * Send a chat completion request.
     * $messages: array of {role: 'user'|'assistant'|'system', content: string}
     * $options:  optional overrides (max_tokens, temperature, etc.)
     * Returns: the assistant's response text (string)
     */
    abstract public function chat(array $messages, array $options = []): string;

    /**
     * Returns the model identifier string (e.g. 'llama-3.3-70b-versatile')
     */
    abstract public function getModelName(): string;

    /**
     * Internal HTTP helper — sends JSON POST, returns decoded response array
     */
    protected function callAPI(string $url, array $payload, array $headers): array;
}
```

---

## Adding a New Provider

1. Create `providers/MyNewProvider.php`:

```php
<?php
require_once __DIR__ . '/BaseProvider.php';

class MyNewProvider extends BaseProvider {

    public function chat(array $messages, array $options = []): string {
        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        $response = $this->callAPI('https://api.example.com/v1/chat', $payload, $headers);
        return $response['choices'][0]['message']['content'] ?? '';
    }

    public function getModelName(): string {
        return $this->model;
    }
}
```

2. Register in `AIProviderFactory.php`:
```php
case 'mynew':
    return new MyNewProvider(MY_NEW_API_KEY, $model);
```

3. Add constant in `config/config.php`:
```php
define('MY_NEW_API_KEY', 'key_...');
```

4. Add the provider option in `admin-secure/pages/admin-ai.php` dropdown.

---

## Provider Setup Guides

### GROQ (Recommended — Free Tier)

1. Sign up at [console.groq.com](https://console.groq.com)
2. Dashboard → API Keys → Create key
3. Add to `config/config.php`:
   ```php
   define('GROQ_API_KEY', 'gsk_...');
   ```

**Free tier limits:**
- `llama-3.3-70b-versatile`: 14,400 tokens/min, 1,000 req/day
- `llama-3.1-8b-instant`: 20,000 tokens/min, 14,400 req/day

**Models available:**
| Model | Use Case | Speed |
|-------|---------|-------|
| `llama-3.3-70b-versatile` | Main agent responses | Medium |
| `llama-3.1-8b-instant` | Titles, follow-ups (fast provider) | Fast |

---

### Google Gemini (Disease Detection — Required)

Gemini is **always** used for disease detection regardless of the primary provider setting.

1. Visit [aistudio.google.com](https://aistudio.google.com) → Get API Key
2. Add to `config/config.php`:
   ```php
   define('GEMINI_API_KEY', 'AIza...');
   ```

**Used in:** `api/disease/analyze.php`  
**Model:** `gemini-1.5-flash` (vision capable)  
**Free tier:** 1,500 requests/day, 1 million tokens/minute

---

### Anthropic Claude (Optional)

1. Visit [console.anthropic.com](https://console.anthropic.com) → API Keys
2. Add to `config/config.php`:
   ```php
   define('CLAUDE_API_KEY', 'sk-ant-...');
   ```
3. Switch to Claude via admin panel

**Default model:** `claude-3-5-haiku-20241022`  
**Context window:** 200K tokens (handles very long conversations)

---

### OpenAI (Optional)

1. Visit [platform.openai.com](https://platform.openai.com) → API Keys
2. Add to `config/config.php`:
   ```php
   define('OPENAI_API_KEY', 'sk-...');
   ```

**Default model:** `gpt-4o-mini`  
**Notes:** OpenAI uses a credit-based system. Monitor usage at platform.openai.com/usage.

---

### DeepSeek (Optional — Cost-Effective)

1. Visit [platform.deepseek.com](https://platform.deepseek.com) → API Keys
2. Add to `config/config.php`:
   ```php
   define('DEEPSEEK_API_KEY', '...');
   ```

**Default model:** `deepseek-chat`  
**Notes:** Very low cost per token; strong multilingual performance.

---

### OpenRouter (Optional — Multi-model Gateway)

Access 100+ models from different providers through a single API.

1. Visit [openrouter.ai](https://openrouter.ai) → Keys
2. Add to `config/config.php`:
   ```php
   define('OPENROUTER_API_KEY', 'sk-or-...');
   ```
3. In the admin panel, set the model string to any OpenRouter-supported model  
   (e.g., `anthropic/claude-3.5-sonnet`, `google/gemini-pro`, `meta-llama/llama-3.3-70b`)

**Notes:** OpenRouter charges pass-through pricing. Useful for testing different models without separate API accounts.

---

## Switching the Primary Provider

1. Log in as admin → `http://localhost/smartchashi/admin-secure/`
2. Navigate to **AI Providers** (`admin-ai`)
3. Select provider from the dropdown
4. Enter or select the model name
5. Click **Test Connection** to verify the key works
6. Click **Save** — takes effect immediately for all new agent requests

The change writes to `ai_providers` table (`is_active = 1`). No server restart needed.

---

## AI Usage Tracking

Every call to any provider (primary or fast) is logged to `ai_usage_logs`:

```sql
CREATE TABLE ai_usage_logs (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    user_id           INT,
    provider          VARCHAR(50),
    model             VARCHAR(100),
    prompt_tokens     INT,
    completion_tokens INT,
    total_tokens      INT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Viewing Usage

- Admin Panel → **Analytics** → **AI Usage** tab
- Shows: token breakdown by provider, daily/weekly totals, top users

### Usage in Code

```php
// Logged automatically in agent/api/send.php after each chat() call
$db->query("INSERT INTO ai_usage_logs (user_id, provider, model, prompt_tokens, completion_tokens, total_tokens)
            VALUES (?, ?, ?, ?, ?, ?)")
   ->bind(1, $userId)->bind(2, $providerName)->bind(3, $modelName)
   ->bind(4, $prompt_tokens)->bind(5, $completion_tokens)->bind(6, $total_tokens)
   ->execute();
```

---

## What Each Provider Is Used For

| Location in Code | Provider Used | Purpose |
|-----------------|--------------|---------|
| `agent/api/send.php` (main response) | Primary (admin-configured) | Chashi Bhai AI reply |
| `agent/api/send.php` (title gen) | Fast (GROQ 8B) | Auto-generate conversation title |
| `agent/api/send.php` (follow-ups) | Fast (GROQ 8B) | Suggest 3 follow-up questions |
| `api/disease/analyze.php` | Gemini Vision | Crop disease identification from image |
| `api/disease/analyze.php` (backup) | Plant.id API | Secondary plant identification |

---

## Cost & Rate Limit Reference

| Provider | Model | Input $/1M tokens | Output $/1M tokens | Free Tier |
|----------|-------|------------------|-------------------|-----------|
| GROQ | llama-3.3-70b | $0 (free tier) | $0 (free tier) | 14,400 req/day |
| GROQ | llama-3.1-8b | $0 (free tier) | $0 (free tier) | 14,400 req/day |
| Gemini | gemini-1.5-flash | ~$0.075 | ~$0.30 | 1,500 req/day |
| Claude | claude-3-5-haiku | $0.80 | $4.00 | No |
| OpenAI | gpt-4o-mini | $0.15 | $0.60 | No |
| DeepSeek | deepseek-chat | $0.14 | $0.28 | No |
| OpenRouter | varies | pass-through | pass-through | $1 credit on signup |

> Prices approximate as of 2025 — check provider dashboards for current rates.

### Application-Level Rate Limits

In addition to provider limits, Smart Chashi enforces:

| Limit | Value | Scope |
|-------|-------|-------|
| Agent requests | 30 per 60 seconds | Per PHP session |
| Disease detection | No explicit limit | Provider quota applies |
