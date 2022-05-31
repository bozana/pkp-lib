<?php

/**
 * @file classes/services/queryBuilders/PKPStatsPublicationQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics_submission table.
 */

namespace PKP\services\queryBuilders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\plugins\HookRegistry;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsPublicationQueryBuilder extends PKPStatsQueryBuilder
{
    /**
     *Include records for one of these object types:
     * ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_SUBMISSION_FILE, ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER
     */
    protected array $assocTypes = [];

    /** Include records for these file types: STATISTICS_FILE_TYPE_* */
    protected array $fileTypes = [];

    /** Include records from for these sections (or series in OMP) */
    protected array $sectionIds = [];

    /** Include records for these submissions */
    protected array $submissionIds = [];

    /** Include records for these representations (galley or publication format) */
    protected array $representationIds = [];

    /** Include records for these submission files */
    protected array $submissionFileIds = [];


    /**
     * Set the submissions to get records for
     */
    public function filterBySubmissions(array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Set the representations to get records for
     */
    public function filterByRepresentations(array $representationIds): self
    {
        $this->representationIds = $representationIds;
        return $this;
    }

    /**
     * Set the files to get records for
     */
    public function filterBySubmissionFiles(array $submissionFileIds): self
    {
        $this->submissionFileIds = $submissionFileIds;
        return $this;
    }

    /**
     * Set the assocTypes to get records for
     */
    public function filterByAssocTypes(array $assocTypes): self
    {
        $this->assocTypes = $assocTypes;
        return $this;
    }

    /**
     * Set the galley file type to get records for
     */
    public function filterByFileTypes(array $fileTypes): self
    {
        $this->fileTypes = $fileTypes;
        return $this;
    }

    /**
     * Get submission IDs
     */
    public function getSubmissionIds(): Builder
    {
        return $this->_getObject()
            ->select([PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID])
            ->distinct();
    }

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     */
    protected function _getObject(): Builder
    {
        $q = DB::table('metrics_submission');

        if (!empty($this->contextIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        if (!empty($this->submissionIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, $this->submissionIds);
        }

        if (!empty($this->assocTypes)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE, $this->assocTypes);
        }

        if (!empty($this->fileTypes)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE, $this->fileTypes);
        }

        if (!empty($this->representationIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_REPRESENTATION_ID, $this->representationIds);
        }

        if (!empty($this->submissionFileIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID, $this->submissionFileIds);
        }

        $q->whereBetween(PKPStatisticsHelper::STATISTICS_DIMENSION_DATE, [$this->dateStart, $this->dateEnd]);

        if ($this->limit > 0) {
            $q->limit($this->limit);
            if ($this->offset > 0) {
                $q->offset($this->offset);
            }
        }

        HookRegistry::call('StatsPublication::queryObject', [&$q, $this]);

        return $q;
    }
}
