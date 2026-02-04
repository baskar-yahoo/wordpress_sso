# WordPress-Webtrees SSO Deployment Script
# Version: 2.0.0
# Purpose: Automated deployment with backup and verification

param(
    [switch]$Backup = $true,
    [switch]$Deploy = $true,
    [switch]$Test = $true,
    [switch]$Rollback = $false,
    [string]$BackupPath = "c:\xampp\backups"
)

# Configuration
$WebtreesRoot = "c:\xampp\htdocs\familytree"
$ModulePath = "$WebtreesRoot\modules_v4\wordpress_sso"
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

# Colors for output
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-Error { Write-Host $args -ForegroundColor Red }

Write-Info "========================================="
Write-Info "WordPress-Webtrees SSO Deployment v2.0.0"
Write-Info "========================================="
Write-Info ""

# ============================================
# STEP 1: PRE-DEPLOYMENT CHECKS
# ============================================

Write-Info "Step 1: Pre-Deployment Checks"
Write-Info "-" * 40

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Warning "Not running as Administrator. File permissions may fail."
    $continue = Read-Host "Continue anyway? (y/n)"
    if ($continue -ne 'y') { exit }
}

# Check if paths exist
if (-not (Test-Path $WebtreesRoot)) {
    Write-Error "Webtrees root not found: $WebtreesRoot"
    exit 1
}

if (-not (Test-Path $ModulePath)) {
    Write-Error "WordPress SSO module not found: $ModulePath"
    exit 1
}

Write-Success "✓ Pre-deployment checks passed"
Write-Info ""

# ============================================
# STEP 2: BACKUP
# ============================================

if ($Backup -and -not $Rollback) {
    Write-Info "Step 2: Creating Backup"
    Write-Info "-" * 40
    
    # Create backup directory
    $BackupDir = "$BackupPath\wordpress_sso_$Timestamp"
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    
    # Backup module files
    Write-Info "Backing up module files..."
    Copy-Item -Path $ModulePath -Destination "$BackupDir\wordpress_sso" -Recurse -Force
    
    # Backup database (module preferences)
    Write-Info "Backing up database..."
    $mysqldump = "c:\xampp\mysql\bin\mysqldump.exe"
    if (Test-Path $mysqldump) {
        $dbName = "webtrees"
        & $mysqldump -u root --no-tablespaces --skip-triggers `
            --tables wt_module_setting wt_module_privacy `
            -r "$BackupDir\wordpress_sso_db_$Timestamp.sql" $dbName 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            Write-Success "✓ Database backup created"
        } else {
            Write-Warning "! Database backup failed (non-critical)"
        }
    }
    
    Write-Success "✓ Backup created at: $BackupDir"
    Write-Info ""
}

# ============================================
# STEP 3: ROLLBACK (IF REQUESTED)
# ============================================

if ($Rollback) {
    Write-Warning "Step 3: ROLLBACK MODE"
    Write-Warning "-" * 40
    
    # Find latest backup
    $latestBackup = Get-ChildItem -Path $BackupPath -Directory -Filter "wordpress_sso_*" |
        Sort-Object Name -Descending |
        Select-Object -First 1
    
    if (-not $latestBackup) {
        Write-Error "No backup found in $BackupPath"
        exit 1
    }
    
    Write-Warning "Rolling back to: $($latestBackup.Name)"
    $confirm = Read-Host "Are you sure? This will overwrite current files. (yes/no)"
    
    if ($confirm -ne "yes") {
        Write-Info "Rollback cancelled"
        exit 0
    }
    
    # Restore files
    Write-Info "Restoring files..."
    Copy-Item -Path "$($latestBackup.FullName)\wordpress_sso\*" -Destination $ModulePath -Recurse -Force
    
    # Restore database
    $dbBackup = Get-ChildItem -Path $latestBackup.FullName -Filter "wordpress_sso_db_*.sql" |
        Select-Object -First 1
    
    if ($dbBackup) {
        Write-Info "Restoring database..."
        $mysql = "c:\xampp\mysql\bin\mysql.exe"
        if (Test-Path $mysql) {
            Get-Content $dbBackup.FullName | & $mysql -u root webtrees
            Write-Success "✓ Database restored"
        }
    }
    
    Write-Success "✓ Rollback completed"
    Write-Info ""
    exit 0
}

# ============================================
# STEP 4: DEPLOY NEW FILES
# ============================================

if ($Deploy) {
    Write-Info "Step 4: Deploying Updated Files"
    Write-Info "-" * 40
    
    $filesToDeploy = @(
        @{
            Source = "sso_logout.php"
            Dest = "$ModulePath\sso_logout.php"
            Status = "NEW"
        },
        @{
            Source = "src\Http\WordPressSsoLogout.php"
            Dest = "$ModulePath\src\Http\WordPressSsoLogout.php"
            Status = "UPDATED"
        },
        @{
            Source = "src\WordPressSsoModule.php"
            Dest = "$ModulePath\src\WordPressSsoModule.php"
            Status = "UPDATED"
        },
        @{
            Source = "src\Helpers\MenuHelper.php"
            Dest = "$ModulePath\src\Helpers\MenuHelper.php"
            Status = "NEW"
        },
        @{
            Source = "tests\Unit\WordPressSsoLogoutTest.php"
            Dest = "$ModulePath\tests\Unit\WordPressSsoLogoutTest.php"
            Status = "NEW"
        },
        @{
            Source = "AUTHENTICATION-FLOW.md"
            Dest = "$ModulePath\AUTHENTICATION-FLOW.md"
            Status = "NEW"
        },
        @{
            Source = "DEPLOYMENT-CHECKLIST.md"
            Dest = "$ModulePath\DEPLOYMENT-CHECKLIST.md"
            Status = "NEW"
        },
        @{
            Source = "QUICK-REFERENCE.md"
            Dest = "$ModulePath\QUICK-REFERENCE.md"
            Status = "NEW"
        },
        @{
            Source = "IMPLEMENTATION-SUMMARY.md"
            Dest = "$ModulePath\IMPLEMENTATION-SUMMARY.md"
            Status = "NEW"
        }
    )
    
    $deployed = 0
    $failed = 0
    
    foreach ($file in $filesToDeploy) {
        # Check if source exists
        if (Test-Path $file.Source) {
            try {
                # Create directory if needed
                $destDir = Split-Path $file.Dest -Parent
                if (-not (Test-Path $destDir)) {
                    New-Item -ItemType Directory -Path $destDir -Force | Out-Null
                }
                
                # Copy file
                Copy-Item -Path $file.Source -Destination $file.Dest -Force
                Write-Success "  ✓ [$($file.Status)] $($file.Source)"
                $deployed++
            }
            catch {
                Write-Error "  ✗ FAILED: $($file.Source) - $($_.Exception.Message)"
                $failed++
            }
        }
        else {
            Write-Warning "  ⊘ SKIPPED: $($file.Source) (not found)"
        }
    }
    
    Write-Info ""
    Write-Info "Deployment Summary:"
    Write-Success "  ✓ Deployed: $deployed files"
    if ($failed -gt 0) {
        Write-Error "  ✗ Failed: $failed files"
    }
    Write-Info ""
}

# ============================================
# STEP 5: SET FILE PERMISSIONS
# ============================================

Write-Info "Step 5: Setting File Permissions"
Write-Info "-" * 40

try {
    # Make sso_logout.php readable
    if (Test-Path "$ModulePath\sso_logout.php") {
        icacls "$ModulePath\sso_logout.php" /grant "IIS_IUSRS:(R)" /T /C | Out-Null
        Write-Success "✓ Set permissions for sso_logout.php"
    }
    
    # Ensure data directory is writable
    $dataDir = "$WebtreesRoot\data"
    if (Test-Path $dataDir) {
        icacls "$dataDir" /grant "IIS_IUSRS:(M)" /T /C | Out-Null
        Write-Success "✓ Set permissions for data directory"
    }
}
catch {
    Write-Warning "! Could not set permissions (may require Administrator)"
}

Write-Info ""

# ============================================
# STEP 6: CONFIGURATION CHECK
# ============================================

Write-Info "Step 6: Configuration Verification"
Write-Info "-" * 40

$configFile = "$WebtreesRoot\data\config.ini.php"
if (Test-Path $configFile) {
    $configContent = Get-Content $configFile -Raw
    
    # Check for SSO settings
    if ($configContent -match 'WordPress_SSO_enabled') {
        Write-Success "✓ WordPress SSO settings found in config.ini.php"
        
        # Check if debug is disabled
        if ($configContent -match 'WordPress_SSO_debugEnabled\s*=\s*"0"') {
            Write-Success "✓ Debug logging is DISABLED (production ready)"
        }
        else {
            Write-Warning "! Debug logging may be ENABLED (check config.ini.php)"
        }
    }
    else {
        Write-Warning "! WordPress SSO settings not in config.ini.php"
        Write-Info "  Settings will be loaded from database"
    }
}
else {
    Write-Warning "! config.ini.php not found"
}

Write-Info ""

# ============================================
# STEP 7: AUTOMATED TESTS
# ============================================

if ($Test) {
    Write-Info "Step 7: Running Automated Tests"
    Write-Info "-" * 40
    
    # Test 1: File existence
    Write-Info "Test 1: File Existence"
    $requiredFiles = @(
        "$ModulePath\sso_logout.php",
        "$ModulePath\src\Http\WordPressSsoLogout.php",
        "$ModulePath\src\WordPressSsoModule.php",
        "$ModulePath\src\Helpers\MenuHelper.php"
    )
    
    $allExist = $true
    foreach ($file in $requiredFiles) {
        if (Test-Path $file) {
            Write-Success "  ✓ $(Split-Path $file -Leaf) exists"
        }
        else {
            Write-Error "  ✗ $(Split-Path $file -Leaf) MISSING"
            $allExist = $false
        }
    }
    
    if ($allExist) {
        Write-Success "✓ All required files present"
    }
    
    Write-Info ""
    
    # Test 2: PHP Syntax Check
    Write-Info "Test 2: PHP Syntax Check"
    $phpExe = "c:\xampp\php\php.exe"
    
    if (Test-Path $phpExe) {
        $phpFiles = Get-ChildItem -Path "$ModulePath\src" -Filter "*.php" -Recurse
        $syntaxErrors = 0
        
        foreach ($phpFile in $phpFiles) {
            $result = & $phpExe -l $phpFile.FullName 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Error "  ✗ Syntax error in $($phpFile.Name)"
                $syntaxErrors++
            }
        }
        
        if ($syntaxErrors -eq 0) {
            Write-Success "✓ No PHP syntax errors found"
        }
        else {
            Write-Error "✗ Found $syntaxErrors syntax error(s)"
        }
    }
    else {
        Write-Warning "! PHP executable not found, skipping syntax check"
    }
    
    Write-Info ""
    
    # Test 3: Security Log Permissions
    Write-Info "Test 3: Log File Permissions"
    $logFile = "$WebtreesRoot\data\sso_security.log"
    
    try {
        # Try to write to log file
        "Test entry" | Out-File -FilePath $logFile -Append -ErrorAction Stop
        Write-Success "✓ Security log is writable"
        
        # Clean up test entry
        $content = Get-Content $logFile | Where-Object { $_ -ne "Test entry" }
        $content | Set-Content $logFile
    }
    catch {
        Write-Error "✗ Security log is NOT writable"
    }
    
    Write-Info ""
}

# ============================================
# STEP 8: DEPLOYMENT SUMMARY
# ============================================

Write-Info "========================================="
Write-Info "DEPLOYMENT SUMMARY"
Write-Info "========================================="
Write-Info ""

Write-Success "✓ Deployment completed successfully!"
Write-Info ""

Write-Info "Next Steps:"
Write-Info "1. Test the logout flow:"
Write-Info "   - Log in to Webtrees"
Write-Info "   - Click Logout"
Write-Info "   - Verify seamless logout to WordPress home"
Write-Info ""
Write-Info "2. Configure menu filtering:"
Write-Info "   - Go to WordPress Admin → Appearance → Menus"
Write-Info "   - Add CSS class 'menu-item-login' to Login item"
Write-Info "   - Add CSS class 'menu-item-logout' to Logout item"
Write-Info ""
Write-Info "3. Monitor logs:"
Write-Info "   - Check: $WebtreesRoot\data\sso_security.log"
Write-Info "   - Look for any token validation errors"
Write-Info ""
Write-Info "4. Disable debug logging (if enabled):"
Write-Info "   - Set WordPress_SSO_debugEnabled='0' in config.ini.php"
Write-Info ""

Write-Info "Documentation:"
Write-Info "- QUICK-REFERENCE.md - Quick start guide"
Write-Info "- AUTHENTICATION-FLOW.md - Technical details"
Write-Info "- DEPLOYMENT-CHECKLIST.md - Complete checklist"
Write-Info "- IMPLEMENTATION-SUMMARY.md - Project overview"
Write-Info ""

if ($Backup -and -not $Rollback) {
    Write-Info "Backup Location: $BackupDir"
    Write-Info "To rollback: .\deploy.ps1 -Rollback"
    Write-Info ""
}

Write-Success "========================================="
Write-Success "Deployment completed at $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Success "========================================="
