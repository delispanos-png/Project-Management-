<?php
/**
 * AI Consumption Results Template
 *
 * Renders the results table and pagination for AI-processed tickets.
 * Displays ticket information or a "no results" message.
 *
 * @var array $rows Collection of ticket records
 * @var ListTable $tbl Configured table object with columns and data
 * @var string $paginationHtml Rendered pagination HTML
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\ListTable;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
?>

<?php if (count($rows) > 0) : ?>
    <!-- Results Table -->
    <h2><?= $translator->get(TranslationKeys::RECENT_TICKETS_TITLE) ?></h2>
    <?= $tbl->output() ?>
<?php else : ?>
    <!-- No Results Message -->
    <div class="no-results-message">
        <?= $translator->get(TranslationKeys::NO_RECORDS_FOUND) ?>
    </div>
<?php endif; ?>

