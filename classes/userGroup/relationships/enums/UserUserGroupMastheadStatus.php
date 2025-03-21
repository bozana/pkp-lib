<?php

/**
 * @file classes/userGroup/relationships/enums/UserUserGroupMastheadStatus.php
 *
 * Copyright (c) 2024-2025 Simon Fraser University
 * Copyright (c) 2024-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserUserGroupMastheadStatus
 *
 * @brief Enumeration for user user group masthead statuses
 */

namespace PKP\userGroup\relationships\enums;

enum UserUserGroupMastheadStatus: int
{
    case STATUS_ON = 1;		    // Aggreed to be displayed on masthead for the user group
    case STATUS_OFF = 0;	    // Disaggreed to be displayed on masthead for the user group

    /**
     * Default DB value in null, meaning that it is undefined if the user agrees
     * to be displayed on the masthead for the user group.
     * This is above all the case for the users that existed before upgrade to 3.5 or next release
     * that are active in a user group that is per default not dispalyed on the masthead.
     *
     */
}
