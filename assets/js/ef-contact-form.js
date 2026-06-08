let recaptchaLoader = null;

function loadRecaptcha(siteKey) {
    if (typeof window.grecaptcha?.execute === 'function') {
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
    const tokenInput = form.querySelector('input.js-contact-recaptcha-token');

    if (!siteKey || !tokenInput) {
        return;
    }

    form.dataset.efContactBound = '1';

    form.addEventListener('submit', (event) => {
        if (form.dataset.recaptchaSkip === '1') {
            form.dataset.recaptchaSkip = '0';

            return;
        }

        if (form.dataset.recaptchaPending === '1') {
            event.preventDefault();

            return;
        }

        event.preventDefault();
        form.dataset.recaptchaPending = '1';
        tokenInput.value = '';

        loadRecaptcha(siteKey)
            .then(
                () => new Promise((resolve) => {
                    window.grecaptcha.ready(resolve);
                }),
            )
            .then(() => {
                if (typeof window.grecaptcha?.execute !== 'function') {
                    throw new Error('recaptcha_v3_required');
                }

                return window.grecaptcha.execute(siteKey, { action: 'contact' });
            })
            .then((token) => {
                tokenInput.value = token;
                tokenInput.setAttribute('value', token);
                form.dataset.recaptchaPending = '0';
                form.dataset.recaptchaSkip = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            })
            .catch(() => {
                form.dataset.recaptchaPending = '0';
                window.alert(
                    form.dataset.efAlertRecaptchaV3
                        || form.dataset.efAlertRecaptcha
                        || 'Anti-spam verification unavailable.',
                );
            });
    });
}
