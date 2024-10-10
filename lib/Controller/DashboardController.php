<?php

namespace OCA\OpenConnector\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCA\OpenConnector\Db\SynchronizationMapper;
use OCA\OpenConnector\Db\SourceMapper;
use OCA\OpenConnector\Db\SynchronizationContractMapper;
use OCA\OpenConnector\Db\ConsumerMapper;
use OCA\OpenConnector\Db\EndpointMapper;
use OCA\OpenConnector\Db\JobMapper;
use OCA\OpenConnector\Db\MappingMapper;

class DashboardController extends Controller
{
    private $synchronizationMapper;
    private $sourceMapper;
    private $synchronizationContractMapper;
    private $consumerMapper;
    private $endpointMapper;
    private $jobMapper;
    private $mappingMapper;

    public function __construct(
        $appName,
        IRequest $request,
        SynchronizationMapper $synchronizationMapper,
        SourceMapper $sourceMapper,
        SynchronizationContractMapper $synchronizationContractMapper,
        ConsumerMapper $consumerMapper,
        EndpointMapper $endpointMapper,
        JobMapper $jobMapper,
        MappingMapper $mappingMapper
    ) {
        parent::__construct($appName, $request);
        $this->synchronizationMapper = $synchronizationMapper;
        $this->sourceMapper = $sourceMapper;
        $this->synchronizationContractMapper = $synchronizationContractMapper;
        $this->consumerMapper = $consumerMapper;
        $this->endpointMapper = $endpointMapper;
        $this->jobMapper = $jobMapper;
        $this->mappingMapper = $mappingMapper;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function page(?string $getParameter)
    {
        try {
            $response = new TemplateResponse(
                $this->appName,
                'index',
                []
            );
            
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedConnectDomain('*');
            $response->setContentSecurityPolicy($csp);

            return $response;
        } catch (\Exception $e) {
            return new TemplateResponse(
                $this->appName,
                'error',
                ['error' => $e->getMessage()],
                '500'
            );
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse
    {
        try {
            $results = [
                "synchronizations" => $this->synchronizationMapper->getTotalCallCount(),
                "sources" => $this->sourceMapper->getTotalCallCount(),
                "synchronizationContracts" => $this->synchronizationContractMapper->getTotalCallCount(),
                "consumers" => $this->consumerMapper->getTotalCallCount(),
                "endpoints" => $this->endpointMapper->getTotalCallCount(),
                "jobs" => $this->jobMapper->getTotalCallCount(),
                "mappings" => $this->mappingMapper->getTotalCallCount()
            ];
            return new JSONResponse($results);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
}
