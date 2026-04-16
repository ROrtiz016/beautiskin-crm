import Sortable from 'sortablejs';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function applyDashboardOrder(container, order) {
    if (!container || !Array.isArray(order) || order.length === 0) {
        return;
    }

    const items = [...container.querySelectorAll('[data-dashboard-panel]')];
    const byId = Object.fromEntries(
        items.map((el) => [el.getAttribute('data-dashboard-panel'), el]).filter(([id]) => Boolean(id)),
    );

    for (const id of order) {
        const el = byId[id];
        if (el) {
            container.appendChild(el);
        }
    }
}

function initDashboardSortables() {
    document.querySelectorAll('[data-sortable-dashboard]').forEach((container) => {
        const dashboard = container.getAttribute('data-sortable-dashboard');
        const saveUrl = container.getAttribute('data-save-dashboard-url');
        if (!dashboard || !saveUrl) {
            return;
        }

        const orderJson = container.getAttribute('data-initial-order');
        if (orderJson) {
            try {
                const order = JSON.parse(orderJson);
                applyDashboardOrder(container, order);
            } catch {
                /* ignore */
            }
        }

        Sortable.create(container, {
            animation: 180,
            handle: '[data-drag-handle]',
            draggable: '[data-dashboard-panel]',
            ghostClass: 'opacity-50',
            chosenClass: 'sortable-chosen-outline',
            onEnd: () => {
                const order = [...container.querySelectorAll('[data-dashboard-panel]')].map((n) =>
                    n.getAttribute('data-dashboard-panel'),
                );
                fetch(saveUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ dashboard, order }),
                }).catch(() => {
                    /* silent fail; user can retry */
                });
            },
        });
    });
}

document.addEventListener('DOMContentLoaded', initDashboardSortables);
