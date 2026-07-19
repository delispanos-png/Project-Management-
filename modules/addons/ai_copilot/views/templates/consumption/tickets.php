<?php
/**
 * Recent Tickets Template
 *
 * Displays recently AI-resolved tickets summary with search and filters.
 *
 * @var array $tickets Recent ticket data
 * @var int $totalCount Total number of tickets
 * @var array|null $filterData Current filter values (optional)
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

use WHMCS\Module\Addon\AiCopilot\Constants\URLConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\ModuleConstants;
use WHMCS\Module\Addon\AiCopilot\Constants\TranslationKeys;
use WHMCS\Module\Addon\AiCopilot\Service\TranslationService;

$translator = TranslationService::getInstance();
$hasFilters = isset($filterData);
$searchValue = $hasFilters ? ($filterData['search'] ?? '') : '';
$statusValue = $hasFilters ? ($filterData['status'] ?? '') : '';
?>

<div class="recent-tickets-section">
    <h2><?= $translator->get(TranslationKeys::RECENT_TICKETS_TITLE) ?></h2>

    <?php if ($hasFilters): ?>
        <!-- Quick Filters -->
        <form method="get" action="<?= URLConstants::buildAddonUrl(ModuleConstants::ACTION_CONSUMPTION_CENTER) ?>" class="recent-tickets-filters">
            <input type="hidden" name="module" value="<?= ModuleConstants::MODULE_NAME ?>">
            <input type="hidden" name="action" value="<?= ModuleConstants::ACTION_CONSUMPTION_CENTER ?>">

            <div class="filter-row">
                <div class="filter-group">
                    <label for="ticket_search" class="sr-only">Search Tickets</label>
                    <input type="text"
                           id="ticket_search"
                           name="ticket_search"
                           value="<?= htmlspecialchars($searchValue) ?>"
                           class="form-control"
                           placeholder="<?= $translator->get(TranslationKeys::PLACEHOLDER_SEARCH) ?>"
                           style="width: 250px;">
                </div>

                <div class="filter-group">
                    <label for="ticket_status" class="sr-only">Filter by Status</label>
                    <select id="ticket_status" name="ticket_status" class="form-control" style="width: 150px;">
                        <option value=""><?= \AdminLang::trans(TranslationKeys::GLOBAL_ANY) ?></option>
                        <option value="Open"<?= $statusValue === 'Open' ? ' selected' : '' ?>>Open</option>
                        <option value="Answered"<?= $statusValue === 'Answered' ? ' selected' : '' ?>>Answered</option>
                        <option value="Customer-Reply"<?= $statusValue === 'Customer-Reply' ? ' selected' : '' ?>>Customer-Reply</option>
                        <option value="On Hold"<?= $statusValue === 'On Hold' ? ' selected' : '' ?>>On Hold</option>
                        <option value="In Progress"<?= $statusValue === 'In Progress' ? ' selected' : '' ?>>In Progress</option>
                        <option value="Closed"<?= $statusValue === 'Closed' ? ' selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?= URLConstants::buildAddonUrl(ModuleConstants::ACTION_CONSUMPTION_CENTER) ?>" class="btn btn-default btn-sm"><?= $translator->get(TranslationKeys::BTN_RESET) ?></a>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($totalCount > 0) : ?>
        <div class="tickets-summary">
            <p class="summary-text">
                <?= $translator->trans(TranslationKeys::RECENT_TICKETS_COUNT, $totalCount) ?>
            </p>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= $translator->get(TranslationKeys::TABLE_TICKET_NUMBER) ?></th>
                        <th><?= $translator->get(TranslationKeys::TABLE_SUBJECT) ?></th>
                        <th><?= $translator->get(TranslationKeys::TABLE_CLIENT) ?></th>
                        <th><?= $translator->get(TranslationKeys::TABLE_AI_CHARGED_AT) ?></th>
                        <th><?= $translator->get(TranslationKeys::TABLE_STATUS) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket) : ?>
                        <tr>
                            <td>
                                <a href="<?= URLConstants::buildTicketUrl($ticket->id) ?>">
                                    <?= htmlspecialchars($ticket->tid) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($ticket->title ?? '-') ?></td>
                            <td><?= htmlspecialchars($ticket->client_name ?? '-') ?></td>
                            <td><?= htmlspecialchars($ticket->ai_token_charged_at ?? '-') ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $ticket->status ?? 'default'))) ?>">
                                    <?= htmlspecialchars($ticket->status ?? '-') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="view-all-link">
                <a href="<?= URLConstants::buildAddonUrl(ModuleConstants::ACTION_CONSUMPTION) ?>" class="btn btn-primary">
                    <?= $translator->get(TranslationKeys::BTN_VIEW_ALL_TICKETS) ?>
                </a>
            </div>
        </div>
    <?php else : ?>
        <div class="no-tickets-message">
            <p><?= $translator->get(TranslationKeys::NO_AI_TICKETS_FOUND) ?></p>
        </div>
    <?php endif; ?>
</div>

