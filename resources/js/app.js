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

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || ! form.dataset.confirm) {
            return;
        }

        if (! window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
};

const initializeDriverSafetyUi = () => {
    initializeVehicleSummaryPopovers();
    initializeConfirmationForms();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDriverSafetyUi);
} else {
    initializeDriverSafetyUi();
}
