<?php
/**
 * Consumption Progress Bar Template
 *
 * Displays usage progress bar with percentage indicator.
 *
 * @var array $metrics Metrics data (percent, progressClass)
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
$percent = is_numeric($metrics['percent'] ?? null) ? (float) $metrics['percent'] : 0.0;
$percentLabel = (string) round($percent);
$usageTotal = $metrics['usageTotal'] ?? ($metrics['planLimit'] ?? 0);
?>

<div class="consumption-progress-section">
    <h2><?= $translator->get(TranslationKeys::METRIC_TOKEN_USAGE) ?></h2>

    <div class="progress-container">
        <div class="progress">
            <div class="progress-bar <?= htmlspecialchars($metrics['progressClass']) ?>"
                 role="progressbar"
                 style="width: <?= htmlspecialchars((string) $percent) ?>%"
                 aria-valuenow="<?= htmlspecialchars((string) $percent) ?>"
                 aria-valuemin="0"
                 aria-valuemax="100">
                <?php if ($percent > 0): ?>
                    <?= htmlspecialchars($percentLabel) ?>%
                <?php endif; ?>
            </div>

            <?php if ($percent <= 0): ?>
                <span class="progress-percent-overlay">0%</span>
            <?php endif; ?>
        </div>

        <div class="progress-legend">
            <span class="progress-label">
                <?= $translator->trans(TranslationKeys::PROGRESS_TOKENS_USED, number_format($metrics['consumed']), number_format((int) $usageTotal)) ?>
            </span>
        </div>
    </div>
</div>

