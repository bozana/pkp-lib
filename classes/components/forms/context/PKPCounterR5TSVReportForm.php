<?php
/**
 * @file classes/components/form/context/PKPCounterR5TSVReportForm.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCounterR5TSVReportForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for COUNTER R5 TSV reports
 */

namespace PKP\components\forms\context;

use APP\sushi\IR;
use APP\sushi\IR_A1;
use APP\sushi\PR;
use APP\sushi\PR_P1;
use APP\sushi\TR;
use APP\sushi\TR_J3;
use PKP\components\forms\Field;

use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\sushi\CounterR5Report;

class PKPCounterR5TSVReportForm extends FormComponent
{
    public const FORM_COUNTER_R5_TSV_REPORT = 'counterR5TSVReport';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_COUNTER_R5_TSV_REPORT;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    protected const GENERAL_SETTINGS = 'generalSettings';
    protected const REPORT_SPECIFIC_SETTINGS = 'reportSpecificSettings';

    /** @var Field[] Report-specific settings */
    protected array $reportFields;

    /**
     * Constructor
     *
     */
    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $pr = new PR();
        $pr_p1 = new PR_P1();
        $tr = new TR();
        $tr_j3 = new TR_J3();
        $ir = new IR();
        $ir_a1 = new IR_A1();
        $reports = collect([$pr, $pr_p1, $tr, $tr_j3, $ir, $ir_a1]);
        /*
                Hook::call('DoiSettingsForm::setEnabledRegistrationAgencies', [&$registrationAgencies]);
        */
        // Add report options
        $options = [
            [
                'value' => 'bla bla',
                'label' => __('doi.manager.settings.registrationAgency.none'), // TO-DO: change key
            ],
        ];

        $this->reportFields = [];

        $reports->each(function (CounterR5Report $report) use (&$options, $context) {
            $options[] = [
                'value' => $report->getID(),
                'label' => $report->getName(),
            ];

            $this->reportFields[$report->getID()] = array_map(function ($field) {
                $field->groupId = self::REPORT_SPECIFIC_SETTINGS;
                return $field;
            }, $report->getFields());
        });

        $this->addGroup([
            'id' => self::GENERAL_SETTINGS,
        ]);

        $this->addGroup([
            'id' => self::REPORT_SPECIFIC_SETTINGS,
            'showWhen' => 'report',
        ]);

        $this->addField(new FieldSelect('report', [
            'label' => __('doi.manager.settings.registrationAgency'),
            'description' => __('doi.manager.settings.registrationAgency.description'),
            'options' => $options,
            'value' => null,
            'groupId' => self::GENERAL_SETTINGS,
        ]));
    }

    public function getConfig()
    {
        $activeReportField = array_filter($this->fields, function ($field) {
            return $field->name === 'report';
        });
        $activeReport = empty($activeReportField) ? '' : $activeReportField[0]->value;
        if (!empty($this->reportFields[$activeReport])) {
            $this->fields = array_merge($this->fields, $this->reportFields[$activeReport]);
        }

        $config = parent::getConfig();

        // Set up field config for non-active fields
        $config['reportFields'] = array_map(function ($reportFields) {
            return array_map(function ($reportField) {
                $field = $this->getFieldConfig($reportField);
                $field['groupId'] = self::REPORT_SPECIFIC_SETTINGS;
                return $field;
            }, $reportFields);
        }, $this->reportFields);

        return $config;
    }
}
