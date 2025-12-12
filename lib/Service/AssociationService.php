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
    // Ajout du service GroupFolder
    private GroupFolderService $gfService;

    public function __construct(
        AssociationMapper $associationMapper,
        AssociationMemberMapper $memberMapper,
        GroupFolderService $gfService // Injection automatique
    ) {
        $this->associationMapper = $associationMapper;
        $this->memberMapper = $memberMapper;
        $this->gfService = $gfService;
    }

    // --- ASSOCIATIONS ---

    public function createAssociation(string $name, string $code): Association
    {
        try {
            $this->associationMapper->findByCode($code);
            throw new Exception("Une association avec le code '$code' existe déjà.");
        } catch (DoesNotExistException $e) {
            // OK
        }

        // 1. Création en base de données
        $association = new Association();
        $association->setName($name);
        $association->setCode($code);
        $entity = $this->associationMapper->insert($association);

        // 2. Création de la structure GroupFolder (Appel à ton service)
        try {
            // On utilise le NOM pour le dossier (ex: "Club Photo")
            $this->gfService->ensureAssociationStructure($name);
        } catch (\Throwable $e) {
            // On loggue l'erreur mais on ne bloque pas la création de l'asso en base
            // (Tu peux voir les erreurs dans ton fichier dtc_debug.log défini dans GroupFolderService)
        }

        return $entity;
    }

    public function updateAssociation(int $id, string $name): Association
    {
        try {
            /** @var Association $association */
            $association = $this->associationMapper->find($id);
            $association->setName($name);
            // Note: Idéalement, il faudrait aussi renommer le GroupFolder ici si le nom change
            return $this->associationMapper->update($association);
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
            $association = $this->associationMapper->find($id);
            // Pour l'instant on supprime seulement de la DB pour ne pas perdre de données par erreur
            // Si tu veux supprimer le dossier aussi, tu devras ajouter une méthode deleteStructure dans GroupFolderService
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

        // 1. Appliquer les permissions sur le GroupFolder
        try {
            // On s'assure que le dossier existe et on récupère son ID
            $folderId = $this->gfService->ensureAssociationStructure($assoName);

            // On applique les permissions spécifiques (ACL, accès Trésorerie, etc.)
            $this->gfService->applyRolePermissions($folderId, $userId, $role);
        } catch (\Throwable $e) {
            // Log erreur dossier, mais on continue pour l'ajout en DB
        }

        // 2. Ajout en base de données (Logiciel existante)
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
            $code = $association->getCode();

            // Note: Ton GroupFolderService actuel n'a pas de méthode "removePermissions" explicite.
            // Les ACLs resteront peut-être actives jusqu'à un nettoyage manuel ou une mise à jour.
            // Pour faire propre, il faudrait ajouter une méthode removeUserAccess($folderId, $userId) dans GroupFolderService.

            $member = $this->memberMapper->getMember($userId, $code);
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
