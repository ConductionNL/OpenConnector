<?php

namespace OCA\OpenConnector\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

class CallLogMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'openconnector_call_logs');
    }

    public function find(int $id): CallLog
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('openconnector_call_logs')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity($qb);
    }

    public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('openconnector_call_logs')
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

        return $this->findEntities($qb);
    }

    public function createFromArray(array $object): CallLog
    {
        $obj = new CallLog();
		$obj->hydrate($object);
		// Set uuid
		if($obj->getUuid() === null){
			$obj->setUuid(Uuid::v4());
		}
        return $this->insert($obj);
    }

    public function updateFromArray(int $id, array $object): CallLog
    {
        $obj = $this->find($id);
		$obj->hydrate($object);;
		// Set uuid
		if($obj->getUuid() === null){
			$obj->setUuid(Uuid::v4());
		}

        return $this->update($obj);
    }

    /**
     * Clear expired logs from the database.
     *
     * This method deletes all call logs that have expired (i.e., their 'expired' date is earlier than the current date and time).
     *
     * @return bool True if any logs were deleted, false otherwise.
     */
    public function clearLogs(): bool
    {
        // Get the query builder
        $qb = $this->db->getQueryBuilder();

        // Build the delete query
        $qb->delete('openconnector_call_logs')
           ->where($qb->expr()->lt('expired', $qb->createFunction('NOW()')));

        // Execute the query and get the number of affected rows
        $result = $qb->execute();

        // Return true if any rows were affected (i.e., any logs were deleted)
        return $result > 0;
    }

    /**
     * Get call log counts grouped by creation date.
     *
     * @return array An associative array where the key is the creation date and the value is the count of calls created on that date.
     */
    public function getCallCountsByDate(): array
    {
        $qb = $this->db->getQueryBuilder();

        // Select the date part of the created timestamp and count of logs
        $qb->select($qb->createFunction('DATE(created) as date'), $qb->createFunction('COUNT(*) as count'))
           ->from('openconnector_call_logs')
           ->groupBy('date')
           ->orderBy('date', 'ASC');

        $result = $qb->execute();
        $counts = [];

        // Fetch results and build the return array
        while ($row = $result->fetch()) {
            $counts[$row['date']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Get call log counts grouped by creation time (hour).
     *
     * @return array An associative array where the key is the creation time (hour) and the value is the count of calls created at that time.
     */
    public function getCallCountsByTime(): array
    {
        $qb = $this->db->getQueryBuilder();

        // Select the hour part of the created timestamp and count of logs
        $qb->select($qb->createFunction('HOUR(created) as hour'), $qb->createFunction('COUNT(*) as count'))
           ->from('openconnector_call_logs')
           ->groupBy('hour')
           ->orderBy('hour', 'ASC');

        $result = $qb->execute();
        $counts = [];

        // Fetch results and build the return array
        while ($row = $result->fetch()) {
            $counts[$row['hour']] = (int)$row['count'];
        }

        return $counts;
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
           ->from('openconnector_call_logs');

        $result = $qb->execute();
        $row = $result->fetch();

        // Return the total count
        return (int)$row['count'];
    }

    /**
     * Get the last call log.
     *
     * @return CallLog|null The last call log or null if no logs exist.
     */
    public function getLastCallLog(): ?CallLog
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
           ->from('openconnector_call_logs')
           ->orderBy('created', 'DESC')
           ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return null;
        }
    }
}
