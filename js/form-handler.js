document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('main-contact-form');
    if (!form) return;

    async function checkAuth() {
        try {
            const res = await fetch(API_BASE + '?action=profile', { method: 'GET' });
            return res.ok;
        } catch(e) { return false; }
    }

    async function sendMessage(formData, formElement) {
        const submitBtn = formElement.querySelector('.submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        submitBtn.disabled = true;
        if (btnText) btnText.classList.add('d-none');
        if (btnLoading) btnLoading.classList.remove('d-none');

        try {
            const res = await fetch(API_BASE + '?action=message&data=' + encodeURIComponent(JSON.stringify(formData)), { method: 'GET' });
            const data = await res.json();
            if (res.ok) {
                alert('Сообщение отправлено!');
                formElement.reset();
            } else {
                alert('Ошибка: ' + (data.error || data.errors?.message || 'Не удалось отправить'));
            }
        } catch(e) {
            alert('Ошибка сети');
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.classList.remove('d-none');
            if (btnLoading) btnLoading.classList.add('d-none');
        }
    }

    function savePendingMessage(data) {
        localStorage.setItem('pending_message', JSON.stringify(data));
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const privacy = form.querySelector('input[name="privacy"]');
        if (privacy && !privacy.checked) {
            alert('Необходимо согласиться с обработкой данных');
            return;
        }
        const subject = form.querySelector('[name="subject"]')?.value || '';
        const message = form.querySelector('[name="message"]')?.value || '';
        const data = { action: 'message', subject, message, privacy: true };

        const isAuth = await checkAuth();
        if (!isAuth) {
            savePendingMessage(data);
            window.location.href = 'fan_login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        await sendMessage(data, form);
    });

    // Обработка отложенных сообщений после авторизации
    (async function processPending() {
        const pending = localStorage.getItem('pending_message');
        if (!pending) return;
        const authRes = await fetch(API_BASE + '?action=profile', { method: 'GET' });
        if (authRes.ok) {
            const data = JSON.parse(pending);
            await fetch(API_BASE + '?action=message&data=' + encodeURIComponent(JSON.stringify(data)), { method: 'GET' });
            localStorage.removeItem('pending_message');
            alert('✅ Ваше сообщение было отправлено автоматически!');
        }
    })();
});