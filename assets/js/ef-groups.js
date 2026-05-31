function bindCharCounter(input, counter) {
    const max = Number(input.getAttribute('maxlength') || counter.dataset.max || 500);

    const update = () => {
        counter.textContent = `${input.value.length} / ${max}`;
    };

    if (input._efCountHandler) {
        input.removeEventListener('input', input._efCountHandler);
    }

    input._efCountHandler = update;
    input.addEventListener('input', update);
    update();
}

function initDescriptionCounters(root = document) {
    root.querySelectorAll('[data-ef-char-count]').forEach((wrap) => {
        const input = wrap.querySelector('.js-input-count');
        const counter = wrap.querySelector('.js-counter');

        if (!input || !counter) {
            return;
        }

        bindCharCounter(input, counter);
    });

    root.querySelectorAll('.js-input-count').forEach((input) => {
        if (input.closest('[data-ef-char-count]')) {
            return;
        }

        const counter = input.closest('.mb-3')?.querySelector('.js-counter');

        if (!counter) {
            return;
        }

        bindCharCounter(input, counter);
    });
}

function handleCharCounterInput(event) {
    const target = event.target;

    if (!(target instanceof HTMLTextAreaElement || target instanceof HTMLInputElement)) {
        return;
    }

    if (!target.classList.contains('js-input-count')) {
        return;
    }

    const wrap = target.closest('[data-ef-char-count]') ?? target.closest('.mb-3');
    const counter = wrap?.querySelector('.js-counter');

    if (!counter) {
        return;
    }

    const max = Number(target.getAttribute('maxlength') || counter.dataset.max || 500);
    counter.textContent = `${target.value.length} / ${max}`;
}

let charCounterDelegationBound = false;

function ensureCharCounterDelegation() {
    if (charCounterDelegationBound) {
        return;
    }

    charCounterDelegationBound = true;
    document.addEventListener('input', handleCharCounterInput);
}

function closeAllMessageRows(exceptId = null) {
    document.querySelectorAll('.ef-GroupShow__message-row').forEach((row) => {
        if (exceptId && row.id === exceptId) {
            return;
        }
        row.classList.add('d-none');
    });
}

function initGroupMessageRows() {
    document.querySelectorAll('[data-ef-toggle-message-row]').forEach((button) => {
        if (button.dataset.efBound === '1') {
            return;
        }
        button.dataset.efBound = '1';

        button.addEventListener('click', () => {
            const rowId = button.getAttribute('data-ef-toggle-message-row');
            const row = rowId ? document.getElementById(rowId) : null;
            if (!row) {
                return;
            }

            const willOpen = row.classList.contains('d-none');
            closeAllMessageRows();
            if (willOpen) {
                row.classList.remove('d-none');
                row.querySelector('input[name="content"]')?.focus();
            }
        });
    });

    document.querySelectorAll('[data-ef-close-message-row]').forEach((button) => {
        if (button.dataset.efBound === '1') {
            return;
        }
        button.dataset.efBound = '1';

        button.addEventListener('click', () => {
            const rowId = button.getAttribute('data-ef-close-message-row');
            document.getElementById(rowId)?.classList.add('d-none');
        });
    });
}

function initGroupsPage() {
    ensureCharCounterDelegation();
    initDescriptionCounters();
    initGroupMessageRows();
}

document.addEventListener('turbo:load', initGroupsPage);

if (document.readyState !== 'loading') {
    initGroupsPage();
} else {
    document.addEventListener('DOMContentLoaded', initGroupsPage);
}

export { initDescriptionCounters, ensureCharCounterDelegation, initGroupMessageRows, initGroupsPage };
