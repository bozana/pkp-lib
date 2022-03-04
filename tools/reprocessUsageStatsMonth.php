<?php

/**
 * @file tools/reprocessUsageStatsMonth.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class reprocessUsageStatsMonth
 * @ingroup tools
 *
 * @brief CLI tool to reprocess the usage stats log files for a month.
 */

use APP\core\Services;
use APP\tasks\UsageStatsLoader;

require(dirname(__FILE__, 4) . '/tools/bootstrap.inc.php');

class ReprocessUsageStatsMonth extends \PKP\cliTool\CommandLineTool
{
    public $month;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct(array $argv = [])
    {
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ constructor argv++++\n", true);
        $current .= print_r($argv, true);
        file_put_contents($file, $current);

        parent::__construct($argv);
        if (count($this->argv) != 1) {
            $this->usage();
            exit(1);
        }
        $this->month = array_shift($this->argv);
        if (!preg_match('/[0-9]{6}/', $this->month)) {
            $this->usage();
            exit(1);
        }
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\nReprocess the usage stats log files for a month.\n\n"
            . "  Usage: php {$this->scriptName} [YYYYMM]\n\n";
    }

    /**
     * Reprocess usage stats log file for the given month.
     */
    public function execute()
    {
        // Remove the month from the monthly DB tables
        $counterService = Services::get('sushiStats');
        $geoService = Services::get('sushiStats');
        $counterService->deleteMonthlyMetrics($this->month);
        $geoService->deleteMonthlyMetrics($this->month);
        // Check if all log files from that month are in usageEventLogs folder???
        $usageStatsLoader = new UsageStatsLoader([$this->month]);
        $usageStatsLoader->execute();
    }
}

$tool = new ReprocessUsageStatsMonth($argv ?? []);
$tool->execute();
