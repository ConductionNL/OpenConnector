<?php

namespace OCA\OpenConnector\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Synchronization extends Entity implements JsonSerializable
{
    protected ?string $uuid = null;
	protected ?string $name = null;	// The name of the synchronization
	protected ?string $description = null;	// The description of the synchronization
	protected ?string $version = null;	// The version of the synchronization
	// Source
	protected ?string $sourceId = null;	// The id of the source object
	protected ?string $sourceType = null;	// The type of the source object (e.g. api, database, register/schema.)
	protected ?string $sourceHash = null;	// The hash of the source object when it was last synced.
	protected ?string $sourceTargetMapping = null;	// The mapping of the source object to the target object
	protected ?array $sourceConfig = null; // The configuration of the object in the source
	protected ?DateTime $sourceLastChanged = null;	// The last changed date of the source object
	protected ?DateTime $sourceLastChecked = null;	// The last checked date of the source object
	protected ?DateTime $sourceLastSynced = null;	// The last synced date of the source object
	// Target
	protected ?string $targetId = null;	// The id of the target object
	protected ?string $targetType = null;	// The type of the target object (e.g. api, database, register/schema.)
	protected ?string $targetHash = null;	// The hash of the target object
	protected ?string $targetSourceMapping = null;	// The mapping of the target object to the source object
	protected ?array $targetConfig = null; // The configuration of the object in the target
	protected ?DateTime $targetLastChanged = null;	// The last changed date of the target object
	protected ?DateTime $targetLastChecked = null;	// The last checked date of the target object
	protected ?DateTime $targetLastSynced = null;	// The last synced date of the target object
	// General
	protected ?DateTime $created = null;	// The date and time the synchronization was created
	protected ?DateTime $updated = null;	// The date and time the synchronization was updated


	public function __construct() {
        $this->addType('uuid', 'string');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('version', 'string');
		$this->addType('sourceId', 'string');
		$this->addType('sourceType', 'string');
		$this->addType('sourceHash', 'string');
		$this->addType('sourceTargetMapping', 'string');
		$this->addType('sourceConfig', 'json');
		$this->addType('sourceLastChanged', 'datetime');
		$this->addType('sourceLastChecked', 'datetime');
		$this->addType('sourceLastSynced', 'datetime');
		$this->addType('targetId', 'string');
		$this->addType('targetType', 'string');
		$this->addType('targetHash', 'string');
		$this->addType('targetSourceMapping', 'string');
		$this->addType('targetConfig', 'json');
		$this->addType('targetLastChanged', 'datetime');
		$this->addType('targetLastChecked', 'datetime');
		$this->addType('targetLastSynced', 'datetime');
		$this->addType('created', 'datetime');
		$this->addType('updated', 'datetime');
	}

	public function getJsonFields(): array
	{
		return array_keys(
			array_filter($this->getFieldTypes(), function ($field) {
				return $field === 'json';
			})
		);
	}

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
				// Error handling could be improved here
			}
		}

		return $this;
	}

	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'uuid' => $this->uuid,
			'name' => $this->name,
			'description' => $this->description,
			'version' => $this->version,
			'sourceId' => $this->sourceId,
			'sourceType' => $this->sourceType,
			'sourceHash' => $this->sourceHash,
			'sourceTargetMapping' => $this->sourceTargetMapping,
			'sourceConfig' => $this->sourceConfig,
			'sourceLastChanged' => $this->sourceLastChanged,
			'sourceLastChecked' => $this->sourceLastChecked,
			'sourceLastSynced' => $this->sourceLastSynced,
			'targetId' => $this->targetId,
			'targetType' => $this->targetType,
			'targetHash' => $this->targetHash,
			'targetSourceMapping' => $this->targetSourceMapping,
			'targetConfig' => $this->targetConfig,
			'targetLastChanged' => $this->targetLastChanged,
			'targetLastChecked' => $this->targetLastChecked,
			'targetLastSynced' => $this->targetLastSynced,
			'created' => isset($this->created) ? $this->created->format('c') : null,
			'updated' => isset($this->updated) ? $this->updated->format('c') : null
		];
	}
}
