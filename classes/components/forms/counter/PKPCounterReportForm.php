<?php
/**
 * @file classes/components/form/counter/PKPCounterReportForm.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCounterReportForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for setting a counter report
 */

namespace PKP\components\forms\counter;

use APP\core\Application;
use APP\sushi\PR;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_COUNTER', 'counter');

class PKPCounterReportForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_COUNTER;

    /** @copydoc FormComponent::$method */
    public $method = 'GET';

    public $reportId = '';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $context = Application::get()->getRequest()->getContext();
        $formFields = PR::getReportSettingsFormFields($context);
        $this->addPage(['id' => 'default', 'submitButton' => ['label' => __('common.download')]]);
        $this->addGroup(['id' => 'default', 'pageId' => 'default']);
        foreach ($formFields as $formField) {
            $this->addField($formField);
        }
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ formFields ++++\n", true);
        $current .= print_r($this->fields, true);
        file_put_contents($file, $current);
        /*
        $this->addField(new FieldText('begin_date', [
            'label' => __('common.date'),
            'size' => 'small',
            'isMultilingual' => false,
            'groupId' => 'default',
        ]));
        $this->addField(new FieldText('end_date', [
            'label' => __('common.date'),
            'size' => 'small',
            'isMultilingual' => false,
            'groupId' => 'default',
        ]));
        */
    }
}
