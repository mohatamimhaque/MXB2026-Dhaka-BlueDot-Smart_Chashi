<?php
ini_set('display_errors', '0');
ob_start();
/**
 * Agent Message Send API — Chashi Bhai Backend v2
 *
 * Dual-path processing:
 *
 * CASE 1 — Image(s) submitted (image | image_with_text):
 *   1. Validate format + size (JPEG/PNG/WebP/GIF, <= 10 MB each)
 *   2. Save images to disk for persistent display on reload
 *   3. Sequential Disease Detection API calls, one per image
 *   4. Combine all analyses into structured disease context
 *   5. Build image-optimised system prompt
 *   6. AI returns: disease name, symptoms, treatment, prevention, next steps
 *
 * CASE 2 — Text only:
 *   1. Session cache (30-min TTL, first messages only)
 *   2. Greeting shortcut (bypass LLM)
 *   3. Language detection (Bengali / English)
 *   4. Weather -> Open-Meteo 5-day forecast
 *   5. RAG Knowledge Base (9 agricultural domains)
 *   6. Few-shot examples
 *   7. Bangladesh research data (BRRI/BARI/DAE/BARC)
 *   8. FAO sustainability guidelines
 *   9. NASA EONET natural hazard events
 *  10. DuckDuckGo web search (prices, current news)
 *  11. User memory injection
 *
 * SHARED (both paths):
 *   A. Dynamic AI provider (reads config from admin_settings DB)
 *   B. Railway API fallback if primary fails
 *   C. Conversation title generation (first turn only)
 *   D. Follow-up suggestion generation
 *   E. Persist messages + extract user memory facts
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../providers/AIProviderFactory.php';
header('Content-Type: application/json; charset=utf-8');

// Ensure any uncaught exception returns JSON (not an HTML error page)
// so the JS catch block sees a parseable response, not a network error.
set_exception_handler(function (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred. Please try again.',
        'code'    => 'SERVER_ERROR',
        'error'   => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

if (!isLoggedIn()) {
    jsonOut(['success' => false, 'message' => 'Login required.', 'code' => 'AUTH_REQUIRED']);
}

$db     = new Database();
$userId = $_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];

// ── Input parsing ─────────────────────────────────────────────────────────
$cid         = trim($body['conversation_id'] ?? '');
$message     = trim($body['message'] ?? '');
$location    = trim($body['location'] ?? 'Bangladesh') ?: 'Bangladesh';
$personality = trim($body['personality'] ?? 'general');
$forceLang   = trim($body['lang'] ?? '');

// Accept [{data, mime, name?}] array or legacy single image_data/image_mime
$rawImages  = is_array($body['images'] ?? null) ? $body['images'] : [];
$singleData = trim($body['image_data'] ?? '');
$singleMime = trim($body['image_mime'] ?? 'image/jpeg');
if ($singleData && empty($rawImages)) {
    $rawImages = [['data' => $singleData, 'mime' => $singleMime, 'name' => 'image.jpg']];
}

$hasImages = !empty($rawImages);
$queryType = !$hasImages ? 'text' : (trim($message) !== '' ? 'image_with_text' : 'image');

if (!$hasImages && $message === '') {
    jsonOut(['success' => false, 'message' => 'Please enter a message or attach an image.', 'code' => 'EMPTY_REQUEST']);
}

// ── Rate limiting: 30 req / min per user ─────────────────────────────────
if (!isset($_SESSION['req_log'])) $_SESSION['req_log'] = [];
$now = time();
$_SESSION['req_log'] = array_values(array_filter($_SESSION['req_log'], fn($t) => $t > $now - 60));
if (count($_SESSION['req_log']) >= 30) {
    jsonOut(['success' => false, 'message' => 'Too many requests. Please wait a moment.', 'code' => 'RATE_LIMITED']);
}
$_SESSION['req_log'][] = $now;

global $SYSTEM_SETTINGS;
$settings      = is_array($SYSTEM_SETTINGS) ? $SYSTEM_SETTINGS : [];
$diseaseApiUrl = $settings['disease_detection_api_url'] ?? '';

// =============================================================================
// CASE 1 — IMAGE PIPELINE
// =============================================================================
$storedImagePaths = [];  // saved to disk -> shown on reload
$imageAnalyses    = [];  // structured per-image disease results
$diseaseContext   = '';  // combined text injected into system prompt

if ($hasImages) {
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $maxBytes     = 10 * 1024 * 1024; // 10 MB

    foreach ($rawImages as $idx => $imgItem) {
        $imgData = trim($imgItem['data'] ?? '');
        $imgMime = strtolower(trim($imgItem['mime'] ?? 'image/jpeg'));
        $imgName = trim($imgItem['name'] ?? ('Image ' . ($idx + 1)));
        $num     = $idx + 1;

        if (!$imgData) continue;

        // Validate format
        if (!in_array($imgMime, $allowedMimes, true)) {
            jsonOut([
                'success' => false,
                'message' => "Image {$num}: unsupported format '{$imgMime}'. Please upload JPEG, PNG, WebP, or GIF.",
                'code'    => 'INVALID_FORMAT',
            ]);
        }

        // Strip data URI prefix, decode, validate size
        $rawBase64 = preg_replace('/^data:[^;]+;base64,/', '', $imgData);
        $decoded   = base64_decode($rawBase64, true);

        if ($decoded === false || strlen($decoded) < 100) {
            jsonOut([
                'success' => false,
                'message' => "Image {$num} could not be decoded. Please re-upload.",
                'code'    => 'INVALID_IMAGE_DATA',
            ]);
        }

        if (strlen($decoded) > $maxBytes) {
            jsonOut([
                'success' => false,
                'message' => "Image {$num} exceeds the 10 MB limit. Please compress and re-upload.",
                'code'    => 'IMAGE_TOO_LARGE',
            ]);
        }

        // Save to disk (persistent storage for conversation reload)
        $savedPath = saveImageToDisk($rawBase64, $imgMime, $userId);
        if ($savedPath) $storedImagePaths[] = $savedPath;

        // Disease Detection API (sequential, one per image)
        [$analysis, $errorMsg] = processAgentImage($rawBase64, $imgMime, $diseaseApiUrl);

        if ($errorMsg) {
            jsonOut([
                'success'       => false,
                'message'       => $errorMsg,
                'code'          => 'IMAGE_NOT_CROP',
                'image_invalid' => true,
            ]);
        }

        $imageAnalyses[] = [
            'index'   => $num,
            'name'    => $imgName,
            'context' => $analysis,
        ];
    }

    // Combine all analyses into one context block
    if (count($imageAnalyses) === 1) {
        $diseaseContext = $imageAnalyses[0]['context'];
    } else {
        foreach ($imageAnalyses as $a) {
            if ($a['context']) {
                $diseaseContext .= "--- Image {$a['index']}: {$a['name']} ---\n{$a['context']}\n\n";
            }
        }
    }

    // Default message for image-only submissions
    if ($message === '') {
        $count   = count($imageAnalyses);
        $message = $count > 1
            ? "Please analyze these {$count} crop images and provide comprehensive advice on any diseases, treatments, and farming recommendations."
            : 'Please analyze this crop image and provide advice on any diseases, treatments, and farming recommendations.';
    }
}

// ── Get or create conversation ────────────────────────────────────────────
if ($cid) {
    $convo = $db->single(
        "SELECT * FROM agent_conversations WHERE conversation_id = ? AND user_id = ?",
        [$cid, $userId]
    );
    if (!$convo) {
        jsonOut(['success' => false, 'message' => 'Conversation not found.', 'code' => 'NOT_FOUND']);
    }
} else {
    $cid = bin2hex(random_bytes(16));
    $db->query("INSERT INTO agent_conversations (conversation_id, user_id, title) VALUES (?,?,?)")
       ->bind(1, $cid)->bind(2, $userId)->bind(3, 'New Chat')->execute();
    $convo = ['conversation_id' => $cid, 'title' => 'New Chat'];
}

// Load last 8 messages as conversation history
$history = $db->resultSet(
    "SELECT role, content FROM (
        SELECT role, content, created_at FROM agent_messages
        WHERE conversation_id = ?
        ORDER BY created_at DESC LIMIT 8
     ) sub ORDER BY created_at ASC",
    [$cid]
);
$isFirstMessage = count($history) === 0;

$userMemory = loadUserMemory($db, $userId);

// ── Language detection ────────────────────────────────────────────────────
$banglaChars  = preg_match_all('/[\x{0980}-\x{09FF}]/u', $message);
$totalChars   = mb_strlen(preg_replace('/\s+/', '', $message)) ?: 1;
$isBangla     = ($banglaChars / $totalChars) > 0.15;
$effectiveLang = $forceLang ?: ($isBangla ? 'bn' : 'en');

// =============================================================================
// CASE 2 — TEXT PIPELINE (skipped for pure image queries)
// =============================================================================
$hybridContext = '';
$cacheKey      = '';

if ($queryType === 'text') {
    // Session cache (30-min TTL, first messages only)
    if (!isset($_SESSION['agent_cache'])) $_SESSION['agent_cache'] = [];
    foreach (array_keys($_SESSION['agent_cache']) as $k) {
        if ($now - ($_SESSION['agent_cache'][$k]['time'] ?? 0) > 1800) unset($_SESSION['agent_cache'][$k]);
    }
    $cacheKey = 'r_' . md5($message . $location . $personality . $forceLang);
    if (isset($_SESSION['agent_cache'][$cacheKey]) && $isFirstMessage) {
        $c = $_SESSION['agent_cache'][$cacheKey];
        jsonOut([
            'success'         => true,
            'reply'           => $c['reply'],
            'detectedLang'    => $c['lang'],
            'translatedQuery' => $message,
            'conversation_id' => $cid,
            'title'           => $convo['title'],
            'followUps'       => [],
            'type'            => 'text',
            'cached'          => true,
        ]);
    }

    // Greeting shortcut (bypass LLM entirely)
    if (preg_match('/\b(hi|hello|hey|greetings|assalamu|salam)\b/iu', $message)
        || preg_match('/^(হ্যালো|সালাম|আস্সালামু|ওহে)\b/u', $message)) {

        $greeting = ($effectiveLang === 'bn')
            ? "**চাষী ভাই — আপনার কৃষি বিশেষজ্ঞ AI সহকারী**\n\nআসসালামু আলাইকুম! আমি চাষী ভাই। বাংলাদেশের কৃষক ভাইদের জন্য AI সহায়তা প্রদান করি।\n\n**আমি যা নিয়ে সাহায্য করতে পারি:**\n- ফসল ব্যবস্থাপনা ও চাষ পদ্ধতি\n- মাটি স্বাস্থ্য ও সার ব্যবস্থাপনা\n- পোকামাকড় ও রোগ নিয়ন্ত্রণ\n- আবহাওয়া ও সেচ পরামর্শ\n- বাজার মূল্য ও বিক্রয় কৌশল\n- সরকারি সুবিধা ও ভর্তুকি\n\nছবি পাঠান ফসলের রোগ শনাক্ত করতে, বা যেকোনো প্রশ্ন করুন!"
            : "**Chashi Bhai — Your Expert Agriculture AI Assistant**\n\nHello! I'm Chashi Bhai, your dedicated AI farming advisor for Bangladesh.\n\n**I can help you with:**\n- Crop management and cultivation techniques\n- Soil health, pH, and fertilizer planning\n- Pest and disease identification & control\n- Weather-based irrigation scheduling\n- Market prices and selling strategies\n- Government subsidies and extension services\n\nSend a crop photo for instant disease detection, or ask me anything about farming!";

        $greetingHtml = formatMarkdownToHtml($greeting);
        $greetMsgId   = saveMessages($db, $cid, $message, $greetingHtml, []);
        extractAndSaveMemory($db, $userId, $message);
        $newTitle = $isBangla ? 'শুভেচ্ছা ও পরিচয়' : 'Greeting & Introduction';
        if ($isFirstMessage) updateTitle($db, $cid, $newTitle);

        jsonOut([
            'success'         => true,
            'reply'           => $greetingHtml,
            'detectedLang'    => strtoupper($effectiveLang),
            'translatedQuery' => $message,
            'conversation_id' => $cid,
            'title'           => $isFirstMessage ? $newTitle : $convo['title'],
            'msg_id'          => $greetMsgId,
            'followUps'       => [],
            'type'            => 'text',
        ]);
    }

    // Knowledge layers (text path)
    $ragContext     = getRelevantKnowledge($message);
    $fewShot        = getRelevantFewShot($message);
    $bangladeshData = getBangladeshData($message);
    $faoData        = getFAOData($message);
    $nasaData       = fetchNasaEonetData($message);
    $webSearch      = fetchWebSearch($message);

    $weatherBlock = '';
    if (isForecastQuery($message)) {
        $coords = geocodeLocation($location);
        if ($coords) {
            [$lat, $lon, $locationName] = $coords;
            $weatherBlock = fetchOpenMeteoWeather($lat, $lon, $locationName);
        }
    }

    if ($fewShot)        $hybridContext .= "===== FEW-SHOT EXAMPLES =====\n{$fewShot}\n==============================\n\n";
    if ($ragContext)     $hybridContext .= "===== RAG KNOWLEDGE BASE =====\n{$ragContext}\n==============================\n\n";
    if ($bangladeshData) $hybridContext .= $bangladeshData . "\n\n";
    if ($faoData)        $hybridContext .= $faoData . "\n\n";
    if ($weatherBlock)   $hybridContext .= $weatherBlock . "\n\n";
    if ($nasaData)       $hybridContext .= $nasaData . "\n\n";
    if ($webSearch)      $hybridContext .= $webSearch . "\n\n";
}

// image_with_text also gets RAG context (but disease context is primary)
if ($queryType === 'image_with_text') {
    $ragContext = getRelevantKnowledge($message);
    if ($ragContext) $hybridContext .= "===== RAG KNOWLEDGE BASE =====\n{$ragContext}\n==============================\n\n";
}

// Always append disease context for image paths
if ($diseaseContext) {
    $hybridContext .= $diseaseContext;
}

// ── Build system prompt ───────────────────────────────────────────────────
$systemPrompt = buildSystemPrompt(
    $location, $hybridContext, $personality, $effectiveLang,
    $userMemory, $queryType, count($imageAnalyses)
);

// ── Build AI messages array ───────────────────────────────────────────────
$aiMessages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $h) {
    $aiMessages[] = ['role' => $h['role'], 'content' => strip_tags($h['content'])];
}
$aiMessages[] = ['role' => 'user', 'content' => $message];

// ── Primary AI provider ───────────────────────────────────────────────────
$aiProvider = AIProviderFactory::create($db);
$aiSettings = AIProviderFactory::getSettings($db);
$t0         = hrtime(true);
$reply      = $aiProvider->chat(
    $aiMessages,
    $aiSettings['model'],
    $aiSettings['temperature'],
    $aiSettings['max_tokens']
);
$responseMs = (int)((hrtime(true) - $t0) / 1e6);
logAiUsage($db, $userId, $cid, $aiProvider->getName(), $aiSettings['model'], $responseMs, $reply !== null, $reply ? '' : 'Provider returned null');

// ── Fallback 1: Secondary Groq model (llama-3.1-8b-instant) ─────────────
// Uses a smaller model with its own separate rate-limit quota.
if (!$reply) {
    $groqFallback = AIProviderFactory::createFast($db);
    if ($groqFallback->hasApiKey()) {
        $reply = $groqFallback->chat($aiMessages, 'llama-3.1-8b-instant', $aiSettings['temperature'], $aiSettings['max_tokens']);
        if ($reply) error_log('[Agent send.php] Groq fallback (llama-3.1-8b-instant) succeeded — user ' . $userId);
    }
}

// ── Fallback 2: Railway API (legacy, if configured) ───────────────────────
if (!$reply) {
    $apiBase = $settings['agent_api_url'] ?? '';
    if ($apiBase) {
        $ctxParts = [];
        foreach ($history as $h) {
            $ctxParts[] = ($h['role'] === 'user' ? 'User' : 'Assistant') . ': ' . mb_substr(strip_tags($h['content']), 0, 200);
        }
        $contextBlock  = $ctxParts ? "[Previous conversation]\n" . implode("\n", $ctxParts) . "\n[/Previous conversation]\n\n" : '';
        $railwayResult = curlPost($apiBase, ['message' => $contextBlock . $message, 'location' => $location]);
        if ($railwayResult) {
            $data  = json_decode($railwayResult, true);
            $reply = $data['reply'] ?? null;
        }
    }
}

if (!$reply) {
    error_log('[Agent send.php] All providers failed — user ' . $userId);
    jsonOut([
        'success' => false,
        'message' => 'Unable to get a response right now. Please try again in a moment.',
        'code'    => 'AI_FAILURE',
    ]);
}

// ── Format + persist ──────────────────────────────────────────────────────
$detectedLang   = strtoupper($effectiveLang);
$replyHtml      = formatMarkdownToHtml($reply);
$assistantMsgId = saveMessages($db, $cid, $message, $replyHtml, $storedImagePaths);
extractAndSaveMemory($db, $userId, $message);

// ── Title + follow-ups ────────────────────────────────────────────────────
$fastProvider = AIProviderFactory::createFast($db);
$title        = $convo['title'];
if ($isFirstMessage || $title === 'New Chat') {
    $title = generateConversationTitle($message, $fastProvider);
    updateTitle($db, $cid, $title);
} else {
    $db->query("UPDATE agent_conversations SET updated_at = NOW() WHERE conversation_id = ?")
       ->bind(1, $cid)->execute();
}

$followUps = generateFollowUps($message, $replyHtml, $fastProvider, $isBangla);

// Cache text-only first responses
if ($queryType === 'text' && $isFirstMessage && $cacheKey) {
    $_SESSION['agent_cache'][$cacheKey] = ['reply' => $replyHtml, 'lang' => $detectedLang, 'time' => $now];
}

// ── Final JSON response ───────────────────────────────────────────────────
jsonOut([
    'success'          => true,
    'reply'            => $replyHtml,
    'detectedLang'     => $detectedLang,
    'translatedQuery'  => $message,
    'conversation_id'  => $cid,
    'title'            => $title,
    'msg_id'           => $assistantMsgId,
    'followUps'        => $followUps,
    'type'             => $queryType,
    'image_count'      => count($imageAnalyses),
    'has_disease_data' => !empty($diseaseContext) && str_contains($diseaseContext, 'DISEASE DETECTION'),
]);


// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function jsonOut(array $data): never {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function saveMessages(Database $db, string $cid, string $userMsg, string $aiHtml, array $imagePaths = []): int {
    $imagesJson = empty($imagePaths) ? null : json_encode($imagePaths);
    try {
        // Requires migration_v3.sql: ALTER TABLE agent_messages ADD COLUMN images JSON NULL
        $db->query("INSERT INTO agent_messages (conversation_id, role, content, images) VALUES (?,?,?,?)")
           ->bind(1, $cid)->bind(2, 'user')->bind(3, $userMsg)
           ->bind(4, $imagesJson, $imagesJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR)
           ->execute();
    } catch (Exception $e) {
        // Fallback: insert without images column (migration_v3.sql not yet run)
        $db->query("INSERT INTO agent_messages (conversation_id, role, content) VALUES (?,?,?)")
           ->bind(1, $cid)->bind(2, 'user')->bind(3, $userMsg)->execute();
    }
    $db->query("INSERT INTO agent_messages (conversation_id, role, content) VALUES (?,?,?)")
       ->bind(1, $cid)->bind(2, 'assistant')->bind(3, $aiHtml)->execute();
    return (int)$db->lastInsertId();
}

function updateTitle(Database $db, string $cid, string $title): void {
    $db->query("UPDATE agent_conversations SET title = ?, updated_at = NOW() WHERE conversation_id = ?")
       ->bind(1, $title)->bind(2, $cid)->execute();
}

function saveImageToDisk(string $base64Data, string $mime, int $userId): ?string {
    $ext = match(true) {
        str_contains($mime, 'png')  => 'png',
        str_contains($mime, 'webp') => 'webp',
        str_contains($mime, 'gif')  => 'gif',
        default                      => 'jpg',
    };
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        error_log('[saveImageToDisk] Cannot create uploads dir'); return null;
    }
    $filename = $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $decoded  = base64_decode($base64Data, true);
    if (!$decoded) return null;
    if (file_put_contents($uploadDir . $filename, $decoded) === false) {
        error_log("[saveImageToDisk] Cannot write {$filename}"); return null;
    }
    return 'agent/uploads/' . $filename;
}

// Returns [$context, $errorMessage]. Non-empty $errorMessage = user-facing rejection.
function processAgentImage(string $base64Data, string $mime, string $diseaseApiUrl): array {
    $noApiCtx = "**SYSTEM NOTE — IMAGE SUBMITTED:**\nThe user has uploaded a crop/plant image. Automated disease detection is currently unavailable. Ask the farmer to describe the visible symptoms in detail (leaf colour changes, spots, lesions, wilting, patterns, smell), which plant parts are affected, the crop type and growth stage. Once they describe symptoms, provide diagnosis and treatment advice. Do NOT mention technical limitations.";

    if (empty(trim($diseaseApiUrl))) return [$noApiCtx, ''];

    $ext     = match(true) {
        str_contains($mime, 'png')  => 'png',
        str_contains($mime, 'webp') => 'webp',
        str_contains($mime, 'gif')  => 'gif',
        default                      => 'jpg',
    };
    $tmpFile = tempnam(sys_get_temp_dir(), 'chashi_') . '.' . $ext;
    file_put_contents($tmpFile, base64_decode($base64Data, true));

    $ch = curl_init($diseaseApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'image' => new CURLFile($tmpFile, $mime, 'crop.' . $ext),
            'crop'  => '',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($tmpFile);

    if (!$result || $httpCode !== 200) {
        error_log("[processAgentImage] Disease API HTTP {$httpCode}");
        return [$noApiCtx, ''];
    }

    $resp = json_decode($result, true);

    if (!($resp['success'] ?? false)) {
        if (($resp['error_code'] ?? '') === 'NOT_CROP') {
            return ['', 'This image does not appear to be a crop, plant, or agricultural photo. Please upload a clear photo of the affected plant or field.'];
        }
        return ["**SYSTEM NOTE — IMAGE ANALYSIS ERROR:**\nThe detection system returned an error. Ask the farmer to describe the visible symptoms so you can diagnose and advise.", ''];
    }

    $d = $resp['data'] ?? [];

    $ctx  = "===== DISEASE DETECTION API ANALYSIS =====\n";
    $ctx .= "Crop:              " . ($d['crop'] ?? 'Unknown') . "\n";
    $ctx .= "Disease/Condition: " . ($d['disease_name'] ?? 'Unknown') . "\n";
    if (!empty($d['disease_name_bn'])) $ctx .= "Bengali name:      " . $d['disease_name_bn'] . "\n";
    $ctx .= "Confidence:        " . ($d['confidence'] ?? '?') . "%\n";
    $ctx .= "Severity:          " . ($d['severity'] ?? 'unknown') . "\n";
    $ctx .= "Healthy:           " . (($d['is_healthy'] ?? false) ? 'YES' : 'NO') . "\n";
    $ctx .= "Uncertain:         " . (($d['is_uncertain'] ?? false) ? 'YES' : 'NO') . "\n";
    if (!empty($d['symptoms']))          $ctx .= "\nSymptoms:\n"           . $d['symptoms'] . "\n";
    if (!empty($d['symptoms_bn']))       $ctx .= "Symptoms (bn):\n"       . $d['symptoms_bn'] . "\n";
    if (!empty($d['treatment']))         $ctx .= "\nTreatment:\n"          . $d['treatment'] . "\n";
    if (!empty($d['treatment_bn']))      $ctx .= "Treatment (bn):\n"      . $d['treatment_bn'] . "\n";
    if (!empty($d['organic_treatment'])) $ctx .= "\nOrganic Treatment:\n"  . $d['organic_treatment'] . "\n";
    if (!empty($d['prevention']))        $ctx .= "\nPrevention:\n"         . $d['prevention'] . "\n";
    $ctx .= "===========================================\n";
    $ctx .= "\nIMPORTANT: Use the above structured analysis as your primary reference. Give the farmer complete, actionable advice. Mention specific product names, dosages, and timing.\n";

    return [$ctx, ''];
}

function buildSystemPrompt(
    string $location, string $hybridContext, string $personality,
    string $effectiveLang, string $userMemory,
    string $queryType, int $imageCount
): string {
    $langRule = $effectiveLang === 'bn'
        ? "\n\n**LANGUAGE — MANDATORY:** Write your ENTIRE response in Bengali (বাংলা). Use English only for scientific or technical terms with no Bangla equivalent."
        : "\n\n**LANGUAGE — MANDATORY:** Write your ENTIRE response in English. Do NOT switch to Bengali script under any circumstance.";

    $contextBlock = $hybridContext
        ? "\n\n---\n**KNOWLEDGE CONTEXT (factual reference to ground your answer):**\n{$hybridContext}\n---"
        : '';

    $memoryBlock = $userMemory
        ? "\n\n**USER PROFILE (personalise your response using these known facts):**\n{$userMemory}"
        : '';

    // Image-specific prompt
    if ($queryType === 'image' || $queryType === 'image_with_text') {
        $imgNote     = $imageCount > 1
            ? "The farmer has submitted {$imageCount} crop images."
            : "The farmer has submitted a crop/plant image.";
        $hasAnalysis = str_contains($hybridContext, 'DISEASE DETECTION API ANALYSIS');

        $modeBlock = $hasAnalysis
            ? <<<MODE

**IMAGE ANALYSIS MODE — DISEASE DETECTION RESULTS AVAILABLE**

{$imgNote} A disease detection model has already analysed the image(s). The structured results appear in the KNOWLEDGE CONTEXT below.

Your structured response MUST follow this format:
1. **Disease / Condition** — State the detected disease clearly, including confidence and severity.
2. **Symptoms to Confirm** — Key visible signs the farmer should verify on their plant.
3. **Chemical Treatment** — Specific product names, dosage, application method, timing, and pre-harvest intervals.
4. **Organic / IPM Alternative** — A practical organic treatment a smallholder can realistically apply.
5. **Prevention for Next Season** — 2-3 concrete steps.
6. **Immediate Next Steps** — Exactly what the farmer should do today.

Rules:
- NEVER say you cannot see or analyse images — the analysis is already done.
- If image is marked healthy: congratulate the farmer and give maintenance advice.
- If confidence is low or result is uncertain: guide the farmer to describe symptoms for confirmation.
- If multiple images were analysed, address each one.
MODE
            : <<<MODE

**IMAGE SUBMITTED — AUTOMATED ANALYSIS UNAVAILABLE**

{$imgNote} The automated disease detection system is currently unavailable.

Your response MUST:
1. Acknowledge the image was received.
2. Ask the farmer to describe visible symptoms (leaf colour, spots, lesions, wilting, smell).
3. Ask which plant parts are affected and the crop growth stage.
4. Once they describe symptoms, diagnose and provide complete treatment advice.
5. Do NOT say you are text-only or cannot process images.
MODE;

        return <<<PROMPT
You are **Chashi Bhai (চাষী ভাই)**, an expert agricultural AI and plant pathologist serving Bangladesh farmers on the Smart Chashi platform.
{$modeBlock}

**Your Expertise:**
- Plant disease identification (fungal, bacterial, viral, nutrient deficiencies)
- Chemical treatments with exact dosage rates and brand names available in Bangladesh
- Organic and IPM alternatives for smallholder farmers
- BRRI/BARI recommended varieties and disease-resistant options
- Bangladesh crop calendar and seasonal crop management
{$langRule}{$contextBlock}{$memoryBlock}

**Current user location:** {$location}
PROMPT;
    }

    // Text-only prompt
    $personalityNote = match($personality) {
        'pest'   => "\n\n**ACTIVE MODE: Pest & Disease Expert** — Focus on pest identification, disease diagnosis, chemical and organic treatments with dosages, resistance management, and prevention.",
        'soil'   => "\n\n**ACTIVE MODE: Soil Scientist** — Focus on soil pH, nutrient deficiency diagnosis, amendments (lime, gypsum, ZnSO4, borax), composting, and precision fertilisation.",
        'market' => "\n\n**ACTIVE MODE: Market Advisor** — Focus on crop prices in Bangladesh, selling strategies, grading, cold storage, cooperatives, value-added processing, and middleman reduction.",
        'weather'=> "\n\n**ACTIVE MODE: Weather Analyst** — Focus on weather impact on crop decisions, irrigation scheduling from forecasts, disaster crop protection, drought/flood adaptation.",
        default  => '',
    };

    return <<<PROMPT
You are **Chashi Bhai (চাষী ভাই)**, the expert agricultural AI assistant for the Smart Chashi platform — built specifically for Bangladesh farmers. You have the combined expertise of a senior agricultural extension officer, soil scientist, crop pathologist, IPM specialist, and agricultural market analyst.

**Core Expertise:**
- All three Bangladesh rice seasons: Boro (Jan-May), Aman (Jul-Dec), Aus (Apr-Aug) — variety selection, timing, yield optimisation
- Pest and disease identification: symptom diagnosis, IPM protocols, chemical + organic treatments with exact dosages
- Soil health: pH management, NPK deficiencies, micronutrients (Zinc, Boron, Sulfur), compost, vermicompost
- Weather-based decisions: irrigation scheduling, flood/drought adaptation, AWD technique
- Market intelligence: price trends, cooperative selling, value-added processing, post-harvest loss reduction
- Government schemes: DAE subsidies, BADC programs, BRRI/BARI technology adoption, Krishi Card
- Modern technology: drip irrigation, solar pumps, mechanical transplanters, IoT sensors, precision agriculture

**Response Standards:**
- Give SPECIFIC, ACTIONABLE advice with exact quantities, timing, and product names
- For diseases and pests: always provide both chemical AND organic/IPM options
- For fertilizer: give exact kg per bigha/acre with split application timing
- Reference the user's location ({$location}) for localised advice
- Format: bold for key terms, bullet lists for steps, numbered lists for sequences
- Length: 200-500 words; go longer only for complex multi-part questions
- Tone: warm and knowledgeable like an expert friend, not clinical or bureaucratic
{$personalityNote}{$langRule}{$contextBlock}{$memoryBlock}

**Current user location:** {$location}
PROMPT;
}

function isForecastQuery(string $text): bool {
    foreach (['weather','forecast','rain','temperature','precipitation','wind','humidity',
              'next days','coming days','7 day','5 day','outlook',
              'আবহাওয়া','বৃষ্টি','তাপমাত্রা'] as $kw) {
        if (str_contains(mb_strtolower($text), $kw)) return true;
    }
    return false;
}

function geocodeLocation(string $location): ?array {
    if (in_array(mb_strtolower($location), ['bangladesh','বাংলাদেশ',''], true)) {
        return [23.8103, 90.4125, 'Dhaka, Bangladesh'];
    }
    $url    = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($location) . '&format=json&limit=1';
    $result = curlGet($url, ['User-Agent: SmartChashi-AgriBot/1.0']);
    if (!$result) return null;
    $data   = json_decode($result, true);
    if (!empty($data[0])) {
        return [(float)$data[0]['lat'], (float)$data[0]['lon'], $data[0]['display_name'] ?? $location];
    }
    return null;
}

function fetchOpenMeteoWeather(float $lat, float $lon, string $name): string {
    $params = 'temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,windspeed_10m_max,relative_humidity_2m_max';
    $url    = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&daily={$params}&timezone=auto&forecast_days=5";
    $result = curlGet($url);
    if (!$result) return '';
    $data  = json_decode($result, true);
    $daily = $data['daily'] ?? [];
    $times = $daily['time'] ?? [];
    if (empty($times)) return '';

    $lines = ["**5-Day Agricultural Forecast — {$name} (Open-Meteo)**\n"];
    foreach ($times as $i => $day) {
        $tMax = isset($daily['temperature_2m_max'][$i])            ? round($daily['temperature_2m_max'][$i], 1)        : null;
        $tMin = isset($daily['temperature_2m_min'][$i])            ? round($daily['temperature_2m_min'][$i], 1)        : null;
        $rain = isset($daily['precipitation_sum'][$i])             ? round($daily['precipitation_sum'][$i], 1)         : null;
        $prob = isset($daily['precipitation_probability_max'][$i]) ? (int)$daily['precipitation_probability_max'][$i]  : null;
        $wind = isset($daily['windspeed_10m_max'][$i])             ? round($daily['windspeed_10m_max'][$i], 1)         : null;
        $rh   = isset($daily['relative_humidity_2m_max'][$i])      ? (int)$daily['relative_humidity_2m_max'][$i]       : null;

        $parts = ["**{$day}**:"];
        if ($tMin !== null && $tMax !== null) $parts[] = "{$tMin}C-{$tMax}C";
        if ($rain !== null)  $parts[] = "{$rain}mm" . ($prob !== null ? " ({$prob}% chance)" : '');
        if ($wind !== null)  $parts[] = "{$wind} km/h";
        if ($rh !== null)    $parts[] = "RH {$rh}%";
        $lines[] = implode(' | ', $parts);
    }
    $lines[] = "\n**Agricultural Notes:**";
    $totalRain = array_sum(array_slice($daily['precipitation_sum'] ?? [], 0, 5));
    $maxTemps  = array_slice($daily['temperature_2m_max'] ?? [], 0, 5);
    $maxRH     = max(array_slice($daily['relative_humidity_2m_max'] ?? [], 0, 5) ?: [0]);
    if ($totalRain < 5)      $lines[] = "- Low rainfall this week — plan irrigation";
    elseif ($totalRain > 50) { $lines[] = "- Heavy rainfall — ensure drainage"; $lines[] = "- Delay fertilizer application"; }
    if ($maxTemps && max($maxTemps) > 35) $lines[] = "- Heat stress risk — irrigate and use shade nets for vegetables";
    if ($maxRH > 85) $lines[] = "- High humidity — increased fungal disease risk, monitor closely";
    $lines[] = "\nSource: Open-Meteo (real-time, no API key required)";
    return implode("\n", $lines);
}

function getRelevantKnowledge(string $text): string {
    $text = mb_strtolower($text);
    $kb = [
        'rice|ধান|paddy|বোরো|আমন|আউশ|dhan' => [
            'tags'    => ['rice','paddy','dhan','boro','aman','aus','ধান','বোরো','আমন'],
            'content' => "**Rice Cultivation (Bangladesh):**\n- Boro (Jan-May): BRRI dhan28, 29, 58, 88, 89 — high yield, irrigation-dependent.\n- Aman (Jul-Dec): BRRI dhan49, 50, 52, 71, 75 — rainfed, flood-tolerant.\n- Aus (Apr-Aug): BR26, BRRI dhan27, 48, 83 — drought-tolerant, short-duration.\n- AWD irrigation: saves 25-30% water without yield loss.\n- Blast (grey-centred spots): spray Tricyclazole 0.1%; BPH: spray Imidacloprid + drain field 7 days.\n- Yellow leaf (N deficiency): urea 10 kg/bigha; white patches, stunted (Zn deficiency): ZnSO4 2 kg/bigha.\n- Fertilizer per bigha: Urea 80 kg + TSP 22 kg + MoP 30 kg + Gypsum 25 kg.",
        ],
        'wheat|গম' => [
            'tags'    => ['wheat','গম'],
            'content' => "**Wheat Cultivation:**\n- Season: Nov-Dec sowing, Feb-Mar harvest.\n- Varieties: BARI Gom-25, 26 (heat tolerant), Shatabdi, Bijoy.\n- Yellow rust: spray Propiconazole (Tilt 250 EC) 0.1%.\n- Critical irrigation: crown root initiation (21 DAS) + grain filling (75 DAS).\n- Fertilizer/ha: Urea 130 kg, TSP 85 kg, MoP 55 kg.",
        ],
        'vegetable|সবজি|potato|আলু|tomato|টমেটো|brinjal|বেগুন|chili|মরিচ|cabbage' => [
            'tags'    => ['vegetable','tomato','cabbage','cauliflower','okra','potato','আলু','সবজি'],
            'content' => "**Vegetable Farming (Bangladesh):**\n- Winter veg (Oct-Feb): tomato, cabbage, cauliflower, potato, brinjal.\n- Summer veg (Mar-Jun): okra, bottle gourd, bitter gourd, snake gourd.\n- Potato: plant Oct-Dec, harvest Feb-Mar. BARI Alu 7, 25, 28, 41 for high yield.\n- Late blight: brownish-black spots -> spray Mancozeb 0.2% every 7 days.\n- Shoot borer (brinjal): pheromone traps + spinosad; remove infested shoots daily.\n- Drip irrigation: 60% water saving, 20-50% yield increase; 50% subsidy available.",
        ],
        'soil|মাটি|fertilizer|সার|urea|nitrogen|phosphorus|potash|compost|জৈব|deficiency' => [
            'tags'    => ['soil','fertility','compost','fertilizer','pH','urea','জৈব','মাটি','সার'],
            'content' => "**Soil & Fertilizer Management:**\n- Common deficiencies in Bangladesh: Zinc, Boron, Sulfur, Iron.\n- Balanced NPK: N:P:K = 3:1:2 ratio for most crops.\n- Split urea: 1/3 basal + 1/3 tillering + 1/3 panicle initiation.\n- Zinc deficiency (white patches, stunted): ZnSO4 2 kg/bigha.\n- Boron deficiency (hollow stem, poor fruit set): Borax 1 kg/bigha.\n- Target soil pH 5.5-7.0; lime to raise pH, sulfur to lower.\n- Organic: 5 t compost/ha or 2 t vermicompost/ha before planting.\n- Free soil testing at district BADC labs.",
        ],
        'pest|insect|কীটনাশক|পোকা|disease|রোগ|blight|blast|wilt|IPM' => [
            'tags'    => ['pest','disease','insect','IPM','organic','পোকা','রোগ'],
            'content' => "**Integrated Pest Management (IPM):**\n- Identify pest/disease correctly before applying any chemical.\n- Monitoring: pheromone traps, light traps, yellow sticky traps.\n- Biological: Trichogramma (50,000/ha), Bt for caterpillars, NPV for armyworm.\n- Neem oil 3-5 ml/L weekly: effective against soft-bodied insects.\n- Bordeaux mixture: fungal disease prevention.\n- Chemical as last resort: follow label, use PPE, avoid when bees are active.\n- IPM reduces pesticide cost 40-60% while maintaining yields.",
        ],
        'irrigation|সেচ|water|পানি|drip|AWD|flood|drought|খরা' => [
            'tags'    => ['irrigation','water','drip','solar','pump','সেচ','পানি'],
            'content' => "**Modern Irrigation Technologies:**\n- Drip irrigation: 40-70% water saving, 20-50% yield increase. ROI 2-3 years.\n- AWD for rice: saves 25% water, reduces methane emissions.\n- AWD method: irrigate only when water drops 15 cm below surface (perforated tube).\n- Solar pumps: no fuel cost, 20-year lifespan; 50% govt subsidy available.\n- Sprinkler: 25-40% water saving, good for leafy vegetables.",
        ],
        'weather|আবহাওয়া|forecast|climate|temperature|rainfall|flood|cyclone|drought' => [
            'tags'    => ['weather','climate','forecast','flood','drought','temperature','আবহাওয়া','বৃষ্টি'],
            'content' => "**Weather & Climate Management:**\n- Flood risk: Jun-Sep. Plant flood-tolerant BRRI dhan51, 52, 79.\n- Drought: mulching, AWD, short-duration varieties (BRRI dhan29, 58).\n- Heat stress: shade nets for vegetables, timely irrigation, mulching.\n- Pre-cyclone: harvest early-maturing crops; apply potash to strengthen stems.\n- BMD Weather app (free, Bengali) + DAE helpline 3331 for SMS alerts.",
        ],
        'market|বাজার|price|দাম|sell|profit|মুনাফা|economics' => [
            'tags'    => ['market','price','selling','profit','বাজার','দাম'],
            'content' => "**Market & Economics:**\n- Price info: Krishoker Janala app, DAE helpline 3331.\n- Cold storage: Bogura, Munshiganj, Jessore for potato/vegetables.\n- Smart Chashi Marketplace: sell directly, avoid 15-20% middleman commission.\n- Grade-A produce commands 20-30% premium; sort and clean before selling.\n- Value addition (processing, grading): increases profit 30-50%.",
        ],
        'government|subsidy|কৃষি|DAE|BADC|loan|ঋণ|scheme|organic|certification' => [
            'tags'    => ['government','subsidy','DAE','BADC','loan','scheme','certification'],
            'content' => "**Government Agricultural Support (Bangladesh):**\n- DAE: free advice, demo plots, subsidised seeds and training.\n- BADC: subsidised certified seeds for Boro, Aman, wheat.\n- Bangladesh Bank agricultural loan: 4% interest for small farmers (up to Tk 2 lakh).\n- Krishok Bondhu card: fertiliser subsidy Tk 4,500/year for 2.5 bigha+.\n- Subsidy: 25-50% on farm machinery, 50% on drip irrigation and solar pumps.\n- Organic certification: BOPMA. Organic premium 20-50% higher market price.",
        ],
    ];

    $scores = [];
    foreach ($kb as $pattern => $item) {
        $score = 0;
        foreach ($item['tags'] as $tag) {
            if (str_contains($text, mb_strtolower($tag))) $score += 10;
        }
        if ($score === 0 && preg_match('/' . $pattern . '/iu', $text)) $score += 8;
        if ($score > 0) $scores[$pattern] = $score;
    }
    if (empty($scores)) return '';
    arsort($scores);
    $matched = [];
    foreach (array_keys(array_slice($scores, 0, 3, true)) as $k) {
        $matched[] = $kb[$k]['content'];
    }
    return "**RELEVANT KNOWLEDGE FROM DATABASE:**\n\n" . implode("\n\n", $matched);
}

function getRelevantFewShot(string $text): string {
    $examples = [
        ['keywords' => ['boro','rice','plant','ধান','বোরো'],
         'query'    => 'When should I plant Boro rice?',
         'response' => "**Boro Rice Planting Schedule:**\n- Seedbed: Mid-November; Transplanting: January-February (30-35 day old seedlings)\n- Best varieties: BRRI dhan28, 29, 58 for high yield\n- AWD irrigation saves 30% water without yield loss\n- Fertilizer: Urea in 3 splits (basal + tillering + panicle initiation)\n- Harvest: April-May; Mechanical transplanter is 8x faster"],
        ['keywords' => ['pest','organic','control','পোকা','জৈব','IPM'],
         'query'    => 'How to control pests organically?',
         'response' => "**Organic IPM Strategy:**\n- Monitoring: pheromone traps, yellow sticky traps, light traps\n- Biological: Trichogramma (50,000/ha) for stem borer; Bt for caterpillars\n- Neem oil 3-5 ml/L weekly — safe for beneficial insects\n- IPM reduces pesticide cost 40-60% while maintaining yields"],
        ['keywords' => ['irrigation','vegetable','water','সেচ','সবজি'],
         'query'    => 'Best irrigation method for vegetables?',
         'response' => "**Modern Irrigation for Vegetables:**\n- Drip irrigation: 60-70% water saving, 20-50% yield increase. ROI 2-3 years.\n- Setup cost: Tk 40,000-60,000/acre; 50% govt subsidy available\n- Solar pump: zero fuel cost, 20-year lifespan"],
        ['keywords' => ['soil','health','compost','fertiliser','মাটি','সার'],
         'query'    => 'How to improve soil health?',
         'response' => "**Soil Health Improvement:**\n- Soil test every 2-3 years at district BADC lab (Tk 100-200)\n- Add 5 t compost/ha or 2 t vermicompost/ha before planting\n- Green manuring (Dhaincha) adds nitrogen naturally\n- pH 5.5-7.0 ideal; apply lime to raise pH"],
    ];

    $lower  = mb_strtolower($text);
    $scored = [];
    foreach ($examples as $ex) {
        $score = 0;
        foreach ($ex['keywords'] as $kw) {
            if (str_contains($lower, mb_strtolower($kw))) $score += 5;
        }
        if ($score > 0) $scored[] = [$score, $ex];
    }
    if (empty($scored)) return '';
    usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
    $out = "**LEARNING EXAMPLES:**\n";
    foreach (array_slice($scored, 0, 1) as [$sc, $ex]) {
        $out .= "\nQ: {$ex['query']}\nA: " . mb_substr($ex['response'], 0, 400) . "...\n";
    }
    return $out;
}

function getBangladeshData(string $text): string {
    $isRice = (bool)preg_match('/rice|paddy|ধান|boro|aman|aus/i', $text);
    $isVeg  = (bool)preg_match('/vegetable|potato|tomato|cabbage|সবজি|আলু/i', $text);
    $lines  = ["**Bangladesh Agricultural Research Insights**\n"];

    if ($isRice) {
        $lines[] = "**BRRI (Bangladesh Rice Research Institute):**";
        $lines[] = "- BRRI dhan28, 29 (Boro — high yield), dhan58 (short-duration Boro)";
        $lines[] = "- BRRI dhan49 (Aus — drought tolerant), dhan52 (Aman — salt tolerant), dhan79 (flood tolerant)";
        $lines[] = "- BRRI dhan62, 64, 72 (Zinc-enriched biofortified varieties)";
        $lines[] = "- AWD saves 25% water; Drum seeder reduces labour; Mechanical transplanter 8x faster";
        $lines[] = "";
    }
    if ($isVeg) {
        $lines[] = "**BARI (Bangladesh Agricultural Research Institute):**";
        $lines[] = "- Potato: BARI Alu 7, 25, 28, 41 (disease resistant, high yield)";
        $lines[] = "- Tomato: BARI Tomato 14, 15 (heat tolerant for Rabi season)";
        $lines[] = "- Technologies: drip irrigation (60% water saving), mulching, polyhouse";
        $lines[] = "";
    }
    $lines[] = "**DAE (Department of Agricultural Extension):**";
    $lines[] = "- Free soil testing, farmer training, demo plots, subsidised seeds";
    $lines[] = "- Subsidies: 50% on drip irrigation, solar pumps, farm machinery";
    $lines[] = "- Mobile app: Krishi Prabaha (free, weather + farming advice)";

    return count($lines) > 4 ? implode("\n", $lines) : '';
}

function getFAOData(string $text): string {
    if (!preg_match('/food|safety|pesticide|organic|certif|nutrition|sustainable|FAO/i', $text)) return '';
    return "**FAO Food Safety & Sustainability Guidelines**\n\n**Food Safety:**\n- Follow Codex Alimentarius MRLs for pesticide residue limits\n- Respect pre-harvest intervals (typically 7-21 days per label)\n- Organic Certification: Contact BOPMA (Bangladesh)\n\n**Nutrition:**\n- Balanced NPK from soil testing; Zinc and Boron critical for Bangladesh\n- Biofortified varieties: BRRI dhan62, 64, 72 (Zinc-enriched)\n\n**Sustainable Agriculture:**\n- Good Agricultural Practices (GAP) certification\n- IPM to reduce chemical pesticides by 50%\n- AWD irrigation to reduce water use and methane emissions";
}

function fetchNasaEonetData(string $message): string {
    if (!preg_match('/flood|drought|storm|cyclone|disaster|বন্যা|খরা|ঘূর্ণিঝড়|wildfire|earthquake/i', $message)) return '';
    $url    = 'https://eonet.gsfc.nasa.gov/api/v3/events?status=open&limit=5&bbox=88,20,93,27&days=30';
    $result = curlGet($url, ['User-Agent: SmartChashi-AgriBot/1.0'], 8);
    if (!$result) return '';
    $data   = json_decode($result, true);
    $events = $data['events'] ?? [];
    if (empty($events)) return '';
    $lines = ["**NASA EONET — Active Natural Events (Bangladesh Region, last 30 days):**"];
    foreach (array_slice($events, 0, 5) as $ev) {
        $title    = $ev['title'] ?? 'Unknown event';
        $category = $ev['categories'][0]['title'] ?? 'Event';
        $date     = substr($ev['geometry'][0]['date'] ?? '', 0, 10);
        $lines[]  = "- [{$category}] {$title}" . ($date ? " ({$date})" : '');
    }
    $lines[] = "\nThese events may affect crop planning and field operations. Prioritise safety.";
    return implode("\n", $lines);
}

function fetchWebSearch(string $message): string {
    if (!preg_match('/price|দাম|মূল্য|current|today|latest|news|market rate|2024|2025|scheme|program/i', $message)) return '';
    $q      = mb_substr(trim(preg_replace('/\s+/', ' ', preg_replace('/[\x{0980}-\x{09FF}]/u', '', $message))), 0, 80) . ' Bangladesh agriculture 2025';
    $url    = 'https://api.duckduckgo.com/?q=' . urlencode($q) . '&format=json&no_html=1&skip_disambig=1&t=SmartChashi';
    $result = curlGet($url, ['User-Agent: SmartChashi-AgriBot/1.0'], 6);
    if (!$result) return '';
    $data     = json_decode($result, true);
    $snippets = [];
    if (!empty($data['AbstractText'])) {
        $snippets[] = "**" . ($data['AbstractSource'] ?? 'Web') . ":** " . mb_substr($data['AbstractText'], 0, 300);
    }
    foreach (array_slice($data['RelatedTopics'] ?? [], 0, 3) as $topic) {
        if (!empty($topic['Text'])) $snippets[] = "- " . mb_substr($topic['Text'], 0, 150);
    }
    if (empty($snippets)) return '';
    return "**Web Search Results (supplementary reference):**\n" . implode("\n", $snippets) . "\nVerify prices and policies with local DAE office or official BARI/BRRI sources.";
}

function loadUserMemory(Database $db, int $userId): string {
    try {
        $rows = $db->resultSet(
            "SELECT memory_key, memory_value FROM agent_user_memory WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20",
            [$userId]
        );
    } catch (Exception $e) { return ''; }
    if (empty($rows)) return '';
    return implode("\n", array_map(fn($r) => "- {$r['memory_key']}: {$r['memory_value']}", $rows));
}

function extractAndSaveMemory(Database $db, int $userId, string $message): void {
    $facts   = [];
    $cropMap = [
        'boro|বোরো'                 => ['grows_boro_rice', 'Boro rice'],
        'aman|আমন'                  => ['grows_aman_rice', 'Aman rice'],
        'aus|আউশ'                   => ['grows_aus_rice',  'Aus rice'],
        'rice|ধান|paddy|dhan'       => ['grows_crop',      'rice'],
        'wheat|গম'                  => ['grows_crop',      'wheat'],
        'potato|আলু'                => ['grows_crop',      'potato'],
        'tomato|টমেটো'              => ['grows_crop',      'tomato'],
        'brinjal|বেগুন'             => ['grows_crop',      'brinjal'],
        'chili|মরিচ'                => ['grows_crop',      'chili'],
        'vegetable|সবজি'            => ['farming_type',    'vegetable farmer'],
        'organic|জৈব'               => ['farming_method',  'organic farming'],
        'drip irrigation|ড্রিপ'    => ['uses_technology', 'drip irrigation'],
        'solar pump|সোলার'          => ['uses_technology', 'solar pump'],
    ];
    foreach ($cropMap as $pattern => [$key, $value]) {
        if (preg_match("/{$pattern}/iu", $message)) $facts[$key] = $value;
    }

    $districts = [
        'dhaka' => 'Dhaka','ঢাকা' => 'Dhaka','chittagong' => 'Chittagong','চট্টগ্রাম' => 'Chittagong',
        'sylhet' => 'Sylhet','সিলেট' => 'Sylhet','rajshahi' => 'Rajshahi','রাজশাহী' => 'Rajshahi',
        'khulna' => 'Khulna','খুলনা' => 'Khulna','barisal' => 'Barisal','বরিশাল' => 'Barisal',
        'rangpur' => 'Rangpur','রংপুর' => 'Rangpur','mymensingh' => 'Mymensingh','ময়মনসিংহ' => 'Mymensingh',
        'bogura' => 'Bogura','বগুড়া' => 'Bogura','dinajpur' => 'Dinajpur','দিনাজপুর' => 'Dinajpur',
        'jessore' => 'Jashore','যশোর' => 'Jashore','comilla' => 'Comilla','কুমিল্লা' => 'Comilla',
        'noakhali' => 'Noakhali','নোয়াখালী' => 'Noakhali','pabna' => 'Pabna','পাবনা' => 'Pabna',
    ];
    $lower = mb_strtolower($message);
    foreach ($districts as $keyword => $name) {
        if (str_contains($lower, mb_strtolower($keyword))) { $facts['user_district'] = $name; break; }
    }
    if (preg_match('/(\d+(?:\.\d+)?)\s*(bigha|বিঘা|acre|একর|hectare|হেক্টর)/iu', $message, $m)) {
        $facts['farm_size'] = $m[1] . ' ' . $m[2];
    }
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $message)) $facts['preferred_language'] = 'Bangla';
    if (empty($facts)) return;
    foreach ($facts as $key => $value) {
        try {
            $db->query("INSERT INTO agent_user_memory (user_id, memory_key, memory_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE memory_value = VALUES(memory_value), updated_at = NOW()")
               ->bind(1, $userId)->bind(2, $key)->bind(3, $value)->execute();
        } catch (Exception $e) {}
    }
}

function generateConversationTitle(string $firstMessage, BaseProvider $provider): string {
    $fallback = mb_substr(strip_tags($firstMessage), 0, 50) . (mb_strlen($firstMessage) > 50 ? '...' : '');
    if (!$provider->hasApiKey()) return $fallback;
    $isBangla = (bool)preg_match('/[\x{0980}-\x{09FF}]/u', $firstMessage);
    $langNote = $isBangla ? 'Respond in Bangla (Bengali script).' : 'Respond in English.';
    $result   = $provider->chat([
        ['role' => 'system', 'content' => "Generate a short 4-6 word conversation title for the farming question below. Return ONLY the title, no quotes or punctuation. {$langNote}"],
        ['role' => 'user',   'content' => mb_substr($firstMessage, 0, 200)],
    ], 'llama-3.1-8b-instant', 0.4, 24);
    if ($result) {
        $clean = mb_substr(trim(strip_tags($result), " \t\n\r\"'"), 0, 60);
        if (mb_strlen($clean) >= 3) return $clean;
    }
    return $fallback;
}

function logAiUsage(Database $db, int $userId, string $cid, string $provider, string $model, int $responseMs, bool $success, string $error = ''): void {
    try {
        $db->query("INSERT INTO ai_usage_logs (user_id, conversation_id, provider, model, response_time_ms, success, error_message) VALUES (?,?,?,?,?,?,?)")
           ->bind(1, $userId)->bind(2, $cid)->bind(3, $provider)->bind(4, $model)
           ->bind(5, $responseMs)->bind(6, $success ? 1 : 0)->bind(7, $error ?: null)->execute();
    } catch (Exception $e) { error_log('[logAiUsage] ' . $e->getMessage()); }
}

function generateFollowUps(string $message, string $replyHtml, BaseProvider $provider, bool $isBangla): array {
    if (!$provider->hasApiKey()) return [];
    $cleanReply = mb_substr(strip_tags($replyHtml), 0, 350);
    $langNote   = $isBangla
        ? 'প্রশ্নগুলো বাংলায় লিখুন। প্রতিটি প্রশ্ন সর্বোচ্চ ১০ শব্দের।'
        : 'Write in English. Max 10 words each.';
    try {
        $result = $provider->chat([
            ['role' => 'system', 'content' => "Generate exactly 3 short follow-up questions a farmer might ask next. Return ONLY the 3 questions, one per line, no numbering or extra text. {$langNote}"],
            ['role' => 'user',   'content' => "Farmer asked: {$message}\nAnswer summary: {$cleanReply}"],
        ], 'llama-3.1-8b-instant', 0.7, 120);
        if ($result) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", strip_tags($result))),
                fn($l) => mb_strlen($l) > 5 && mb_strlen($l) < 130
            ));
            return array_slice($lines, 0, 3);
        }
    } catch (Exception $e) {}
    return [];
}

function curlPost(string $url, array $data, array $extraHeaders = [], int $timeout = 30): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $extraHeaders),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) { error_log("[curlPost] {$url} cURL error: {$err}"); return null; }
    if ($httpCode < 200 || $httpCode >= 300) { error_log("[curlPost] {$url} HTTP {$httpCode}"); return null; }
    return $result ?: null;
}

function curlGet(string $url, array $headers = [], int $timeout = 10): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode >= 200 && $httpCode < 300) ? ($result ?: null) : null;
}

// Markdown -> safe HTML with correct ul/ol wrapping
function formatMarkdownToHtml(string $text): string {
    // Protect code blocks first
    $codeBlocks = [];
    $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/m', function ($m) use (&$codeBlocks) {
        $lang        = htmlspecialchars($m[1]);
        $code        = htmlspecialchars(rtrim($m[2]));
        $placeholder = "\x00CODE" . count($codeBlocks) . "\x00";
        $codeBlocks[$placeholder] = "<pre class=\"code-block\"><code class=\"lang-{$lang}\">{$code}</code></pre>";
        return $placeholder;
    }, $text);

    // Inline code
    $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
        return '<code class="inline-code">' . htmlspecialchars($m[1]) . '</code>';
    }, $text);

    // Bold + italic combinations
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/s',     '<strong>$1</strong>',           $text);
    $text = preg_replace('/\*(.+?)\*/s',          '<em>$1</em>',                  $text);

    // Headings
    $text = preg_replace('/^#### (.+)$/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^### (.+)$/m',  '<h4>$1</h4>', $text);
    $text = preg_replace('/^## (.+)$/m',   '<h3>$1</h3>', $text);
    $text = preg_replace('/^# (.+)$/m',    '<h2>$1</h2>', $text);

    // Horizontal rule
    $text = preg_replace('/^---+$/m', '<hr>', $text);

    // Lists — line-by-line parser for correct ul/ol wrapping
    $lines    = explode("\n", $text);
    $out      = [];
    $listType = null;

    foreach ($lines as $line) {
        $isBullet   = preg_match('/^[\-\*\•]\s+(.+)$/', $line, $mb);
        $isNumbered = !$isBullet && preg_match('/^\d+\.\s+(.+)$/', $line, $mn);
        $isEmpty    = trim($line) === '';

        if ($isBullet) {
            if ($listType !== 'ul') {
                if ($listType !== null) $out[] = "</{$listType}>";
                $out[] = '<ul>';
                $listType = 'ul';
            }
            $out[] = '<li>' . $mb[1] . '</li>';
        } elseif ($isNumbered) {
            if ($listType !== 'ol') {
                if ($listType !== null) $out[] = "</{$listType}>";
                $out[] = '<ol>';
                $listType = 'ol';
            }
            $out[] = '<li>' . $mn[1] . '</li>';
        } elseif ($isEmpty && $listType !== null) {
            // Blank lines between list items — swallow them, keep the list open
        } else {
            if ($listType !== null) {
                $out[] = "</{$listType}>";
                $listType = null;
            }
            $out[] = $line;
        }
    }
    if ($listType !== null) $out[] = "</{$listType}>";
    $text = implode("\n", $out);

    // Paragraphs
    $text = preg_replace('/\n{2,}/', '</p><p>', $text);
    $text = str_replace("\n", '<br>', $text);
    $text = '<p>' . $text . '</p>';

    // Clean up empty tags and wrongly-wrapped block elements
    $text = preg_replace('/<p>\s*<\/p>/', '', $text);
    $text = preg_replace('/<p>(<(?:ul|ol|hr|h[2-5]|pre)[^>]*>)/i', '$1', $text);
    $text = preg_replace('#(<\/(?:ul|ol|h[2-5]|pre)>)\s*</p>#i', '$1', $text);

    // Restore code blocks
    foreach ($codeBlocks as $placeholder => $html) {
        $text = str_replace($placeholder, $html, $text);
    }

    return $text;
}
