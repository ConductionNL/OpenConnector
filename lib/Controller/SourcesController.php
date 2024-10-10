<?php

namespace OCA\OpenConnector\Controller;

use OCA\OpenConnector\Service\ObjectService;
use OCA\OpenConnector\Service\SearchService;
use OCA\OpenConnector\Service\CallService;
use OCA\OpenConnector\Db\Source;
use OCA\OpenConnector\Db\SourceMapper;
use OCA\OpenConnector\Db\CallLogMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class SourcesController extends Controller
{
    /**
     * Constructor for the SourcesController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly IAppConfig $config,
        private readonly SourceMapper $sourceMapper,
        private readonly CallLogMapper $callLogMapper
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
     * Retrieves a list of all sources
     *
     * This method returns a JSON response containing an array of all sources in the system.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of sources
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch:  $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->sourceMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single source by its ID
     *
     * This method returns a JSON response containing the details of a specific source.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the source to retrieve
     * @return JSONResponse A JSON response containing the source details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->sourceMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new source
     *
     * This method creates a new source based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created source
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

        return new JSONResponse($this->sourceMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing source
     *
     * This method updates an existing source based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the source to update
     * @return JSONResponse A JSON response containing the updated source details
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
        return new JSONResponse($this->sourceMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes a source
     *
     * This method deletes a source based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the source to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->sourceMapper->delete($this->sourceMapper->find((int) $id));

        return new JSONResponse([]);
    }

    /**
     * Retrieves call logs for a source
     *
     * This method returns all the call logs associated with a source based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the source to retrieve logs for
     * @return JSONResponse A JSON response containing the call logs
     */
    public function logs(int $id): JSONResponse
    {
        try {
            $callLogs = $this->callLogMapper->findAll(null, null, ['source_id' =>  $id]);
            return new JSONResponse($callLogs);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Source not found'], 404);
        }
    }

    /**
     * Test a source
     *
     * This method fires a test call to the source and returns the response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * Endpoint: /api/source-test/{id}
     * Properties:
     *   query: (expected key-value array)
     *   headers: (expected key-value array)
     *   method: (string, one of POST, GET, PUT, DELETE) -> defaults to POST
     *   endpoint: (string) can be empty
     *   type: (string, one of: json, xml, yaml)
     *   body: (string)
     *
     * @param int $id The ID of the source to test
     * @return JSONResponse A JSON response containing the test results
     */
    public function test(CallService $callService,int $id): JSONResponse
    {
        // get the source
        try {
            $source = $this->sourceMapper->find(id: (int) $id);
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }

        // Get the request data
        $requestData = $this->request->getParams();

        // Build Guzzle call configuration array
        $config = [];

        // Add headers if present
        if (isset($requestData['headers']) && is_array($requestData['headers'])) {
            $config['headers'] = $requestData['headers'];
        }

        // Add query parameters if present
        if (isset($requestData['query']) && is_array($requestData['query'])) {
            $config['query'] = $requestData['query'];
        }

        // Set method, default to POST if not provided
        $method = $requestData['method'] ?? 'GET';

        // Set endpoint
        $endpoint = $requestData['endpoint'] ?? '';

        // Set body if present
        if (isset($requestData['body'])) {
            $config['body'] = $requestData['body'];
        }

        // Set content type based on the type parameter
        if (isset($requestData['type'])) {
            switch ($requestData['type']) {
                case 'json':
                    $config['headers']['Content-Type'] = 'application/json';
                    break;
                case 'xml':
                    $config['headers']['Content-Type'] = 'application/xml';
                    break;
                case 'yaml':
                    $config['headers']['Content-Type'] = 'application/x-yaml';
                    break;
            }
        }

        // fire the call

        $time_start = microtime(true);
        $callLog = $callService->call($source, $endpoint, $method, $config);
        $time_end = microtime(true);

        return new JSONResponse($callLog->jsonSerialize());
    }
}
