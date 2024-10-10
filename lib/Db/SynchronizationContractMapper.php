<?php

namespace OCA\OpenConnector\Db;

use OCA\OpenConnector\Db\SynchronizationContract;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

class SynchronizationContractMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'openconnector_synchronization_contracts');
	}

	public function find(int $id): SynchronizationContract
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronization_contracts')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity(query: $qb);
	}

	public function findOnSynchronizationIdSourceId(string $synchronizationId, string $sourceId): ?SynchronizationContract
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronization_contracts')
			->where(
				$qb->expr()->eq('synchronization_id', $qb->createNamedParameter($synchronizationId))
			)
			->andWhere(
				$qb->expr()->eq('source_id', $qb->createNamedParameter($sourceId))
			);

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return null;
		}
	}


	public function findOnTarget(string $synchronization, string $targetId): SynchronizationContract|bool|null
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronization_contracts')
			->where(
				$qb->expr()->eq('synchronization_id', $qb->createNamedParameter($synchronization))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter($targetId))
			);


		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return null;
		}
	}

	public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('openconnector_synchronization_contracts')
			->setMaxResults($limit)
			->setFirstResult($offset);

        foreach ($filters as $filter => $value) {
			if ($value === 'IS NOT NULL') {
				$qb->andWhere($qb->expr()->isNotNull($filter));
			} elseif ($value === 'IS NULL') {
				$qb->andWhere($qb->expr()->isNull($filter));
			} else {
				$qb->andWhere($qb->expr()->eq($filter, $qb->createNamedParameter($value)));
			}
        }

        if (!empty($searchConditions)) {
            $qb->andWhere('(' . implode(' OR ', $searchConditions) . ')');
            foreach ($searchParams as $param => $value) {
                $qb->setParameter($param, $value);
            }
        }

		return $this->findEntities(query: $qb);
	}

	public function createFromArray(array $object): SynchronizationContract
	{
		$obj = new SynchronizationContract();
		$obj->hydrate(object: $object);
		// Set uuid
		if($obj->getUuid() === null){
			$obj->setUuid(Uuid::v4());
		}
		return $this->insert(entity: $synchronizationContract);
	}

	public function updateFromArray(int $id, array $object): SynchronizationContract
	{
		$obj = $this->find($id);
		$obj->hydrate($object);
		
		// Set or update the version
		$version = explode('.', $obj->getVersion());
		$version[2] = (int)$version[2] + 1;
		$obj->setVersion(implode('.', $version));

		return $this->update($obj);
	}

	/**
	 * Find synchronization contracts by type and ID
	 *
	 * This method searches for synchronization contracts where either the source or target
	 * matches the given type and ID.
	 *
	 * @param string $type The type to search for (e.g., 'user', 'group', etc.)
	 * @param string $id The ID to search for within the given type
	 * @return array An array of SynchronizationContract entities matching the criteria
	 */
	public function findByTypeAndId(string $type, string $id): array
	{
		$qb = $this->db->getQueryBuilder();

		// Build a query to select all columns from the synchronization contracts table
		$qb->select('*')
			->from('openconnector_synchronization_contracts')
			->where(
				$qb->expr()->orX(
					// Check if the contract matches as a source
					$qb->expr()->andX(
						$qb->expr()->eq('source_type', $qb->createNamedParameter($type)),
						$qb->expr()->eq('source_id', $qb->createNamedParameter($id))
					),
					// Check if the contract matches as a target
					$qb->expr()->andX(
						$qb->expr()->eq('target_type', $qb->createNamedParameter($type)),
						$qb->expr()->eq('target_id', $qb->createNamedParameter($id))
					)
				)
			);

		// Execute the query and return the resulting entities
		return $this->findEntities($qb);
	}

    /**
     * Get the total count of all call logs.
     *
     * @return int The total number of call logs in the database.
     */
    public function getTotalCallCount(): int
    {
        $qb = $this->db->getQueryBuilder();

        // Select count of all logs
        $qb->select($qb->createFunction('COUNT(*) as count'))
           ->from('openconnector_synchronization_contracts');

        $result = $qb->execute();
        $row = $result->fetch();

        // Return the total count
        return (int)$row['count'];
    }
}
