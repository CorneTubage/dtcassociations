<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Service;

use OCA\DTCAssociations\Db\Association;
use OCA\DTCAssociations\Db\AssociationMapper;
use OCA\DTCAssociations\Db\AssociationMember;
use OCA\DTCAssociations\Db\AssociationMemberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Exception;

class AssociationService
{

    private AssociationMapper $associationMapper;
    private AssociationMemberMapper $memberMapper;
    private GroupFolderService $gfService;

    public function __construct(
        AssociationMapper $associationMapper,
        AssociationMemberMapper $memberMapper,
        GroupFolderService $gfService
    ) {
        $this->associationMapper = $associationMapper;
        $this->memberMapper = $memberMapper;
        $this->gfService = $gfService;
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

            // 1. Mise à jour du nom en base
            $association->setName($name);
            $updated = $this->associationMapper->update($association);

            // 2. Renommage du dossier GroupFolder
            if ($oldName !== $name) {
                try {
                    $this->gfService->renameFolder($oldName, $name);
                } catch (\Throwable $e) {
                    // Log mais continue (non bloquant)
                }
            }

            return $updated;
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }
    }

    public function getAllAssociations(): array
    {
        return $this->associationMapper->findAll();
    }

    public function deleteAssociation(int $id): void
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($id);

            // Suppression du dossier et groupe
            try {
                $this->gfService->deleteStructure($association->getName());
            } catch (\Throwable $e) {
                // Log mais continue
            }

            $this->associationMapper->delete($association);
        } catch (DoesNotExistException $e) {
            throw new Exception("Association introuvable.");
        }
    }

    // --- MEMBRES ---

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

        try {
            $folderId = $this->gfService->ensureAssociationStructure($assoName);
            $this->gfService->applyRolePermissions($folderId, $userId, $role);
        } catch (\Throwable $e) {
        }

        try {
            $member = $this->memberMapper->getMember($userId, $code);
            $member->setRole($role);
            return $this->memberMapper->update($member);
        } catch (DoesNotExistException $e) {
            $member = new AssociationMember();
            $member->setUserId($userId);
            $member->setGroupId($code);
            $member->setRole($role);
            return $this->memberMapper->insert($member);
        }
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
}
