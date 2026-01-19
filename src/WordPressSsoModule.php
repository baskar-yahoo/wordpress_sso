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
use Webtrees\WordPressSso\Http\WordPressSsoLoginAction;
use Webtrees\WordPressSso\Http\WordPressSsoLogout;

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
    public const SSO_PKCE_METHOD = 'sso_pkce_method';
    public const SSO_SYNC_EMAIL = 'sso_sync_email';
    public const SSO_DEBUG_ENABLED = 'sso_debug_enabled';

    public function title(): string
    {
        return I18N::translate('WordPress SSO');
    }

    public function description(): string
    {
        return I18N::translate('Production-ready Single Sign-On with WordPress.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Enhanced by Gemini';
    }

    public function customModuleVersion(): string
    {
        return '2.0.0';
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

    /**
     * Get configuration value from config.ini.php or fallback to database preference
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getConfig(string $key, string $default = ''): string
    {
        // Try to get from config.ini.php first
        $config_key = 'WordPress_SSO_' . $key;
        
        // Read config.ini.php directly
        // Use dirname() for cross-platform path resolution
        $module_dir = dirname(__DIR__); // Go up from src to wordpress_sso
        $modules_dir = dirname($module_dir); // Go up to modules_v4
        $webtrees_dir = dirname($modules_dir); // Go up to familytree
        $config_file = $webtrees_dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.ini.php';
        
        if (file_exists($config_file)) {
            $config_content = @file_get_contents($config_file);
            
            if ($config_content !== false) {
                // Look for the config key in the file
                // Pattern: WordPress_SSO_key='value' or WordPress_SSO_key="value"
                $pattern = '/' . preg_quote($config_key, '/') . '\s*=\s*[\'"]([^\'"]*)[\'"]/' ;
                
                if (preg_match($pattern, $config_content, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        // Fallback to module preference (database)
        return $this->getPreference($key, $default);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $is_sso_callback = $request->getUri()->getPath() === self::SSO_CALLBACK_ROUTE;
        $sso_enabled = $this->getConfig('enabled', '0');
        
        // Debug logging (only if enabled)
        if ($this->getConfig('debugEnabled', '0') === '1') {
            $auth_check = Auth::check() ? 'true' : 'false';
            $sso_callback_str = $is_sso_callback ? 'true' : 'false';
            $log_data = date('Y-m-d H:i:s') . " - SSO Debug: Enabled='$sso_enabled', Auth='$auth_check', IsCallback='$sso_callback_str', Route='" . $request->getUri()->getPath() . "'\n";
            @file_put_contents(__DIR__ . '/../../../data/sso_debug.txt', $log_data, FILE_APPEND);
        }

        if (!Auth::check() && !$is_sso_callback && $sso_enabled === '1') {
            if ($this->getConfig('debugEnabled', '0') === '1') {
                @file_put_contents(__DIR__ . '/../../../data/sso_debug.txt', "SSO Debug: Redirecting to Login Action\n", FILE_APPEND);
            }
            return redirect(route('WordPressSsoLoginAction'));
        }

        return $this->next->handle($request);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $params = [
            'title' => $this->title(),
            'sso_enabled' => (bool) $this->getConfig('enabled'),
            'sso_allow_creation' => (bool) $this->getConfig('allowCreation'),
            'sso_client_id' => $this->getConfig('clientId'),
            'sso_client_secret' => $this->getConfig('clientSecret'),
            'sso_url_authorize' => $this->getConfig('urlAuthorize'),
            'sso_url_access_token' => $this->getConfig('urlAccessToken'),
            'sso_url_resource_owner_details' => $this->getConfig('urlResourceOwner'),
            'sso_url_logout' => $this->getConfig('urlLogout'),
            'sso_pkce_method' => $this->getConfig('pkceMethod', 'S256'),
            'sso_sync_email' => (bool) $this->getConfig('syncEmail'),
            'sso_debug_enabled' => (bool) $this->getConfig('debugEnabled'),
            'callback_url' => route('WordPressSsoLoginAction'),
            'action_url' => $this->getConfigLink(),
            'config_location' => $this->getConfigLocation(),
        ];

        return $this->viewResponse($this->name() . '::settings', $params);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $validator = Validator::parsedBody($request);

        $this->setPreference(self::SSO_ENABLED, (string) (int) $validator->boolean(self::SSO_ENABLED));
        $this->setPreference(self::SSO_ALLOW_CREATION, (string) (int) $validator->boolean(self::SSO_ALLOW_CREATION));
        $this->setPreference(self::SSO_CLIENT_ID, $validator->string(self::SSO_CLIENT_ID, ''));
        $this->setPreference(self::SSO_CLIENT_SECRET, $validator->string(self::SSO_CLIENT_SECRET, ''));
        $this->setPreference(self::SSO_URL_AUTHORIZE, $validator->string(self::SSO_URL_AUTHORIZE, ''));
        $this->setPreference(self::SSO_URL_ACCESS_TOKEN, $validator->string(self::SSO_URL_ACCESS_TOKEN, ''));
        $this->setPreference(self::SSO_URL_RESOURCE_OWNER, $validator->string(self::SSO_URL_RESOURCE_OWNER, ''));
        $this->setPreference(self::SSO_URL_LOGOUT, $validator->string(self::SSO_URL_LOGOUT, ''));
        $this->setPreference(self::SSO_PKCE_METHOD, $validator->string(self::SSO_PKCE_METHOD, 'S256'));
        $this->setPreference(self::SSO_SYNC_EMAIL, (string) (int) $validator->boolean(self::SSO_SYNC_EMAIL));
        $this->setPreference(self::SSO_DEBUG_ENABLED, (string) (int) $validator->boolean(self::SSO_DEBUG_ENABLED));

        return redirect($this->getConfigLink());
    }

    /**
     * Get the configuration location (config.ini.php or database)
     *
     * @return string
     */
    private function getConfigLocation(): string
    {
        // Check if config.ini.php has WordPress SSO settings
        $module_dir = dirname(__DIR__);
        $modules_dir = dirname($module_dir);
        $webtrees_dir = dirname($modules_dir);
        $config_file = $webtrees_dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.ini.php';
        
        if (file_exists($config_file)) {
            $config_content = @file_get_contents($config_file);
            
            if ($config_content !== false) {
                // Check if any WordPress_SSO_ setting exists
                if (preg_match('/WordPress_SSO_\w+\s*=/', $config_content)) {
                    return 'config.ini.php';
                }
            }
        }
        
        return 'database';
    }
}
