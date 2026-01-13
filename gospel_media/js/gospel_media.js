/**
 * CRC Gospel Media JavaScript
 * Refactored for proper AJAX state management (no page reloads)
 */

// Get CSRF token
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =====================================================
// POST MODAL
// =====================================================

function openPostModal(type) {
    const modal = document.getElementById('postModal');
    if (modal) {
        modal.classList.add('show');
        document.getElementById('postContent')?.focus();
    }
}

function closePostModal() {
    const modal = document.getElementById('postModal');
    if (modal) {
        modal.classList.remove('show');
        document.getElementById('createPostForm')?.reset();
        const preview = document.getElementById('mediaPreview');
        if (preview) preview.innerHTML = '';
    }
}

// Preview media before upload
function previewMedia(input) {
    const preview = document.getElementById('mediaPreview');
    if (!preview) return;
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

// =====================================================
// CREATE POST
// =====================================================

async function createPost(e) {
    e.preventDefault();

    const btn = document.getElementById('postSubmitBtn');
    const contentEl = document.getElementById('postContent');
    const scopeEl = document.getElementById('postScope');
    const mediaInput = document.getElementById('postMedia');

    if (!btn || !contentEl) return;

    const content = contentEl.value.trim();
    if (!content) {
        showToast('Please write something', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Posting...';

    const formData = new FormData();
    formData.append('content', content);
    formData.append('scope', scopeEl?.value || 'congregation');

    if (mediaInput?.files.length > 0) {
        Array.from(mediaInput.files).forEach(file => {
            formData.append('media[]', file);
        });
    }

    try {
        const response = await fetch('/gospel_media/api/posts.php?action=create', {
            method: 'POST',
            headers: { 'X-CSRF-Token': getCSRFToken() },
            body: formData
        });

        const data = await response.json();

        if (data.ok) {
            showToast('Post created!');
            closePostModal();
            // Reload to show new post at top (simple approach)
            // Future: prepend post HTML directly
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

// =====================================================
// REACTIONS (Like/Unlike) - NO PAGE RELOAD
// =====================================================

async function toggleReaction(postId) {
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    if (!postCard) return;

    const likeBtn = postCard.querySelector('.post-action');
    if (!likeBtn) return;

    // Optimistic UI update
    const wasLiked = likeBtn.classList.contains('liked');
    const svgIcon = likeBtn.querySelector('svg');

    // Toggle state immediately for responsive feel
    likeBtn.classList.toggle('liked', !wasLiked);
    if (svgIcon) {
        svgIcon.setAttribute('fill', wasLiked ? 'none' : 'currentColor');
    }

    try {
        const response = await fetch('/gospel_media/api/reactions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ type: 'post', id: postId })
        });

        const data = await response.json();

        if (data.ok) {
            const isNowLiked = data.action === 'added';
            likeBtn.classList.toggle('liked', isNowLiked);
            if (svgIcon) {
                svgIcon.setAttribute('fill', isNowLiked ? 'currentColor' : 'none');
            }

            // Update reaction count in engagement stats
            updateReactionCount(postCard, isNowLiked);
        } else {
            // Revert on error
            likeBtn.classList.toggle('liked', wasLiked);
            if (svgIcon) {
                svgIcon.setAttribute('fill', wasLiked ? 'currentColor' : 'none');
            }
            showToast('Failed to update', 'error');
        }
    } catch (error) {
        // Revert on network error
        likeBtn.classList.toggle('liked', wasLiked);
        if (svgIcon) {
            svgIcon.setAttribute('fill', wasLiked ? 'currentColor' : 'none');
        }
        showToast('Network error', 'error');
    }
}

function updateReactionCount(postCard, added) {
    let statsContainer = postCard.querySelector('.engagement-stats');
    let reactionStat = statsContainer?.querySelector('.stat');

    // Find or create the reaction stat element
    if (!statsContainer) {
        // Create engagement-stats container if it doesn't exist
        const engagementDiv = postCard.querySelector('.post-engagement');
        if (engagementDiv) {
            statsContainer = document.createElement('div');
            statsContainer.className = 'engagement-stats';
            engagementDiv.insertBefore(statsContainer, engagementDiv.firstChild);
        }
    }

    if (!statsContainer) return;

    // Find existing reaction stat (has heart icon)
    reactionStat = Array.from(statsContainer.querySelectorAll('.stat')).find(el =>
        el.querySelector('.reaction-icons') || el.querySelector('svg[fill*="danger"]')
    );

    if (reactionStat) {
        // Parse current count
        const countText = reactionStat.textContent.trim();
        let count = parseInt(countText.replace(/[^0-9]/g, '')) || 0;
        count = added ? count + 1 : Math.max(0, count - 1);

        if (count > 0) {
            reactionStat.innerHTML = `
                <span class="reaction-icons">
                    <svg viewBox="0 0 24 24" fill="var(--danger)" width="16" height="16">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </span>
                ${count}
            `;
        } else {
            reactionStat.remove();
            // Remove container if empty
            if (statsContainer.children.length === 0) {
                statsContainer.remove();
            }
        }
    } else if (added) {
        // Create new reaction stat
        const newStat = document.createElement('span');
        newStat.className = 'stat';
        newStat.innerHTML = `
            <span class="reaction-icons">
                <svg viewBox="0 0 24 24" fill="var(--danger)" width="16" height="16">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </span>
            1
        `;
        statsContainer.insertBefore(newStat, statsContainer.firstChild);
    }
}

// =====================================================
// COMMENTS - NO PAGE RELOAD
// =====================================================

async function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    if (!section) return;

    const isVisible = section.style.display !== 'none';

    if (isVisible) {
        section.style.display = 'none';
        return;
    }

    // Show section and load comments
    section.style.display = 'block';
    await loadComments(postId);
}

async function loadComments(postId) {
    const section = document.getElementById('comments-' + postId);
    if (!section) return;

    const list = section.querySelector('.comments-list');
    if (!list) return;

    list.innerHTML = '<p style="color: #9CA3AF; font-size: 0.875rem;">Loading...</p>';

    try {
        const response = await fetch(`/gospel_media/api/comments.php?post_id=${postId}`);
        const data = await response.json();

        if (data.ok) {
            if (data.comments.length === 0) {
                list.innerHTML = '<p style="color: #9CA3AF; font-size: 0.875rem;">No comments yet. Be the first!</p>';
            } else {
                list.innerHTML = data.comments.map(comment => `
                    <div class="comment-item" data-comment-id="${comment.id}">
                        <div class="author-avatar-placeholder" style="width:32px;height:32px;font-size:0.75rem;">
                            ${comment.author_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="comment-content">
                            <strong>${escapeHtml(comment.author_name)}</strong>
                            <p>${escapeHtml(comment.content)}</p>
                            <span class="comment-time">${comment.time_ago}</span>
                        </div>
                    </div>
                `).join('');
            }
        } else {
            list.innerHTML = '<p style="color: #EF4444; font-size: 0.875rem;">Failed to load comments</p>';
        }
    } catch (error) {
        list.innerHTML = '<p style="color: #EF4444; font-size: 0.875rem;">Network error</p>';
    }
}

async function submitComment(e, postId) {
    e.preventDefault();

    const form = e.target;
    const input = form.querySelector('.comment-input');
    const submitBtn = form.querySelector('.comment-submit');
    const content = input?.value.trim();

    if (!content) return;

    input.disabled = true;
    if (submitBtn) submitBtn.disabled = true;

    try {
        const response = await fetch('/gospel_media/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId, content: content })
        });

        const data = await response.json();

        if (data.ok) {
            input.value = '';
            // Reload comments to show new one
            await loadComments(postId);
            // Update comment count in stats
            updateCommentCount(postId, true);
            showToast('Comment posted');
        } else {
            showToast(data.error || 'Failed to post comment', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    } finally {
        input.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
    }
}

function updateCommentCount(postId, added) {
    const postCard = document.querySelector(`[data-post-id="${postId}"]`);
    if (!postCard) return;

    let statsContainer = postCard.querySelector('.engagement-stats');
    if (!statsContainer) {
        const engagementDiv = postCard.querySelector('.post-engagement');
        if (engagementDiv) {
            statsContainer = document.createElement('div');
            statsContainer.className = 'engagement-stats';
            engagementDiv.insertBefore(statsContainer, engagementDiv.firstChild);
        }
    }
    if (!statsContainer) return;

    // Find comment stat (text contains "comment")
    let commentStat = Array.from(statsContainer.querySelectorAll('.stat')).find(el =>
        el.textContent.toLowerCase().includes('comment')
    );

    if (commentStat) {
        const count = parseInt(commentStat.textContent.replace(/[^0-9]/g, '')) || 0;
        const newCount = added ? count + 1 : Math.max(0, count - 1);
        if (newCount > 0) {
            commentStat.textContent = `${newCount} comment${newCount !== 1 ? 's' : ''}`;
        } else {
            commentStat.remove();
        }
    } else if (added) {
        const newStat = document.createElement('span');
        newStat.className = 'stat';
        newStat.textContent = '1 comment';
        statsContainer.appendChild(newStat);
    }
}

// =====================================================
// POST MANAGEMENT (Delete/Edit)
// =====================================================

async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;

    try {
        const response = await fetch('/gospel_media/api/posts.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId })
        });

        const data = await response.json();

        if (data.ok) {
            // Remove post from DOM
            const postCard = document.querySelector(`[data-post-id="${postId}"]`);
            if (postCard) {
                postCard.style.transition = 'opacity 0.3s, transform 0.3s';
                postCard.style.opacity = '0';
                postCard.style.transform = 'scale(0.95)';
                setTimeout(() => postCard.remove(), 300);
            }
            showToast('Post deleted');
        } else {
            showToast(data.error || 'Failed to delete post', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    }
}

function editPost(postId) {
    window.location.href = '/gospel_media/edit.php?id=' + postId;
}

// =====================================================
// POST OPTIONS MENU
// =====================================================

function togglePostMenu(postId) {
    const menu = document.getElementById('postMenu-' + postId);
    if (!menu) return;

    // Close all other menus first
    document.querySelectorAll('.post-options-menu.show').forEach(m => {
        if (m.id !== 'postMenu-' + postId) {
            m.classList.remove('show');
        }
    });

    menu.classList.toggle('show');
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.post-options')) {
        document.querySelectorAll('.post-options-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// =====================================================
// SHARE POST
// =====================================================

function sharePost(postId) {
    const url = window.location.origin + '/gospel_media/post.php?id=' + postId;

    if (navigator.share) {
        navigator.share({ title: 'CRC Post', url: url }).catch(() => {});
    } else {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copied to clipboard');
        }).catch(() => {
            showToast('Could not copy link', 'error');
        });
    }
}

// =====================================================
// KEYBOARD SHORTCUTS
// =====================================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePostModal();
        // Close any open menus
        document.querySelectorAll('.post-options-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// =====================================================
// IMAGE VIEWER
// =====================================================

function openImageViewer(src) {
    const viewer = document.getElementById('imageViewer');
    const img = document.getElementById('viewerImage');
    if (viewer && img) {
        img.src = src;
        viewer.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageViewer() {
    const viewer = document.getElementById('imageViewer');
    if (viewer) {
        viewer.classList.remove('show');
        document.body.style.overflow = '';
    }
}
