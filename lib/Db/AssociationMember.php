<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Db;

use OCP\AppFramework\Db\Entity;

class AssociationMember extends Entity
{
    protected string $userId = '';
    protected string $groupId = '';
    protected string $role = '';

    public function __construct()
    {
        $this->addType('userId', 'string');
        $this->addType('groupId', 'string');
        $this->addType('role', 'string');
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'group_id' => $this->groupId,
            'role' => $this->role,
        ];
    }
}
