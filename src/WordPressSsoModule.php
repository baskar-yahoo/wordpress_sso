<?php

namespace Webtrees\WordPressSso;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;
use Fisharebest\Webtrees\Http\Middleware\SessionMiddleware;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webtrees\WordPressSso\Http\WordPressSsoLoginAction;
use Webtrees\WordPressSso\Http\WordPressSsoLogout;

class WordPressSsoModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;

    public const SSO_CALLBACK_ROUTE = '/wordpress-sso/callback';

    public function boot(): void
    {
        // Register our custom login and logout handlers
        $router = Registry::routeFactory()->routeMap();
        $router->get('WordPressSsoLoginAction', self::SSO_CALLBACK_ROUTE)
            ->handler(WordPressSsoLoginAction::class);

        // Replace the default logout handler with our own
        Registry::container()->set(Logout::class, Registry::container()->get(WordPressSsoLogout::class));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // This is the core of the seamless login.
        // If the user is not logged in, and not already on the callback route,
        // and the seamless login is enabled, start the login process.

        $is_sso_callback = $request->getUri()->getPath() === self::SSO_CALLBACK_ROUTE;

        if (!Auth::check() && !$is_sso_callback && $this->getPreference('sso_enabled') === '1') {
            // The user is not logged in. Start the SSO process.
            // We redirect to our own login action handler, which will then redirect to WordPress.
            return redirect(route('WordPressSsoLoginAction'));
        }

        // If the user is logged in, or SSO is disabled, continue with the normal request.
        return $this->next->handle($request);
    }

    // ... (title, description, and other standard module methods)
}
