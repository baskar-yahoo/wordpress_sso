<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webtrees\WordPressSso\WordPressSsoModule;


class WordPressSsoLogout extends Logout
{
    private WordPressSsoModule $module;

    public function __construct(WordPressSsoModule $module)
    {
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // First, log the user out of webtrees
        parent::handle($request);

        // Then, redirect to the WordPress logout URL
        // We use a local bridge script (sso_logout.php) located in the module folder
        // to load WP Core and generate a nonce. This avoids the "Do you really want to log out?" prompt.
        
        return redirect('modules_v4/wordpress_sso/sso_logout.php');
    }
}
