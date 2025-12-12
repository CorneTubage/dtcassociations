<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Controller;

use OCA\DTCAssociations\Service\AssociationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ApiController extends Controller
{

	private AssociationService $service;

	public function __construct(
		string $appName,
		IRequest $request,
		AssociationService $service
	) {
		parent::__construct($appName, $request);
		$this->service = $service;
	}

	/** @NoAdminRequired */
	public function getAssociations(): DataResponse
	{
		try {
			$associations = $this->service->getAllAssociations();
			$data = array_map(function ($assoc) {
				return $assoc->jsonSerialize();
			}, $associations);
			return new DataResponse($data);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/** @NoAdminRequired */
	public function createAssociation(string $name, string $code): DataResponse
	{
		try {
			$association = $this->service->createAssociation($name, $code);
			return new DataResponse($association->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** @NoAdminRequired */
	public function updateAssociation(int $id, string $name): DataResponse
	{
		try {
			$association = $this->service->updateAssociation($id, $name);
			return new DataResponse($association->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/** @NoAdminRequired */
	public function deleteAssociation(int $id): DataResponse
	{
		try {
			$this->service->deleteAssociation($id);
			return new DataResponse(['status' => 'success']);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

    // --- MEMBRES ---

	/** @NoAdminRequired */
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

	/** @NoAdminRequired */
	public function addMember(int $id, string $userId, string $role): DataResponse
	{
		try {
			$member = $this->service->addMember($id, $userId, $role);
			return new DataResponse($member->jsonSerialize());
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** @NoAdminRequired */
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
