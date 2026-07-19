// Learn More button tracking
$(document).on('click', '[id^="btnLearnMore-"]', function () {
    if (!isMixpanelEnabled()) return;

    const serviceSlug = this.id.replace('btnLearnMore-', '');
    const serviceName = $(`#mpItem${serviceSlug}`).find('h3').text().trim() || serviceSlug;

    trackAppModalEvent('page', `${serviceName} - Learn More`, {
        service: serviceName
    });
});

// Tabs tracking
$(document).on('shown.bs.tab', '.modal-mc-service a[data-toggle="tab"]', function (e) {
    if (!isMixpanelEnabled()) return;

    const $tab = $(e.target);
    const tabId = $tab.attr('href').replace('#', '');
    const tabLabel = $tab.text().trim();
    const serviceName = $('.modal-mc-service .title h3').text().trim();

    const allowedSimpleTabs = ['faq', 'pricing', 'lite'];
    const advancedTabMap = {
        'activate-advanced-products': 'Products',
        'activate-advanced-promos': 'Promotions',
        'activate-advanced-finish': 'Finish'
    };

    if (allowedSimpleTabs.includes(tabId)) {
        trackAppModalEvent('page', `${serviceName} - ${tabLabel}`, {
            tab: tabId,
            service: serviceName
        });
        return;
    }

    if (advancedTabMap[tabId]) {
        trackAppModalEvent('page', `${serviceName} - Activate - ${advancedTabMap[tabId]}`, {
            tab: tabId,
            service: serviceName
        });
    }
});

// Close modal tracking
$(document).on('hidden.bs.modal', '.modal', function () {
    if (!isMixpanelEnabled()) return;

    trackAppModalEvent('page', 'MarketConnect - Listings', {});
});

// Activate/Deactivate button tracking
$(document).on('click', '.btn-activate, .btn-deactivate', function () {
    if (!isMixpanelEnabled()) return;

    const isActivate = $(this).hasClass('btn-activate');
    const eventType = isActivate ? 'Activated' : 'Deactivated';

    const serviceName = $('.modal-mc-service .title h3').text().trim();

    trackAppModalEvent('event', `${serviceName} - ${eventType}`, {
        service: serviceName
    });
});

// Advanced/Simple Setup Mode tracking
$(document).on('click', '.do-advanced-setup-mode, .do-simple-setup-mode', function () {
    if (!isMixpanelEnabled()) return;

    const isAdvanced = $(this).hasClass('do-advanced-setup-mode');
    const mode = isAdvanced ? 'Activate - Advanced' : 'Activate - Simple';

    const serviceName = $('[data-service]').first().data('service');

    trackAppModalEvent('page', `${serviceName} - ${mode}`, {
        service: serviceName,
        mode: isAdvanced ? 'advanced' : 'simple',
    });
});
