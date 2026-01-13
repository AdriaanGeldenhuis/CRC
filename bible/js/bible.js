/**
 * CRC Bible Reader JavaScript
 * Based on OAC Bible - English KJV only
 */
(() => {
  'use strict';

  // ===== UTILITIES =====
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[m]);

  // Polyfill for scheduleTask (not supported in Android WebView)
  const scheduleTask = (fn) => setTimeout(fn, 10);

  // ===== INDEXEDDB SETUP =====
  let db = null;
  const DB_NAME = 'CRCBibleDB';
  const DB_VERSION = 2;
  const STORE_NAME = 'bibleData';

  async function initDB() {
    return new Promise((resolve, reject) => {
      try {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onerror = () => resolve(null);
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

  // ===== BIBLE BOOKS =====
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
    currentBookIndex: 0,
    currentChapter: 1,
    earliestBookIndex: 0,
    earliestChapter: 1,
    renderedChapters: new Set(),
    isLoading: false,
    isLoadingPrev: false,
    navState: { testament: null, book: null, chapter: null }
  };

  // ===== ELEMENTS =====
  const els = {
    // Navigation modal
    quickNavModal: $('quickNavModal'),
    navStepTestament: $('navStepTestament'),
    navStepBook: $('navStepBook'),
    navStepChapter: $('navStepChapter'),
    navBookTitle: $('navBookTitle'),
    navChapterTitle: $('navChapterTitle'),
    navBookGrid: $('navBookGrid'),
    navChapterGrid: $('navChapterGrid'),
    // Context menu
    verseContextMenu: $('verseContextMenu'),
    // Panels
    searchPanel: $('searchPanel'),
    searchInput: $('searchInput'),
    searchBtn: $('searchBtn'),
    searchResults: $('searchResults'),
    notesPanel: $('notesPanel'),
    notesList: $('notesList'),
    noteEditor: $('noteEditor'),
    noteReference: $('noteReference'),
    noteText: $('noteText'),
    bookmarksPanel: $('bookmarksPanel'),
    bookmarksList: $('bookmarksList'),
    aiPanel: $('aiPanel'),
    aiOutput: $('aiOutput'),
    crossRefPanel: $('crossRefPanel'),
    crossRefList: $('crossRefList'),
    readingPlanPanel: $('readingPlanPanel'),
    readingPlanContent: $('readingPlanContent'),
    leftContent: $('leftContent'),
    leftColumn: $('leftColumn')
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
      const res = await fetch(url, { credentials: 'same-origin', cache: 'force-cache' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const contentLength = +res.headers.get('Content-Length');
      const reader = res.body.getReader();
      let receivedLength = 0;
      let chunks = [];

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
        receivedLength += value.length;
        if (onProgress && contentLength) {
          onProgress((receivedLength / contentLength) * 100);
        }
      }

      const chunksAll = new Uint8Array(receivedLength);
      let position = 0;
      for (let chunk of chunks) {
        chunksAll.set(chunk, position);
        position += chunk.length;
      }

      const text = new TextDecoder("utf-8").decode(chunksAll);
      const data = JSON.parse(text);
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
    if (Array.isArray(book.chapters)) return book.chapters[chapterNum - 1] || [];
    if (Array.isArray(book.chapter)) return book.chapter[chapterNum - 1] || [];
    const chKey = String(chapterNum);
    return book[chKey] || [];
  }

  function parseVerse(item) {
    if (!item) return { type: 'verse', text: '' };
    if (typeof item === 'string') return { type: 'verse', text: item };
    if (typeof item === 'object') {
      if (item.h !== undefined) return { type: 'heading', text: String(item.h) };
      if (item.v !== undefined) return { type: 'verse', text: String(item.v) };
      if (item.text !== undefined) return { type: 'verse', text: String(item.text) };
      if (item.verse !== undefined) return { type: 'verse', text: String(item.verse) };
      if (item.t !== undefined) return { type: 'verse', text: String(item.t) };
      const vals = Object.values(item);
      if (vals.length > 0) return { type: 'verse', text: String(vals[0]) };
    }
    return { type: 'verse', text: String(item) };
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

    // leftContent is the actual scroll container (leftColumn has overflow:hidden)
    const scrollContainer = els.leftContent;
    if (!scrollContainer) return;

    const containerRect = scrollContainer.getBoundingClientRect();
    const verses = document.querySelectorAll('.bible-verse[data-verse]');
    if (!verses.length) return;

    // Find the TOP verse (first one visible at top of container)
    let topVerse = null;
    let lastVisibleChapter = null;
    let lastVisibleBookIdx = -1;

    for (const v of verses) {
      const rect = v.getBoundingClientRect();
      // Find first verse that's at or below the top of container
      if (rect.top >= containerRect.top - 20 && rect.top < containerRect.bottom) {
        if (!topVerse) {
          topVerse = v;
        }
        // Track the last visible chapter for infinite scroll
        lastVisibleChapter = parseInt(v.dataset.chapter, 10);
        lastVisibleBookIdx = state.books.indexOf(v.dataset.book);
      }
    }

    // Update current position for infinite scroll (track furthest point scrolled)
    if (lastVisibleBookIdx !== -1 && lastVisibleChapter) {
      if (lastVisibleBookIdx > state.currentBookIndex ||
          (lastVisibleBookIdx === state.currentBookIndex && lastVisibleChapter > state.currentChapter)) {
        state.currentBookIndex = lastVisibleBookIdx;
        state.currentChapter = lastVisibleChapter;
      }
    }

    if (topVerse) {
      const book = topVerse.dataset.book;
      const chapter = topVerse.dataset.chapter;
      const verse = topVerse.dataset.verse;

      // Header shows only the TOP verse: "John 8:48"
      headerTitle.textContent = `${book} ${chapter}:${verse}`;

      // Update URL
      const newUrl = new URL(window.location);
      newUrl.searchParams.set('book', book);
      newUrl.searchParams.set('chapter', chapter);
      window.history.replaceState({}, '', newUrl);
    }
  }

  // ===== URL PARAMETER HANDLING =====
  function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
      book: params.get('book'),
      chapter: parseInt(params.get('chapter'), 10) || 1
    };
  }

  function getPreviousChapterInfo(bookIndex, chapter) {
    if (chapter > 1) {
      return { bookIndex, chapter: chapter - 1 };
    }
    if (bookIndex > 0) {
      const prevBook = state.books[bookIndex - 1];
      const prevChapterCount = getChapterCount(state.data, prevBook);
      return { bookIndex: bookIndex - 1, chapter: prevChapterCount };
    }
    return null;
  }

  function getNextChapterInfo(bookIndex, chapter) {
    const book = state.books[bookIndex];
    const chapterCount = getChapterCount(state.data, book);
    if (chapter < chapterCount) {
      return { bookIndex, chapter: chapter + 1 };
    }
    if (bookIndex < state.books.length - 1) {
      return { bookIndex: bookIndex + 1, chapter: 1 };
    }
    return null;
  }

  // ===== RENDERING =====
  function renderInitialChapters() {
    const urlParams = getUrlParams();
    let startBookIndex = 0;
    let startChapter = 1;

    // Check if URL has book parameter
    if (urlParams.book) {
      const bookIdx = state.books.indexOf(urlParams.book);
      if (bookIdx !== -1) {
        startBookIndex = bookIdx;
        startChapter = urlParams.chapter;
        // Validate chapter number
        const maxChapters = getChapterCount(state.data, state.books[startBookIndex]);
        if (startChapter > maxChapters) startChapter = maxChapters;
        if (startChapter < 1) startChapter = 1;
      }
    }

    state.currentBookIndex = startBookIndex;
    state.currentChapter = startChapter;
    state.earliestBookIndex = startBookIndex;
    state.earliestChapter = startChapter;

    els.leftContent.innerHTML = '';

    // Load 2 chapters BEFORE the target chapter
    const chaptersBefore = [];
    let prevInfo = { bookIndex: startBookIndex, chapter: startChapter };
    for (let i = 0; i < 2; i++) {
      prevInfo = getPreviousChapterInfo(prevInfo.bookIndex, prevInfo.chapter);
      if (prevInfo) {
        chaptersBefore.unshift(prevInfo);
      } else {
        break;
      }
    }

    // Render the chapters before (in order)
    chaptersBefore.forEach(info => {
      const book = state.books[info.bookIndex];
      const key = `${book}-${info.chapter}`;
      if (!state.renderedChapters.has(key)) {
        const chapterEl = createChapterElement(book, info.chapter);
        els.leftContent.appendChild(chapterEl);
        state.renderedChapters.add(key);
      }
      state.earliestBookIndex = info.bookIndex;
      state.earliestChapter = info.chapter;
    });

    // Render the target chapter
    const startBook = state.books[startBookIndex];
    const targetKey = `${startBook}-${startChapter}`;
    if (!state.renderedChapters.has(targetKey)) {
      const chapterEl = createChapterElement(startBook, startChapter);
      els.leftContent.appendChild(chapterEl);
      state.renderedChapters.add(targetKey);
    }

    applyFontSize();
    bindVerseInteractions();

    // Scroll to target chapter after a brief delay
    setTimeout(() => {
      const targetChapterEl = document.querySelector(`.bible-chapter-block[data-book="${startBook}"][data-chapter="${startChapter}"]`);
      if (targetChapterEl) {
        targetChapterEl.scrollIntoView({ behavior: 'auto', block: 'start' });
      }
      updateHeaderRef();
    }, 50);

    // Load 4 chapters AFTER the target chapter
    scheduleTask(() => loadNextChapters(4));
  }

  function loadNextChapters(count = 5) {
    if (state.isLoading) return;
    state.isLoading = true;

    let loaded = 0;
    let skipped = 0;
    const maxSkips = 20; // Safety limit

    // Find the last rendered chapter to continue from there
    let bookIdx = state.currentBookIndex;
    let chapter = state.currentChapter;

    const loadChapter = () => {
      // Stop if we've loaded enough or hit end of Bible
      if (loaded >= count || bookIdx >= state.books.length) {
        state.isLoading = false;
        return;
      }

      // Safety: don't loop forever if something's wrong
      if (skipped > maxSkips) {
        state.isLoading = false;
        return;
      }

      // Move to next chapter
      const nextInfo = getNextChapterInfo(bookIdx, chapter);
      if (!nextInfo) {
        state.isLoading = false;
        return;
      }

      bookIdx = nextInfo.bookIndex;
      chapter = nextInfo.chapter;

      const book = state.books[bookIdx];
      const key = `${book}-${chapter}`;

      if (!state.renderedChapters.has(key)) {
        const chapterEl = createChapterElement(book, chapter);
        els.leftContent.appendChild(chapterEl);
        state.renderedChapters.add(key);
        bindVerseInteractions();
        loaded++;
      } else {
        skipped++;
      }

      // Update tracking to furthest point
      state.currentBookIndex = bookIdx;
      state.currentChapter = chapter;

      scheduleTask(loadChapter);
    };

    scheduleTask(loadChapter);
  }

  function loadPreviousChapters(count = 2) {
    if (state.isLoadingPrev) return;
    state.isLoadingPrev = true;

    let loaded = 0;
    let bookIdx = state.earliestBookIndex;
    let chapter = state.earliestChapter;

    const loadChapter = () => {
      if (loaded >= count) {
        state.isLoadingPrev = false;
        return;
      }

      const prevInfo = getPreviousChapterInfo(bookIdx, chapter);
      if (!prevInfo) {
        state.isLoadingPrev = false;
        return;
      }

      bookIdx = prevInfo.bookIndex;
      chapter = prevInfo.chapter;
      const book = state.books[bookIdx];
      const key = `${book}-${chapter}`;

      if (!state.renderedChapters.has(key)) {
        // Save current scroll position (leftContent is the scroll container)
        const scrollContainer = els.leftContent;
        const scrollHeightBefore = scrollContainer.scrollHeight;
        const scrollTopBefore = scrollContainer.scrollTop;

        // Create and prepend chapter
        const chapterEl = createChapterElement(book, chapter);
        els.leftContent.insertBefore(chapterEl, els.leftContent.firstChild);
        state.renderedChapters.add(key);
        bindVerseInteractions();

        // Restore scroll position to prevent jump
        const scrollHeightAfter = scrollContainer.scrollHeight;
        const heightDiff = scrollHeightAfter - scrollHeightBefore;
        scrollContainer.scrollTop = scrollTopBefore + heightDiff;
      }

      state.earliestBookIndex = bookIdx;
      state.earliestChapter = chapter;
      loaded++;

      scheduleTask(loadChapter);
    };

    scheduleTask(loadChapter);
  }

  function createChapterElement(book, chapter) {
    const chapterDiv = document.createElement('div');
    chapterDiv.className = 'bible-chapter-block';
    chapterDiv.dataset.book = book;
    chapterDiv.dataset.chapter = chapter;

    const chTitle = document.createElement('h3');
    chTitle.className = 'bible-chapter-title';
    chTitle.textContent = `${book} ${chapter}`;
    chapterDiv.appendChild(chTitle);

    const verses = getChapter(state.data, book, chapter);
    let verseNum = 0;

    verses.forEach(v => {
      const parsed = parseVerse(v);
      if (parsed.type === 'heading') {
        const h = document.createElement('div');
        h.className = 'bible-heading';
        h.textContent = parsed.text;
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

    if (state.highlights[ref]) {
      vDiv.classList.add(`bible-highlight-${state.highlights[ref]}`);
    }

    const numSpan = document.createElement('span');
    numSpan.className = 'bible-verse-number';
    numSpan.textContent = verseNum;

    const textSpan = document.createElement('span');
    textSpan.className = 'bible-verse-text';
    textSpan.textContent = text;

    vDiv.appendChild(numSpan);
    vDiv.appendChild(textSpan);

    if (state.bookmarks[ref]) {
      const icon = document.createElement('span');
      icon.className = 'bible-bookmark-indicator';
      icon.innerHTML = '🔖';
      vDiv.appendChild(icon);
    }

    if (state.notes[ref]) {
      const icon = document.createElement('span');
      icon.className = 'bible-note-indicator';
      icon.innerHTML = '📝';
      icon.dataset.ref = ref;
      vDiv.appendChild(icon);
    }

    return vDiv;
  }

  // ===== INFINITE SCROLL =====
  function setupInfiniteScroll() {
    let scrollTimeout = null;
    let headerTimeout = null;

    // leftContent is the actual scroll container (leftColumn has overflow:hidden)
    const scrollContainer = els.leftContent;
    if (!scrollContainer) {
      console.error('Scroll container not found!');
      return;
    }

    const handleScroll = () => {
      // Update header immediately (with small debounce)
      clearTimeout(headerTimeout);
      headerTimeout = setTimeout(updateHeaderRef, 30);

      // Load chapters with slightly longer debounce
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const scrollHeight = scrollContainer.scrollHeight;
        const scrollTop = scrollContainer.scrollTop;
        const clientHeight = scrollContainer.clientHeight;
        const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

        // Load more chapters when within 2000px of bottom (more aggressive)
        if (distanceFromBottom < 2000) {
          loadNextChapters(5);
        }

        // Load previous chapters when scrolling near top
        if (scrollTop < 800) {
          loadPreviousChapters(2);
        }
      }, 30);
    };

    scrollContainer.addEventListener('scroll', handleScroll, { passive: true });
  }

  // ===== CONTEXT MENU =====
  function showContextMenu(x, y) {
    const menu = els.verseContextMenu;
    console.log('showContextMenu called', { menu, x, y, menuExists: !!menu });
    if (!menu) {
      console.error('Context menu element not found! els.verseContextMenu is null');
      return;
    }

    menu.classList.remove('bible-context-hidden');
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    console.log('Menu should now be visible at', x, y);

    // Adjust if off-screen
    setTimeout(() => {
      const rect = menu.getBoundingClientRect();
      console.log('Menu rect:', rect);
      if (rect.right > window.innerWidth) {
        menu.style.left = `${window.innerWidth - rect.width - 20}px`;
      }
      if (rect.bottom > window.innerHeight) {
        menu.style.top = `${window.innerHeight - rect.height - 20}px`;
      }
    }, 0);
  }

  function hideContextMenu() {
    els.verseContextMenu?.classList.add('bible-context-hidden');
  }

  // ===== NAVIGATION =====
  function showQuickNav() {
    if (!els.quickNavModal) return;
    els.quickNavModal.style.display = 'flex';
    els.quickNavModal.classList.remove('bible-modal-hidden');
    state.navState = { testament: null, book: null, chapter: null };
    showNavStep('testament');
  }

  function hideQuickNav() {
    if (!els.quickNavModal) return;
    els.quickNavModal.style.display = 'none';
    els.quickNavModal.classList.add('bible-modal-hidden');
  }

  function showNavStep(step) {
    els.navStepTestament?.classList.toggle('bible-nav-hidden', step !== 'testament');
    els.navStepBook?.classList.toggle('bible-nav-hidden', step !== 'book');
    els.navStepChapter?.classList.toggle('bible-nav-hidden', step !== 'chapter');
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
      btn.innerHTML = `<div class="bible-nav-card-title">${esc(book)}</div>`;
      btn.onclick = function() {
        state.navState.book = book;
        renderChapterChoice();
        showNavStep('chapter');
      };
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
      els.navChapterGrid.innerHTML = '<p class="bible-empty-state">No chapters found.</p>';
      return;
    }

    const frag = document.createDocumentFragment();
    for (let i = 1; i <= chapterCount; i++) {
      const btn = document.createElement('button');
      btn.className = 'bible-nav-card bible-nav-card-small';
      btn.innerHTML = `<div class="bible-nav-card-title">${i}</div>`;
      const chapterNum = i;
      btn.onclick = function() {
        state.navState.chapter = chapterNum;
        goToChapter(state.navState.book, chapterNum);
      };
      frag.appendChild(btn);
    }
    els.navChapterGrid.appendChild(frag);
  }

  function goToChapter(book, chapter) {
    hideQuickNav();

    const bookIdx = state.books.indexOf(book);
    if (bookIdx === -1) return;

    // Update URL
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('book', book);
    newUrl.searchParams.set('chapter', chapter);
    window.history.replaceState({}, '', newUrl);

    // Reset state
    state.currentBookIndex = bookIdx;
    state.currentChapter = chapter;
    state.earliestBookIndex = bookIdx;
    state.earliestChapter = chapter;
    state.renderedChapters.clear();
    state.isLoading = false;
    state.isLoadingPrev = false;

    els.leftContent.innerHTML = '';

    // Load 2 chapters BEFORE the target chapter
    const chaptersBefore = [];
    let prevInfo = { bookIndex: bookIdx, chapter: chapter };
    for (let i = 0; i < 2; i++) {
      prevInfo = getPreviousChapterInfo(prevInfo.bookIndex, prevInfo.chapter);
      if (prevInfo) {
        chaptersBefore.unshift(prevInfo);
      } else {
        break;
      }
    }

    // Render the chapters before (in order)
    chaptersBefore.forEach(info => {
      const bookName = state.books[info.bookIndex];
      const key = `${bookName}-${info.chapter}`;
      if (!state.renderedChapters.has(key)) {
        const chapterEl = createChapterElement(bookName, info.chapter);
        els.leftContent.appendChild(chapterEl);
        state.renderedChapters.add(key);
      }
      state.earliestBookIndex = info.bookIndex;
      state.earliestChapter = info.chapter;
    });

    // Render the target chapter
    const targetKey = `${book}-${chapter}`;
    if (!state.renderedChapters.has(targetKey)) {
      const chapterEl = createChapterElement(book, chapter);
      els.leftContent.appendChild(chapterEl);
      state.renderedChapters.add(targetKey);
    }

    applyFontSize();
    bindVerseInteractions();

    // Scroll to target chapter
    setTimeout(() => {
      const targetChapterEl = document.querySelector(`.bible-chapter-block[data-book="${book}"][data-chapter="${chapter}"]`);
      if (targetChapterEl) {
        targetChapterEl.scrollIntoView({ behavior: 'auto', block: 'start' });
      }
      updateHeaderRef();
    }, 50);

    // Load more chapters after
    scheduleTask(() => loadNextChapters(4));
  }

  // ===== VERSE INTERACTIONS =====
  function handleVerseClick(e) {
    e.preventDefault();

    // Click on note indicator opens note editor
    if (e.target.classList.contains('bible-note-indicator')) {
      const ref = e.target.dataset.ref;
      showNoteEditor(ref);
      showPanel(els.notesPanel);
      return;
    }

    // Click on verse shows context menu
    const verse = e.currentTarget;
    document.querySelectorAll('.bible-verse').forEach(v => v.classList.remove('selected'));
    verse.classList.add('selected');
    state.selectedVerse = verse.dataset.ref;
    showContextMenu(e.clientX, e.clientY);
  }

  function bindVerseInteractions() {
    document.querySelectorAll('.bible-verse:not(.bound)').forEach(verse => {
      verse.classList.add('bound');
      verse.addEventListener('click', handleVerseClick);
      verse.addEventListener('contextmenu', handleVerseClick);
    });
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
      formData.append('color', color);

      const res = await fetch('/bible/api/highlights.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content }
      });

      const data = await res.json();
      if (data.ok) {
        if (color === 0) {
          delete state.highlights[state.selectedVerse];
        } else {
          state.highlights[state.selectedVerse] = color;
        }
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Highlight save failed:', e);
    }
    hideContextMenu();
  }

  // ===== BOOKMARKS =====
  async function toggleBookmark() {
    if (!state.selectedVerse) return;

    const verseEl = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verseEl?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

    try {
      const formData = new FormData();
      formData.append('action', 'toggle');
      formData.append('book_number', bookIndex);
      formData.append('chapter', parsed.chapter);
      formData.append('verse', parsed.verse);

      const res = await fetch('/bible/api/bookmarks.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content }
      });

      const data = await res.json();
      if (data.ok) {
        if (data.bookmarked === false) {
          delete state.bookmarks[state.selectedVerse];
        } else {
          state.bookmarks[state.selectedVerse] = { text: verseText, timestamp: Date.now() };
        }
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Bookmark toggle failed:', e);
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
    if (bookIdx === -1) return;

    state.currentBookIndex = bookIdx;
    state.currentChapter = parsed.chapter;
    const key = `${parsed.book}-${parsed.chapter}`;

    // Update URL without reloading
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('book', parsed.book);
    newUrl.searchParams.set('chapter', parsed.chapter);
    window.history.replaceState({}, '', newUrl);

    if (!state.renderedChapters.has(key)) {
      const chapterEl = createChapterElement(parsed.book, parsed.chapter);
      els.leftContent.appendChild(chapterEl);
      state.renderedChapters.add(key);
      bindVerseInteractions();

      // Load a couple chapters after if needed
      scheduleTask(() => loadNextChapters(2));
    }

    setTimeout(() => {
      const verseEl = document.querySelector(`[data-ref="${ref}"]`);
      if (verseEl) {
        verseEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        verseEl.classList.add('bible-verse-flash');
        setTimeout(() => verseEl.classList.remove('bible-verse-flash'), 2000);
        updateHeaderRef();
      }
    }, 100);
  }

  // ===== NOTES =====
  function renderNotesList() {
    if (!els.notesList) return;
    const refs = Object.keys(state.notes);

    if (!refs.length) {
      els.notesList.innerHTML = `<p class="bible-empty-state">No notes yet.</p>`;
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
      `;
      noteItem.addEventListener('click', () => goToReference(ref));
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
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content }
      });

      const data = await res.json();
      if (data.ok) {
        if (text) {
          state.notes[state.selectedVerse] = text;
        } else {
          delete state.notes[state.selectedVerse];
        }
        els.noteEditor?.classList.add('bible-note-hidden');
        els.notesList?.classList.remove('bible-note-hidden');
        renderNotesList();
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Note save failed:', e);
    }
  }

  function cancelNote() {
    els.noteEditor?.classList.add('bible-note-hidden');
    els.notesList?.classList.remove('bible-note-hidden');
  }

  function addNoteToVerse() {
    if (!state.selectedVerse) return;
    hideContextMenu();
    showPanel(els.notesPanel);
    showNoteEditor(state.selectedVerse);
  }

  // ===== SEARCH =====
  function handleSearch() {
    const q = (els.searchInput?.value || '').trim().toLowerCase();
    if (!q || q.length < 2) return;
    if (!els.searchResults) return;

    els.searchResults.innerHTML = '<div class="bible-loading">Searching...</div>';

    setTimeout(() => {
      const results = [];
      state.books.forEach(book => {
        const chapterCount = getChapterCount(state.data, book);
        for (let ch = 1; ch <= chapterCount && results.length < 50; ch++) {
          const verses = getChapter(state.data, book, ch);
          let verseNum = 0;
          verses.forEach(v => {
            const parsed = parseVerse(v);
            if (parsed.type === 'verse') {
              verseNum++;
              if (parsed.text.toLowerCase().includes(q)) {
                results.push({ book, chapter: ch, verse: verseNum, text: parsed.text });
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

  // ===== AI & CROSS REFS =====
  async function showAIPrompt() {
    if (!state.selectedVerse) return;
    hideContextMenu();

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const verseRef = `${parsed.book} ${parsed.chapter}:${parsed.verse}`;
    const bookIndex = state.books.indexOf(parsed.book) + 1;

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

      const res = await fetch('/bible/api/ai_explain.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content }
      });

      const data = await res.json();
      if (data.ok && data.explanation) {
        els.aiOutput.innerHTML = `
          <div class="bible-ai-response">
            <div class="bible-ai-verse-ref">${esc(verseRef)}</div>
            <div class="bible-ai-answer">${data.explanation.replace(/\n/g, '<br>')}</div>
          </div>
        `;
      } else {
        throw new Error(data.error || 'Unknown error');
      }
    } catch (e) {
      els.aiOutput.innerHTML = `<p class="bible-error">Could not get AI explanation.</p>`;
    }
  }

  async function loadCrossReferences() {
    if (!state.selectedVerse) return;
    hideContextMenu();
    const parsed = parseRef(state.selectedVerse);
    const bookIndex = state.books.indexOf(parsed.book) + 1;

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
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content }
      });

      const data = await res.json();
      if (data.ok && data.cross_references?.length) {
        const frag = document.createDocumentFragment();
        data.cross_references.forEach(ref => {
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
      } else {
        els.crossRefList.innerHTML = `<p class="bible-empty-state">No cross-references found.</p>`;
      }
    } catch (e) {
      els.crossRefList.innerHTML = `<p class="bible-error">Could not load cross references.</p>`;
    }
  }

  function showReadingPlan() {
    if (!els.readingPlanPanel || !els.readingPlanContent) return;
    els.readingPlanContent.innerHTML = `
      <div class="bible-plan-options">
        <button class="bible-plan-option"><div class="bible-plan-name">Bible in a Year</div><div class="bible-plan-desc">365 days</div></button>
        <button class="bible-plan-option"><div class="bible-plan-name">NT in a Month</div><div class="bible-plan-desc">30 days</div></button>
        <button class="bible-plan-option"><div class="bible-plan-name">Psalms in a Month</div><div class="bible-plan-desc">30 days</div></button>
      </div>
    `;
    showPanel(els.readingPlanPanel);
  }

  // ===== UTILITIES =====
  function copyVerse() {
    if (!state.selectedVerse) return;
    hideContextMenu();
    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const copyText = `${parsed.book} ${parsed.chapter}:${parsed.verse} - ${verseText} (KJV)`;

    navigator.clipboard.writeText(copyText).then(() => {
      alert('Verse copied!');
    }).catch(() => {
      alert('Failed to copy');
    });
  }

  function shareVerse() {
    if (!state.selectedVerse) return;
    hideContextMenu();
    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const shareText = `${parsed.book} ${parsed.chapter}:${parsed.verse} - ${verseText} (KJV)`;

    if (navigator.share) {
      navigator.share({ title: `${parsed.book} ${parsed.chapter}:${parsed.verse}`, text: shareText }).catch(() => {});
    } else {
      copyVerse();
    }
  }

  function changeFontSize(direction) {
    const sizes = ['small', 'medium', 'large', 'xlarge'];
    const currentIndex = sizes.indexOf(state.fontSize);
    let newIndex = currentIndex;
    if (direction === 'increase' && currentIndex < sizes.length - 1) newIndex++;
    else if (direction === 'decrease' && currentIndex > 0) newIndex--;
    state.fontSize = sizes[newIndex];
    applyFontSize();
  }

  function applyFontSize() {
    const sizeMap = { small: '0.9rem', medium: '1.05rem', large: '1.2rem', xlarge: '1.35rem' };
    document.querySelectorAll('.bible-verse-text').forEach(el => {
      el.style.fontSize = sizeMap[state.fontSize];
    });
  }

  function refreshVerseDisplay() {
    document.querySelectorAll('.bible-verse').forEach(verse => {
      const ref = verse.dataset.ref;
      verse.className = 'bible-verse';
      if (verse.classList.contains('bound')) verse.classList.add('bound');
      if (state.highlights[ref]) verse.classList.add(`bible-highlight-${state.highlights[ref]}`);

      verse.querySelectorAll('.bible-note-indicator, .bible-bookmark-indicator').forEach(n => n.remove());

      if (state.bookmarks[ref]) {
        const icon = document.createElement('span');
        icon.className = 'bible-bookmark-indicator';
        icon.innerHTML = '🔖';
        verse.appendChild(icon);
      }

      if (state.notes[ref]) {
        const icon = document.createElement('span');
        icon.className = 'bible-note-indicator';
        icon.innerHTML = '📝';
        icon.dataset.ref = ref;
        verse.appendChild(icon);
      }
    });
    bindVerseInteractions();
  }

  // ===== USER DATA =====
  async function loadUserData() {
    if (!state.userId) return;
    try {
      const res = await fetch('/bible/api/load_all.php', { method: 'GET', credentials: 'same-origin' });
      const data = await res.json();

      if (data.ok) {
        if (data.highlights) {
          data.highlights.forEach(h => {
            const book = state.books[h.book_number - 1];
            if (book) {
              const verse = h.verse || h.verse_start || 1;
              const ref = makeRef(book, h.chapter, verse);
              state.highlights[ref] = h.color;
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
            }
          });
        }
        if (data.bookmarks) {
          data.bookmarks.forEach(b => {
            const book = state.books[b.book_number - 1];
            if (book) {
              const verse = b.verse || b.verse_start || 1;
              const ref = makeRef(book, b.chapter, verse);
              state.bookmarks[ref] = { text: b.notes || '', timestamp: Date.now() };
            }
          });
        }
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Load error:', e);
    }
  }

  // ===== PANEL MANAGEMENT =====
  function showPanel(panel) {
    if (!panel) return;
    hideAllPanels();
    panel.style.display = 'flex';
    panel.classList.remove('bible-panel-hidden');
  }

  function hidePanel(panel) {
    if (!panel) return;
    panel.style.display = 'none';
    panel.classList.add('bible-panel-hidden');
  }

  function hideAllPanels() {
    [els.searchPanel, els.notesPanel, els.bookmarksPanel, els.aiPanel, els.crossRefPanel, els.readingPlanPanel]
      .forEach(panel => {
        if (panel) {
          panel.style.display = 'none';
          panel.classList.add('bible-panel-hidden');
        }
      });
  }

  function togglePanel(panel) {
    if (!panel) return;
    const isHidden = panel.style.display === 'none' || panel.classList.contains('bible-panel-hidden');
    if (isHidden) {
      showPanel(panel);
    } else {
      hidePanel(panel);
    }
  }

  // ===== EVENT BINDINGS =====
  function bindEvents() {
    els.searchBtn?.addEventListener('click', handleSearch);
    els.searchInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSearch(); });

    // Close context menu when clicking outside
    document.addEventListener('click', (e) => {
      if (els.verseContextMenu && !els.verseContextMenu.contains(e.target) && !e.target.closest('.bible-verse')) {
        hideContextMenu();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { hideAllPanels(); hideContextMenu(); hideQuickNav(); }
    });
  }

  // ===== INITIALIZATION =====
  async function init() {
    const overlay = createLoadingOverlay();

    try {
      await initDB();
      updateLoadingProgress(10, 'Database ready');

      const data = await loadJSON(state.path, (p) => updateLoadingProgress(10 + (p * 0.6), 'Loading Bible...'));

      if (!data) throw new Error('Bible data is empty');

      state.data = data;
      state.books = extractBooks(state.data);

      if (!state.books.length) throw new Error('No books found in Bible data');

      updateLoadingProgress(80, 'Loading user data...');
      await loadUserData();

      updateLoadingProgress(95, 'Building Bible...');
      renderInitialChapters();
      setupInfiniteScroll();

      updateLoadingProgress(100, 'Ready!');
      bindEvents();

      setTimeout(() => removeLoadingOverlay(), 500);

    } catch (e) {
      console.error('Initialization failed:', e);
      removeLoadingOverlay();
      if (els.leftContent) {
        els.leftContent.innerHTML = `
          <div style="text-align:center;padding:2rem;color:#ef4444;">
            <h2>Could not load Bible</h2>
            <p>${esc(e.message)}</p>
            <button onclick="location.reload()" style="margin-top:1rem;padding:0.75rem 1.5rem;background:#8B5CF6;color:white;border:none;border-radius:8px;cursor:pointer;">Refresh</button>
          </div>
        `;
      }
    }
  }

  // ===== GLOBAL API FOR INLINE ONCLICK HANDLERS =====
  window.BibleApp = {
    // Navigation
    showNav: function() {
      if (els.quickNavModal) {
        els.quickNavModal.style.display = 'flex';
        els.quickNavModal.classList.remove('bible-modal-hidden');
      }
      state.navState = { testament: null, book: null, chapter: null };
      showNavStep('testament');
    },
    hideNav: function() {
      if (els.quickNavModal) {
        els.quickNavModal.style.display = 'none';
        els.quickNavModal.classList.add('bible-modal-hidden');
      }
    },
    selectTestament: function(t) {
      state.navState.testament = t;
      renderBookChoice();
      showNavStep('book');
    },
    showStep: function(step) {
      showNavStep(step);
    },

    // Context Menu
    showMenu: showContextMenu,
    hideMenu: hideContextMenu,
    highlight: applyHighlight,
    bookmark: toggleBookmark,
    addNote: addNoteToVerse,
    askAI: showAIPrompt,
    crossRef: loadCrossReferences,
    copy: copyVerse,
    share: shareVerse,

    // Panels
    toggleSearch: function() {
      togglePanel(els.searchPanel);
    },
    toggleNotes: function() {
      togglePanel(els.notesPanel);
      renderNotesList();
    },
    toggleBookmarks: function() {
      togglePanel(els.bookmarksPanel);
      renderBookmarksList();
    },
    showReadingPlan: showReadingPlan,
    closePanel: function(name) {
      const panels = {
        search: els.searchPanel,
        notes: els.notesPanel,
        bookmarks: els.bookmarksPanel,
        ai: els.aiPanel,
        crossRef: els.crossRefPanel,
        readingPlan: els.readingPlanPanel
      };
      const panel = panels[name];
      if (panel) {
        panel.style.display = 'none';
        panel.classList.add('bible-panel-hidden');
      }
    },

    // Notes
    saveNote: saveNote,
    cancelNote: cancelNote,

    // Font size
    fontIncrease: function() { changeFontSize('increase'); },
    fontDecrease: function() { changeFontSize('decrease'); }
  };

  init();
})();
