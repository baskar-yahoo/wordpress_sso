<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\RequestHandlers\Logout;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WordPressSsoLogout extends Logout
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // First, log the user out of webtrees
        parent::handle($request);

        // Then, redirect to the WordPress logout URL
        $wordPressLogoutUrl = get_preference('sso_url_logout');

        if (!empty($wordPressLogoutUrl)) {
            // Add a redirect parameter so WordPress can send the user back to webtrees
            $returnUrl = route(HomePage::class);
            $wordPressLogoutUrl .= (parse_url($wordPressLogoutUrl, PHP_URL_QUERY) ? '&' : '?') . 'redirect_to=' . urlencode($returnUrl);

            return redirect($wordPressLogoutUrl);
        }

        return redirect(route(HomePage::class));
    }
}
