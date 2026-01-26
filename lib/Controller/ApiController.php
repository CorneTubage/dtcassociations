<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Controller;

use OCA\DTCAssociations\Service\AssociationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class ApiController extends Controller
{

	private AssociationService $service;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		AssociationService $service,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->userSession = $userSession;
	}

	private function getCurrentUserId(): string
	{
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : '';
	}

	#[NoAdminRequired]
	public function getUserPermissions(): DataResponse
	{
		$userId = $this->getCurrentUserId();
		return new DataResponse([
			'canManage' => $this->service->hasGlobalAccess($userId),
			'canDelete' => $this->service->isFullAdmin($userId)
		]);
	}

	#[NoAdminRequired]
    #[NoCSRFRequired]
    public function getAssociations(): DataResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $associations = $this->service->getAllAssociations($userId);
            
            $data = array_map(function ($assoc) {
                $item = $assoc->jsonSerialize();
                
                // Récupération des stats dynamiques
                $stats = $this->service->getStats($assoc->getName());
                
                $item['usage'] = $stats['usage']; // Octets utilisés
                $item['quota'] = $stats['quota']; // Octets max (-3 si illimité)
                
                return $item;
            }, $associations);
            
            return new DataResponse($data);
        } catch (\Exception $e) {
            return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAssociationNames(): DataResponse
	{
		try {
			$userId = $this->getCurrentUserId();
			$associations = $this->service->getAllAssociations($userId);
			$data = array_map(function ($assoc) {
				return ['id' => $assoc->getId(), 'name' => $assoc->getName()];
			}, $associations);
			return new DataResponse($data);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAssociationsList(): DataResponse
	{
		return $this->getAssociations();
	}

	#[NoAdminRequired]
	public function createAssociation(string $name, string $code): DataResponse
	{
		try {
			$association = $this->service->createAssociation($name, $code);
			return new DataResponse($association->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function updateAssociation(int $id, string $name): DataResponse
	{
		try {
			$association = $this->service->updateAssociation($id, $name);
			return new DataResponse($association->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function deleteAssociation(int $id): DataResponse
	{
		try {
			$userId = $this->getCurrentUserId();
			$this->service->deleteAssociation($id, $userId);
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMembers(int $id): DataResponse
	{
		try {
			$members = $this->service->getMembers($id);
			$data = array_map(function ($m) {
				return $m->jsonSerialize();
			}, $members);
			return new DataResponse($data);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function addMember(int $id, string $userId, string $role): DataResponse
	{
		try {
			$actorId = $this->getCurrentUserId();
			$member = $this->service->addMember($id, $userId, $role, $actorId);
			return new DataResponse($member->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function removeMember(int $id, string $userId): DataResponse
	{
		try {
			$this->service->removeMember($id, $userId);
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
