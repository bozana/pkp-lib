<?php

/**
 * @file classes/services/PKPStatsService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsService
 * @ingroup services
 *
 * @brief Abstract class that contains common statistics services business logic
 */

namespace PKP\services;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use PKP\plugins\HookRegistry;

abstract class PKPStatsService
{
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
     * Get the sum of all matching records,
     * grouped by $groupBy,
     * ordered by $orderBy, one or more columns and their directions,
     * filtered by $args.
     *
     * See child classes for detailed parameter explanation.
     *
     * @param array $groupBy
     *  see getStatsColumns() of the child service for what columns can be selected
     * 	assumes the given columns are correct i.e. exist
     * @param array $orderBy
     * 	column => StatisticsHelper::STATISTICS_ORDER_ASC or StatisticsHelper::STATISTICS_ORDER_DESC
     * @param array $args
     *  see prepareStatsArgs() of the child service for what arguments can be provided
     *
     */
    public function getMetrics(array $groupBy = [], array $orderBy = [], array $args = []): \Illuminate\Support\Collection
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call(get_class($this) . '::getMetrics::queryBuilder', [&$metricsQB, $args]);

        $metricsQB = $metricsQB->getSum($groupBy);
        if (!empty($orderBy)) {
            foreach ($orderBy as $orderColumn => $direction) {
                $direction === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
                $metricsQB->orderBy($orderColumn, $direction);
            }
        }

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }
        /*
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ get stats context service metrics qb: ++++\n", true);
        $current .= print_r($metricsQB->toSql(), true);
        $current .= print_r($metricsQB->getBindings(), true);
        file_put_contents($file, $current);
        */
        //return $metricsQB;

        return $metricsQB->get();
        /*
        $result = $metricsQB->get();

        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ get stats service metrics result: ++++\n", true);
        $current .= print_r($result, true);
        file_put_contents($file, $current);
        return $result;
        */
    }

    /**
     * Get columns used by this service, to get the metrics.
     *
     */
    abstract public function getStatsColumns(): array;

    /**
     * Prepare filters passed by the user to be used by this service.
     */
    abstract public function prepareStatsArgs(array $filters = []): array;

    /**
     * Get default parameters
     *
     */
    abstract public function getDefaultArgs(): array;

    /**
     * Get a QueryBuilder object with the passed args
     *
     * See child classes for detailed parameter explanation.
     *
     *
     */
    abstract public function getQueryBuilder(array $args = []): \PKP\services\queryBuilders\PKPStatsQueryBuilder;
}
