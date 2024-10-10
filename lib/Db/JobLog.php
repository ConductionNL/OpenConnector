<?php

namespace OCA\OpenConnector\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class JobLog extends Entity implements JsonSerializable
{
    protected ?string $uuid = null;
    protected string $level = 'INFO';
    protected string $message = 'success';
    protected ?string $jobId = null;
    protected ?string $jobListId = null;
    protected ?string $jobClass = 'OCA\OpenConnector\Action\PingAction';
    protected ?array $arguments = null;
    protected ?int $executionTime = 3600;
    protected ?string $userId = null;
    protected ?string $sessionId = null;
    protected ?array $stackTrace = [];
    protected ?DateTime $expires = null;
    protected ?DateTime $lastRun = null;
    protected ?DateTime $nextRun = null;
    protected ?DateTime $created = null;

    public function __construct() {
        $this->addType('uuid', 'string');
        $this->addType('level', 'string');
        $this->addType('message', 'string');
        $this->addType('jobId', 'string');
        $this->addType('jobListId', 'string');
        $this->addType('jobClass', 'string');
        $this->addType('arguments', 'json');
        $this->addType('executionTime', 'integer');
        $this->addType('userId', 'string');
        $this->addType('sessionId', 'string');
        $this->addType('stackTrace', 'json');
        $this->addType('expires', 'datetime');
        $this->addType('lastRun', 'datetime');
        $this->addType('nextRun', 'datetime');
        $this->addType('created', 'datetime');
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
                // Handle or log the exception if needed
            }
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'level' => $this->level,
            'message' => $this->message,
            'jobId' => $this->jobId,
            'jobListId' => $this->jobListId,
            'jobClass' => $this->jobClass,
            'arguments' => $this->arguments,
            'executionTime' => $this->executionTime,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'stackTrace' => $this->stackTrace,
            'expires' => isset($this->expires) ? $this->expires->format('c') : null,
            'lastRun' => isset($this->lastRun) ? $this->lastRun->format('c') : null,
            'nextRun' => isset($this->nextRun) ? $this->nextRun->format('c') : null,
            'created' => isset($this->created) ? $this->created->format('c') : null,
            
        ];
    }
}
