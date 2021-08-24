<?php

/**
 * @file classes/services/PKPStatsGeoService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoService
 * @ingroup services
 *
 * @brief Helper class that encapsulates geographic statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use PKP\plugins\HookRegistry;

class PKPStatsGeoService
{
    /**
     * Get total count of geo data (countries, regions or cities) matching the given parameters
     */
    public function getTotalCount(array $args, string $scale): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsGeo::getTotalCountriesCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    /**
     * Get total metrics for every geo data (countrie, region or city), ordered desc by metrics, return just the requested offset
     */
    public function getTotalMetrics(array $args, string $scale): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsGeo::getTotalCountriesMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(StatisticsHelper::STATISTICS_METRIC_TOTAL_ORDERBY, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    /**
     * Get the sum of a set of metrics broken down by day or month
     *
     * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
     * @param array $args Filter the records to include. See self::getQueryBuilder()
     *
     */
    public function getTimeline(string $timelineInterval, array $args = []): array
    {
        $defaultArgs = array_merge($this->getDefaultArgs(), ['orderDirection' => StatisticsHelper::STATISTICS_ORDER_ASC]);
        $args = array_merge($defaultArgs, $args);
        $timelineQB = $this->getQueryBuilder($args);

        HookRegistry::call(get_class($this) . '::getTimeline::queryBuilder', [&$timelineQB, $args]);

        $timelineQO = $timelineQB
            ->getSum([$timelineInterval])
            ->orderBy($timelineInterval, $args['orderDirection']);

        $result = $timelineQO->get();

        $dateValues = [];
        foreach ($result as $row) {
            $row = (array) $row;
            $date = $row[$timelineInterval];
            if ($timelineInterval === StatisticsHelper::STATISTICS_DIMENSION_MONTH) {
                $date = substr($date, 0, 7);
            }
            $dateValues[$date] = (int) $row['metric'];
        }

        $timeline = $this->getEmptyTimelineIntervals($args['dateStart'], $args['dateEnd'], $timelineInterval);

        $timeline = array_map(function ($entry) use ($dateValues) {
            foreach ($dateValues as $date => $value) {
                if ($entry['date'] === $date) {
                    $entry['value'] = $value;
                    break;
                }
            }
            return $entry;
        }, $timeline);

        return $timeline;
    }

    /**
     * Get all time segments (months or days) between the start and end date
     * with empty values.
     *
     * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
     *
     * @return array of time segments in ASC order
     */
    public function getEmptyTimelineIntervals(string $startDate, string $endDate, string $timelineInterval): array
    {
        if ($timelineInterval === StatisticsHelper::STATISTICS_DIMENSION_MONTH) {
            $dateFormat = 'Y-m';
            $labelFormat = '%B %Y';
            $interval = 'P1M';
        } elseif ($timelineInterval === StatisticsHelper::STATISTICS_DIMENSION_DAY) {
            $dateFormat = 'Y-m-d';
            $labelFormat = Application::get()->getRequest()->getContext()->getLocalizedDateFormatLong();
            $interval = 'P1D';
        }

        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);

        $timelineIntervals = [];
        while ($startDate->format($dateFormat) <= $endDate->format($dateFormat)) {
            $timelineIntervals[] = [
                'date' => $startDate->format($dateFormat),
                'label' => strftime($labelFormat, $startDate->getTimestamp()),
                'value' => 0,
            ];
            $startDate->add(new \DateInterval($interval));
        }

        return $timelineIntervals;
    }

    /**
     * Get default parameters
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),

            // Require a context to be specified to prevent unwanted data leakage
            // if someone forgets to specify the context. If you really want to
            // get data across all contexts, pass an empty `contextId` arg.
            'contextIds' => [\PKP\core\PKPApplication::CONTEXT_ID_NONE],
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder($args = []): \PKP\services\queryBuilders\PKPStatsGeoQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsGeoQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty($args['submissionIds'])) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }
        if (!empty($args['countries'])) {
            $statsQB->filterByCountries($args['countries']);
        }
        if (!empty($args['regions'])) {
            $statsQB->filterByRegions($args['regions']);
        }
        if (!empty($args['cities'])) {
            $statsQB->filterByCities($args['cities']);
        }

        HookRegistry::call('StatsGeo::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }
}
