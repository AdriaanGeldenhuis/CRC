/**
 * CRC Morning Watch JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initEntryForm();
});

function initEntryForm() {
    const form = document.getElementById('entry-form');
    if (!form) return;

    const sessionId = form.dataset.sessionId;

    // Auto-save on blur
    const textareas = form.querySelectorAll('textarea');
    let saveTimeout;

    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => autoSave(form, sessionId), 2000);
        });
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        await saveEntry(form, sessionId);
    });
}

async function autoSave(form, sessionId) {
    const reflection = form.querySelector('#reflection').value.trim();
    const prayer = form.querySelector('#prayer').value.trim();
    const application = form.querySelector('#application').value.trim();

    if (!reflection && !prayer && !application) return;

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('session_id', sessionId);
    formData.append('reflection', reflection);
    formData.append('prayer', prayer);
    formData.append('application', application);

    try {
        await fetch('/morning_watch/api/entry.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        // Silent auto-save
    } catch (error) {
        console.error('Auto-save failed:', error);
    }
}

async function saveEntry(form, sessionId) {
    const reflection = form.querySelector('#reflection').value.trim();
    const prayer = form.querySelector('#prayer').value.trim();
    const application = form.querySelector('#application').value.trim();

    if (!reflection && !prayer && !application) {
        showToast('Please fill in at least one field');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('session_id', sessionId);
    formData.append('reflection', reflection);
    formData.append('prayer', prayer);
    formData.append('application', application);

    try {
        const response = await fetch('/morning_watch/api/entry.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Entry saved!');
            updateSavedIndicator();
            submitBtn.textContent = 'Update Entry';
        } else {
            showToast(data.error || 'Failed to save');
            submitBtn.textContent = 'Save Entry';
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to save entry');
        submitBtn.textContent = 'Save Entry';
    }

    submitBtn.disabled = false;
}

function updateSavedIndicator() {
    const actions = document.querySelector('.form-actions');
    let indicator = actions.querySelector('.saved-indicator');

    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    if (indicator) {
        indicator.textContent = `✓ Saved at ${timeStr}`;
    } else {
        indicator = document.createElement('span');
        indicator.className = 'saved-indicator';
        indicator.textContent = `✓ Saved at ${timeStr}`;
        actions.insertBefore(indicator, actions.firstChild);
    }
}

function showToast(message) {
    // Create toast if doesn't exist
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1F2937;
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            z-index: 300;
            opacity: 0;
            transition: all 0.3s ease;
        `;
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.style.transform = 'translateX(-50%) translateY(0)';
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.transform = 'translateX(-50%) translateY(100px)';
        toast.style.opacity = '0';
    }, 3000);
}
