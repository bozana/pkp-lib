<?php

/**
 * @file classes/submission/Representation.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Representation
 * @ingroup submission
 *
 * @brief A submission's representation (Publication Format, Galley, ...)
 */

import('lib.pkp.classes.core.DataObject');

class Representation extends DataObject {
	/**
	 * Constructor.
	 */
	function Representation() {
		// Switch on meta-data adapter support.
		$this->setHasLoadableAdapters(true);

		parent::DataObject();
	}

	/**
	 * Get sequence of format in format listings for the submission.
	 * @return float
	 */
	function getSeq() {
		return $this->getData('seq');
	}

	/**
	 * Set sequence of format in format listings for the submission.
	 * @param $sequence float
	 */
	function setSeq($seq) {
		$this->setData('seq', $seq);
	}

	/**
	 * Get "localized" format name (if applicable).
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLocalizedData('name');
	}

	/**
	 * Get the format name (if applicable).
	 * @param $locale string
	 * @return string
	 */
	function getName($locale) {
		return $this->getData('name', $locale);
	}

	/**
	 * Set name.
	 * @param $name string
	 * @param $locale
	 */
	function setName($name, $locale = null) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * Set submission ID.
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		$this->setData('submissionId', $submissionId);
	}

	/**
	 * Get submission id
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Determines if a representation is approved or not.
	 * @return boolean
	 */
	function getIsApproved() {
		return (boolean) $this->getData('isApproved');
	}

	/**
	 * Sets whether a representation is approved or not.
	 * @param boolean $isApproved
	 */
	function setIsApproved($isApproved) {
		return $this->setData('isApproved', $isApproved);
	}

	/**
	 * Get stored public ID of the submission.
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @return int
	 */
	function getStoredPubId($pubIdType) {
		return $this->getData('pub-id::'.$pubIdType);
	}

	/**
	 * Set the stored public ID of the submission.
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 */
	function setStoredPubId($pubIdType, $pubId) {
		$this->setData('pub-id::'.$pubIdType, $pubId);
	}

	/**
	 * Get a public ID for this representation.
	 * @param @literal $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
	 * @param $preview boolean If true, generate a non-persisted preview only.
	 */
	function getPubId($pubIdType, $preview = false) {
		if ($pubIdType === 'publisher-id') {
			$pubId = $this->getStoredPubId($pubIdType);
			return ($pubId ? $pubId : null);
		}

		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $this->getContextId());

		if (is_array($pubIdPlugins)) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				if ($pubIdPlugin->getPubIdType() == $pubIdType) {
					// If we already have an assigned ID, use it.
					$storedId = $this->getStoredPubId($pubIdType);
					if (!empty($storedId)) return $storedId;

					return $pubIdPlugin->getPubId($this, $preview);
				}
			}
		}
		return null;
	}
	/**
	 * Get the context id from the submission assigned to this representation.
	 * @return int
	 */
	function getContextId() {
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->getSubmissionId());
		return $submission->getContextId();
	}
}

?>
