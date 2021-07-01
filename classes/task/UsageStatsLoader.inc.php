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

namespace PKP\task;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use PKP\core\Core;

use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTaskHelper;

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

        $statsInstitutionDao = DAORegistry::getDAO('UsageStatsInstitutionTemporaryRecordDAO'); /* @var $statsInstitutionDao UsageStatsInstitutionTemporaryRecordDAO */
        $statsTotalDao = DAORegistry::getDAO('UsageStatsTotalTemporaryRecordDAO'); /* @var $statsTotalDao UsageStatsTotalTemporaryRecordDAO */
        $statsUniqueInvestigationsDao = DAORegistry::getDAO('UsageStatsUniqueInvestigationsTemporaryRecordDAO'); /* @var $statsUniqueInvestigationsDao UsageStatsUniqueInvestigationsTemporaryRecordDAO */
        $statsUniqueRequestsDao = DAORegistry::getDAO('UsageStatsUniqueRequestsTemporaryRecordDAO'); /* @var $statsUniqueRequestsDao UsageStatsUniqueRequestsTemporaryRecordDAO */
        // Make sure we don't have any temporary records associated
        // with the current load id in database.
        $statsInstitutionDao->deleteByLoadId($loadId);
        $statsTotalDao->deleteByLoadId($loadId);
        $statsUniqueInvestigationsDao->deleteByLoadId($loadId);
        $statsUniqueRequestsDao->deleteByLoadId($loadId);

        $lineNumber = 0;
        while (!feof($fhandle)) {
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines.

            $entryData = json_decode($line);

            try {
                $this->_isLogEntryValid($entryData);
            } catch (\Exception $e) {
                throw new \Exception(__(
                    'usageStats.invalidLogEntry',
                    ['file' => $filePath, 'lineNumber' => $lineNumber, 'error' => $e->getMessage()]
                ));
            }

            // Avoid bots.
            if (Core::isUserAgentBot($entryData->userAgent)) {
                continue;
            }

            $foreignKeyErrors = $statsTotalDao->checkForeignKeys($entryData);
            if (!empty($foreignKeyErrors)) {
                $missingForeignKeys = implode(', ', $foreignKeyErrors);
                $this->addExecutionLogEntry(__('usageStats.logfileProcessing.foreignKeyError', ['missingForeignKeys' => $missingForeignKeys, 'loadId' => $loadId, 'lineNumber' => $lineNumber]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            } else {
                $statsInstitutionDao->insert($entryData->institutionIds, $lineNumber, $loadId);
                $statsTotalDao->insert($entryData, $lineNumber, $loadId);
                if (!empty($entryData->submissionId)) {
                    $statsUniqueInvestigationsDao->insert($entryData, $lineNumber, $loadId);
                    if ($entryData->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE) {
                        $statsUniqueRequestsDao->insert($entryData, $lineNumber, $loadId);
                    }
                }
            }
        }
        fclose($fhandle);

        $statsTotalDao->removeDoubleClicks(self::COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS);
        $statsUniqueInvestigationsDao->removeUniqueClicks();
        $statsUniqueRequestsDao->removeUniqueClicks();

        // load data from temporary tables into the actual metrics tables
        $loadSuccessful = true;
        $loadSuccessful = $statsTotalDao->loadMetricsContext($loadId);
        if ($loadSuccessful) {
            $loadSuccessful = $statsTotalDao->loadMetricsIssue($loadId);
        }
        if ($loadSuccessful) {
            $loadSuccessful = $statsTotalDao->loadMetricsSubmission($loadId);
        }

        if ($loadSuccessful) {
            $statsTotalDao->deleteCounterSubmissionDailyByLoadId($loadId);
            $loadSuccessful = $statsTotalDao->loadMetricsCounterSubmissionDaily($loadId);
            if ($loadSuccessful) {
                $loadSuccessful = $statsUniqueInvestigationsDao->loadMetricsCounterSubmissionDaily($loadId);
                if ($loadSuccessful) {
                    $loadSuccessful = $statsUniqueRequestsDao->loadMetricsCounterSubmissionDaily($loadId);
                }
            }
        }

        if ($loadSuccessful) {
            $statsTotalDao->deleteCounterSubmissionGeoDailyByLoadId($loadId);
            $loadSuccessful = $statsTotalDao->loadMetricsCounterSubmissionGeoDaily($loadId);
            if ($loadSuccessful) {
                $loadSuccessful = $statsUniqueInvestigationsDao->loadMetricsCounterSubmissionGeoDaily($loadId);
                if ($loadSuccessful) {
                    $loadSuccessful = $statsUniqueRequestsDao->loadMetricsCounterSubmissionGeoDaily($loadId);
                }
            }
        }

        //$start = microtime(true);
        if ($loadSuccessful) {
            $statsTotalDao->deleteCounterSubmissionInstitutionDailyByLoadId($loadId);
            $loadSuccessful = $statsTotalDao->loadMetricsCounterSubmissionInstitutionDaily($loadId);
            if ($loadSuccessful) {
                $loadSuccessful = $statsUniqueInvestigationsDao->loadMetricsCounterSubmissionInstitutionDaily($loadId);
                if ($loadSuccessful) {
                    $loadSuccessful = $statsUniqueRequestsDao->loadMetricsCounterSubmissionInstitutionDaily($loadId);
                }
            }
        }
        //$time_elapsed_secs = microtime(true) - $start;

        //$statsTotalDao->deleteByLoadId($loadId);
        //$statsUniqueInvestigationsDao->deleteByLoadId($loadId);
        //$statsUniqueRequestsDao->deleteByLoadId($loadId);
        //$statsInstitutionDao->deleteByLoadId($loadId);

        if (!$loadSuccessful) {
            // TO-DO: move plugins.generic.usageStats.loadDataError to usageStats.loadDataError
            $this->addExecutionLogEntry(__(
                'usageStats.loadDataError',
                ['file' => $filePath]
            ), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return FileLoader::FILE_LOADER_RETURN_TO_STAGING;
        } else {
            return true;
        }

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
    private function _isLogEntryValid(\stdClass $entry)
    {
        if (!$this->_validateDate($entry->time)) {
            throw new \Exception(__('usageStats.invalidLogEntry.time'));
        }
        // check hashed IP ?
        // check canonicalUrl ?
        if (!is_int($entry->contextId)) {
            throw new \Exception(__('usageStats.invalidLogEntry.contextId'));
        } else {
            if ($entry->assocType == Application::ASSOC_TYPE_JOURNAL && $entry->assocId != $entry->contextId) {
                throw new \Exception(__('usageStats.invalidLogEntry.contextAssocTypeNoMatch'));
            }
        }
        if (!empty($entry->issueId)) {
            if (!is_int($entry->issueId)) {
                throw new \Exception(__('usageStats.invalidLogEntry.issueId'));
            } else {
                if ($entry->assocType == Application::ASSOC_TYPE_ISSUE && $entry->assocId != $entry->issueId) {
                    throw new \Exception(__('usageStats.invalidLogEntry.issueAssocTypeNoMatch'));
                }
            }
        }
        if (!empty($entry->submissionId)) {
            if (!is_int($entry->submissionId)) {
                throw new \Exception(__('usageStats.invalidLogEntry.submissionId'));
            } else {
                if ($entry->assocType == Application::ASSOC_TYPE_SUBMISSION && $entry->assocId != $entry->submissionId) {
                    throw new \Exception(__('usageStats.invalidLogEntry.submissionAssocTypeNoMatch'));
                }
            }
        }
        if (!empty($entry->representationId) && !is_int($entry->representationId)) {
            throw new \Exception(__('usageStats.invalidLogEntry.representationId'));
        }
        $validAssocTypes = [
            Application::ASSOC_TYPE_SUBMISSION_FILE,
            Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER,
            Application::ASSOC_TYPE_SUBMISSION,
            Application::ASSOC_TYPE_ISSUE_GALLEY,
            Application::ASSOC_TYPE_ISSUE,
            Application::ASSOC_TYPE_JOURNAL,
        ];
        if (!in_array($entry->assocType, $validAssocTypes)) {
            throw new \Exception(__('usageStats.invalidLogEntry.assocType'));
        }
        if (!is_int($entry->assocId)) {
            throw new \Exception(__('usageStats.invalidLogEntry.assocId'));
        }
        $validFileTypes = [
            StatisticsHelper::STATISTICS_FILE_TYPE_PDF,
            StatisticsHelper::STATISTICS_FILE_TYPE_DOC,
            StatisticsHelper::STATISTICS_FILE_TYPE_HTML,
            StatisticsHelper::STATISTICS_FILE_TYPE_OTHER,
        ];
        if (!empty($entry->fileType) && !in_array($entry->fileType, $validFileTypes)) {
            throw new \Exception(__('usageStats.invalidLogEntry.fileType'));
        }
        if (!empty($entry->country) && (!ctype_alpha($entry->country) || !strlen($entry->country == 2))) {
            throw new \Exception(__('usageStats.invalidLogEntry.country'));
        }
        if (!empty($entry->region) && (!ctype_alnum($entry->region) || !strlen($entry->region <= 3))) {
            throw new \Exception(__('usageStats.invalidLogEntry.region'));
        }
        if (!is_array($entry->institutionIds)) {
            throw new \Exception(__('usageStats.invalidLogEntry.institutionIds'));
        }
    }

    /**
     * Validate datetime
     */
    private function _validateDate(string $datetime, string $format = 'Y-m-d H:i:s'): bool
    {
        $d = \DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) == $datetime;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\UsageStatsLoader', '\UsageStatsLoader');
}
