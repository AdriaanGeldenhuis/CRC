/**
 * CRC Gospel Media JavaScript
 */

// Get CSRF token
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Show toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Post Modal
function openPostModal(type) {
    document.getElementById('postModal').classList.add('show');
    document.getElementById('postContent').focus();
}

function closePostModal() {
    document.getElementById('postModal').classList.remove('show');
    document.getElementById('createPostForm').reset();
    document.getElementById('mediaPreview').innerHTML = '';
}

// Preview media
function previewMedia(input) {
    const preview = document.getElementById('mediaPreview');
    preview.innerHTML = '';

    Array.from(input.files).forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });
}

// Create post
async function createPost(e) {
    e.preventDefault();

    const btn = document.getElementById('postSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Posting...';

    const formData = new FormData();
    formData.append('scope', document.getElementById('postScope').value);
    formData.append('content', document.getElementById('postContent').value);

    const mediaInput = document.getElementById('postMedia');
    if (mediaInput.files.length > 0) {
        Array.from(mediaInput.files).forEach((file, i) => {
            formData.append('media[]', file);
        });
    }

    try {
        const response = await fetch('/gospel_media/api/posts.php?action=create', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': getCSRFToken()
            },
            body: formData
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Post created!');
            closePostModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to create post', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Post';
    }
}

// Toggle reaction
async function toggleReaction(postId) {
    const btn = document.querySelector(`[data-post-id="${postId}"] .post-action:first-child`);

    try {
        const response = await fetch('/gospel_media/api/reactions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                type: 'post',
                id: postId
            })
        });

        const data = await response.json();

        if (data.ok) {
            btn.classList.toggle('active', data.action === 'added');

            // Update count
            const statsEl = document.querySelector(`[data-post-id="${postId}"] .post-stats`);
            if (statsEl) {
                // Simple refresh approach
                location.reload();
            }
        }
    } catch (error) {
        showToast('Error', 'error');
    }
}

// Toggle comments
async function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    const isVisible = section.style.display !== 'none';

    if (isVisible) {
        section.style.display = 'none';
        return;
    }

    // Load comments
    try {
        const response = await fetch(`/gospel_media/api/comments.php?post_id=${postId}`);
        const data = await response.json();

        if (data.ok) {
            const list = section.querySelector('.comments-list');
            list.innerHTML = '';

            if (data.comments.length === 0) {
                list.innerHTML = '<p style="color: #9CA3AF; font-size: 0.875rem;">No comments yet</p>';
            } else {
                data.comments.forEach(comment => {
                    list.innerHTML += `
                        <div class="comment-item">
                            <div class="author-avatar-placeholder" style="width:32px;height:32px;font-size:0.75rem;">
                                ${comment.author_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="comment-content">
                                <strong>${escapeHtml(comment.author_name)}</strong>
                                <p>${escapeHtml(comment.content)}</p>
                                <span class="comment-time">${comment.time_ago}</span>
                            </div>
                        </div>
                    `;
                });
            }
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }

    section.style.display = 'block';
}

// Submit comment
async function submitComment(e, postId) {
    e.preventDefault();

    const form = e.target;
    const input = form.querySelector('.comment-input');
    const content = input.value.trim();

    if (!content) return;

    input.disabled = true;

    try {
        const response = await fetch('/gospel_media/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                post_id: postId,
                content: content
            })
        });

        const data = await response.json();

        if (data.ok) {
            input.value = '';
            // Reload comments
            toggleComments(postId);
            toggleComments(postId);
        } else {
            showToast(data.error || 'Failed to post comment', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    } finally {
        input.disabled = false;
    }
}

// Share post
function sharePost(postId) {
    const url = window.location.origin + '/gospel_media/post.php?id=' + postId;

    if (navigator.share) {
        navigator.share({
            title: 'CRC Post',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copied to clipboard');
        });
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePostModal();
    }
});
