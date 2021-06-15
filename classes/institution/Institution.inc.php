<?php

/**
 * @defgroup institution Institution
 * Implements institutions that are used for subscriptions and statistics.
 */

 /**
 * @file classes/institution/Institution.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Institution
 * @ingroup institution
 *
 * @see DAO
 *
 * @brief Basic class describing an institution.
 */

namespace PKP\institution;

class Institution extends \PKP\core\DataObject
{
    public const IP_RANGE_RANGE = '-';
    public const IP_RANGE_WILDCARD = '*';

    //
    // Get/set methods
    //

    /**
     * Get the context ID
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set the context ID
     *
     * @param $contextId int
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the ROR
     *
     * @return string
     */
    public function getROR()
    {
        return $this->getData('ror');
    }

    /**
     * Set the ROR
     *
     * @param $ror string
     */
    public function setROR($ror)
    {
        $this->setData('ror', $ror);
    }

    /**
     * Get the localized name of the institution
     *
     * @param $preferredLocale string
     *
     * @return string
     */
    public function getLocalizedName($preferredLocale = null)
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * get the name of the institution
     *
     * @param null|mixed $locale
     */
    public function getName($locale = null)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set the name of the institution
     *
     * @param $name string
     * @param null|mixed $locale
     */
    public function setName($name, $locale = null)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get institution ip ranges.
     *
     * @return array
     */
    public function getIPRanges()
    {
        return $this->getData('ipRanges');
    }

    /**
     * Set institution ip ranges.
     *
     * @param $ipRanges array
     */
    public function setIPRanges($ipRanges)
    {
        return $this->setData('ipRanges', $ipRanges);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\institution\Institution', '\Institution');
    foreach ([
        'IP_RANGE_RANGE',
        'IP_RANGE_WILDCARD',
    ] as $constantName) {
        define($constantName, constant('\Institution::' . $constantName));
    }
}
