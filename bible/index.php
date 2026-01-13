<?php
/**
 * CRC Bible Reader
 * Matches OAC Bible functionality - English only
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAuth();

$user = Auth::user();
$pageTitle = 'Bible - CRC';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <?= CSRF::meta() ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/bible/css/bible.css?v=<?= time() ?>">
</head>
<body class="bible-body">
  <?php include __DIR__ . '/../home/partials/navbar.php'; ?>

  <main class="bible-main">

    <!-- Quick Navigation Modal -->
    <div class="bible-modal bible-modal-hidden" id="quickNavModal" style="display:none;">
      <div class="bible-modal-overlay" onclick="BibleApp.hideNav()"></div>
      <div class="bible-modal-content">
        <div class="bible-modal-header">
          <h2 class="bible-modal-title">Quick Navigation</h2>
          <button class="bible-modal-close" onclick="BibleApp.hideNav()">&times;</button>
        </div>
        <div class="bible-modal-body">

          <!-- Step 1: Testament Selection -->
          <div class="bible-nav-step" id="navStepTestament">
            <h3 class="bible-nav-step-title">Choose Testament</h3>
            <div class="bible-nav-grid">
              <div class="bible-nav-card" onclick="BibleApp.selectTestament('old')">
                <div class="bible-nav-card-icon">O</div>
                <div class="bible-nav-card-title">Old Testament</div>
                <div class="bible-nav-card-subtitle">Genesis - Malachi</div>
              </div>
              <div class="bible-nav-card" onclick="BibleApp.selectTestament('new')">
                <div class="bible-nav-card-icon">N</div>
                <div class="bible-nav-card-title">New Testament</div>
                <div class="bible-nav-card-subtitle">Matthew - Revelation</div>
              </div>
            </div>
          </div>

          <!-- Step 2: Book Selection -->
          <div class="bible-nav-step bible-nav-hidden" id="navStepBook">
            <button class="bible-nav-back" onclick="BibleApp.showStep('testament')">Back</button>
            <h3 class="bible-nav-step-title" id="navBookTitle">Choose Book</h3>
            <div class="bible-nav-grid" id="navBookGrid"></div>
          </div>

          <!-- Step 3: Chapter Selection -->
          <div class="bible-nav-step bible-nav-hidden" id="navStepChapter">
            <button class="bible-nav-back" onclick="BibleApp.showStep('book')">Back</button>
            <h3 class="bible-nav-step-title" id="navChapterTitle">Choose Chapter</h3>
            <div class="bible-nav-grid bible-nav-grid-small" id="navChapterGrid"></div>
          </div>

        </div>
      </div>
    </div>

    <!-- Verse Context Menu -->
    <div id="verseContextMenu" class="bible-context-menu bible-context-hidden" style="display:none;">
      <div class="bible-context-header">
        <h2 class="bible-context-title">Verse Actions</h2>
        <button class="bible-context-close" onclick="BibleApp.hideMenu()">&times;</button>
      </div>
      <div class="bible-context-body">
        <div class="bible-context-section-title">Highlight Color</div>
        <div class="bible-highlight-colors">
          <button class="bible-color-btn bible-color-1" onclick="BibleApp.highlight(1)"></button>
          <button class="bible-color-btn bible-color-2" onclick="BibleApp.highlight(2)"></button>
          <button class="bible-color-btn bible-color-3" onclick="BibleApp.highlight(3)"></button>
          <button class="bible-color-btn bible-color-4" onclick="BibleApp.highlight(4)"></button>
          <button class="bible-color-btn bible-color-5" onclick="BibleApp.highlight(5)"></button>
          <button class="bible-color-btn bible-color-6" onclick="BibleApp.highlight(6)"></button>
          <button class="bible-color-btn bible-color-btn-clear" onclick="BibleApp.highlight(0)">X</button>
        </div>

        <div class="bible-context-divider"></div>

        <button class="bible-context-item" onclick="BibleApp.bookmark()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2"/>
          </svg>
          Bookmark
        </button>

        <button class="bible-context-item" onclick="BibleApp.addNote()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
          </svg>
          Add Note
        </button>

        <button class="bible-context-item" onclick="BibleApp.askAI()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2"/>
          </svg>
          Ask AI
        </button>

        <button class="bible-context-item" onclick="BibleApp.crossRef()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="2"/>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2"/>
          </svg>
          Cross Refs
        </button>

        <button class="bible-context-item" onclick="BibleApp.copy()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"/>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="2"/>
          </svg>
          Copy
        </button>

        <button class="bible-context-item" onclick="BibleApp.share()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="18" cy="5" r="3" stroke="currentColor" stroke-width="2"/>
            <circle cx="6" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <circle cx="18" cy="19" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M8.59 13.51l6.83 3.98m-.01-10.98l-6.82 3.98" stroke="currentColor" stroke-width="2"/>
          </svg>
          Share
        </button>
      </div>
    </div>

    <!-- Search Panel -->
    <section class="bible-panel bible-panel-hidden" id="searchPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">Search Bible</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('search')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div class="bible-search-container">
          <input type="text" id="searchInput" class="bible-input" placeholder="Type to search...">
          <button class="bible-btn bible-btn-primary" id="searchBtn">
            <svg class="bible-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>Search</span>
          </button>
        </div>
        <div id="searchResults" class="bible-search-results"></div>
      </div>
    </section>

    <!-- Notes Panel -->
    <section class="bible-panel bible-panel-hidden" id="notesPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">Notes</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('notes')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div id="notesList" class="bible-notes-list">
          <p class="bible-empty-state">No notes yet. Click on a verse to add a note!</p>
        </div>
        <div id="noteEditor" class="bible-note-editor bible-note-hidden">
          <div class="bible-note-ref" id="noteReference"></div>
          <textarea id="noteText" class="bible-textarea" placeholder="Write your note here..."></textarea>
          <div class="bible-note-actions">
            <button class="bible-btn bible-btn-primary" onclick="BibleApp.saveNote()">Save</button>
            <button class="bible-btn" onclick="BibleApp.cancelNote()">Cancel</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Bookmarks Panel -->
    <section class="bible-panel bible-panel-hidden" id="bookmarksPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">Bookmarks</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('bookmarks')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div id="bookmarksList" class="bible-bookmarks-list">
          <p class="bible-empty-state">No bookmarks yet.</p>
        </div>
      </div>
    </section>

    <!-- AI Commentary Panel -->
    <section class="bible-panel bible-panel-hidden" id="aiPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">AI Commentary</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('ai')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div id="aiOutput" class="bible-ai-output">
          <p class="bible-empty-state">Select a verse to get AI explanation</p>
        </div>
      </div>
    </section>

    <!-- Cross References Panel -->
    <section class="bible-panel bible-panel-hidden" id="crossRefPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">Cross References</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('crossRef')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div id="crossRefList" class="bible-cross-ref-list">
          <p class="bible-empty-state">Select a verse to see cross references</p>
        </div>
      </div>
    </section>

    <!-- Reading Plan Panel -->
    <section class="bible-panel bible-panel-hidden" id="readingPlanPanel" style="display:none;">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title">Reading Plan</h3>
        <button class="bible-panel-close" onclick="BibleApp.closePanel('readingPlan')">&times;</button>
      </div>
      <div class="bible-panel-body">
        <div id="readingPlanContent"></div>
      </div>
    </section>

    <!-- Main Reading View (Single Column) -->
    <section class="bible-reading-section">
      <div class="bible-single-container">
        <div class="bible-column" id="leftColumn">
          <div class="bible-column-content" id="leftContent">
            <div class="bible-loading">Loading Bible...</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Fixed Footer Toolbar -->
  <section class="bible-toolbar">
    <button class="bible-tool-btn bible-tool-btn-primary" onclick="BibleApp.showNav()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="currentColor" stroke-width="2"/>
        <path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2"/>
      </svg>
      <span>Navigate</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.toggleSearch()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>Search</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.toggleNotes()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
      </svg>
      <span>Notes</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.toggleBookmarks()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2"/>
      </svg>
      <span>Bookmarks</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.showReadingPlan()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
        <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2"/>
      </svg>
      <span>Plan</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.fontDecrease()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 7V4h16v3M9 20h6M12 4v16" stroke="currentColor" stroke-width="2"/>
        <circle cx="18" cy="18" r="4" fill="currentColor"/>
        <path d="M16 18h4" stroke="white" stroke-width="1.5"/>
      </svg>
      <span>A-</span>
    </button>

    <button class="bible-tool-btn" onclick="BibleApp.fontIncrease()">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 7V4h16v3M9 20h6M12 4v16" stroke="currentColor" stroke-width="2"/>
        <circle cx="18" cy="18" r="4" fill="currentColor"/>
        <path d="M16 18h4M18 16v4" stroke="white" stroke-width="1.5"/>
      </svg>
      <span>A+</span>
    </button>
  </section>

  <script>
    window.BIBLE = {
      userId: <?= (int)$user['id'] ?>,
      path: '/bible/api/bible_data.php'
    };
  </script>
  <script src="/bible/js/bible.js?v=<?= time() ?>"></script>

</body>
</html>
