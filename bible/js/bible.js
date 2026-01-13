/**
 * CRC Bible Reader JavaScript
 * Matches OAC Bible functionality - English only
 */
(() => {
  'use strict';

  // ===== DEBUG PANEL FOR MOBILE TROUBLESHOOTING =====
  const DEBUG_MODE = false; // Set to false in production
  let debugPanel = null;

  function createDebugPanel() {
    if (!DEBUG_MODE) return;
    debugPanel = document.createElement('div');
    debugPanel.id = 'bibleDebugPanel';
    debugPanel.style.cssText = `
      position: fixed;
      bottom: 80px;
      left: 10px;
      right: 10px;
      max-height: 150px;
      overflow-y: auto;
      background: rgba(0,0,0,0.9);
      color: #0f0;
      font-family: monospace;
      font-size: 10px;
      padding: 8px;
      border-radius: 8px;
      z-index: 99999;
      border: 1px solid #0f0;
    `;
    document.body.appendChild(debugPanel);
    debugLog('Debug panel ready');
  }

  function debugLog(msg) {
    console.log('[Bible]', msg);
    if (debugPanel) {
      const line = document.createElement('div');
      line.textContent = `${new Date().toLocaleTimeString()}: ${msg}`;
      debugPanel.appendChild(line);
      debugPanel.scrollTop = debugPanel.scrollHeight;
    }
  }

  // Create debug panel immediately
  if (document.body) {
    createDebugPanel();
  } else {
    document.addEventListener('DOMContentLoaded', createDebugPanel);
  }

  // ===== POLYFILLS FOR OLDER WEBVIEWS =====
  // requestIdleCallback polyfill
  window.requestIdleCallback = window.requestIdleCallback || function(cb) {
    const start = Date.now();
    return setTimeout(function() {
      cb({
        didTimeout: false,
        timeRemaining: function() {
          return Math.max(0, 50 - (Date.now() - start));
        }
      });
    }, 1);
  };

  window.cancelIdleCallback = window.cancelIdleCallback || function(id) {
    clearTimeout(id);
  };

  // ===== UTILITIES =====
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[m]);

  // ===== INDEXEDDB SETUP =====
  let db = null;
  const DB_NAME = 'CRCBibleDB';
  const DB_VERSION = 2;
  const STORE_NAME = 'bibleData';

  // Check if IndexedDB is available
  const indexedDBAvailable = (function() {
    try {
      return typeof indexedDB !== 'undefined' && indexedDB !== null;
    } catch (e) {
      return false;
    }
  })();

  async function initDB() {
    if (!indexedDBAvailable) {
      console.warn('IndexedDB not available, using memory cache only');
      return null;
    }

    return new Promise((resolve, reject) => {
      try {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => {
          console.warn('IndexedDB failed to open:', request.error);
          resolve(null); // Resolve with null instead of rejecting
        };

        request.onsuccess = () => {
          db = request.result;
          resolve(db);
        };

        request.onupgradeneeded = (e) => {
          const database = e.target.result;
          if (!database.objectStoreNames.contains(STORE_NAME)) {
            database.createObjectStore(STORE_NAME, { keyPath: 'key' });
          }
        };
      } catch (e) {
        console.warn('IndexedDB initialization error:', e);
        resolve(null);
      }
    });
  }

  async function getFromDB(key) {
    if (!db) return null;

    return new Promise((resolve) => {
      try {
        const transaction = db.transaction([STORE_NAME], 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.get(key);

        request.onsuccess = () => resolve(request.result?.data || null);
        request.onerror = () => resolve(null);
      } catch (e) {
        resolve(null);
      }
    });
  }

  async function saveToDB(key, data) {
    if (!db) return false;

    return new Promise((resolve) => {
      try {
        const transaction = db.transaction([STORE_NAME], 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.put({ key, data, timestamp: Date.now() });

        request.onsuccess = () => resolve(true);
        request.onerror = () => resolve(false);
      } catch (e) {
        resolve(false);
      }
    });
  }

  // ===== STATE =====
  const state = {
    userId: window.BIBLE?.userId || 0,
    path: window.BIBLE?.path || '/bible/bibles/en_kjv.json',
    data: null,
    books: [],

    selectedVerse: null,
    highlights: {},
    notes: {},
    bookmarks: {},
    fontSize: 'medium',
    navState: {
      testament: null,
      book: null,
      chapter: null
    },

    currentBookIndex: 0,
    currentChapter: 1,
    renderedChapters: new Set(),
    isLoading: false
  };

  // ===== BIBLE BOOK LISTS =====
  const OLD_TESTAMENT = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings',
    '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther',
    'Job', 'Psalms', 'Proverbs', 'Ecclesiastes', 'Song of Solomon',
    'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel',
    'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum',
    'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi'
  ];

  const NEW_TESTAMENT = [
    'Matthew', 'Mark', 'Luke', 'John', 'Acts',
    'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
    'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
    '1 Timothy', '2 Timothy', 'Titus', 'Philemon',
    'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
    'Jude', 'Revelation'
  ];

  // ===== ELEMENTS =====
  const els = {
    quickNavToggle: $('quickNavToggle'),
    quickNavModal: $('quickNavModal'),
    quickNavOverlay: $('quickNavOverlay'),
    quickNavClose: $('quickNavClose'),
    navStepTestament: $('navStepTestament'),
    navStepBook: $('navStepBook'),
    navStepChapter: $('navStepChapter'),
    navBookTitle: $('navBookTitle'),
    navChapterTitle: $('navChapterTitle'),
    navBookGrid: $('navBookGrid'),
    navChapterGrid: $('navChapterGrid'),
    navBackToTestament: $('navBackToTestament'),
    navBackToBook: $('navBackToBook'),
    searchToggle: $('searchToggle'),
    notesToggle: $('notesToggle'),
    bookmarksToggle: $('bookmarksToggle'),
    readingPlanToggle: $('readingPlanToggle'),
    searchPanel: $('searchPanel'),
    searchClose: $('searchClose'),
    searchInput: $('searchInput'),
    searchBtn: $('searchBtn'),
    searchResults: $('searchResults'),
    notesPanel: $('notesPanel'),
    notesClose: $('notesClose'),
    notesList: $('notesList'),
    noteEditor: $('noteEditor'),
    noteReference: $('noteReference'),
    noteText: $('noteText'),
    saveNoteBtn: $('saveNoteBtn'),
    cancelNoteBtn: $('cancelNoteBtn'),
    bookmarksPanel: $('bookmarksPanel'),
    bookmarksClose: $('bookmarksClose'),
    bookmarksList: $('bookmarksList'),
    aiPanel: $('aiPanel'),
    aiClose: $('aiClose'),
    aiOutput: $('aiOutput'),
    crossRefPanel: $('crossRefPanel'),
    crossRefClose: $('crossRefClose'),
    crossRefList: $('crossRefList'),
    readingPlanPanel: $('readingPlanPanel'),
    readingPlanClose: $('readingPlanClose'),
    readingPlanContent: $('readingPlanContent'),
    leftContent: $('leftContent'),
    leftColumn: $('leftColumn'),
    singleContainer: document.querySelector('.bible-single-container'),
    verseContextMenu: $('verseContextMenu'),
    ctxBookmark: $('ctxBookmark'),
    ctxAddNote: $('ctxAddNote'),
    ctxAI: $('ctxAI'),
    ctxCrossRef: $('ctxCrossRef'),
    ctxCopy: $('ctxCopy'),
    ctxShare: $('ctxShare'),
    ctxClose: $('ctxClose'),
    fontSizeIncrease: $('fontSizeIncrease'),
    fontSizeDecrease: $('fontSizeDecrease')
  };

  // ===== LOADING OVERLAY =====
  function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'bibleLoadingOverlay';
    overlay.innerHTML = `
      <div class="bible-loading-container">
        <div class="bible-loading-spinner"></div>
        <h2 class="bible-loading-title">Loading Bible...</h2>
        <div class="bible-loading-bar">
          <div class="bible-loading-progress" id="loadingProgress"></div>
        </div>
        <p class="bible-loading-text" id="loadingText">0%</p>
      </div>
    `;
    document.body.appendChild(overlay);

    // Safety timeout - remove overlay after 30 seconds no matter what
    setTimeout(() => {
      const existingOverlay = $('bibleLoadingOverlay');
      if (existingOverlay) {
        console.warn('Loading timeout - forcing overlay removal');
        existingOverlay.remove();
      }
    }, 30000);

    return overlay;
  }

  function updateLoadingProgress(percent, text) {
    const progress = $('loadingProgress');
    const textEl = $('loadingText');

    if (progress) progress.style.width = `${percent}%`;
    if (textEl) textEl.textContent = text || `${Math.round(percent)}%`;
  }

  function removeLoadingOverlay() {
    const overlay = $('bibleLoadingOverlay');
    if (overlay) {
      overlay.style.opacity = '0';
      setTimeout(() => overlay.remove(), 300);
    }
  }

  // ===== DATA LOADING =====
  async function loadJSON(url, onProgress) {
    const cacheKey = `bible_v2_${url}`;

    const cached = await getFromDB(cacheKey);
    if (cached) {
      if (onProgress) onProgress(100);
      return cached;
    }

    try {
      const res = await fetch(url, {
        credentials: 'same-origin',
        cache: 'force-cache'
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      let data;

      // Check if streaming is supported (some WebViews don't support it)
      const supportsStreaming = res.body && typeof res.body.getReader === 'function';

      if (supportsStreaming) {
        // Use streaming for progress reporting
        try {
          const contentLength = +res.headers.get('Content-Length');
          const reader = res.body.getReader();

          let receivedLength = 0;
          let chunks = [];

          while(true) {
            const {done, value} = await reader.read();
            if (done) break;

            chunks.push(value);
            receivedLength += value.length;

            if (onProgress && contentLength) {
              onProgress((receivedLength / contentLength) * 100);
            }
          }

          const chunksAll = new Uint8Array(receivedLength);
          let position = 0;
          for(let chunk of chunks) {
            chunksAll.set(chunk, position);
            position += chunk.length;
          }

          const text = new TextDecoder("utf-8").decode(chunksAll);
          data = JSON.parse(text);
        } catch (streamError) {
          console.warn('Streaming failed, falling back to simple fetch:', streamError);
          // Refetch without streaming
          const res2 = await fetch(url, { credentials: 'same-origin' });
          data = await res2.json();
          if (onProgress) onProgress(100);
        }
      } else {
        // Fallback for WebViews without streaming support
        console.log('Using simple fetch (no streaming support)');
        data = await res.json();
        if (onProgress) onProgress(100);
      }

      await saveToDB(cacheKey, data);

      return data;

    } catch (e) {
      console.error(`Failed to load ${url}:`, e);
      throw e;
    }
  }

  function extractBooks(data) {
    if (!data) return [];
    if (Array.isArray(data.books)) {
      return data.books.map(b => b.name || b.book || 'Unknown');
    }
    if (typeof data === 'object') {
      return Object.keys(data);
    }
    return [];
  }

  function getBook(data, bookName) {
    if (!data || !bookName) return null;
    if (Array.isArray(data.books)) {
      return data.books.find(b => (b.name || b.book) === bookName);
    }
    if (typeof data[bookName] === 'object') {
      return data[bookName];
    }
    return null;
  }

  function getChapterCount(data, bookName) {
    const book = getBook(data, bookName);
    if (!book) return 0;

    if (Array.isArray(book.chapters)) return book.chapters.length;
    if (Array.isArray(book.chapter)) return book.chapter.length;

    const numericKeys = Object.keys(book).filter(k => /^\d+$/.test(k));
    return numericKeys.length;
  }

  function getChapter(data, bookName, chapterNum) {
    const book = getBook(data, bookName);
    if (!book) return [];

    let chapter = null;

    // Try different chapter access patterns
    if (Array.isArray(book.chapters)) {
      chapter = book.chapters[chapterNum - 1];
    } else if (Array.isArray(book.chapter)) {
      chapter = book.chapter[chapterNum - 1];
    } else if (book[String(chapterNum)]) {
      chapter = book[String(chapterNum)];
    } else if (book[chapterNum]) {
      chapter = book[chapterNum];
    }

    // Ensure we return an array
    if (Array.isArray(chapter)) return chapter;
    if (chapter && typeof chapter === 'object') return Object.values(chapter);
    return [];
  }

  function parseVerse(item) {
    if (!item) return { type: 'verse', text: '' };

    if (typeof item === 'string') {
      return { type: 'verse', text: item };
    }

    // Helper to extract string value
    function extractText(val) {
      if (typeof val === 'string') return val;
      if (typeof val === 'number') return String(val);
      if (typeof val === 'object' && val !== null) {
        // Handle nested objects - try to find text content
        if (val.text) return extractText(val.text);
        if (val.content) return extractText(val.content);
        if (val.value) return extractText(val.value);
        const vals = Object.values(val);
        if (vals.length > 0 && typeof vals[0] === 'string') return vals[0];
      }
      return '';
    }

    if (typeof item === 'object') {
      if (item.h !== undefined) return { type: 'heading', text: extractText(item.h) };
      if (item.v !== undefined) return { type: 'verse', text: extractText(item.v) };
      if (item.text !== undefined) return { type: 'verse', text: extractText(item.text) };
      if (item.verse !== undefined) return { type: 'verse', text: extractText(item.verse) };
      if (item.t !== undefined) return { type: 'verse', text: extractText(item.t) };

      const vals = Object.values(item);
      if (vals.length > 0) return { type: 'verse', text: extractText(vals[0]) };
    }

    return { type: 'verse', text: '' };
  }

  function makeRef(book, chapter, verse) {
    return `${book}-${chapter}-${verse}`;
  }

  function parseRef(ref) {
    const parts = ref.split('-');
    const verse = parseInt(parts.pop(), 10);
    const chapter = parseInt(parts.pop(), 10);
    const book = parts.join('-');
    return { book, chapter, verse };
  }

  // ===== HEADER UPDATE =====
  function updateHeaderRef() {
    const headerTitle = document.querySelector('.nav-logo');
    if (!headerTitle) return;

    const verses = document.querySelectorAll('.bible-verse[data-verse]');
    if (!verses.length) return;

    let topVerse = null;
    let minDist = Infinity;

    verses.forEach(v => {
      const rect = v.getBoundingClientRect();
      const dist = Math.abs(rect.top - 150);
      if (rect.top > 70 && rect.top < window.innerHeight && dist < minDist) {
        minDist = dist;
        topVerse = v;
      }
    });

    if (topVerse) {
      const book = topVerse.dataset.book;
      const chapter = topVerse.dataset.chapter;
      const verse = topVerse.dataset.verse;
      headerTitle.textContent = `${book} ${chapter}:${verse}`;
    }
  }

  // ===== PROGRESSIVE RENDERING =====
  function renderInitialChapters() {
    debugLog('renderInitialChapters called');

    if (!els.leftContent) {
      debugLog('ERROR: leftContent not found!');
      return;
    }

    if (!state.books || !state.books.length) {
      debugLog('ERROR: No books to render');
      els.leftContent.innerHTML = '<div style="padding:2rem;text-align:center;color:#f00;">No Bible books found</div>';
      return;
    }

    const startBook = state.books[0] || 'Genesis';
    debugLog('First book: ' + startBook);

    state.currentBookIndex = 0;
    state.currentChapter = 1;

    debugLog('Creating chapter element...');
    const leftChapter = createChapterElement(startBook, 1);

    if (!leftChapter) {
      debugLog('ERROR: createChapterElement returned null');
      els.leftContent.innerHTML = '<div style="padding:2rem;text-align:center;color:#f00;">Failed to create chapter</div>';
      return;
    }

    const verseCount = leftChapter.querySelectorAll('.bible-verse').length;
    debugLog('Chapter created, verses: ' + verseCount);

    if (verseCount === 0) {
      debugLog('WARNING: No verses in chapter!');
    }

    els.leftContent.innerHTML = '';
    els.leftContent.appendChild(leftChapter);

    debugLog('Chapter appended to DOM');

    state.renderedChapters.add(`${startBook}-1`);

    applyFontSize();
    bindVerseInteractions();

    requestIdleCallback(() => {
      loadNextChapters(3);
    });
  }

  function loadNextChapters(count = 5) {
    if (state.isLoading) return;
    state.isLoading = true;

    let loaded = 0;
    let bookIdx = state.currentBookIndex;
    let chapter = state.currentChapter + 1;

    const loadChapter = () => {
      if (loaded >= count || bookIdx >= state.books.length) {
        state.isLoading = false;
        return;
      }

      const book = state.books[bookIdx];
      const chapterCount = getChapterCount(state.data, book);

      if (chapter > chapterCount) {
        bookIdx++;
        chapter = 1;
        requestIdleCallback(loadChapter);
        return;
      }

      const key = `${book}-${chapter}`;

      if (!state.renderedChapters.has(key)) {
        const chapterEl = createChapterElement(book, chapter);
        els.leftContent.appendChild(chapterEl);
        state.renderedChapters.add(key);
      }

      loaded++;
      chapter++;
      state.currentChapter = chapter - 1;
      state.currentBookIndex = bookIdx;

      requestIdleCallback(loadChapter);
    };

    requestIdleCallback(loadChapter);
  }

  function createChapterElement(book, chapter) {
    const chapterDiv = document.createElement('div');
    chapterDiv.className = 'bible-chapter-block';
    chapterDiv.dataset.book = book;
    chapterDiv.dataset.chapter = chapter;
    // Inline styles for WebView compatibility
    chapterDiv.style.cssText = 'display: block; margin-bottom: 2rem;';

    const chTitle = document.createElement('h3');
    chTitle.className = 'bible-chapter-title';
    chTitle.textContent = `${book} ${chapter}`;
    // Inline styles for WebView compatibility
    chTitle.style.cssText = 'display: block; color: #8B5CF6; font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);';
    chapterDiv.appendChild(chTitle);

    const verses = getChapter(state.data, book, chapter);
    let verseNum = 0;

    verses.forEach(v => {
      const parsed = parseVerse(v);
      if (parsed.type === 'heading') {
        const h = document.createElement('div');
        h.className = 'bible-heading';
        h.textContent = parsed.text;
        // Inline styles for WebView compatibility
        h.style.cssText = 'display: block; color: #A1A1C7; font-size: 1rem; font-weight: 600; font-style: italic; margin: 1.5rem 0 0.75rem;';
        chapterDiv.appendChild(h);
      } else {
        verseNum++;
        const ref = makeRef(book, chapter, verseNum);
        const vDiv = createVerseElement(book, chapter, verseNum, parsed.text, ref);
        chapterDiv.appendChild(vDiv);
      }
    });

    return chapterDiv;
  }

  function createVerseElement(book, chapter, verseNum, text, ref) {
    const vDiv = document.createElement('div');
    vDiv.className = 'bible-verse';
    vDiv.dataset.ref = ref;
    vDiv.dataset.book = book;
    vDiv.dataset.chapter = chapter;
    vDiv.dataset.verse = verseNum;

    // Inline styles for WebView compatibility
    vDiv.style.cssText = 'display: block; color: #FFFFFF; padding: 0.35rem 0.5rem; margin: 0.25rem 0;';

    if (state.highlights[ref]) {
      vDiv.classList.add(`bible-highlight-${state.highlights[ref]}`);
    }

    const numSpan = document.createElement('span');
    numSpan.className = 'bible-verse-number';
    numSpan.textContent = verseNum;
    // Inline styles for WebView compatibility
    numSpan.style.cssText = 'display: inline; color: #8B5CF6; font-size: 0.75rem; font-weight: 700; margin-right: 0.25rem;';

    const textSpan = document.createElement('span');
    textSpan.className = 'bible-verse-text';
    textSpan.textContent = text;
    // Inline styles for WebView compatibility
    textSpan.style.cssText = 'display: inline; color: #FFFFFF; font-size: 1.05rem; line-height: 1.7;';

    vDiv.appendChild(numSpan);
    vDiv.appendChild(textSpan);

    if (state.bookmarks[ref]) {
      const bookmarkIcon = document.createElement('span');
      bookmarkIcon.className = 'bible-bookmark-indicator';
      bookmarkIcon.innerHTML = '🔖';
      bookmarkIcon.title = 'Bookmarked';
      vDiv.appendChild(bookmarkIcon);
    }

    if (state.notes[ref]) {
      const noteIcon = document.createElement('span');
      noteIcon.className = 'bible-note-indicator';
      noteIcon.innerHTML = '📝';
      noteIcon.title = 'Click to view note';
      noteIcon.dataset.ref = ref;
      vDiv.appendChild(noteIcon);
    }

    return vDiv;
  }

  // ===== INFINITE SCROLL =====
  function setupInfiniteScroll() {
    let scrollTimeout = null;

    const handleScroll = () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const column = els.leftColumn;
        const scrollHeight = column.scrollHeight;
        const scrollTop = column.scrollTop;
        const clientHeight = column.clientHeight;

        if (scrollHeight - scrollTop - clientHeight < 1000) {
          loadNextChapters(5);
        }

        updateHeaderRef();
      }, 100);
    };

    els.leftColumn.addEventListener('scroll', handleScroll, { passive: true });
  }

  // ===== VERSE INTERACTIONS =====
  function bindVerseInteractions() {
    document.querySelectorAll('.bible-verse:not(.bound)').forEach(verse => {
      verse.classList.add('bound');

      // Handle click for verse selection
      const clickHandler = (e) => {
        handleVerseClick(e, verse);
      };

      verse.addEventListener('click', clickHandler);
      verse.addEventListener('contextmenu', clickHandler);

      // Also handle touch for mobile WebView
      let touchTimeout = null;
      verse.addEventListener('touchstart', (e) => {
        touchTimeout = setTimeout(() => {
          // Long press - show context menu
          handleVerseClick(e, verse);
        }, 500);
      }, { passive: true });

      verse.addEventListener('touchend', (e) => {
        if (touchTimeout) {
          clearTimeout(touchTimeout);
          touchTimeout = null;
        }
      });

      verse.addEventListener('touchmove', () => {
        if (touchTimeout) {
          clearTimeout(touchTimeout);
          touchTimeout = null;
        }
      }, { passive: true });
    });
  }

  function handleVerseClick(e, verse) {
    e.preventDefault();
    e.stopPropagation();

    // Check if clicking on note indicator
    const target = e.target;
    if (target && target.classList && target.classList.contains('bible-note-indicator')) {
      const ref = target.dataset.ref;
      // Navigate to add-note page
      const parsed = parseRef(ref);
      window.location.href = '/bible/add-note.php?book=' + encodeURIComponent(parsed.book) +
        '&chapter=' + parsed.chapter +
        '&verse=' + parsed.verse;
      return;
    }

    // Get the verse element - either from parameter or currentTarget
    const verseEl = verse || e.currentTarget;
    if (!verseEl || !verseEl.dataset) return;

    // Get verse data
    const book = verseEl.dataset.book;
    const chapter = verseEl.dataset.chapter;
    const verseNum = verseEl.dataset.verse;
    const text = verseEl.querySelector('.bible-verse-text')?.textContent || '';

    // Navigate to verse-actions page (full page, no overlay)
    window.location.href = '/bible/verse-actions.php?book=' + encodeURIComponent(book) +
      '&chapter=' + chapter +
      '&verse=' + verseNum +
      '&text=' + encodeURIComponent(text);
  }

  function showContextMenu(x, y) {
    const menu = els.verseContextMenu;
    if (!menu) {
      console.warn('Context menu element not found');
      return;
    }

    // First ensure it's hidden, then show it (forces re-render)
    menu.classList.add('bible-context-hidden');

    // Use setTimeout to ensure the hide is applied before showing
    setTimeout(() => {
      menu.classList.remove('bible-context-hidden');
    }, 10);
  }

  function hideContextMenu() {
    const menu = els.verseContextMenu;
    if (menu) {
      menu.classList.add('bible-context-hidden');
    }
  }

  // ===== HIGHLIGHTS =====
  async function applyHighlight(color) {
    if (!state.selectedVerse) return;

    try {
      const parsed = parseRef(state.selectedVerse);
      const bookIndex = state.books.indexOf(parsed.book) + 1;

      const formData = new FormData();
      formData.append('action', color === 0 ? 'remove' : 'add');
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse', parsed.verse);
      formData.append('color', color); // Send as integer 1-6

      const res = await fetch('/bible/api/highlights.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      const data = await res.json();

      if (data.ok) {
        if (color === 0) {
          delete state.highlights[state.selectedVerse];
        } else {
          state.highlights[state.selectedVerse] = color;
        }

        refreshVerseDisplay();
        showToast('Highlight saved');
      } else {
        throw new Error(data.error || 'Failed to save');
      }
    } catch (e) {
      console.error('Highlight save failed:', e);
      showToast('Could not save highlight');
    }

    hideContextMenu();
  }

  function getColorName(num) {
    const colors = ['none', 'pink', 'orange', 'yellow', 'green', 'blue', 'purple'];
    return colors[num] || 'yellow';
  }

  // ===== BOOKMARKS =====
  async function toggleBookmark() {
    if (!state.selectedVerse) return;

    const verseEl = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verseEl?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    try {
      const isBookmarked = !!state.bookmarks[state.selectedVerse];

      const formData = new FormData();
      formData.append('action', 'toggle');
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse', parsed.verse);

      const res = await fetch('/bible/api/bookmarks.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      const data = await res.json();

      if (data.ok) {
        if (data.bookmarked === false) {
          delete state.bookmarks[state.selectedVerse];
          showToast('Bookmark removed');
        } else {
          state.bookmarks[state.selectedVerse] = {
            text: verseText,
            timestamp: Date.now()
          };
          showToast('Bookmark added');
        }

        refreshVerseDisplay();
      } else {
        throw new Error(data.error || 'Failed to save');
      }
    } catch (e) {
      console.error('Bookmark toggle failed:', e);
      showToast('Could not save bookmark');
    }

    hideContextMenu();
  }

  function renderBookmarksList() {
    if (!els.bookmarksList) return;

    const refs = Object.keys(state.bookmarks).sort((a, b) => {
      return (state.bookmarks[b].timestamp || 0) - (state.bookmarks[a].timestamp || 0);
    });

    if (!refs.length) {
      els.bookmarksList.innerHTML = `<p class="bible-empty-state">No bookmarks yet.</p>`;
      return;
    }

    const frag = document.createDocumentFragment();

    refs.forEach(ref => {
      const parsed = parseRef(ref);
      const bookmark = state.bookmarks[ref];

      const item = document.createElement('div');
      item.className = 'bible-bookmark-item';
      item.innerHTML = `
        <div class="bible-bookmark-ref">${esc(parsed.book)} ${parsed.chapter}:${parsed.verse}</div>
        <div class="bible-bookmark-text">${esc((bookmark.text || '').substring(0, 100))}...</div>
      `;

      item.addEventListener('click', () => {
        goToReference(ref);
        hidePanel(els.bookmarksPanel);
      });

      frag.appendChild(item);
    });

    els.bookmarksList.innerHTML = '';
    els.bookmarksList.appendChild(frag);
  }

  function goToReference(ref) {
    const parsed = parseRef(ref);

    const bookIdx = state.books.indexOf(parsed.book);
    if (bookIdx !== -1) {
      state.currentBookIndex = bookIdx;
      state.currentChapter = parsed.chapter;

      const key = `${parsed.book}-${parsed.chapter}`;

      if (!state.renderedChapters.has(key)) {
        const chapterEl = createChapterElement(parsed.book, parsed.chapter);
        els.leftContent.appendChild(chapterEl);
        state.renderedChapters.add(key);
        bindVerseInteractions();
      }
    }

    setTimeout(() => {
      const verseEl = document.querySelector(`[data-ref="${ref}"]`);
      if (verseEl) {
        verseEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        verseEl.classList.add('bible-verse-flash');
        setTimeout(() => verseEl.classList.remove('bible-verse-flash'), 2000);
      }
    }, 100);
  }

  // ===== NOTES =====
  function renderNotesList() {
    if (!els.notesList) return;

    const refs = Object.keys(state.notes);

    if (!refs.length) {
      els.notesList.innerHTML = `<p class="bible-empty-state">No notes yet. Click on a verse to add a note!</p>`;
      return;
    }

    const frag = document.createDocumentFragment();

    refs.forEach(ref => {
      const parsed = parseRef(ref);

      const noteItem = document.createElement('div');
      noteItem.className = 'bible-note-item';
      noteItem.innerHTML = `
        <div class="bible-note-item-ref">${esc(parsed.book)} ${parsed.chapter}:${parsed.verse}</div>
        <div class="bible-note-item-text">${esc(state.notes[ref])}</div>
        <div class="bible-note-item-actions">
          <button class="bible-btn-small bible-note-edit" data-ref="${ref}">Edit</button>
          <button class="bible-btn-small bible-note-delete" data-ref="${ref}">Delete</button>
        </div>
      `;

      noteItem.querySelector('.bible-note-edit').addEventListener('click', (e) => {
        e.stopPropagation();
        showNoteEditor(ref);
      });

      noteItem.querySelector('.bible-note-delete').addEventListener('click', async (e) => {
        e.stopPropagation();
        if (confirm('Delete this note?')) {
          await deleteNote(ref);
        }
      });

      noteItem.addEventListener('click', () => {
        goToReference(ref);
      });

      frag.appendChild(noteItem);
    });

    els.notesList.innerHTML = '';
    els.notesList.appendChild(frag);
  }

  function showNoteEditor(ref) {
    if (!els.noteReference || !els.noteText || !els.notesList || !els.noteEditor) return;

    state.selectedVerse = ref;
    const parsed = parseRef(ref);

    els.noteReference.textContent = `${parsed.book} ${parsed.chapter}:${parsed.verse}`;
    els.noteText.value = state.notes[ref] || '';
    els.notesList.classList.add('bible-note-hidden');
    els.noteEditor.classList.remove('bible-note-hidden');
  }

  async function saveNote() {
    if (!state.selectedVerse || !els.noteText) return;

    const text = els.noteText.value.trim();
    const parsed = parseRef(state.selectedVerse);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    try {
      const formData = new FormData();
      formData.append('action', text ? 'add' : 'delete');
      formData.append('version', 'KJV');
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse_start', parsed.verse);
      formData.append('verse_end', parsed.verse);
      formData.append('content', text);

      const res = await fetch('/bible/api/notes.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      const data = await res.json();

      if (data.ok) {
        if (text) {
          state.notes[state.selectedVerse] = text;
          showToast('Note saved');
        } else {
          delete state.notes[state.selectedVerse];
          showToast('Note deleted');
        }

        els.noteEditor?.classList.add('bible-note-hidden');
        els.notesList?.classList.remove('bible-note-hidden');
        renderNotesList();
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Note save failed:', e);
      showToast('Could not save note');
    }
  }

  async function deleteNote(ref) {
    const parsed = parseRef(ref);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    try {
      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('version', 'KJV');
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse_start', parsed.verse);

      const res = await fetch('/bible/api/notes.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      const data = await res.json();

      if (data.ok) {
        delete state.notes[ref];
        renderNotesList();
        refreshVerseDisplay();
        showToast('Note deleted');
      }
    } catch (e) {
      console.error('Note delete failed:', e);
    }
  }

  function cancelNote() {
    els.noteEditor?.classList.add('bible-note-hidden');
    els.notesList?.classList.remove('bible-note-hidden');
  }

  function addNoteToVerse() {
    if (!state.selectedVerse) {
      showToast('Select a verse first!');
      return;
    }
    showPanel(els.notesPanel);
    showNoteEditor(state.selectedVerse);
    hideContextMenu();
  }

  // ===== NAVIGATION =====
  function showQuickNav() {
    const modal = els.quickNavModal;
    if (!modal) {
      console.warn('Navigation modal not found');
      return;
    }

    // Reset state
    state.navState = { testament: null, book: null, chapter: null };

    // Show testament step first
    showNavStep('testament');

    // Show modal
    modal.classList.remove('bible-modal-hidden');
    document.body.style.overflow = 'hidden';
  }

  function hideQuickNav() {
    const modal = els.quickNavModal;
    if (!modal) return;

    modal.classList.add('bible-modal-hidden');
    document.body.style.overflow = '';
  }

  function showNavStep(step) {
    // Get all step elements
    const stepTestament = els.navStepTestament;
    const stepBook = els.navStepBook;
    const stepChapter = els.navStepChapter;

    // Hide all steps first
    if (stepTestament) {
      stepTestament.style.display = step === 'testament' ? 'block' : 'none';
      stepTestament.classList.toggle('bible-nav-hidden', step !== 'testament');
    }
    if (stepBook) {
      stepBook.style.display = step === 'book' ? 'block' : 'none';
      stepBook.classList.toggle('bible-nav-hidden', step !== 'book');
    }
    if (stepChapter) {
      stepChapter.style.display = step === 'chapter' ? 'block' : 'none';
      stepChapter.classList.toggle('bible-nav-hidden', step !== 'chapter');
    }
  }

  function renderTestamentChoice() {
    const cards = document.querySelectorAll('[data-testament]');

    cards.forEach(btn => {
      // Use both click and touchend for better mobile support
      const handler = (e) => {
        e.preventDefault();
        e.stopPropagation();

        state.navState.testament = btn.dataset.testament;
        renderBookChoice();
        showNavStep('book');
      };

      btn.addEventListener('click', handler);
      btn.addEventListener('touchend', handler);
    });
  }

  function renderBookChoice() {
    const books = state.navState.testament === 'old' ? OLD_TESTAMENT : NEW_TESTAMENT;
    const title = state.navState.testament === 'old' ? 'Old Testament Books' : 'New Testament Books';

    if (!els.navBookTitle || !els.navBookGrid) return;

    els.navBookTitle.textContent = title;
    els.navBookGrid.innerHTML = '';

    const frag = document.createDocumentFragment();

    books.forEach(book => {
      const btn = document.createElement('button');
      btn.className = 'bible-nav-card bible-nav-card-small';
      btn.type = 'button';

      // Create inner content
      const titleDiv = document.createElement('div');
      titleDiv.className = 'bible-nav-card-title';
      titleDiv.textContent = book;
      btn.appendChild(titleDiv);

      const handler = (e) => {
        e.preventDefault();
        e.stopPropagation();
        state.navState.book = book;
        renderChapterChoice();
        showNavStep('chapter');
      };

      btn.addEventListener('click', handler);
      btn.addEventListener('touchend', handler);

      frag.appendChild(btn);
    });

    els.navBookGrid.appendChild(frag);
  }

  function renderChapterChoice() {
    if (!state.navState.book) return;

    const chapterCount = getChapterCount(state.data, state.navState.book);

    if (!els.navChapterTitle || !els.navChapterGrid) return;

    els.navChapterTitle.textContent = `${state.navState.book} - Choose Chapter`;
    els.navChapterGrid.innerHTML = '';

    if (chapterCount === 0) {
      els.navChapterGrid.innerHTML = `<p class="bible-empty-state">No chapters found.</p>`;
      return;
    }

    const frag = document.createDocumentFragment();

    for (let i = 1; i <= chapterCount; i++) {
      const btn = document.createElement('button');
      btn.className = 'bible-nav-card bible-nav-card-small';
      btn.type = 'button';

      const titleDiv = document.createElement('div');
      titleDiv.className = 'bible-nav-card-title';
      titleDiv.textContent = String(i);
      btn.appendChild(titleDiv);

      const chapterNum = i;
      const handler = (e) => {
        e.preventDefault();
        e.stopPropagation();
        state.navState.chapter = chapterNum;
        goToChapter(state.navState.book, chapterNum);
      };

      btn.addEventListener('click', handler);
      btn.addEventListener('touchend', handler);

      frag.appendChild(btn);
    }

    els.navChapterGrid.appendChild(frag);
  }

  function goToChapter(book, chapter) {
    hideQuickNav();
    const ref = makeRef(book, chapter, 1);
    goToReference(ref);
  }

  // ===== SEARCH =====
  function handleSearch() {
    const q = (els.searchInput?.value || '').trim().toLowerCase();
    if (!q || q.length < 2) {
      showToast('Enter at least 2 characters to search');
      return;
    }

    if (!els.searchResults) return;

    els.searchResults.innerHTML = '<div class="bible-loading">Searching...</div>';

    setTimeout(() => {
      const results = [];

      state.books.forEach((book, idx) => {
        const chapterCount = getChapterCount(state.data, book);

        for (let ch = 1; ch <= chapterCount && results.length < 50; ch++) {
          const verses = getChapter(state.data, book, ch);
          let verseNum = 0;

          verses.forEach(v => {
            const parsed = parseVerse(v);
            if (parsed.type === 'verse') {
              verseNum++;
              if (parsed.text.toLowerCase().includes(q)) {
                results.push({
                  book,
                  chapter: ch,
                  verse: verseNum,
                  text: parsed.text
                });
              }
            }
          });
        }
      });

      if (!results.length) {
        els.searchResults.innerHTML = `<p class="bible-empty-state">No results found.</p>`;
        return;
      }

      const frag = document.createDocumentFragment();

      results.forEach(hit => {
        const row = document.createElement('div');
        row.className = 'bible-search-result-item';
        row.innerHTML = `
          <div class="bible-search-result-ref">${esc(hit.book)} ${hit.chapter}:${hit.verse}</div>
          <div class="bible-search-result-text">${esc(hit.text.substring(0, 150))}...</div>
        `;

        row.addEventListener('click', () => {
          const ref = makeRef(hit.book, hit.chapter, hit.verse);
          goToReference(ref);
          hidePanel(els.searchPanel);
        });

        frag.appendChild(row);
      });

      els.searchResults.innerHTML = '';
      els.searchResults.appendChild(frag);
    }, 100);
  }

  // ===== AI COMMENTARY =====
  async function showAIPrompt() {
    if (!state.selectedVerse) {
      showToast('Select a verse first!');
      return;
    }

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const verseRef = `${parsed.book} ${parsed.chapter}:${parsed.verse}`;
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    // Get context verses
    const prevVerses = [];
    const nextVerses = [];
    const currentChapter = getChapter(state.data, parsed.book, parsed.chapter);

    // Get up to 3 verses before and after for context
    for (let i = parsed.verse - 4; i < parsed.verse - 1; i++) {
      if (i > 0 && currentChapter[i]) {
        const p = parseVerse(currentChapter[i]);
        if (p.type === 'verse') prevVerses.push(`v${i+1}: ${p.text}`);
      }
    }
    for (let i = parsed.verse; i < parsed.verse + 3; i++) {
      if (currentChapter[i]) {
        const p = parseVerse(currentChapter[i]);
        if (p.type === 'verse') nextVerses.push(`v${i+1}: ${p.text}`);
      }
    }

    hideContextMenu();

    if (!els.aiPanel || !els.aiOutput) return;

    els.aiOutput.innerHTML = `<div class="bible-loading">AI explaining passage...</div>`;
    showPanel(els.aiPanel);

    try {
      const formData = new FormData();
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse', parsed.verse);
      formData.append('verse_text', verseText);
      formData.append('book_name', parsed.book);
      formData.append('context_before', prevVerses.join('\n'));
      formData.append('context_after', nextVerses.join('\n'));

      const res = await fetch('/bible/api/ai_explain.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      const data = await res.json();

      if (data.ok && data.explanation) {
        const formattedAnswer = data.explanation.replace(/\n/g, '<br>');
        els.aiOutput.innerHTML = `
          <div class="bible-ai-response">
            <div class="bible-ai-verse-ref">${esc(verseRef)}</div>
            <div class="bible-ai-answer">${formattedAnswer}</div>
          </div>
        `;
      } else {
        throw new Error(data.error || 'Unknown error');
      }
    } catch (e) {
      els.aiOutput.innerHTML = `<p class="bible-error">Could not get AI explanation. Please try again.</p>`;
      console.error('AI Commentary error:', e);
    }
  }

  // ===== CROSS REFERENCES =====
  async function loadCrossReferences() {
    if (!state.selectedVerse) return;

    const parsed = parseRef(state.selectedVerse);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    hideContextMenu();

    if (!els.crossRefPanel || !els.crossRefList) return;

    els.crossRefList.innerHTML = '<div class="bible-loading">Loading cross references...</div>';
    showPanel(els.crossRefPanel);

    try {
      const formData = new FormData();
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse', parsed.verse);

      const res = await fetch('/bible/api/cross_references.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
      });

      const data = await res.json();

      if (data.ok && data.cross_references && data.cross_references.length > 0) {
        displayCrossReferences(data.cross_references);
      } else {
        els.crossRefList.innerHTML = `<p class="bible-empty-state">${data.message || 'No cross-references found for this verse.'}</p>`;
      }
    } catch (e) {
      console.error('Cross references error:', e);
      els.crossRefList.innerHTML = `<p class="bible-error">Could not load cross references.</p>`;
    }
  }

  function displayCrossReferences(refs) {
    if (!els.crossRefList) return;

    const frag = document.createDocumentFragment();

    refs.forEach(ref => {
      // Get book name from book number
      const bookName = state.books[ref.book_number - 1] || `Book ${ref.book_number}`;

      const item = document.createElement('div');
      item.className = 'bible-cross-ref-item';
      item.innerHTML = `
        <div class="bible-cross-ref-title">${esc(bookName)} ${ref.chapter}:${ref.verse}</div>
        <div class="bible-cross-ref-text">${esc(ref.text || '')}</div>
      `;

      item.addEventListener('click', () => {
        const verseRef = makeRef(bookName, ref.chapter, ref.verse);
        goToReference(verseRef);
        hidePanel(els.crossRefPanel);
      });

      frag.appendChild(item);
    });

    els.crossRefList.innerHTML = '';
    els.crossRefList.appendChild(frag);
  }

  // ===== READING PLAN =====
  function showReadingPlan() {
    if (!els.readingPlanPanel || !els.readingPlanContent) return;

    const plans = [
      { id: 'year', name: 'Bible in a Year', desc: '365 days' },
      { id: 'nt_month', name: 'NT in a Month', desc: '30 days' },
      { id: 'psalms', name: 'Psalms in a Month', desc: '30 days' }
    ];

    let html = '<div class="bible-plan-options">';

    plans.forEach(plan => {
      html += `
        <button class="bible-plan-option" data-plan="${plan.id}">
          <div class="bible-plan-name">${esc(plan.name)}</div>
          <div class="bible-plan-desc">${esc(plan.desc)}</div>
        </button>
      `;
    });

    html += '</div>';

    els.readingPlanContent.innerHTML = html;

    els.readingPlanContent.querySelectorAll('.bible-plan-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const planId = btn.dataset.plan;
        showToast(`Starting reading plan: ${planId}`);
      });
    });

    showPanel(els.readingPlanPanel);
  }

  // ===== UTILITIES =====
  function copyVerse() {
    if (!state.selectedVerse) return;

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);

    const copyText = `${parsed.book} ${parsed.chapter}:${parsed.verse} - ${verseText} (KJV)`;

    navigator.clipboard.writeText(copyText).then(() => {
      showToast('Verse copied!');
    }).catch(() => {
      showToast('Failed to copy');
    });

    hideContextMenu();
  }

  function shareVerse() {
    if (!state.selectedVerse) return;

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);

    const shareText = `${parsed.book} ${parsed.chapter}:${parsed.verse} - ${verseText} (KJV)`;
    const shareUrl = `${window.location.origin}/bible/?ref=${encodeURIComponent(state.selectedVerse)}`;

    if (navigator.share) {
      navigator.share({
        title: `${parsed.book} ${parsed.chapter}:${parsed.verse}`,
        text: shareText,
        url: shareUrl
      }).catch(() => {});
    } else {
      copyVerse();
    }

    hideContextMenu();
  }

  function changeFontSize(direction) {
    const sizes = ['small', 'medium', 'large', 'xlarge'];
    const currentIndex = sizes.indexOf(state.fontSize);
    let newIndex = currentIndex;

    if (direction === 'increase' && currentIndex < sizes.length - 1) {
      newIndex++;
    } else if (direction === 'decrease' && currentIndex > 0) {
      newIndex--;
    }

    state.fontSize = sizes[newIndex];
    applyFontSize();
    showToast(`Font size: ${state.fontSize}`);
  }

  function applyFontSize() {
    // Remove all font size classes first
    const container = els.leftContent || document.body;
    container.classList.remove('bible-font-small', 'bible-font-medium', 'bible-font-large', 'bible-font-xlarge');
    // Add the current font size class
    container.classList.add(`bible-font-${state.fontSize}`);
  }

  function refreshVerseDisplay() {
    document.querySelectorAll('.bible-verse').forEach(verse => {
      const ref = verse.dataset.ref;

      verse.className = 'bible-verse';
      if (verse.classList.contains('bound')) verse.classList.add('bound');

      if (state.highlights[ref]) {
        verse.classList.add(`bible-highlight-${state.highlights[ref]}`);
      }

      verse.querySelectorAll('.bible-note-indicator, .bible-bookmark-indicator').forEach(n => n.remove());

      if (state.bookmarks[ref]) {
        const bookmarkIcon = document.createElement('span');
        bookmarkIcon.className = 'bible-bookmark-indicator';
        bookmarkIcon.innerHTML = '🔖';
        bookmarkIcon.title = 'Bookmarked';
        verse.appendChild(bookmarkIcon);
      }

      if (state.notes[ref]) {
        const noteIcon = document.createElement('span');
        noteIcon.className = 'bible-note-indicator';
        noteIcon.innerHTML = '📝';
        noteIcon.title = 'Click to view note';
        noteIcon.dataset.ref = ref;
        verse.appendChild(noteIcon);
      }
    });

    bindVerseInteractions();
  }

  // ===== TOAST =====
  function showToast(message) {
    let toast = document.getElementById('bibleToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'bibleToast';
      toast.className = 'bible-toast';
      document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.classList.add('show');

    setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  // ===== DATA PERSISTENCE =====
  async function loadUserData() {
    if (!state.userId) return;

    try {
      const res = await fetch('/bible/api/load_all.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();

      if (data.ok) {
        // Convert database format to state format
        if (data.highlights) {
          data.highlights.forEach(h => {
            const book = state.books[h.book_number - 1];
            if (book) {
              const verse = h.verse || h.verse_start || 1;
              const ref = makeRef(book, h.chapter, verse);
              // Color comes as integer 1-6 from API
              state.highlights[ref] = typeof h.color === 'number' ? h.color : colorNameToNumber(h.color);
            }
          });
        }

        if (data.notes) {
          data.notes.forEach(n => {
            const book = state.books[n.book_number - 1];
            if (book) {
              const verse = n.verse || n.verse_start || 1;
              const ref = makeRef(book, n.chapter, verse);
              state.notes[ref] = n.content;
              // Store note ID for delete operations
              state.noteIds = state.noteIds || {};
              state.noteIds[ref] = n.id;
            }
          });
        }

        if (data.bookmarks) {
          data.bookmarks.forEach(b => {
            const book = state.books[b.book_number - 1];
            if (book) {
              const verse = b.verse || b.verse_start || 1;
              const ref = makeRef(book, b.chapter, verse);
              state.bookmarks[ref] = {
                text: b.notes || b.text || '',
                timestamp: b.created_at ? new Date(b.created_at).getTime() : Date.now()
              };
            }
          });
        }

        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Load error:', e);
    }
  }

  function colorNameToNumber(name) {
    const map = { pink: 1, orange: 2, yellow: 3, green: 4, blue: 5, purple: 6 };
    return map[name] || 3;
  }

  // ===== PANEL MANAGEMENT =====
  function showPanel(panel) {
    if (!panel) return;
    hideAllPanels();
    panel.classList.remove('bible-panel-hidden');
  }

  function hidePanel(panel) {
    if (!panel) return;
    panel.classList.add('bible-panel-hidden');
  }

  function hideAllPanels() {
    const panels = [
      els.searchPanel,
      els.notesPanel,
      els.bookmarksPanel,
      els.aiPanel,
      els.crossRefPanel,
      els.readingPlanPanel
    ];

    panels.forEach(panel => {
      if (panel) panel.classList.add('bible-panel-hidden');
    });
  }

  function togglePanel(panel) {
    if (!panel) return;

    if (panel.classList.contains('bible-panel-hidden')) {
      showPanel(panel);
    } else {
      hidePanel(panel);
    }
  }

  // ===== EVENT BINDINGS =====
  function bindEvents() {
    els.quickNavToggle?.addEventListener('click', showQuickNav);
    els.quickNavClose?.addEventListener('click', hideQuickNav);
    els.quickNavOverlay?.addEventListener('click', hideQuickNav);
    els.navBackToTestament?.addEventListener('click', () => showNavStep('testament'));
    els.navBackToBook?.addEventListener('click', () => showNavStep('book'));
    renderTestamentChoice();

    els.searchToggle?.addEventListener('click', () => togglePanel(els.searchPanel));
    els.notesToggle?.addEventListener('click', () => {
      togglePanel(els.notesPanel);
      renderNotesList();
    });
    els.bookmarksToggle?.addEventListener('click', () => {
      togglePanel(els.bookmarksPanel);
      renderBookmarksList();
    });
    els.readingPlanToggle?.addEventListener('click', showReadingPlan);

    els.searchClose?.addEventListener('click', () => hidePanel(els.searchPanel));
    els.notesClose?.addEventListener('click', () => hidePanel(els.notesPanel));
    els.bookmarksClose?.addEventListener('click', () => hidePanel(els.bookmarksPanel));
    els.aiClose?.addEventListener('click', () => hidePanel(els.aiPanel));
    els.crossRefClose?.addEventListener('click', () => hidePanel(els.crossRefPanel));
    els.readingPlanClose?.addEventListener('click', () => hidePanel(els.readingPlanPanel));

    els.searchBtn?.addEventListener('click', handleSearch);
    els.searchInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') handleSearch();
    });

    els.saveNoteBtn?.addEventListener('click', saveNote);
    els.cancelNoteBtn?.addEventListener('click', cancelNote);

    document.querySelectorAll('.bible-color-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const color = parseInt(btn.dataset.color, 10);
        applyHighlight(color);
      });
    });

    els.ctxBookmark?.addEventListener('click', toggleBookmark);
    els.ctxAddNote?.addEventListener('click', addNoteToVerse);
    els.ctxAI?.addEventListener('click', showAIPrompt);
    els.ctxCrossRef?.addEventListener('click', loadCrossReferences);
    els.ctxCopy?.addEventListener('click', copyVerse);
    els.ctxShare?.addEventListener('click', shareVerse);
    els.ctxClose?.addEventListener('click', hideContextMenu);

    els.fontSizeIncrease?.addEventListener('click', () => changeFontSize('increase'));
    els.fontSizeDecrease?.addEventListener('click', () => changeFontSize('decrease'));

    document.addEventListener('click', (e) => {
      if (els.verseContextMenu && !els.verseContextMenu.contains(e.target) && !e.target.closest('.bible-verse')) {
        hideContextMenu();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        hideAllPanels();
        hideContextMenu();
        hideQuickNav();
      }
    });
  }

  // ===== INITIALIZATION =====
  async function init() {
    debugLog('Init starting...');
    const overlay = createLoadingOverlay();

    try {
      debugLog('Initializing DB...');
      await initDB();
      debugLog('DB ready, IndexedDB: ' + indexedDBAvailable);
      updateLoadingProgress(10, 'Database ready');

      els.quickNavModal?.classList.add('bible-modal-hidden');

      let data = null;
      let loadError = null;

      // Try primary path first (API endpoint)
      try {
        debugLog('Loading from: ' + state.path);
        data = await loadJSON(state.path, (p) => {
          updateLoadingProgress(10 + (p * 0.5), 'Loading Bible...');
        });
        debugLog('API load success, has books: ' + !!(data && data.books));
      } catch (apiError) {
        debugLog('API FAILED: ' + apiError.message);
        loadError = apiError;
      }

      // Fallback to direct JSON file if API fails
      if (!data || !data.books) {
        const fallbackPath = '/bible/bibles/en_kjv.json';
        debugLog('Trying fallback: ' + fallbackPath);
        updateLoadingProgress(60, 'Loading Bible (fallback)...');
        try {
          data = await loadJSON(fallbackPath, (p) => {
            updateLoadingProgress(60 + (p * 0.2), 'Loading Bible...');
          });
          debugLog('Fallback success, has books: ' + !!(data && data.books));
        } catch (fallbackError) {
          debugLog('FALLBACK FAILED: ' + fallbackError.message);
          throw loadError || fallbackError;
        }
      }

      if (!data) {
        throw new Error('Bible data is empty');
      }

      state.data = data;
      state.books = extractBooks(state.data);

      debugLog('Books extracted: ' + state.books.length);

      if (!state.books.length) {
        throw new Error('No books found in Bible data');
      }

      updateLoadingProgress(85, 'Loading user data...');

      try {
        await loadUserData();
        debugLog('User data loaded');
      } catch (userDataError) {
        debugLog('User data failed (ok): ' + userDataError.message);
      }

      updateLoadingProgress(95, 'Building Bible...');

      debugLog('Rendering chapters...');
      renderInitialChapters();
      setupInfiniteScroll();
      debugLog('Render complete');

      updateLoadingProgress(100, 'Ready!');

      bindEvents();
      debugLog('Init complete!');

      setTimeout(() => {
        removeLoadingOverlay();
      }, 500);

    } catch (e) {
      debugLog('INIT ERROR: ' + e.message);
      removeLoadingOverlay();

      if (els.leftContent) {
        els.leftContent.innerHTML = `
          <div class="bible-error-container" style="text-align:center;padding:2rem;color:#ef4444;">
            <h2 style="margin-bottom:1rem;">Could not load Bible</h2>
            <p style="margin-bottom:0.5rem;">${esc(e.message)}</p>
            <p style="color:#888;font-size:0.9rem;">Please check your connection and try refreshing.</p>
            <button onclick="location.reload()" style="margin-top:1rem;padding:0.75rem 1.5rem;background:#8B5CF6;color:white;border:none;border-radius:8px;cursor:pointer;">
              Refresh Page
            </button>
          </div>
        `;
      }
    }
  }

  init();
})();
