/**
 * CRC Learning JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize based on page
});

async function enrollCourse(courseId) {
    const formData = new FormData();
    formData.append('action', 'enroll');
    formData.append('course_id', courseId);

    try {
        const response = await fetch('/learning/api/courses.php', {
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
            alert(data.error || 'Failed to enroll');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to enroll');
    }
}

async function markComplete(lessonId) {
    const btn = document.getElementById('complete-btn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }

    const formData = new FormData();
    formData.append('action', 'complete');
    formData.append('lesson_id', lessonId);

    try {
        const response = await fetch('/learning/api/progress.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            // Update UI
            const actions = document.querySelector('.lesson-actions');
            actions.innerHTML = `
                <div class="completed-message">
                    ✓ Completed just now
                </div>
            `;

            // Update sidebar
            const sidebarLesson = document.querySelector(`.sidebar-lesson[href*="id=${lessonId}"]`);
            if (sidebarLesson) {
                sidebarLesson.classList.add('completed');
                if (!sidebarLesson.querySelector('.lesson-check')) {
                    sidebarLesson.insertAdjacentHTML('beforeend', '<span class="lesson-check">✓</span>');
                }
            }

            // Update progress dots
            const progressDots = document.querySelectorAll('.progress-dot');
            let foundActive = false;
            progressDots.forEach(dot => {
                if (dot.classList.contains('active')) {
                    dot.classList.add('completed');
                    dot.classList.remove('active');
                    foundActive = true;
                } else if (foundActive && !dot.classList.contains('completed')) {
                    dot.classList.add('active');
                    foundActive = false;
                }
            });

            // Auto-navigate to next lesson after delay
            if (typeof nextLessonId !== 'undefined' && nextLessonId) {
                setTimeout(() => {
                    if (confirm('Lesson completed! Continue to next lesson?')) {
                        window.location.href = `/learning/lesson.php?id=${nextLessonId}`;
                    }
                }, 1000);
            }
        } else {
            alert(data.error || 'Failed to mark complete');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Mark as Complete';
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to mark complete');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Mark as Complete';
        }
    }
}

// Quiz functionality
function initQuiz() {
    const quizForm = document.getElementById('quiz-form');
    if (!quizForm) return;

    quizForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const answers = {};
        let score = 0;
        let total = 0;

        formData.forEach((value, key) => {
            if (key.startsWith('question_')) {
                const questionId = key.replace('question_', '');
                answers[questionId] = value;
                total++;
            }
        });

        // Check answers (assuming correct answers are in data attributes)
        const questions = document.querySelectorAll('.quiz-question');
        questions.forEach(q => {
            const questionId = q.dataset.id;
            const correct = q.dataset.correct;
            if (answers[questionId] === correct) {
                score++;
            }
        });

        const percentage = Math.round((score / total) * 100);

        // Save quiz results
        const lessonId = document.querySelector('input[name="lesson_id"]').value;
        const saveData = new FormData();
        saveData.append('action', 'save_quiz');
        saveData.append('lesson_id', lessonId);
        saveData.append('score', percentage);

        for (const [key, value] of Object.entries(answers)) {
            saveData.append(`answers[${key}]`, value);
        }

        try {
            const response = await fetch('/learning/api/progress.php', {
                method: 'POST',
                body: saveData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            // Show results
            const resultsDiv = document.getElementById('quiz-results');
            resultsDiv.innerHTML = `
                <div class="quiz-score ${percentage >= 70 ? 'passing' : 'failing'}">
                    <h3>Quiz Complete!</h3>
                    <p class="score">${percentage}%</p>
                    <p>You got ${score} out of ${total} correct</p>
                    ${percentage >= 70
                        ? '<p class="pass-message">Great job! You passed!</p>'
                        : '<p class="fail-message">Review the material and try again.</p>'
                    }
                </div>
            `;
            resultsDiv.style.display = 'block';

        } catch (error) {
            console.error('Error:', error);
        }
    });
}
