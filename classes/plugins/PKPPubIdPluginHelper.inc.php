<?php

/**
 * @file classes/plugins/PKPPubIdPluginHelper.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPubIdPluginHelper
 * @ingroup plugins
 *
 * @brief Helper class for public identifiers plugins
 */


class PKPPubIdPluginHelper {

	/**
	 * Validate the additional form fields from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object
	 * 	OJS Article, Issue, or SubmissionFile
	 */
	function validate($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$fieldNames = $pubIdPlugin->getFormFieldNames();
				foreach ($fieldNames as $fieldName) {
					$fieldValue = $form->getData($fieldName);
					$errorMsg = '';
					if(!$pubIdPlugin->verifyData($fieldName, $fieldValue, $pubObject, $contextId, $errorMsg)) {
						$form->addError($fieldName, $errorMsg);
					}
				}
			}
		}
	}

	/**
	 * Set from link actions.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object
	 * 	OJS Article, Issue, or SubmissionFile
	 */
	function setLinkActions($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$linkActions = $pubIdPlugin->getLinkActions($pubObject);
				foreach ($linkActions as $linkActionName => $linkAction) {
					$form->setData($linkActionName, $linkAction);
				}
			}
		}
	}

	/**
	 * Init the additional form fields from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object
	 * 	OJS Article, Issue, or SubmissionFile
	 */
	function init($contextId, $form, $pubObject) {
		if (isset($pubObject)) {
			$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
			if (is_array($pubIdPlugins)) {
				foreach ($pubIdPlugins as $pubIdPlugin) {
					$fieldNames = $pubIdPlugin->getFormFieldNames();
					foreach ($fieldNames as $fieldName) {
						$form->setData($fieldName, $pubObject->getData($fieldName));
					}
				}
			}
		}
	}

	/**
	 * Read the additional input data from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * 	OJS IssueForm, MetadataForm, or SubmissionFile
	 */
	function readInputData($contextId, $form) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$form->readUserVars($pubIdPlugin->getFormFieldNames());
				$clearFormFieldName = 'clear_' . $pubIdPlugin->getPubIdType();
				$form->readUserVars(array($clearFormFieldName));
			}
		}
	}

	/**
	 * Set the additional data from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object An Article, Issue, or ArticleGalley
	 * 	OJS Article, Issue, or SubmissionFile
	 */
	function execute($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				// Public ID data can only be changed as long
				// as no ID has been generated.
				$storedId = $pubObject->getStoredPubId($pubIdPlugin->getPubIdType());
				$fieldNames = $pubIdPlugin->getFormFieldNames();
				$excludeFormFieldName = $pubIdPlugin->getExcludeFormFieldName();
				foreach ($fieldNames as $fieldName) {
					$data = $form->getData($fieldName);
					// if the exclude checkbox is unselected
					if ($fieldName == $excludeFormFieldName && !isset($data))  {
						$data = 0;
					}
					$pubObject->setData($fieldName, $data);
					if ($data) {
						$this->clearPubId($pubIdPlugin, $pubObject);
					}
				}
			}
		}
	}

	/**
	 * Clear a pubId from a pubObject.
	 * @param $clearParam string Defines which pub id (plugin)
	 * @param $pubObject object
	 * 	OJS Article, Issue, or SubmissionFile
	 */
	function clearPubId($clearParam, $pubObject) {
$file = 'debug.txt';
$current = file_get_contents($file);
$current .= print_r('clear pub id', true);
file_put_contents($file, $current);
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				if ($pubIdPlugin->getClearActionName() == $clearParam) {
					// clear the pubId:
					// delte the pubId from the DB
					$pubObjectType = $pubIdPlugin->getPubObjectType($pubObject);
					$daos = $pubIdPlugin->getDAOs();
					$dao = DAORegistry::getDAO($daos[$pubObjectType]);
					$dao->deletePubId($pubObject->getId(), $pubIdPlugin->getPubIdType());
					// set the object setting/data 'pub-id::...' to null, in order
					// not to be considered in the DB object update later in the form
					$settingName = 'pub-id::'.$pubIdPlugin->getPubIdType();
					$pubObject->setData($settingName, null);
				}
			}
		}
	}

}

?>
