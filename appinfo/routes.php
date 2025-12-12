<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'api#getAssociations', 'url' => '/api/1.0/associations', 'verb' => 'GET'],
        ['name' => 'api#createAssociation', 'url' => '/api/1.0/associations', 'verb' => 'POST'],
        ['name' => 'api#deleteAssociation', 'url' => '/api/1.0/associations/{id}', 'verb' => 'DELETE'],
        ['name' => 'api#updateAssociation', 'url' => '/api/1.0/associations/{id}', 'verb' => 'PUT'],
        ['name' => 'api#getMembers', 'url' => '/api/1.0/associations/{id}/members', 'verb' => 'GET'],
        ['name' => 'api#addMember', 'url' => '/api/1.0/associations/{id}/members', 'verb' => 'POST'],
        ['name' => 'api#removeMember', 'url' => '/api/1.0/associations/{id}/members/{userId}', 'verb' => 'DELETE'],
    ],
];
