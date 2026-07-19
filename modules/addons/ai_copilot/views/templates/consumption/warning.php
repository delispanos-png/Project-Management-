<?php
/**
 * Consumption Warning Template
 *
 * Displays warning alert when usage is approaching limit.
 *
 * @var float $percent Current usage percentage
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Module\Addon\AiCopilot\Constants\UIConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
$alertClass = $percent >= UIConstants::THRESHOLD_DANGER ? UIConstants::CSS_ALERT_DANGER : UIConstants::CSS_ALERT_WARNING;
$iconClass = $percent >= UIConstants::THRESHOLD_DANGER ? UIConstants::ICON_EXCLAMATION_TRIANGLE : UIConstants::ICON_EXCLAMATION_CIRCLE;
$titleKey = $percent >= UIConstants::THRESHOLD_DANGER ? TranslationKeys::WARNING_CRITICAL_USAGE : TranslationKeys::WARNING_HIGH_USAGE;
$messageKey = $percent >= UIConstants::THRESHOLD_DANGER ? TranslationKeys::WARNING_CONSIDER_UPGRADE : TranslationKeys::WARNING_MONITOR_USAGE;
?>

<div class="alert <?= $alertClass ?> consumption-warning">
    <i class="fas <?= $iconClass ?>"></i>
    <div class="alert-content">
        <h4><?= $translator->get($titleKey) ?></h4>
        <p>
            <?= $translator->trans(TranslationKeys::WARNING_USAGE_MESSAGE, number_format($percent, 2)) ?>
        </p>
        <p>
            <?= $translator->get($messageKey) ?>
        </p>
    </div>
</div>

