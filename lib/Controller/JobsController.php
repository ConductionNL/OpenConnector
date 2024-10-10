<?php

namespace OCA\OpenConnector\Controller;

use OCA\OpenConnector\Service\ObjectService;
use OCA\OpenConnector\Service\SearchService;
use OCA\OpenConnector\Db\Job;
use OCA\OpenConnector\Db\JobMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\BackgroundJob\IJobList;
use OCA\OpenConnector\Db\JobLogMapper;
use OCA\OpenConnector\Service\JobService;

class JobsController extends Controller
{
    /**
     * Constructor for the JobController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private IAppConfig $config,
        private JobMapper $jobMapper,
        private JobLogMapper $jobLogMapper,
        private JobService $jobService,
        private IJobList $jobList,
    )
    {
        parent::__construct($appName, $request);
        $this->IJobList = $jobList;
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
     * Retrieves a list of all jobs
     *
     * This method returns a JSON response containing an array of all jobs in the system.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of jobs
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch:  $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->jobMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single job by its ID
     *
     * This method returns a JSON response containing the details of a specific job.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the job to retrieve
     * @return JSONResponse A JSON response containing the job details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->jobMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new job
     *
     * This method creates a new job based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created job
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

        // Create the job
        $job = $this->jobMapper->createFromArray(object: $data);
        // Let's schedule the job
        $job = $this->jobService->scheduleJob($job);

        return new JSONResponse($job);
    }

    /**
     * Updates an existing job
     *
     * This method updates an existing job based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the job to update
     * @return JSONResponse A JSON response containing the updated job details
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

        // Create the job
        $job = $this->jobMapper->updateFromArray(id: (int) $id, object: $data);
        // Let's schedule the job
        $job = $this->jobService->scheduleJob($job);

        return new JSONResponse($job);
    }

    /**
     * Deletes a job
     *
     * This method deletes a job based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the job to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->jobMapper->delete($this->jobMapper->find((int) $id));

        return new JSONResponse([]);
    }

    /**
     * Retrieves call logs for a job
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
            $jobLogs = $this->jobLogMapper->findAll(null, null, ['job_id' => $id]);
            return new JSONResponse($jobLogs);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Job not found'], 404);
        }
    }

    /**
     * Test a job
     *
     * This method fires a test call to the job and returns the response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * Endpoint: /api/job-run/{id}
     *
     * @param int $id The ID of the job to test
     * @return JSONResponse A JSON response containing the test results
     */
    public function run(int $id): JSONResponse
    {
        try {
            $job = $this->jobMapper->find(id: $id);
            if (!$job->getJobListId()) {
                return new JSONResponse(data: ['error' => 'Job not scheduled'], statusCode: 404);
            }
            $this->IJobList->getById($job->getJobListId())->start($this->IJobList);
            return new JSONResponse($this->jobLogMapper->getLastCallLog());
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }
}
