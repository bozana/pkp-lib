<?php

/**
 * @file controllers/grid/representations/form/AssignPublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AssignPublicIdentifiersForm
 * @ingroup controllers_grid_representations_form_AssignPublicIdentifiersForm
 *
 * @brief Displays a assign pub id form.
 */

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.plugins.PKPPubIdPluginHelper');

class AssignPublicIdentifiersForm extends Form {

	/** @var int The context id */
	var $_contextId;

	/** @var object The pub object the identifiers are edited of
	 * (Submission, Issue)
	 */
	var $_pubObject;

	/** @var boolean */
	var $_approval;

	/**
	 * @var string Confirmation to display.
	 */
	var $_confirmationText;

	/**
	 * Constructor.
	 * @param $pubObject object
	 * @param $stageId integer
	 * @param $formParams array
	 */
	function AssignPublicIdentifiersForm($pubObject, $approval, $confirmationText) {
		parent::Form('controllers/grid/pubIds/form/assignPublicIdentifiersForm.tpl');

		$this->_pubObject = $pubObject;
		$this->_approval = $approval;
		$this->_confirmationText = $confirmationText;

		$request = Application::getRequest();
		$context = $request->getContext();
		$this->_contextId = $context->getId();

		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Fetch the HTML contents of the form.
	 * @param $request PKPRequest
	 * return string
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$pubIdPlugins =& PluginRegistry::loadCategory('pubIds', true, $this->getContextId());
		$templateMgr->assign('pubIdPlugins', $pubIdPlugins);
		$templateMgr->assign('pubObject', $this->getPubObject());
		$templateMgr->assign('approval', $this->getApproval());
		$templateMgr->assign('confirmationText', $this->getConfirmationText());
		return parent::fetch($request);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the pub object
	 * @return object
	 */
	function getPubObject() {
		return $this->_pubObject;
	}

	/**
	 * Get the stage id
	 * @return integer WORKFLOW_STAGE_ID_
	 */
	function getApproval() {
		return $this->_approval;
	}

	/**
	 * Get the context id
	 * @return integer
	 */
	function getContextId() {
		return $this->_contextId;
	}

	/**
	 * Get the extra form parameters.
	 */
	function getConfirmationText() {
		return $this->_confirmationText;
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->readAssignInputData($this);
	}

	/**
	 * Save the metadata and store the catalog data for this published
	 * monograph.
	 */
	function execute($request) {
		parent::execute($request);

		$pubObject = $this->getPubObject();
		$pubIdPluginHelper = new PKPPubIdPluginHelper();
		$pubIdPluginHelper->assignPubId($this->getContextId(), $this, $pubObject);

	}

}

?>
