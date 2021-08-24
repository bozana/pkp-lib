<?php

/**
* @file classes/statistics/PKPStatisticsHelper.inc.php
*
* Copyright (c) 2013-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class PKPStatisticsHelper
* @ingroup statistics
*
* @brief Statistics helper class.
*
*/

namespace PKP\statistics;

use APP\core\Application;

abstract class PKPStatisticsHelper
{
    // Dimensions:
    // 1) publication object dimension:
    public const STATISTICS_DIMENSION_CONTEXT_ID = 'context_id';

    public const STATISTICS_DIMENSION_SUBMISSION_ID = 'submission_id';
    public const STATISTICS_DIMENSION_ASSOC_TYPE = 'assoc_type';
    public const STATISTICS_DIMENSION_FILE_TYPE = 'file_type';
    //public const STATISTICS_DIMENSION_FILE_ID = 'file_id';
    public const STATISTICS_DIMENSION_SUBMISSION_FILE_ID = 'submission_file_id';
    public const STATISTICS_DIMENSION_REPRESENTATION_ID = 'representation_id';
    // helpers
    public const STATISTICS_DIMENSION_PKP_SECTION_ID = 'pkp_section_id';
    public const STATISTICS_DIMENSION_ASSOC_ID = 'assoc_id';

    // 2) time dimension:
    public const STATISTICS_DIMENSION_YEAR = 'year';
    public const STATISTICS_DIMENSION_MONTH = 'month';
    public const STATISTICS_DIMENSION_DAY = 'day';
    public const STATISTICS_DIMENSION_DATE = 'date';

    // 3) geography dimension:
    public const STATISTICS_DIMENSION_COUNTRY = 'country';
    public const STATISTICS_DIMENSION_REGION = 'region';
    public const STATISTICS_DIMENSION_CITY = 'city';

    // Metrics:
    public const STATISTICS_METRIC = 'metric';

    // Ordering:
    public const STATISTICS_ORDER_ASC = 'ASC';
    public const STATISTICS_ORDER_DESC = 'DESC';

    // File type to be used in publication object dimension.
    public const STATISTICS_FILE_TYPE_HTML = 1;
    public const STATISTICS_FILE_TYPE_PDF = 2;
    public const STATISTICS_FILE_TYPE_OTHER = 3;
    public const STATISTICS_FILE_TYPE_DOC = 4;

    // Constants used to filter time dimension to current time.
    public const STATISTICS_YESTERDAY = 'yesterday';
    public const STATISTICS_CURRENT_MONTH = 'currentMonth';

    // Set the earliest date used
    public const STATISTICS_EARLIEST_DATE = '2001-01-01';


    /**
    * Get object type string.
    *
    * @param $assocType mixed int or null (optional)
    *
    * @return mixed string or array
    */
    public function getObjectTypeString($assocType = null)
    {
        $objectTypes = $this->getReportObjectTypesArray();

        if (is_null($assocType)) {
            return $objectTypes;
        } else {
            if (isset($objectTypes[$assocType])) {
                return $objectTypes[$assocType];
            } else {
                assert(false);
            }
        }
    }

    /**
     * Get all statistics report public objects, with their
     * respective names as array values.
     *
     * @return array
     */
    protected function getReportObjectTypesArray()
    {
        return [
            Application::ASSOC_TYPE_SUBMISSION_FILE => __('submission.submit.submissionFiles')
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\statistics\PKPStatisticsHelper', '\PKPStatisticsHelper');
    foreach ([
        'STATISTICS_DIMENSION_CONTEXT_ID',
        'STATISTICS_DIMENSION_PKP_SECTION_ID',
        'STATISTICS_DIMENSION_SUBMISSION_ID',
        'STATISTICS_DIMENSION_REPRESENTATION_ID',
        'STATISTICS_DIMENSION_ASSOC_TYPE',
        'STATISTICS_DIMENSION_ASSOC_ID',
        'STATISTICS_DIMENSION_FILE_TYPE',
        'STATISTICS_DIMENSION_MONTH',
        'STATISTICS_DIMENSION_DAY',
        'STATISTICS_DIMENSION_COUNTRY',
        'STATISTICS_DIMENSION_REGION',
        'STATISTICS_DIMENSION_CITY',
        'STATISTICS_METRIC',
        'STATISTICS_ORDER_ASC',
        'STATISTICS_ORDER_DESC',
        'STATISTICS_FILE_TYPE_HTML',
        'STATISTICS_FILE_TYPE_PDF',
        'STATISTICS_FILE_TYPE_OTHER',
        'STATISTICS_FILE_TYPE_DOC',
        'STATISTICS_YESTERDAY',
        'STATISTICS_CURRENT_MONTH',
        'STATISTICS_EARLIEST_DATE',
    ] as $constantName) {
        define($constantName, constant('\PKPStatisticsHelper::' . $constantName));
    }
}
