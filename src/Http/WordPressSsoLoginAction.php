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
use Fisharebest\Webtrees\Services\MessageService;
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
    private MessageService $message_service;
    private DebugLogger $logger;

    public const WP_USER_ID_PREFERENCE = 'wordpress_user_id';

    public function __construct(
        UserService $user_service,
        WordPressSsoModule $module,
        EmailService $email_service,
        MessageService $message_service
    ) {
        $this->user_service = $user_service;
        $this->module = $module;
        $this->email_service = $email_service;
        $this->message_service = $message_service;

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
            // Check if user is already logged in - prevent re-processing
            if (Auth::check()) {
                $this->logger->log('User already logged in - skipping OAuth processing', [
                    'user' => Auth::user()->userName()
                ]);
                
                // Clean up OAuth session
                Session::forget('oauth2state');
                Session::forget('oauth2pkceCode');
                Session::forget('wordpress_sso_initiating_user');
                
                return redirect(route(HomePage::class));
            }

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

            // Always login WordPress users (authentication)
            // Webtrees will handle authorization (what they can access) based on approval status
            Auth::login($user);
            Log::addAuthenticationLog('WordPress SSO Login: ' . $user->userName());
            $user->setPreference(UserInterface::PREF_TIMESTAMP_ACTIVE, (string) time());

            // Clean up OAuth session data
            Session::forget('oauth2state');
            Session::forget('oauth2pkceCode');
            Session::forget('wordpress_sso_initiating_user');

            // Inform user of their account status if not fully approved
            if ($user->getPreference(UserInterface::PREF_IS_EMAIL_VERIFIED) !== '1') {
                FlashMessages::addMessage(
                    I18N::translate('This account has not been verified. Please check your email for a verification message.'),
                    'warning'
                );
                
                $this->logger->log('User logged in but email not verified', [
                    'user' => $user->userName(),
                    'email' => $user->email()
                ]);
            } elseif ($user->getPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED) !== '1') {
                FlashMessages::addMessage(
                    I18N::translate('Your account is pending administrator approval. You have limited access until approved. You will be notified via email once approved.'),
                    'warning'
                );
                
                $this->logger->log('User logged in but not approved - restricted access', [
                    'user' => $user->userName(),
                    'email' => $user->email()
                ]);
                
                // Notify administrators about pending approval (only once)
                $this->notifyAdministratorsAboutPendingUser($user, $request);
            }

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
                'redirect_uri_sent' => route(self::class),
                'code' => substr($queryParams['code'] ?? '', 0, 8) . '...'
            ]);

            // Log the exact URI for debugging
            $this->logger->log('DEBUG: WEBTREES SENT THIS REDIRECT URI: ' . route(self::class));
            $this->logger->log('DEBUG: Ensure WordPress OAuth Client is set to EXACTLY this value.');

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
        $redirectUri = urldecode(route(self::class));

        $config = [
            'clientId'                => $this->module->getConfig('clientId'),
            'clientSecret'            => $this->module->getConfig('clientSecret'),
            'redirectUri'             => $redirectUri,
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

        $this->logger->log('OAuth Provider Configuration', [
            'redirectUri' => $redirectUri,
            'redirectUri_urlencoded' => urlencode($redirectUri),
            'redirectUri_length' => strlen($redirectUri),
            'clientId' => substr($this->module->getConfig('clientId'), 0, 8) . '...',
            'urlAuthorize' => $this->module->getConfig('urlAuthorize'),
        ]);

        // Log the exact redirect URI for WordPress configuration
        error_log('[WordPress SSO] EXACT REDIRECT URI TO CONFIGURE IN WORDPRESS:');
        error_log('[WordPress SSO] ' . $redirectUri);
        error_log('[WordPress SSO] URL Encoded: ' . urlencode($redirectUri));

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
            $errorMessage = $e->getMessage();
            $this->logger->log('Token exchange failed', [
                'error' => $errorMessage,
                'code_length' => strlen($code),
            ]);

            // If it's a redirect_uri_mismatch error, log detailed debugging info
            if (strpos($errorMessage, 'redirect_uri_mismatch') !== false) {
                $redirectUri = route(self::class);
                error_log('=== REDIRECT URI MISMATCH DEBUG ===');
                error_log('[WordPress SSO] Redirect URI sent: "' . $redirectUri . '"');
                error_log('[WordPress SSO] Redirect URI length: ' . strlen($redirectUri));
                error_log('[WordPress SSO] Redirect URI (URL encoded): ' . urlencode($redirectUri));
                error_log('[WordPress SSO] Redirect URI (raw bytes): ' . bin2hex($redirectUri));
                error_log('[WordPress SSO] Error from WordPress: ' . $errorMessage);
                error_log('[WordPress SSO] SOLUTION: Verify Redirect URI matches your WordPress OAuth client settings.');
                error_log('[WordPress SSO] Make sure the redirect URI in WordPress EXACTLY matches the one above');
                error_log('=== END DEBUG ===');
            }

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
     * Clean up session data
     */
    private function cleanupSession(): void
    {
        Session::forget('oauth2state');
        Session::forget('oauth2pkceCode');
        Session::forget('wordpress_sso_initiating_user');
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
        $newUser = $this->user_service->create($user_name, $user_name, $email, md5(random_bytes(32)));
        $newUser->setPreference(self::WP_USER_ID_PREFERENCE, $wp_user_id);

        $newUser->setPreference(UserInterface::PREF_IS_ACCOUNT_APPROVED, '0');
        $newUser->setPreference(UserInterface::PREF_IS_EMAIL_VERIFIED, '1');

        // Notify administrators of the new user account.
        $subject = I18N::translate('New user registration requires approval');
        $body = I18N::translate('A new user has registered via WordPress SSO and requires your approval.') . "\n\n"
            . I18N::translate('Username') . ': ' . $newUser->userName() . "\n"
            . I18N::translate('Email') . ': ' . $newUser->email() . "\n\n"
            . I18N::translate('You can approve this user in the webtrees control panel under \'Users\'.');

        foreach ($this->user_service->administrators() as $admin) {
            $this->logger->log('Sending approval request email', [
                'to_admin' => $admin->email(),
                'new_user' => $newUser->userName()
            ]);
            
            // EmailService::send($from, $to, $reply_to, $subject, $text, $html)
            $this->email_service->send($newUser, $admin, $newUser, $subject, $body, $body);
        }

        return $newUser;
    }

    /**
     * Notify administrators about pending user approval
     * Leverages Webtrees' existing notification system (same as native user registration)
     * Sends both internal messages and emails to all administrators
     * 
     * @param UserInterface $user The user awaiting approval
     * @param ServerRequestInterface $request The current request
     */
    private function notifyAdministratorsAboutPendingUser(UserInterface $user, ServerRequestInterface $request): void
    {
        // Check if we already notified admins for this user (prevent duplicate notifications)
        $notification_sent_pref = 'sso_admin_notified';
        if ($user->getPreference($notification_sent_pref) === '1') {
            $this->logger->log('Admin notification already sent for this user, skipping', [
                'user' => $user->userName()
            ]);
            return;
        }

        // Get WordPress user ID if available
        $wp_user_id = $user->getPreference(self::WP_USER_ID_PREFERENCE, 'Unknown');
        
        // Get client IP and user agent for security tracking
        $ip_address = $request->getAttribute('client-ip', 'Unknown');
        $user_agent = $request->getHeaderLine('User-Agent') ?: 'Unknown';
        
        // Prepare notification details
        $username = $user->userName();
        $email = $user->email();
        $real_name = $user->realName();
        $timestamp = date('Y-m-d H:i:s');
        
        // Build message subject
        $subject = I18N::translate('New user registration - approval needed');
        
        // Build message body (plain text)
        $message_text = I18N::translate('A new user has registered via WordPress Single Sign-On and requires approval.') . "\n\n"
            . I18N::translate('User Details:') . "\n"
            . '• ' . I18N::translate('Username') . ': ' . $username . "\n"
            . '• ' . I18N::translate('Email') . ': ' . $email . "\n"
            . '• ' . I18N::translate('Real name') . ': ' . $real_name . "\n"
            . '• ' . I18N::translate('WordPress User ID') . ': ' . $wp_user_id . "\n"
            . '• ' . I18N::translate('Login Attempt Time') . ': ' . $timestamp . "\n"
            . '• ' . I18N::translate('IP Address') . ': ' . $ip_address . "\n\n"
            . I18N::translate('The user has attempted to login via WordPress SSO but was blocked because their account has not been approved yet.') . "\n\n"
            . I18N::translate('To approve this user:') . "\n"
            . '1. ' . I18N::translate('Go to Control Panel → User Administration') . "\n"
            . '2. ' . I18N::translate('Find user: %s', $username) . "\n"
            . '3. ' . I18N::translate('Click the user to edit') . "\n"
            . '4. ' . I18N::translate('Check "Approved" and save') . "\n\n"
            . I18N::translate('Once approved, the user will be able to login automatically via WordPress SSO.');
        
        // Build HTML version of the message
        $message_html = '<p><strong>' . I18N::translate('A new user has registered via WordPress Single Sign-On and requires approval.') . '</strong></p>'
            . '<h3>' . I18N::translate('User Details:') . '</h3>'
            . '<ul>'
            . '<li><strong>' . I18N::translate('Username') . ':</strong> ' . e($username) . '</li>'
            . '<li><strong>' . I18N::translate('Email') . ':</strong> ' . e($email) . '</li>'
            . '<li><strong>' . I18N::translate('Real name') . ':</strong> ' . e($real_name) . '</li>'
            . '<li><strong>' . I18N::translate('WordPress User ID') . ':</strong> ' . e($wp_user_id) . '</li>'
            . '<li><strong>' . I18N::translate('Login Attempt Time') . ':</strong> ' . e($timestamp) . '</li>'
            . '<li><strong>' . I18N::translate('IP Address') . ':</strong> ' . e($ip_address) . '</li>'
            . '</ul>'
            . '<p>' . I18N::translate('The user has attempted to login via WordPress SSO but was blocked because their account has not been approved yet.') . '</p>'
            . '<h3>' . I18N::translate('To approve this user:') . '</h3>'
            . '<ol>'
            . '<li>' . I18N::translate('Go to Control Panel → User Administration') . '</li>'
            . '<li>' . I18N::translate('Find user: %s', e($username)) . '</li>'
            . '<li>' . I18N::translate('Click the user to edit') . '</li>'
            . '<li>' . I18N::translate('Check "Approved" and save') . '</li>'
            . '</ol>'
            . '<p>' . I18N::translate('Once approved, the user will be able to login automatically via WordPress SSO.') . '</p>';

        // Get all administrators
        $administrators = $this->user_service->administrators();
        $admin_count = count($administrators);
        
        $this->logger->log('Sending pending approval notifications to administrators', [
            'user' => $username,
            'email' => $email,
            'wp_user_id' => $wp_user_id,
            'admin_count' => $admin_count,
            'ip_address' => $ip_address
        ]);

        $notification_success_count = 0;
        $notification_error_count = 0;

        foreach ($administrators as $admin) {
            try {
                // Send internal Webtrees message (visible in user's inbox)
                $this->message_service->deliverMessage(
                    $user,      // From: the pending user
                    $admin,     // To: admin
                    $subject,
                    $message_text,
                    '',         // No URL
                    ''          // No recipient email (internal message only)
                );

                // Send email notification
                $this->email_service->send(
                    $user,          // From
                    $admin,         // To
                    $user,          // Reply-to
                    $subject,
                    $message_text,  // Plain text version
                    $message_html   // HTML version
                );

                $notification_success_count++;
                
                $this->logger->log('Notification sent successfully', [
                    'admin_email' => $admin->email(),
                    'admin_username' => $admin->userName()
                ]);

            } catch (\Exception $e) {
                $notification_error_count++;
                
                $this->logger->log('Failed to send notification to admin', [
                    'admin_email' => $admin->email(),
                    'error' => $e->getMessage()
                ]);
                
                // Continue with other admins even if one fails
                continue;
            }
        }

        // Mark that we've notified admins for this user (prevent duplicate notifications)
        $user->setPreference($notification_sent_pref, '1');

        $this->logger->log('Admin notification process completed', [
            'total_admins' => $admin_count,
            'successful' => $notification_success_count,
            'failed' => $notification_error_count
        ]);

        // Log the event for audit trail
        Log::addAuthenticationLog('WordPress SSO: Pending approval notification sent to ' . $notification_success_count . ' administrator(s) for user: ' . $username);
    }
}

