<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Constants;
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

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        IRootFolder $rootFolder,
        FolderManager $folderManager,
        RuleManager $ruleManager,
        UserMappingManager $mappingManager
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->rootFolder = $rootFolder;
        $this->folderManager = $folderManager;
        $this->ruleManager = $ruleManager;
        $this->mappingManager = $mappingManager;
    }

    private function log($message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $msg = "[$timestamp] $message\n";
        $relativePath = dirname(__DIR__, 4) . '/data/dtc_debug.log';
        if (!@file_put_contents($relativePath, $msg, FILE_APPEND)) {
            @file_put_contents(sys_get_temp_dir() . '/dtc_debug.log', "FALLBACK: $msg", FILE_APPEND);
        }
    }

    public function ensureAssociationStructure(string $assoName): int
    {
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
        }

        try {
            $this->folderManager->addApplicableGroup($folderId, $assoName);
            if (method_exists($this->folderManager, 'setGroupPermissions')) {
                $this->folderManager->setGroupPermissions($folderId, $assoName, Constants::PERMISSION_ALL);
            }
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
            $this->log("Error creating subfolders: " . $e->getMessage());
        }
    }

    // --- CORRECTION SUPPRESSION ---
    public function deleteStructure(string $assoName): void
    {
        $this->log("Deleting structure for $assoName");

        // Supprimer le groupe Nextcloud
        if ($this->groupManager->groupExists($assoName)) {
            $this->groupManager->get($assoName)->delete();
        }

        // Supprimer le Group Folder
        $allFolders = $this->folderManager->getAllFolders();
        foreach ($allFolders as $id => $folder) {
            $name = is_string($folder) ? $folder : $folder->mountPoint;
            if ($name === $assoName) {
                // CORRECTION : Utilisation de removeFolder (API correcte)
                if (method_exists($this->folderManager, 'removeFolder')) {
                    $this->folderManager->removeFolder($id);
                    $this->log("Deleted Group Folder ID $id using removeFolder");
                } else {
                    $this->log("CRITICAL: No delete method found on FolderManager");
                }
                break;
            }
        }
    }

    // --- CORRECTION RENOMMAGE ---
    public function renameFolder(string $oldName, string $newName): void
    {
        $allFolders = $this->folderManager->getAllFolders();
        $this->log("Attempting rename from '$oldName' to '$newName'");

        foreach ($allFolders as $id => $folder) {
            $name = is_string($folder) ? $folder : $folder->mountPoint;
            if ($name === $oldName) {
                if (method_exists($this->folderManager, 'renameFolder')) {
                    $this->folderManager->renameFolder($id, $newName);
                    $this->log("Renamed Group Folder ID $id (renameFolder)");
                } else {
                    $this->log("CRITICAL: No rename method found on FolderManager");
                }
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
        $allFolders = $this->folderManager->getAllFolders();
        $folderObject = $allFolders[$folderId];
        $assoName = is_string($folderObject) ? $folderObject : $folderObject->mountPoint;

        $this->addUserToGroup($userId, $assoName);

        /** @var Folder $this */
        try {
            if (method_exists($this->folderManager, 'setFolderACL')) {
                $this->folderManager->setFolderACL($folderId, true);
            } elseif (method_exists($this->folderManager, 'setAcl')) {
                $this->folderManager->setAcl($folderId, 1);
            }
        } catch (\Throwable $e) {
        }

        $userObj = $this->userManager->get($userId);
        $allMappings = $this->mappingManager->getMappingsForUser($userObj);

        if (empty($allMappings)) return;

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

        /** @var ACL $this */
        $this->setRule($mapping, $rootNode->getId(), Constants::PERMISSION_ALL);

        if ($rootNode->nodeExists('archive')) {
            $this->setRule($mapping, $rootNode->get('archive')->getId(), Constants::PERMISSION_READ);
        }

        if ($rootNode->nodeExists('officiel')) {
            $officiel = $rootNode->get('officiel');
            $this->setRule($mapping, $officiel->getId(), Constants::PERMISSION_ALL);

            if ($officiel->nodeExists('General')) {
                $this->setRule($mapping, $officiel->get('General')->getId(), Constants::PERMISSION_ALL);
            }

            if ($officiel->nodeExists('Tresorie')) {
                $treso = $officiel->get('Tresorie');
                if ($role === 'president' || $role === 'treasurer' || $role === 'tresorier') {
                    $this->setRule($mapping, $treso->getId(), Constants::PERMISSION_ALL);
                } else {
                    $this->setRule($mapping, $treso->getId(), 0);
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
        }
    }
}
