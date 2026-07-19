<?php
/**
 * Error Message Template
 *
 * Displays error message when billing stats cannot be loaded.
 *
 * @var string $errorMessage Error message to display
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Module\Addon\AiCopilot\Constants\UIConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
?>

<div class="alert alert-danger consumption-error">
    <i class="fas <?= UIConstants::ICON_EXCLAMATION_TRIANGLE ?>"></i>
    <div class="error-content">
        <h4><?= $translator->get(TranslationKeys::UNABLE_TO_LOAD_DATA) ?></h4>
        <p><?= htmlspecialchars($errorMessage) ?></p>
        <p class="error-help">
            <?= $translator->get(TranslationKeys::CHECK_LICENSE_CONFIG) ?>
        </p>
    </div>
</div>

