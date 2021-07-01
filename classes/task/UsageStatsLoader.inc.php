<?php

/**
 * @file classes/tasks/UsageStatsLoader.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsLoader
 * @ingroup tasks
 *
 * @brief Scheduled task to extract transform and load usage statistics data into database.
 */

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use PKP\core\Core;

use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\task\FileLoader;

class UsageStatsLoader extends FileLoader
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
        $this->setCompressArchives(true);
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
        // TO-DO: plugins.generic.usageStats.usageStatsLoaderName -> usageStats.usageStatsLoaderName
        return __('usageStats.usageStatsLoaderName');
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
     * @copydoc FileLoader::processFile()
     * The file's entries MUST be ordered by date-time to successfully identify double-clicks and unique items.
     */
    protected function processFile(string $filePath)
    {
        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            // TO-DO: move plugins.generic.usageStats.openFileFailed to usageStats.openFileFailed
            throw new \Exception(__('usageStats.openFileFailed', ['file' => $filePath]));
        }

        $loadId = basename($filePath);

        $statsTotalDao = DAORegistry::getDAO('UsageStatsTotalTemporaryRecordDAO'); /* @var $statsTotalDao UsageStatsTotalTemporaryRecordDAO */
        $statsUniqueDao = DAORegistry::getDAO('UsageStatsUniqueTemporaryRecordDAO'); /* @var $statsUniqueDao UsageStatsUniqueTemporaryRecordDAO */
        // Make sure we don't have any temporary records associated
        // with the current load id in database.
        $statsTotalDao->deleteByLoadId($loadId);
        $statsUniqueDao->deleteByLoadId($loadId);

        $lastInsertedUniqueEntries = $lastInsertedTotalEntries = [];
        $lineNumber = 0;

        while (!feof($fhandle)) {
            $considerUniqueItem = false;
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines.

            $entryData = json_decode($line, true);

            if (!$this->_isLogEntryValid($entryData)) {
                // TO-DO: move plugins.generic.usageStats.invalidLogEntry to usageStats.invalidLogEntry
                throw new \Exception(__(
                    'plugins.generic.usageStats.invalidLogEntry',
                    ['file' => $filePath, 'lineNumber' => $lineNumber]
                ));
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData['userAgent'])) {
                continue;
            }

            //$entryData['canonicalUrl'] = urldecode($entryData['canonicalUrl']); // this should not be necessary?
            $userIdentification = $entryData['ip'] . $entryData['userAgent'];
            $day = date('Y-m-d', strtotime($entryData['time']));
            $hour = date('H', strtotime($entryData['time']));

            if (!empty($entryData['submissionId'])) {
                $considerUniqueItem = true;
            }

            // Consider double click filtering.
            $totalEntryHash = $entryData['canonicalUrl'] . $userIdentification;
            $totalTimeFilter = self::COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS;
            // Because the entries are ordered by date-time, we can
            // clean the last inserted total entries, removing the entries that have
            // no importance for the time between requests check i.e.
            // entries older than the time defined in the double click time filter.
            // Those are stored in the temporary DB table (s. below).
            foreach ($lastInsertedTotalEntries as $hash => $hashTimeLineNoArray) {
                if ($hashTimeLineNoArray['time'] + $totalTimeFilter < strtotime($entryData['time'])) {
                    unset($lastInsertedTotalEntries[$hash]);
                }
            }

            // Remove double clicks
            if (isset($lastInsertedTotalEntries[$totalEntryHash])) {
                $secondsBetweenRequests = strtotime($entryData['time']) - $lastInsertedTotalEntries[$totalEntryHash]['time'];
                if ($secondsBetweenRequests < $totalTimeFilter) {
                    $dateTimeToDelete = date('Y-m-d H:i:s', $lastInsertedTotalEntries[$totalEntryHash]['time']);
                    // We have to store the current entry, so we delete the last one.
                    $statsTotalDao->deleteRecord($entryData['canonicalUrl'], $lastInsertedTotalEntries[$totalEntryHash]['lineNumber'], $dateTimeToDelete, $loadId);
                }
            }

            $lastInsertedTotalEntries[$totalEntryHash]['time'] = strtotime($entryData['time']);
            $lastInsertedTotalEntries[$totalEntryHash]['lineNumber'] = $lineNumber;
            $statsTotalDao->insert($entryData, $lineNumber, $loadId);

            if ($considerUniqueItem) {
                // Consider unique item filtering
                // There fore the day is sliced into 24 hour pieces.
                $uniqueEntryHash = $entryData['contextId'] . $entryData['submissionId'] . $userIdentification . $day;
                // Because the entries are ordered by date-time, we can
                // clean the last inserted unique entries, removing the entries that have
                // no importance for the time between requests check i.e.
                // entries older than the current hour.
                // Those are stored in the temporary DB table (s. below).
                foreach ($lastInsertedUniqueEntries as $hash => $hashHourArray) {
                    foreach ($hashHourArray as $hashHour => $hashTimeLineNoArray) {
                        if ($hashHour < $hour) {
                            unset($lastInsertedUniqueEntries[$hash][$hashHour]);
                        }
                    }
                }
                // Remove unique clicks
                if (isset($lastInsertedUniqueEntries[$uniqueEntryHash][$hour])) {
                    $dateTimeToDelete = date('Y-m-d H:i:s', $lastInsertedUniqueEntries[$uniqueEntryHash][$hour]['time']);
                    // We store the current entry, so we delete the last one.
                    $statsUniqueDao->deleteRecord($entryData['contextId'], $entryData['submissionId'], $lastInsertedUniqueEntries[$uniqueEntryHash][$hour]['lineNumber'], $dateTimeToDelete, $loadId);
                }
                $lastInsertedUniqueEntries[$uniqueEntryHash][$hour]['time'] = strtotime($entryData['time']);
                $lastInsertedUniqueEntries[$uniqueEntryHash][$hour]['lineNumber'] = $lineNumber;
                $statsUniqueDao->insert($entryData, $lineNumber, $loadId);
            }
        }

        fclose($fhandle);
        /*
        $loadResult = $this->_loadData($loadId);
        $statsTotalDao->deleteByLoadId($loadId);
        if ($considerUniqueItem) {
            $statsUniqueDao->deleteByLoadId($loadId);
        }

        if (!$loadResult) {
            // TO-DO: move plugins.generic.usageStats.loadDataError to usageStats.loadDataError
            $this->addExecutionLogEntry(__('usageStats.loadDataError',
                array('file' => $filePath)), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return FileLoader::FILE_LOADER_RETURN_TO_STAGING;
        } else {
            return true;
        }
        */
        return true;
    }

    /**
     * Load the entries inside the temporary database associated with
     * the passed load id to the metrics tables.
     */
    private function _loadData(string $loadId): bool
    {
        $statsTotalDao = DAORegistry::getDAO('UsageStatsTotalTemporaryRecordDAO'); /* @var $statsTotalDao UsageStatsTotalTemporaryRecordDAO */
        $statsUniqueDao = DAORegistry::getDAO('UsageStatsUniqueTemporaryRecordDAO'); /* @var $statsUniqueDao UsageStatsUniqueTemporaryRecordDAO */

        /*
        $metricsDao = DAORegistry::getDAO('MetricsDAO'); / @var $metricsDao PKPMetricsDAO /
        $metricsDao->purgeLoadBatch($loadId);

        $records = $statsDao->getByLoadId($loadId);
        foreach ($records as $record) {
            $record = (array) $record;
            $record['metric_type'] = $this->getMetricType();
            $metricsDao->insertRecord($record);
        }
        */

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
     * Validate an usage log entry.
     */
    private function _isLogEntryValid(array $entry): bool
    {
        if (empty($entry)) {
            return false;
        }

        if (!$this->_validateDate($entry['time'])) {
            return false;
        }
        // check hashed IP ?
        // check canonicalUrl ?
        if (!is_numeric($entry['contextId'])) {
            return false;
        }
        if (!empty($entry['issueId']) && !is_numeric($entry['issueId'])) {
            return false;
        }
        if (!empty($entry['submissionId']) && !is_numeric($entry['submissionId'])) {
            return false;
        }
        if (!empty($entry['representationId']) && !is_numeric($entry['representationId'])) {
            return false;
        }
        $validAssocTypes = [
            Application::ASSOC_TYPE_SUBMISSION_FILE,
            Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER,
            Application::ASSOC_TYPE_SUBMISSION,
            Application::ASSOC_TYPE_ISSUE_GALLEY,
            Application::ASSOC_TYPE_ISSUE,
            Application::ASSOC_TYPE_JOURNAL,
        ];
        if (!in_array($entry['assocType'], $validAssocTypes)) {
            return false;
        }
        if (!is_numeric($entry['assocId'])) {
            return false;
        }
        $validFileTypes = [
            StatisticsHelper::STATISTICS_FILE_TYPE_PDF,
            StatisticsHelper::STATISTICS_FILE_TYPE_DOC,
            StatisticsHelper::STATISTICS_FILE_TYPE_HTML,
            StatisticsHelper::STATISTICS_FILE_TYPE_OTHER,
        ];
        if (!empty($entry['fileType']) && !in_array($entry['fileType'], $validFileTypes)) {
            return false;
        }
        if (!empty($entry['country']) && (!ctype_alpha($entry['country']) || !strlen($entry['country'] == 2))) {
            return false;
        }
        if (!empty($entry['region']) && (!ctype_alnum($entry['region']) || !strlen($entry['region'] <= 3))) {
            return false;
        }
        if (!is_array($entry['institutionIds'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate datetime
     */
    private function _validateDate(string $datetime, string $format = 'Y-m-d H:i:s'): bool
    {
        $d = DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) == $datetime;
    }
}
