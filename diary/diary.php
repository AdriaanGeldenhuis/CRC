<?php
/**
 * CRC Diary - AI-Enhanced Premium Diary
 * Main diary page with timeline, gallery, and search views
 */

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

Auth::requireAuth();

$user = Auth::user();
$userId = (int)$user['id'];
$userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')) ?: 'User';

// Helper function to escape output
function esc($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Mood emoji helper
function getMoodEmoji($mood) {
    $moods = [
        'happy' => '😊',
        'sad' => '😢',
        'excited' => '🤗',
        'calm' => '😌',
        'thoughtful' => '🤔',
        'grateful' => '🙏',
        'inspired' => '✨',
        'tired' => '😴',
        // CRC moods
        'joyful' => '😊',
        'peaceful' => '😌',
        'hopeful' => '🌟',
        'anxious' => '😰',
        'angry' => '😤',
        'confused' => '😕'
    ];
    return $moods[$mood] ?? '😊';
}

// Weather emoji helper
function getWeatherEmoji($weather) {
    $weathers = [
        'sunny' => '☀️',
        'cloudy' => '☁️',
        'rainy' => '🌧️',
        'stormy' => '⛈️',
        'snowy' => '❄️',
        'windy' => '🌬️'
    ];
    return $weathers[$weather] ?? '☀️';
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Diary - CRC</title>
    <?= CSRF::meta() ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Parisienne&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/diary/css/diary.css?v=<?= filemtime(__DIR__ . '/css/diary.css') ?>">
</head>
<body class="diary-body">
    <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

    <!-- Hero Section -->
    <div class="diary-hero">
        <div class="diary-hero-glow"></div>
        <div class="diary-hero-content">
            <h1 class="diary-hero-title">My Diary</h1>
            <p class="diary-hero-subtitle">Preserve your thoughts, prayers, and reflections</p>
        </div>
        <div class="diary-sparkles">
            <span class="diary-sparkle" style="--delay: 0s; --x: 15%; --y: 25%;"></span>
            <span class="diary-sparkle" style="--delay: 0.7s; --x: 80%; --y: 35%;"></span>
            <span class="diary-sparkle" style="--delay: 1.3s; --x: 45%; --y: 65%;"></span>
            <span class="diary-sparkle" style="--delay: 1.9s; --x: 25%; --y: 75%;"></span>
            <span class="diary-sparkle" style="--delay: 2.5s; --x: 85%; --y: 80%;"></span>
        </div>
    </div>

    <main class="diary-main">

        <!-- Quick Stats -->
        <section class="diary-stats">
            <div class="stat-card" data-stat="total">
                <div class="stat-icon">📝</div>
                <div class="stat-info">
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Entries</div>
                </div>
            </div>
            <div class="stat-card" data-stat="month">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <div class="stat-value" id="statMonth">0</div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>
            <div class="stat-card" data-stat="streak">
                <div class="stat-icon">🔥</div>
                <div class="stat-info">
                    <div class="stat-value" id="statStreak">0</div>
                    <div class="stat-label">Day Streak</div>
                </div>
            </div>
            <div class="stat-card" data-stat="words">
                <div class="stat-icon">✍️</div>
                <div class="stat-info">
                    <div class="stat-value" id="statWords">0</div>
                    <div class="stat-label">Total Words</div>
                </div>
            </div>
        </section>

        <!-- View Toggle -->
        <section class="diary-view-toggle">
            <button class="view-btn active" data-view="timeline" title="Timeline">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 10h18M3 14h18M8 6h13M8 18h13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>Timeline</span>
            </button>
            <button class="view-btn" data-view="gallery" title="Gallery">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span>Gallery</span>
            </button>
            <button class="view-btn" data-view="search" title="Search">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                    <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>Search</span>
            </button>
        </section>

        <!-- Quick Actions -->
        <section class="diary-actions">
            <button class="action-btn action-primary" id="newEntryBtn">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>New Entry</span>
            </button>
        </section>

        <!-- Timeline View -->
        <section class="diary-view diary-timeline" id="timelineView">
            <div class="timeline-filters">
                <input type="text" class="filter-input" id="timelineSearch" placeholder="Search entries...">
                <select class="filter-select" id="timelineSort">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="title">Title A-Z</option>
                </select>
                <select class="filter-select" id="timelineFilter">
                    <option value="all">All</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <div class="timeline-container" id="timelineContainer">
                <div class="timeline-loading">
                    <div class="loading-spinner"></div>
                    <p>Loading entries...</p>
                </div>
            </div>
        </section>

        <!-- Gallery View -->
        <section class="diary-view diary-gallery" id="galleryView" style="display: none;">
            <div class="gallery-grid" id="galleryGrid">
                <div class="gallery-loading">
                    <div class="loading-spinner"></div>
                    <p>Loading gallery...</p>
                </div>
            </div>
        </section>

        <!-- Search View -->
        <section class="diary-view diary-search" id="searchView" style="display: none;">
            <div class="search-panel">
                <div class="search-header">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search through all entries...">
                    <button class="search-btn" id="searchBtn">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="search-filters">
                    <label class="search-filter">
                        <input type="checkbox" id="searchTitle" checked>
                        <span>Titles</span>
                    </label>
                    <label class="search-filter">
                        <input type="checkbox" id="searchBody" checked>
                        <span>Content</span>
                    </label>
                    <label class="search-filter">
                        <input type="checkbox" id="searchTags" checked>
                        <span>Tags</span>
                    </label>
                </div>
                <div class="search-results" id="searchResults">
                    <div class="search-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <p>Start typing to search...</p>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Entry Modal -->
    <div class="modal" id="entryModal">
        <div class="modal-overlay" id="modalOverlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">New Entry</h2>
                <button class="modal-close" id="modalClose">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="entryForm">
                    <?= CSRF::field() ?>
                    <input type="hidden" id="entryId" name="entryId" value="">

                    <div class="form-group">
                        <label class="form-label">Date & Time</label>
                        <div class="form-row">
                            <input type="date" class="form-input" id="entryDate" name="date" required>
                            <input type="time" class="form-input" id="entryTime" name="time" value="00:00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="entryTitle">Title</label>
                        <input type="text" class="form-input" id="entryTitle" name="title" placeholder="My thoughts today...">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="entryBody">Content</label>
                        <div class="editor-toolbar">
                            <button type="button" class="editor-btn" data-action="bold" title="Bold">
                                <strong>B</strong>
                            </button>
                            <button type="button" class="editor-btn" data-action="italic" title="Italic">
                                <em>I</em>
                            </button>
                            <button type="button" class="editor-btn" data-action="underline" title="Underline">
                                <u>U</u>
                            </button>
                            <span class="editor-divider"></span>
                            <button type="button" class="editor-btn" data-action="quote" title="Quote">
                                <span>"</span>
                            </button>
                        </div>
                        <textarea class="form-textarea" id="entryBody" name="content" rows="10" placeholder="Write your thoughts here..."></textarea>
                        <div class="word-count" id="wordCount">0 words</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div class="tags-container" id="tagsContainer"></div>
                        <input type="text" class="form-input" id="tagInput" placeholder="Type a tag and press Enter...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mood</label>
                        <div class="mood-selector">
                            <button type="button" class="mood-btn" data-mood="happy">😊</button>
                            <button type="button" class="mood-btn" data-mood="sad">😢</button>
                            <button type="button" class="mood-btn" data-mood="excited">🤗</button>
                            <button type="button" class="mood-btn" data-mood="calm">😌</button>
                            <button type="button" class="mood-btn" data-mood="thoughtful">🤔</button>
                            <button type="button" class="mood-btn" data-mood="grateful">🙏</button>
                            <button type="button" class="mood-btn" data-mood="inspired">✨</button>
                            <button type="button" class="mood-btn" data-mood="tired">😴</button>
                        </div>
                        <input type="hidden" id="entryMood" name="mood">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Weather</label>
                        <div class="weather-selector">
                            <button type="button" class="weather-btn" data-weather="sunny">☀️</button>
                            <button type="button" class="weather-btn" data-weather="cloudy">☁️</button>
                            <button type="button" class="weather-btn" data-weather="rainy">🌧️</button>
                            <button type="button" class="weather-btn" data-weather="stormy">⛈️</button>
                            <button type="button" class="weather-btn" data-weather="snowy">❄️</button>
                            <button type="button" class="weather-btn" data-weather="windy">🌬️</button>
                        </div>
                        <input type="hidden" id="entryWeather" name="weather">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reminder</label>
                        <select class="form-select" id="reminderMinutes" name="reminder_minutes">
                            <option value="0">No reminder</option>
                            <option value="15">15 minutes before</option>
                            <option value="30">30 minutes before</option>
                            <option value="60" selected>1 hour before</option>
                            <option value="120">2 hours before</option>
                            <option value="1440">1 day before</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="custom-checkbox">
                            <input type="checkbox" id="addToCalendar" name="add_to_calendar" checked>
                            <span class="checkmark"></span>
                            <span>Add to Calendar 📅</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-ai" id="aiAssistBtn">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" fill="none"/>
                            </svg>
                            AI Assist
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal" id="shareModal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-container modal-sm">
            <div class="modal-header">
                <h2 class="modal-title">Share Entry</h2>
                <button class="modal-close" onclick="closeShareModal()">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="share-options">
                    <button class="share-option" onclick="shareAs('friend')">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Share with Friend</span>
                    </button>
                    <button class="share-option" onclick="shareAs('link')">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Copy Link</span>
                    </button>
                    <button class="share-option" onclick="shareAs('export')">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5m0 0L7 8m5-5v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Export PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.USER_ID = <?= $userId ?>;
        window.CSRF_TOKEN = '<?= CSRF::generate() ?>';
    </script>
    <script src="/diary/js/diary.js?v=<?= filemtime(__DIR__ . '/js/diary.js') ?>"></script>

</body>
</html>
