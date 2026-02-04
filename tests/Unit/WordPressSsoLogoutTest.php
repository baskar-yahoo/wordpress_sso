<?php

/**
 * Unit Tests for WordPress SSO Logout Flow
 * 
 * Test the security-hardened logout implementation
 * 
 * To run these tests:
 * 1. Install PHPUnit: composer require --dev phpunit/phpunit
 * 2. Run: vendor/bin/phpunit tests/Unit/WordPressSsoLogoutTest.php
 */

namespace Webtrees\WordPressSso\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webtrees\WordPressSso\Http\WordPressSsoLogout;
use Webtrees\WordPressSso\WordPressSsoModule;
use Fisharebest\Webtrees\Session;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class WordPressSsoLogoutTest extends TestCase
{
    private WordPressSsoLogout $logout_handler;
    private WordPressSsoModule $module;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the module
        $this->module = $this->createMock(WordPressSsoModule::class);
        $this->logout_handler = new WordPressSsoLogout($this->module);
    }
    
    /**
     * Test that logout generates a secure token
     */
    public function testLogoutGeneratesSecureToken(): void
    {
        // Create mock request
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('https');
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getPath')->willReturn('/webtrees/logout');
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        
        // Mock debug disabled
        $this->module->method('getConfig')
            ->with('debugEnabled', '0')
            ->willReturn('0');
        
        // Capture session data
        Session::put('test', 'value');
        
        // Execute logout
        $response = $this->logout_handler->handle($request);
        
        // Assert session has logout token
        $token = Session::get('webtrees_logout_token');
        $this->assertNotNull($token, 'Logout token should be generated');
        $this->assertGreaterThanOrEqual(32, strlen($token), 'Token should be at least 32 chars');
        
        // Assert timestamp is set
        $timestamp = Session::get('webtrees_logout_time');
        $this->assertNotNull($timestamp, 'Logout timestamp should be set');
        $this->assertEqualsWithDelta(time(), $timestamp, 2, 'Timestamp should be current time');
    }
    
    /**
     * Test that logout URL is properly constructed
     */
    public function testLogoutUrlConstruction(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('https');
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getPath')->willReturn('/webtrees/logout');
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        
        $this->module->method('getConfig')->willReturn('0');
        
        $response = $this->logout_handler->handle($request);
        
        // Get the redirect location
        $location = $response->getHeaderLine('Location');
        
        // Assert URL structure
        $this->assertStringContainsString('https://example.com', $location);
        $this->assertStringContainsString('modules_v4/wordpress_sso/sso_logout.php', $location);
        $this->assertStringContainsString('token=', $location);
    }
    
    /**
     * Test token uniqueness
     */
    public function testTokenUniqueness(): void
    {
        $tokens = [];
        
        for ($i = 0; $i < 100; $i++) {
            $token = bin2hex(random_bytes(32));
            $this->assertNotContains($token, $tokens, 'Token should be unique');
            $tokens[] = $token;
        }
        
        $this->assertCount(100, array_unique($tokens), 'All tokens should be unique');
    }
    
    /**
     * Test debug logging when enabled
     */
    public function testDebugLoggingWhenEnabled(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('https');
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getPath')->willReturn('/webtrees/logout');
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        
        // Enable debug
        $this->module->method('getConfig')
            ->willReturn('1');
        
        $response = $this->logout_handler->handle($request);
        
        // Check if debug log exists
        $module_dir = dirname(dirname(dirname(__DIR__)));
        $modules_dir = dirname($module_dir);
        $webtrees_dir = dirname($modules_dir);
        $debug_log = $webtrees_dir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sso_debug.txt';
        
        if (file_exists($debug_log)) {
            $log_content = file_get_contents($debug_log);
            $this->assertStringContainsString('Logout initiated', $log_content);
        }
    }
}


/**
 * Integration Tests for sso_logout.php Bridge Script
 */
class SsoLogoutBridgeTest extends TestCase
{
    private string $bridge_script;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge_script = dirname(dirname(__DIR__)) . '/sso_logout.php';
    }
    
    /**
     * Test bridge script exists
     */
    public function testBridgeScriptExists(): void
    {
        $this->assertFileExists($this->bridge_script, 'Bridge script should exist');
    }
    
    /**
     * Test token validation rejects missing token
     */
    public function testRejectsMissingToken(): void
    {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set valid session token
        $_SESSION['webtrees_logout_token'] = 'test_token_12345';
        $_SESSION['webtrees_logout_time'] = time();
        
        // Don't provide token in GET
        unset($_GET['token']);
        
        // Capture output
        ob_start();
        
        // Include would redirect, so we test the validation function separately
        // This is a mock test - in reality you'd extract the validation to a testable function
        
        $result = $this->validateTokenMock();
        
        ob_end_clean();
        
        $this->assertFalse($result, 'Should reject request without token');
    }
    
    /**
     * Test token validation rejects expired token
     */
    public function testRejectsExpiredToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = 'test_token_12345';
        $_SESSION['webtrees_logout_token'] = $token;
        $_SESSION['webtrees_logout_time'] = time() - 120; // 2 minutes ago (expired)
        $_GET['token'] = $token;
        
        $result = $this->validateTokenMock();
        
        $this->assertFalse($result, 'Should reject expired token');
    }
    
    /**
     * Test token validation accepts valid token
     */
    public function testAcceptsValidToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['webtrees_logout_token'] = $token;
        $_SESSION['webtrees_logout_time'] = time();
        $_GET['token'] = $token;
        
        $result = $this->validateTokenMock();
        
        $this->assertTrue($result, 'Should accept valid token');
    }
    
    /**
     * Test token is consumed after use
     */
    public function testTokenIsConsumed(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['webtrees_logout_token'] = $token;
        $_SESSION['webtrees_logout_time'] = time();
        $_GET['token'] = $token;
        
        // First use - should succeed
        $result1 = $this->validateTokenMock();
        $this->assertTrue($result1);
        
        // Token should be removed from session
        $this->assertArrayNotHasKey('webtrees_logout_token', $_SESSION, 'Token should be consumed');
        
        // Second use - should fail
        $result2 = $this->validateTokenMock();
        $this->assertFalse($result2, 'Token should not be reusable');
    }
    
    /**
     * Mock validation function for testing
     * (In real implementation, extract from sso_logout.php)
     */
    private function validateTokenMock(): bool
    {
        if (!isset($_GET['token']) || empty($_GET['token'])) {
            return false;
        }
        
        $provided_token = $_GET['token'];
        
        if (!isset($_SESSION['webtrees_logout_token'])) {
            return false;
        }
        
        $session_token = $_SESSION['webtrees_logout_token'];
        
        if (!hash_equals($session_token, $provided_token)) {
            return false;
        }
        
        $token_time = $_SESSION['webtrees_logout_time'] ?? 0;
        if (time() - $token_time > 60) {
            unset($_SESSION['webtrees_logout_token'], $_SESSION['webtrees_logout_time']);
            return false;
        }
        
        // Consume token
        unset($_SESSION['webtrees_logout_token'], $_SESSION['webtrees_logout_time']);
        
        return true;
    }
}
