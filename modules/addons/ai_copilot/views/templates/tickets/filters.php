<?php
/**
 * AI Consumption Filters Template
 *
 * Renders the search and filter form for the AI consumption dashboard.
 * Allows filtering by ticket ID, admin, client, status, and date range.
 *
 * @var array $filterData Current filter values
 * @var FilterFormService $filterFormService Service for rendering form options
 * @var Admin $aInt Admin interface helper
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Admin;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Constants\UIConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\ModuleConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\URLConstants;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;
use WHMCS\Module\Addon\AiCopilot\Service\UI\FilterFormService;

$translator = TranslationService::getInstance();
$formAction = URLConstants::buildAddonUrl(ModuleConstants::ACTION_CONSUMPTION);
?>

<form action="<?= $formAction ?>" method="get">
    <!-- Hidden fields to maintain module context -->
    <input type="hidden" name="<?= URLConstants::PARAM_MODULE ?>" value="<?= ModuleConstants::MODULE_NAME ?>">
    <input type="hidden" name="<?= URLConstants::PARAM_ACTION ?>" value="<?= ModuleConstants::ACTION_CONSUMPTION ?>">

    <table class="form form-fixed">
        <!-- Search by Ticket ID or Subject -->
        <tr>
            <td class="fieldlabel">
                <?= \AdminLang::trans(TranslationKeys::SUPPORT_TICKET_ID) ?>
            </td>
            <td class="fieldarea">
                <input type="text"
                       name="search"
                       value="<?= htmlspecialchars($filterData['search']) ?>"
                       class="form-control input-500"
                       placeholder="<?= $translator->get(TranslationKeys::PLACEHOLDER_SEARCH) ?>"
                       aria-label="<?= UIConstants::ARIA_SEARCH ?>">
            </td>
        </tr>

        <!-- Filter by Assigned Admin (only for users with permission) -->
        <?php if ($aInt->hasPermission(ModuleConstants::ADMIN_PERMISSION)): ?>
            <tr>
                <td class="fieldlabel">
                    <?= \AdminLang::trans(TranslationKeys::SUPPORT_ASSIGNED_TO) ?>
                </td>
                <td class="fieldarea">
                    <select name="searchflag"
                            class="form-control select-inline"
                            aria-label="<?= UIConstants::ARIA_FILTER_ADMIN ?>">
                        <?= $filterFormService->renderAdminUsersOptions($filterData['assignedAdmin']) ?>
                    </select>
                </td>
            </tr>
        <?php endif; ?>

        <!-- Filter by Client -->
        <tr>
            <td class="fieldlabel">
                <?= \AdminLang::trans(TranslationKeys::FIELD_CLIENT) ?>
            </td>
            <td class="fieldarea">
                <?= $filterFormService->renderClientDropdown($filterData['client']) ?>
            </td>
        </tr>

        <!-- Filter by Ticket Status -->
        <tr>
            <td class="fieldlabel">
                <?= \AdminLang::trans(TranslationKeys::FIELD_STATUS) ?>
            </td>
            <td class="fieldarea">
                <select name="status"
                        class="form-control select-inline"
                        aria-label="<?= UIConstants::ARIA_FILTER_STATUS ?>">
                    <?= $filterFormService->renderStatusOptions($filterData['status']) ?>
                </select>
            </td>
        </tr>

        <!-- Filter by Date Range -->
        <tr>
            <td class="fieldlabel">
                <?= \AdminLang::trans(TranslationKeys::FIELD_DATE_RANGE) ?>
            </td>
            <td class="fieldarea">
                <div class="form-group date-picker-prepend-icon">
                    <label for="inputOrderDate" class="field-icon">
                        <i class="fal fa-calendar-alt"></i>
                    </label>
                    <input id="inputOrderDate"
                           type="text"
                           name="orderdate"
                           value="<?= htmlspecialchars($filterData['dateRange']) ?>"
                           class="form-control date-picker-search"
                           aria-label="<?= UIConstants::ARIA_SELECT_DATE_RANGE ?>" />
                </div>
            </td>
        </tr>

        <!-- Filter by AI Processing Date Range -->
        <tr>
            <td class="fieldlabel">
                <?= $translator->get(TranslationKeys::AI_DATE_RANGE) ?>
            </td>
            <td class="fieldarea">
                <div class="form-group date-picker-prepend-icon">
                    <label for="inputAiDate" class="field-icon">
                        <i class="fal fa-calendar-alt"></i>
                    </label>
                    <input id="inputAiDate"
                           type="text"
                           name="aidate"
                           value="<?= htmlspecialchars($filterData['aiDateRange'] ?? '') ?>"
                           class="form-control date-picker-search"
                           aria-label="<?= $translator->get(TranslationKeys::AI_DATE_RANGE) ?>" />
                </div>
            </td>
        </tr>
    </table>

    <!-- Form Action Buttons -->
    <div class="btn-container">
        <input type="submit"
               value="<?= \AdminLang::trans(TranslationKeys::GLOBAL_SEARCH_FILTER) ?>"
               class="btn btn-primary" />
        <a href="<?= URLConstants::buildAddonUrl(ModuleConstants::ACTION_CONSUMPTION) ?>"
           class="btn btn-default">
            <?= $translator->get(TranslationKeys::BTN_RESET) ?>
        </a>
    </div>
</form>

