/**
 * CRC Diary - AI Enhanced JavaScript
 * Timeline, Gallery, Search views with AI assistance
 */

// ===== GLOBAL STATE =====
const DiaryApp = {
    currentView: 'timeline',
    currentEntry: null,
    entries: [],
    tags: [],
    userId: window.USER_ID || 0,
    csrfToken: window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
};

// ===== TOAST NOTIFICATIONS =====
const Toast = {
    container: null,

    init: function() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show: function(message, type = 'info', duration = 3000) {
        this.init();

        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'toast-slide-in 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    },

    success: function(message, duration) { this.show(message, 'success', duration); },
    error: function(message, duration) { this.show(message, 'error', duration); },
    warning: function(message, duration) { this.show(message, 'warning', duration); },
    info: function(message, duration) { this.show(message, 'info', duration); }
};

// ===== API HELPER =====
const API = {
    async call(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': DiaryApp.csrfToken
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`/diary/api/${endpoint}`, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'API error');
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            Toast.error('Error: ' + error.message);
            throw error;
        }
    },

    async get(endpoint) { return this.call(endpoint, 'GET'); },
    async post(endpoint, data) { return this.call(endpoint, 'POST', data); },
    async put(endpoint, data) { return this.call(endpoint, 'PUT', data); },
    async delete(endpoint) { return this.call(endpoint, 'DELETE'); }
};

// ===== STATS LOADER =====
async function loadStats() {
    try {
        const stats = await API.get('stats.php');
        animateValue('statTotal', 0, stats.total || 0, 1000);
        animateValue('statMonth', 0, stats.month || 0, 1000);
        animateValue('statStreak', 0, stats.streak || 0, 1000);
        animateValue('statWords', 0, stats.words || 0, 1500);
    } catch (error) {
        console.error('Stats load error:', error);
    }
}

function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    if (!element) return;

    const range = end - start;
    if (range === 0) { element.textContent = end; return; }

    const increment = range / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 16);
}

// ===== VIEW SWITCHING =====
function switchView(viewName) {
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-view="${viewName}"]`)?.classList.add('active');

    document.querySelectorAll('.diary-view').forEach(view => view.style.display = 'none');

    const viewMap = { timeline: 'timelineView', gallery: 'galleryView', search: 'searchView' };
    const targetView = document.getElementById(viewMap[viewName]);
    if (targetView) targetView.style.display = 'block';

    DiaryApp.currentView = viewName;

    switch(viewName) {
        case 'timeline': loadTimeline(); break;
        case 'gallery': loadGallery(); break;
    }
}

// ===== TIMELINE =====
async function loadTimeline(filters = {}) {
    const container = document.getElementById('timelineContainer');
    if (!container) return;

    container.innerHTML = `<div class="timeline-loading"><div class="loading-spinner"></div><p>Loading entries...</p></div>`;

    try {
        const params = new URLSearchParams(filters);
        const data = await API.get(`list.php?${params}`);

        if (!data.entries || data.entries.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📔</div>
                    <h3 class="empty-state-title">No entries</h3>
                    <p class="empty-state-text">Start your journey by creating your first entry</p>
                    <div class="empty-state-action">
                        <button class="btn btn-primary" onclick="openEntryModal()">Create Entry</button>
                    </div>
                </div>
            `;
            return;
        }

        DiaryApp.entries = data.entries;

        container.innerHTML = data.entries.map(entry => `
            <div class="timeline-entry" data-id="${entry.id}" onclick="viewEntry(${entry.id})">
                <div class="entry-header">
                    <h3 class="entry-title">${escapeHtml(entry.title || 'Untitled')}</h3>
                    <span class="entry-date">${formatDate(entry.entry_date, entry.entry_time)}</span>
                </div>
                ${entry.mood || entry.weather ? `
                    <div class="entry-meta">
                        ${entry.mood ? `<span class="entry-mood" title="Mood">${getMoodEmoji(entry.mood)}</span>` : ''}
                        ${entry.weather ? `<span class="entry-weather" title="Weather">${getWeatherEmoji(entry.weather)}</span>` : ''}
                    </div>
                ` : ''}
                ${entry.tags && entry.tags.length > 0 ? `
                    <div class="entry-tags">${entry.tags.map(tag => `<span class="entry-tag">#${escapeHtml(tag)}</span>`).join('')}</div>
                ` : ''}
                <div class="entry-body">${escapeHtml(truncateText(entry.content || '', 200))}</div>
                <div class="entry-actions">
                    <button class="entry-action-btn" onclick="event.stopPropagation(); editEntry(${entry.id})">Edit</button>
                    <button class="entry-action-btn" onclick="event.stopPropagation(); shareEntry(${entry.id})">Share</button>
                    <button class="entry-action-btn" onclick="event.stopPropagation(); deleteEntry(${entry.id})">Delete</button>
                </div>
            </div>
        `).join('');

    } catch (error) {
        container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">❌</div><h3 class="empty-state-title">Error</h3><p class="empty-state-text">${error.message}</p></div>`;
    }
}

// ===== GALLERY =====
async function loadGallery() {
    const container = document.getElementById('galleryGrid');
    if (!container) return;

    container.innerHTML = `<div class="gallery-loading"><div class="loading-spinner"></div><p>Loading gallery...</p></div>`;

    try {
        const data = await API.get('list.php?view=gallery');

        if (!data.entries || data.entries.length === 0) {
            container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">🖼️</div><h3 class="empty-state-title">No entries</h3><p class="empty-state-text">Start your journey by creating your first entry</p></div>`;
            return;
        }

        container.innerHTML = data.entries.map(entry => `
            <div class="gallery-item" onclick="viewEntry(${entry.id})">
                <div class="gallery-preview"><p class="gallery-text">${escapeHtml(truncateText(entry.content || '', 150))}</p></div>
                <div class="gallery-info">
                    <h4 class="gallery-title">${escapeHtml(entry.title || 'Untitled')}</h4>
                    <p class="gallery-date">${formatDate(entry.entry_date, entry.entry_time)}</p>
                </div>
            </div>
        `).join('');

    } catch (error) {
        container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">❌</div><h3 class="empty-state-title">Error</h3><p class="empty-state-text">${error.message}</p></div>`;
    }
}

// ===== SEARCH =====
async function performSearch() {
    const query = document.getElementById('searchInput')?.value.trim();
    if (!query) return;

    const searchTitle = document.getElementById('searchTitle')?.checked;
    const searchBody = document.getElementById('searchBody')?.checked;
    const searchTags = document.getElementById('searchTags')?.checked;

    const resultsContainer = document.getElementById('searchResults');
    if (!resultsContainer) return;

    resultsContainer.innerHTML = `<div class="timeline-loading"><div class="loading-spinner"></div><p>Searching...</p></div>`;

    try {
        const data = await API.post('search.php', { query, searchTitle, searchBody, searchTags });

        if (!data.results || data.results.length === 0) {
            resultsContainer.innerHTML = `<div class="search-placeholder"><svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><p>No results found</p></div>`;
            return;
        }

        resultsContainer.innerHTML = data.results.map(result => `
            <div class="search-result" onclick="viewEntry(${result.id})">
                <h4 class="search-result-title">${highlightText(result.title || 'Untitled', query)}</h4>
                <p class="search-result-excerpt">${highlightText(truncateText(result.content || '', 200), query)}</p>
                <div class="search-result-meta">
                    <span>${formatDate(result.entry_date, result.entry_time)}</span>
                    ${result.tags && result.tags.length ? `<span>${result.tags.length} tags</span>` : ''}
                </div>
            </div>
        `).join('');

    } catch (error) {
        resultsContainer.innerHTML = `<div class="search-placeholder"><p>Error: ${error.message}</p></div>`;
    }
}

// ===== ENTRY MODAL =====
function openEntryModal(date = null, entryId = null) {
    const modal = document.getElementById('entryModal');
    if (!modal) return;

    document.getElementById('entryForm')?.reset();
    document.getElementById('entryId').value = entryId || '';

    document.getElementById('entryDate').value = date || new Date().toISOString().split('T')[0];
    document.getElementById('entryTime').value = new Date().toTimeString().slice(0, 5);

    document.getElementById('tagsContainer').innerHTML = '';
    DiaryApp.tags = [];

    document.querySelectorAll('.mood-btn, .weather-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('entryMood').value = '';
    document.getElementById('entryWeather').value = '';

    if (entryId) {
        loadEntryForEdit(entryId);
        document.getElementById('modalTitle').textContent = 'Edit Entry';
    } else {
        document.getElementById('modalTitle').textContent = 'New Entry';
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEntryModal() {
    const modal = document.getElementById('entryModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

async function loadEntryForEdit(entryId) {
    try {
        const data = await API.get(`get.php?id=${entryId}`);
        const entry = data.entry;

        document.getElementById('entryId').value = entry.id;
        document.getElementById('entryDate').value = entry.entry_date;
        document.getElementById('entryTime').value = entry.entry_time || '00:00';
        document.getElementById('entryTitle').value = entry.title || '';
        document.getElementById('entryBody').value = entry.content || '';
        document.getElementById('reminderMinutes').value = entry.reminder_minutes || 60;

        if (entry.mood) {
            document.getElementById('entryMood').value = entry.mood;
            document.querySelector(`[data-mood="${entry.mood}"]`)?.classList.add('active');
        }

        if (entry.weather) {
            document.getElementById('entryWeather').value = entry.weather;
            document.querySelector(`[data-weather="${entry.weather}"]`)?.classList.add('active');
        }

        if (entry.tags) {
            DiaryApp.tags = entry.tags;
            renderTags();
        }

        updateWordCount();

    } catch (error) {
        Toast.error('Could not load entry');
        closeEntryModal();
    }
}

async function saveEntry() {
    const entryId = document.getElementById('entryId').value;
    const date = document.getElementById('entryDate').value;
    const time = document.getElementById('entryTime').value;
    const title = document.getElementById('entryTitle').value;
    const body = document.getElementById('entryBody').value;
    const mood = document.getElementById('entryMood').value;
    const weather = document.getElementById('entryWeather').value;
    const reminderMinutes = document.getElementById('reminderMinutes').value;
    const addToCalendar = document.getElementById('addToCalendar')?.checked ?? true;

    if (!date) { Toast.error('Date is required'); return; }

    const saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    try {
        const data = {
            entry_date: date,
            entry_time: time,
            title,
            content: body,
            mood,
            weather,
            tags: DiaryApp.tags,
            reminder_minutes: parseInt(reminderMinutes),
            add_to_calendar: addToCalendar
        };

        if (entryId) {
            data.id = entryId;
            await API.post('update.php', data);
            Toast.success('Entry updated');
        } else {
            await API.post('create.php', data);
            Toast.success('Entry created');
        }

        closeEntryModal();
        refreshCurrentView();
        loadStats();

    } catch (error) {
        Toast.error('Could not save');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
    }
}

// ===== ENTRY ACTIONS =====
function viewEntry(entryId) { editEntry(entryId); }
function editEntry(entryId) { openEntryModal(null, entryId); }

async function deleteEntry(entryId) {
    if (!confirm('Are you sure you want to delete this entry?')) return;

    try {
        await API.post('delete.php', { id: entryId });
        Toast.success('Entry deleted');
        refreshCurrentView();
        loadStats();
    } catch (error) {
        Toast.error('Could not delete');
    }
}

function shareEntry(entryId) {
    DiaryApp.currentEntry = entryId;
    const modal = document.getElementById('shareModal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeShareModal() {
    const modal = document.getElementById('shareModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

async function shareAs(type) {
    const entryId = DiaryApp.currentEntry;
    if (!entryId) return;

    try {
        switch(type) {
            case 'friend':
                Toast.info('Share with friend coming soon');
                break;
            case 'link':
                const data = await API.post('share.php', { entry_id: entryId, type: 'link' });
                if (data.link) {
                    navigator.clipboard.writeText(data.link);
                    Toast.success('Link copied');
                }
                break;
            case 'export':
                window.open(`/diary/api/export.php?id=${entryId}&format=pdf`, '_blank');
                Toast.success('PDF is being prepared...');
                break;
        }
        closeShareModal();
    } catch (error) {
        Toast.error('Could not share');
    }
}

// ===== TAGS =====
function addTag(tag) {
    tag = tag.trim();
    if (!tag || DiaryApp.tags.includes(tag)) return;
    DiaryApp.tags.push(tag);
    renderTags();
}

function removeTag(tag) {
    DiaryApp.tags = DiaryApp.tags.filter(t => t !== tag);
    renderTags();
}

function renderTags() {
    const container = document.getElementById('tagsContainer');
    if (!container) return;
    container.innerHTML = DiaryApp.tags.map(tag => `
        <span class="tag">#${escapeHtml(tag)}<button type="button" class="tag-remove" onclick="removeTag('${escapeHtml(tag)}')">×</button></span>
    `).join('');
}

// ===== WORD COUNT =====
function updateWordCount() {
    const body = document.getElementById('entryBody')?.value || '';
    const words = body.trim().split(/\s+/).filter(w => w.length > 0).length;
    const counter = document.getElementById('wordCount');
    if (counter) counter.textContent = `${words} words`;
}

// ===== AI ENHANCE =====
async function aiEnhanceEntry() {
    const body = document.getElementById('entryBody')?.value;
    if (!body) { Toast.warning('Write something first...'); return; }

    const btn = document.getElementById('aiAssistBtn');
    btn.disabled = true;
    btn.innerHTML = `<div class="loading-spinner" style="width: 18px; height: 18px; border-width: 2px;"></div> AI Enhancing...`;

    try {
        const data = await API.post('ai_enhance.php', { text: body });
        document.getElementById('entryBody').value = data.enhanced_text;
        updateWordCount();
        Toast.success('Text enhanced!');
    } catch (error) {
        Toast.error('AI could not help');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" fill="none"/></svg> AI Assist`;
    }
}

// ===== UTILITY FUNCTIONS =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function formatDate(date, time) {
    if (!date) return '';
    const d = new Date(date + 'T' + (time || '00:00:00'));
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (d.toDateString() === today.toDateString()) {
        return 'Today ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    } else if (d.toDateString() === yesterday.toDateString()) {
        return 'Yesterday ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    } else {
        return d.toLocaleDateString('en-US') + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
}

function getMoodEmoji(mood) {
    const moods = { happy: '😊', sad: '😢', excited: '🤗', calm: '😌', thoughtful: '🤔', grateful: '🙏', inspired: '✨', tired: '😴', joyful: '😊', peaceful: '😌', hopeful: '🌟', anxious: '😰', angry: '😤', confused: '😕' };
    return moods[mood] || '😊';
}

function getWeatherEmoji(weather) {
    const weathers = { sunny: '☀️', cloudy: '☁️', rainy: '🌧️', stormy: '⛈️', snowy: '❄️', windy: '🌬️' };
    return weathers[weather] || '☀️';
}

function highlightText(text, query) {
    if (!query) return escapeHtml(text);
    const regex = new RegExp(`(${query})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark>$1</mark>');
}

function refreshCurrentView() {
    switch(DiaryApp.currentView) {
        case 'timeline': loadTimeline(); break;
        case 'gallery': loadGallery(); break;
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => { clearTimeout(timeout); func(...args); };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ===== LEGACY SUPPORT FOR EXISTING PAGES =====
// Entry Form (for entry.php)
function initEntryForm() {
    const form = document.getElementById('entry-form');
    if (!form) return;

    const entryId = form.dataset.entryId;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', entryId ? 'update' : 'create');
        if (entryId) formData.append('entry_id', entryId);

        const tagItems = document.querySelectorAll('.tag-item');
        tagItems.forEach(tag => {
            formData.append('tags[]', tag.textContent.trim().replace('×', ''));
        });

        try {
            const response = await fetch('/diary/api/entries.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
            });

            const data = await response.json();

            if (data.ok) {
                window.location.href = '/diary/entry.php?id=' + data.id;
            } else {
                alert(data.error || 'Failed to save entry');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save entry');
        }
    });
}

// Tags Input (for entry.php)
function initTagsInput() {
    const tagInput = document.getElementById('tag-input');
    const tagsList = document.getElementById('tags-list');
    const suggestions = document.getElementById('tag-suggestions');

    if (!tagInput || !tagsList) return;

    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const tagName = this.value.trim();
            if (tagName) {
                addTagLegacy(tagName);
                this.value = '';
            }
        }
    });

    if (suggestions) {
        suggestions.addEventListener('click', function(e) {
            const btn = e.target.closest('.tag-suggestion');
            if (btn) {
                addTagLegacy(btn.dataset.name);
                tagInput.value = '';
            }
        });
    }

    tagsList.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.tag-remove');
        if (removeBtn) {
            removeBtn.closest('.tag-item').remove();
        }
    });
}

function addTagLegacy(name) {
    const tagsList = document.getElementById('tags-list');
    if (!tagsList) return;

    const existing = Array.from(tagsList.querySelectorAll('.tag-item')).find(
        tag => tag.textContent.trim().replace('×', '') === name
    );
    if (existing) return;

    const tagEl = document.createElement('span');
    tagEl.className = 'tag-item';
    tagEl.innerHTML = `${name}<button type="button" class="tag-remove">&times;</button>`;
    tagsList.appendChild(tagEl);
}

// Prayer Modal functions for prayers.php
function openPrayerModal(prayerId = null) {
    const modal = document.getElementById('prayer-modal');
    const form = document.getElementById('prayer-form');
    const title = document.getElementById('modal-title');

    if (prayerId) {
        title.textContent = 'Edit Prayer Request';
        loadPrayer(prayerId);
    } else {
        title.textContent = 'Add Prayer Request';
        form.reset();
        document.getElementById('prayer-id').value = '';
    }

    modal.classList.add('show');
}

function closePrayerModal() {
    document.getElementById('prayer-modal')?.classList.remove('show');
}

async function loadPrayer(prayerId) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', prayerId);

    try {
        const response = await fetch('/diary/api/prayers.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
        });

        const data = await response.json();

        if (data.ok && data.prayer) {
            document.getElementById('prayer-id').value = data.prayer.id;
            document.getElementById('prayer-title').value = data.prayer.title;
            document.getElementById('prayer-request').value = data.prayer.request;
            document.getElementById('prayer-category').value = data.prayer.category || 'personal';
            document.getElementById('prayer-scripture').value = data.prayer.scripture_ref || '';
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function initPrayerForm() {
    const form = document.getElementById('prayer-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const prayerId = document.getElementById('prayer-id').value;
        formData.append('action', prayerId ? 'update' : 'create');

        try {
            const response = await fetch('/diary/api/prayers.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
            });

            const data = await response.json();

            if (data.ok) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to save prayer');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save prayer');
        }
    });
}

function editPrayer(prayerId) { openPrayerModal(prayerId); }

async function deletePrayer(prayerId) {
    if (!confirm('Are you sure you want to delete this prayer request?')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', prayerId);

    try {
        const response = await fetch('/diary/api/prayers.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to delete prayer');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete prayer');
    }
}

async function togglePin(prayerId) {
    const formData = new FormData();
    formData.append('action', 'toggle_pin');
    formData.append('prayer_id', prayerId);

    try {
        const response = await fetch('/diary/api/prayers.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
        });

        const data = await response.json();
        if (data.ok) window.location.reload();
        else alert(data.error || 'Failed to toggle pin');
    } catch (error) {
        console.error('Error:', error);
    }
}

function markAnswered(prayerId) {
    const modal = document.getElementById('answered-modal');
    document.getElementById('answered-prayer-id').value = prayerId;
    document.getElementById('testimony').value = '';
    modal.classList.add('show');
}

function closeAnsweredModal() {
    document.getElementById('answered-modal')?.classList.remove('show');
}

function initAnsweredForm() {
    const form = document.getElementById('answered-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'mark_answered');

        try {
            const response = await fetch('/diary/api/prayers.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': DiaryApp.csrfToken }
            });

            const data = await response.json();
            if (data.ok) window.location.reload();
            else alert(data.error || 'Failed to mark as answered');
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to mark as answered');
        }
    });
}

// ===== EVENT LISTENERS =====
document.addEventListener('DOMContentLoaded', function() {
    // New diary.php view toggle
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });

    // New entry button
    document.getElementById('newEntryBtn')?.addEventListener('click', () => openEntryModal());

    // Modal close
    document.getElementById('modalClose')?.addEventListener('click', closeEntryModal);
    document.getElementById('modalOverlay')?.addEventListener('click', closeEntryModal);
    document.getElementById('cancelBtn')?.addEventListener('click', closeEntryModal);

    // Form submit
    document.getElementById('entryForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        saveEntry();
    });

    // AI assist
    document.getElementById('aiAssistBtn')?.addEventListener('click', aiEnhanceEntry);

    // Word count
    document.getElementById('entryBody')?.addEventListener('input', updateWordCount);

    // Tag input
    document.getElementById('tagInput')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag(e.target.value);
            e.target.value = '';
        }
    });

    // Mood selector
    document.querySelectorAll('.mood-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('entryMood').value = btn.dataset.mood;
        });
    });

    // Weather selector
    document.querySelectorAll('.weather-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.weather-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('entryWeather').value = btn.dataset.weather;
        });
    });

    // Timeline filters
    document.getElementById('timelineSearch')?.addEventListener('input', debounce(() => {
        loadTimeline({ search: document.getElementById('timelineSearch').value });
    }, 500));

    document.getElementById('timelineSort')?.addEventListener('change', (e) => {
        loadTimeline({ sort: e.target.value });
    });

    document.getElementById('timelineFilter')?.addEventListener('change', (e) => {
        loadTimeline({ filter: e.target.value });
    });

    // Search
    document.getElementById('searchBtn')?.addEventListener('click', performSearch);
    document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); performSearch(); }
    });

    // Load initial view if on diary.php
    if (document.getElementById('timelineView')) {
        loadStats();
        loadTimeline();
    }

    // Legacy support for entry.php and prayers.php
    initEntryForm();
    initTagsInput();
    initPrayerForm();
    initAnsweredForm();

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openEntryModal();
        }
        if (e.key === 'Escape') {
            closeEntryModal();
            closeShareModal();
        }
    });
});
