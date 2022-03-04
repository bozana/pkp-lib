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
use APP\core\Services;
use APP\Jobs\Statistics\LoadMetricsDataJob;
use APP\Jobs\Statistics\LoadMonthlyMetricsDataJob;
use APP\statistics\StatisticsHelper;
use PKP\core\Core;
use PKP\file\FileManager;
use PKP\scheduledTask\ScheduledTaskHelper;

abstract class PKPUsageStatsLoader extends FileLoader
{
    /** @var string $_autoStage */
    public $_autoStage;
    public array $months = [];

    /**
     * Constructor.
     */
    public function __construct($args)
    {
        $this->_autoStage = true;

        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ PKPUsageStatsLoader args ++++\n", true);
        $current .= print_r($args, true);
        file_put_contents($file, $current);
        if (!empty($args)) {
            $reprocessMonth = current($args);
            $reprocessFiles = $this->getStagedFilesByMonth($reprocessMonth);
            $file = 'debug.txt';
            $current = file_get_contents($file);
            $current .= print_r("++++ reprocessFiles ++++\n", true);
            $current .= print_r($reprocessFiles, true);
            file_put_contents($file, $current);
            $this->setOnlyConsiderFiles($reprocessFiles);
            $this->_autoStage = false;
        }

        $site = Application::get()->getRequest()->getSite();
        if ($site->getData('archivedUsageStatsLogFiles') == 1) {
            $this->setCompressArchives(true);
        }

        // Define the base filesystem path.
        $basePath = StatisticsHelper::getUsageStatsDirPath();
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
        $processFilesResult = parent::executeActions();
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ months ++++\n", true);
        $current .= print_r($this->months, true);
        file_put_contents($file, $current);
        foreach ($this->months as $month) {
            dispatch(new LoadMonthlyMetricsDataJob($month));
        }
        return ($processFilesResult && !$processingDirError);
    }

    abstract protected function deleteByLoadId(string $loadId);
    abstract protected function insertTemporaryUsageStatsData(\stdClass $entry, int $lineNumber, string $loadId);
    abstract protected function checkForeignKeys(\stdClass $entry): array;
    abstract protected function isLogEntryValid(\stdClass $entry);

    /**
     * @copydoc FileLoader::processFile()
     * The file's entries MUST be ordered by date-time to successfully identify double-clicks and unique items.
     */
    protected function processFile(string $filePath)
    {
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ processFile filePath ++++\n", true);
        $current .= print_r($filePath, true);
        file_put_contents($file, $current);

        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            // TO-DO: move plugins.generic.usageStats.openFileFailed to usageStats.openFileFailed
            throw new \Exception(__('usageStats.openFileFailed', ['file' => $filePath]));
        }

        $loadId = basename($filePath);
        $logFileDate = substr($loadId, -12, 8);
        $month = substr($loadId, -12, 6);
        $currentMonth = date('Ym');

        $statsService = Services::get('sushiStats');
        $dateR5Installed = date('Ymd', strtotime($statsService->getEarliestDate()));
        $dateR5Installed = '20000101';
        if ($logFileDate < $dateR5Installed) {
            // the log file is in old log file format
            // return the file to staging and
            // log the error
            // TO-DO: once we decided how the log files in the old format should be reprocessed, this might change
            $this->addExecutionLogEntry(__(
                'usageStats.logfileProcessing.veryOldLogFile',
                ['file' => $filePath]
            ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return self::FILE_LOADER_RETURN_TO_STAGING;
        }

        $site = Application::get()->getRequest()->getSite();
        // if the daily metrics are not kept, and this is not the current month (which is kept in the DB)
        if (!$site->getData('usageStatsKeepDaily') && $month != $currentMonth) {
            // check if the month is already processed
            // currently only the table metrics_counter_submission_monthly will be considered
            // TO-DO: once we decided how the log files in the old format should be reprocessed
            // this should eventually be adapted, because the metrics_submission_geo_monthly could contain also earlier months
            $monthExists = $statsService->monthExists($month);
            if ($monthExists) {
                // the month is already processed
                // return the file to staging and
                // log the error that a script for reprocessing should be called for the whole month
                $this->addExecutionLogEntry(__(
                    'usageStats.logfileProcessing.monthProcessed',
                    ['file' => $filePath]
                ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                return self::FILE_LOADER_RETURN_TO_STAGING;
            }
        }

        if (!in_array($month, $this->months)) {
            $this->months[] = $month;
        }

        // Make sure we don't have any temporary records associated
        // with the current load id in database.
        $this->deleteByLoadId($loadId);

        $lineNumber = 0;
        while (!feof($fhandle)) {
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines. Should not occur in the new format.

            // Regex to parse this usageStats plugin's log access files.
            $parseRegex = '/^(?P<ip>\S+) \S+ \S+ "(?P<date>.*?)" (?P<url>\S+) (?P<returnCode>\S+) "(?P<userAgent>.*?)"/';
            if (preg_match($parseRegex, $line, $m)) {
                // This is a line in the old logfile format
                $this->addExecutionLogEntry(__('usageStats.logfileProcessing.oldLogfileFormat', ['loadId' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            } else {
                $entryData = json_decode($line);
            }

            try {
                $this->isLogEntryValid($entryData);
            } catch (\Exception $e) {
                // reject the file ???
                throw new \Exception(__(
                    'usageStats.invalidLogEntry',
                    ['file' => $filePath, 'lineNumber' => $lineNumber, 'error' => $e->getMessage()]
                ));
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData->userAgent)) {
                continue;
            }

            $foreignKeyErrors = $this->checkForeignKeys($entryData);
            if (!empty($foreignKeyErrors)) {
                $missingForeignKeys = implode(', ', $foreignKeyErrors);
                $this->addExecutionLogEntry(__('usageStats.logfileProcessing.foreignKeyError', ['missingForeignKeys' => $missingForeignKeys, 'loadId' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                $file = 'debug.txt';
                $current = file_get_contents($file);
                $current .= print_r("++++ missingForeignKeys ++++\n", true);
                $current .= print_r($missingForeignKeys, true);
                $current .= print_r("++++ loadId ++++\n", true);
                $current .= print_r($loadId, true);
                $current .= print_r("++++ lineNumber ++++\n", true);
                $current .= print_r($lineNumber, true);
                file_put_contents($file, $current);
            } else {
                $this->insertTemporaryUsageStatsData($entryData, $lineNumber, $loadId);
            }
        }
        fclose($fhandle);

        dispatch(new LoadMetricsDataJob($loadId));
        // TO-DO: add locale key:
        $this->addExecutionLogEntry(__(
            'usageStats.loadMetricsData.jobDispatched',
            ['file' => $filePath]
        ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        return true;
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
        if (is_array($logsDirFiles)) {
            $logFiles = array_merge($logFiles, $logsDirFiles);
        }
        // It's possible that the processing directory have files that
        // were being processed but the php process was stopped before
        // finishing the processing. Just copy them to the stage directory too.
        $processingDirFiles = glob($this->getProcessingPath() . DIRECTORY_SEPARATOR . '*');
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
     * Get staged usage log files belonging to a month, that should be reprocessed
     */
    protected function getStagedFilesByMonth(string $month): array
    {
        $filesToConsider = [];
        $stagePath = StatisticsHelper::getUsageStatsDirPath() . DIRECTORY_SEPARATOR . self::FILE_LOADER_PATH_STAGING;
        $stageDir = opendir($stagePath);
        while ($filename = readdir($stageDir)) {
            if (str_starts_with($filename, 'usage_events_BB_' . $month)) {
                $filesToConsider[] = $filename;
            }
        }
        return $filesToConsider;
    }

    /**
     * Get the usage event logs directory path.
     */
    public function getUsageEventLogsPath(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . DIRECTORY_SEPARATOR . 'usageEventLogs';
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
