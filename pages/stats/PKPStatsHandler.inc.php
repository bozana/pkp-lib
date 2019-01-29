<?php

/**
 * @file pages/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsHandler
 * @ingroup pages_stats
 *
 * @brief Handle requests for statistics pages.
 */

import('classes.handler.Handler');

class PKPStatsHandler extends Handler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
			array('articles')
		);
	}

	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods.
	//
	/**
	 * Display article statistics page
	 * @param $request PKPRequest
	 * @param $args array
	 */
	public function articles($args, $request) {
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();

		if (!$context) {
			$dispatcher->handle404();
		}

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$dateStart = date('Y-m-d', strtotime('-31 days'));
		$dateEnd = date('Y-m-d', strtotime('yesterday'));
		$count = 20;

		$params = [
			'count' => $count,
			'from' => str_replace('-', '', $dateStart),
			'to' => str_replace('-', '', $dateEnd),
			'dimension' => STATISTICS_DIMENSION_DAY,
		];

		$statsService = ServicesContainer::instance()->get('stats');

		// Get total stats
		$totalStatsRecords = $statsService->getTotalStats($context->getId(), $params);
		$totalStats = $statsService->getTotalStatsProperties($totalStatsRecords, [
			'request' => $request,
			'slimRequest' => $slimRequest,
			'params' => $params
		]);

		// Get submission stats
		$submissionsRecords = $statsService->getOrderedSubmissions($context->getId(), $params);

		$items = [];
		if (!empty($submissionsRecords)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
				'params' => $params
			);
			$slicedSubmissionsRecords = array_slice($submissionsRecords, 0, $params['count']);
			foreach ($slicedSubmissionsRecords as $submissionsRecord) {
				$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
				$submission = $publishedArticleDao->getById($submissionsRecord['submission_id']);
				$items[] = $statsService->getSummaryProperties($submission, $propertyArgs);
			}
		}

		import('controllers.list.submissions.SubmissionsListHandler');
		import('lib.pkp.controllers.stats.StatsComponentHandler');
		$statsHandler = new StatsComponentHandler(
			$dispatcher->url($request, ROUTE_API, $context->getPath(), 'stats/articles'),
			[
				'timeSegment' => 'daily',
				'timeSegments' => $totalStats['timeSegments'],
				'items' => $items,
				'itemsMax' => count($submissionsRecords),
				'tableColumns' => [
					[
						'name' => 'title',
						'label' => __('submission.title'),
					],
					[
						'name' => 'abstractViews',
						'label' => __('submission.abstractViews'),
						'value' => 'abstractViews',
					],
					[
						'name' => 'totalGalleyViews',
						'label' => __('stats.galleyViews'),
						'value' => 'totalGalleyViews',
					],
					[
						'name' => 'pdf',
						'label' => __('stats.pdf'),
						'value' => 'pdf',
					],
					[
						'name' => 'html',
						'label' => __('stats.html'),
						'value' => 'html',
					],
					[
						'name' => 'other',
						'label' => __('common.other'),
						'value' => 'other',
					],
					[
						'name' => 'total',
						'label' => __('stats.total'),
						'value' => 'total',
						'orderBy' => 'total',
						'initialOrderDirection' => true,
					],
				],
				'count' => $count,
				'dateStart' => $dateStart,
				'dateEnd' => $dateEnd,
				'dateRangeOptions' => [
					[
						'dateStart' => date('Y-m-d', strtotime('-91 days')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last90Days'),
					],
					[
						'dateStart' => $dateStart,
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last30Days'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime('-12 months')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last12Months'),
					],
					[
						'dateStart' => '',
						'dateEnd' => '',
						'label' => __('stats.dateRange.allDates'),
					],
				],
				'filters' => [
					'sectionIds' => [
						'heading' => __('section.sections'),
						'filters' => SubmissionsListHandler::getSectionFilters(),
					],
				],
				'orderBy' => 'total',
				'orderDirection' => true,
			]
		);

		$data = array(
			'itemsMax' => count($submissionsRecords),
			'items' => $items,
		);

		$templateMgr->assign('statsData', $statsHandler->getConfig());

		$templateMgr->display('stats/articles.tpl');
	}
}
