<?php
// =====================================================================
// /diary/diary.php — AI-Enhanced Premium Diary
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// -------------------------- Taal --------------------------------------
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /diary/diary.php');
  exit;
}
// Translation helper using central 5-language system
function t(string $key): string {
  global $pageLang;
  return __t($key, $pageLang);
}

function esc($s) { 
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get user info
$user = null;
try {
  $stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Diary user fetch error: " . $e->getMessage());
}

$userName = $user ? trim($user['name'] . ' ' . $user['surname']) : 'User';

?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('ai_diary')) ?></title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    @font-face {
      font-family: 'Parisienne';
      src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }
  </style>
  
  <link rel="stylesheet" href="/diary/css/diary.css?v=<?= time() ?>">
</head>
<body class="diary-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Hero Section -->
  <div class="diary-hero">
    <div class="diary-hero-glow"></div>
    <div class="diary-hero-content">
      <h1 class="diary-hero-title"><?= esc(t('my_diary')) ?></h1>
      <p class="diary-hero-subtitle"><?= esc(t('preserve_thoughts')) ?></p>
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
          <div class="stat-label"><?= esc(t('total_entries')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="month">
        <div class="stat-icon">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statMonth">0</div>
          <div class="stat-label"><?= esc(t('this_month')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="streak">
        <div class="stat-icon">🔥</div>
        <div class="stat-info">
          <div class="stat-value" id="statStreak">0</div>
          <div class="stat-label"><?= esc(t('day_streak')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="words">
        <div class="stat-icon">✍️</div>
        <div class="stat-info">
          <div class="stat-value" id="statWords">0</div>
          <div class="stat-label"><?= esc(t('total_words')) ?></div>
        </div>
      </div>
    </section>

    <!-- View Toggle -->
    <section class="diary-view-toggle">
      <button class="view-btn active" data-view="timeline" title="<?= esc(t('timeline')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 10h18M3 14h18M8 6h13M8 18h13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?= esc(t('timeline')) ?></span>
      </button>
      <button class="view-btn" data-view="gallery" title="<?= esc(t('gallery')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('gallery')) ?></span>
      </button>
      <button class="view-btn" data-view="search" title="<?= esc(t('search')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
          <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?= esc(t('search')) ?></span>
      </button>
    </section>

    <!-- Quick Actions -->
    <section class="diary-actions">
      <button class="action-btn action-primary" id="newEntryBtn">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?= esc(t('new_entry')) ?></span>
      </button>
    </section>

    <!-- Timeline View -->
    <section class="diary-view diary-timeline" id="timelineView">
      <div class="timeline-filters">
        <input type="text" class="filter-input" id="timelineSearch" placeholder="<?= esc(t('search_entries')) ?>">
        <select class="filter-select" id="timelineSort">
          <option value="newest"><?= esc(t('newest_first')) ?></option>
          <option value="oldest"><?= esc(t('oldest_first')) ?></option>
          <option value="title"><?= esc(t('title_az')) ?></option>
        </select>
        <select class="filter-select" id="timelineFilter">
          <option value="all"><?= esc(t('all')) ?></option>
          <option value="today"><?= esc(t('today')) ?></option>
          <option value="week"><?= esc(t('this_week')) ?></option>
          <option value="month"><?= esc(t('this_month')) ?></option>
          <option value="year"><?= esc(t('this_year')) ?></option>
        </select>
      </div>
      <div class="timeline-container" id="timelineContainer">
        <div class="timeline-loading">
          <div class="loading-spinner"></div>
          <p><?= esc(t('loading_entries')) ?></p>
        </div>
      </div>
    </section>

    <!-- Gallery View -->
    <section class="diary-view diary-gallery" id="galleryView" style="display: none;">
      <div class="gallery-grid" id="galleryGrid">
        <div class="gallery-loading">
          <div class="loading-spinner"></div>
          <p><?= esc(t('loading_gallery')) ?></p>
        </div>
      </div>
    </section>

    <!-- Search View -->
    <section class="diary-view diary-search" id="searchView" style="display: none;">
      <div class="search-panel">
        <div class="search-header">
          <input type="text" class="search-input" id="searchInput" placeholder="<?= esc(t('search_through_all')) ?>">
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
            <span><?= esc(t('titles')) ?></span>
          </label>
          <label class="search-filter">
            <input type="checkbox" id="searchBody" checked>
            <span><?= esc(t('content')) ?></span>
          </label>
          <label class="search-filter">
            <input type="checkbox" id="searchTags" checked>
            <span><?= esc(t('tags')) ?></span>
          </label>
        </div>
        <div class="search-results" id="searchResults">
          <div class="search-placeholder">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p><?= esc(t('start_typing_search')) ?></p>
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
        <h2 class="modal-title" id="modalTitle"><?= esc(t('new_entry')) ?></h2>
        <button class="modal-close" id="modalClose">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <form id="entryForm">
          <input type="hidden" id="entryId" value="">
          
          <div class="form-group">
            <label class="form-label"><?= esc(t('date_time')) ?></label>
            <div class="form-row">
              <input type="date" class="form-input" id="entryDate" required>
              <input type="time" class="form-input" id="entryTime" value="00:00">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="entryTitle"><?= esc(t('title')) ?></label>
            <input type="text" class="form-input" id="entryTitle" placeholder="<?= esc(t('my_thoughts_today')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="entryBody"><?= esc(t('content')) ?></label>
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
              <button type="button" class="editor-btn" data-action="strikethrough" title="Strikethrough">
                <s>S</s>
              </button>
              <span class="editor-divider"></span>
              <button type="button" class="editor-btn" data-action="heading" title="Heading">
                <span>H1</span>
              </button>
              <button type="button" class="editor-btn" data-action="bulletList" title="Bullet List">
                <span>•</span>
              </button>
              <button type="button" class="editor-btn" data-action="numberedList" title="Numbered List">
                <span>1.</span>
              </button>
              <span class="editor-divider"></span>
              <button type="button" class="editor-btn" data-action="quote" title="Quote">
                <span>"</span>
              </button>
              <button type="button" class="editor-btn" data-action="code" title="Code">
                <span>&lt;/&gt;</span>
              </button>
            </div>
            <textarea class="form-textarea" id="entryBody" rows="10" placeholder="<?= esc(t('write_thoughts_here')) ?>"></textarea>
            <div class="word-count" id="wordCount">0 <?= esc(t('words')) ?></div>
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(t('tags')) ?></label>
            <div class="tags-container" id="tagsContainer"></div>
            <input type="text" class="form-input" id="tagInput" placeholder="<?= esc(t('type_tag_enter')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(t('mood')) ?></label>
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
            <input type="hidden" id="entryMood">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(t('weather')) ?></label>
            <div class="weather-selector">
              <button type="button" class="weather-btn" data-weather="sunny">☀️</button>
              <button type="button" class="weather-btn" data-weather="cloudy">☁️</button>
              <button type="button" class="weather-btn" data-weather="rainy">🌧️</button>
              <button type="button" class="weather-btn" data-weather="stormy">⛈️</button>
              <button type="button" class="weather-btn" data-weather="snowy">❄️</button>
              <button type="button" class="weather-btn" data-weather="windy">🌬️</button>
            </div>
            <input type="hidden" id="entryWeather">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(t('reminder')) ?></label>
            <select class="form-select" id="reminderMinutes">
              <option value="0"><?= esc(t('no_reminder')) ?></option>
              <option value="15">15 <?= esc(t('minutes_before')) ?></option>
              <option value="30">30 <?= esc(t('minutes_before')) ?></option>
              <option value="60" selected>1 <?= esc(t('hour_before')) ?></option>
              <option value="120">2 <?= esc(t('hours_before')) ?></option>
              <option value="1440">1 <?= esc(t('day_before')) ?></option>
            </select>
          </div>

          <div class="form-group">
            <label class="custom-checkbox">
              <input type="checkbox" id="addToCalendar" checked>
              <span class="checkmark"></span>
              <span><?= esc(t('add_to_calendar')) ?> 📅</span>
            </label>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="cancelBtn">
              <?= esc(t('cancel')) ?>
            </button>
            <button type="button" class="btn btn-ai" id="aiAssistBtn">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" fill="none"/>
              </svg>
              <?= esc(t('ai_assist')) ?>
            </button>
            <button type="submit" class="btn btn-primary" id="saveBtn">
              <?= esc(t('save')) ?>
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
        <h2 class="modal-title"><?= esc(t('share_entry')) ?></h2>
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
            <span><?= esc(t('share_with_friend')) ?></span>
          </button>
          <button class="share-option" onclick="shareAs('link')">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(t('copy_link')) ?></span>
          </button>
          <button class="share-option" onclick="shareAs('export')">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5m0 0L7 8m5-5v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(t('export_pdf')) ?></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.DIARY_LANG = '<?= $pageLang ?>';
    window.USER_ID = <?= $userId ?>;
  </script>
  <script src="/diary/js/diary.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>

