<?php

namespace OCA\OpenConnector\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * This class is used to define a contract for a synchronization. Or in other words, a contract between a source and target object.
 *
 * @package OCA\OpenConnector\Db
 */
class SynchronizationContract extends Entity implements JsonSerializable
{
    protected ?string $uuid = null;
    protected ?string $version = null;
	protected ?string $synchronizationId = null; // The synchronization that this contract belongs to
	// Source
	protected ?string $sourceId = null; // The id of the object in the source
	protected ?string $sourceHash = null; // The hash of the object in the source
	protected ?DateTime $sourceLastChanged = null; // The last changed date of the object in the source
	protected ?DateTime $sourceLastChecked = null; // The last checked date of the object in the source
	protected ?DateTime $sourceLastSynced = null; // The last synced date of the object in the source
	// Target
	protected ?string $targetId = null; // The id of the object in the target
	protected ?string $targetHash = null; // The hash of the object in the target
	protected ?DateTime $targetLastChanged = null; // The last changed date of the object in the target
	protected ?DateTime $targetLastChecked = null; // The last checked date of the object in the target
	protected ?DateTime $targetLastSynced = null; // The last synced date of the object in the target
	// General
	protected ?DateTime $created = null; // the date and time the synchronization was created
	protected ?DateTime $updated = null; // the date and time the synchronization was updated


	public function __construct() {
        $this->addType('uuid', 'string');
        $this->addType('version', 'string');
		$this->addType('synchronizationId', 'string');
		$this->addType('sourceId', 'string');
		$this->addType('sourceHash', 'string');
		$this->addType('sourceLastChanged', 'datetime');
		$this->addType('sourceLastChecked', 'datetime');
		$this->addType('sourceLastSynced', 'datetime');
		$this->addType('targetId', 'string');
		$this->addType('targetHash', 'string');
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
			'version' => $this->version,
			'synchronizationId' => $this->synchronizationId,
			'sourceId' => $this->sourceId,
			'sourceHash' => $this->sourceHash,
			'sourceLastChanged' => $this->sourceLastChanged,
			'sourceLastChecked' => $this->sourceLastChecked,
			'sourceLastSynced' => $this->sourceLastSynced,
			'targetId' => $this->targetId,
			'targetHash' => $this->targetHash,
			'targetLastChanged' => $this->targetLastChanged,
			'targetLastChecked' => $this->targetLastChecked,
			'targetLastSynced' => $this->targetLastSynced,
			'created' => $this->created,
			'updated' => $this->updated
		];
	}
}
