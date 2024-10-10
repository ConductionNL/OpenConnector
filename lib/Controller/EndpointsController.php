<?php

namespace OCA\OpenConnector\Controller;

use OCA\OpenConnector\Service\ObjectService;
use OCA\OpenConnector\Service\SearchService;
use OCA\OpenConnector\Db\Endpoint;
use OCA\OpenConnector\Db\EndpointMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\AppFramework\Db\DoesNotExistException;

class EndpointsController extends Controller
{
    /**
     * Constructor for the EndpointsController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     * @param EndpointMapper $endpointMapper The endpoint mapper object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private IAppConfig $config,
        private EndpointMapper $endpointMapper
    )
    {
        parent::__construct($appName, $request);
    }

    /**
     * Returns the template of the main app's page
     *
     * This method renders the main page of the application, adding any necessary data to the template.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return TemplateResponse The rendered template response
     */
    public function page(): TemplateResponse
    {
        return new TemplateResponse(
            'openconnector',
            'index',
            []
        );
    }

    /**
     * Retrieves a list of all endpoints
     *
     * This method returns a JSON response containing an array of all endpoints in the system.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of endpoints
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description', 'endpoint'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->endpointMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single endpoint by its ID
     *
     * This method returns a JSON response containing the details of a specific endpoint.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the endpoint to retrieve
     * @return JSONResponse A JSON response containing the endpoint details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->endpointMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new endpoint
     *
     * This method creates a new endpoint based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created endpoint
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }

        if (isset($data['id'])) {
            unset($data['id']);
        }

        $endpoint = $this->endpointMapper->createFromArray(object: $data);

        return new JSONResponse($endpoint);
    }

    /**
     * Updates an existing endpoint
     *
     * This method updates an existing endpoint based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the endpoint to update
     * @return JSONResponse A JSON response containing the updated endpoint details
     */
    public function update(int $id): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }

        $endpoint = $this->endpointMapper->updateFromArray(id: (int) $id, object: $data);

        return new JSONResponse($endpoint);
    }

    /**
     * Deletes an endpoint
     *
     * This method deletes an endpoint based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the endpoint to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->endpointMapper->delete($this->endpointMapper->find((int) $id));

        return new JSONResponse([]);
    }

    /**
     * Retrieves call logs for an endpoint
     *
     * This method returns all the call logs associated with an endpoint based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the endpoint to retrieve logs for
     * @return JSONResponse A JSON response containing the call logs
     */
    public function logs(int $id): JSONResponse
    {
        try {
            $endpoint = $this->endpointMapper->find($id);
            $endpointLogs = $this->endpointLogMapper->findAll(null, null, ['endpoint_id' => $endpoint->getId()]);
            return new JSONResponse($endpointLogs);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Endpoint not found'], 404);
        }
    }

    /**
     * Test an endpoint
     *
     * This method fires a test call to the endpoint and returns the response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * Endpoint: /api/endpoints-test/{id}
     *
     * @param int $id The ID of the endpoint to test
     * @return JSONResponse A JSON response containing the test results
     */
    public function test(int $id): JSONResponse
    {
        try {
            $endpoint = $this->endpointMapper->find(id: $id);
            // Implement the logic to test the endpoint here
            // This is a placeholder implementation
            $testResult = ['status' => 'success', 'message' => 'Endpoint test successful'];
            return new JSONResponse($testResult);
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Endpoint not found'], statusCode: 404);
        }
    }

    /**
     * Actually run an endpoint
     *
     * This method runs an endpoint based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the endpoint to run
     * @return JSONResponse A JSON response containing the run results
     */
    public function run(int $id): JSONResponse
    {
        try {
            $endpoint = $this->endpointMapper->find(id: $id);
            // Implement the logic to run the endpoint here
            // This is a placeholder implementation
            $runResult = ['status' => 'success', 'message' => 'Endpoint run successful'];
            return new JSONResponse($runResult);
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Endpoint not found'], statusCode: 404);
        }
    }
}
