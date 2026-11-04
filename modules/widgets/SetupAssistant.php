<?php

namespace WHMCS\Module\Widget;

use WHMCS\Admin\Setup\SetupTasks;
use WHMCS\Config\Setting;
use WHMCS\Module\AbstractWidget;

/**
 * Setup Assistant Widget.
 *
 * Displays setup progress and guides administrators through initial WHMCS configuration.
 *
 * @copyright Copyright (c) WHMCS Limited 2005-2026
 * @license https://www.whmcs.com/eula/ WHMCS Eula
 */
class SetupAssistant extends AbstractWidget
{
    protected $title = 'Setup Tasks';
    protected $description = 'Track your WHMCS setup progress and complete the next suggested tasks.';
    protected $columns = 1;
    protected $weight = 15;
    protected $cache = false;
    protected $wrapper = true;
    protected $draggable = true;

    /**
     * Get widget data.
     *
     * @return array
     */
    public function getData()
    {
        $tasks = $this->getSetupTasks();
        $completedCount = 0;
        $totalCount = count($tasks);

        foreach ($tasks as $task) {
            if ($task['completed']) {
                $completedCount++;
            }
        }

        $progressPercentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

        // Determine rating based on progress
        if ($progressPercentage >= 100) {
            $rating = [
                'message' => '<span style="color:#49a94d;">Complete</span>',
                'icon' => '<i class="pe-7s-like2" style="color:#49a94d;"></i>',
            ];
        } else {
            $rating = [
                'message' => '<span style="color:#eaae53;">In Progress</span>',
                'icon' => '<i class="pe-7s-config" style="color:#eaae53;"></i>',
            ];
        }

        return [
            'tasks' => $tasks,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'progressPercentage' => $progressPercentage,
            'rating' => $rating,
        ];
    }

    /**
     * Generate widget content output.
     *
     * @param array $data
     * @return string
     */
    public function generateOutput($data)
    {
        $tasks = $data['tasks'];
        $completedCount = $data['completedCount'];
        $totalCount = $data['totalCount'];
        $progressPercentage = $data['progressPercentage'];
        $rating = $data['rating'];
        $needingAttention = $totalCount - $completedCount;

        // Load external assets
        $widgetDir = __DIR__ . '/setup-assistant';
        $cssFile = $widgetDir . '/styles.css';
        $jsFile = $widgetDir . '/script.js';
        $viewFile = $widgetDir . '/view.php';

        $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';
        $js = file_exists($jsFile) ? file_get_contents($jsFile) : '';

        // Start output buffering for template
        ob_start();
        if (file_exists($viewFile)) {
            include $viewFile;
        }
        $html = ob_get_clean();

        return $html . '<style>' . $css . '</style><script>' . $js . '</script>';
    }

    /**
     * Get setup tasks with completion status.
     *
     * @return array
     */
    protected function getSetupTasks()
    {
        $setupTasks = new SetupTasks();
        $tasks = $setupTasks->evaluateAndGet();

        // Filter out backup task if Cloud policy indicates it should be hidden
        if (!$this->shouldShowBackupTask()) {
            $tasks = array_filter($tasks, function ($task) {
                $link = $task['link'] ?? '';
                return $link !== 'configbackups.php';
            });
            // Re-index array after filtering
            $tasks = array_values($tasks);
        }

        // Add full URL to each task
        foreach ($tasks as &$task) {
            $task['url'] = $task['link'] ?? '#';
        }
        unset($task);

        return $tasks;
    }

    /**
     * Determine if backup configuration task should be shown.
     *
     * Cloud instances with platform-managed backups should not show backup task.
     *
     * @return bool True if backup task should be shown, false if it should be hidden
     */
    protected function shouldShowBackupTask()
    {
        if (class_exists('\WHMCS\Cloud\Policy') &&
            method_exists('\WHMCS\Cloud\Policy', 'isCloudMode') &&
            method_exists('\WHMCS\Cloud\Policy', 'allowBackups')) {
            return !\WHMCS\Cloud\Policy::isCloudMode() || \WHMCS\Cloud\Policy::allowBackups();
        }

        return Setting::getValue('is_cloud') !== 'true';
    }
}
