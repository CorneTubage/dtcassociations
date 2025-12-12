<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
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
        $tmpPath = sys_get_temp_dir() . '/dtc_debug.log';

        if (!@file_put_contents($relativePath, $msg, FILE_APPEND)) {
            @file_put_contents($tmpPath, "FALLBACK TO TMP: $msg", FILE_APPEND);
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

            foreach (['General', 'Tresorerie', 'Archives'] as $dir) {
                if (!$mountPoint->nodeExists($dir)) {
                    $mountPoint->newFolder($dir);
                }
            }
        } catch (\Throwable $e) {
            $this->log("Error creating subfolders: " . $e->getMessage());
        }
    }

    public function applyRolePermissions(int $folderId, string $userId, string $role): void
    {
        $this->log("Applying permissions: User=$userId, Role=$role, FolderID=$folderId");

        $allFolders = $this->folderManager->getAllFolders();
        $folderObject = $allFolders[$folderId];
        $assoName = is_string($folderObject) ? $folderObject : $folderObject->mountPoint;

        // 1. Group Membership
        $group = $this->groupManager->get($assoName);
        $user = $this->userManager->get($userId);

        if ($group && $user) {
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }
        }

        // 2. Enable ACL
        try {
            if (method_exists($this->folderManager, 'setFolderACL')) {
                $this->folderManager->setFolderACL($folderId, true);
                $this->log("ACL mode enabled");
            } elseif (method_exists($this->folderManager, 'setAcl')) {
                $this->folderManager->setAcl($folderId, 1);
            }
        } catch (\Throwable $e) {
            $this->log("ACL Enable Warning: " . $e->getMessage());
        }

        // 3. Apply Rules
        $fullRights = Constants::PERMISSION_ALL;

        try {
            $userObj = $this->userManager->get($userId);
            $allMappings = $this->mappingManager->getMappingsForUser($userObj);

            if (empty($allMappings)) {
                $this->log("Error: No mappings found. User not in group?");
                return;
            }

            // Filtrage Mapping amélioré (DisplayName = Nom du groupe/asso)
            $mapping = null;
            foreach ($allMappings as $m) {
                // On vérifie le nom affiché (DisplayName) ou la clé (Key) qui correspondent souvent au nom du groupe
                $displayName = method_exists($m, 'getDisplayName') ? $m->getDisplayName() : '';
                $key = method_exists($m, 'getKey') ? $m->getKey() : '';

                // On compare avec le nom de l'asso (qui est le nom du groupe)
                if ($displayName === $assoName || $key === $assoName) {
                    $mapping = $m;
                    break;
                }

                // Fallback ID si dispo
                if (method_exists($m, 'getId') && $m->getId() == $folderId) {
                    $mapping = $m;
                    break;
                }
            }

            if (!$mapping) {
                $this->log("Warning: Specific mapping not found. Using fallback.");
                $mapping = reset($allMappings);
            }

            // IDs réels
            $userFolder = $this->rootFolder->getUserFolder('admin');
            /** @var Folder $assoFolder */
            $assoFolder = $userFolder->get($assoName);
            $rootFileId = $assoFolder->getId();

            $this->log("Using Root File ID: $rootFileId");

            // --- A. RACINE ---
            // Suppression ancienne règle
            // IMPORTANT: Le masque est aussi nécessaire pour identifier la règle à supprimer (souvent)
            try {
                $ruleToDelete = new Rule($mapping, $rootFileId, $fullRights, 0);
                $this->ruleManager->deleteRule($ruleToDelete);
            } catch (\Throwable $e) {
            }

            // Ajout règle : CORRECTION DU MASQUE (3e argument)
            // On dit : "J'applique cette règle sur TOUTES les permissions (Mask=ALL)"
            // Et la valeur est "TOUT AUTORISER" (Permissions=ALL)
            $ruleToAdd = new Rule($mapping, $rootFileId, $fullRights, $fullRights);
            $this->ruleManager->saveRule($ruleToAdd);
            $this->log("ACL: Rule saved on Root (Mask: ALL, Perms: ALL)");

            // --- B. SOUS-DOSSIERS (General, Archives) ---
            foreach (['General', 'Archives'] as $subName) {
                if ($assoFolder->nodeExists($subName)) {
                    /** @var Node $subNode */
                    $subNode = $assoFolder->get($subName);
                    $subId = $subNode->getId();

                    try {
                        $del = new Rule($mapping, $subId, $fullRights, 0);
                        $this->ruleManager->deleteRule($del);
                    } catch (\Throwable $e) {
                    }

                    $add = new Rule($mapping, $subId, $fullRights, $fullRights);
                    $this->ruleManager->saveRule($add);
                }
            }

            // --- C. TRESORERIE ---
            if ($assoFolder->nodeExists('Tresorerie')) {
                /** @var Node $tresoNode */
                $tresoNode = $assoFolder->get('Tresorerie');
                $tresoFileId = $tresoNode->getId();

                try {
                    $del = new Rule($mapping, $tresoFileId, $fullRights, 0);
                    $this->ruleManager->deleteRule($del);
                } catch (\Throwable $e) {
                }

                if ($role === 'president' || $role === 'tresorier' || $role === 'admin_iut') {
                    // AUTORISER : Mask=ALL, Perms=ALL
                    $add = new Rule($mapping, $tresoFileId, $fullRights, $fullRights);
                    $this->ruleManager->saveRule($add);
                    $this->log("ACL: Tresorerie ALLOWED");
                } else {
                    // BLOQUER : Mask=ALL, Perms=0 (Aucun droit)
                    $add = new Rule($mapping, $tresoFileId, $fullRights, 0);
                    $this->ruleManager->saveRule($add);
                    $this->log("ACL: Tresorerie BLOCKED");
                }
            }
        } catch (\Throwable $e) {
            $this->log("CRITICAL ERROR: " . $e->getMessage());
        }
    }
}
