<?php

namespace OCA\OpenConnector\Service;

use Exception;
use OCA\OpenConnector\Db\CallLog;
use OCA\OpenConnector\Db\Source;
use OCA\OpenConnector\Db\SourceMapper;
use OCA\OpenConnector\Db\Synchronization;
use OCA\OpenConnector\Db\SynchronizationMapper;
use OCA\OpenConnector\Db\SynchronizationContract;
use OCA\OpenConnector\Db\SynchronizationContractLog;
use OCA\OpenConnector\Db\SynchronizationContractLogMapper;
use OCA\OpenConnector\Db\SynchronizationContractMapper;
use OCA\OpenConnector\Service\CallService;
use OCA\OpenConnector\Service\MappingService;
use Symfony\Component\Uid\Uuid;

use Psr\Container\ContainerInterface;
use DateInterval;
use DateTime;


class SynchronizationService
{
    private CallService $callService;
    private MappingService $mappingService;
    private ContainerInterface $containerInterface;
    private SynchronizationMapper $synchronizationMapper;
    private SourceMapper $sourceMapper;
    private SynchronizationContractMapper $synchronizationContractMapper;
    private SynchronizationContractLogMapper $synchronizationContractLogMapper;
    private ObjectService $objectService;
    private Source $source;


	public function __construct(
		CallService $callService,
		MappingService $mappingService,
		ContainerInterface $containerInterface,
        SourceMapper $sourceMapper,
		SynchronizationMapper $synchronizationMapper,
		SynchronizationContractMapper $synchronizationContractMapper,
        SynchronizationContractLogMapper $synchronizationContractLogMapper
	) {
		$this->callService = $callService;
		$this->mappingService = $mappingService;
		$this->containerInterface = $containerInterface;
		$this->synchronizationMapper = $synchronizationMapper;
		$this->synchronizationContractMapper = $synchronizationContractMapper;
        $this->synchronizationContractLogMapper = $synchronizationContractLogMapper;
        $this->sourceMapper = $sourceMapper;
	}

	/**
	 * Synchronizes a given synchronization (or a complete source).
	 *
	 * @param Synchronization $synchronization
	 * @return array
	 * @throws Exception
	 */
    public function synchronize(Synchronization $synchronization): array
	{
        $objectList = $this->getAllObjectsFromSource($synchronization);

        foreach ($objectList as $key => $object) {
            // Get the synchronization contract for this object
            $synchronizationContract = $this->synchronizationContractMapper->findOnSynchronizationIdSourceId($synchronization->id, $object['id']);

            if (!($synchronizationContract instanceof SynchronizationContract)) {
                $synchronizationContract = new SynchronizationContract();
                $synchronizationContract->setUuid(Uuid::v4());
                $synchronizationContract->setSynchronizationId($synchronization->id);
                $synchronizationContract->setSourceId($object['id']);
                $synchronizationContract->setSourceHash(md5(serialize($object)));

                $synchronizationContract = $this->synchronizeContract($synchronizationContract, $synchronization, $object);
                $objectList[$key] = $this->synchronizationContractMapper->insert($synchronizationContract);

            }
            else{
                // @todo this is wierd
                $synchronizationContract = $this->synchronizeContract($synchronizationContract, $synchronization, $object);
                $objectList[$key] = $this->synchronizationContractMapper->update($synchronizationContract);
            }
        }

        return $objectList;
    }

	/**
	 * Synchronize a contract
	 * @param SynchronizationContract $synchronizationContract
	 * @param Synchronization|null $synchronization
	 * @param array $object
	 *
	 * @return SynchronizationContract
	 * @throws Exception
	 */
    public function synchronizeContract(SynchronizationContract $synchronizationContract, Synchronization $synchronization = null, array $object = [])
    {
        // Let create a source hash for the object
        $sourceHash = md5(serialize($object));
        $synchronizationContract->setSourceLastChecked(new DateTime());

        // Let's prevent pointless updates @todo account for omnidirectional sync, unless the config has been updated since last check then we do want to rebuild and check if the tagert object has changed
        if ($sourceHash === $synchronizationContract->getSourceHash() && $synchronization->getUpdated() < $synchronizationContract->getSourceLastChecked()) {
            // The object has not changed and the config has not been updated since last check
            // return $synchronizationContract; 
            // @todo: somehow this always returns true, so we never do the updateTarget
        }

        // The object has changed, oke let do mappig and bla die bla
        $synchronizationContract->setSourceHash($sourceHash);
        $synchronizationContract->setSourceLastChanged(new DateTime());

        // let do the mapping if provided
        if ($synchronization->getSourceTargetMapping()){
            $targetObject = $this->mappingService->mapping($synchronization->getSourceTargetMapping(), $object);
        }
        else{
            $targetObject = $object;
        }


        // set the target hash
        $targetHash = md5(serialize($targetObject));
        $synchronizationContract->setTargetHash($targetHash);
        $synchronizationContract->setTargetLastChanged(new DateTime());
        $synchronizationContract->setTargetLastSynced(new DateTime());
        $synchronizationContract->setSourceLastSynced(new DateTime());

        // Do the magic!!

        $this->updateTarget($synchronizationContract, $targetObject);

        // Log it
        $log = new SynchronizationContractLog();
        $log->setUuid(Uuid::v4());
        $log->setSynchronizationId($synchronizationContract->getSynchronizationId());
        $log->setSynchronizationContractId($synchronizationContract->getId());
        $log->setSource($object);
        $log->setTarget($targetObject);
        $log->setExpires(new DateTime('+1 day')); // @todo make this configurable
        $this->synchronizationContractLogMapper->insert($log);

        return $synchronizationContract;

    }

	/**
	 *  Write the data to the target
	 *
	 * @param SynchronizationContract $synchronizationContract
	 * @param array $targetObject
	 *
	 * @return void
	 * @throws Exception
	 */
    public function updateTarget(SynchronizationContract $synchronizationContract, array $targetObject): void
	{
         // The function can be called solo set let's make sure we have the full synchronization object
         if (!$synchronization){
            $synchronization = $this->synchronizationMapper->find($synchronizationContract->getSynchronizationId());
        }

        // Let's check if we need to create or update
        $update = false;
        if ($synchronizationContract->getTargetId()){
            $update = true;
        }

        $type = $synchronization->getTargetType();

        switch($type){
            case 'register/schema':
                // Setup the object service
                $objectService = $this->containerInterface->get('OCA\OpenRegister\Service\ObjectService');
                // if we alreadey have an id, we need to get the object and update it
                if ($synchronizationContract->getTargetId()){
                    $targetObject['id'] = $synchronizationContract->getTargetId();
                }
                // Extract register and schema from the targetId
                $targetId = $synchronization->getTargetId();
                list($register, $schema) = explode('/', $targetId);
                // Save the object to the target
                $target = $objectService->saveObject($register, $schema, $targetObject);
                // Get the id form the target object
                $synchronizationContract->setTargetId($target->getUuid());
                break;
            case 'api':
                //@todo: implement
                //$this->callService->put($targetObject);
                break;
            case 'database':
                //@todo: implement
                break;
            default:
                throw new Exception("Unsupported target type: $type");
        }
    }

    /**
     * Get all the object from a source
     *
     * @param SynchronizationContract $synchronizationContract
     * @return void
     */
    public function getAllObjectsFromSource(Synchronization $synchronization)
    {
        $objects = [];

        $type = $synchronization->getSourceType();

        switch($type){
            case 'register/schema':
                // Setup the object service
                $this->objectService = $this->containerInterface->get('OCA\OpenRegister\Service\ObjectService');

                break;
            case 'api':
                $objects = $this->getAllObjectsFromApi($synchronization);
                break;
            case 'database':
                //@todo: implement
                break;
        }
        return $objects;
    }
    /**
     * Retrieves all objects from an API source for a given synchronization.
     *
     * @param Synchronization $synchronization The synchronization object containing source information.
     * @return array An array of all objects retrieved from the API.
     */
    public function getAllObjectsFromApi(Synchronization $synchronization)
    {
        $objects = [];
        // Retrieve the source object based on the synchronization's source ID
        $source = $this->sourceMapper->find($synchronization->getSourceId());
        
        // Make the initial API call
        $response = $this->callService->call($source)->getResponse();
        $body = json_decode($response['body'], true);
        $objects = array_merge($objects, $this->getAllObjectsFromArray($body, $synchronization));
        $nextLink = $this->getNextlinkFromCall($body, $synchronization);

        // Continue making API calls if there are more pages of results
        while ($nextLink !== null && $nextLink !== '' && $nextLink !== false) {
            $endpoint = str_replace($source->getLocation(), '', $nextLink);
            $response = $this->callService->call($source, $endpoint)->getResponse();
            $body = json_decode($response['body'], true);
            $objects = array_merge($objects, $this->getAllObjectsFromArray($body, $synchronization));
            $nextLink = $this->getNextlinkFromCall($body, $synchronization);
        }
        
        return $objects;        
    }

    /**
     * Extracts all objects from the API response body.
     *
     * @param array $body The decoded JSON body of the API response.
     * @param Synchronization $synchronization The synchronization object containing source configuration.
     * @return array An array of items extracted from the response body.
     * @throws Exception If the position of objects in the return body cannot be determined.
     */
    public function getAllObjectsFromArray(array $array, Synchronization $synchronization)
    {
        // Get the source configuration from the synchronization object
        $sourceConfig = $synchronization->getSourceConfig();
        
        // Check if a specific objects position is defined in the source configuration
        if (isset($sourceConfig['objectsPosition'])) {
            $position = $sourceConfig['objectsPosition'];
            // Use Dot notation to access nested array elements
            $dot = new Dot($array);
            if ($dot->has($position)) {
                // Return the objects at the specified position
                return $dot->get($position);
            } else {
                // Throw an exception if the specified position doesn't exist
                throw new Exception("Cannot find the specified position of objects in the return body.");
            }
        }
        
        // Check for common keys where objects might be stored
        // If 'items' key exists, return its value
        if (isset($array['items'])) {
            return $array['items'];
        }
        
        // If 'result' key exists, return its value
        if (isset($array['result'])) {
            return $array['result'];
        }
        
        // If 'results' key exists, return its value
        if (isset($array['results'])) {
            return $array['results'];
        }
        
        // If no objects can be found, throw an exception
        throw new Exception("Cannot determine the position of objects in the return body.");
    }

    /**
     * Retrieves the next link for pagination from the API response body.
     *
     * @param array $body The decoded JSON body of the API response.
     * @param Synchronization $synchronization The synchronization object (unused in this method, but kept for consistency).
     * @return string|bool The URL for the next page of results, or false if there is no next page.
     */
    public function getNextlinkFromCall(array $body, Synchronization $synchronization): string | bool | null
    {
        // Check if the 'next' key exists in the response body
        return $body['next'];
    }
}
