<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#detail', 'url' => '/{id}', 'verb' => 'GET'],

        ['name' => 'api#getAssociations', 'url' => '/api/1.0/associations', 'verb' => 'GET'],
        ['name' => 'api#createAssociation', 'url' => '/api/1.0/associations', 'verb' => 'POST'],
        ['name' => 'api#updateAssociation', 'url' => '/api/1.0/associations/{id}', 'verb' => 'PUT'],
        ['name' => 'api#deleteAssociation', 'url' => '/api/1.0/associations/{id}', 'verb' => 'DELETE'],

        ['name' => 'api#getMembers', 'url' => '/api/1.0/associations/{id}/members', 'verb' => 'GET'],
        ['name' => 'api#addMember', 'url' => '/api/1.0/associations/{id}/members', 'verb' => 'POST'],
        ['name' => 'api#removeMember', 'url' => '/api/1.0/associations/{id}/members/{userId}', 'verb' => 'DELETE'],

        ['name' => 'api#getAssociationNames', 'url' => '/api/1.0/associations/names', 'verb' => 'GET'],
        ['name' => 'api#getAssociationsList', 'url' => '/api/1.0/associations/list', 'verb' => 'GET'],

        ['name' => 'api#getUserPermissions', 'url' => '/api/1.0/user/permissions', 'verb' => 'GET'],
    ]
];
