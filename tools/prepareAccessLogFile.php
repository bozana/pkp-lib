<?php

/**
 * @file tools/PrepareAccessLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrepareAccessLogFile
 * @ingroup tools
 *
 * @brief CLI tool to copy and prepare apache access log files for further conversion and reprocessing.
 *
 * The files will be copied to the {files_dir}/usageStats/tmp/ folder,
 * the entries related only to the current instalation will be filtered,
 * renamed into apache_usage_events_YYYYMMDD.log,
 * the files will be spit by day,
 * and copied into the {files_dir}/usageStats/archive/ folder.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\core\Application;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use PKP\cliTool\ConvertLogFile;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\statistics\PKPStatisticsHelper;
use PKP\submission\Genre;
use PKP\task\FileLoader;

class PrepareAccessLogFile extends ConvertLogFile
{
    /**
     * Path to the egrep program, required for this tool to work, e.g. '/bin/egrep'
     */
    public const EGREP_PATH = '/bin/egrep';

    /**
     * Weather the URL parameters are used instead of CGI PATH_INFO.
     * This is the former variable 'disable_path_info' in the config.inc.php
     *
     * This needs to be set to true if the URLs in the old log file contain the paramteres as URL query string.
     */
    public const PATHINFODISABLED = false;

    /**
     * Regular expression that is used for parsing the apache access log files.
     *
     * The default regex can parse apache access log files in combined format ("%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\"").
     *
     * If the apache log files are in different format the correct regex needs to be entered here, so
     * that the following information can be extracted, in the given order: IP, date, URL, return code, user agent
     */
    //public const PARSEREGEX = '/^(\S+) \S+ \S+ \[(.*?)\] "\S+ (\S+) \S+" (\S+) \S+ \S+ "(.*?)"/';
    public const PARSEREGEX = '/^(?P<ip>\S+) \S+ \S+ \[(?P<date>.*?)\] "\S+ (?P<url>\S+).*?" (?P<returnCode>\S+) \S+ ".*?" "(?P<userAgent>.*?)"/';
    /**
     * PHP format of the time in the log file.
     * S. https://www.php.net/manual/en/datetime.format.php
     *
     * The default format can parse the apache access log file combined format ([day/month/year:hour:minute:second zone]).
     *
     * If the time in the apache log files is in a different format the correct PHP format needs to be entered here.
     */
    // TO-DO: ask how to deal with timezone, do we need it?
    public const PHPDATETIMEFORMAT = 'd/M/Y:H:i:s O';

    /**
     * PHP format of the date (without time and timezone)
     */
    public const PHPDATEFORMAT = 'd/M/Y';


    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);
        if (count($this->argv) < 1 || count($this->argv) > 2) {
            $this->usage();
            exit(1);
        }


        // This tool needs egrep path configured.
        if (self::EGREP_PATH == '') {
            echo __('admin.error.executingUtil') . "\n";
            exit(1);
        }
    }

    public function getProcessingDir(): string
    {
        return PKPStatisticsHelper::getUsageStatsDirPath() . '/tmp';
    }
    public function getParseRegex(): string
    {
        return self::PARSEREGEX;
    }
    public function getPhpDateTimeFormat(): string
    {
        return self::PHPDATETIMEFORMAT;
    }
    public function getPathInfoDisabled(): bool
    {
        return self::PATHINFODISABLED;
    }
    public function isApacheAccessLogFile(): bool
    {
        return true;
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\n" . __('admin.copyAccessLogFileTool.usage', ['scriptName' => $this->scriptName]) . "\n\n";
    }

    /**
     * Prepare apache access log files for reprocessing.
     * Can work with both a specific file or a directory.
     */
    public function execute(): void
    {
        $fileMgr = new FileManager();
        $filePath = current($this->argv);

        if ($fileMgr->fileExists($this->getProcessingDir(), 'dir')) {
            $fileMgr->rmtree($this->getProcessingDir());
        }

        if (!$fileMgr->mkdir($this->getProcessingDir())) {
            printf(__('admin.copyAccessLogFileTool.error.creatingFolder', ['tmpDir' => $this->getProcessingDir()]) . "\n");
            exit(1);
        }

        if ($fileMgr->fileExists($filePath, 'dir')) {
            // Directory.
            $filesToCopy = glob("{$filePath}/*");
            foreach ($filesToCopy as $file) {
                // If a base filename is given as a parameter, check it.
                if (count($this->argv) == 2) {
                    $baseFilename = $this->argv[1];
                    if (strpos(pathinfo($file, PATHINFO_BASENAME), $baseFilename) !== 0) {
                        continue;
                    }
                }
                $this->processAccessLogFile($file);
            }
        } else {
            if ($fileMgr->fileExists($filePath)) {
                // File.
                $this->processAccessLogFile($filePath);
            } else {
                // Can't access.
                printf(__('admin.copyAccessLogFileTool.error.acessingFile', ['filePath' => $filePath]) . "\n");
            }
        }

        //$fileMgr->rmtree($this->getProcessingDir());
    }

    /**
     * Process the access log file:
     * copy it to the usageStats/tmp/ folder,
     * filter entries related to this installation,
     * split by day,
     * convert into the new JSON format,
     * copy to usageStats/archive/ folder.
     */
    public function processAccessLogFile(string $filePath)
    {
        $copiedFilePath = $this->copyFile($filePath);
        $filteredFilePath = $this->filterFile($copiedFilePath);
        $dailyFiles = $this->splitFileByDay($filteredFilePath);
        foreach ($dailyFiles as $dailyFile) {
            $this->convert($dailyFile);
            $this->archive($dailyFile);
        }
    }

    /**
     * Copy acess log file to the folder usageStats/tmp/
     */
    public function copyFile(string $filePath): string
    {
        $fileName = pathinfo($filePath, PATHINFO_BASENAME);
        $tmpFilePath = "{$this->getProcessingDir()}/{$fileName}";
        $fileMgr = new FileManager();
        if (!$fileMgr->copyFile($filePath, $tmpFilePath)) {
            echo __('admin.copyAccessLogFileTool.error.copyingFile', ['filePath' => $filePath, 'tmpFilePath' => $tmpFilePath]) . "\n";
            exit(1);
        }
        echo "File {$filePath} copied to {$tmpFilePath}.\n";
        return $tmpFilePath;
    }

    /**
     * Filtering accell log file entries related to this installation, i.e.
     * that contain context paths. Save the filteres entries into
     * a new file with the ending _tmp.
     */
    public function filterFile(string $filePath): string
    {
        $isCompressed = false;
        if (pathinfo($filePath, PATHINFO_EXTENSION) == 'gz') {
            $isCompressed = true;
        }
        // Uncompress it, if needed.
        $fileMgr = new FileManager();
        if ($isCompressed) {
            try {
                $filePath = $fileMgr->gzDecompressFile($filePath);
            } catch (Exception $e) {
                printf($e->getMessage() . "\n");
                exit(1);
            }
        }

        // Each context path is already escaped, see the constructor.
        $filteredFilePath = $filePath . '_tmp';
        $escapedContextPaths = implode('/|/', array_map('escapeshellarg', $this->contextsByPath));
        $contextPaths = implode('/|/', $contextPaths);
        $output = null;
        $returnValue = 0;
        exec(escapeshellarg(self::EGREP_PATH) . " -i '" . $escapedContextPaths . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($filteredFilePath), $output, $returnValue);
        if ($returnValue > 1) {
            printf(__('admin.error.executingUtil', ['utilPath' => self::EGREP_PATH, 'utilVar' => 'egrep']) . "\n");
            exit(1);
        }
        /*
        if (!$fileMgr->deleteByPath($filePath)) {
            printf(__('admin.copyAccessLogFileTool.error.deletingFile', ['filePath' => $filePath]) . "\n");
            exit(1);
        }
        */
        return $filteredFilePath;
    }

    /**
     * Split accell log file by day. The new, daily files will be named apache_usage_events_{day}.log
     *
     * @return array List of daily access log files.
     */
    public function splitFileByDay(string $filePath): array
    {
        // Get the first and the last date in the log file
        $firstDate = $lastDate = null;
        $splFileObject = new SplFileObject($filePath, 'r');
        while (!$splFileObject->eof()) {
            $line = $splFileObject->fgets();
            if (preg_match(self::PARSEREGEX, $line, $m)) {
                $firstDate = DateTime::createFromFormat(self::PHPDATETIMEFORMAT, $m[2]);
                break;
            }
        }
        $splFileObject->seek(PHP_INT_MAX);
        $lastLineNo = $splFileObject->key() + 1;
        do {
            $splFileObject->seek($lastLineNo);
            $line = $splFileObject->current();
            if (preg_match(self::PARSEREGEX, $line, $m)) {
                $lastDate = DateTime::createFromFormat(self::PHPDATETIMEFORMAT, $m[2]);
                break;
            }
            $lastLineNo = $splFileObject->key() - 1;
        } while ($lastLineNo > 0);
        //explicitly assign null, so that the file can be deleted
        $splFileObject = null;

        if (is_null($firstDate) || is_null($lastDate)) {
            echo 'error, no first or last date found' . "\n";
            exit(1);
        }

        // Get all days between the first and the last date, including the last date
        $period = new DatePeriod(
            $firstDate,
            new DateInterval('P1D'),
            $lastDate
        );

        $dailyFiles = [];
        foreach ($period as $key => $value) {
            $day = $value->format('Ymd');
            // Check if a converted apache file with the same day already exists in any of usageStats/ folders.
            $existingApacheUsageEventsFiles = glob(PKPStatisticsHelper::getUsageStatsDirPath() . '/*/apache_usage_events_' . $day . '*');
            $existingApacheUsageEventsFilesCount = count($existingApacheUsageEventsFiles) ? count($existingApacheUsageEventsFiles) : 0;
            $countPartOfFileName = '';
            if ($existingApacheUsageEventsFilesCount) {
                $countPartOfFileName = "_{$existingApacheUsageEventsFilesCount}_";
                echo "Warning: One or more files apache_usage_events_{$day}.log already exist. You will need to clean or merge them into one before reprocessing them.\n";
            }
            $dailyFileName = 'apache_usage_events_' . $day . $countPartOfFileName . '.log';
            $dayFilePath = $this->getProcessingDir() . '/' . $dailyFileName;
            $output = null;
            $returnValue = 0;
            exec(escapeshellarg(self::EGREP_PATH) . " -i '" . preg_quote($value->format(self::PHPDATEFORMAT)) . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($dayFilePath), $output, $returnValue);
            if ($returnValue > 1) {
                echo "could not split the file by day\n";
                exit(1);
            }
            $dailyFiles[] = $dailyFileName;
            echo "File {$dayFilePath} created.\n";
        }

        /*
        if (!$fileMgr->deleteByPath($filePath)) {
            printf(__('admin.copyAccessLogFileTool.error.deletingFile', ['tmpFilePath' => $filePath]) . "\n");
            exit(1);
        }
        */
        return $dailyFiles;
    }

    /**
     * Copy the file from the folder usageStats/tmp/ into usageStats/archive/.
     */
    public function archive(string $fileName): void
    {
        $tmpFilePath = "{$this->getProcessingDir()}/{$fileName}";
        $archiveFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE . '/' . $fileName;
        $fileMgr = new FileManager();
        if (!$fileMgr->copyFile($tmpFilePath, $archiveFilePath)) {
            echo __('admin.copyAccessLogFileTool.error.copyingFile', ['filePath' => $tmpFilePath, 'tmpFilePath' => $archiveFilePath]) . "\n";
            exit(1);
        }
        echo "File {$tmpFilePath} successfully archived to {$archiveFilePath}.\n";
    }

    /**
    * Get the expected page and operation.
    * They are grouped by the object type constant that
    * they give access to.
    */
    protected function getExpectedPageAndOp(): array
    {
        $pageAndOp = [
            Application::getContextAssocType() => [
                'index/index'
            ]
        ];
        $application = Application::get();
        $applicationName = $application->getName();
        switch ($applicationName) {
            case 'ojs2':
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'article/download', 'article/downloadSuppFile', 'article/viewFile'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'article/view', 'article/viewArticle'],
                    Application::ASSOC_TYPE_ISSUE => [
                        'issue/view'],
                    Application::ASSOC_TYPE_ISSUE_GALLEY => [
                        'issue/download', 'issue/viewFile']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'index';
                break;
            case 'omp':
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'catalog/download'],
                    Application::ASSOC_TYPE_MONOGRAPH => [
                        'catalog/book'],
                    Application::ASSOC_TYPE_SERIES => [
                        'catalog/series']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'catalog/index';
                break;
            case 'ops':
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'preprint/download'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'preprint/view']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'index';
                break;
            default:
                throw new Exception('Unrecognized application name.');
        }
        return $pageAndOp;
    }

    /**
     * Set assoc type and IDs from the passed page, operation and arguments.
     */
    protected function setAssoc(int $assocType, string $op, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                if (!isset($args[0]) || !isset($args[1])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                // Consider also this issue: https://github.com/pkp/pkp-lib/issues/6573
                // where apache log files can contain URL download/$submissionId/$representationId, i.e. without $submissionFileId argument.
                // Also the URLs from releases 2.x will not have submissionFileId.
                $publicationId = $submissionFileId = null; // do not necessarily exist
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3])) {
                        // if version is there, there must be $publicationId and $representationId arguments
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationUrlPath = $args[3];
                    if (isset($args[4])) {
                        $submissionFileId = (int) $args[4];
                    }
                } else {
                    $representationUrlPath = $args[1];
                    if (isset($args[2])) {
                        $submissionFileId = (int) $args[2];
                    }
                }

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    echo "Submission with the URL path or ID {$args[0]} does not exist in the context (journal, press or server) with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $submissionId = $submission->getId();

                // Find the galley and representation ID
                $representationId = $galley = null;
                if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                    // assume it is ID and not the URL path
                    $representationId = (int) $representationUrlPath;
                    $galley = Repo::galley()->get($representationId);
                    if (!$galley) {
                        echo "Represantation (galley or publication format) with the ID {$representationUrlPath} does not exist.\n";
                        break;
                    }
                } else {
                    // We need to get the publication in order to be able to get galley by URL path
                    $publications = $submission->getData('publications');
                    if (isset($publicationId)) {
                        $publication = $publications->first(function ($value, $key) use ($publicationId) {
                            return $value->getId() == $publicationId;
                        });
                        if (!$publication) {
                            echo "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}.\n";
                            break;
                        }
                        $galley = Repo::galley()->getByUrlPath($representationUrlPath, $publication);
                        if (!$galley) {
                            echo "Represantation (galley or publication format) with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}.\n";
                            break;
                        }
                        $representationId = $galley->getId();
                    } else {
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication and galley found with the given representationUrlPath.
                        $possibleGalleys = [];
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('galleys') as $publicationGalley) {
                                if ($publicationGalley->getBestGalleyId() == $representationUrlPath) {
                                    $possibleGalleys[] = $publicationGalley;
                                    if (isset($submissionFileId) && $publicationGalley->getData('submissionFileId') == $submissionFileId) {
                                        $galley = $publicationGalley;
                                        $representationId = $publicationGalley->getId();
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (empty($possibleGalleys)) {
                            echo "Represantation (galley or publication format) with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}.\n";
                            break;
                        }
                        // if no matching galley has been found yet, take the first possible
                        if (!isset($representationId)) {
                            $galley = $possibleGalleys[0];
                            $representationId = $galley->getId();
                        }
                    }
                }

                switch (Application::get()->getName()) {
                    case 'ojs2':
                    case 'ops':
                        // consider this issue: https://github.com/pkp/pkp-lib/issues/6573
                        // apache log files contain URL download/submissionId/galleyId, i.e. without third argument
                        if (!$submissionFileId) {
                            $submissionFileId = $galley->getData('submissionFileId');
                        }
                        break;
                    case 'omp':
                        // TO-DO: check OMP!!!
                        if (!isset($args[2])) {
                            echo "Missing URL parameter.\n";
                            break 2;
                        } else {
                            $submissionFileId = (int) $args[2];
                        }
                        break;
                    default:
                        throw new Exception('Unrecognized application name!');
                }

                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    echo "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}.\n";
                    break;
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    echo "Submission file with the ID {$submissionFileId} does not belong to the represantation (galley or publication format) with the ID {$representationId}.\n";
                    break;
                }

                // is this a full text or supp file
                $genreDao = DAORegistry::getDAO('GenreDAO');
                $genre = $genreDao->getById($submissionFile->getData('genreId'));
                if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                } else {
                    $newEntry['assocType'] = $assocType;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['representationId'] = $representationId;
                $newEntry['submissionFileId'] = $submissionFileId;
                $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    echo "Submission with the URL path or ID {$args[0]} does not exist in the context (journal, press or server) with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                // Consider also releases 2.x where log files can contain URL
                // view/$submissionId/$representationId i.e. without $submissionFileId argument
                $publicationId = null;
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2])) {
                        break;
                    }
                    $publicationId = (int) $args[2];
                } elseif (count($args) == 2) {
                    // Consider usage stats log files from releases 2.x:
                    // The URL article/view/{$articleId}/{$galleyId} was used for assoc type galley, HTML and remote galleys.
                    // Those should now be assoc type submission file.
                    $representationUrlPath = $args[1];
                    $galley = $representationId = null;
                    if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                        // assume it is ID and not the URL path
                        $representationId = (int) $representationUrlPath;
                        $galley = Repo::galley()->get($representationId);
                        if (!$galley) {
                            echo "Represantation (galley or publication format) with the ID {$representationUrlPath} does not exist.\n";
                            break;
                        }
                    } else {
                        // We need to get the publication in order to be able to get galley by URL path
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication and galley found with the given representationUrlPath.
                        // It is not accurate but only possible.
                        $publications = $submission->getData('publications');
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('galleys') as $publicationGalley) {
                                if ($publicationGalley->getBestGalleyId() == $representationUrlPath) {
                                    $galley = $publicationGalley;
                                    $representationId = $publicationGalley->getId();
                                    break 2;
                                }
                            }
                        }
                        if (!isset($galley)) {
                            echo "Represantation (galley or publication format) with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}.\n";
                            break;
                        }
                        $submissionFileId = $galley->getData('submissionFileId');
                        if (!$submissionFileId) {
                            // it is a remote galley from releases 2.x
                            break;
                        }
                        $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                        $fileType = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                        if ($fileType == StatisticsHelper::STATISTICS_FILE_TYPE_PDF) {
                            // Do not consider PDF file, the download URL will follow
                            break;
                        }
                        // is this a full text or supp file
                        // it should be only full texts but do the check however
                        $genreDao = DAORegistry::getDAO('GenreDAO');
                        $genre = $genreDao->getById($submissionFile->getData('genreId'));
                        if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                            $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                        } else {
                            $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE;
                        }
                        $newEntry['submissionId'] = $submissionId;
                        $newEntry['representationId'] = $representationId;
                        $newEntry['submissionFileId'] = $submissionFileId;
                        $newEntry['fileType'] = $fileType;
                        break;
                    }
                }
                if ($publicationId && !Repo::publication()->exists($publicationId, $submissionId)) {
                    echo "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}.\n";
                    break;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
                break;

            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                break;
        }

        if (!array_key_exists('assocType', $newEntry)) {
            $application = Application::get();
            $applicationName = $application->getName();
            switch ($applicationName) {
                case 'ojs2':
                    $this->setOJSAssoc($assocType, $args, $newEntry);
                    break;
                case 'omp':
                    $this->setOMPAssoc($assocType, $args, $newEntry);
                    break;
                case 'ops':
                    break; // noop
                default:
                    throw new Exception('Unrecognized application name!');
            }
        }
    }

    /**
     * Set assoc type and IDs from the passed page, operation and
     * arguments specific to OJS.
     */
    protected function setOJSAssoc(int $assocType, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::ASSOC_TYPE_ISSUE:
                if (!isset($args[0])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                // Consider issue https://github.com/pkp/pkp-lib/issues/6611
                // where apache log files contain both URLs for issue galley download:
                // issue/view/issueId/galleyId (that should not be considered here), as well as
                // issue/download/issueId/galleyId
                if (count($args) != 1) {
                    break;
                }
                $issue = Repo::issue()->getByBestId($args[0], $newEntry['contextId']);
                if (!$issue) {
                    echo "Issue with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $issueId = $issue->getId();
                $newEntry['issueId'] = $issueId;
                $newEntry['assocType'] = $assocType;
                break;

            case Application::ASSOC_TYPE_ISSUE_GALLEY:
                if (!isset($args[0]) || !isset($args[1])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                $issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
                $issue = Repo::issue()->getByBestId($args[0], $newEntry['contextId']);
                if (!$issue) {
                    echo "Issue with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $issueId = $issue->getId();
                $issueGalley = $issueGalleyDao->getByBestId($args[1], $issueId);
                if (!$issueGalley) {
                    echo "Issue galley with the URL path or ID {$args[1]} does not exist in the issue with the ID {$issueId}.\n";
                    break;
                }
                $newEntry['issueId'] = $issueId;
                $newEntry['issueGalleyId'] = $issueGalley->getId();
                $newEntry['assocType'] = $assocType;
                break;
        }
    }
}

$tool = new PrepareAccessLogFile($argv ?? []);
$tool->execute();
