<?php

/**
 * @file tools/convertAccessLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertLogFileTrait
 * @ingroup tools
 *
 * @brief CLI tool to convert the apache access log files into the new format.
 */

namespace PKP\cliTool;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use DateTime;
use Exception;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\file\FileManager;

trait ConvertLogFileTrait
{
    /**
     * Weather the URL parameters are used instead of CGI PATH_INFO.
     * This is the former variable 'disable_path_info' in the config.inc.php
     *
     * This needs to be set to true if the URLs in the old log file contain the paramteres as URL query string.
     */
    public bool $pathInfoDisabled;

    /**
     * Regular expression that is used for parsing the old log file entries that should be converted to the new format.
     *
     * This regex can parse the usageStats plugin's log files.
     */
    public string $parseRegex;

    /**
     * PHP format of the time in the log file.
     * S. https://www.php.net/manual/en/datetime.format.php
     *
     * This format can parse the usageStats plugin's log files.
     */
    public string $phpDateTimeFormat = 'Y-m-d H:i:s';

    public string $sourcePath = '';

    /**
     * Name of the log file that should be converted into the new format.
     *
     * The file needs to be in the folder usageStats/archive/
     */
    public string $fileName;

    /** List of contexts by their paths */
    public array $contextsByPath;

    public bool $isApacheAccessLogFile = false;

    abstract public function setProcessingDir(string $processingDir): void;

    abstract public function setParseRegex(string $parseRegex): void;

    abstract public function setPhpDateTimeFormat(string $phpDateTimeFormat): void;

    abstract public function setPathInfoDisabled(string $pathInfoDisabled): void;

    abstract public function setIsApacheAccessLogFile(bool $isApacheAccessLogFile): void;


    /**
     * Convert log file into the new format.
     *
     * The old log file will be renamed: _old is added at the end of the file name.
     */
    public function execute(): void
    {
        $filePath = $this->processingDir . '/' . $this->fileName;

        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ filePath ++++\n", true);
        $current .= print_r($filePath, true);
        file_put_contents($file, $current);

        $path_parts = pathinfo($filePath);
        $extension = $path_parts['extension'];

        $newFilePath = $this->processingDir . '/' . $path_parts['filename'] . '_new.log';
        if ($extension == 'gz') {
            $fileMgr = new FileManager();
            try {
                $filePath = $fileMgr->gzDecompressFile($filePath);
            } catch (Exception $e) {
                printf($e->getMessage() . "\n");
                exit(1);
            }
        }

        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            echo "Can not open file {$filePath}.\n";
            exit(1);
        }
        $lineNumber = 0;
        $isSuccessful = false;
        while (!feof($fhandle)) {
            $newEntry = [];
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines.

            $entryData = $this->getDataFromLogEntry($line);

            if (!$this->isLogEntryValid($entryData)) {
                echo "Invalid log entry at line {$lineNumber}.\n";
                continue;
            }

            // Avoid internal apache requests.
            if ($entryData['url'] == '*') {
                continue;
            }

            // Avoid non sucessful requests.
            $sucessfulReturnCodes = [200, 304];
            if (!in_array($entryData['returnCode'], $sucessfulReturnCodes)) {
                continue;
            }

            $newEntry['time'] = $entryData['date'];

            $ip = $entryData['ip'];
            $ipNotHashed = preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip);
            // shell IPv6 be considered ?
            if ($ipNotHashed === 1) {
                $saltFileName = StatisticsHelper::getSaltFileName();
                $salt = trim(file_get_contents($saltFileName));
                $hashedIp = StatisticsHelper::hashIp($ip, $salt);
                $newEntry['ip'] = $hashedIp;
            } else {
                // check if it is a string(64) i.e. sha256 ?
                $newEntry['ip'] = $ip;
            }

            $newEntry['userAgent'] = $entryData['userAgent'];
            $newEntry['canonicalUrl'] = $entryData['url'];

            [$assocType, $contextPaths, $page, $op, $args] = $this->getUrlMatches($entryData['url'], $lineNumber);
            if ($assocType && $contextPaths && $page && $op) {
                $foundContextPath = current($contextPaths);
                if (!array_key_exists($foundContextPath, $this->contextsByPath)) {
                    echo "Context with the path {$foundContextPath} does not exist.\n";
                    continue;
                }
                $context = $this->contextsByPath[$foundContextPath];
                $newEntry['contextId'] = $context->getId();

                $this->setAssoc($assocType, $op, $args, $newEntry);
                if (!array_key_exists('assocType', $newEntry)) {
                    if (!$this->isApacheAccessLogFile) {
                        echo "The URL {$entryData['url']} in the line number {$lineNumber} was not considered.\n";
                    }
                    continue;
                }
            } else {
                continue;
            }

            // Geo data
            $country = $region = $city = null;
            if ($ipNotHashed === 1) {
                $statisticsHelper = new StatisticsHelper();
                $site = Application::get()->getRequest()->getSite();
                [$country, $region, $city] = $statisticsHelper->getGeoData($site, $context, $ip, $hashedIp, false);
            }
            $newEntry['country'] = $country;
            $newEntry['region'] = $region;
            $newEntry['city'] = $city;

            // institutions IDs
            $institutionIds = [];
            if ($ipNotHashed === 1 && $context->isInstitutionStatsEnabled($site)) {
                $institutionIds = $statisticsHelper->getInstitutionIds($context->getId(), $ip, $hashedIp, false);
            }
            $newEntry['institutionIds'] = $institutionIds;

            $newEntry['version'] = Registry::get('appVersion');

            // write to a new file
            $newLogEntry = json_encode($newEntry) . PHP_EOL;
            file_put_contents($newFilePath, $newLogEntry, FILE_APPEND);
            $isSuccessful = true;
        }
        fclose($fhandle);

        if ($isSuccessful) {
            $renameFilePath = $this->processingDir . '/' . $path_parts['filename'] . '_old.log';
            if (!rename($filePath, $renameFilePath)) {
                echo "Cound not rename the file {$filePath} to {$renameFilePath}.\n";
                exit(1);
            } else {
                if (!$this->isApacheAccessLogFile) {
                    echo "The original file is renamed to {$renameFilePath}.\n";
                }
            }
            if (!rename($newFilePath, $filePath)) {
                echo "Cound not rename the new file {$newFilePath} to {$filePath}.\n";
                exit(1);
            } else {
                echo "File {$filePath} is converted.\n";
            }
            if ($extension == 'gz') {
                try {
                    $renameFilePath = $fileMgr->gzCompressFile($renameFilePath);
                    $filePath = $fileMgr->gzCompressFile($filePath);
                } catch (Exception $e) {
                    printf($e->getMessage() . "\n");
                    exit(1);
                }
            }
        } else {
            echo "File {$filePath} could not be successfully converted.\n";
            exit(1);
        }
    }

    /**
     * Get data from the passed log entry.
     */
    private function getDataFromLogEntry(string $entry): array
    {
        $entryData = [];
        if (preg_match($this->parseRegex, $entry, $m)) {
            $associative = count(array_filter(array_keys($m), 'is_string')) > 0;
            $entryData['ip'] = $associative ? $m['ip'] : $m[1];
            $time = $associative ? $m['date'] : $m[2];
            $dateTime = DateTime::createFromFormat($this->phpDateTimeFormat, $time);
            $entryData['date'] = $dateTime->format('Y-m-d H:i:s');
            $entryData['url'] = urldecode($associative ? $m['url'] : $m[3]);
            $entryData['returnCode'] = $associative ? $m['returnCode'] : $m[4];
            $entryData['userAgent'] = $associative ? $m['userAgent'] : $m[5];
        }
        return $entryData;
    }

    /**
     * Validate a access log entry.
     * This maybe does not have much sense, but because it was used till now, we will leave it.
     */
    private function isLogEntryValid(array $entry): bool
    {
        if (empty($entry)) {
            return false;
        }
        $date = $entry['date'];
        if (!is_numeric($date) && $date <= 0) {
            return false;
        }
        return true;
    }

    /**
     * Get assoc type, page, operation and args from the passed url,
     * if it matches any that's defined in getExpectedPageAndOp().
     */
    private function getUrlMatches(string $url, int $lineNumber): array
    {
        $noMatchesReturner = [null, null, null, null, null];

        $expectedPageAndOp = $this->getExpectedPageAndOp();

        // Apache and usage stats plugin log files come with complete or partial base url,
        // remove it so we can retrieve path, page, operation and args.
        $url = Core::removeBaseUrl($url);
        if ($url) {
            $contextPaths = $this->getContextPaths($url, !$this->pathInfoDisabled);
            $page = Core::getPage($url, !$this->pathInfoDisabled);
            $operation = Core::getOp($url, !$this->pathInfoDisabled);
            $args = Core::getArgs($url, !$this->pathInfoDisabled);
        } else {
            // Could not remove the base url, can't go on.
            // __('plugins.generic.usageStats.removeUrlError', array('file' => $filePath, 'lineNumber' => $lineNumber))
            echo "The line number {$lineNumber} contains an url that the system can't remove the base url from.\n";
            return $noMatchesReturner;
        }

        if ($this->isApacheAccessLogFile) {
            // in apache access log files there could be all kind of URLs, e.g.
            // /favicon.ico, /plugins/..., /lib/pkp/...
            // In that case stop here to look further.
            //$correctContextPaths = array_intersect($contextPaths, array_keys($this->contextsByPath));
            if (empty(array_intersect($contextPaths, array_keys($this->contextsByPath)))) {
                return $noMatchesReturner;
            }
        }

        // See bug #8698#.
        if (is_array($contextPaths) && !$page && $operation == 'index') {
            $page = 'index';
        }

        if (empty($contextPaths) || !$page || !$operation) {
            echo "Either context paths, page or operation could not be parsed from the URL correctly.\n";
            return $noMatchesReturner;
        }

        $pageAndOperation = $page . '/' . $operation;

        $pageAndOpMatch = false;
        foreach ($expectedPageAndOp as $workingAssocType => $workingPageAndOps) {
            foreach ($workingPageAndOps as $workingPageAndOp) {
                if ($pageAndOperation == $workingPageAndOp) {
                    // Expected url, don't look any futher.
                    $pageAndOpMatch = true;
                    break 2;
                }
            }
        }
        if ($pageAndOpMatch) {
            return [$workingAssocType, $contextPaths, $page, $operation, $args];
        } else {
            if (!$this->isApacheAccessLogFile) {
                echo "No matching page and operation found on line number {$lineNumber}.\n";
            }
            return $noMatchesReturner;
        }
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
                        'article/download'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'article/view'],
                    Application::ASSOC_TYPE_ISSUE => [
                        'issue/view'],
                    Application::ASSOC_TYPE_ISSUE_GALLEY => [
                        'issue/download']
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
     * Get context paths present into the passed
     * url information.
     */
    protected static function getContextPaths(string $urlInfo, bool $isPathInfo): array
    {
        $contextPaths = [];
        $application = Application::get();
        $contextList = $application->getContextList();
        $contextDepth = $application->getContextDepth();
        // Handle context depth 0
        if (!$contextDepth) {
            return $contextPaths;
        }

        if ($isPathInfo) {
            // Split the path info into its constituents. Save all non-context
            // path info in $contextPaths[$contextDepth]
            // by limiting the explode statement.
            $contextPaths = explode('/', trim((string) $urlInfo, '/'), $contextDepth + 1);
            // Remove the part of the path info that is not relevant for context (if present)
            unset($contextPaths[$contextDepth]);
        } else {
            // Retrieve context from url query string
            foreach ($contextList as $key => $contextName) {
                parse_str((string) parse_url($urlInfo, PHP_URL_QUERY), $userVarsFromUrl);
                $contextPaths[$key] = $userVarsFromUrl[$contextName] ?? null;
            }
        }

        // Canonicalize and clean context paths
        for ($key = 0; $key < $contextDepth; $key++) {
            $contextPaths[$key] = (
                isset($contextPaths[$key]) && !empty($contextPaths[$key]) ?
                $contextPaths[$key] : 'index'
            );
            $contextPaths[$key] = Core::cleanFileVar($contextPaths[$key]);
        }
        return $contextPaths;
    }

    /**
     * Get the page present into
     * the passed url information. It expects that urls
     * were built using the system.
     */
    protected static function getPage(string $urlInfo, bool $isPathInfo): string
    {
        $page = self::getUrlComponents($urlInfo, $isPathInfo, 0, 'page');
        return Core::cleanFileVar(is_null($page) ? '' : $page);
    }

    /**
     * Get the operation present into
     * the passed url information. It expects that urls
     * were built using the system.
     */
    protected static function getOp(string $urlInfo, bool $isPathInfo): string
    {
        $operation = self::getUrlComponents($urlInfo, $isPathInfo, 1, 'op');
        return Core::cleanFileVar(empty($operation) ? 'index' : $operation);
    }

    /**
     * Get the arguments present into
     * the passed url information (not GET/POST arguments,
     * only arguments appended to the URL separated by "/").
     * It expects that urls were built using the system.
     */
    protected static function getArgs(string $urlInfo, bool $isPathInfo): array
    {
        return self::getUrlComponents($urlInfo, $isPathInfo, 2, 'path');
    }

    /**
     * Get url components (page, operation and args)
     * based on the passed offset.
     */
    protected static function getUrlComponents(string $urlInfo, bool $isPathInfo, int $offset, string $varName = ''): mixed
    {
        $component = null;

        $isArrayComponent = false;
        if ($varName == 'path') {
            $isArrayComponent = true;
        }
        if ($isPathInfo) {
            $application = Application::get();
            $contextDepth = $application->getContextDepth();

            $vars = explode('/', trim($urlInfo, '/'));
            if (count($vars) > $contextDepth + $offset) {
                if ($isArrayComponent) {
                    $component = array_slice($vars, $contextDepth + $offset);
                } else {
                    $component = $vars[$contextDepth + $offset];
                }
            }
        } else {
            parse_str((string) parse_url($urlInfo, PHP_URL_QUERY), $userVarsFromUrl);
            $component = $userVarsFromUrl[$varName] ?? null;
        }

        if ($isArrayComponent) {
            if (empty($component)) {
                $component = [];
            } elseif (!is_array($component)) {
                $component = [$component];
            }
        }

        return $component;
    }
}
