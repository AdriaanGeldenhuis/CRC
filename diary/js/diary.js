/**
 * CRC Diary JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initEntryForm();
    initTagsInput();
    initPrayerForm();
    initAnsweredForm();
});

// Entry Form
function initEntryForm() {
    const form = document.getElementById('entry-form');
    if (!form) return;

    const entryId = form.dataset.entryId;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', entryId ? 'update' : 'create');
        if (entryId) {
            formData.append('entry_id', entryId);
        }

        // Get tags
        const tagItems = document.querySelectorAll('.tag-item');
        tagItems.forEach(tag => {
            formData.append('tags[]', tag.textContent.trim().replace('×', ''));
        });

        try {
            const response = await fetch('/diary/api/entries.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
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

// Delete Entry
async function deleteEntry(entryId) {
    if (!confirm('Are you sure you want to delete this entry? This cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('entry_id', entryId);

    try {
        const response = await fetch('/diary/api/entries.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.href = '/diary/';
        } else {
            alert(data.error || 'Failed to delete entry');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete entry');
    }
}

// Tags Input
function initTagsInput() {
    const tagInput = document.getElementById('tag-input');
    const tagsList = document.getElementById('tags-list');
    const suggestions = document.getElementById('tag-suggestions');

    if (!tagInput || !tagsList) return;

    // Add tag on enter
    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const tagName = this.value.trim();
            if (tagName) {
                addTag(tagName);
                this.value = '';
            }
        }
    });

    // Suggestion click
    if (suggestions) {
        suggestions.addEventListener('click', function(e) {
            const btn = e.target.closest('.tag-suggestion');
            if (btn) {
                addTag(btn.dataset.name);
                tagInput.value = '';
            }
        });
    }

    // Remove tag
    tagsList.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.tag-remove');
        if (removeBtn) {
            removeBtn.closest('.tag-item').remove();
        }
    });
}

function addTag(name) {
    const tagsList = document.getElementById('tags-list');
    if (!tagsList) return;

    // Check if already exists
    const existing = Array.from(tagsList.querySelectorAll('.tag-item')).find(
        tag => tag.textContent.trim().replace('×', '') === name
    );
    if (existing) return;

    const tagEl = document.createElement('span');
    tagEl.className = 'tag-item';
    tagEl.innerHTML = `${name}<button type="button" class="tag-remove">&times;</button>`;
    tagsList.appendChild(tagEl);
}

// Prayer Modal
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
    document.getElementById('prayer-modal').classList.remove('show');
}

async function loadPrayer(prayerId) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', prayerId);

    try {
        const response = await fetch('/diary/api/prayers.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
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
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
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

function editPrayer(prayerId) {
    openPrayerModal(prayerId);
}

async function deletePrayer(prayerId) {
    if (!confirm('Are you sure you want to delete this prayer request?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', prayerId);

    try {
        const response = await fetch('/diary/api/prayers.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
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
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to toggle pin');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Answered Modal
function markAnswered(prayerId) {
    const modal = document.getElementById('answered-modal');
    document.getElementById('answered-prayer-id').value = prayerId;
    document.getElementById('testimony').value = '';
    modal.classList.add('show');
}

function closeAnsweredModal() {
    document.getElementById('answered-modal').classList.remove('show');
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
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.ok) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to mark as answered');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to mark as answered');
        }
    });
}
