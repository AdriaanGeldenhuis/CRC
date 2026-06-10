/**
 * CRC Gospel Media JavaScript
 * Enhanced with: Multiple reactions, comment replies, infinite scroll, search
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
// REACTIONS - Multiple Types (Like, Love, Pray, Amen)
// =====================================================

var reactionPickerTimeout = null;

// Show reaction picker on long press / hover
function setupReactionPickers() {
    document.querySelectorAll('.reaction-btn-wrap').forEach(function(wrap) {
        var btn = wrap.querySelector('.post-action');
        var picker = wrap.querySelector('.reaction-picker');
        if (!btn || !picker) return;

        // Long press on mobile
        var longPressTimer = null;

        btn.addEventListener('touchstart', function(e) {
            longPressTimer = setTimeout(function() {
                e.preventDefault();
                showReactionPicker(picker);
            }, 500);
        }, { passive: false });

        btn.addEventListener('touchend', function() {
            clearTimeout(longPressTimer);
        });

        btn.addEventListener('touchmove', function() {
            clearTimeout(longPressTimer);
        });

        // Hover on desktop
        wrap.addEventListener('mouseenter', function() {
            reactionPickerTimeout = setTimeout(function() {
                showReactionPicker(picker);
            }, 600);
        });

        wrap.addEventListener('mouseleave', function() {
            clearTimeout(reactionPickerTimeout);
            setTimeout(function() {
                if (!picker.matches(':hover')) {
                    picker.classList.remove('show');
                }
            }, 300);
        });

        picker.addEventListener('mouseleave', function() {
            picker.classList.remove('show');
        });
    });
}

function showReactionPicker(picker) {
    // Close all other pickers
    document.querySelectorAll('.reaction-picker.show').forEach(function(p) {
        p.classList.remove('show');
    });
    picker.classList.add('show');
}

async function toggleReaction(postId, reactionType) {
    reactionType = reactionType || 'like';
    var postCard = document.querySelector('[data-post-id="' + postId + '"]');
    if (!postCard) return;

    var reactionBtn = postCard.querySelector('.reaction-btn-wrap .post-action');
    if (!reactionBtn) return;

    // Close picker
    var picker = postCard.querySelector('.reaction-picker');
    if (picker) picker.classList.remove('show');

    // The server toggles the reaction off when the same type is sent again,
    // so we always send the requested type and let the API decide.
    var sendType = reactionType;

    try {
        var response = await fetch('/gospel_media/api/reactions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ type: 'post', id: postId, reaction_type: sendType })
        });

        var data = await response.json();

        if (data.ok) {
            var labels = { like: 'Like', love: 'Love', pray: 'Pray', amen: 'Amen' };
            var colors = { like: '#3B82F6', love: '#EF4444', pray: '#8B5CF6', amen: '#F59E0B' };
            var icons = {
                like: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
                love: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
                pray: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>',
                amen: '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>'
            };

            if (data.action === 'removed') {
                reactionBtn.className = 'post-action';
                reactionBtn.setAttribute('data-reaction', '');
                reactionBtn.style.color = '';
                reactionBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg><span>Like</span>';
                reactionBtn.onclick = function() { toggleReaction(postId, 'like'); };
            } else {
                var rt = data.reaction_type || sendType;
                reactionBtn.className = 'post-action reacted reaction-' + rt;
                reactionBtn.setAttribute('data-reaction', rt);
                reactionBtn.style.color = colors[rt] || '';
                reactionBtn.innerHTML = (icons[rt] || icons.like) + '<span>' + (labels[rt] || 'Like') + '</span>';
                reactionBtn.onclick = function() { toggleReaction(postId, rt); };
            }

            // Update reaction summary
            updateReactionSummary(postCard, data.summary);
        } else {
            showToast('Failed to update', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    }
}

function selectReaction(postId, reactionType) {
    // Close picker
    var picker = document.getElementById('reactionPicker-' + postId);
    if (picker) picker.classList.remove('show');
    toggleReaction(postId, reactionType);
}

function updateReactionSummary(postCard, summary) {
    if (!summary) return;

    var engagementDiv = postCard.querySelector('.post-engagement');
    if (!engagementDiv) return;

    var statsContainer = postCard.querySelector('.engagement-stats');
    var reactionStat = postCard.querySelector('.reaction-summary');

    if (summary.total > 0) {
        if (!statsContainer) {
            statsContainer = document.createElement('div');
            statsContainer.className = 'engagement-stats';
            engagementDiv.insertBefore(statsContainer, engagementDiv.firstChild);
        }

        var miniIcons = {
            like: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
            love: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            pray: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>',
            amen: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>'
        };
        var miniColors = { like: '#3B82F6', love: '#EF4444', pray: '#8B5CF6', amen: '#F59E0B' };

        var iconsHtml = '';
        var count = 0;
        for (var type in summary) {
            if (type === 'total' || count >= 3) continue;
            if (summary[type] > 0 && miniIcons[type]) {
                iconsHtml += '<span class="reaction-mini" style="color:' + miniColors[type] + '">' + miniIcons[type] + '</span>';
                count++;
            }
        }

        if (!reactionStat) {
            reactionStat = document.createElement('span');
            reactionStat.className = 'stat reaction-summary';
            statsContainer.insertBefore(reactionStat, statsContainer.firstChild);
        }
        reactionStat.innerHTML = '<span class="reaction-icons-row">' + iconsHtml + '</span> ' + summary.total;
    } else if (reactionStat) {
        reactionStat.remove();
        if (statsContainer && statsContainer.children.length === 0) {
            statsContainer.remove();
        }
    }
}

// =====================================================
// COMMENTS - With Reply Support
// =====================================================

async function toggleComments(postId) {
    var section = document.getElementById('comments-' + postId);
    if (!section) return;

    var isVisible = section.style.display !== 'none';

    if (isVisible) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    await loadComments(postId);
}

async function loadComments(postId) {
    var section = document.getElementById('comments-' + postId);
    if (!section) return;

    var list = section.querySelector('.comments-list');
    if (!list) return;

    // Skeleton loading
    list.innerHTML = '<div class="comment-skeleton"><div class="skel-avatar"></div><div class="skel-body"><div class="skel-line w60"></div><div class="skel-line w80"></div><div class="skel-line w40"></div></div></div>'.repeat(2);

    try {
        var response = await fetch('/gospel_media/api/comments.php?post_id=' + postId);
        var data = await response.json();

        if (data.ok) {
            if (data.comments.length === 0) {
                list.innerHTML = '<p class="comments-empty">No comments yet. Be the first!</p>';
            } else {
                list.innerHTML = data.comments.map(function(comment) {
                    return renderComment(comment, postId);
                }).join('');
            }
        } else {
            list.innerHTML = '<p class="comments-error">Failed to load comments</p>';
        }
    } catch (error) {
        list.innerHTML = '<p class="comments-error">Network error</p>';
    }
}

function renderComment(comment, postId) {
    var replyCount = comment.reply_count || 0;
    var replyHtml = replyCount > 0
        ? '<button class="comment-action-btn reply-toggle" onclick="loadReplies(' + comment.id + ', ' + postId + ')">View ' + replyCount + ' repl' + (replyCount === 1 ? 'y' : 'ies') + '</button>'
        : '';

    return '<div class="comment-item" data-comment-id="' + comment.id + '">' +
        '<div class="author-avatar-placeholder" style="width:32px;height:32px;font-size:0.75rem;">' +
            comment.author_name.charAt(0).toUpperCase() +
        '</div>' +
        '<div class="comment-content">' +
            '<strong>' + escapeHtml(comment.author_name) + '</strong>' +
            '<p class="comment-text">' + escapeHtml(comment.content) + '</p>' +
            '<div class="comment-meta">' +
                '<span class="comment-time">' + comment.time_ago + '</span>' +
                '<button class="comment-action-btn" onclick="replyToComment(' + comment.id + ', ' + postId + ', \'' + escapeHtml(comment.author_name).replace(/'/g, "\\'") + '\')">Reply</button>' +
                replyHtml +
                (comment.can_edit ? (
                    '<button class="comment-action-btn" onclick="editComment(' + comment.id + ', ' + postId + ')">Edit</button>' +
                    '<button class="comment-action-btn delete" onclick="deleteComment(' + comment.id + ', ' + postId + ')">Delete</button>'
                ) : '') +
            '</div>' +
            '<div class="comment-replies" id="replies-' + comment.id + '"></div>' +
            '<div class="reply-form-wrap" id="replyForm-' + comment.id + '" style="display:none;"></div>' +
        '</div>' +
    '</div>';
}

function replyToComment(commentId, postId, authorName) {
    var formWrap = document.getElementById('replyForm-' + commentId);
    if (!formWrap) return;

    // Toggle
    if (formWrap.style.display !== 'none') {
        formWrap.style.display = 'none';
        return;
    }

    formWrap.style.display = 'block';
    formWrap.innerHTML =
        '<form class="reply-form" onsubmit="submitReply(event, ' + postId + ', ' + commentId + ')">' +
            '<input type="text" class="comment-input reply-input" placeholder="Reply to ' + escapeHtml(authorName) + '..." required>' +
            '<button type="submit" class="comment-submit">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>' +
            '</button>' +
        '</form>';

    formWrap.querySelector('.reply-input').focus();
}

async function submitReply(e, postId, parentId) {
    e.preventDefault();

    var form = e.target;
    var input = form.querySelector('.reply-input');
    var content = input.value.trim();
    if (!content) return;

    input.disabled = true;

    try {
        var response = await fetch('/gospel_media/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId, content: content, parent_id: parentId })
        });

        var data = await response.json();

        if (data.ok) {
            input.value = '';
            var formWrap = document.getElementById('replyForm-' + parentId);
            if (formWrap) formWrap.style.display = 'none';

            await loadReplies(parentId, postId);
            updateCommentCount(postId, true);
            showToast('Reply posted');
        } else {
            showToast(data.error || 'Failed to post reply', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    } finally {
        input.disabled = false;
    }
}

async function loadReplies(commentId, postId) {
    var repliesDiv = document.getElementById('replies-' + commentId);
    if (!repliesDiv) return;

    repliesDiv.innerHTML = '<p style="color: var(--muted); font-size: 0.8rem; padding: 4px 0;">Loading...</p>';

    try {
        var response = await fetch('/gospel_media/api/comments.php?post_id=' + postId + '&parent_id=' + commentId);
        var data = await response.json();

        if (data.ok && data.comments.length > 0) {
            repliesDiv.innerHTML = data.comments.map(function(reply) {
                return '<div class="comment-item reply-item" data-comment-id="' + reply.id + '">' +
                    '<div class="author-avatar-placeholder" style="width:26px;height:26px;font-size:0.65rem;">' +
                        reply.author_name.charAt(0).toUpperCase() +
                    '</div>' +
                    '<div class="comment-content">' +
                        '<strong>' + escapeHtml(reply.author_name) + '</strong>' +
                        '<p class="comment-text">' + escapeHtml(reply.content) + '</p>' +
                        '<div class="comment-meta">' +
                            '<span class="comment-time">' + reply.time_ago + '</span>' +
                            (reply.can_edit ? (
                                '<button class="comment-action-btn" onclick="editComment(' + reply.id + ', ' + postId + ')">Edit</button>' +
                                '<button class="comment-action-btn delete" onclick="deleteComment(' + reply.id + ', ' + postId + ')">Delete</button>'
                            ) : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        } else {
            repliesDiv.innerHTML = '';
        }
    } catch (error) {
        repliesDiv.innerHTML = '';
    }
}

// Edit comment
async function editComment(commentId, postId) {
    var commentItem = document.querySelector('[data-comment-id="' + commentId + '"]');
    if (!commentItem) return;

    var textEl = commentItem.querySelector('.comment-text');
    var currentText = textEl.textContent;

    var input = document.createElement('textarea');
    input.className = 'comment-edit-input';
    input.value = currentText;

    var actions = document.createElement('div');
    actions.className = 'comment-edit-actions';
    actions.innerHTML =
        '<button class="comment-save-btn">Save</button>' +
        '<button class="comment-cancel-btn">Cancel</button>';

    textEl.replaceWith(input);
    input.after(actions);
    input.focus();

    actions.querySelector('.comment-save-btn').onclick = async function() {
        var newContent = input.value.trim();
        if (!newContent) {
            showToast('Comment cannot be empty', 'error');
            return;
        }

        try {
            var response = await fetch('/gospel_media/api/comments.php?action=update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({ comment_id: commentId, content: newContent })
            });

            var data = await response.json();

            if (data.ok) {
                showToast('Comment updated');
                await loadComments(postId);
            } else {
                showToast(data.error || 'Failed to update comment', 'error');
            }
        } catch (error) {
            showToast('Network error', 'error');
        }
    };

    actions.querySelector('.comment-cancel-btn').onclick = function() {
        loadComments(postId);
    };
}

// Delete comment
async function deleteComment(commentId, postId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;

    try {
        var response = await fetch('/gospel_media/api/comments.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ comment_id: commentId })
        });

        var data = await response.json();

        if (data.ok) {
            showToast('Comment deleted');
            await loadComments(postId);
            updateCommentCount(postId, false);
        } else {
            showToast(data.error || 'Failed to delete comment', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    }
}

async function submitComment(e, postId) {
    e.preventDefault();

    var form = e.target;
    var input = form.querySelector('.comment-input');
    var submitBtn = form.querySelector('.comment-submit');
    var content = input ? input.value.trim() : '';

    if (!content) return;

    input.disabled = true;
    if (submitBtn) submitBtn.disabled = true;

    try {
        var response = await fetch('/gospel_media/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId, content: content })
        });

        var data = await response.json();

        if (data.ok) {
            input.value = '';
            await loadComments(postId);
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
    var postCard = document.querySelector('[data-post-id="' + postId + '"]');
    if (!postCard) return;

    var statsContainer = postCard.querySelector('.engagement-stats');
    if (!statsContainer) {
        var engagementDiv = postCard.querySelector('.post-engagement');
        if (engagementDiv) {
            statsContainer = document.createElement('div');
            statsContainer.className = 'engagement-stats';
            engagementDiv.insertBefore(statsContainer, engagementDiv.firstChild);
        }
    }
    if (!statsContainer) return;

    var commentStat = statsContainer.querySelector('.comment-stat');

    if (commentStat) {
        var count = parseInt(commentStat.textContent.replace(/[^0-9]/g, '')) || 0;
        var newCount = added ? count + 1 : Math.max(0, count - 1);
        if (newCount > 0) {
            commentStat.textContent = newCount + ' comment' + (newCount !== 1 ? 's' : '');
        } else {
            commentStat.remove();
        }
    } else if (added) {
        var newStat = document.createElement('span');
        newStat.className = 'stat comment-stat';
        newStat.textContent = '1 comment';
        statsContainer.appendChild(newStat);
    }
}

// =====================================================
// POST MANAGEMENT (Delete/Edit/Pin)
// =====================================================

async function togglePin(postId, currentlyPinned) {
    try {
        var response = await fetch('/gospel_media/api/posts.php?action=pin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId })
        });

        var data = await response.json();

        if (data.ok) {
            var postCard = document.querySelector('[data-post-id="' + postId + '"]');
            if (postCard) {
                var headerRight = postCard.querySelector('.post-header-right');
                var pinnedBadge = postCard.querySelector('.pinned-badge');

                if (data.pinned) {
                    if (!pinnedBadge && headerRight) {
                        pinnedBadge = document.createElement('span');
                        pinnedBadge.className = 'pinned-badge';
                        pinnedBadge.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>';
                        headerRight.insertBefore(pinnedBadge, headerRight.firstChild);
                    }
                } else {
                    if (pinnedBadge) pinnedBadge.remove();
                }
            }

            showToast(data.pinned ? 'Post pinned' : 'Post unpinned');

            document.querySelectorAll('.post-options-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        } else {
            showToast(data.error || 'Failed to update', 'error');
        }
    } catch (error) {
        showToast('Network error', 'error');
    }
}

async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;

    try {
        var response = await fetch('/gospel_media/api/posts.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ post_id: postId })
        });

        var data = await response.json();

        if (data.ok) {
            var postCard = document.querySelector('[data-post-id="' + postId + '"]');
            if (postCard) {
                postCard.style.transition = 'opacity 0.3s, transform 0.3s';
                postCard.style.opacity = '0';
                postCard.style.transform = 'scale(0.95)';
                setTimeout(function() { postCard.remove(); }, 300);
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
    var postCard = document.querySelector('[data-post-id="' + postId + '"]');
    if (!postCard) {
        window.location.href = '/gospel_media/edit.php?id=' + postId;
        return;
    }

    var contentEl = postCard.querySelector('.post-content');
    if (!contentEl) {
        window.location.href = '/gospel_media/edit.php?id=' + postId;
        return;
    }

    // Close options menu
    document.querySelectorAll('.post-options-menu.show').forEach(function(menu) {
        menu.classList.remove('show');
    });

    var currentContent = contentEl.innerHTML
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .trim();

    var editForm = document.createElement('div');
    editForm.className = 'post-edit-form';
    editForm.innerHTML =
        '<textarea class="post-edit-textarea">' + escapeHtml(currentContent) + '</textarea>' +
        '<div class="post-edit-actions">' +
            '<button class="post-edit-cancel">Cancel</button>' +
            '<button class="post-edit-save">Save Changes</button>' +
        '</div>';

    contentEl.dataset.originalContent = contentEl.innerHTML;
    contentEl.innerHTML = '';
    contentEl.appendChild(editForm);

    var textarea = editForm.querySelector('.post-edit-textarea');
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);

    editForm.querySelector('.post-edit-save').onclick = async function() {
        var newContent = textarea.value.trim();
        if (!newContent) {
            showToast('Post content cannot be empty', 'error');
            return;
        }

        var saveBtn = editForm.querySelector('.post-edit-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            var response = await fetch('/gospel_media/api/posts.php?action=update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({ post_id: postId, content: newContent })
            });

            var data = await response.json();

            if (data.ok) {
                contentEl.innerHTML = escapeHtml(newContent).replace(/\n/g, '<br>');
                showToast('Post updated');
            } else {
                showToast(data.error || 'Failed to update post', 'error');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        } catch (error) {
            showToast('Network error', 'error');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        }
    };

    editForm.querySelector('.post-edit-cancel').onclick = function() {
        contentEl.innerHTML = contentEl.dataset.originalContent;
    };
}

// =====================================================
// POST OPTIONS MENU
// =====================================================

function togglePostMenu(postId) {
    var menu = document.getElementById('postMenu-' + postId);
    if (!menu) return;

    document.querySelectorAll('.post-options-menu.show').forEach(function(m) {
        if (m.id !== 'postMenu-' + postId) {
            m.classList.remove('show');
        }
    });

    menu.classList.toggle('show');
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.post-options')) {
        document.querySelectorAll('.post-options-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
        });
    }
    if (!e.target.closest('.reaction-btn-wrap')) {
        document.querySelectorAll('.reaction-picker.show').forEach(function(p) {
            p.classList.remove('show');
        });
    }
});

// =====================================================
// SHARE POST
// =====================================================

function sharePost(postId) {
    var url = window.location.origin + '/gospel_media/post.php?id=' + postId;

    if (navigator.share) {
        navigator.share({ title: 'CRC Post', url: url }).catch(function() {});
    } else {
        navigator.clipboard.writeText(url).then(function() {
            showToast('Link copied to clipboard');
        }).catch(function() {
            showToast('Could not copy link', 'error');
        });
    }
}

// =====================================================
// SEARCH
// =====================================================

var searchDebounceTimer = null;

function handleSearch(query) {
    var clearBtn = document.getElementById('searchClear');
    if (clearBtn) {
        clearBtn.style.display = query.length > 0 ? 'flex' : 'none';
    }

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(function() {
        filterPosts(query.toLowerCase().trim());
    }, 300);
}

function clearSearch() {
    var input = document.getElementById('feedSearch');
    if (input) {
        input.value = '';
        input.focus();
    }
    var clearBtn = document.getElementById('searchClear');
    if (clearBtn) clearBtn.style.display = 'none';
    filterPosts('');
}

function filterPosts(query) {
    var posts = document.querySelectorAll('.post-card');
    var found = 0;

    posts.forEach(function(post) {
        if (!query) {
            post.style.display = '';
            found++;
            return;
        }

        var content = (post.querySelector('.post-content')?.textContent || '').toLowerCase();
        var author = (post.querySelector('.author-info strong')?.textContent || '').toLowerCase();

        if (content.includes(query) || author.includes(query)) {
            post.style.display = '';
            found++;

            // Highlight matching text
            var contentEl = post.querySelector('.post-content');
            if (contentEl && !contentEl.dataset.originalHtml) {
                contentEl.dataset.originalHtml = contentEl.innerHTML;
            }
        } else {
            post.style.display = 'none';
        }
    });

    // Show/hide empty search state
    var existingMsg = document.getElementById('searchEmpty');
    if (found === 0 && query) {
        if (!existingMsg) {
            var msg = document.createElement('div');
            msg.id = 'searchEmpty';
            msg.className = 'empty-state';
            msg.innerHTML = '<div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div><h3>No results found</h3><p>Try a different search term</p>';
            var feed = document.querySelector('.posts-feed');
            if (feed) feed.appendChild(msg);
        }
    } else if (existingMsg) {
        existingMsg.remove();
    }
}

// =====================================================
// INFINITE SCROLL
// =====================================================

var isLoadingMore = false;

function setupInfiniteScroll() {
    var sentinel = document.getElementById('infiniteScrollSentinel');
    if (!sentinel) return;

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting && !isLoadingMore) {
                loadMorePosts();
            }
        });
    }, { rootMargin: '200px' });

    observer.observe(sentinel);
}

async function loadMorePosts() {
    var sentinel = document.getElementById('infiniteScrollSentinel');
    if (!sentinel || isLoadingMore) return;

    var currentPage = parseInt(sentinel.dataset.page) || 1;
    var totalPages = parseInt(sentinel.dataset.total) || 1;
    var scope = sentinel.dataset.scope || 'all';

    if (currentPage >= totalPages) {
        sentinel.remove();
        return;
    }

    isLoadingMore = true;
    var nextPage = currentPage + 1;

    try {
        var response = await fetch('/gospel_media/api/posts.php?scope=' + scope + '&page=' + nextPage + '&per_page=20');
        var data = await response.json();

        if (data.ok && data.posts && data.posts.length > 0) {
            var feed = document.querySelector('.posts-feed');

            data.posts.forEach(function(post) {
                var article = createPostElement(post);
                feed.insertBefore(article, sentinel);
            });

            sentinel.dataset.page = nextPage;

            // Setup reaction pickers for new posts
            setupReactionPickers();

            if (nextPage >= totalPages) {
                sentinel.remove();
            }
        } else {
            sentinel.remove();
        }
    } catch (error) {
        console.error('Failed to load more posts:', error);
    } finally {
        isLoadingMore = false;
    }
}

var FEED_REACTION_COLORS = { like: '#3B82F6', love: '#EF4444', pray: '#8B5CF6', amen: '#F59E0B' };
var FEED_REACTION_MINI = {
    like: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
    love: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    pray: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>',
    amen: '<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>'
};

function createPostElement(post) {
    var article = document.createElement('article');
    article.className = 'post-card fade-in-post';
    article.dataset.postId = post.id;

    var ctx = window.FEED_CTX || { userInitial: '?', userAvatar: '', isAdmin: false, canModerate: false };
    var initial = (post.author_name || '?').charAt(0).toUpperCase();

    // Author avatar (image with placeholder fallback, matching server render)
    var avatarHtml = post.author_avatar
        ? '<img src="' + escapeHtml(post.author_avatar) + '" alt="" class="author-avatar" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><div class="author-avatar-placeholder" style="display:none;">' + initial + '</div>'
        : '<div class="author-avatar-placeholder">' + initial + '</div>';

    var scopeHtml = '';
    if (post.scope === 'global') {
        scopeHtml = '<span class="scope-badge global">Global</span>';
    } else if (post.congregation_name) {
        scopeHtml = '<span class="scope-badge">' + escapeHtml(post.congregation_name) + '</span>';
    }

    var pinned = (post.is_pinned == 1);
    var pinnedHtml = pinned
        ? '<span class="pinned-badge"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg></span>'
        : '';

    // Options menu (edit/delete for owner/admin, pin for admins) — parity with server
    var optionsHtml = '';
    if (post.can_edit) {
        var pinBtn = ctx.isAdmin
            ? '<button class="post-option" onclick="togglePin(' + post.id + ', ' + (pinned ? 'true' : 'false') + ')"><svg viewBox="0 0 24 24" fill="' + (pinned ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>' + (pinned ? 'Unpin' : 'Pin') + '</button>'
            : '';
        optionsHtml =
            '<div class="post-options">' +
                '<button class="post-options-btn" onclick="togglePostMenu(' + post.id + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></button>' +
                '<div class="post-options-menu" id="postMenu-' + post.id + '">' + pinBtn +
                    '<button class="post-option" onclick="editPost(' + post.id + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Edit</button>' +
                    '<button class="post-option delete" onclick="deletePost(' + post.id + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete</button>' +
                '</div>' +
            '</div>';
    }

    var mediaHtml = '';
    if (post.media) {
        try {
            var media = typeof post.media === 'string' ? JSON.parse(post.media) : post.media;
            if (media && media.length > 0) {
                var gridClass = media.length > 1 ? ' media-grid-' + Math.min(media.length, 4) : '';
                mediaHtml = '<div class="post-media' + gridClass + '">';
                media.slice(0, 4).forEach(function(item) {
                    if (item.type && item.type.indexOf('image') !== -1) {
                        mediaHtml += '<img src="' + escapeHtml(item.url) + '" alt="" class="media-image" onclick="openImageViewer(\'' + escapeHtml(item.url) + '\')">';
                    }
                });
                mediaHtml += '</div>';
            }
        } catch(e) {}
    }

    var content = escapeHtml(post.content || '')
        .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" class="post-link" target="_blank" rel="noopener">$1</a>')
        .replace(/#(\w+)/g, '<span class="hashtag">#$1</span>')
        .replace(/@(\w+)/g, '<span class="mention">@$1</span>')
        .replace(/\n/g, '<br>');

    // Engagement stats (reaction + comment counts)
    var reactionCount = parseInt(post.reaction_count) || 0;
    var commentCount = parseInt(post.comment_count) || 0;
    var userReaction = post.user_reaction || '';
    var statsHtml = '';
    if (reactionCount > 0 || commentCount > 0) {
        var miniType = userReaction || 'like';
        var reactPart = reactionCount > 0
            ? '<span class="stat reaction-summary" data-post-id="' + post.id + '"><span class="reaction-icons-row"><span class="reaction-mini" style="color:' + (FEED_REACTION_COLORS[miniType] || '#7C3AED') + '">' + FEED_REACTION_MINI[miniType] + '</span></span> ' + reactionCount + '</span>'
            : '';
        var commentPart = commentCount > 0
            ? '<span class="stat comment-stat" onclick="toggleComments(' + post.id + ')">' + commentCount + ' comment' + (commentCount !== 1 ? 's' : '') + '</span>'
            : '';
        statsHtml = '<div class="engagement-stats">' + reactPart + commentPart + '</div>';
    }

    // Like button reflecting the user's existing reaction
    var labels = { like: 'Like', love: 'Love', pray: 'Pray', amen: 'Amen' };
    var likeIcon = (userReaction && FEED_REACTION_MINI[userReaction])
        ? FEED_REACTION_MINI[userReaction].replace(' width="14" height="14"', '')
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>';
    var likeClass = 'post-action' + (userReaction ? ' reacted reaction-' + userReaction : '');
    var likeColor = userReaction ? (FEED_REACTION_COLORS[userReaction] || '') : '';
    var likeLabel = labels[userReaction] || 'Like';

    // Comment form avatar (current viewer)
    var commentAvatar = ctx.userAvatar
        ? '<img src="' + escapeHtml(ctx.userAvatar) + '" alt="" class="comment-avatar" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><div class="comment-avatar-placeholder" style="display:none;">' + escapeHtml(ctx.userInitial) + '</div>'
        : '<div class="comment-avatar-placeholder">' + escapeHtml(ctx.userInitial) + '</div>';

    article.innerHTML =
        '<div class="post-header">' +
            '<div class="post-author">' + avatarHtml +
                '<div class="author-info"><strong>' + escapeHtml(post.author_name) + '</strong>' +
                '<span class="post-meta">' + (post.time_ago || '') + ' ' + scopeHtml + '</span></div>' +
            '</div>' +
            '<div class="post-header-right">' + pinnedHtml + optionsHtml + '</div>' +
        '</div>' +
        '<div class="post-content">' + content + '</div>' +
        mediaHtml +
        '<div class="post-engagement">' + statsHtml + '</div>' +
        '<div class="post-actions">' +
            '<div class="reaction-btn-wrap">' +
                '<button class="' + likeClass + '" data-reaction="' + userReaction + '" onclick="toggleReaction(' + post.id + ', \'' + (userReaction || 'like') + '\')"' + (likeColor ? ' style="color:' + likeColor + '"' : '') + '>' +
                    likeIcon + '<span>' + likeLabel + '</span>' +
                '</button>' +
                '<div class="reaction-picker" id="reactionPicker-' + post.id + '">' +
                    '<button class="reaction-option" onclick="selectReaction(' + post.id + ', \'like\')"><span class="reaction-emoji">&#128077;</span></button>' +
                    '<button class="reaction-option" onclick="selectReaction(' + post.id + ', \'love\')"><span class="reaction-emoji">&#10084;&#65039;</span></button>' +
                    '<button class="reaction-option" onclick="selectReaction(' + post.id + ', \'pray\')"><span class="reaction-emoji">&#128591;</span></button>' +
                    '<button class="reaction-option" onclick="selectReaction(' + post.id + ', \'amen\')"><span class="reaction-emoji">&#11088;</span></button>' +
                '</div>' +
            '</div>' +
            '<button class="post-action" onclick="toggleComments(' + post.id + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span>Comment</span></button>' +
            '<button class="post-action" onclick="sharePost(' + post.id + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg><span>Share</span></button>' +
        '</div>' +
        '<div class="comments-section" id="comments-' + post.id + '" style="display:none;">' +
            '<div class="comments-list"></div>' +
            '<form class="comment-form" onsubmit="submitComment(event, ' + post.id + ')">' +
                commentAvatar +
                '<input type="text" placeholder="Write a comment..." class="comment-input" required>' +
                '<button type="submit" class="comment-submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></button>' +
            '</form>' +
        '</div>';

    // Trigger animation
    requestAnimationFrame(function() {
        article.classList.add('visible');
    });

    return article;
}

// =====================================================
// KEYBOARD SHORTCUTS
// =====================================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.post-options-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
        });
        document.querySelectorAll('.reaction-picker.show').forEach(function(p) {
            p.classList.remove('show');
        });
    }
});

// =====================================================
// IMAGE VIEWER
// =====================================================

function openImageViewer(src) {
    var viewer = document.getElementById('imageViewer');
    var img = document.getElementById('viewerImage');
    if (viewer && img) {
        img.src = src;
        viewer.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageViewer() {
    var viewer = document.getElementById('imageViewer');
    if (viewer) {
        viewer.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// =====================================================
// INITIALIZATION
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    setupReactionPickers();
    setupInfiniteScroll();

    // Register the service worker so push notifications work from the feed
    // (these pages don't include the shared navbar that registers it).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function() {});
    }
});
