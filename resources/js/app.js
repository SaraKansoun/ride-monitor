const initializeVehicleSummaryPopovers = () => {
    if (window.driverSafetyVehiclePopoversInitialized) {
        return;
    }

    window.driverSafetyVehiclePopoversInitialized = true;

    const floatingPopover = document.createElement('div');
    const supportsPopover = typeof floatingPopover.showPopover === 'function';
    let activeSummary = null;
    let hideTimer = null;

    floatingPopover.className = 'vehicle-summary-floating-popover';
    floatingPopover.setAttribute('role', 'tooltip');
    floatingPopover.setAttribute('data-vehicle-summary-floating', '');
    floatingPopover.hidden = true;

    if (supportsPopover) {
        floatingPopover.setAttribute('popover', 'manual');
    }

    document.body.append(floatingPopover);

    const positionPopover = (summary) => {
        const summaryRect = summary.getBoundingClientRect();
        const viewportPadding = 8;
        const gap = 8;

        floatingPopover.style.minWidth = `${Math.max(summaryRect.width, 160)}px`;
        floatingPopover.style.maxWidth = `${Math.min(window.innerWidth - viewportPadding * 2, 320)}px`;

        const popoverRect = floatingPopover.getBoundingClientRect();
        const left = Math.min(
            Math.max(summaryRect.left, viewportPadding),
            window.innerWidth - popoverRect.width - viewportPadding,
        );
        const preferredTop = summaryRect.bottom + gap;
        const flippedTop = summaryRect.top - popoverRect.height - gap;
        const top = preferredTop + popoverRect.height + viewportPadding > window.innerHeight && flippedTop > viewportPadding
            ? flippedTop
            : Math.min(preferredTop, window.innerHeight - popoverRect.height - viewportPadding);

        floatingPopover.style.left = `${left}px`;
        floatingPopover.style.top = `${Math.max(top, viewportPadding)}px`;
    };

    const showPopover = (summary) => {
        const sourcePopover = summary.querySelector('[data-vehicle-summary-popover]');

        if (! sourcePopover) {
            return;
        }

        window.clearTimeout(hideTimer);
        activeSummary = summary;
        floatingPopover.innerHTML = sourcePopover.innerHTML;
        floatingPopover.hidden = false;

        if (supportsPopover && ! floatingPopover.matches(':popover-open')) {
            floatingPopover.showPopover();
        }

        positionPopover(summary);
    };

    const hidePopover = () => {
        activeSummary = null;

        if (supportsPopover && floatingPopover.matches(':popover-open')) {
            floatingPopover.hidePopover();
        }

        floatingPopover.hidden = true;
    };

    const scheduleHide = () => {
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(hidePopover, 100);
    };

    document.querySelectorAll('[data-vehicle-summary]').forEach((summary) => {
        summary.addEventListener('mouseenter', () => showPopover(summary));
        summary.addEventListener('focus', () => showPopover(summary));
        summary.addEventListener('mouseleave', scheduleHide);
        summary.addEventListener('blur', scheduleHide);
    });

    floatingPopover.addEventListener('mouseenter', () => window.clearTimeout(hideTimer));
    floatingPopover.addEventListener('mouseleave', scheduleHide);

    window.addEventListener('resize', () => {
        if (activeSummary) {
            positionPopover(activeSummary);
        }
    });

    window.addEventListener('scroll', () => {
        if (activeSummary) {
            positionPopover(activeSummary);
        }
    }, true);
};

const initializeConfirmationForms = () => {
    if (window.driverSafetyConfirmationsInitialized) {
        return;
    }

    window.driverSafetyConfirmationsInitialized = true;
    let pendingForm = null;

    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'confirm-modal-title');
    modal.innerHTML = `
        <div class="confirm-card">
            <h2 id="confirm-modal-title">Confirm action</h2>
            <p data-confirm-message></p>
            <div class="confirm-actions">
                <button class="app-button app-button-muted" type="button" data-confirm-cancel>Cancel</button>
                <button class="app-button app-button-primary" type="button" data-confirm-accept>Continue</button>
            </div>
        </div>
    `;

    document.body.append(modal);

    const message = modal.querySelector('[data-confirm-message]');
    const cancelButton = modal.querySelector('[data-confirm-cancel]');
    const acceptButton = modal.querySelector('[data-confirm-accept]');

    const closeModal = () => {
        modal.classList.remove('is-open');
        pendingForm = null;
    };

    const openModal = (form) => {
        pendingForm = form;
        message.textContent = form.dataset.confirm || 'Confirm this action?';
        modal.classList.add('is-open');
        acceptButton.focus();
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || ! form.dataset.confirm) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();
        openModal(form);
    });

    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    acceptButton.addEventListener('click', () => {
        if (! pendingForm) {
            return;
        }

        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
};

const initializeSidebar = () => {
    const shell = document.querySelector('[data-app-shell]');

    if (! shell || window.driverSafetySidebarInitialized) {
        return;
    }

    window.driverSafetySidebarInitialized = true;

    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeControls = document.querySelectorAll('[data-sidebar-close]');

    const setOpen = (isOpen) => {
        shell.classList.toggle('is-sidebar-open', isOpen);
        toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    toggle?.addEventListener('click', () => {
        setOpen(! shell.classList.contains('is-sidebar-open'));
    });

    closeControls.forEach((control) => {
        control.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
};

const initializeUserMenu = () => {
    const menu = document.querySelector('[data-user-menu]');

    if (! menu || window.driverSafetyUserMenuInitialized) {
        return;
    }

    window.driverSafetyUserMenuInitialized = true;

    const button = menu.querySelector('[data-user-menu-button]');
    const panel = menu.querySelector('[data-user-menu-panel]');

    const setOpen = (isOpen) => {
        panel.hidden = ! isOpen;
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    button.addEventListener('click', () => {
        setOpen(panel.hidden);
    });

    document.addEventListener('click', (event) => {
        if (! menu.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
};

const initializeAutoFilters = () => {
    if (window.driverSafetyAutoFiltersInitialized) {
        return;
    }

    window.driverSafetyAutoFiltersInitialized = true;

    document.querySelectorAll('[data-auto-filter]').forEach((form) => {
        if (! (form instanceof HTMLFormElement)) {
            return;
        }

        let debounceTimer = null;
        let isSubmitting = false;

        const removePageFromRequest = () => {
            const actionUrl = new URL(form.action || window.location.href, window.location.href);
            actionUrl.searchParams.delete('page');
            form.action = actionUrl.toString();

            form.querySelectorAll('[name="page"]').forEach((field) => {
                field.remove();
            });
        };

        const submitForm = () => {
            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            window.clearTimeout(debounceTimer);
            removePageFromRequest();
            form.requestSubmit();
        };

        form.querySelectorAll('input[type="search"]').forEach((input) => {
            input.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(submitForm, 450);
            });
        });

        form.querySelectorAll('select, input[type="date"]').forEach((control) => {
            control.addEventListener('change', submitForm);
        });

        form.addEventListener('submit', () => {
            isSubmitting = true;
            window.clearTimeout(debounceTimer);
            removePageFromRequest();
        });
    });
};

const initializeDriverSafetyUi = () => {
    initializeVehicleSummaryPopovers();
    initializeSidebar();
    initializeUserMenu();
    initializeConfirmationForms();
    initializeAutoFilters();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDriverSafetyUi);
} else {
    initializeDriverSafetyUi();
}
