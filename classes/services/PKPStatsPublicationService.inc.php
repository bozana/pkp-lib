<?php

/**
 * @file classes/services/PKPStatsPublicationService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationService
 * @ingroup services
 *
 * @brief Helper class that encapsulates publication statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use PKP\plugins\HookRegistry;
use PKP\statistics\PKPStatisticsHelper;
use stdClass;

class PKPStatsPublicationService extends PKPStatsService
{
    /**
     * A callback to be used with array_map() to return all
     * submission IDs from the records.
     */
    public function filterSubmissionIds(stdClass $record): int
    {
        return $record->submission_id;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for a PDF file.
     */
    public function filterRecordPdf(stdClass $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_PDF;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for a HTML file.
     */
    public function filterRecordHtml(stdClass $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_HTML;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for Other (than PDF and HTML) file.
     */
    public function filterRecordOther(stdClass $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_OTHER;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for absract.
     */
    public function filterRecordAbstract(stdClass $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION;
    }

    /**
     * Get columns used by the this service,
     * to get publications metrics.
     */
    public function getStatsColumns(): array
    {
        return [
            PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE,
            PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE,
            PKPStatisticsHelper::STATISTICS_DIMENSION_REPRESENTATION_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_ID,
            PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
            PKPStatisticsHelper::STATISTICS_DIMENSION_DAY,
        ];
    }

    public function getTotalCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]]);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    public function getTotalMetrics(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    public function getMetricsByType(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getMetricsByType::queryBuilder', [&$metricsQB, $args]);

        // get abstract, pdf, html and other views for the submission
        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE, PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE];

        $metricsQB = $metricsQB->getSum($groupBy);
        return $metricsQB->get()->toArray();
    }

    public function getTotalFilesCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]]);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalFilesCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    public function getTotalFilesMetrics(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getFilesMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    /**
     * @copydoc PKPStatsServie::prepareStatsArgs()
     */
    public function prepareStatsArgs(array $filters = []): array
    {
        $args = [];
        $validColumns = $this->getStatsColumns();
        foreach ($filters as $filterColumn => $value) {
            if (!in_array($filterColumn, $validColumns)) {
                continue;
            }
            switch ($filterColumn) {
                case PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID:
                    $args['contextIds'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE:
                    $args['assocTypes'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID:
                    $args['submissionIds'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_REPRESENTATION_ID:
                    $args['representationIds'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_ID:
                    $args['fileIds'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE:
                    $args['fileTypes'] = $value;
                    break;
                case PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH:
                case PKPStatisticsHelper::STATISTICS_DIMENSION_DAY:
                    $args['timeInterval'] = $filterColumn;
                    if (isset($value['from'])) {
                        $args['dateStart'] = $value['from'];
                    }
                    if (isset($value['to'])) {
                        $args['dateEnd'] = $value['to'];
                    }
                    break;
            }
        }
        return $args;
    }

    /**
     * @copydoc PKPStatsService::getDefaultArgs()
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => PKPStatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),

            // Require a context to be specified to prevent unwanted data leakage
            // if someone forgets to specify the context. If you really want to
            // get data across all contexts, pass an empty `contextId` arg.
            'contextIds' => [\PKP\core\PKPApplication::CONTEXT_ID_NONE],
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     *
     * @param array $args See self::prepareStatsArgs()
     *
     */
    public function getQueryBuilder(array $args = []): \PKP\services\queryBuilders\PKPStatsPublicationQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsPublicationQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty(($args['submissionIds']))) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }

        if (!empty($args['assocTypes'])) {
            $statsQB->filterByAssocTypes($args['assocTypes']);
        }

        if (!empty($args['fileTypes'])) {
            $statsQB->filterByFileTypes(($args['fileTypes']));
        }

        if (!empty(($args['representationIds']))) {
            $statsQB->filterByRepresentations($args['representationIds']);
        }

        if (!empty(($args['fileIds']))) {
            $statsQB->filterByFiles($args['fileIds']);
        }

        HookRegistry::call('Stats::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }
}
