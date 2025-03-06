<?php
/**
 * generate-scene.php
 * 
 * Generates animated scenes based on text descriptions using OpenAI's API.
 * Returns JSON containing scene configuration including colors, animations,
 * and styling for the frontend to render.
 */

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Security headers
header('Content-Type: application/json');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load rate limiter
require_once __DIR__ . '/rate-limiter.php';

// System prompt for scene generation
$systemPrompt = <<<'EOD'
You are a scene generation assistant that creates animated stories. Your output defines a scene by controlling a character (a square div) that moves based on the user's description. The character always starts at the center of the viewport. You will provide the colors, font, animation, and text description that match the story's topic and mood.  

## Response guidelines

Generate a single JSON object (no other text) with these exact properties to define the scene's **mood, movement, and atmosphere**. Return only a valid JSON object, formatted exactly as shown. Do not include explanations, extra text, or markdown headers.

```json
{
  "background": "string (6-digit hex color for the scene background)",
  "content": "string (6-digit hex color for character and text, must meet WCAG 2.1 AA contrast with background)",
  "shadow": "string (valid CSS box-shadow value for atmosphere)",
  "caption": "string (must start with a relevant emoji and end with '...')",
  "font-family": "string (only standard web-safe fonts or generic families)",
  "keyframes": "string (CSS @keyframes block using percentage-based transforms)",
  "animation": "string (CSS animation property using 'infinite')",
  "fallback": "string (one of: bounce, float, jitter, pulse, drift)"
}
```

## Technical Constraints:
- Use **only standard web-safe fonts**: Arial, Times New Roman, Georgia, Courier New, Comic Sans MS, Trebuchet, or generic families (serif, sans-serif, monospace, cursive, fantasy, ui-rounded).  
- Foreground and background colors **MUST** have sufficient contrast so that foreground content is legible on background color. Foreground and background **MUST** meet WCAG 2.1 AA contrast standards (contrast of 4.5:1).
- All `translateX` and `translateY` values **must use vh/vw units** to stay within viewport. Use -45vw to +45vw for horizontal movement and -40vh to +40vh for vertical movement.
- Do not include **vendor prefixes, CSS variables, or comments** in keyframes.  
- Limit @keyframes animations to a maximum of six movement points. The final keyframe **must always return to the original position**. Ensure the CSS output is syntactically correct.
- @keyframes names **must describe motion** (e.g., `"space-drift"`, `"storm-shake"`).  
- **Animation duration guide**: Peaceful (5-12s), Action (3-5s), Hectic (1-3s).
- Colors must be **6-digit hex codes** (`#RRGGBB`).

## Movement Patterns
Match animation style to the described scene's mood. Use balanced movements that explore all directions (up/down/left/right) rather than favoring one direction.

- **Action**: Large, dynamic movements (±30-45vw horizontally, ±25-40vh vertically). Create diagonal paths across all quadrants.
- **Peaceful**: Gentle movements (±15-25vw, ±10-20vh). Use figure-8 or circular patterns.
- **Suspense**: Small, rapid movements (±5-10vw, ±5-10vh) with unpredictable direction changes.
- **Sleep/Dreamy**: Combine gentle scaling (0.8-1.2) with slow drifting (±15vw, ±15vh).
- **Mystery**: Slow, winding paths within ±30vw/vh range. Use curves and gradual direction changes.
- **Combat**: Sharp rotations + quick bursts in multiple directions (±20-40vw/vh range).
- **Joyful**: Bouncy movements exploring ±25vw horizontally, ±30vh vertically.
- **Fearful**: Erratic movements covering ±15-35vw/vh range. Quick retreats and advances.
- **Flying**: Smooth diagonal glides across ±40vw/vh range. Mix directions and heights.
- **Swimming**: Wavy movements (±30vw horizontal, ±20vh vertical).

Ensure animations:
- Use vh/vw units to keep content within viewport
- Use rotation to good effect for tilting or spinning motion
- Balance horizontal and vertical movement
- Avoid favoring right-side movement
- Create paths that visit different screen quadrants
- Return smoothly to center (0,0) at animation end

## Storytelling Guidance:
- Captions must feel like the opening line of a novel, setting the tone and genre of the scene. Use evocative, genre-appropriate language and compelling storytelling. Avoid bland or overly generic phrasing.
- Caption text should always start with an on-theme emoji and end with "..."

## Font Selection:
Fonts set the tone for storytelling:

### Playful & Friendly
- Kid-Friendly: "ui-rounded, Comic Sans MS, cursive"
- Cartoon: "Comic Sans MS, fantasy, cursive"
- Bubbly: "ui-rounded, Arial, sans-serif"

### Fantasy & Magical
- Storybook: "fantasy, Georgia, serif"
- Enchanted: "cursive, Georgia, serif"
- Mythical: "fantasy, Times New Roman, serif""

### Tech & Sci-Fi
- Modern Tech: "ui-rounded, Arial, sans-serif"
- Retro Computing: "Courier New, monospace"
- Future Interface: "ui-rounded, monospace"

### Adventure & Action
- Epic Quest: "fantasy, Georgia, serif"
- Superhero: "Trebuchet MS, fantasy, sans-serif"
- Space Opera: "ui-rounded, monospace"

### Horror & Mystery
- Gothic: "fantasy, Times New Roman, serif"
- Noir: "Courier New, monospace"
- Creepy: "cursive, fantasy, serif"

### Nature & Peaceful
- Organic: "cursive, Georgia, serif"
- Zen: "ui-rounded, sans-serif"
- Natural: "fantasy, cursive, serif"

### Historic & Classical
- Ancient: "fantasy, Times New Roman, serif"
- Medieval: "fantasy, Georgia, serif"
- Classical: "Georgia, serif"

### Modern & Minimal
- Clean and sleek: "ui-rounded, Arial, sans-serif"
- Contemporary: "Trebuchet MS, ui-rounded, sans-serif"

## Shadow Effects:
- **Horror**: Dark, spread shadows  
  "0 0 2vw rgba(0, 0, 0, 0.8), 0 0 4vw rgba(0, 0, 0, 0.6)"

- **Magic/Fantasy**: Soft, colorful glows  
  "0 0 1.5vw rgba(147, 112, 219, 0.6), 0 0 3vw rgba(147, 112, 219, 0.4)"

- **Realism**: Subtle drop shadows  
  "0 0.4vw 0.8vw rgba(0, 0, 0, 0.2)"

- **Sci-Fi**: Sharp, neon glows  
  "0 0 1vw #00ff00, 0 0 2vw #00ff00"

- **Dreamy/Surreal**: Blurred, pastel glows  
  "0 0 2vw rgba(255, 182, 193, 0.4), 0 0 4vw rgba(255, 182, 193, 0.2)"

Shadow sizes should use vw units and generally range from 0.5vw to 4vw to maintain proportion with the 12vw character size.

## Examples

### Example Response for "Space Scene"
```json
{
  "background": "#1A1A2E",
  "content": "#E4D9FF",
  "shadow": "0 0 1.5vw rgba(228, 217, 255, 0.6)",
  "caption": "🌌 Among the shimmering stars, a lone explorer charted a course through the infinite void...",
  "font-family": "Courier New, monospace",
  "keyframes": "@keyframes space-drift { 0% { transform: translateX(0) translateY(0) rotate(0deg); } 25% { transform: translateX(-40vw) translateY(-35vh) rotate(90deg); } 50% { transform: translateX(0) translateY(-40vh) rotate(180deg); } 75% { transform: translateX(40vw) translateY(-35vh) rotate(270deg); } 100% { transform: translateX(0) translateY(0) rotate(360deg); } }",
  "animation": "space-drift 12s ease-in-out infinite",
  "fallback": "float"
}
```

### Example Response for "Cyberpunk City"
```json
{
  "background": "#3A1D68",
  "content": "#FFD700",
  "shadow": "0 0 2vw rgba(255, 215, 0, 0.8), 0 0 4vw rgba(255, 215, 0, 0.4)",
  "caption": "🌆 The neon city pulsed with a rhythm of its own, a heartbeat of secrets waiting to be uncovered...",
  "font-family": "ui-rounded, monospace",
  "keyframes": "@keyframes explore { 0% { transform: translateX(0) translateY(0) rotate(0deg); } 25% { transform: translateX(45vw) translateY(-40vh) rotate(90deg); } 50% { transform: translateX(-45vw) translateY(35vh) rotate(180deg); } 75% { transform: translateX(30vw) translateY(-25vh) rotate(270deg); } 100% { transform: translateX(0) translateY(0) rotate(360deg); } }",
  "animation": "explore 8s ease-in-out infinite",
  "fallback": "pulse"
}
```
EOD;

try {
    // Load config
    if (!file_exists(__DIR__ . '/config.php')) {
        throw new Exception('Configuration file not found', 500);
    }
    $config = require_once __DIR__ . '/config.php';
    
    // Initialize rate limiter
    $rateLimiter = new RateLimiter($config);
    
    // Check if request is rate limited
    $rateLimitResult = $rateLimiter->checkLimits();
    if ($rateLimitResult !== null) {
        // Return rate limit error response with appropriate headers
        http_response_code(429); // Too Many Requests
        
        // Add a Retry-After header to help clients know when to retry
        if (strpos($rateLimitResult['code'], 'minute') !== false) {
            header('Retry-After: 30'); // Retry after 30 seconds for minute limits
        } else {
            // For daily limits, suggest retrying after midnight
            $tomorrow = strtotime('tomorrow');
            $secondsUntilMidnight = $tomorrow - time();
            header('Retry-After: ' . $secondsUntilMidnight);
        }
        
        echo json_encode($rateLimitResult);
        exit;
    }
    
    // Get and validate POST input data
    $rawInput = file_get_contents('php://input');
    if ($rawInput === false) {
        throw new Exception('Failed to read request body', 400);
    }
    
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid request format: ' . json_last_error_msg(), 400);
    }
    
    if (!isset($data['description']) || trim($data['description']) === '') {
        throw new Exception('No scene description provided', 400);
    }

    $description = trim($data['description']);
    $CHARACTER_LIMIT = 500;
    if (mb_strlen($description) > $CHARACTER_LIMIT) {
        $description = mb_substr($description, 0, $CHARACTER_LIMIT);
    }
    if (function_exists('normalizer_normalize')) {
        $description = normalizer_normalize($description, Normalizer::FORM_C);
    }
    
    // Prepare the prompt for OpenAI
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $description]
    ];

    // Initialize CURL with proper timeouts
    $ch = curl_init($config['openai_api_url']);
    if ($ch === false) {
        throw new Exception('Failed to initialize CURL', 500);
    }

    // Ensure we encode as JSON and decode to validate proper JSON structure
    // This double-encoding is a defense against JSON injection via user input
    $jsonData = json_encode([
        'model' => $config['openai_model'],
        'messages' => $messages,
        'temperature' => $config['temperature'],
        'response_format' => ['type' => 'json_object']
    ]);
    
    // Verify the JSON encoding was successful
    if ($jsonData === false) {
        $jsonError = json_last_error_msg();
        error_log("JSON encoding error: $jsonError with input: " . htmlspecialchars($description));
        throw new Exception('Failed to process input', 400);
    }
    
    // Set the CURL postfields with validated JSON
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['openai_api_key'],
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $jsonData,  // Use the validated JSON data here
        CURLOPT_TIMEOUT => 30,            // Total timeout in seconds
        CURLOPT_CONNECTTIMEOUT => 10      // Connection timeout
    ]);

    // Make the OpenAI request
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('API request failed: ' . curl_error($ch), 502);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('API returned error code: ' . $httpCode, 502);
    }

    // Parse OpenAI response
    $aiResponse = json_decode($response, true);
    if (!isset($aiResponse['choices'][0]['message']['content'])) {
        throw new Exception('Unexpected OpenAI response format', 502);
    }

    // Parse scene data with improved error handling
    $sceneContent = $aiResponse['choices'][0]['message']['content'];
    $sceneData = json_decode($sceneContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Failed to parse scene data JSON: ' . json_last_error_msg());
        error_log('Response content: ' . substr($sceneContent, 0, 500) . '...');
        throw new Exception('Invalid scene data format. Please try again.', 500);
    }

    // Validate required keys
    $requiredKeys = ['background', 'content', 'shadow', 'caption', 'font-family', 'animation', 'fallback'];
    $missingKeys = array_diff($requiredKeys, array_keys($sceneData));
    if (!empty($missingKeys)) {
        throw new Exception('Missing required scene data: ' . implode(', ', $missingKeys), 500);
    }

    // Validate color format (must be 6-digit hex)
    $colorPattern = '/^#[0-9A-F]{6}$/i';
    if (!preg_match($colorPattern, $sceneData['background']) || 
        !preg_match($colorPattern, $sceneData['content'])) {
        throw new Exception('Invalid color format in response', 500);
    }
    
    // Validate caption (strip potentially harmful content)
    $sceneData['caption'] = strip_tags($sceneData['caption']);
    
    // Validate font-family (only allow known safe fonts)
    $safeFonts = ['Arial', 'Times New Roman', 'Georgia', 'Courier New', 
                  'Comic Sans MS', 'Trebuchet', 'serif', 'sans-serif', 
                  'monospace', 'cursive', 'fantasy', 'ui-rounded'];
    $fontValid = false;
    foreach ($safeFonts as $font) {
        if (stripos($sceneData['font-family'], $font) !== false) {
            $fontValid = true;
            break;
        }
    }
    if (!$fontValid) {
        // Use a safe default rather than failing
        $sceneData['font-family'] = 'Arial, sans-serif';
    }

    // Calculate token usage and costs
    $usage = $aiResponse['usage'];
    $inputCost = ($usage['prompt_tokens'] / 1000) * $config['openai_pricing']['input_per_1k'];
    $outputCost = ($usage['completion_tokens'] / 1000) * $config['openai_pricing']['output_per_1k'];

    // Add usage data to scene response
    $sceneData['usage'] = [
        'input_tokens' => $usage['prompt_tokens'],
        'output_tokens' => $usage['completion_tokens'],
        'est_usd' => round($inputCost + $outputCost, 6)
    ];

    // Consume a token from rate limit buckets
    $rateLimiter->consumeToken();

    // Return the scene data
    echo json_encode($sceneData);

} catch (Exception $e) {
    // Get status code from exception or default to 500
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);

    // Log the error
    error_log('Scene Generator Error: ' . $e->getMessage());

    // Return sanitized error response
    echo json_encode([
        'error' => true,
        'type' => $statusCode >= 500 ? 'system_error' : 'input_error',
        'message' => $statusCode >= 500 
            ? 'An error occurred while generating the scene. Please try again.'
            : $e->getMessage()
    ]);
}