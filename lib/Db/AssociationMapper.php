<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AssociationMapper extends QBMapper
{

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'dtc_associations', Association::class);
    }

    
    public function find(int $id): Association
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    public function findByCode(string $code): Association
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('code', $qb->createNamedParameter($code)));
        return $this->findEntity($qb);
    }
    
    public function findByCodes(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->in('code', $qb->createNamedParameter($codes, IQueryBuilder::PARAM_STR_ARRAY)));

        return $this->findEntities($qb);
    }

    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName);
        return $this->findEntities($qb);
    }

    public function findByName(string $name): Association
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)));

        return $this->findEntity($qb);
    }
}
