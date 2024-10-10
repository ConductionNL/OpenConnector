<?php

namespace OCA\OpenConnector\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Class Consumer
 *
 * A consumer is a service or application that consumes events, has access to endpoints and mappings,
 * and is able to trigger actions based on the events. It is the main actor in the openconnector platform
 * and determines authentication and authorizations on all aspects of the platform.
 *
 * @package OCA\OpenConnector\Db
 */
class Consumer extends Entity implements JsonSerializable
{
    protected ?string $uuid = null;
	protected ?string $name = null; // The name of the consumer	
	protected ?string $description = null; // The description of the consumer
    protected ?array $domains = []; // The domains the consumer is allowed to run from
    protected ?array $ips = []; // The ips the consumer is allowed to run from
    protected ?string $authorizationType = null; // The authorization type of the consumer, should be one of the following: 'none', 'basic', 'bearer', 'apiKey', 'oauth2', 'jwt'. Keep in mind that the consumer needs to be able to handle the authorization type.
    protected ?string $authorizationConfiguration = null; // The authorization configuration of the consumer	
	protected ?DateTime $created = null; // the date and time the consumer was created
	protected ?DateTime $updated = null; // the date and time the consumer was updated

	/**
	 * Consumer constructor.
	 * Initializes the field types for the Consumer entity.
	 */
	public function __construct() {
        $this->addType('uuid', 'string');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('domains', 'json');
		$this->addType('ips', 'json');
		$this->addType('authorizationType', 'string');
		$this->addType('authorizationConfiguration', 'string');
		$this->addType('created', 'datetime');
		$this->addType('updated', 'datetime');
	}

	/**
	 * Get the JSON fields of the Consumer entity.
	 *
	 * @return array An array of field names that are of type 'json'
	 */
	public function getJsonFields(): array
	{
		return array_keys(
			array_filter($this->getFieldTypes(), function ($field) {
				return $field === 'json';
			})
		);
	}

	/**
	 * Hydrate the Consumer entity with data from an array.
	 *
	 * @param array $object The array containing the data to hydrate the entity
	 * @return self Returns the hydrated Consumer entity
	 */
	public function hydrate(array $object): self
	{
		$jsonFields = $this->getJsonFields();

		foreach ($object as $key => $value) {
			if (in_array($key, $jsonFields) === true && $value === []) {
				$value = [];
			}

			$method = 'set'.ucfirst($key);

			try {
				$this->$method($value);
			} catch (\Exception $exception) {
//				("Error writing $key");
			}
		}

		return $this;
	}

	/**
	 * Serialize the Consumer entity to JSON.
	 *
	 * @return array An array representation of the Consumer entity for JSON serialization
	 */
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
            'uuid' => $this->uuid,
			'name' => $this->name,
			'description' => $this->description,
			'domains' => $this->domains,
			'ips' => $this->ips,
			'authorizationType' => $this->authorizationType,
			'authorizationConfiguration' => $this->authorizationConfiguration,
			'created' => isset($this->created) ? $this->created->format('c') : null,
			'updated' => isset($this->updated) ? $this->updated->format('c') : null,
		];
	}
}
