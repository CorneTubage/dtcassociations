<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Constants;
use Psr\Log\LoggerInterface; // CORRECTION : Utilisation du standard PSR-3
use OCP\App\IAppManager;

/**
 * Service pour gérer les Dossiers de Groupe de manière sécurisée.
 */
class GroupFolderService
{
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IRootFolder $rootFolder;
    private LoggerInterface $logger; // Typage mis à jour
    private IAppManager $appManager;

    private $folderManager = null;
    private $ruleManager = null;
    private $mappingManager = null;

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        IRootFolder $rootFolder,
        LoggerInterface $logger, // Injection mise à jour
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
        // Adaptation pour PSR-3
        // On s'assure que le niveau existe (info, error, debug...)
        if (!method_exists($this->logger, $level)) {
            $level = 'info';
        }
        $this->logger->$level("[DTC] " . $message, ['app' => 'dtcassociations']);
    }

    /**
     * Helper pour récupérer les services GroupFolders dynamiquement
     */
    private function getService(string $className)
    {
        if (!$this->appManager->isEnabledForUser('groupfolders')) {
            $this->log("App GroupFolders non active", 'error');
            throw new \Exception("GroupFolders missing");
        }
        return \OC::$server->get($className);
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
            }

            try {
                $fm->addApplicableGroup($folderId, $assoName);
                if (method_exists($fm, 'setGroupPermissions')) {
                    $fm->setGroupPermissions($folderId, $assoName, Constants::PERMISSION_ALL);
                }
                $fm->addApplicableGroup($folderId, 'admin');
            } catch (\Throwable $e) {
            }

            $this->createSubFolders($assoName);
            return $folderId;
        } catch (\Throwable $e) {
            $this->log("Error ensures structure: " . $e->getMessage(), 'error');
            return -1;
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
            foreach (['General', 'Tresorie'] as $sub) {
                if (!$officiel->nodeExists($sub)) $officiel->newFolder($sub);
            }
        } catch (\Throwable $e) {
            // Ignorer silencieusement si dossier pas prêt
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
            $this->log("Renaming folder from '$oldName' to '$newName'");

            foreach ($allFolders as $id => $folder) {
                $name = is_string($folder) ? $folder : $folder->mountPoint;
                if ($name === $oldName) {
                    if (method_exists($fm, 'renameFolder')) {
                        $fm->renameFolder($id, $newName);
                    } elseif (method_exists($fm, 'setFolderName')) {
                        $fm->setFolderName($id, $newName);
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

            $userFolder = $this->rootFolder->getUserFolder('admin');
            $rootNode = $userFolder->get($assoName);

            $this->setRule($rm, $mapping, $rootNode->getId(), Constants::PERMISSION_ALL);

            if ($rootNode->nodeExists('archive')) {
                $this->setRule($rm, $mapping, $rootNode->get('archive')->getId(), Constants::PERMISSION_READ);
            }

            if ($rootNode->nodeExists('officiel')) {
                $officiel = $rootNode->get('officiel');
                $this->setRule($rm, $mapping, $officiel->getId(), Constants::PERMISSION_ALL);

                if ($officiel->nodeExists('General')) {
                    $this->setRule($rm, $mapping, $officiel->get('General')->getId(), Constants::PERMISSION_ALL);
                }

                if ($officiel->nodeExists('Tresorie')) {
                    $treso = $officiel->get('Tresorie');
                    if ($role === 'president' || $role === 'treasurer' || $role === 'tresorier') {
                        $this->setRule($rm, $mapping, $treso->getId(), Constants::PERMISSION_ALL);
                    } else {
                        $this->setRule($rm, $mapping, $treso->getId(), 0);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->log("Error ACL: " . $e->getMessage(), 'error');
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
