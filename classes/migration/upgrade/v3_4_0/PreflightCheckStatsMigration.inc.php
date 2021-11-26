<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckStatsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreflightCheckStatsMigration
 * @brief Check for common problems early in the upgrade process.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;

class PreflightCheckStatsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Clean orphaned metrics issue IDs
            // as m.assoc_id
            $orphanedIds = DB::table('metrics AS m')->leftJoin('issues AS i', 'm.assoc_id', '=', 'i.issue_id')->where('m.assoc_type', '=', Application::ASSOC_TYPE_ISSUE)->whereNull('i.issue_id')->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $issueId) {
                $this->_installer->log("Removing orphaned metrics issue ID ${issueId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::ASSOC_TYPE_ISSUE)->where('assoc_id', '=', $issueId)->delete();
            }
            // as m.assoc_object_id
            $orphanedIds = DB::table('metrics AS m')->leftJoin('issues AS i', 'm.assoc_object_id', '=', 'i.issue_id')->where('m.assoc_object_type', '=', Application::ASSOC_TYPE_ISSUE)->whereNull('i.issue_id')->distinct()->pluck('m.assoc_object_id');
            foreach ($orphanedIds as $issueId) {
                $this->_installer->log("Removing orphaned metrics issue ID ${issueId}.");
                //DB::table('metrics')->where('assoc_object_type', '=', Application::ASSOC_TYPE_ISSUE)->where('assoc_object_id', '=', $issueId)->delete();
            }
            // Clean orphaned metrics issue galley IDs
            $orphanedIds = DB::table('metrics AS m')->leftJoin('issue_galleys AS ig', 'm.assoc_id', '=', 'ig.galley_id')->where('m.assoc_type', '=', Application::ASSOC_TYPE_ISSUE_GALLEY)->whereNull('ig.galley_id')->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $issueGalleyId) {
                $this->_installer->log("Removing orphaned metrics issue galley ID ${issueGalleyId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::ASSOC_TYPE_ISSUE_GALLEY)->where('assoc_id', '=', $issueGalleyId)->delete();
            }

            // Clean orphaned metrics context IDs
            // as assoc_id
            $contextDao = Application::getContextDAO();
            $orphanedIds = DB::table('metrics AS m')->leftJoin($contextDao->tableName . ' AS c', 'm.assoc_id', '=', 'c.' . $contextDao->primaryKeyColumn)->where('m.assoc_type', '=', Application::getContextAssocType())->whereNull('c.' . $contextDao->primaryKeyColumn)->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $contextId) {
                $this->_installer->log("Removing orphaned metrics context (journal or press) ID ${contextId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::getContextAssocType())->where('assoc_id', '=', $contextId)->delete();
            }
            // as context_id
            $orphanedIds = DB::table('metrics AS m')->leftJoin($contextDao->tableName . ' AS c', 'm.context_id', '=', 'c.' . $contextDao->primaryKeyColumn)->whereNull('c.' . $contextDao->primaryKeyColumn)->distinct()->pluck('m.context_id');
            foreach ($orphanedIds as $contextId) {
                $this->_installer->log("Removing orphaned metrics context (journal, press or server) ID ${contextId}.");
                //DB::table('metrics')->where('context_id', '=', $contextId)->delete();
            }

            // Clean orphaned metrics submission IDs
            // as assoc_id
            $orphanedIds = DB::table('metrics AS m')->leftJoin('submissions AS s', 'm.assoc_id', '=', 's.submission_id')->where('m.assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION)->whereNull('s.submission_id')->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $submissionId) {
                $this->_installer->log("Removing orphaned metrics submission ID ${submissionId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION)->where('assoc_id', '=', $submissionId)->delete();
            }
            // as submission_id
            $orphanedIds = DB::table('metrics AS m')->leftJoin('submissions AS s', 'm.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->distinct()->pluck('m.submission_id');
            foreach ($orphanedIds as $submissionId) {
                $this->_installer->log("Removing orphaned metrics submission ID ${submissionId}.");
                //DB::table('metrics')->where('submission_id', '=', $submissionId)->delete();
            }

            // Clean orphaned metrics submission file IDs
            $orphanedIds = DB::table('metrics AS m')->leftJoin('submission_files AS sf', 'm.assoc_id', '=', 'sf.submission_file_id')->where('m.assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE)->whereNull('sf.submission_file_id')->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $submissionFileId) {
                $this->_installer->log("Removing orphaned metrics submission file ID ${submissionFileId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE)->where('assoc_id', '=', $submissionFileId)->delete();
            }
            // Clean orphaned metrics submission supp file IDs
            $orphanedIds = DB::table('metrics AS m')->leftJoin('submission_files AS sf', 'm.assoc_id', '=', 'sf.submission_file_id')->where('m.assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)->whereNull('sf.submission_file_id')->distinct()->pluck('m.assoc_id');
            foreach ($orphanedIds as $submissionSuppFileId) {
                $this->_installer->log("Removing orphaned metrics submission supplementary file ID ${submissionSuppFileId}.");
                //DB::table('metrics')->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)->where('assoc_id', '=', $submissionSuppFileId)->delete();
            }

            // Clean orphaned metrics representation IDs
            $representationDao = Application::getRepresentationDAO();
            $orphanedIds = DB::table('metrics AS m')->leftJoin($representationDao->tableName . ' AS r', 'm.representation_id', '=', 'r.' . $representationDao->primaryKeyColumn)->whereNull('r.' . $representationDao->primaryKeyColumn)->distinct()->pluck('m.representation_id');
            foreach ($orphanedIds as $representationId) {
                $this->_installer->log("Removing orphaned metrics representation (article galley or presentation format) ID ${representationId}.");
                //DB::table('metrics')->where('representation_id', '=', $representationId)->delete();
            }

            // m.pkp_section_id will not be considered here, because it will be not migrated

            // Inform about probably old and not anymore supported assoc_type in the DB table metrics
            $oldAssocTypes = DB::table('metrics AS m')->whereNotIn('m.assoc_type', [Application::getContextAssocType(), Application::ASSOC_TYPE_ISSUE, Application::ASSOC_TYPE_ISSUE_GALLEY, Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER])->distinct()->pluck('m.assoc_type');
            foreach ($oldAssocTypes as $oldAssocType) {
                $this->_installer->log("The DB table metrics contains old and not anymore supported assoc_type ${oldAssocType} that will be not migrated to new metrics tables.");
            }
        } catch (\Exception $e) {
            if ($fallbackVersion = $this->setFallbackVersion()) {
                $this->_installer->log("A pre-flight check failed. The software was successfully upgraded to ${fallbackVersion} but could not be upgraded further (to " . $this->_installer->newVersion->getVersionString() . '). Check and correct the error, then try again.');
            }
            throw ($e);
        }
    }

    public function down(): void
    {
        if ($fallbackVersion = $this->setFallbackVersion()) {
            $this->_installer->log("An upgrade step failed! Fallback set to ${fallbackVersion}. Check and correct the error and try the upgrade again. We recommend restoring from backup, though you may be able to continue without doing so.");
            // Prevent further downgrade migrations from executing.
            $this->_installer->migrations = [];
        }
    }

    /**
     * Store the fallback version in the database, permitting resumption of partial upgrades.
     *
     * @return ?string Fallback version, if one was identified
     */
    protected function setFallbackVersion(): ?string
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            return $fallbackVersion;
        }
        return null;
    }
}
