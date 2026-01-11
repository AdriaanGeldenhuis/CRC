// =====================================================================
// /diary/js/diary.js — AI Diary JavaScript
// =====================================================================

// ===== GLOBAL STATE =====
const DiaryApp = {
  currentView: 'timeline',
  currentEntry: null,
  entries: [],
  calendar: null,
  tags: [],
  lang: window.DIARY_LANG || 'af',
  userId: window.USER_ID || 0,
  
  // Translation function
  t: function(af, en) {
    return this.lang === 'en' ? en : af;
  }
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
  
  success: function(message, duration) {
    this.show(message, 'success', duration);
  },
  
  error: function(message, duration) {
    this.show(message, 'error', duration);
  },
  
  warning: function(message, duration) {
    this.show(message, 'warning', duration);
  },
  
  info: function(message, duration) {
    this.show(message, 'info', duration);
  }
};

// ===== API HELPER =====
const API = {
  async call(endpoint, method = 'GET', data = null) {
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json'
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
      Toast.error(DiaryApp.t('Fout: ', 'Error: ') + error.message);
      throw error;
    }
  },
  
  async get(endpoint) {
    return this.call(endpoint, 'GET');
  },
  
  async post(endpoint, data) {
    return this.call(endpoint, 'POST', data);
  },
  
  async put(endpoint, data) {
    return this.call(endpoint, 'PUT', data);
  },
  
  async delete(endpoint) {
    return this.call(endpoint, 'DELETE');
  }
};

// ===== STATS LOADER =====
async function loadStats() {
  try {
    const stats = await API.get('stats.php');
    
    document.getElementById('statTotal').textContent = stats.total || 0;
    document.getElementById('statMonth').textContent = stats.month || 0;
    document.getElementById('statStreak').textContent = stats.streak || 0;
    document.getElementById('statWords').textContent = stats.words || 0;
    
    // Animate numbers
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
  // Update buttons
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  document.querySelector(`[data-view="${viewName}"]`)?.classList.add('active');
  
  // Hide all views
  document.querySelectorAll('.diary-view').forEach(view => {
    view.style.display = 'none';
  });
  
  // Show selected view
  const viewMap = {
    timeline: 'timelineView',
    calendar: 'calendarView',
    gallery: 'galleryView',
    search: 'searchView'
  };
  
  const targetView = document.getElementById(viewMap[viewName]);
  if (targetView) {
    targetView.style.display = 'block';
  }
  
  DiaryApp.currentView = viewName;
  
  // Load data for view
  switch(viewName) {
    case 'timeline':
      loadTimeline();
      break;
    case 'calendar':
      loadCalendar();
      break;
    case 'gallery':
      loadGallery();
      break;
    case 'search':
      // Search is user-initiated
      break;
  }
}

// ===== TIMELINE =====
async function loadTimeline(filters = {}) {
  const container = document.getElementById('timelineContainer');
  if (!container) return;
  
  container.innerHTML = `
    <div class="timeline-loading">
      <div class="loading-spinner"></div>
      <p>${DiaryApp.t('Laai inskrywings...', 'Loading entries...')}</p>
    </div>
  `;
  
  try {
    const params = new URLSearchParams(filters);
    const data = await API.get(`list.php?${params}`);
    
    if (!data.entries || data.entries.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">📔</div>
          <h3 class="empty-state-title">${DiaryApp.t('Geen inskrywings', 'No entries')}</h3>
          <p class="empty-state-text">${DiaryApp.t('Begin jou reis deur jou eerste inskrywing te skep', 'Start your journey by creating your first entry')}</p>
          <div class="empty-state-action">
            <button class="btn btn-primary" onclick="openEntryModal()">${DiaryApp.t('Skep Inskrywing', 'Create Entry')}</button>
          </div>
        </div>
      `;
      return;
    }
    
    DiaryApp.entries = data.entries;
    
    container.innerHTML = data.entries.map(entry => `
      <div class="timeline-entry" data-id="${entry.id}" onclick="viewEntry(${entry.id})">
        <div class="entry-header">
          <h3 class="entry-title">${escapeHtml(entry.title || DiaryApp.t('Geen titel', 'No title'))}</h3>
          <span class="entry-date">${formatDate(entry.date, entry.time)}</span>
        </div>
        ${entry.mood || entry.weather ? `
          <div class="entry-meta">
            ${entry.mood ? `<span class="entry-mood" title="${DiaryApp.t('Gemoed', 'Mood')}">${getMoodEmoji(entry.mood)}</span>` : ''}
            ${entry.weather ? `<span class="entry-weather" title="${DiaryApp.t('Weer', 'Weather')}">${getWeatherEmoji(entry.weather)}</span>` : ''}
          </div>
        ` : ''}
        ${entry.tags && entry.tags.length > 0 ? `
          <div class="entry-tags">
            ${entry.tags.map(tag => `<span class="entry-tag">#${escapeHtml(tag)}</span>`).join('')}
          </div>
        ` : ''}
        <div class="entry-body">
          ${escapeHtml(truncateText(entry.body || '', 200))}
        </div>
        <div class="entry-actions">
          <button class="entry-action-btn" onclick="event.stopPropagation(); editEntry(${entry.id})">${DiaryApp.t('Redigeer', 'Edit')}</button>
          <button class="entry-action-btn" onclick="event.stopPropagation(); shareEntry(${entry.id})">${DiaryApp.t('Deel', 'Share')}</button>
          <button class="entry-action-btn" onclick="event.stopPropagation(); deleteEntry(${entry.id})">${DiaryApp.t('Vee uit', 'Delete')}</button>
        </div>
      </div>
    `).join('');
    
  } catch (error) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">❌</div>
        <h3 class="empty-state-title">${DiaryApp.t('Fout', 'Error')}</h3>
        <p class="empty-state-text">${error.message}</p>
      </div>
    `;
  }
}


// ===== GALLERY =====
async function loadGallery() {
  const container = document.getElementById('galleryGrid');
  if (!container) return;
  
  container.innerHTML = `
    <div class="gallery-loading">
      <div class="loading-spinner"></div>
      <p>${DiaryApp.t('Laai galery...', 'Loading gallery...')}</p>
    </div>
  `;
  
  try {
    const data = await API.get('list.php?view=gallery');
    
    if (!data.entries || data.entries.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">🖼️</div>
          <h3 class="empty-state-title">${DiaryApp.t('Geen inskrywings', 'No entries')}</h3>
          <p class="empty-state-text">${DiaryApp.t('Begin jou reis deur jou eerste inskrywing te skep', 'Start your journey by creating your first entry')}</p>
        </div>
      `;
      return;
    }
    
    container.innerHTML = data.entries.map(entry => `
      <div class="gallery-item" onclick="viewEntry(${entry.id})">
        <div class="gallery-preview">
          <p class="gallery-text">${escapeHtml(truncateText(entry.body || '', 150))}</p>
        </div>
        <div class="gallery-info">
          <h4 class="gallery-title">${escapeHtml(entry.title || DiaryApp.t('Geen titel', 'No title'))}</h4>
          <p class="gallery-date">${formatDate(entry.date, entry.time)}</p>
        </div>
      </div>
    `).join('');
    
  } catch (error) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">❌</div>
        <h3 class="empty-state-title">${DiaryApp.t('Fout', 'Error')}</h3>
        <p class="empty-state-text">${error.message}</p>
      </div>
    `;
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
  
  resultsContainer.innerHTML = `
    <div class="timeline-loading">
      <div class="loading-spinner"></div>
      <p>${DiaryApp.t('Soek...', 'Searching...')}</p>
    </div>
  `;
  
  try {
    const data = await API.post('search.php', {
      query,
      searchTitle,
      searchBody,
      searchTags
    });
    
    if (!data.results || data.results.length === 0) {
      resultsContainer.innerHTML = `
        <div class="search-placeholder">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <p>${DiaryApp.t('Geen resultate gevind', 'No results found')}</p>
        </div>
      `;
      return;
    }
    
    resultsContainer.innerHTML = data.results.map(result => `
      <div class="search-result" onclick="viewEntry(${result.id})">
        <h4 class="search-result-title">${highlightText(result.title || DiaryApp.t('Geen titel', 'No title'), query)}</h4>
        <p class="search-result-excerpt">${highlightText(truncateText(result.body || '', 200), query)}</p>
        <div class="search-result-meta">
          <span>${formatDate(result.date, result.time)}</span>
          ${result.tags ? `<span>${result.tags.length} ${DiaryApp.t('etikette', 'tags')}</span>` : ''}
        </div>
      </div>
    `).join('');
    
  } catch (error) {
    resultsContainer.innerHTML = `
      <div class="search-placeholder">
        <p>${DiaryApp.t('Fout: ', 'Error: ')}${error.message}</p>
      </div>
    `;
  }
}

// ===== ENTRY MODAL =====
function openEntryModal(date = null, entryId = null) {
  const modal = document.getElementById('entryModal');
  if (!modal) return;
  
  // Reset form
  document.getElementById('entryForm').reset();
  document.getElementById('entryId').value = entryId || '';
  
  if (date) {
    document.getElementById('entryDate').value = date;
  } else {
    document.getElementById('entryDate').value = new Date().toISOString().split('T')[0];
  }
  
  document.getElementById('entryTime').value = new Date().toTimeString().slice(0, 5);
  
  // Clear tags
  document.getElementById('tagsContainer').innerHTML = '';
  DiaryApp.tags = [];
  
  // Reset mood and weather
  document.querySelectorAll('.mood-btn, .weather-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Load entry if editing
  if (entryId) {
    loadEntryForEdit(entryId);
    document.getElementById('modalTitle').textContent = DiaryApp.t('Redigeer Inskrywing', 'Edit Entry');
  } else {
    document.getElementById('modalTitle').textContent = DiaryApp.t('Nuwe Inskrywing', 'New Entry');
  }
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeEntryModal() {
  const modal = document.getElementById('entryModal');
  if (!modal) return;
  
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

async function loadEntryForEdit(entryId) {
  try {
    const data = await API.get(`get.php?id=${entryId}`);
    const entry = data.entry;
    
    document.getElementById('entryId').value = entry.id;
    document.getElementById('entryDate').value = entry.date;
    document.getElementById('entryTime').value = entry.time || '00:00';
    document.getElementById('entryTitle').value = entry.title || '';
    document.getElementById('entryBody').value = entry.body || '';
    document.getElementById('reminderMinutes').value = entry.reminder_minutes || 60;
    
    // Set mood
    if (entry.mood) {
      document.getElementById('entryMood').value = entry.mood;
      document.querySelector(`[data-mood="${entry.mood}"]`)?.classList.add('active');
    }
    
    // Set weather
    if (entry.weather) {
      document.getElementById('entryWeather').value = entry.weather;
      document.querySelector(`[data-weather="${entry.weather}"]`)?.classList.add('active');
    }
    
    // Set tags
    if (entry.tags) {
      DiaryApp.tags = entry.tags;
      renderTags();
    }
    
    updateWordCount();
    
  } catch (error) {
    Toast.error(DiaryApp.t('Kon nie inskrywing laai nie', 'Could not load entry'));
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
  const addToCalendar = document.getElementById('addToCalendar')?.checked ?? true; // NEW
  
  if (!date) {
    Toast.error(DiaryApp.t('Datum is verplig', 'Date is required'));
    return;
  }
  
  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = DiaryApp.t('Stoor...', 'Saving...');
  
  try {
    const data = {
      date,
      time,
      title,
      body,
      mood,
      weather,
      tags: DiaryApp.tags,
      reminder_minutes: parseInt(reminderMinutes),
      add_to_calendar: addToCalendar,  // NEW
      sync_to_calendar: addToCalendar  // NEW (for updates)
    };
    
    if (entryId) {
      await API.put(`update.php?id=${entryId}`, data);
      Toast.success(DiaryApp.t('Inskrywing opgedateer', 'Entry updated'));
    } else {
      await API.post('create.php', data);
      Toast.success(DiaryApp.t('Inskrywing geskep', 'Entry created'));
    }
    
    closeEntryModal();
    refreshCurrentView();
    loadStats();
    
  } catch (error) {
    Toast.error(DiaryApp.t('Kon nie stoor nie', 'Could not save'));
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = DiaryApp.t('Stoor', 'Save');
  }
}

// ===== ENTRY ACTIONS =====
async function viewEntry(entryId) {
  editEntry(entryId);
}

async function editEntry(entryId) {
  openEntryModal(null, entryId);
}

async function deleteEntry(entryId) {
  if (!confirm(DiaryApp.t('Is jy seker jy wil hierdie inskrywing uitvee?', 'Are you sure you want to delete this entry?'))) {
    return;
  }
  
  try {
    await API.delete(`delete.php?id=${entryId}`);
    Toast.success(DiaryApp.t('Inskrywing uitgevee', 'Entry deleted'));
    refreshCurrentView();
    loadStats();
  } catch (error) {
    Toast.error(DiaryApp.t('Kon nie uitvee nie', 'Could not delete'));
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
        // TODO: Implement friend sharing
        Toast.info(DiaryApp.t('Deel met vriend binnekort beskikbaar', 'Share with friend coming soon'));
        break;
      case 'link':
        const data = await API.post('share.php', { entry_id: entryId, type: 'link' });
        navigator.clipboard.writeText(data.link);
        Toast.success(DiaryApp.t('Skakel gekopieer', 'Link copied'));
        break;
      case 'export':
        window.open(`/diary/api/export.php?id=${entryId}&format=pdf`, '_blank');
        Toast.success(DiaryApp.t('PDF word voorberei...', 'PDF is being prepared...'));
        break;
    }
    closeShareModal();
  } catch (error) {
    Toast.error(DiaryApp.t('Kon nie deel nie', 'Could not share'));
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
    <span class="tag">
      #${escapeHtml(tag)}
      <button type="button" class="tag-remove" onclick="removeTag('${escapeHtml(tag)}')">×</button>
    </span>
  `).join('');
}

// ===== WORD COUNT =====
function updateWordCount() {
  const body = document.getElementById('entryBody')?.value || '';
  const words = body.trim().split(/\s+/).filter(w => w.length > 0).length;
  const counter = document.getElementById('wordCount');
  if (counter) {
    counter.textContent = `${words} ${DiaryApp.t('woorde', 'words')}`;
  }
}

// ===== AI ENHANCE =====
async function aiEnhanceEntry() {
  const body = document.getElementById('entryBody')?.value;
  if (!body) {
    Toast.warning(DiaryApp.t('Skryf eers iets...', 'Write something first...'));
    return;
  }
  
  const btn = document.getElementById('aiAssistBtn');
  btn.disabled = true;
  btn.innerHTML = `
    <div class="loading-spinner" style="width: 18px; height: 18px; border-width: 2px;"></div>
    ${DiaryApp.t('AI Verbeter...', 'AI Enhancing...')}
  `;
  
  try {
    const data = await API.post('ai_enhance.php', { text: body });
    document.getElementById('entryBody').value = data.enhanced_text;
    updateWordCount();
    Toast.success(DiaryApp.t('Teks verbeter!', 'Text enhanced!'));
  } catch (error) {
    Toast.error(DiaryApp.t('AI kon nie help nie', 'AI could not help'));
  } finally {
    btn.disabled = false;
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" fill="none"/>
      </svg>
      ${DiaryApp.t('AI Hulp', 'AI Assist')}
    `;
  }
}

// ===== EXPORT =====
async function exportDiary() {
  const format = prompt(DiaryApp.t('Voer formaat in (pdf/json):', 'Enter format (pdf/json):'), 'pdf');
  if (!format) return;
  
  try {
    window.open(`/diary/api/export.php?format=${format}`, '_blank');
    Toast.success(DiaryApp.t('Uitvoer begin...', 'Export starting...'));
  } catch (error) {
    Toast.error(DiaryApp.t('Kon nie uitvoer nie', 'Could not export'));
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
  const d = new Date(date + 'T' + (time || '00:00:00'));
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  
  if (d.toDateString() === today.toDateString()) {
    return DiaryApp.t('Vandag', 'Today') + ' ' + d.toLocaleTimeString(DiaryApp.lang === 'af' ? 'af-ZA' : 'en-US', { hour: '2-digit', minute: '2-digit' });
  } else if (d.toDateString() === yesterday.toDateString()) {
    return DiaryApp.t('Gister', 'Yesterday') + ' ' + d.toLocaleTimeString(DiaryApp.lang === 'af' ? 'af-ZA' : 'en-US', { hour: '2-digit', minute: '2-digit' });
  } else {
    return d.toLocaleDateString(DiaryApp.lang === 'af' ? 'af-ZA' : 'en-US') + ' ' + d.toLocaleTimeString(DiaryApp.lang === 'af' ? 'af-ZA' : 'en-US', { hour: '2-digit', minute: '2-digit' });
  }
}

function getMoodEmoji(mood) {
  const moods = {
    happy: '😊',
    sad: '😢',
    excited: '🤗',
    calm: '😌',
    thoughtful: '🤔',
    grateful: '🙏',
    inspired: '✨',
    tired: '😴'
  };
  return moods[mood] || '😊';
}

function getWeatherEmoji(weather) {
  const weathers = {
    sunny: '☀️',
    cloudy: '☁️',
    rainy: '🌧️',
    stormy: '⛈️',
    snowy: '❄️',
    windy: '🌬️'
  };
  return weathers[weather] || '☀️';
}

function highlightText(text, query) {
  if (!query) return escapeHtml(text);
  const regex = new RegExp(`(${query})`, 'gi');
  return escapeHtml(text).replace(regex, '<mark>$1</mark>');
}

function refreshCurrentView() {
  switch(DiaryApp.currentView) {
    case 'timeline':
      loadTimeline();
      break;
    case 'calendar':
      if (DiaryApp.calendar) DiaryApp.calendar.refetchEvents();
      break;
    case 'gallery':
      loadGallery();
      break;
  }
}

// ===== EVENT LISTENERS =====
document.addEventListener('DOMContentLoaded', function() {
  
  // View toggle
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      switchView(btn.dataset.view);
    });
  });
  
  // New entry button
  document.getElementById('newEntryBtn')?.addEventListener('click', () => {
    openEntryModal();
  });
  
  // Export button
  document.getElementById('exportBtn')?.addEventListener('click', exportDiary);
  
  // Share button (for current view)
  document.getElementById('shareBtn')?.addEventListener('click', () => {
    Toast.info(DiaryApp.t('Kies n inskrywing om te deel', 'Choose an entry to share'));
  });
  
  // AI enhance button (in actions)
  document.getElementById('aiEnhanceBtn')?.addEventListener('click', () => {
    Toast.info(DiaryApp.t('Maak eers n inskrywing oop', 'Open an entry first'));
  });
  
  // Modal close
  document.getElementById('modalClose')?.addEventListener('click', closeEntryModal);
  document.getElementById('modalOverlay')?.addEventListener('click', closeEntryModal);
  document.getElementById('cancelBtn')?.addEventListener('click', closeEntryModal);
  
  // Form submit
  document.getElementById('entryForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    saveEntry();
  });
  
  // AI assist in modal
  document.getElementById('aiAssistBtn')?.addEventListener('click', aiEnhanceEntry);
  
  // Word count
  document.getElementById('entryBody')?.addEventListener('input', updateWordCount);
  
  // Tag input
  document.getElementById('tagInput')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const input = e.target;
      addTag(input.value);
      input.value = '';
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
    const search = document.getElementById('timelineSearch').value;
    loadTimeline({ search });
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
    if (e.key === 'Enter') {
      e.preventDefault();
      performSearch();
    }
  });
  
  // Editor toolbar buttons
  document.querySelectorAll('.editor-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const action = btn.dataset.action;
      // Simple formatting - you can expand this
      Toast.info(DiaryApp.t('Formatering binnekort', 'Formatting coming soon'));
    });
  });
  
  // Load initial view
  loadStats();
  loadTimeline();
  
  // Keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + N = New entry
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
      e.preventDefault();
      openEntryModal();
    }
    
    // Escape = Close modal
    if (e.key === 'Escape') {
      closeEntryModal();
      closeShareModal();
    }
  });
  
});

// Debounce helper
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

