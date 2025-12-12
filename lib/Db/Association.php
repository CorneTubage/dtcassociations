<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Db;

use OCP\AppFramework\Db\Entity;

class Association extends Entity implements \JsonSerializable
{
    protected string $name = '';
    protected string $code = '';

    public function __construct()
    {
        $this->addType('name', 'string');
        $this->addType('code', 'string');
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
