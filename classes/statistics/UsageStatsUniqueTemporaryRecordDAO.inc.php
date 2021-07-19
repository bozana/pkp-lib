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

use Illuminate\Support\Facades\DB;
use PKP\config\Config;

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
            'country' => $entryData->country,
            'region' => $entryData->region,
            'city' => $entryData->city,
            'institution_ids' => implode('-', $entryData->institutionIds),
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
     */
    public function removeUniqueClicks()
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            // TO-DO
        } else {
            DB::statement("DELETE usu FROM {$this->table} usu JOIN {$this->table} usut ON (usut.load_id = usu.load_id AND usut.ip = usu.ip AND usut.user_agent = usu.user_agent AND usut.context_id = usu.context_id AND usut.submission_id = usu.submission_id) WHERE TIMESTAMPDIFF(HOUR, usu.date, usut.date) = 0 AND usu.line_number < usut.line_number");
        }
    }
}
