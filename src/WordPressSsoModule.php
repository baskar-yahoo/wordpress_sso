<?php

namespace Webtrees\WordPressSso;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WordPressSsoModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ModuleConfigTrait;
    use ViewResponseTrait;

    public const SSO_CALLBACK_ROUTE = '/wordpress-sso/callback';

    // Constants for preference keys
    public const SSO_ENABLED = 'sso_enabled';
    public const SSO_ALLOW_CREATION = 'sso_allow_creation';
    public const SSO_CLIENT_ID = 'sso_client_id';
    public const SSO_CLIENT_SECRET = 'sso_client_secret';
    public const SSO_URL_AUTHORIZE = 'sso_url_authorize';
    public const SSO_URL_ACCESS_TOKEN = 'sso_url_access_token';
    public const SSO_URL_RESOURCE_OWNER = 'sso_url_resource_owner_details';
    public const SSO_URL_LOGOUT = 'sso_url_logout';

    public function title(): string
    {
        return I18N::translate('WordPress SSO');
    }

    public function description(): string
    {
        return I18N::translate('Seamless Single Sign-On with WordPress.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Gemini';
    }

    public function customModuleVersion(): string
    {
        return '1.0.0-dev';
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    public function boot(): void
    {
        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // Register our custom login and logout handlers
        $router = Registry::routeFactory()->routeMap();
        $router->get('WordPressSsoLoginAction', self::SSO_CALLBACK_ROUTE)
            ->handler(WordPressSsoLoginAction::class);

        // Replace the default logout handler with our own
        Registry::container()->set(Logout::class, Registry::container()->get(WordPressSsoLogout::class));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $is_sso_callback = $request->getUri()->getPath() === self::SSO_CALLBACK_ROUTE;

        if (!Auth::check() && !$is_sso_callback && $this->getPreference(self::SSO_ENABLED) === '1') {
            return redirect(route('WordPressSsoLoginAction'));
        }

        return $this->next->handle($request);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $params = [
            'title' => $this->title(),
            'sso_enabled' => (bool) $this->getPreference(self::SSO_ENABLED),
            'sso_allow_creation' => (bool) $this->getPreference(self::SSO_ALLOW_CREATION),
            'sso_client_id' => $this->getPreference(self::SSO_CLIENT_ID),
            'sso_client_secret' => $this->getPreference(self::SSO_CLIENT_SECRET),
            'sso_url_authorize' => $this->getPreference(self::SSO_URL_AUTHORIZE),
            'sso_url_access_token' => $this->getPreference(self::SSO_URL_ACCESS_TOKEN),
            'sso_url_resource_owner_details' => $this->getPreference(self::SSO_URL_RESOURCE_OWNER),
            'sso_url_logout' => $this->getPreference(self::SSO_URL_LOGOUT),
            'callback_url' => route('WordPressSsoLoginAction'),
        ];

        return $this->viewResponse($this->name() . '::settings', $params);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        $this->setPreference(self::SSO_ENABLED, (string) (int)isset($body[self::SSO_ENABLED]));
        $this->setPreference(self::SSO_ALLOW_CREATION, (string) (int)isset($body[self::SSO_ALLOW_CREATION]));
        $this->setPreference(self::SSO_CLIENT_ID, Validator::string($body[self::SSO_CLIENT_ID] ?? null));
        $this->setPreference(self::SSO_CLIENT_SECRET, Validator::string($body[self::SSO_CLIENT_SECRET] ?? null));
        $this->setPreference(self::SSO_URL_AUTHORIZE, Validator::string($body[self::SSO_URL_AUTHORIZE] ?? null));
        $this->setPreference(self::SSO_URL_ACCESS_TOKEN, Validator::string($body[self::SSO_URL_ACCESS_TOKEN] ?? null));
        $this->setPreference(self::SSO_URL_RESOURCE_OWNER, Validator::string($body[self::SSO_URL_RESOURCE_OWNER] ?? null));
        $this->setPreference(self::SSO_URL_LOGOUT, Validator::string($body[self::SSO_URL_LOGOUT] ?? null));

        return redirect($this->getConfigLink());
    }
}
