<?php

/**
 * @file classes/migration/upgrade/3_4_0/I6192_AnnouncementTypes.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6192_AnnouncementTypes
 * @brief Describe upgrade/downgrade operations for DB table usage_stats_temporary_records.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class I6192_AnnouncementTypes extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		// pkp/pkp-lib#6192: remove column setting_type
		Capsule::schema()->table('announcement_type_settings', function(Blueprint $table) {
			$table->dropColumn('setting_type');
		});
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
	}
}
