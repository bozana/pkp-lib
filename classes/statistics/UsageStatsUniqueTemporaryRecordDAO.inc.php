<?php

/**
 * @file classes/statistics/UsageStatsUniqueTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsUniqueTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique temporary usage statistics records.
 */

namespace PKP\statistics;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class UsageStatsUniqueTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_unique_temporary_records';


    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Add the passed usage statistic record.
     *
     * @param \stdClass $entryData [
     * 	issue_id
     *  time
     *  ip
     *  canonicalUrl
     *  contextId
     *  submissionId
     *  representationId
     *  assocType
     *  assocId
     *  fileType
     *  userAgent
     *  country
     *  region
     *  city
     *  instituionIds
     * ]
     */
    public function insert(\stdClass $entryData, int $lineNumber, string $loadId)
    {
        DB::table($this->table)->insert([
            'date' => $entryData->time,
            'ip' => $entryData->ip,
            'user_agent' => substr($entryData->userAgent, 0, 255),
            'line_number' => $lineNumber,
            'issue_id' => !empty($entryData->ssueId) ? $entryData->issueId : null,
            'context_id' => $entryData->contextId,
            'submission_id' => $entryData->submissionId,
            'representation_id' => $entryData->representationId,
            'assoc_type' => $entryData->assocType,
            'assoc_id' => $entryData->assocId,
            'file_type' => $entryData->fileType,
            'country' => !empty($entryData->country) ? $entryData->country : '',
            'region' => !empty($entryData->region) ? $entryData->region : '',
            'city' => !empty($entryData->city) ? $entryData->city : '',
            'institution_ids' => json_encode($entryData->institutionIds),
            'load_id' => $loadId,
        ]);
    }

    /**
     * Delete all temporary records associated
     * with the passed load id.
     */
    public function deleteByLoadId(string $loadId)
    {
        DB::table($this->table)->where('load_id', '=', $loadId)->delete();
    }

    /**
     * Remove Unique Clicks
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#counting
     */
    public function removeUniqueClicks()
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            // TO-DO
        } else {
            DB::statement("DELETE FROM {$this->table} usu WHERE EXISTS (SELECT * FROM (SELECT 1 FROM {$this->table} usut WHERE usut.load_id = usu.load_id AND usut.ip = usu.ip AND usut.user_agent = usu.user_agent AND usut.context_id = usu.context_id AND usut.submission_id = usu.submission_id AND TIMESTAMPDIFF(HOUR, usu.date, usut.date) = 0 AND usu.line_number < usut.line_number) AS tmp)");
        }
    }

    public function loadMetricsCounterSubmissionDaily(string $loadId): bool
    {
        // s. https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
        $result = DB::statement(
            "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
            ",
            [$loadId]
        );
        if ($result) {
            return DB::statement(
                "
                INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                        FROM {$this->table}
                        WHERE load_id = ? AND assoc_type = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
        }
        return $result;
    }

    public function loadMetricsCounterSubmissionGeoDaily(string $loadId): bool
    {
        $result = DB::statement(
            "
            INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
            ",
            [$loadId]
        );
        if ($result) {
            return DB::statement(
                "
                INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                        FROM {$this->table}
                        WHERE load_id = ? AND assoc_type = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
        }
        return $result;
    }

    public function loadMetricsCounterSubmissionInstitutionDaily(string $loadId): bool
    {
        /*
        $institutionIds1 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->get();
        $institutionIds2 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->get()->toArray();
        $institutionIds3 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->pluck('institution_ids');
        */

        /* if JSON should be used:
        $institutionIds = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->pluck('institution_ids')
            ->toArray();

        $institutionIdsResult = array_map(function ($json) {
            return json_decode($json, true);
        }, $institutionIds);
        $institutionIdsUnique = array_unique(call_user_func_array('array_merge', $institutionIdsResult));
        foreach ($institutionIdsUnique as $institutionId) {
            $result = DB::statement(
                '
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT t.load_id, t.context_id, t.submission_id, t.date, ' . $institutionId . ' as institution_id, 0 as metric_investigations, t.metric, 0 as metric_requests, 0 as metric_requests_unique
                    FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, JSON_CONTAINS(institution_ids, \'' . $institutionId . '\') as institution_tmp, count(*) as metric
                        FROM usage_stats_unique_temporary_records
                        WHERE load_id = ? AND submission_id IS NOT NULL AND JSON_CONTAINS(institution_ids, \'' . $institutionId . '\')
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_tmp) AS t
                ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ',
                [$loadId]
            );
            if (!$result) {
                return false;
            }
            $result = DB::statement(
                '
                    INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT t.load_id, t.context_id, t.submission_id, t.date, ' . $institutionId . ' as institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, t.metric
                        FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, JSON_CONTAINS(institution_ids, \'' . $institutionId . '\') as institution_tmp, count(*) as metric
                            FROM usage_stats_unique_temporary_records
                            WHERE load_id = ? AND assoc_type = ?  AND JSON_CONTAINS(institution_ids, \'' . $institutionId . '\')
                            GROUP BY load_id, context_id, submission_id, DATE(date), institution_tmp) AS t
                    ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                    ',
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
        */

        $statsInstitutionDao = DAORegistry::getDAO('UsageStatsInstitutionTemporaryRecordDAO'); /* @var $statsInstitutionDao UsageStatsInstitutionTemporaryRecordDAO */
        $institutionIds = $statsInstitutionDao->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            $result = DB::statement(
                "
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (
                        SELECT usut.load_id, usut.context_id, usut.submission_id, DATE(usut.date) as date, usit.institution_id, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                        FROM {$this->table} usut
                        JOIN usage_stats_institution_temporary_records usit on (usit.load_id = usut.load_id AND usit.line_number = usut.line_number)
                        WHERE usut.load_id = ? AND submission_id IS NOT NULL AND usit.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ",
                [$loadId, (int) $institutionId]
            );
            if (!$result) {
                return false;
            }
            $result = DB::statement(
                "
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (
                        SELECT usut.load_id, usut.context_id, usut.submission_id, DATE(usut.date) as date, usit.institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                        FROM {$this->table} usut
                        JOIN usage_stats_institution_temporary_records usit on (usit.load_id = usut.load_id AND usit.line_number = usut.line_number)
                        WHERE usut.load_id = ? AND usut.assoc_type = ? AND usit.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE, (int) $institutionId]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
    }
}
