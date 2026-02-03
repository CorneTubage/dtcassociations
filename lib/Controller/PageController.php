<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Controller;

use OCA\DTCAssociations\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller
{
	public function __construct(string $appName, IRequest $request)
	{
		parent::__construct($appName, $request);
	}


	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse
	{
		return new TemplateResponse('dtcassociations', 'index');
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function detail(string $id): TemplateResponse
	{
		return new TemplateResponse('dtcassociations', 'index');
	}
}
