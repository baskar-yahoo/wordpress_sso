<?php

namespace Webtrees\WordPressSso\Http;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\User;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webtrees\WordPressSso\Exceptions\ConfigurationException;
use Webtrees\WordPressSso\Exceptions\SecurityException;
use Webtrees\WordPressSso\Exceptions\StateValidationException;
use Webtrees\WordPressSso\Exceptions\TokenExchangeException;
use Webtrees\WordPressSso\Exceptions\UserDataException;
use Webtrees\WordPressSso\Exceptions\UserCreationException;
use Webtrees\WordPressSso\Exceptions\LoginException;
use Webtrees\WordPressSso\Services\DebugLogger;
use Webtrees\WordPressSso\WordPressSsoModule;

/**
 * WordPress SSO Login Action Handler
 * Implements OAuth2 authorization code flow with PKCE, user switch detection, and comprehensive error handling
 */
class WordPressSsoLoginAction implements RequestHandlerInterface
{
    private UserService $user_service;
    private WordPressSsoModule $module;
    private EmailService $email_service;
    private DebugLogger $logger;

    public const WP_USER_ID_PREFERENCE = 'wordpress_user_id';

    public function __construct(
        UserService $user_service,
        WordPressSsoModule $module,
        EmailService $email_service
    ) {
        $this->user_service = $user_service;
        $this->module = $module;
        $this->email_service = $email_service;
        
        // Initialize debug logger
        $debug_enabled = $this->module->getConfig('debugEnabled', '0') === '1';
        $this->logger = new DebugLogger($debug_enabled);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->logRequest('SSO Request Start', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'authenticated' => Auth::check() ? 'Yes (User: ' . Auth::user()->userName() . ')' : 'No',
        ]);

        // Cookie validation - check before any OAuth flow
        if ($_COOKIE === []) {
            $this->logger->log('Cookie validation failed - no cookies present');
            Log::addAuthenticationLog('WordPress SSO: Login failed (no session cookies)');
            FlashMessages::addMessage(
                I18N::translate('You cannot sign in because your browser does not accept cookies.'),
                'danger'
            );
            return redirect(route(HomePage::class));
        }

        try {
            $this->validateConfiguration();
            $provider = $this->createProvider();
        } catch (ConfigurationException $e) {
            Log::addErrorLog('[WordPress SSO] Configuration Error: ' . $e->getMessage());
            FlashMessages::addMessage(
                I18N::translate('WordPress SSO is not configured correctly. Please contact the administrator.'),
                'danger'
            );
            $this->logger->log('Configuration validation failed', ['error' => $e->getMessage()]);
            return redirect(route(HomePage::class));
        }

        $queryParams = $request->getQueryParams();

        // Authorization phase - redirect to WordPress
        if (!isset($queryParams['code'])) {
            try {
                // Save current user ID (or 0 for guest) to session for user switch detection
                Session::put('wordpress_sso_initiating_user', Auth::id());
                $this->logger->log('Saved initiating user to session', [
                    'user_id' => Auth::id(),
                    'username' => Auth::check() ? Auth::user()->userName() : 'Guest'
                ]);

                $authorizationUrl = $provider->getAuthorizationUrl();
                Session::put('oauth2state', $provider->getState());
                
                // Save PKCE code to session (if PKCE is enabled)
                $pkceCode = $provider->getPkceCode();
                if ($pkceCode !== null) {
                    Session::put('oauth2pkceCode', $pkceCode);
                    $this->logger->log('PKCE enabled - code saved to session');
                }
                
                $this->logger->log('Redirecting to authorization URL', ['url' => $authorizationUrl]);
                return redirect($authorizationUrl);
                
            } catch (\Throwable $e) {
                Log::addErrorLog('[WordPress SSO] Authorization initiation failed: ' . $e->getMessage());
                FlashMessages::addMessage(
                    I18N::translate('Failed to start WordPress login. Please try again.'),
                    'danger'
                );
                $this->logger->log('Authorization initiation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return redirect(route(HomePage::class));
            }
        }

        // Callback phase - process the authorization code
        try {
            // User switch detection
            $this->detectUserSwitch();
            
            // State validation (CSRF protection)
            $this->validateState($queryParams);
            
            // Load PKCE code from session (if PKCE was enabled)
            $pkceCode = Session::get('oauth2pkceCode', '');
            if ($pkceCode !== '') {
                $provider->setPkceCode($pkceCode);
                $this->logger->log('PKCE code loaded from session');
            }
            
            // Token exchange
            $accessToken = $this->exchangeCodeForToken($provider, $queryParams['code']);
            
            // User data retrieval
            $userData = $this->getUserData($provider, $accessToken);
            
            // User validation
            $this->validateUserData($userData);
            
            // User matching/creation
            $user = $this->findOrCreateUser($userData);
            
            // Email sync
            $this->syncEmail($user, $userData['user_email']);
            
            // Login
            $this->performLogin($user);
            
            // Clean up session
            Session::forget('oauth2state');
            Session::forget('oauth2pkceCode');
            Session::forget('wordpress_sso_initiating_user');
            
            $this->logger->log('Login successful', [
                'user' => $user->userName(),
                'timestamp' => time(),
            ]);
            
            return redirect(route(HomePage::class));
            
        } catch (SecurityException $e) {
            Log::addErrorLog('[WordPress SSO] Security violation: ' . $e->getMessage());
            FlashMessages::addMessage($e->getMessage(), 'danger');
            $this->logger->log('Security violation', ['error' => $e->getMessage()]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (StateValidationException $e) {
            Log::addErrorLog('[WordPress SSO] State validation failed: ' . $e->getMessage());
            FlashMessages::addMessage(
                I18N::translate('Security validation failed. This may be a CSRF attack. Please try again.'),
                'danger'
            );
            $this->logger->log('State validation failed', ['error' => $e->getMessage()]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (TokenExchangeException $e) {
            Log::addErrorLog('[WordPress SSO] Token exchange failed: ' . $e->getMessage());
            FlashMessages::addMessage(
                I18N::translate('Failed to communicate with WordPress. Please try again.'),
                'danger'
            );
            $this->logger->log('Token exchange failed', [
                'error' => $e->getMessage(),
                'code' => substr($queryParams['code'] ?? '', 0, 8) . '...'
            ]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (UserDataException $e) {
            Log::addErrorLog('[WordPress SSO] Invalid user data: ' . $e->getMessage());
            FlashMessages::addMessage(
                I18N::translate('WordPress did not provide valid user information. Please contact the administrator.'),
                'danger'
            );
            $this->logger->log('User data validation failed', ['error' => $e->getMessage()]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (UserCreationException $e) {
            Log::addErrorLog('[WordPress SSO] User creation failed: ' . $e->getMessage());
            FlashMessages::addMessage($e->getMessage(), 'danger');
            $this->logger->log('User creation failed', ['error' => $e->getMessage()]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (LoginException $e) {
            Log::addErrorLog('[WordPress SSO] Login failed: ' . $e->getMessage());
            FlashMessages::addMessage($e->getMessage(), 'danger');
            $this->logger->log('Login failed', ['error' => $e->getMessage()]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
            
        } catch (\Throwable $e) {
            Log::addErrorLog('[WordPress SSO] Unexpected error: ' . $e->getMessage() . 
                            ' in ' . $e->getFile() . ':' . $e->getLine());
            FlashMessages::addMessage(
                I18N::translate('An unexpected error occurred. Please contact the administrator.'),
                'danger'
            );
            $this->logger->log('Unexpected error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->cleanupSession();
            return redirect(route(HomePage::class));
        }
    }

    /**
     * Validate module configuration
     */
    private function validateConfiguration(): void
    {
        $required = [
            'clientId' => 'Client ID',
            'clientSecret' => 'Client Secret',
            'urlAuthorize' => 'Authorization URL',
            'urlAccessToken' => 'Access Token URL',
            'urlResourceOwner' => 'Resource Owner URL',
        ];

        foreach ($required as $key => $name) {
            if (empty($this->module->getConfig($key))) {
                throw new ConfigurationException("Missing configuration: $name");
            }
        }
    }

    /**
     * Create OAuth2 provider instance
     */
    private function createProvider(): GenericProvider
    {
        $config = [
            'clientId'                => $this->module->getConfig('clientId'),
            'clientSecret'            => $this->module->getConfig('clientSecret'),
            'redirectUri'             => route('WordPressSsoLoginAction'),
            'urlAuthorize'            => $this->module->getConfig('urlAuthorize'),
            'urlAccessToken'          => $this->module->getConfig('urlAccessToken'),
            'urlResourceOwnerDetails' => $this->module->getConfig('urlResourceOwner'),
        ];

        // Add PKCE if enabled
        $pkceMethod = $this->module->getConfig('pkceMethod', '');
        if ($pkceMethod !== '' && $pkceMethod !== 'none') {
            $config['pkceMethod'] = $pkceMethod;
            $this->logger->log('PKCE enabled', ['method' => $pkceMethod]);
        }

        return new GenericProvider($config);
    }

    /**
     * Detect user switch (security check)
     */
    private function detectUserSwitch(): void
    {
        $initiating_user_id = Session::get('wordpress_sso_initiating_user', null);
        $current_user_id = Auth::id();

        if ($initiating_user_id !== null && $initiating_user_id !== $current_user_id) {
            $this->logger->log('User switch detected - security violation', [
                'initiating_user_id' => $initiating_user_id,
                'current_user_id' => $current_user_id,
            ]);
            
            throw new SecurityException(
                I18N::translate('Security violation: The login was initiated by a different user. Please try again.')
            );
        }
    }

    /**
     * Validate OAuth2 state (CSRF protection)
     */
    private function validateState(array $queryParams): void
    {
        if (empty($queryParams['state'])) {
            throw new StateValidationException('State parameter is missing');
        }
        
        if (!Session::has('oauth2state')) {
            throw new StateValidationException('No state found in session');
        }
        
        if ($queryParams['state'] !== Session::get('oauth2state')) {
            throw new StateValidationException('State mismatch - possible CSRF attack');
        }
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(GenericProvider $provider, string $code)
    {
        try {
            $accessToken = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $this->logger->log('Access token received from WordPress');
            return $accessToken;
        } catch (IdentityProviderException $e) {
            throw new TokenExchangeException('Token exchange failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user data from WordPress
     */
    private function getUserData(GenericProvider $provider, $accessToken): array
    {
        try {
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $userData = $resourceOwner->toArray();
            
            $this->logger->log('User data retrieved from WordPress', [
                'wp_user_id' => $userData['ID'] ?? 'missing',
                'username' => $userData['user_login'] ?? 'missing',
                'email' => $userData['user_email'] ?? 'missing',
            ]);
            
            return $userData;
        } catch (\Throwable $e) {
            throw new UserDataException('Failed to retrieve user data: ' . $e->getMessage());
        }
    }

    /**
     * Validate user data from WordPress
     */
    private function validateUserData(array $userData): void
    {
        if (empty($userData['ID'])) {
            throw new UserDataException('WordPress user ID is missing');
        }
        
        if (empty($userData['user_email'])) {
            throw new UserDataException('WordPress user email is missing');
        }
        
        if (empty($userData['user_login'])) {
            throw new UserDataException('WordPress username is missing');
        }
    }

    /**
     * Find or create user
     */
    private function findOrCreateUser(array $userData): User
    {
        $wp_user_id = (string) $userData['ID'];
        $user_email = $userData['user_email'];
        $user_name = $userData['user_login'];

        $user = $this->findUserByWpId($wp_user_id);

        if (!$user) {
            $user = $this->user_service->findByEmail($user_email);
            if ($user) {
                $user->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);
                $this->logger->log('Linked existing user by email', ['user' => $user->userName()]);
            } else {
                if ($this->module->getConfig('allowCreation', '0') === '1') {
                    $user = $this->createUser($user_name, $user_email, $wp_user_id);
                    $this->logger->log('Created new user', ['user' => $user->userName()]);
                } else {
                    throw new UserCreationException(
                        I18N::translate('User not found and automatic account creation is disabled.')
                    );
                }
            }
        } else {
            $this->logger->log('Found existing user by WordPress ID', ['user' => $user->userName()]);
        }

        return $user;
    }

    /**
     * Sync email from WordPress if enabled
     */
    private function syncEmail(User $user, string $wordpress_email): void
    {
        if ($this->module->getConfig('syncEmail', '0') !== '1') {
            return;
        }

        if ($user->email() !== $wordpress_email) {
            $old_email = $user->email();
            $user->setEmail($wordpress_email);
            
            Log::addAuthenticationLog(sprintf(
                'WordPress SSO: Email synchronized for user %s from %s to %s',
                $user->userName(),
                $old_email,
                $wordpress_email
            ));
            
            FlashMessages::addMessage(
                I18N::translate('Your email address has been synchronized with WordPress: %s', $wordpress_email),
                'info'
            );
            
            $this->logger->log('Email synchronized', [
                'user' => $user->userName(),
                'old_email' => $old_email,
                'new_email' => $wordpress_email
            ]);
        }
    }

    /**
     * Perform login
     */
    private function performLogin(User $user): void
    {
        if ($user->getPreference(UserInterface::PREF_IS_EMAIL_VERIFIED) !== '1') {
            throw new LoginException(
                I18N::translate('This account has not been verified. Please check your email for a verification message.')
            );
        }

        if ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
            throw new LoginException(
                I18N::translate('This account has not been approved. Please wait for an administrator to approve it.')
            );
        }

        Auth::login($user);
        Log::addAuthenticationLog('WordPress SSO Login: ' . Auth::user()->userName());
        Auth::user()->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, (string) time());
    }

    /**
     * Find user by WordPress ID
     */
    private function findUserByWpId(string $wp_user_id): ?User
    {
        $user_id = DB::table('user_setting')
            ->where('setting_name', self::WP_USER_ID_PREFERENCE)
            ->where('setting_value', $wp_user_id)
            ->value('user_id');

        return $user_id ? $this->user_service->find($user_id) : null;
    }

    /**
     * Create new user
     */
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

    /**
     * Clean up session data
     */
    private function cleanupSession(): void
    {
        Session::forget('oauth2state');
        Session::forget('oauth2pkceCode');
        Session::forget('wordpress_sso_initiating_user');
    }
}