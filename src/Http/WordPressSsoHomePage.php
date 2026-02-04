<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webtrees\WordPressSso\WordPressSsoModule;

class WordPressSsoHomePage extends HomePage
{
    private WordPressSsoModule $module;

    public function __construct(TreeService $tree_service, WordPressSsoModule $module)
    {
        parent::__construct($tree_service);
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Check if user has a pending approval (prevent SSO redirect loop)
        if (Session::has('sso_approval_pending')) {
            // Clear the flag after showing the message once
            Session::forget('sso_approval_pending');
            Session::forget('sso_pending_user_email');
            
            // Let the normal HomePage show the pending approval message
            return parent::handle($request);
        }
        
        // If not logged in and SSO is enabled, redirect to SSO login
        if (!Auth::check() && $this->module->getConfig('enabled') === '1') {
            // Check if we are already on the callback or login route to avoid infinite loops
            // (Though HomePage handler shouldn't be called for those routes anyway)
            
            return redirect(route(WordPressSsoLoginAction::class));
        }

        return parent::handle($request);
    }
}
