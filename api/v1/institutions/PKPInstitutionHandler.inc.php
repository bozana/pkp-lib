<?php

/**
 * @file api/v1/institutions/PKPInstitutionHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionHandler
 * @ingroup api_v1_institutions
 *
 * @brief Handle API requests for institution operations.
 *
 */

use APP\facades\Repo;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPInstitutionHandler extends APIHandler
{
    /** @var int The default number of institutions to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maxium number of institutions to return in one request */
    public const MAX_COUNT = 100;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'institutions';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single institution
     */
    public function get(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        if (!Repo::institution()->existsByContextId((int) $args['institutionId'], $this->getRequest()->getContext()->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }
        $institution = Repo::institution()->get((int) $args['institutionId']);
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Get a collection of institutions
     */
    public function getMany(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $collector = Repo::institution()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($slimRequest->getQueryParams() as $param => $val) {
            switch ($param) {
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;
            }
        }

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        HookRegistry::call('API::institutions::params', [$collector, $slimRequest]);

        $institutions = Repo::institution()->getMany($collector);

        return $response->withJson([
            'itemsMax' => $institutions->count(),
            'items' => Repo::institution()->getSchemaMap()->summarizeMany($institutions),
        ], 200);
    }

    /**
     * Add an institution
     *
     * @throws \Exception For sending a request to the API endpoint of a particular context.
     */
    public function add(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            throw new \Exception('You can not add an institution without sending a request to the API endpoint of a particular context.');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $slimRequest->getParsedBody());
        $params['contextId'] = $request->getContext()->getId();
        // Convert IP ranges string to array
        if (!empty($params['ipRanges'])) {
            $ipRanges = explode(PHP_EOL, trim($params['ipRanges']));
            $params['ipRanges'] = $ipRanges;
        }

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getSupportedFormLocales();
        $errors = Repo::institution()->validate(null, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $institution = Repo::institution()->newDataObject($params);
        $id = Repo::institution()->add($institution);
        $institution = Repo::institution()->get($id);
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Edit an institution
     */
    public function edit(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        if (!Repo::institution()->existsByContextId((int) $args['institutionId'], $context->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }

        $institution = Repo::institution()->get((int) $args['institutionId']);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $slimRequest->getParsedBody());
        $params['id'] = $institution->getId();
        $params['contextId'] = $context->getId();
        // Convert IP ranges string to array
        if (!empty($params['ipRanges'])) {
            $ipRanges = explode(PHP_EOL, trim($params['ipRanges']));
            $params['ipRanges'] = $ipRanges;
        }

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();
        $errors = Repo::institution()->validate($institution, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::institution()->edit($institution, $params);
        $institution = Repo::institution()->get($institution->getId());
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Delete an institution
     */
    public function delete(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        if (!Repo::institution()->existsByContextId((int) $args['institutionId'], $this->getRequest()->getContext()->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }

        $institution = Repo::institution()->get((int) $args['institutionId']);
        $institutionProps = Repo::institution()->getSchemaMap()->map($institution);
        Repo::institution()->delete($institution);
        return $response->withJson($institutionProps, 200);
    }
}
