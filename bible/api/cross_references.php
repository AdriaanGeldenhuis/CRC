<?php
/**
 * CRC Bible Cross References API
 * POST /bible/api/cross_references.php
 *
 * Returns cross references for a specific verse.
 * Uses a static mapping of common cross references.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requireAuth();
Response::requirePost();
CSRF::require();

$bookNumber = (int)input('book_number');
$chapter = (int)input('chapter');
$verse = (int)input('verse');

if (!$bookNumber || !$chapter || !$verse) {
    Response::error('Book, chapter, and verse are required');
}

// Common cross references mapping
// Format: "book:chapter:verse" => [["book", chapter, verse, "text"], ...]
$crossReferences = [
    // Genesis 1:1
    "1:1:1" => [
        [43, 1, 1, "In the beginning was the Word, and the Word was with God, and the Word was God."],
        [58, 11, 3, "Through faith we understand that the worlds were framed by the word of God."],
        [19, 33, 6, "By the word of the LORD were the heavens made; and all the host of them by the breath of his mouth."]
    ],
    // John 3:16
    "43:3:16" => [
        [45, 5, 8, "But God commendeth his love toward us, in that, while we were yet sinners, Christ died for us."],
        [62, 4, 9, "In this was manifested the love of God toward us, because that God sent his only begotten Son into the world."],
        [43, 1, 14, "And the Word was made flesh, and dwelt among us, and we beheld his glory."]
    ],
    // Romans 8:28
    "45:8:28" => [
        [49, 1, 11, "In whom also we have obtained an inheritance, being predestinated according to the purpose of him."],
        [45, 8, 29, "For whom he did foreknow, he also did predestinate to be conformed to the image of his Son."],
        [52, 5, 18, "In every thing give thanks: for this is the will of God in Christ Jesus concerning you."]
    ],
    // Psalm 23:1
    "19:23:1" => [
        [43, 10, 11, "I am the good shepherd: the good shepherd giveth his life for the sheep."],
        [26, 34, 23, "And I will set up one shepherd over them, and he shall feed them, even my servant David."],
        [60, 2, 25, "For ye were as sheep going astray; but are now returned unto the Shepherd and Bishop of your souls."]
    ],
    // Proverbs 3:5
    "20:3:5" => [
        [24, 10, 20, "He that handleth a matter wisely shall find good: and whoso trusteth in the LORD, happy is he."],
        [19, 37, 5, "Commit thy way unto the LORD; trust also in him; and he shall bring it to pass."],
        [23, 26, 3, "Thou wilt keep him in perfect peace, whose mind is stayed on thee: because he trusteth in thee."]
    ],
    // Isaiah 53:5
    "23:53:5" => [
        [60, 2, 24, "Who his own self bare our sins in his own body on the tree, that we, being dead to sins, should live."],
        [47, 5, 21, "For he hath made him to be sin for us, who knew no sin; that we might be made the righteousness of God."],
        [45, 4, 25, "Who was delivered for our offences, and was raised again for our justification."]
    ],
    // Matthew 28:19
    "40:28:19" => [
        [44, 1, 8, "But ye shall receive power, after that the Holy Ghost is come upon you: and ye shall be witnesses."],
        [41, 16, 15, "And he said unto them, Go ye into all the world, and preach the gospel to every creature."],
        [42, 24, 47, "And that repentance and remission of sins should be preached in his name among all nations."]
    ],
    // Philippians 4:13
    "50:4:13" => [
        [47, 12, 9, "And he said unto me, My grace is sufficient for thee: for my strength is made perfect in weakness."],
        [49, 3, 16, "That he would grant you, according to the riches of his glory, to be strengthened with might."],
        [23, 40, 31, "But they that wait upon the LORD shall renew their strength; they shall mount up with wings."]
    ],
    // Jeremiah 29:11
    "24:29:11" => [
        [45, 8, 28, "And we know that all things work together for good to them that love God."],
        [20, 19, 21, "There are many devices in a man's heart; nevertheless the counsel of the LORD, that shall stand."],
        [19, 33, 11, "The counsel of the LORD standeth for ever, the thoughts of his heart to all generations."]
    ],
    // Hebrews 11:1
    "58:11:1" => [
        [47, 5, 7, "For we walk by faith, not by sight."],
        [45, 8, 24, "For we are saved by hope: but hope that is seen is not hope."],
        [43, 20, 29, "Jesus saith unto him, Thomas, because thou hast seen me, thou hast believed: blessed are they."]
    ]
];

$key = "$bookNumber:$chapter:$verse";
$refs = $crossReferences[$key] ?? [];

// Format response
$formattedRefs = [];
foreach ($refs as $ref) {
    $formattedRefs[] = [
        'book_number' => $ref[0],
        'chapter' => $ref[1],
        'verse' => $ref[2],
        'text' => $ref[3]
    ];
}

// If no specific refs found, return empty with a note
if (empty($formattedRefs)) {
    Response::success([
        'cross_references' => [],
        'message' => 'No cross references found for this verse.'
    ]);
} else {
    Response::success([
        'cross_references' => $formattedRefs
    ]);
}
