<?php

/**
 * @file tools/convertUsageStatsLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertUsageStatsLogFile
 * @ingroup tools
 *
 * @brief CLI tool to convert old usage stats log file (used in releases < 3.4) into the new format.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');


/**
 * Weather the URL parameters are used instead of CGI PATH_INFO.
 * This is the former variable 'disable_path_info' in the config.inc.php
 *
 * This needs to be set to true if the URLs in the old log file contain the paramteres as URL query string.
 */
$pathInfoDisabled = false;

echo json_encode($argv);
$tool = new \PKP\cliTool\ConvertUsageStatsLogFile($argv ?? []);
$tool->setPathInfoDisabled($pathInfoDisabled);
$tool->execute();
