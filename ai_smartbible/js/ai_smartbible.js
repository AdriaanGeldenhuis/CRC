/**
 * CRC AI SmartBible - JavaScript
 * Handles streaming responses from OpenAI API
 */

(function() {
  'use strict';

  // DOM Elements
  const form = document.getElementById('sbForm');
  const input = document.getElementById('q_input');
  const btn = document.getElementById('askBtn');
  const box = document.getElementById('answerBox');
  const content = document.getElementById('answerContent');
  const placeholder = document.getElementById('placeholder');
  const loadingIndicator = document.getElementById('loadingIndicator');
  const exampleCards = document.querySelectorAll('.example-card');

  let eventSource = null;
  let rawText = '';

  // ===== UTILITY FUNCTIONS =====

  function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatAnswer(text) {
    // Convert raw text to formatted HTML
    let html = escapeHTML(text);

    // Convert markdown-style bold (**text**)
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Convert markdown-style italic (*text*)
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');

    // Convert emoji headers (📖, 📚, 🎯)
    html = html.replace(/(📖|📚|🎯)\s*([A-Z][A-Z\s\/]+)/g, '<h4 class="sb-heading">$1 $2</h4>');

    // Split into paragraphs on double line breaks
    const paragraphs = html.split(/\n\n+/);

    html = paragraphs.map(para => {
      para = para.trim();
      if (!para) return '';

      // Check if it's a Bible verse reference line (contains book name with chapter:verse)
      if (/^[""]?[A-Z1-3]?\s?[A-Za-z]+\s+\d+:\d+/.test(para) || /^\([A-Z1-3]?\s?[A-Za-z]+\s+\d+:\d+/.test(para)) {
        return `<p class="sb-verse">${para}</p>`;
      }

      // Check if it starts with a number followed by period (numbered list)
      if (/^\d+\.\s/.test(para)) {
        return `<p class="sb-paragraph">${para}</p>`;
      }

      // Check if it's a heading (starts with emoji or capital letter and ends with colon)
      if (/^[📖📚🎯✨🙏❤️]/.test(para) || /^[A-Z][A-Z\s\/]+:?\s*$/.test(para)) {
        return `<h4 class="sb-heading">${para}</h4>`;
      }

      // Regular paragraph
      return `<p class="sb-paragraph">${para}</p>`;
    }).join('');

    // Handle single line breaks as <br> within paragraphs
    html = html.replace(/\n/g, '<br>');

    return html;
  }

  function stopStream() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
    btn.disabled = false;
    loadingIndicator.hidden = true;

    // Final format of complete text
    if (rawText) {
      content.innerHTML = formatAnswer(rawText);
    }
  }

  function scrollToAnswer() {
    setTimeout(() => {
      const answerSection = document.querySelector('.answer-section');
      if (answerSection) {
        answerSection.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    }, 100);
  }

  // ===== ASK QUESTION =====

  function askQuestion(question) {
    if (!question || question.trim() === '') return;

    // Stop any existing stream
    if (eventSource) stopStream();

    // Reset
    rawText = '';

    // Update UI
    input.value = question;
    placeholder.hidden = true;
    content.hidden = false;
    content.innerHTML = '';
    btn.disabled = true;
    loadingIndicator.hidden = false;

    // Scroll to answer
    scrollToAnswer();

    // Build SSE URL
    const url = '/ai_smartbible/ai_smartbible.php?stream=1&q=' + encodeURIComponent(question);

    // Create EventSource
    eventSource = new EventSource(url);

    eventSource.onmessage = function(e) {
      const token = e.data;
      if (token) {
        rawText += token;
        // Update display with formatted version
        content.innerHTML = formatAnswer(rawText);
        box.scrollTop = box.scrollHeight;
      }
    };

    eventSource.addEventListener('error', function(e) {
      console.error('SSE Error:', e);
      if (rawText === '') {
        content.innerHTML = '<p class="sb-paragraph" style="color: var(--danger);">An error occurred. Please try again.</p>';
      }
      stopStream();
    });

    eventSource.addEventListener('done', function() {
      stopStream();
    });

    eventSource.onerror = function() {
      stopStream();
    };
  }

  // ===== EVENT LISTENERS =====

  // Form submit
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const question = input.value.trim();
      askQuestion(question);
    });
  }

  // Example cards
  exampleCards.forEach(card => {
    card.addEventListener('click', function() {
      const question = this.getAttribute('data-question');
      askQuestion(question);
    });
  });

  // Focus input on load
  if (input) {
    input.focus();
  }

  // ===== CLEANUP =====

  window.addEventListener('beforeunload', function() {
    stopStream();
  });

})();
