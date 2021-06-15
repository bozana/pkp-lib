<?php
/**
 * @file classes/components/listPanels/PKPInstitutionsListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing institutions
 */

namespace PKP\components\listPanels;

use APP\i18n\AppLocale;

class PKPInstitutionsListPanel extends ListPanel
{
    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var int How many items to display on one page in this list */
    public $count = 30;

    /** @param \PKP\components\forms\institution\PKPInstitutionForm Form for adding or editing an institution */
    public $form = null;

    /** @var array Query parameters to pass if this list executes GET requests  */
    public $getParams = [];

    /** @var int Max number of items available to display in this list panel  */
    public $itemsMax = 0;

    /**
     * Initialize the form with config parameters
     *
     * @param string $id
     * @param string $title
     * @param array $args Configuration params
     */
    public function __construct($id, $title, $args = [])
    {
        parent::__construct($id, $title, $args);
    }

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'addInstitutionLabel' => __('grid.action.addInstitution'),
                'apiUrl' => $this->apiUrl,
                'confirmDeleteMessage' => __('manager.institutions.confirmDelete'),
                'count' => $this->count,
                'deleteInstitutionLabel' => __('manager.institutions.deleteInstitution'),
                'editInstitutionLabel' => __('manager.institutions.edit'),
                'form' => $this->form->getConfig(),
                'itemsMax' => $this->itemsMax
            ]
        );

        return $config;
    }
}
