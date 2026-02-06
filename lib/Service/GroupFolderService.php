<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Constants;
use Psr\Log\LoggerInterface;
use OCP\App\IAppManager;

class GroupFolderService
{
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IRootFolder $rootFolder;
    private LoggerInterface $logger;
    private IAppManager $appManager;

    private $folderManager = null;
    private const QUOTA_ASSO = 10;

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
        if (!method_exists($this->logger, $level)) {
            $level = 'info';
        }
        $this->logger->$level("[DTC] " . $message, ['app' => 'dtcassociations']);
    }

    private function getService(string $className)
    {
        if (!$this->appManager->isEnabledForUser('groupfolders')) {
            throw new \Exception("GroupFolders missing");
        }
        return \OC::$server->get($className);
    }

    public function getFolderStats(string $assoName): array
    {
        $stats = [
            'id' => -1,
            'size' => 0,
            'usage' => 0,
            'quota' => self::QUOTA_ASSO * 1024 * 1024 * 1024
        ];

        try {
            $userFolder = $this->rootFolder->getUserFolder('admin');
            if ($userFolder->nodeExists($assoName)) {
                $node = $userFolder->get($assoName);
                $stats['id'] = $node->getId();
                $stats['size'] = $node->getSize();
                $stats['usage'] = $node->getSize();

                try {
                    $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
                    $allFolders = $fm->getAllFolders();
                    if (isset($allFolders[$node->getId()])) {
                        $folderData = $allFolders[$node->getId()];
                        $q = is_object($folderData) ? $folderData->getQuota() : $folderData['quota'];
                        if ($q > 0) $stats['quota'] = $q;
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return $stats;
    }

    public function ensureGlobalGroupsExist(): void
    {
        $groups = ['president', 'tresorier', 'secretaire', 'enseignent', 'admin_iut', 'invite'];
        foreach ($groups as $gid) {
            if (!$this->groupManager->groupExists($gid)) {
                $this->groupManager->createGroup($gid);
            }
        }
    }

    public function updateUserGlobalGroup(string $userId, string $groupName, bool $shouldBeIn): void
    {
        if (!in_array($groupName, ['president', 'tresorier', 'secretaire', 'enseignent', 'admin_iut', 'invite'])) {
            return;
        }

        try {
            $this->ensureGlobalGroupsExist();
            $group = $this->groupManager->get($groupName);
            $user = $this->userManager->get($userId);

            if (!$group || !$user) return;

            $isIn = $group->inGroup($user);

            if ($shouldBeIn && !$isIn) {
                try {
                    $group->addUser($user);
                } catch (\Throwable $e) {
                    $this->log("Ignored Circles error (add $groupName): " . $e->getMessage(), 'warning');
                }
            } elseif (!$shouldBeIn && $isIn) {
                try {
                    $group->removeUser($user);
                } catch (\Throwable $e) {
                    $this->log("Ignored Circles error (remove $groupName): " . $e->getMessage(), 'warning');
                }
            }
        } catch (\Throwable $e) {
            $this->log("Global group error: " . $e->getMessage(), 'error');
        }
    }

    public function ensureAssociationStructure(string $assoName): int
    {
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
                if (method_exists($fm, 'setFolderQuota')) {
                    $quotaBytes = self::QUOTA_ASSO * 1024 * 1024 * 1024;
                    $fm->setFolderQuota($folderId, $quotaBytes);
                }
            }
            try {
                $fm->addApplicableGroup($folderId, $assoName);
                $fm->addApplicableGroup($folderId, 'admin');
                $fm->addApplicableGroup($folderId, 'admin_iut');
                $fm->addApplicableGroup($folderId, 'invite');

                if (method_exists($fm, 'setGroupPermissions')) {
                    $fm->setGroupPermissions($folderId, $assoName, Constants::PERMISSION_ALL);
                    $fm->setGroupPermissions($folderId, 'admin_iut', Constants::PERMISSION_ALL);
                    $fm->setGroupPermissions($folderId, 'invite', Constants::PERMISSION_READ);
                }
            } catch (\Throwable $e) {
            }

            $this->createSubFolders($assoName);
            $this->applyAdminIutAcl($folderId, $assoName);
            return $folderId;
        } catch (\Throwable $e) {
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

            if (method_exists($mm, 'getAllMappings')) {
                $allMappings = $mm->getAllMappings($folderId);
            } elseif (method_exists($mm, 'getMappings')) {
                $allMappings = $mm->getMappings($folderId);
            } else {
                return;
            }

            $mappingAdmin = null;
            $mappingInvite = null;

            foreach ($allMappings as $m) {
                $mid = method_exists($m, 'getId') ? $m->getId() : '';
                $label = method_exists($m, 'getDisplayName') ? $m->getDisplayName() : '';

                if ($mid === 'admin_iut' || $label === 'admin_iut') $mappingAdmin = $m;
                if ($mid === 'invite' || $label === 'invite') $mappingInvite = $m;
            }

            if ($mappingAdmin) $this->applyRulesForMapping($rm, $mappingAdmin, $assoName, 'admin_iut');
            if ($mappingInvite) $this->applyRulesForMapping($rm, $mappingInvite, $assoName, 'invite');
        } catch (\Throwable $e) {
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

            if (!$officiel->nodeExists('Autres')) $officiel->newFolder('Autres');

            if (!$officiel->nodeExists('Papiers officiels de l\'association')) {
                $officiel->newFolder('Papiers officiels de l\'association');
            }
            $papiers = $officiel->get('Papiers officiels de l\'association');
            foreach (['Documents Préfecture', 'Statuts', 'Fiche Objectif'] as $sub) {
                if (!$papiers->nodeExists($sub)) $papiers->newFolder($sub);
            }

            if (!$officiel->nodeExists('Comptes')) $officiel->newFolder('Comptes');
            $comptes = $officiel->get('Comptes');
            foreach (['RIB', 'Relevés de comptes mensuels', 'Notes de frais'] as $sub) {
                if (!$comptes->nodeExists($sub)) $comptes->newFolder($sub);
            }

            if (!$officiel->nodeExists('Rendus')) $officiel->newFolder('Rendus');
            $rendus = $officiel->get('Rendus');
            foreach (['Comptes rendus mensuels', 'Plan de gestion', 'Bilan mi-parcours', 'Rapport final', 'Vidéo collectif'] as $sub) {
                if (!$rendus->nodeExists($sub)) $rendus->newFolder($sub);
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
                    if (method_exists($fm, 'removeFolder')) $fm->removeFolder($id);
                    elseif (method_exists($fm, 'deleteFolder')) $fm->deleteFolder($id);
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function renameFolder(string $oldName, string $newName): void
    {
        try {
            $fm = $this->getService('OCA\GroupFolders\Folder\FolderManager');
            $allFolders = $fm->getAllFolders();
            foreach ($allFolders as $id => $folder) {
                $name = is_string($folder) ? $folder : $folder->mountPoint;
                if ($name === $oldName) {
                    if (method_exists($fm, 'renameFolder')) $fm->renameFolder($id, $newName);
                    elseif (method_exists($fm, 'setFolderName')) $fm->setFolderName($id, $newName);

                    if (!$this->groupManager->groupExists($newName)) $this->groupManager->createGroup($newName);
                    $newGroup = $this->groupManager->get($newName);

                    if ($this->groupManager->groupExists($oldName)) {
                        $oldGroup = $this->groupManager->get($oldName);
                        $users = $oldGroup->getUsers();
                        foreach ($users as $user) {
                            if (!$newGroup->inGroup($user)) {
                                try {
                                    $newGroup->addUser($user);
                                } catch (\Throwable $e) {
                                }
                            }
                        }
                        $fm->addApplicableGroup($id, $newName);
                        if (method_exists($fm, 'setGroupPermissions')) $fm->setGroupPermissions($id, $newName, Constants::PERMISSION_ALL);
                        $fm->removeApplicableGroup($id, $oldName);
                        $oldGroup->delete();
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function addUserToGroup(string $userId, string $groupName): void
    {
        try {
            $group = $this->groupManager->get($groupName);
            $user = $this->userManager->get($userId);
            if ($group && $user && !$group->inGroup($user)) {
                try {
                    $group->addUser($user);
                } catch (\Throwable $e) {
                }
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
                try {
                    $group->removeUser($user);
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function updatePresidentGroupMembership(string $userId, bool $shouldBeInGroup): void
    {
        $this->updateUserGlobalGroup($userId, 'president', $shouldBeInGroup);
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

            if (method_exists($mm, 'getMappingsForUser')) {
                $allMappings = $mm->getMappingsForUser($userObj);
            } else {
                $allMappings = [];
            }

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
            $this->applyAdminIutAcl($folderId, $assoName);
        } catch (\Throwable $e) {
            $this->log("Error ACL: " . $e->getMessage(), 'error');
        }
    }

    private function applyRulesForMapping($rm, $mapping, string $assoName, string $role): void
    {
        try {
            $userFolder = $this->rootFolder->getUserFolder('admin');
            $rootNode = $userFolder->get($assoName);
        } catch (\Exception $e) {
            return;
        }

        $this->setRule($rm, $mapping, $rootNode->getId(), Constants::PERMISSION_READ);

        if ($rootNode->nodeExists('archive')) {
            $archiveId = $rootNode->get('archive')->getId();
            if ($role === 'president' || $role === 'admin_iut') {
                $archivePerms = Constants::PERMISSION_ALL & ~Constants::PERMISSION_DELETE;
            } else {
                $archivePerms = Constants::PERMISSION_READ;
            }
            $this->setRule($rm, $mapping, $archiveId, $archivePerms);
        }

        if ($rootNode->nodeExists('officiel')) {
            $officiel = $rootNode->get('officiel');
            $this->setRule($rm, $mapping, $officiel->getId(), Constants::PERMISSION_READ);

            $isReadOnlyRole = ($role === 'teacher' || $role === 'invite');
            $writePerms = $isReadOnlyRole ? Constants::PERMISSION_READ : Constants::PERMISSION_ALL;

            if ($officiel->nodeExists('Autres')) {
                $this->setRule($rm, $mapping, $officiel->get('Autres')->getId(), $writePerms);
            }

            if ($officiel->nodeExists('Papiers officiels de l\'association')) {
                $papiers = $officiel->get('Papiers officiels de l\'association');
                $this->setRule($rm, $mapping, $papiers->getId(), Constants::PERMISSION_READ);

                foreach (['Documents Préfecture', 'Statuts', 'Fiche Objectif'] as $sub) {
                    if ($papiers->nodeExists($sub)) {
                        $this->setRule($rm, $mapping, $papiers->get($sub)->getId(), $writePerms);
                    }
                }
            }

            if ($officiel->nodeExists('Comptes')) {
                $comptes = $officiel->get('Comptes');
                $this->setRule($rm, $mapping, $comptes->getId(), $writePerms);

                foreach (['RIB', 'Relevés de comptes mensuels', 'Notes de frais'] as $sub) {
                    if ($comptes->nodeExists($sub)) {
                        $this->setRule($rm, $mapping, $comptes->get($sub)->getId(), $writePerms);
                    }
                }
            }

            if ($officiel->nodeExists('Rendus')) {
                $rendus = $officiel->get('Rendus');
                $this->setRule($rm, $mapping, $rendus->getId(), Constants::PERMISSION_READ);

                foreach (['Comptes rendus mensuels', 'Plan de gestion', 'Bilan mi-parcours', 'Rapport final', 'Vidéo collectif'] as $sub) {
                    if ($rendus->nodeExists($sub)) {
                        $this->setRule($rm, $mapping, $rendus->get($sub)->getId(), $writePerms);
                    }
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