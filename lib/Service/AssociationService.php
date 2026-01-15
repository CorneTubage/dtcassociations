<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCA\DTCAssociations\Db\Association;
use OCA\DTCAssociations\Db\AssociationMapper;
use OCA\DTCAssociations\Db\AssociationMember;
use OCA\DTCAssociations\Db\AssociationMemberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use Exception;

class AssociationService
{
    private AssociationMapper $associationMapper;
    private AssociationMemberMapper $memberMapper;
    private GroupFolderService $gfService;
    private IGroupManager $groupManager;

    public function __construct(
        AssociationMapper $associationMapper,
        AssociationMemberMapper $memberMapper,
        GroupFolderService $gfService,
        IGroupManager $groupManager
    ) {
        $this->associationMapper = $associationMapper;
        $this->memberMapper = $memberMapper;
        $this->gfService = $gfService;
        $this->groupManager = $groupManager;
    }

    /**
     * Vérifie si l'utilisateur a un accès global (Admin ou Admin IUT)
     */
    private function hasGlobalAccess(string $userId): bool
    {
        return $this->groupManager->isAdmin($userId) ||
            $this->groupManager->isInGroup($userId, 'admin_iut');
    }

    public function createAssociation(string $name, string $code): Association
    {
        try {
            $this->associationMapper->findByCode($code);
            throw new Exception("Une association avec le code '$code' existe déjà.");
        } catch (DoesNotExistException $e) {
        }

        $association = new Association();
        $association->setName($name);
        $association->setCode($code);
        $entity = $this->associationMapper->insert($association);

        try {
            $this->gfService->ensureAssociationStructure($name);
        } catch (\Throwable $e) {
        }

        return $entity;
    }

    public function updateAssociation(int $id, string $name): Association
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($id);
            $oldName = $association->getName();

            $association->setName($name);
            $updated = $this->associationMapper->update($association);

            if ($oldName !== $name) {
                try {
                    $this->gfService->renameFolder($oldName, $name);
                } catch (\Throwable $e) {
                }
            }

            return $updated;
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }
    }

    /**
     * Récupère les associations visibles pour l'utilisateur
     */
    public function getAllAssociations(string $userId): array
    {
        if ($this->hasGlobalAccess($userId)) {
            return $this->associationMapper->findAll();
        }
        $memberships = $this->memberMapper->getUserAssociations($userId);

        if (empty($memberships)) {
            return [];
        }

        $presidentCodes = [];
        foreach ($memberships as $m) {
            if ($m->getRole() === 'president') {
                $presidentCodes[] = $m->getGroupId();
            }
        }

        if (empty($presidentCodes)) {
            return [];
        }

        return $this->associationMapper->findByCodes($presidentCodes);
    }

    public function deleteAssociation(int $id): void
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($id);

            $members = $this->memberMapper->getAssociationMembers($association->getCode());

            try {
                $this->gfService->deleteStructure($association->getName());
            } catch (\Throwable $e) {
            }

            $this->memberMapper->deleteByGroup($association->getCode());
            $this->associationMapper->delete($association);

            foreach ($members as $m) {
                if ($m->getRole() === 'president') {
                    $this->syncPresidentGroup($m->getUserId());
                }
            }
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }
    }

    private function syncPresidentGroup(string $userId): void
    {
        try {
            $memberships = $this->memberMapper->getUserAssociations($userId);
            $isPresidentSomewhere = false;
            foreach ($memberships as $membership) {
                if ($membership->getRole() === 'president') {
                    $isPresidentSomewhere = true;
                    break;
                }
            }
            $this->gfService->updatePresidentGroupMembership($userId, $isPresidentSomewhere);
        } catch (\Throwable $e) {
        }
    }

    public function addMember(int $associationId, string $userId, string $role): AssociationMember
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($associationId);
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }

        $code = $association->getCode();
        $assoName = $association->getName();

        if ($role === 'president') {
            $currentMembers = $this->memberMapper->getAssociationMembers($code);
            $presidentCount = 0;
            $isAlreadyPresident = false;
            foreach ($currentMembers as $m) {
                if ($m->getRole() === 'president') {
                    $presidentCount++;
                    if ($m->getUserId() === $userId) $isAlreadyPresident = true;
                }
            }
            if ($presidentCount >= 2 && !$isAlreadyPresident) {
                throw new Exception("Cette association a déjà 2 présidents.");
            }
        }

        try {
            $folderId = $this->gfService->ensureAssociationStructure($assoName);
            $this->gfService->applyRolePermissions($folderId, $userId, $role);
        } catch (\Throwable $e) {
        }

        $result = null;
        try {
            $member = $this->memberMapper->getMember($userId, $code);
            $member->setRole($role);
            $result = $this->memberMapper->update($member);
        } catch (DoesNotExistException $e) {
            $member = new AssociationMember();
            $member->setUserId($userId);
            $member->setGroupId($code);
            $member->setRole($role);
            $result = $this->memberMapper->insert($member);
        }

        $this->syncPresidentGroup($userId);
        $this->syncGlobalGroups($userId);

        return $result;
    }

    public function removeMember(int $associationId, string $userId): void
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($associationId);
            $assoName = $association->getName();

            try {
                $this->gfService->removeUserFromGroup($userId, $assoName);
            } catch (\Throwable $e) {
            }

            $member = $this->memberMapper->getMember($userId, $association->getCode());
            $this->memberMapper->delete($member);

            $this->syncPresidentGroup($userId);
            $this->syncGlobalGroups($userId);
        } catch (DoesNotExistException $e) {
            throw new Exception("Membre ou association introuvable.");
        }
    }

    public function getMembers(int $associationId): array
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($associationId);
            return $this->memberMapper->getAssociationMembers($association->getCode());
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }
    }

    private function syncGlobalGroups(string $userId): void
    {
        try {
            $memberships = $this->memberMapper->getUserAssociations($userId);
            $isPresidentSomewhere = false;
            $isAdminIutSomewhere = false;

            foreach ($memberships as $membership) {
                if ($membership->getRole() === 'president') $isPresidentSomewhere = true;
                if ($membership->getRole() === 'admin_iut') $isAdminIutSomewhere = true;
            }

            $this->gfService->updateUserGlobalGroup($userId, 'president', $isPresidentSomewhere);
            $this->gfService->updateUserGlobalGroup($userId, 'admin_iut', $isAdminIutSomewhere);
        } catch (\Throwable $e) {
        }
    }
}
