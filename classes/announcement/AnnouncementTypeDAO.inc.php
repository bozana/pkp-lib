<?php

/**
 * @file classes/announcement/AnnouncementTypeDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeDAO
 * @ingroup announcement
 * @see AnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */


import('lib.pkp.classes.announcement.AnnouncementType');
import('lib.pkp.classes.db.SchemaDAO');

class AnnouncementTypeDAO extends SchemaDAO {
	var $schemaName = SCHEMA_ANNOUNCEMENT_TYPE;

	/** @var string The name of the primary table for this object */
	var $tableName = 'announcement_types';

	/** @var string The name of the settings table for this object */
	var $settingsTableName = 'announcement_type_settings';

	/** @var string The column name for the object id in primary and settings tables */
	var $primaryKeyColumn = 'type_id';

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = [
		'id' => 'type_id',
		'assocId' => 'assoc_id',
		'assocType' => 'assoc_type',
	];

	/**
	 * Generate a new data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new AnnouncementType();
	}

	/**
	 * Retrieve an announcement type by announcement type ID.
	 * @param $typeId int Announcement type ID
	 * @param $assocType int Optional assoc type
	 * @param $assocId int Optional assoc ID
	 * @return AnnouncementType
	 */
	function getById($typeId, $assocType = null, $assocId = null) {
		$params = [(int) $typeId];
		if ($assocType !== null) $params[] = (int) $assocType;
		if ($assocId !== null) $params[] = (int) $assocId;
		$result = $this->retrieve(
			'SELECT * FROM announcement_types WHERE type_id = ?' .
			($assocType !== null?' AND assoc_type = ?':'') .
			($assocId !== null?' AND assoc_id = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Delete an announcement type by announcement type ID. Note that all announcements with
	 * this type ID are also deleted.
	 * @param $typeId int
	 */
	function deleteById($typeId) {
		parent::deleteById($typeId);

		$announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /* @var $announcementDao AnnouncementDAO */
		$announcementDao->deleteByTypeId($typeId);
	}

	/**
	 * Delete announcement types by association.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 */
	function deleteByAssoc($assocType, $assocId) {
		foreach ($this->getByAssoc($assocType, $assocId) as $type) {
			$this->deleteObject($type);
		}
	}

	/**
	 * Retrieve an array of announcement types matching a particular Assoc ID.
	 * @param $assocType int ASSOC_TYPE_...
	 * @param $assocId int
	 * @return Generator Matching AnnouncementTypes
	 */
	function getByAssoc($assocType, $assocId) {
		$result = $this->retrieve(
			'SELECT * FROM announcement_types WHERE assoc_type = ? AND assoc_id = ? ORDER BY type_id',
			[(int) $assocType, (int) $assocId]
		);
		foreach ($result as $row) {
			yield $row->type_id => $this->_fromRow((array) $row);
		}
	}

	/**
	 * Get the ID of the last inserted announcement type.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('announcement_types', 'type_id');
	}
}


