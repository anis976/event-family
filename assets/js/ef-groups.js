function initDescriptionCounters(root = document) {
    root.querySelectorAll('.js-input-count').forEach((input) => {
        const counter = input.closest('.mb-3')?.querySelector('.js-counter');

        if (!counter) {
            return;
        }

        const max = Number(input.getAttribute('maxlength') || 500);

        const update = () => {
            counter.textContent = `${input.value.length} / ${max}`;
        };

        if (input._efCountHandler) {
            input.removeEventListener('input', input._efCountHandler);
        }

        input._efCountHandler = update;
        input.addEventListener('input', update);
        update();
    });
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
    initDescriptionCounters();
    initGroupMessageRows();
}

document.addEventListener('turbo:load', initGroupsPage);

if (document.readyState !== 'loading') {
    initGroupsPage();
} else {
    document.addEventListener('DOMContentLoaded', initGroupsPage);
}

export { initDescriptionCounters, initGroupMessageRows, initGroupsPage };
