<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Constants;
use OCP\ILogger;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;

class GroupFolderService
{
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IRootFolder $rootFolder;
    private FolderManager $folderManager;
    private RuleManager $ruleManager;
    private UserMappingManager $mappingManager;
    private ILogger $logger;

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        IRootFolder $rootFolder,
        FolderManager $folderManager,
        RuleManager $ruleManager,
        UserMappingManager $mappingManager,
        ILogger $logger
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->rootFolder = $rootFolder;
        $this->folderManager = $folderManager;
        $this->ruleManager = $ruleManager;
        $this->mappingManager = $mappingManager;
        $this->logger = $logger;
    }

    private function log($message, $level = 'info'): void
    {
        /** @var ILogger $this */
        $context = ['app' => 'dtcassociations'];
        if ($level === 'error') {
            $this->logger->error($message, $context);
        } else {
            $this->logger->info($message, $context);
        }
    }

    public function ensureAssociationStructure(string $assoName): int
    {
        $this->log("Checking structure for $assoName");

        if (!$this->groupManager->groupExists($assoName)) {
            $this->groupManager->createGroup($assoName);
        }

        $folderId = -1;
        $allFolders = $this->folderManager->getAllFolders();

        foreach ($allFolders as $id => $folder) {
            $name = is_string($folder) ? $folder : $folder->mountPoint;
            if ($name === $assoName) {
                $folderId = $id;
                break;
            }
        }

        if ($folderId === -1) {
            $folderId = $this->folderManager->createFolder($assoName);
            $this->log("Created Group Folder $assoName (ID: $folderId)");
        }

        try {
            $this->folderManager->addApplicableGroup($folderId, $assoName);
            $this->folderManager->setGroupPermissions($folderId, $assoName, Constants::PERMISSION_ALL);
        } catch (\Throwable $e) {
        }

        try {
            $this->folderManager->addApplicableGroup($folderId, 'admin');
        } catch (\Throwable $e) {
        }

        $this->createSubFolders($assoName);

        return $folderId;
    }

    private function createSubFolders(string $assoName): void
    {
        try {
            $userFolder = $this->rootFolder->getUserFolder('admin');
            /** @var Folder $mountPoint */
            $mountPoint = $userFolder->get($assoName);

            foreach (['archive', 'officiel'] as $dir) {
                if (!$mountPoint->nodeExists($dir)) $mountPoint->newFolder($dir);
            }

            /** @var Folder $officiel */
            $officiel = $mountPoint->get('officiel');
            foreach (['General', 'Tresorie'] as $sub) {
                if (!$officiel->nodeExists($sub)) $officiel->newFolder($sub);
            }
        } catch (\Throwable $e) {
            $this->log("Error creating subfolders: " . $e->getMessage(), 'error');
        }
    }

    public function deleteStructure(string $assoName): void
    {
        $this->log("Deleting structure for $assoName");

        if ($this->groupManager->groupExists($assoName)) {
            $this->groupManager->get($assoName)->delete();
        }

        $allFolders = $this->folderManager->getAllFolders();
        foreach ($allFolders as $id => $folder) {
            $name = is_string($folder) ? $folder : $folder->mountPoint;
            if ($name === $assoName) {
                $this->folderManager->removeFolder($id);
                $this->log("Deleted Group Folder ID $id");
                break;
            }
        }
    }

    public function renameFolder(string $oldName, string $newName): void
    {
        $allFolders = $this->folderManager->getAllFolders();
        $this->log("Renaming folder from '$oldName' to '$newName'");

        foreach ($allFolders as $id => $folder) {
            $name = is_string($folder) ? $folder : $folder->mountPoint;
            if ($name === $oldName) {
                $this->folderManager->renameFolder($id, $newName);
                break;
            }
        }
    }

    public function addUserToGroup(string $userId, string $groupName): void
    {
        $group = $this->groupManager->get($groupName);
        $user = $this->userManager->get($userId);
        if ($group && $user && !$group->inGroup($user)) {
            $group->addUser($user);
        }
    }

    public function removeUserFromGroup(string $userId, string $groupName): void
    {
        $group = $this->groupManager->get($groupName);
        $user = $this->userManager->get($userId);
        if ($group && $user && $group->inGroup($user)) {
            $group->removeUser($user);
        }
    }

    public function applyRolePermissions(int $folderId, string $userId, string $role): void
    {
        $this->log("Applying permissions: User=$userId, Role=$role, FolderID=$folderId");

        $allFolders = $this->folderManager->getAllFolders();
        $folderObject = $allFolders[$folderId];
        $assoName = is_string($folderObject) ? $folderObject : $folderObject->mountPoint;

        $this->addUserToGroup($userId, $assoName);

        try {
            $this->folderManager->setFolderACL($folderId, true);
        } catch (\Throwable $e) {
        }

        $userObj = $this->userManager->get($userId);
        $allMappings = $this->mappingManager->getMappingsForUser($userObj);

        if (empty($allMappings)) {
            $this->log("No mappings found for user $userId", 'error');
            return;
        }

        $mapping = null;
        foreach ($allMappings as $m) {
            $displayName = method_exists($m, 'getDisplayName') ? $m->getDisplayName() : '';
            if ($displayName === $assoName) {
                $mapping = $m;
                break;
            }
        }
        if (!$mapping) $mapping = reset($allMappings);

        $userFolder = $this->rootFolder->getUserFolder('admin');
        $rootNode = $userFolder->get($assoName);

        $this->setRule($mapping, $rootNode->getId(), Constants::PERMISSION_ALL);

        /** @var Folder $rootNode */
        if ($rootNode->nodeExists('archive')) {
            $this->setRule($mapping, $rootNode->get('archive')->getId(), Constants::PERMISSION_READ);
        }

        if ($rootNode->nodeExists('officiel')) {
            $officiel = $rootNode->get('officiel');
            $this->setRule($mapping, $officiel->getId(), Constants::PERMISSION_ALL);

            /** @var Folder $officiel */
            if ($officiel->nodeExists('General')) {
                $this->setRule($mapping, $officiel->get('General')->getId(), Constants::PERMISSION_ALL);
            }

            if ($officiel->nodeExists('Tresorie')) {
                $treso = $officiel->get('Tresorie');
                if ($role === 'president' || $role === 'treasurer' || $role === 'tresorier') {
                    $this->setRule($mapping, $treso->getId(), Constants::PERMISSION_ALL);
                    $this->log("ACL: Tresorie ALLOWED");
                } else {
                    $this->setRule($mapping, $treso->getId(), 0);
                    $this->log("ACL: Tresorie BLOCKED");
                }
            }
        }
    }

    private function setRule($mapping, int $fileId, int $permissions): void
    {
        try {
            $del = new Rule($mapping, $fileId, Constants::PERMISSION_ALL, 0);
            $this->ruleManager->deleteRule($del);
        } catch (\Throwable $e) {
        }

        try {
            $add = new Rule($mapping, $fileId, Constants::PERMISSION_ALL, $permissions);
            $this->ruleManager->saveRule($add);
        } catch (\Throwable $e) {
            $this->log("Error saving rule: " . $e->getMessage(), 'error');
        }
    }
}
