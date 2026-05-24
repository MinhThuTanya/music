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
            // Отправляем данные как GET-параметры (так работает api.php)
            const params = new URLSearchParams();
            params.append('action', 'message');
            params.append('subject', formData.subject);
            params.append('message', formData.message);
            params.append('privacy', '1');
            const url = API_BASE + '?' + params.toString();
            const res = await fetch(url, { method: 'GET' });
            const data = await res.json();
            if (res.ok) {
                alert('Сообщение отправлено!');
                formElement.reset();
            } else {
                let errMsg = 'Ошибка: ';
                if (data.errors) errMsg += Object.values(data.errors).join(', ');
                else if (data.error) errMsg += data.error;
                else errMsg += 'Неизвестная ошибка';
                alert(errMsg);
                console.error('Server response:', data);
            }
        } catch(e) {
            alert('Ошибка сети: ' + e.message);
            console.error(e);
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
        const data = { subject, message };

        const isAuth = await checkAuth();
        if (!isAuth) {
            savePendingMessage(data);
            window.location.href = 'fan_login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        await sendMessage(data, form);
    });

    // Отложенная отправка после авторизации
    (async function processPending() {
        const pending = localStorage.getItem('pending_message');
        if (!pending) return;
        const authRes = await fetch(API_BASE + '?action=profile', { method: 'GET' });
        if (authRes.ok) {
            const data = JSON.parse(pending);
            const params = new URLSearchParams();
            params.append('action', 'message');
            params.append('subject', data.subject);
            params.append('message', data.message);
            params.append('privacy', '1');
            const url = API_BASE + '?' + params.toString();
            const res = await fetch(url, { method: 'GET' });
            if (res.ok) {
                localStorage.removeItem('pending_message');
                alert('✅ Ваше сообщение было отправлено автоматически!');
            } else {
                const errData = await res.json();
                alert('Не удалось отправить отложенное сообщение: ' + (errData.error || 'Ошибка'));
            }
        }
    })();
});