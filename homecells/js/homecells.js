/**
 * CRC Homecells JavaScript
 */

async function joinHomecell(homecellId) {
    if (!confirm('Join this homecell?')) return;

    const formData = new FormData();
    formData.append('action', 'join');
    formData.append('homecell_id', homecellId);

    try {
        const response = await fetch('/homecells/api/homecells.php', {
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
            alert(data.error || 'Failed to join homecell');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to join homecell');
    }
}

async function leaveHomecell(homecellId) {
    if (!confirm('Are you sure you want to leave this homecell?')) return;

    const formData = new FormData();
    formData.append('action', 'leave');
    formData.append('homecell_id', homecellId);

    try {
        const response = await fetch('/homecells/api/homecells.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.ok) {
            window.location.href = '/homecells/';
        } else {
            alert(data.error || 'Failed to leave homecell');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to leave homecell');
    }
}
