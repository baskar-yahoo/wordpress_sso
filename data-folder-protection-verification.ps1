# Webtrees data/ Folder Protection Verification Script (Windows)
# This script verifies that the data/ folder is properly protected

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Webtrees data/ Folder Security Test" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$WebtreesUrl = "http://localhost/svajana/familytree"
# For production: $WebtreesUrl = "https://svajana.org/familytree"

Write-Host "Testing: $WebtreesUrl" -ForegroundColor Yellow
Write-Host ""

# Function to test URL
function Test-UrlProtection {
    param(
        [string]$Url,
        [string]$TestName
    )
    
    Write-Host "Test: $TestName" -ForegroundColor White
    Write-Host "--------------------------------------" -ForegroundColor Gray
    
    try {
        $response = Invoke-WebRequest -Uri $Url -Method Head -ErrorAction Stop
        $statusCode = $response.StatusCode
        
        if ($statusCode -eq 403) {
            Write-Host "✅ PASS - Blocked (403 Forbidden)" -ForegroundColor Green
        } else {
            Write-Host "❌ FAIL - Returned: $statusCode (Expected: 403)" -ForegroundColor Red
        }
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        
        if ($statusCode -eq 403) {
            Write-Host "✅ PASS - Blocked (403 Forbidden)" -ForegroundColor Green
        } else {
            Write-Host "❌ FAIL - Returned: $statusCode (Expected: 403)" -ForegroundColor Red
        }
    }
    Write-Host ""
}

# Test 1: Direct access to data/ folder
Test-UrlProtection -Url "$WebtreesUrl/data/" -TestName "Direct access to data/ folder"

# Test 2: Access to config.ini.php
Test-UrlProtection -Url "$WebtreesUrl/data/config.ini.php" -TestName "Access to config.ini.php"

# Test 3: Access to sso_debug.txt
Test-UrlProtection -Url "$WebtreesUrl/data/sso_debug.txt" -TestName "Access to sso_debug.txt"

# Test 4: Access to potential database files
Test-UrlProtection -Url "$WebtreesUrl/data/database.sqlite" -TestName "Access to potential database files"

# Test 5: Directory traversal attempt
Test-UrlProtection -Url "$WebtreesUrl/data/../data/config.ini.php" -TestName "Directory traversal protection"

# Test 6: Verify data/.htaccess exists
Write-Host "Test: Verify data/.htaccess exists on local system" -ForegroundColor White
Write-Host "--------------------------------------" -ForegroundColor Gray
$dataHtaccess = "C:\xampp\htdocs\svajana\familytree\data\.htaccess"
if (Test-Path $dataHtaccess) {
    Write-Host "✅ PASS - data\.htaccess exists" -ForegroundColor Green
    Write-Host "Content:" -ForegroundColor Yellow
    Get-Content $dataHtaccess | ForEach-Object { Write-Host "  $_" -ForegroundColor White }
} else {
    Write-Host "❌ FAIL - data\.htaccess NOT FOUND!" -ForegroundColor Red
    Write-Host "⚠️  WARNING: Create data\.htaccess immediately!" -ForegroundColor Yellow
}
Write-Host ""

# Test 7: Verify root .htaccess doesn't expose data/
Write-Host "Test: Root .htaccess impact on data/ folder" -ForegroundColor White
Write-Host "--------------------------------------" -ForegroundColor Gray
Write-Host "Root .htaccess should NOT affect data/ folder protection" -ForegroundColor White
Write-Host "The data\.htaccess takes precedence for data\* paths" -ForegroundColor White

try {
    $response = Invoke-WebRequest -Uri "$WebtreesUrl/data/" -Method Head -ErrorAction Stop
    $statusCode = $response.StatusCode
    
    if ($statusCode -eq 403) {
        Write-Host "✅ PASS - data/ still blocked despite root .htaccess" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL - Root .htaccess may have affected data/ protection!" -ForegroundColor Red
    }
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    
    if ($statusCode -eq 403) {
        Write-Host "✅ PASS - data/ still blocked despite root .htaccess" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL - Protection may be compromised!" -ForegroundColor Red
    }
}
Write-Host ""

# Test 8: Verify normal Webtrees pages work
Write-Host "Test: Normal Webtrees pages accessible" -ForegroundColor White
Write-Host "--------------------------------------" -ForegroundColor Gray

try {
    $response = Invoke-WebRequest -Uri "$WebtreesUrl/" -Method Head -ErrorAction Stop
    $statusCode = $response.StatusCode
    
    if ($statusCode -eq 200 -or $statusCode -eq 302) {
        Write-Host "✅ PASS - Homepage accessible ($statusCode)" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL - Homepage returned: $statusCode (Expected: 200 or 302)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ FAIL - Homepage not accessible" -ForegroundColor Red
}
Write-Host ""

# Test 9: Verify protected files in root
Write-Host "Test: Root config file protection" -ForegroundColor White
Write-Host "--------------------------------------" -ForegroundColor Gray

try {
    $response = Invoke-WebRequest -Uri "$WebtreesUrl/config.ini.php" -Method Head -ErrorAction Stop
    $statusCode = $response.StatusCode
    
    if ($statusCode -eq 403 -or $statusCode -eq 404) {
        Write-Host "✅ PASS - Root config protected ($statusCode)" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL - Root config.ini.php returned: $statusCode" -ForegroundColor Red
    }
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 403 -or $statusCode -eq 404) {
        Write-Host "✅ PASS - Root config protected ($statusCode)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  WARNING - Root config might be accessible" -ForegroundColor Yellow
    }
}
Write-Host ""

# Test 10: Verify index.php exists in data/
Write-Host "Test: Verify data\index.php exists (backup protection)" -ForegroundColor White
Write-Host "--------------------------------------" -ForegroundColor Gray
$dataIndex = "C:\xampp\htdocs\svajana\familytree\data\index.php"
if (Test-Path $dataIndex) {
    Write-Host "✅ PASS - data\index.php exists (Layer 2 protection)" -ForegroundColor Green
} else {
    Write-Host "⚠️  WARNING - data\index.php NOT FOUND" -ForegroundColor Yellow
    Write-Host "   Consider creating it for defense in depth" -ForegroundColor Yellow
}
Write-Host ""

# Summary
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Security Test Summary" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "✅ Tests passed indicate proper data/ folder protection" -ForegroundColor Green
Write-Host "❌ Failed tests indicate security misconfiguration" -ForegroundColor Red
Write-Host ""
Write-Host "Important: data/ folder protection comes from data\.htaccess" -ForegroundColor Yellow
Write-Host "           NOT from the root familytree\.htaccess" -ForegroundColor Yellow
Write-Host ""
Write-Host "Manual verification steps:" -ForegroundColor White
Write-Host "1. Verify: C:\xampp\htdocs\svajana\familytree\data\.htaccess exists" -ForegroundColor White
Write-Host "2. Verify: Content is 'Deny from all'" -ForegroundColor White
Write-Host "3. Verify: File permissions are correct (readable by Apache)" -ForegroundColor White
Write-Host "4. Verify: Apache AllowOverride is set to All in httpd.conf" -ForegroundColor White
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan

# Pause to review results
Write-Host ""
Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
