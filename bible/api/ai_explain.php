<?php
/**
 * CRC Bible AI Explain API
 * POST /bible/api/ai_explain.php
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

Security::requireRateLimit('ai_explain', 10, 60); // 10 requests per minute

$user = Auth::user();
$reference = input('reference');
$text = input('text');
$version = input('version', 'KJV');

if (!$reference || !$text) {
    Response::error('Reference and text are required');
}

// Check cache first
$cacheKey = md5($reference . '|' . $text . '|' . $version);
$cached = Database::fetchOne(
    "SELECT explanation FROM bible_ai_cache
     WHERE cache_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [$cacheKey]
);

if ($cached) {
    Response::success(['explanation' => $cached['explanation'], 'cached' => true]);
}

// Generate explanation
// In production, this would call an AI service like OpenAI/Claude API
// For now, provide contextual explanations for common verses
$explanation = generateExplanation($reference, $text);

// Cache the result
Database::insert('bible_ai_cache', [
    'cache_key' => $cacheKey,
    'reference' => $reference,
    'version_code' => $version,
    'explanation' => $explanation,
    'created_at' => date('Y-m-d H:i:s')
]);

Response::success(['explanation' => $explanation, 'cached' => false]);

function generateExplanation($reference, $text) {
    // Pre-defined explanations for common verses
    $explanations = [
        'Genesis 1:1' => "This foundational verse establishes God as the Creator of all things. The Hebrew word 'bara' (create) is used exclusively for divine creation, emphasizing that God created from nothing (ex nihilo). This verse sets the theological foundation for understanding God's sovereignty over all creation.",

        'John 3:16' => "Often called 'the Gospel in miniature,' this verse encapsulates the core message of Christianity. It reveals: (1) God's motivation - love, (2) God's gift - His Son, (3) The recipients - the whole world, (4) The condition - belief, (5) The promise - eternal life. The Greek word 'agapao' indicates unconditional, sacrificial love.",

        'Psalm 23:1' => "David, drawing from his experience as a shepherd, presents God as the ultimate Shepherd who provides, protects, and guides. The declaration 'I shall not want' expresses complete trust and contentment in God's provision. This metaphor runs throughout Scripture, culminating in Jesus declaring Himself the Good Shepherd (John 10).",

        'Philippians 4:13' => "In context, Paul is speaking about contentment in all circumstances - whether in plenty or want. The strength Christ provides enables believers to endure hardships and remain faithful regardless of external circumstances. It's not a promise of unlimited human capability, but of spiritual endurance through Christ.",

        'Jeremiah 29:11' => "Originally spoken to Israelite exiles in Babylon, this verse promised restoration after 70 years of captivity. While directly addressing Israel's national situation, the principle reveals God's character - He works purposefully in believers' lives, even through difficult seasons, with ultimate good in mind.",

        'Romans 8:28' => "This verse assures believers that God orchestrates all circumstances (good and challenging) for the ultimate good of those who love Him. The 'good' refers primarily to spiritual transformation into Christ's likeness (v. 29), not necessarily material prosperity. It's a call to trust God's sovereignty.",

        'Isaiah 41:10' => "Spoken to Israel during a time of fear and uncertainty, God offers three commands (fear not, be not dismayed) and three promises (I am with you, I will strengthen you, I will help you). The repetition emphasizes God's commitment to His people through any trial.",

        'Proverbs 3:5-6' => "These verses present wisdom for decision-making: wholehearted trust in God (not partial), rejection of self-reliance, and acknowledgment of God in all areas of life. The promise is divine direction - God will make our paths straight (or 'smooth out our paths')."
    ];

    // Check if we have a specific explanation
    foreach ($explanations as $ref => $exp) {
        if (stripos($reference, $ref) !== false) {
            return $exp;
        }
    }

    // Generate a generic explanation based on the text content
    $textLower = strtolower($text);

    if (strpos($textLower, 'love') !== false) {
        return "This verse speaks about love - a central theme in Scripture. In the biblical context, love is not merely an emotion but an action and commitment. God's love (agape) is unconditional and sacrificial, serving as the model for how believers should love others. This love finds its fullest expression in Jesus Christ's sacrifice on the cross.";
    }

    if (strpos($textLower, 'faith') !== false || strpos($textLower, 'believe') !== false) {
        return "Faith is a recurring theme in Scripture, defined in Hebrews 11:1 as 'the substance of things hoped for, the evidence of things not seen.' Biblical faith is not blind belief, but trust based on God's revealed character and promises. It involves both intellectual assent and personal commitment to God.";
    }

    if (strpos($textLower, 'pray') !== false) {
        return "Prayer in Scripture is communication with God - both speaking to Him and listening for His guidance. Jesus modeled prayer throughout His ministry and taught His disciples how to pray. Prayer should include praise, confession, thanksgiving, and supplication (requests for ourselves and others).";
    }

    if (strpos($textLower, 'lord') !== false || strpos($textLower, 'god') !== false) {
        return "This verse references the Lord God, the sovereign Creator and Sustainer of all things. In the Old Testament, 'LORD' (all capitals) represents God's covenant name YHWH, revealing His self-existent, eternal nature. God is described throughout Scripture as holy, just, merciful, and loving.";
    }

    // Default explanation
    return "This passage from $reference invites us to reflect on God's truth as revealed in Scripture. Every verse of the Bible contributes to the overarching narrative of God's redemptive plan for humanity. Consider how this text relates to its immediate context, the broader biblical narrative, and how it applies to your life today. Praying for understanding and discussing Scripture with fellow believers can deepen your comprehension.";
}
