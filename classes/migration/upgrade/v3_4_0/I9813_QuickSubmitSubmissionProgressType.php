<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9813_QuickSubmitSubmissionProgressType.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9813_QuickSubmitSubmissionProgressType
 *
 * @brief Fix the old submission_progress values inserted by QuickSubmit plugin,
 *   from an int to a string to match the new step ids
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\install\DowngradeNotSupportedException;

class I9813_QuickSubmitSubmissionProgressType extends \PKP\migration\Migration
{
    public function up(): void
    {
        $thresholdVersion = '1.0.7.1';
        /** @var VersionDAO $versionDao */
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $installedQuickSubmitPluginVersion = $versionDao->getCurrentVersion('plugins.importexport', 'quickSubmit');
        if ($installedQuickSubmitPluginVersion->compare($thresholdVersion) === 0) {
            throw new Exception('You have the QuickSubmit version 1.0.7.1. Please upgrade the QuickSubmit plugin first, bofore running this upgrade.');
        }

        foreach ($this->getStepMap() as $oldValue => $newValue) {
            DB::table('submissions')
                ->where('submission_progress', $oldValue)
                ->update([
                    'submission_progress' => $newValue,
                ]);
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Mapping of inserted QuickSubmit plugin's values to the new step ids
     *
     * @return array [oldValue => newValue]
     */
    protected function getStepMap(): array
    {
        return [
            0 => '',
            1 => 'start',
        ];
    }
}
