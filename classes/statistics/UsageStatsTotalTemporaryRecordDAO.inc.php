<?php

/**
 * @file classes/statistics/UsageStatsTotalTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsTotalTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding total temporary usage statistics records.
 */

namespace PKP\statistics;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;

class UsageStatsTotalTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_total_temporary_records';


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
     *  canonicalURL
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
            'canonical_url' => $entryData->canonicalUrl,
            'issue_id' => !empty($entryData->issueId) ? $entryData->issueId : null,
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
     * Remove Double Clicks
     */
    public function removeDoubleClicks()
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            // TO-DO
        } else {
            DB::statement("DELETE ust FROM {$this->table} ust JOIN {$this->table} ustt ON (ustt.load_id = ust.load_id AND ustt.ip = ust.ip AND ustt.user_agent = ust.user_agent AND ustt.canonical_url = ust.canonical_url) WHERE TIMESTAMPDIFF(SECOND, ust.date, ustt.date) < 30 AND TIMESTAMPDIFF(SECOND, ust.date, ustt.date) > 0 AND ust.line_number < ustt.line_number");
        }
    }
}
