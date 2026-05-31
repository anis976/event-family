let recaptchaLoader = null;

function loadRecaptcha(siteKey) {
    if (window.grecaptcha?.execute) {
        return Promise.resolve();
    }

    if (null !== recaptchaLoader) {
        return recaptchaLoader;
    }

    recaptchaLoader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('recaptcha_load_failed'));
        document.head.appendChild(script);
    });

    return recaptchaLoader;
}

export function initContactForm() {
    const form = document.querySelector('[data-ef-contact-form]');

    if (!form || form.dataset.efContactBound === '1') {
        return;
    }

    const siteKey = (form.dataset.recaptchaSiteKey ?? '').trim();
    const tokenInput = form.querySelector('.js-contact-recaptcha-token');

    if (!siteKey || !tokenInput) {
        return;
    }

    form.dataset.efContactBound = '1';

    form.addEventListener('submit', (event) => {
        if (form.dataset.recaptchaPending === '1') {
            event.preventDefault();

            return;
        }

        if ('' !== tokenInput.value.trim()) {
            return;
        }

        event.preventDefault();
        form.dataset.recaptchaPending = '1';

        loadRecaptcha(siteKey)
            .then(
                () => new Promise((resolve) => {
                    window.grecaptcha.ready(resolve);
                }),
            )
            .then(() => window.grecaptcha.execute(siteKey, { action: 'contact' }))
            .then((token) => {
                tokenInput.value = token;
                form.dataset.recaptchaPending = '0';
                form.requestSubmit();
            })
            .catch(() => {
                form.dataset.recaptchaPending = '0';
                window.alert('Vérification anti-spam indisponible. Recharge la page et réessaie.');
            });
    });
}
