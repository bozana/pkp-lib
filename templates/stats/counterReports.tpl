{**
 * templates/stats/counterR5TSVReport.tpl
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Add and edit institutions
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
        {translate key="manager.statistics.counterR5Reports"}
	</h1>
    <div class="counterReportsListPage">
	<counter-reports-list-panel
		v-bind="components.counterReportsListPanel"
		@set="set"
	/>
    </div>
{/block}
