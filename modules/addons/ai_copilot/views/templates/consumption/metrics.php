<?php
/**
 * Consumption Metrics Cards Template
 *
 * Displays metric cards showing AI token consumption statistics.
 *
 * @var array $metrics Metrics data (consumed, planLimit, remaining, dailyAverage, etc.)
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Module\Addon\AiCopilot\Constants\UIConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
?>

<div class="consumption-metrics-grid">
    <!-- Consumed Tokens Card -->
    <div class="metric-card">
        <div class="metric-icon">
            <i class="fas <?= UIConstants::ICON_CHART_LINE ?>"></i>
        </div>
        <div class="metric-content">
            <h3 class="metric-label"><?= $translator->get(TranslationKeys::METRIC_CONSUMED_TOKENS) ?></h3>
            <p class="metric-value"><?= number_format($metrics['consumed']) ?></p>
        </div>
    </div>

    <!-- Available Tokens Card -->
    <div class="metric-card">
        <div class="metric-icon">
            <i class="fas <?= UIConstants::ICON_BATTERY ?>"></i>
        </div>
        <div class="metric-content">
            <h3 class="metric-label"><?= $translator->get(TranslationKeys::METRIC_REMAINING_TOKENS) ?></h3>
            <p class="metric-value"><?= number_format($metrics['remaining']) ?></p>
        </div>
    </div>

    <!-- Plan Limit Card -->
    <div class="metric-card">
        <div class="metric-icon">
            <i class="fas <?= UIConstants::ICON_INFINITY ?>"></i>
        </div>
        <div class="metric-content">
            <h3 class="metric-label"><?= $translator->get(TranslationKeys::METRIC_PLAN_LIMIT) ?></h3>
            <p class="metric-value"><?= number_format($metrics['planLimit']) ?></p>
        </div>
    </div>

    <!-- Daily Average Card -->
    <div class="metric-card">
        <div class="metric-icon">
            <i class="fas <?= UIConstants::ICON_CALENDAR ?>"></i>
        </div>
        <div class="metric-content">
            <h3 class="metric-label"><?= $translator->get(TranslationKeys::METRIC_DAILY_AVERAGE) ?></h3>
            <p class="metric-value"><?= number_format($metrics['dailyAverage'], 2) ?></p>
        </div>
    </div>
</div>

<!-- Billing Period Info -->
<div class="billing-period-info">
    <div class="period-item">
        <strong><?= $translator->get(TranslationKeys::BILLING_CURRENT_PERIOD_START) ?>:</strong>
        <span><?= htmlspecialchars($metrics['latestRenewalDate']) ?></span>
    </div>
    <div class="period-item">
        <strong><?= $translator->get(TranslationKeys::BILLING_NEXT_RENEWAL) ?>:</strong>
        <span><?= htmlspecialchars($metrics['nextRenewalDate']) ?></span>
    </div>
</div>

