<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\User;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class WordPressSsoLoginAction implements RequestHandlerInterface
{
    private UserService $user_service;

    public const WP_USER_ID_PREFERENCE = 'wordpress_user_id';

    public function __construct(UserService $user_service)
    {
        $this->user_service = $user_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = new GenericProvider([
            'clientId'                => get_preference('sso_client_id'),
            'clientSecret'            => get_preference('sso_client_secret'),
            'redirectUri'             => route('WordPressSsoLoginAction'),
            'urlAuthorize'            => get_preference('sso_url_authorize'),
            'urlAccessToken'          => get_preference('sso_url_access_token'),
            'urlResourceOwnerDetails' => get_preference('sso_url_resource_owner_details'),
        ]);

        $queryParams = $request->getQueryParams();

        if (!isset($queryParams['code'])) {
            $authorizationUrl = $provider->getAuthorizationUrl();
            Session::put('oauth2state', $provider->getState());
            return redirect($authorizationUrl);
        } else {
            if (empty($queryParams['state']) || (Session::has('oauth2state') && $queryParams['state'] !== Session::get('oauth2state'))) {
                Session::forget('oauth2state');
                throw new \Exception('Invalid OAuth2 state');
            }

            $accessToken = $provider->getAccessToken('authorization_code', ['code' => $queryParams['code']]);
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $userData = $resourceOwner->toArray();

            // --- Start of Secure Provisioning Logic ---

            // 1. Use the stable user ID from WordPress
            $wp_user_id = $userData['ID'];
            $user_email = $userData['user_email'];
            $user_name = $userData['user_login'];

            // 2. Find user by the stable WordPress ID first
            $user = $this->findUserByWpId($wp_user_id);

            if (!$user) {
                // If not found by WordPress ID, try to find by email to link an existing account
                $user = $this->user_service->findByEmail($user_email);
                if ($user) {
                    // Link the account by storing the WordPress ID
                    $user->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);
                } else {
                    // 3. If no user found, create a new one (if enabled)
                    if (get_preference('sso_allow_creation') === '1') {
                        $user = $this->createUser($user_name, $user_email, $wp_user_id);
                    } else {
                        Log::addAuthenticationLog('Login failed (user not found and creation is disabled): ' . $user_name);
                        throw new \Exception('User not found and automatic account creation is disabled.');
                    }
                }
            }

            // 4. Enforce security checks before logging in
            if ($user->getPreference(UserInterface::PREF_IS_EMAIL_VERIFIED) !== '1') {
                Log::addAuthenticationLog('Login failed (not verified by user): ' . $user->userName());
                throw new \Exception('This account has not been verified. Please check your email for a verification message.');
            }
    
            if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
                Log::addAuthenticationLog('Login failed (not approved by admin): ' . $user->userName());
                throw new \Exception('This account has not been approved. Please wait for an administrator to approve it.');
            }

            Auth::login($user);
            Log::addAuthenticationLog('SSO Login: ' . Auth::user()->userName());
            Auth::user()->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, (string) time());

            // --- End of Secure Provisioning Logic ---

            return redirect(route(HomePage::class));
        }
    }

    private function findUserByWpId(string $wp_user_id): ?User
    {
        // This requires iterating through users, as webtrees doesn't have a built-in way to find by preference.
        // For large sites, this could be slow and you might consider a custom database query.
        $users = $this->user_service->all();
        foreach ($users as $user) {
            if ($user->getPreference(self::WP_USER_ID_PREFERENCE) === $wp_user_id) {
                return $user;
            }
        }
        return null;
    }

    private function createUser(string $user_name, string $email, string $wp_user_id): User
    {
        $newUser = $this->user_service->createUser($user_name, $email, md5(random_bytes(32)));
        $newUser->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);
        
        // Set new accounts to require admin approval by default
        $newUser->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '0');
        $newUser->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1'); // Email is trusted from WordPress

        // You might want to set a default role here
        // $newUser->setPreference(UserInterface::PREF_ROLE, 'member');

        // Notify admin about the new user
        // (You would need to implement a notification service)

        return $newUser;
    }
}