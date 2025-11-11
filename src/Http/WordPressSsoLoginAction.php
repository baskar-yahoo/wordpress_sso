<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\User;
use Illuminate\Database\Connection;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


class WordPressSsoLoginAction implements RequestHandlerInterface
{
    private UserService $user_service;
    private WordPressSsoModule $module;
    private Connection $database;
    private EmailService $email_service;

    public const WP_USER_ID_PREFERENCE = 'wordpress_user_id';

    public function __construct(UserService $user_service, WordPressSsoModule $module, Connection $database, EmailService $email_service)
    {
        $this->user_service = $user_service;
        $this->module = $module;
        $this->database = $database;
        $this->email_service = $email_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $provider = new GenericProvider([
                'clientId'                => $this->module->getPreference(WordPressSsoModule::SSO_CLIENT_ID),
                'clientSecret'            => $this->module->getPreference(WordPressSsoModule::SSO_CLIENT_SECRET),
                'redirectUri'             => route('WordPressSsoLoginAction'),
                'urlAuthorize'            => $this->module->getPreference(WordPressSsoModule::SSO_URL_AUTHORIZE),
                'urlAccessToken'          => $this->module->getPreference(WordPressSsoModule::SSO_URL_ACCESS_TOKEN),
                'urlResourceOwnerDetails' => $this->module->getPreference(WordPressSsoModule::SSO_URL_RESOURCE_OWNER),
            ]);
        } catch (\Throwable $e) {
            Log::addErrorLog('[WordPress SSO] Configuration Error: ' . $e->getMessage());
            FlashMessages::addMessage(I18N::translate('The authentication provider is not configured correctly. Please contact the site administrator.'), 'danger');
            return redirect(route(HomePage::class));
        }

        $queryParams = $request->getQueryParams();

        if (!isset($queryParams['code'])) {
            $authorizationUrl = $provider->getAuthorizationUrl();
            Session::put('oauth2state', $provider->getState());
            return redirect($authorizationUrl);
        } 

        try {
            if (empty($queryParams['state']) || !Session::has('oauth2state') || $queryParams['state'] !== Session::get('oauth2state')) {
                throw new \Exception('Invalid OAuth2 state. Possible CSRF attack.');
            }
            Session::forget('oauth2state');

            $accessToken = $provider->getAccessToken('authorization_code', ['code' => $queryParams['code']]);
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $userData = $resourceOwner->toArray();

            if (empty($userData['ID']) || empty($userData['user_email']) || empty($userData['user_login'])) {
                throw new \Exception('Required user data (ID, email, login) was not provided by the authentication server.');
            }

            $wp_user_id = (string) $userData['ID'];
            $user_email = $userData['user_email'];
            $user_name = $userData['user_login'];

            $user = $this->findUserByWpId($wp_user_id);

            if (!$user) {
                $user = $this->user_service->findByEmail($user_email);
                if ($user) {
                    $user->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);
                } else {
                    if ($this->module->getPreference(WordPressSsoModule::SSO_ALLOW_CREATION) === '1') {
                        $user = $this->createUser($user_name, $user_email, $wp_user_id);
                    } else {
                        throw new \Exception(I18N::translate('User not found and automatic account creation is disabled.'));
                    }
                }
            }

            if ($user->getPreference(UserInterface::PREF_IS_EMAIL_VERIFIED) !== '1') {
                throw new \Exception(I18N::translate('This account has not been verified. Please check your email for a verification message.'));
            }
    
            if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
                throw new \Exception(I18N::translate('This account has not been approved. Please wait for an administrator to approve it.'));
            }

            Auth::login($user);
            Log::addAuthenticationLog('SSO Login: ' . Auth::user()->userName());
            Auth::user()->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, (string) time());

            return redirect(route(HomePage::class));

        } catch (IdentityProviderException $e) {
            Log::addErrorLog('[WordPress SSO] IdentityProviderException: ' . $e->getMessage() . ' | Response: ' . $e->getResponseBody());
            FlashMessages::addMessage(I18N::translate('There was a problem communicating with the authentication server. Please try again later.'), 'danger');
            return redirect(route(HomePage::class));
        } catch (\Throwable $e) {
            Log::addErrorLog('[WordPress SSO] Unhandled Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            FlashMessages::addMessage(I18N::translate('An unexpected error occurred during login. Please contact the site administrator.'), 'danger');
            return redirect(route(HomePage::class));
        }
    }

    private function findUserByWpId(string $wp_user_id): ?User
    {
        $user_id = $this->database->table('user_setting')
            ->where('setting_name', self::WP_USER_ID_PREFERENCE)
            ->where('setting_value', $wp_user_id)
            ->value('user_id');

        return $user_id ? $this->user_service->find($user_id) : null;
    }

    private function createUser(string $user_name, string $email, string $wp_user_id): User
    {
        $newUser = $this->user_service->createUser($user_name, $email, md5(random_bytes(32)));
        $newUser->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);
        
        $newUser->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '0');
        $newUser->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');

        // Notify administrators of the new user account.
        $subject = I18N::translate('New user registration requires approval');
        $body = I18N::translate('A new user has registered via WordPress SSO and requires your approval.') . "\n\n"
            . I18N::translate('Username') . ': ' . $newUser->userName() . "\n"
            . I18N::translate('Email') . ': ' . $newUser->email() . "\n\n"
            . I18N::translate('You can approve this user in the webtrees control panel under \'Users\'.');

        foreach ($this->user_service->getAdmins() as $admin) {
            $this->email_service->send($admin, $subject, $body);
        }

        return $newUser;
    }
}