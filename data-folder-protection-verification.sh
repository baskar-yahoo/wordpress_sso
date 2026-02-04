#!/bin/bash
# Webtrees data/ Folder Protection Verification Script
# This script verifies that the data/ folder is properly protected

echo "=========================================="
echo "Webtrees data/ Folder Security Test"
echo "=========================================="
echo ""

# Configuration
WEBTREES_URL="https://svajana.org/familytree"
# For development: WEBTREES_URL="http://localhost/svajana/familytree"

echo "Testing: $WEBTREES_URL"
echo ""

# Test 1: Direct access to data/ folder
echo "Test 1: Direct access to data/ folder"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - data/ folder is blocked (403 Forbidden)"
else
    echo "❌ FAIL - data/ folder returned: $HTTP_CODE (Expected: 403)"
fi
echo ""

# Test 2: Access to config.ini.php
echo "Test 2: Access to config.ini.php"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/config.ini.php")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - config.ini.php is blocked (403 Forbidden)"
else
    echo "❌ FAIL - config.ini.php returned: $HTTP_CODE (Expected: 403)"
fi
echo ""

# Test 3: Access to sso_debug.txt
echo "Test 3: Access to sso_debug.txt"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/sso_debug.txt")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - sso_debug.txt is blocked (403 Forbidden)"
else
    echo "❌ FAIL - sso_debug.txt returned: $HTTP_CODE (Expected: 403)"
fi
echo ""

# Test 4: Access to potential database files
echo "Test 4: Access to potential database files"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/database.sqlite")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - database files are blocked (403 Forbidden)"
else
    echo "❌ FAIL - database.sqlite returned: $HTTP_CODE (Expected: 403)"
fi
echo ""

# Test 5: Directory traversal attempt
echo "Test 5: Directory traversal protection"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/../data/config.ini.php")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - Directory traversal blocked (403 Forbidden)"
else
    echo "❌ FAIL - Traversal returned: $HTTP_CODE (Expected: 403)"
fi
echo ""

# Test 6: Verify data/.htaccess exists
echo "Test 6: Verify data/.htaccess exists on server"
echo "--------------------------------------"
echo "Manual verification required:"
echo "SSH to server and run: cat /path/to/public_html/familytree/data/.htaccess"
echo "Expected content:"
echo "  Order allow,deny"
echo "  Deny from all"
echo ""

# Test 7: Verify root .htaccess doesn't expose data/
echo "Test 7: Root .htaccess impact on data/ folder"
echo "--------------------------------------"
echo "Root .htaccess should NOT affect data/ folder protection"
echo "The data/.htaccess takes precedence for data/* paths"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/data/")
if [ "$HTTP_CODE" = "403" ]; then
    echo "✅ PASS - data/ still blocked despite root .htaccess"
else
    echo "❌ FAIL - Root .htaccess may have affected data/ protection!"
fi
echo ""

# Test 8: Verify normal Webtrees pages work
echo "Test 8: Normal Webtrees pages accessible"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo "✅ PASS - Homepage accessible ($HTTP_CODE)"
else
    echo "❌ FAIL - Homepage returned: $HTTP_CODE (Expected: 200 or 302)"
fi
echo ""

# Test 9: Verify protected files in root
echo "Test 9: Root config file protection"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/config.ini.php")
if [ "$HTTP_CODE" = "403" ] || [ "$HTTP_CODE" = "404" ]; then
    echo "✅ PASS - Root config protected ($HTTP_CODE)"
else
    echo "❌ FAIL - Root config.ini.php returned: $HTTP_CODE (Expected: 403 or 404)"
fi
echo ""

# Test 10: Verify composer.json protection
echo "Test 10: Composer file protection"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$WEBTREES_URL/composer.json")
if [ "$HTTP_CODE" = "403" ] || [ "$HTTP_CODE" = "404" ]; then
    echo "✅ PASS - composer.json protected ($HTTP_CODE)"
else
    echo "⚠️  WARNING - composer.json returned: $HTTP_CODE"
    echo "   (This file may expose version information)"
fi
echo ""

# Summary
echo "=========================================="
echo "Security Test Summary"
echo "=========================================="
echo ""
echo "✅ Tests passed indicate proper data/ folder protection"
echo "❌ Failed tests indicate security misconfiguration"
echo ""
echo "Important: data/ folder protection comes from data/.htaccess"
echo "           NOT from the root familytree/.htaccess"
echo ""
echo "Manual verification steps:"
echo "1. SSH to server"
echo "2. Verify: /path/to/familytree/data/.htaccess contains 'Deny from all'"
echo "3. Verify: File permissions on data/ folder (should be 755)"
echo "4. Verify: Files in data/ have proper permissions (644)"
echo ""
echo "=========================================="
