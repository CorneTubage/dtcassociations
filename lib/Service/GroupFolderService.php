<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Constants;
use Psr\Log\LoggerInterface;
use OCP\App\IAppManager;

/**
 * Service pour gérer les Dossiers de Groupe.
 */
class GroupFolderService
{
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IRootFolder $rootFolder;
    private LoggerInterface $logger;
    private IAppManager $appManager;

    private $folderManager = null;
    private $ruleManager = null;
    private $mappingManager = null;
    private const QUOTA_ASSO = 10;  // 10 GB

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        IRootFolder $rootFolder,
        LoggerInterface $logger,
        IAppManager $appManager
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->appManager = $appManager;
    }

    private function log($message, $level = 'info'): void
    {
        if (!method_exists($this->logger, $level)) $level = 'info';
        $this->logger->$level("[DTC] " . $message, ['app' => 'dtcassociations']);
    }

    private function getService(string $className)
    {
        if (!$this->appManager->isEnabledForUser('groupfolders')) {
            $this->log("App GroupFolders non active", 'error');
            throw new \Exception("GroupFolders missing");
        }
        return \OC::$server->get($className);
    }

    public function ensureGlobalGroupsExist(): void
    {
        $groups = ['president', 'admin_iut'];
        foreach ($groups as $gid) {
            if (!$this->groupManager->groupExists($gid)) {
                $this->groupManager->createGroup($gid);
            }
        }
    }

    public function updateUserGlobalGroup(string $userId, string $groupName, bool $shouldBeIn): void
    {
        $this->ensureGlobalGroupsExist();
        $group = $this->groupManager->get($groupName);
        $user = $this->userManager->get($userId);

        if (!$group || !$user) return;

        $isIn = $group->inGroup($user);
        if ($shouldBeIn && !$isIn) {
            $group->addUser($user);
        } elseif (!$shouldBeIn && $isIn) {
            $group->removeUser($user);
        }
    }

    public function ensureAssociationStructure(string $assoName): int
    {
        $this->log("Ensure structure: $assoName");

        if (!$this->groupManager->groupExists($assoName)) {
            $this->groupManager->createGroup($assoName);
        }

        try {
            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');

            $folderId = -1;
            $allFolders = $fm->getAllFolders();
            foreach ($allFolders as $id => $folder) {
                $name = is_string($folder) ? $folder : $folder->mountPoint;
                if ($name === $assoName) {
                    $folderId = $id;
                    break;
                }
            }

            if ($folderId === -1) {
                $folderId = $fm->createFolder($assoName);
                $this->log("Created folder ID $folderId");

                if (method_exists($fm, 'setFolderQuota')) {
                    $quotaBytes = self::QUOTA_ASSO * 1024 * 1024 * 1024;
                    $fm->setFolderQuota($folderId, $quotaBytes);
                    $this->log("Quota set to 10GB for folder ID $folderId");
                }
            }

            try {
                $fm->addApplicableGroup($folderId, $assoName);
                $fm->addApplicableGroup($folderId, 'admin');
                $fm->addApplicableGroup($folderId, 'admin_iut');

                if (method_exists($fm, 'setGroupPermissions')) {
                    $fm->setGroupPermissions($folderId, $assoName, Constants::PERMISSION_ALL);
                    $fm->setGroupPermissions($folderId, 'admin_iut', Constants::PERMISSION_ALL);
                }
            } catch (\Throwable $e) {
            }

            $this->createSubFolders($assoName);
            $this->applyAdminIutAcl($folderId, $assoName);

            return $folderId;
        } catch (\Throwable $e) {
            $this->log("Error structure: " . $e->getMessage(), 'error');
            return -1;
        }
    }

    private function applyAdminIutAcl(int $folderId, string $assoName): void
    {
        try {
            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
            $rm = $this->getService('OCA\GroupFolders\ACL\RuleManager');
            $mm = $this->getService('OCA\GroupFolders\ACL\UserMapping\UserMappingManager');

            if (method_exists($fm, 'setFolderACL')) $fm->setFolderACL($folderId, true);
            elseif (method_exists($fm, 'setAcl')) $fm->setAcl($folderId, 1);

            $allMappings = $mm->getAllMappings($folderId);
            $mapping = null;

            foreach ($allMappings as $m) {
                $mappingId = method_exists($m, 'getId') ? $m->getId() : '';
                if ($mappingId === 'admin_iut') {
                    $mapping = $m;
                    break;
                }
            }

            if (!$mapping) return;

            $this->applyRulesForMapping($rm, $mapping, $assoName, 'admin_iut');
        } catch (\Throwable $e) {
            $this->log("Error applyAdminIutAcl: " . $e->getMessage(), 'error');
        }
    }

    private function createSubFolders(string $assoName): void
    {
        try {
            $userFolder = $this->rootFolder->getUserFolder('admin');
            $mountPoint = $userFolder->get($assoName);

            foreach (['archive', 'officiel'] as $dir) {
                if (!$mountPoint->nodeExists($dir)) $mountPoint->newFolder($dir);
            }

            $officiel = $mountPoint->get('officiel');
            foreach (['General', 'Tresorerie'] as $sub) {
                if (!$officiel->nodeExists($sub)) $officiel->newFolder($sub);
            }
        } catch (\Throwable $e) {
        }
    }

    public function deleteStructure(string $assoName): void
    {
        try {
            if ($this->groupManager->groupExists($assoName)) {
                $this->groupManager->get($assoName)->delete();
            }

            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
            $allFolders = $fm->getAllFolders();
            foreach ($allFolders as $id => $folder) {
                $name = is_string($folder) ? $folder : $folder->mountPoint;
                if ($name === $assoName) {
                    if (method_exists($fm, 'removeFolder')) {
                        $fm->removeFolder($id);
                    } elseif (method_exists($fm, 'deleteFolder')) {
                        $fm->deleteFolder($id);
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->log("Error delete: " . $e->getMessage(), 'error');
        }
    }

    public function renameFolder(string $oldName, string $newName): void
    {
        try {
            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
            $allFolders = $fm->getAllFolders();
            $this->log("Renaming from '$oldName' to '$newName'");

            foreach ($allFolders as $id => $folder) {
                $name = is_string($folder) ? $folder : $folder->mountPoint;
                if ($name === $oldName) {
                    if (method_exists($fm, 'renameFolder')) {
                        $fm->renameFolder($id, $newName);
                    } elseif (method_exists($fm, 'setFolderName')) {
                        $fm->setFolderName($id, $newName);
                    }

                    if (!$this->groupManager->groupExists($newName)) {
                        $this->groupManager->createGroup($newName);
                    }

                    $newGroup = $this->groupManager->get($newName);

                    if ($this->groupManager->groupExists($oldName)) {
                        $oldGroup = $this->groupManager->get($oldName);
                        $users = $oldGroup->getUsers();

                        foreach ($users as $user) {
                            if (!$newGroup->inGroup($user)) {
                                $newGroup->addUser($user);
                            }
                        }

                        $fm->addApplicableGroup($id, $newName);
                        if (method_exists($fm, 'setGroupPermissions')) {
                            $fm->setGroupPermissions($id, $newName, Constants::PERMISSION_ALL);
                        }

                        $fm->removeApplicableGroup($id, $oldName);
                        $oldGroup->delete();
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->log("Error rename: " . $e->getMessage(), 'error');
        }
    }

    public function addUserToGroup(string $userId, string $groupName): void
    {
        try {
            $group = $this->groupManager->get($groupName);
            $user = $this->userManager->get($userId);
            if ($group && $user && !$group->inGroup($user)) {
                $group->addUser($user);
            }
        } catch (\Throwable $e) {
        }
    }

    public function removeUserFromGroup(string $userId, string $groupName): void
    {
        try {
            $group = $this->groupManager->get($groupName);
            $user = $this->userManager->get($userId);
            if ($group && $user && $group->inGroup($user)) {
                $group->removeUser($user);
            }
        } catch (\Throwable $e) {
        }
    }

    public function updatePresidentGroupMembership(string $userId, bool $shouldBeInGroup): void
    {
        $groupName = 'president';
        if (!$this->groupManager->groupExists($groupName)) {
            $this->groupManager->createGroup($groupName);
        }
        $group = $this->groupManager->get($groupName);
        $user = $this->userManager->get($userId);

        if (!$group || !$user) return;

        $isIn = $group->inGroup($user);
        if ($shouldBeInGroup && !$isIn) {
            $group->addUser($user);
        } elseif (!$shouldBeInGroup && $isIn) {
            $group->removeUser($user);
        }
    }

    public function applyRolePermissions(int $folderId, string $userId, string $role): void
    {
        try {
            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
            $rm = $this->getService('OCA\GroupFolders\ACL\RuleManager');
            $mm = $this->getService('OCA\GroupFolders\ACL\UserMapping\UserMappingManager');

            $allFolders = $fm->getAllFolders();
            $folderObject = $allFolders[$folderId];
            $assoName = is_string($folderObject) ? $folderObject : $folderObject->mountPoint;

            $this->addUserToGroup($userId, $assoName);

            try {
                if (method_exists($fm, 'setFolderACL')) $fm->setFolderACL($folderId, true);
                elseif (method_exists($fm, 'setAcl')) $fm->setAcl($folderId, 1);
            } catch (\Throwable $e) {
            }

            $userObj = $this->userManager->get($userId);
            if (!$userObj) return;

            $allMappings = $mm->getMappingsForUser($userObj);
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

            $this->applyRulesForMapping($rm, $mapping, $assoName, $role);
        } catch (\Throwable $e) {
            $this->log("Error ACL: " . $e->getMessage(), 'error');
        }
    }

    private function applyRulesForMapping($rm, $mapping, string $assoName, string $role): void
    {
        $userFolder = $this->rootFolder->getUserFolder('admin');

        try {
            $rootNode = $userFolder->get($assoName);
        } catch (\Exception $e) {
            return;
        }

        $this->setRule($rm, $mapping, $rootNode->getId(), Constants::PERMISSION_READ);

        if ($rootNode->nodeExists('archive')) {
            $archiveId = $rootNode->get('archive')->getId();
            $archivePerms = Constants::PERMISSION_READ;
            if ($role === 'president' || $role === 'admin_iut') {
                $archivePerms = Constants::PERMISSION_ALL & ~Constants::PERMISSION_DELETE;
            }
            $this->setRule($rm, $mapping, $archiveId, $archivePerms);
        }

        if ($rootNode->nodeExists('officiel')) {
            $officiel = $rootNode->get('officiel');
            $this->setRule($rm, $mapping, $officiel->getId(), Constants::PERMISSION_READ);

            if ($officiel->nodeExists('General')) {
                $this->setRule($rm, $mapping, $officiel->get('General')->getId(), Constants::PERMISSION_ALL);
            }

            if ($officiel->nodeExists('Tresorerie')) {
                $treso = $officiel->get('Tresorerie');
                if ($role === 'president' || $role === 'treasurer' || $role === 'tresorier' || $role === 'admin_iut') {
                    $this->setRule($rm, $mapping, $treso->getId(), Constants::PERMISSION_ALL);
                } else {
                    $this->setRule($rm, $mapping, $treso->getId(), 0);
                }
            }
        }
    }

    private function setRule($ruleManager, $mapping, int $fileId, int $permissions): void
    {
        $fqcnRule = 'OCA\GroupFolders\ACL\Rule';
        try {
            $del = new $fqcnRule($mapping, $fileId, Constants::PERMISSION_ALL, 0);
            $ruleManager->deleteRule($del);
        } catch (\Throwable $e) {
        }

        try {
            $add = new $fqcnRule($mapping, $fileId, Constants::PERMISSION_ALL, $permissions);
            $ruleManager->saveRule($add);
        } catch (\Throwable $e) {
        }
    }
}
