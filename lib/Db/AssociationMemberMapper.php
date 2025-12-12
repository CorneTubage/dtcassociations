<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AssociationMemberMapper extends QBMapper
{

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'dtc_asso_members', AssociationMember::class);
    }

    /**
     * Find a specific membership by user and group
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function getMember(string $userId, string $groupId): AssociationMember
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));

        return $this->findEntity($qb);
    }

    /**
     * Get all members of a specific association
     * @return AssociationMember[]
     */
    public function getAssociationMembers(string $groupId): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));

        return $this->findEntities($qb);
    }

    /**
     * Get all associations for a user (to know if he is President somewhere)
     * @return AssociationMember[]
     */
    public function getUserAssociations(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntities($qb);
    }
}
