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

    public function hasGlobalAccess(string $userId): bool
    {
        return $this->groupManager->isAdmin($userId) ||
            $this->groupManager->isInGroup($userId, 'admin_iut');
    }

    public function isFullAdmin(string $userId): bool
    {
        return $this->groupManager->isAdmin($userId);
    }

    public function createAssociation(string $name, string $code): Association
    {
        if (preg_match('/[^\p{L}0-9 _-]/u', $name)) {
            throw new Exception("Le nom de l'association contient des caractères interdits.");
        }

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
        if (preg_match('/[^\p{L}0-9 _-]/u', $name)) {
            throw new Exception("Le nom de l'association contient des caractères interdits.");
        }

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

    public function deleteAssociation(int $id, string $userId): void
    {
        if (!$this->isFullAdmin($userId)) {
            throw new Exception("Droit refusé : seuls les administrateurs peuvent supprimer une association.");
        }

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

    public function addMember(int $associationId, string $userId, string $role, string $actorId = ''): AssociationMember
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($associationId);
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }

        $code = $association->getCode();
        $assoName = $association->getName();

        if ($role === 'invite') {
            if ($actorId !== '' && !$this->hasGlobalAccess($actorId)) {
                throw new Exception("Seuls les administrateurs peuvent ajouter des invités.");
            }
        }

        if ($actorId !== '' && $actorId === $userId) {
            try {
                $currentMember = $this->memberMapper->getMember($userId, $code);
                $currentRole = $currentMember->getRole();

                if ($currentRole === 'president' && $role !== 'president') {
                    throw new Exception("Les présidents ne peuvent pas modifier leur propre rôle.");
                }

                if ($currentRole === 'admin_iut' && $role !== 'admin_iut') {
                    throw new Exception("Les administrateurs IUT ne peuvent pas modifier leur propre rôle.");
                }
            } catch (DoesNotExistException $e) {
            }
        }

        if ($role === 'president') {
            $userMemberships = $this->memberMapper->getUserAssociations($userId);
            foreach ($userMemberships as $m) {
                if ($m->getRole() === 'president' && $m->getGroupId() !== $code) {
                    throw new Exception("Cet utilisateur est déjà président d'une autre association.");
                }
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

            $rolesToGroups = [
                'president' => 'president',
                'treasurer' => 'tresorier',
                'secretary' => 'secretaire',
                'teacher' => 'enseignent',
                'admin_iut' => 'admin_iut',
                'invite' => 'invite'
            ];

            $status = [
                'president' => false,
                'treasurer' => false,
                'secretary' => false,
                'teacher' => false,
                'admin_iut' => false,
                'invite' => false
            ];

            foreach ($memberships as $membership) {
                $role = $membership->getRole();
                if (isset($status[$role])) {
                    $status[$role] = true;
                }
            }

            foreach ($rolesToGroups as $role => $groupName) {
                $this->gfService->updateUserGlobalGroup($userId, $groupName, $status[$role]);
            }
        } catch (\Throwable $e) {
        }
    }

    public function getStats(string $assoName): array
    {
        return $this->gfService->getFolderStats($assoName);
    }
}
