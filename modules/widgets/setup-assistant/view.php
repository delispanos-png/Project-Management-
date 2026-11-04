<?php
/**
 * Setup Assistant Widget Template
 * 
 * Available variables:
 * @var array $tasks List of setup tasks with completion status
 * @var int $completedCount Number of completed tasks
 * @var int $totalCount Total number of tasks
 * @var int $progressPercentage Completion percentage (0-100)
 * @var int $needingAttention Number of tasks needing attention
 * @var array $rating Rating info with 'message' and 'icon' keys
 */
?>
<div class="widget-content-padded icon-stats">
    <div class="row">
        <div class="col-sm-7">
            <div class="item">
                <div class="icon-holder text-center">
                    <?= $rating['icon'] ?>
                </div>
                <div class="data">
                    <div class="note">
                        Setup Progress
                    </div>
                    <div class="number">
                        <?= $rating['message'] ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-5 text-right">
            <a href="#" class="btn btn-default btn-sm" onclick="setupPanelTrigger = this; toggleSetupTasksPanel(); setTimeout(function() { document.getElementById('setupTasksPanel').querySelector('.close-btn').focus(); }, 50); return false;">
                <i class="fas fa-arrow-right"></i> View Tasks
            </a>
        </div>
    </div>

    <div style="clear:both;"></div>

    <div class="progress">
        <div class="progress-bar progress-bar-success progress-bar-striped" 
             role="progressbar" 
             aria-valuenow="<?= $progressPercentage ?>" 
             aria-valuemin="0" 
             aria-valuemax="100" 
             style="width: <?= $progressPercentage ?>%">
            <span class="sr-only"><?= $progressPercentage ?>% Complete</span>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6 text-center">
            <i class="fas fa-check"></i> <?= $completedCount ?> Completed
        </div>
        <div class="col-sm-6 text-center text-danger">
            <i class="fas fa-times"></i> <?= $needingAttention ?> Not Started
        </div>
    </div>
</div>

<div id="setupTasksPanel" class="setup-tasks-panel" role="dialog" aria-modal="true" aria-labelledby="setupTasksPanelTitle">
    <div class="panel-overlay"
         role="button"
         tabindex="0"
         aria-label="Close Setup Tasks Widget"
         onclick="toggleSetupTasksPanel()"
         onkeydown="if (event.key === 'Enter' || event.key === ' ' || event.keyCode === 13 || event.keyCode === 32) { toggleSetupTasksPanel(); }"
    ></div>
    <div class="panel-content">
        <div class="panel-header">
            <button type="button" class="close-btn" onclick="toggleSetupTasksPanel()" aria-label="Close Setup Tasks Widget">
                <span>&times;</span>
            </button>
            <h3 id="setupTasksPanelTitle">Setup Tasks</h3>
            <p class="text-muted"><?= (int)$completedCount ?> of <?= (int)$totalCount ?> Completed</p>
        </div>
        <div class="panel-body">
            <ul class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $completed = $task['completed'] ?? false;
                    $label = htmlspecialchars($task['label'] ?? '', ENT_QUOTES, 'UTF-8');
                    $url = htmlspecialchars($task['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                    $statusIcon = $completed
                        ? '<i class="fas fa-check text-success"></i>'
                        : '<i class="fas fa-times text-danger"></i>';
                    $taskClass = $completed ? 'completed' : 'incomplete';
                    ?>
                    <li class="task-item <?= $taskClass ?>">
                        <?php if ($completed): ?>
                            <span class="task-link" aria-disabled="true">
                                <span class="task-status"><?= $statusIcon ?></span>
                                <span class="task-label"><?= $label ?></span>
                            </span>
                        <?php else: ?>
                            <a href="<?= $url ?>" class="task-link">
                                <span class="task-status"><?= $statusIcon ?></span>
                                <span class="task-label"><?= $label ?></span>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="panel-footer">
                <a href="https://go.whmcs.com/2357/setup-tasks" target="_blank" rel="noopener noreferrer">
                    Learn More <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
    </div>
</div>
