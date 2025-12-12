<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AssociationMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'dtc_associations', Association::class);
    }

    /**
     * Récupère une association par son ID
     *
     * @throws DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Association
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function findByCode(string $code): Association
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('code', $qb->createNamedParameter($code)));

        return $this->findEntity($qb);
    }

    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName);

        return $this->findEntities($qb);
    }
}
