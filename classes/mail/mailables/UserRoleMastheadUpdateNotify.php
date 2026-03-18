<?php

/**
 * @file classes/mail/mailables/UserRoleMastheadUpdateNotify.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleMastheadUpdateNotify
 *
 * @brief Email sent when user's masthead visibility for a role changes
 */

namespace PKP\mail\mailables;

use APP\facades\Repo;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\traits\AddsStyleToSymfonyMessage;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\userGroup\relationships\UserUserGroup;

class UserRoleMastheadUpdateNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;
    use AddsStyleToSymfonyMessage;

    protected static ?string $name = 'mailable.userRoleMastheadUpdateNotify.name';
    protected static ?string $description = 'mailable.userRoleMastheadUpdateNotify.description';
    protected static ?string $emailTemplateKey = 'USER_ROLE_MASTHEAD_UPDATE';

    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        self::FROM_SYSTEM,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    protected static string $roleNameAndDates = 'roleNameAndDates';
    protected static string $appearOnMasthead = 'appearOnMasthead';

    private UserUserGroup $userUserGroup;

    public function __construct(Context $context, UserUserGroup $userUserGroup)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->userUserGroup = $userUserGroup;

        // Register style injection
        $this->registerMailCss();
    }

    /**
     * Add descriptions for new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::$roleNameAndDates] = __('emailTemplate.variable.userRoleMastheadUpdateNotify.roleNameAndDates');
        $variables[static::$appearOnMasthead] = __('emailTemplate.variable.userRoleMastheadUpdateNotify.appearOnMasthead');
        return $variables;
    }

    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        if (is_null($locale)) {
            $locale = $this->getLocale() ?? Locale::getLocale();
        }

        $userGroup = Repo::userGroup()->get($this->userUserGroup->userGroupId);
        $roleName = $userGroup->getLocalizedData('name', $locale);
        $role = __('emailTemplate.variable.userRoleMastheadUpdateNotify.roleNameAndDates.value', [
            'roleName' => $roleName,
            'startDate' => $this->userUserGroup->dateStart?->format('Y-m-d') ?? '',
            'endDate' => $this->userUserGroup->dateEnd?->format('Y-m-d') ?? '',
        ]);
        $onMasthead = $this->userUserGroup->masthead ? __('invitation.masthead.show') : __('invitation.masthead.hidden');

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$roleNameAndDates => $role,
                static::$appearOnMasthead => $onMasthead,
            ]
        );
    }
}
