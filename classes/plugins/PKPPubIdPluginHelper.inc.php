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
	 * 	OJS Issue, Article, Representation or SubmissionFile
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
	 * 	OJS Issue, Article, Representation or SubmissionFile
	 */
	function setLinkActions($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$linkActions = $pubIdPlugin->getLinkActions($pubObject);
				foreach ($linkActions as $linkActionName => $linkAction) {
					$form->setData($linkActionName, $linkAction);
					unset($linkAction);
				}
			}
		}
	}

	/**
	 * Init the additional form fields from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object
	 * 	OJS Issue, Article, Representation or SubmissionFile
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
	 */
	function readInputData($contextId, $form) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$form->readUserVars($pubIdPlugin->getFormFieldNames());
			}
		}
	}

	/**
	 * Read the the public identifiers' assign form field data.
	 * @param $form object Form containing the assign check box
	 * 	OJS ..., IssueEntryPublicationMetadataForm, ... or ...
	 */
	function readAssignInputData($form) {
		$application = Application::getApplication();
		$request = $application->getRequest();
		$context = $request->getContext();
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $context->getId());
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$form->readUserVars(array($pubIdPlugin->getAssignFormFieldName()));
			}
		}
	}

	/**
	 * Set the additional data from public identifier plugins.
	 * @param $contextId integer
	 * @param $form object PublicIdentifiersForm
	 * @param $pubObject object An Article, Issue, or ArticleGalley
	 * 	OJS Issue, Article, Representation or SubmissionFile
	 */
	function execute($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				// Public ID data can only be changed as long
				// as no ID has been generated.
				$storedId = $pubObject->getStoredPubId($pubIdPlugin->getPubIdType());
				if (!$storedId) {
					$fieldNames = $pubIdPlugin->getFormFieldNames();
					foreach ($fieldNames as $fieldName) {
						$data = $form->getData($fieldName);
						$pubObject->setData($fieldName, $data);
					}
				}
			}
		}
	}

	function assignPubId($contextId, $form, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				if ($form->getData($pubIdPlugin->getAssignFormFieldName())) {
					$pubId = $pubIdPlugin->getPubId($pubObject); // save it here or in the form?
				}
			}
		}
	}

	/**
	 * Clear a pubId from a pubObject.
	 * @param $contextId integer
	 * @param $pubIdPlugInClassName string
	 * @param $pubObject object
	 * 	OJS Issue, Article, Representation or SubmissionFile
	 */
	function clearPubId($contextId, $pubIdPlugInClassName, $pubObject) {
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				if (get_class($pubIdPlugin) == $pubIdPlugInClassName) {
					// clear the pubId:
					// delte the pubId from the DB
					$pubObjectType = $pubIdPlugin->getPubObjectType($pubObject);
					$daos = $pubIdPlugin->getDAOs();
					$dao = $daos[$pubObjectType];
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
