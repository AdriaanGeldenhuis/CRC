/**
 * CRC Bible Reader JavaScript
 */

let selectedVerses = [];
let selectedVerseElement = null;

document.addEventListener('DOMContentLoaded', function() {
    loadVerses();
    initVerseSelection();
    initSearch();

    // Close menus on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.verse-menu') && !e.target.closest('.verse')) {
            hideVerseMenu();
        }
    });
});

// Load verses from API
async function loadVerses() {
    const container = document.getElementById('versesContainer');

    try {
        const formData = new FormData();
        formData.append('book', currentBook);
        formData.append('chapter', currentChapter);
        formData.append('version', currentVersion);

        const response = await fetch('/bible/api/passage.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok && data.verses) {
            renderVerses(data.verses);
        } else {
            container.innerHTML = '<p class="loading">Could not load verses. Please try again.</p>';
        }
    } catch (error) {
        console.error('Error loading verses:', error);
        container.innerHTML = '<p class="loading">Error loading verses.</p>';
    }
}

function renderVerses(verses) {
    const container = document.getElementById('versesContainer');
    container.innerHTML = '';

    verses.forEach(verse => {
        const span = document.createElement('span');
        span.className = 'verse';
        span.dataset.verse = verse.verse_number;

        // Apply highlight if exists
        if (highlights[verse.verse_number]) {
            span.classList.add('highlight-' + highlights[verse.verse_number]);
        }

        span.innerHTML = `<sup class="verse-number">${verse.verse_number}</sup>${verse.text} `;
        container.appendChild(span);
    });

    initVerseSelection();
}

function initVerseSelection() {
    const verses = document.querySelectorAll('.verse');

    verses.forEach(verse => {
        verse.addEventListener('click', function(e) {
            e.stopPropagation();

            // Deselect previous
            if (selectedVerseElement) {
                selectedVerseElement.classList.remove('selected');
            }

            this.classList.add('selected');
            selectedVerseElement = this;
            selectedVerses = [parseInt(this.dataset.verse)];

            showVerseMenu(e);
        });
    });
}

function showVerseMenu(e) {
    const menu = document.getElementById('verseMenu');
    menu.style.display = 'block';

    // Position menu
    let x = e.clientX;
    let y = e.clientY;

    // Adjust if too close to edges
    const rect = menu.getBoundingClientRect();
    if (x + 160 > window.innerWidth) {
        x = window.innerWidth - 170;
    }
    if (y + 200 > window.innerHeight) {
        y = window.innerHeight - 210;
    }

    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
}

function hideVerseMenu() {
    document.getElementById('verseMenu').style.display = 'none';
    if (selectedVerseElement) {
        selectedVerseElement.classList.remove('selected');
        selectedVerseElement = null;
    }
}

// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay') || createOverlay();
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
}

function createOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.onclick = toggleSidebar;
    document.body.appendChild(overlay);
    return overlay;
}

// Navigation
function changeBook(bookName) {
    window.location.href = `?v=${currentVersion}&b=${encodeURIComponent(bookName)}&c=1`;
}

function changeChapter(chapter) {
    window.location.href = `?v=${currentVersion}&b=${encodeURIComponent(currentBook)}&c=${chapter}`;
}

function changeVersion(version) {
    window.location.href = `?v=${version}&b=${encodeURIComponent(currentBook)}&c=${currentChapter}`;
}

// Highlight
async function highlightVerse(color) {
    if (!selectedVerses.length) return;

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('version', currentVersion);
    formData.append('book_number', bookIndex + 1);
    formData.append('chapter', currentChapter);
    formData.append('verse_start', selectedVerses[0]);
    formData.append('verse_end', selectedVerses[selectedVerses.length - 1]);
    formData.append('color', color);

    try {
        const response = await fetch('/bible/api/highlights.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            // Update UI
            selectedVerses.forEach(v => {
                const el = document.querySelector(`.verse[data-verse="${v}"]`);
                if (el) {
                    el.className = `verse highlight-${color}`;
                }
            });
            showToast('Highlight added');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to add highlight');
    }

    hideVerseMenu();
}

// Add Note
function addNote() {
    hideVerseMenu();
    openTools();

    const content = document.getElementById('toolsContentArea');
    content.innerHTML = `
        <div class="note-form">
            <h4>Add Note - ${currentBook} ${currentChapter}:${selectedVerses.join('-')}</h4>
            <textarea id="noteContent" placeholder="Write your note..."></textarea>
            <div class="note-form-actions">
                <button class="btn btn-secondary btn-sm" onclick="closeTools()">Cancel</button>
                <button class="btn btn-primary btn-sm" onclick="saveNote()">Save</button>
            </div>
        </div>
    `;
}

async function saveNote() {
    const noteContent = document.getElementById('noteContent').value.trim();
    if (!noteContent) {
        showToast('Please enter a note');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('version', currentVersion);
    formData.append('book_number', bookIndex + 1);
    formData.append('chapter', currentChapter);
    formData.append('verse_start', selectedVerses[0]);
    formData.append('verse_end', selectedVerses[selectedVerses.length - 1]);
    formData.append('content', noteContent);

    try {
        const response = await fetch('/bible/api/notes.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Note saved');
            closeTools();
        } else {
            showToast(data.error || 'Failed to save note');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to save note');
    }
}

// Add Tag
function addTag() {
    hideVerseMenu();
    showToast('Tags coming soon');
}

// Copy Verse
function copyVerse() {
    if (!selectedVerseElement) return;

    const verseNum = selectedVerseElement.dataset.verse;
    const text = selectedVerseElement.textContent.trim();
    const reference = `${currentBook} ${currentChapter}:${verseNum}`;

    navigator.clipboard.writeText(`"${text}" - ${reference} (${currentVersion})`)
        .then(() => showToast('Copied to clipboard'))
        .catch(() => showToast('Failed to copy'));

    hideVerseMenu();
}

// Share Verse
function shareVerse() {
    if (!selectedVerseElement) return;

    const verseNum = selectedVerseElement.dataset.verse;
    const text = selectedVerseElement.textContent.trim();
    const reference = `${currentBook} ${currentChapter}:${verseNum}`;

    if (navigator.share) {
        navigator.share({
            title: reference,
            text: `"${text}" - ${reference} (${currentVersion})`
        }).catch(() => {});
    } else {
        copyVerse();
    }

    hideVerseMenu();
}

// AI Explain
async function aiExplain() {
    if (!selectedVerseElement) return;

    hideVerseMenu();
    openTools();

    const verseNum = selectedVerseElement.dataset.verse;
    const text = selectedVerseElement.textContent.trim();

    const content = document.getElementById('toolsContentArea');
    content.innerHTML = `
        <div class="ai-explanation">
            <h4>✨ AI Explanation</h4>
            <p><strong>${currentBook} ${currentChapter}:${verseNum}</strong></p>
            <p>"${text}"</p>
            <div class="ai-loading">
                <div class="spinner"></div>
                <span>Getting explanation...</span>
            </div>
        </div>
    `;

    const formData = new FormData();
    formData.append('reference', `${currentBook} ${currentChapter}:${verseNum}`);
    formData.append('text', text);
    formData.append('version', currentVersion);

    try {
        const response = await fetch('/bible/api/ai_explain.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok && data.explanation) {
            content.innerHTML = `
                <div class="ai-explanation">
                    <h4>✨ AI Explanation</h4>
                    <p><strong>${currentBook} ${currentChapter}:${verseNum}</strong></p>
                    <p>"${text}"</p>
                    <hr style="margin: 1rem 0; border: none; border-top: 1px solid rgba(79,70,229,0.2);">
                    <p>${data.explanation}</p>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="ai-explanation">
                    <h4>✨ AI Explanation</h4>
                    <p>Sorry, we couldn't get an explanation right now. Please try again later.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="ai-explanation">
                <h4>✨ AI Explanation</h4>
                <p>An error occurred. Please try again.</p>
            </div>
        `;
    }
}

// Tools Panel
function openTools() {
    document.getElementById('toolsPanel').classList.add('open');
}

function closeTools() {
    document.getElementById('toolsPanel').classList.remove('open');
    document.getElementById('toolsContentArea').innerHTML = '<p class="tools-hint">Select a verse to see options</p>';
}

// Search
function initSearch() {
    // Create search modal if not exists
    if (!document.getElementById('searchModal')) {
        const modal = document.createElement('div');
        modal.id = 'searchModal';
        modal.className = 'search-modal';
        modal.innerHTML = `
            <div class="search-container">
                <div class="search-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Search the Bible...">
                    <button class="search-close" onclick="closeSearch()">×</button>
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>
        `;
        document.body.appendChild(modal);

        // Search on input
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performSearch(this.value), 300);
        });

        // Close on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeSearch();
        });
    }
}

function openSearch() {
    document.getElementById('searchModal').classList.add('show');
    document.getElementById('searchInput').focus();
}

function closeSearch() {
    document.getElementById('searchModal').classList.remove('show');
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
}

async function performSearch(query) {
    if (query.length < 3) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('query', query);
    formData.append('version', currentVersion);

    try {
        const response = await fetch('/bible/api/passage.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok && data.results) {
            renderSearchResults(data.results, query);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function renderSearchResults(results, query) {
    const container = document.getElementById('searchResults');

    if (!results.length) {
        container.innerHTML = '<p class="loading">No results found</p>';
        return;
    }

    container.innerHTML = results.map(r => `
        <a href="?v=${currentVersion}&b=${encodeURIComponent(r.book)}&c=${r.chapter}" class="search-result">
            <div class="search-result-ref">${r.book} ${r.chapter}:${r.verse}</div>
            <div class="search-result-text">${highlightQuery(r.text, query)}</div>
        </a>
    `).join('');
}

function highlightQuery(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Toast notification
function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}
