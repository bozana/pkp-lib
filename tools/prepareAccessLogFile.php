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
use APP\statistics\StatisticsHelper;
use PKP\file\FileManager;
use PKP\statistics\PKPStatisticsHelper;
use PKP\task\FileLoader;

class PrepareAccessLogFile extends \PKP\cliTool\CommandLineTool
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
    public const PARSEREGEX = '/^(\S+) \S+ \S+ \[(.*?)\] "\S+ (\S+) \S+" (\S+) \S+ \S+ "(.*?)"/';

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
     * Processing temporary directory
     */
    public string $tmpDir;

    /**
     * List of this installation context paths,
     * shell escaped and separated by '|'
     */
    public string $contextPaths;


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

        $this->tmpDir = PKPStatisticsHelper::getUsageStatsDirPath() . '/tmp';

        // This tool needs egrep path configured.
        if (self::EGREP_PATH == '') {
            echo __('admin.error.executingUtil') . "\n";
            exit(1);
        }

        // Get a list of context paths.
        $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
        $contextFactory = $contextDao->getAll();
        $contextPaths = [];
        while ($context = $contextFactory->next()) {
            /** @var Context $context */
            $contextPaths[] = escapeshellarg($context->getPath());
        }
        $contextPaths = implode('/|/', $contextPaths);
        $this->contextPaths = $contextPaths;
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
    public function execute()
    {
        $fileMgr = new FileManager();
        $filePath = current($this->argv);

        if ($fileMgr->fileExists($this->tmpDir, 'dir')) {
            $fileMgr->rmtree($this->tmpDir);
        }

        if (!$fileMgr->mkdir($this->tmpDir)) {
            printf(__('admin.copyAccessLogFileTool.error.creatingFolder', ['tmpDir' => $this->tmpDir]) . "\n");
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

        //$fileMgr->rmtree($this->tmpDir);
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
        $tmpFilePath = "{$this->tmpDir}/{$fileName}";
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
        $output = null;
        $returnValue = 0;
        exec(self::EGREP_PATH . " -i '" . $this->contextPaths . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($filteredFilePath), $output, $returnValue);
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
            $dayFilePath = $this->tmpDir . '/' . $dailyFileName;
            $output = null;
            $returnValue = 0;
            exec(self::EGREP_PATH . " -i '" . preg_quote($value->format(self::PHPDATEFORMAT)) . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($dayFilePath), $output, $returnValue);
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
     * Convert the access log file into the new JSON usage stats log file format.
     */
    public function convert(string $fileName): void
    {
        $convertTool = new \PKP\cliTool\ConvertUsageStatsLogFile(['lib/pkp/tools/convertUsageStatsLogFile.php', $fileName]);
        $convertTool->setParseRegex(self::PARSEREGEX);
        $convertTool->setPathInfoDisabled(self::PATHINFODISABLED);
        $convertTool->setPhpDateTimeFormat(self::PHPDATETIMEFORMAT);
        $convertTool->setIsApacheAccessLogFile(true);
        $convertTool->execute();
    }

    /**
     * Copy the file from the folder usageStats/tmp/ into usageStats/archive/.
     */
    public function archive(string $fileName): void
    {
        $tmpFilePath = "{$this->tmpDir}/{$fileName}";
        $archiveFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE . '/' . $fileName;
        $fileMgr = new FileManager();
        if (!$fileMgr->copyFile($tmpFilePath, $archiveFilePath)) {
            echo __('admin.copyAccessLogFileTool.error.copyingFile', ['filePath' => $tmpFilePath, 'tmpFilePath' => $archiveFilePath]) . "\n";
            exit(1);
        }
        echo "File {$tmpFilePath} successfully archived to {$archiveFilePath}.\n";
    }
}

$tool = new PrepareAccessLogFile($argv ?? []);
$tool->execute();
