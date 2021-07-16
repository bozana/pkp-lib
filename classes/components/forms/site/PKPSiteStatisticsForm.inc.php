<?php
/**
 * @file classes/components/form/site/PKPSiteStatisticsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteStatisticsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the site statistics settings.
 */

namespace PKP\components\forms\site;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_SITE_STATISTICS', 'siteStatistics');

class PKPSiteStatisticsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SITE_STATISTICS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param $action string URL to submit the form to
     * @param $locales array Supported locales
     * @param $site Site
     */
    public function __construct($action, $locales, $site)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addGroup([
            'id' => 'archive',
        ])
            ->addField(new FieldOptions('archivedUsageStatsLogFiles', [
                'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles'),
                'description' => __('manager.settings.statistics.archivedUsageStatsLogFiles.description'),
                'groupId' => 'archive',
                'type' => 'radio',
                'options' => [
                    [
                        'value' => 0,
                        'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles.default'),
                    ],
                    [
                        'value' => 1,
                        'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles.compress'),
                    ],
                ],
                'value' => $site->getData('archivedUsageStatsLogFiles') ? $site->getData('archivedUsageStatsLogFiles') : 0,
            ]))
            ->addGroup([
                'id' => 'submission',
            ])
            ->addField(new FieldOptions('submissionUsageStatsKeepDaily', [
                'label' => __('manager.settings.statistics.keepDaily', ['object' => __('manager.settings.statistics.keepDaily.submission')]),
                'description' => __('manager.settings.statistics.keepDaily.description', ['object' => __('manager.settings.statistics.keepDaily.submission')]),
                'groupId' => 'submission',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.keepDaily.option', ['object' => __('manager.settings.statistics.keepDaily.submission')]),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('submissionUsageStatsKeepDaily'),
            ]))
            ->addGroup([
                'id' => 'geo',
            ])
            ->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'description' => __('manager.settings.statistics.geoUsageStats.description'),
                'groupId' => 'geo',
                'type' => 'radio',
                'options' => [
                    [
                        'value' => 0,
                        'label' => __('manager.settings.statistics.geoUsageStats.disabled'),
                    ],
                    [
                        'value' => 1,
                        'label' => __('manager.settings.statistics.geoUsageStats.countryLevel'),
                    ],
                    [
                        'value' => 2,
                        'label' => __('manager.settings.statistics.geoUsageStats.regionLevel'),
                    ],
                    [
                        'value' => 3,
                        'label' => __('manager.settings.statistics.geoUsageStats.cityLevel'),
                    ],
                ],
                'value' => $site->getData('enableGeoUsageStats') ? $site->getData('enableGeoUsageStats') : 0,
            ]))
            ->addField(new FieldOptions('geoUsageStatsKeepDaily', [
                'label' => __('manager.settings.statistics.keepDaily', ['object' => __('manager.settings.statistics.keepDaily.geo')]),
                'description' => __('manager.settings.statistics.keepDaily.description', ['object' => __('manager.settings.statistics.keepDaily.geo')]),
                'groupId' => 'geo',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.keepDaily.option', ['object' => __('manager.settings.statistics.keepDaily.geo')]),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('geoUsageStatsKeepDaily'),
                'showWhen' => 'enableGeoUsageStats',
            ]))
            ->addGroup([
                'id' => 'institution',
            ])
            ->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'description' => __('manager.settings.statistics.institutionUsageStats.description'),
                'groupId' => 'institution',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('enableInstitutionUsageStats'),
            ]))
            ->addField(new FieldOptions('institutionUsageStatsKeepDaily', [
                'label' => __('manager.settings.statistics.keepDaily', ['object' => __('manager.settings.statistics.keepDaily.institution')]),
                'description' => __('manager.settings.statistics.keepDaily.description', ['object' => __('manager.settings.statistics.keepDaily.institution')]),
                'groupId' => 'institution',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.keepDaily.option', ['object' => __('manager.settings.statistics.keepDaily.institution')]),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('institutionUsageStatsKeepDaily'),
                'showWhen' => 'enableInstitutionUsageStats',
            ]));
    }
}
