<?php

/**
 * @file classes/tasks/PKPUsageStatsLoader.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageStatsLoader
 * @ingroup tasks
 *
 * @brief Scheduled task to extract transform and load usage statistics data into database.
 */

namespace PKP\task;

use APP\core\Application;
use PKP\file\FileManager;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTaskHelper;

abstract class PKPUsageStatsLoader extends FileLoader
{
    /** These are rules defined by the COUNTER project.
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#doubleclick
     */
    public const COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS = 30;

    /** @var string $_autoStage */
    public $_autoStage;

    /**
     * Constructor.
     */
    public function __construct($args)
    {
        $site = Application::get()->getRequest()->getSite();
        if ($site->getData('archivedUsageStatsLogFiles') == 1) {
            $this->setCompressArchives(true);
        }
        $this->_autoStage = true;

        // Define the base filesystem path.
        $basePath = $this->getUsageStatsDirPath();
        $args[0] = $basePath;
        parent::__construct($args);

        $this->checkFolderStructure(true);
    }

    /**
     * @copydoc FileLoader::getName()
     */
    public function getName()
    {
        // TO-DO: plugins.generic.usageStats.usageStatsLoaderName -> usageStats.usageStatsLoader
        return __('usageStats.usageStatsLoader');
    }

    /**
    * @copydoc FileLoader::executeActions()
    */
    protected function executeActions()
    {
        // It's possible that the processing directory has files that
        // were being processed but the php process was stopped before
        // finishing the processing, or there may be a concurrent process running.
        // Warn the user if this is the case.
        $processingDirFiles = glob($this->getProcessingPath() . DIRECTORY_SEPARATOR . '*');
        $processingDirError = is_array($processingDirFiles) && count($processingDirFiles);
        if ($processingDirError) {
            $this->addExecutionLogEntry(__('plugins.generic.usageStats.processingPathNotEmpty', ['directory' => $this->getProcessingPath()]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
        }
        if ($this->_autoStage) {
            $this->autoStage();
        }
        return (parent::executeActions() && !$processingDirError);
    }

    /**
     * Auto stage usage stats log files, also moving files that
     * might be in processing folder to stage folder.
     */
    protected function autoStage()
    {
        // Copy all log files to stage directory, except the current day one.
        $fileMgr = new FileManager();
        $logFiles = [];
        $logsDirFiles = glob($this->getUsageEventLogsPath() . DIRECTORY_SEPARATOR . '*');
        // It's possible that the processing directory have files that
        // were being processed but the php process was stopped before
        // finishing the processing. Just copy them to the stage directory too.
        $processingDirFiles = glob($this->getProcessingPath() . DIRECTORY_SEPARATOR . '*');
        if (is_array($logsDirFiles)) {
            $logFiles = array_merge($logFiles, $logsDirFiles);
        }

        if (is_array($processingDirFiles)) {
            $logFiles = array_merge($logFiles, $processingDirFiles);
        }

        foreach ($logFiles as $filePath) {
            // Make sure it's a file.
            if ($fileMgr->fileExists($filePath)) {
                // Avoid current day file.
                $filename = pathinfo($filePath, PATHINFO_BASENAME);
                $currentDayFilename = $this->getUsageEventCurrentDayLogName();
                if ($filename == $currentDayFilename) {
                    continue;
                }
                $this->moveFile(pathinfo($filePath, PATHINFO_DIRNAME), $this->getStagePath(), $filename);
            }
        }
    }

    /**
     * Get the usage stats directory path.
     */
    public function getUsageStatsDirPath(): string
    {
        $fileMgr = new PrivateFileManager();
        return realpath($fileMgr->getBasePath()) . DIRECTORY_SEPARATOR . 'usageStatsBB';
    }

    /**
     * Get the usage event logs directory path.
     */
    public function getUsageEventLogsPath(): string
    {
        return $this->getUsageStatsDirPath() . DIRECTORY_SEPARATOR . 'usageEventLogs';
    }

    /**
     * Get current day usage event log name.
     */
    public function getUsageEventCurrentDayLogName(): string
    {
        return 'usage_events_BB_' . date('Ymd') . '.log';
    }

    /**
     * Validate datetime
     */
    protected function _validateDate(string $datetime, string $format = 'Y-m-d H:i:s'): bool
    {
        $d = \DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) == $datetime;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\PKPUsageStatsLoader', '\PKPUsageStatsLoader');
}
