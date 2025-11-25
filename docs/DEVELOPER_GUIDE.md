# Developer Guide

Complete guide for developers contributing to or maintaining the B2Brouter WooCommerce plugin.

## Table of Contents

- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Project Structure](#project-structure)
- [Development Workflow](#development-workflow)
- [SDK Management](#sdk-management)
- [Testing](#testing)
- [Release Process](#release-process)
- [GitHub Workflow](#github-workflow)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

---

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- Composer
- Git
- WordPress 5.8+
- WooCommerce 5.0+
- B2Brouter API key (for testing)

### Quick Start

```bash
# Clone repository
git clone https://github.com/B2Brouter/b2brouter-woocommerce.git
cd b2brouter-woocommerce

# Install dependencies
composer install

# Link to WordPress (or see full setup guide)
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/b2brouter-woocommerce
```

---

## Development Setup

For complete local WordPress environment setup, see **[LOCAL_DEVELOPMENT_SETUP.md](LOCAL_DEVELOPMENT_SETUP.md)**.

This includes:
- PHP built-in server setup
- Nginx + PHP-FPM setup
- WordPress installation
- WooCommerce configuration
- Plugin installation and testing

---

## Project Structure

```
b2brouter-woocommerce/
├── .github/
│   └── workflows/
│       └── release.yml          # Automated release workflow
├── assets/
│   ├── css/
│   │   └── admin.css            # Admin styles
│   └── js/
│       └── admin.js             # Admin JavaScript
├── docs/
│   ├── LOCAL_DEVELOPMENT_SETUP.md  # Local dev environment setup
│   └── DEVELOPER_GUIDE.md          # This file
├── includes/
│   ├── Admin.php                # Admin interface & AJAX
│   ├── Invoice_Generator.php   # Invoice generation logic
│   ├── Order_Handler.php        # WooCommerce integration
│   └── Settings.php             # Settings & API key management
├── tests/
│   ├── AdminTest.php
│   ├── InvoiceGeneratorTest.php
│   ├── OrderHandlerTest.php
│   ├── SettingsTest.php
│   └── bootstrap.php
├── vendor/                      # Composer dependencies (gitignored)
├── b2brouter-woocommerce.php    # Main plugin file
├── build-release.sh             # Build distribution package
├── composer.json                # Composer configuration
├── phpunit.xml                  # PHPUnit configuration
├── DISTRIBUTION.md              # Distribution guide
├── LICENSE                      # MIT License
└── README.md                    # User-facing documentation
```

### Key Files

| File | Purpose |
|------|---------|
| `b2brouter-woocommerce.php` | Plugin bootstrap, dependency injection container |
| `includes/Settings.php` | API key storage, environment config, validation |
| `includes/Invoice_Generator.php` | Invoice data preparation, SDK calls |
| `includes/Order_Handler.php` | WooCommerce hooks, order integration |
| `includes/Admin.php` | Settings pages, AJAX handlers, admin UI |
| `build-release.sh` | Creates distribution ZIP with vendor/ |
| `.github/workflows/release.yml` | GitHub Actions automated releases |

---

## Development Workflow

### 1. Create Feature Branch

```bash
# Always branch from main
git checkout main
git pull origin main

# Create feature branch
git checkout -b feature/add-credit-notes
```

### 2. Make Changes

```bash
# Edit files
vim includes/Invoice_Generator.php

# Test changes locally
# See LOCAL_DEVELOPMENT_SETUP.md for testing setup
```

### 3. Run Tests

```bash
# Run all tests
composer test
# or
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/InvoiceGeneratorTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### 4. Commit Changes

```bash
# Stage changes
git add includes/Invoice_Generator.php tests/InvoiceGeneratorTest.php

# Commit with descriptive message
git commit -m "Add support for credit notes in invoice generator"
```

### 5. Push and Create Pull Request

```bash
# Push feature branch
git push origin feature/add-credit-notes

# Create PR on GitHub
# - Go to repository on GitHub
# - Click "Compare & pull request"
# - Fill in description
# - Request review
```

### 6. Merge and Clean Up

```bash
# After PR is merged
git checkout main
git pull origin main

# Delete feature branch
git branch -d feature/add-credit-notes
git push origin --delete feature/add-credit-notes
```

---

## SDK Management

The plugin uses the official **B2Brouter PHP SDK** from Packagist.

### Current Configuration

```json
{
    "require": {
        "b2brouter/b2brouter-php": "^0.9.0"
    }
}
```

**This means:**
- ✅ Accepts: `0.9.0`, `0.9.1`, `0.9.2`, `0.10.0`
- ❌ Rejects: `1.0.0` (major version change)

### Check SDK Version

```bash
# Show installed version
composer show b2brouter/b2brouter-php

# Output:
# name     : b2brouter/b2brouter-php
# versions : * v0.9.0
# source   : [git] https://github.com/B2Brouter/b2brouter-php.git
```

### Update SDK

```bash
# Update to latest compatible version
composer update b2brouter/b2brouter-php

# Check for available updates
composer outdated b2brouter/b2brouter-php

# Update to specific version
composer require b2brouter/b2brouter-php:^0.10.0
```

### SDK Breaking Changes

When SDK releases v1.0.0 (major version):

1. **Review changelog:**
   - Check GitHub releases: https://github.com/B2Brouter/b2brouter-php/releases
   - Look for breaking changes

2. **Test compatibility:**
   ```bash
   # Install new version in test environment
   composer require b2brouter/b2brouter-php:^1.0.0

   # Run tests
   composer test
   ```

3. **Update plugin code** if needed

4. **Update version constraint:**
   ```json
   "b2brouter/b2brouter-php": "^1.0.0"
   ```

### SDK Resources

- **Packagist:** https://packagist.org/packages/b2brouter/b2brouter-php
- **GitHub:** https://github.com/B2Brouter/b2brouter-php
- **Documentation:** Check SDK repository README

---

## Testing

### Unit Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/SettingsTest.php

# Run specific test method
./vendor/bin/phpunit --filter test_validate_api_key

# With coverage report
./vendor/bin/phpunit --coverage-html coverage/
# Open coverage/index.html in browser
```

### Integration Tests

Test with real WordPress/WooCommerce:

1. **Set up local environment** (see [LOCAL_DEVELOPMENT_SETUP.md](LOCAL_DEVELOPMENT_SETUP.md))

2. **Configure API key:**
   ```bash
   # Use staging environment
   export B2BROUTER_API_KEY="your-test-key"
   export B2BROUTER_ENVIRONMENT="staging"
   ```

3. **Create test order:**
   - WooCommerce → Orders → Add New
   - Add customer billing details
   - Add products
   - Complete order

4. **Test invoice generation:**
   - Manual: Click "Generate Invoice" button
   - Automatic: Set mode to automatic and complete order

5. **Verify in B2Brouter:**
   - Go to https://app-staging.b2brouter.net
   - Check invoice was created

### Manual Testing Checklist

Before releasing:

- [ ] Plugin activates without errors
- [ ] Settings page loads correctly
- [ ] API key validation works (both valid/invalid)
- [ ] Environment switching works (staging/production)
- [ ] Manual invoice generation works
- [ ] Automatic invoice generation works
- [ ] Bulk invoice generation works
- [ ] Invoice column shows in orders list
- [ ] Invoice meta box shows in order details
- [ ] Admin bar counter works
- [ ] No PHP errors in debug log
- [ ] No JavaScript console errors

---

## Release Process

### Overview

Two methods for creating releases:

| Method | Automation | Use Case |
|--------|-----------|----------|
| **GitHub Actions** | Fully automated | Production releases |
| **Local Build** | Semi-automated | Testing, manual releases |

Both create a ZIP with `vendor/` included (no Composer needed for users).

### Method 1: Automated GitHub Actions (Recommended)

Triggers automatically when you push a version tag.

#### Step 1: Update Version

```bash
# Edit plugin file
vim b2brouter-woocommerce.php
```

Update two lines:
```php
// Line 6
* Version: 1.1.0

// Line 27
define('B2BROUTER_WC_VERSION', '1.1.0');
```

#### Step 2: Commit and Push

```bash
git add b2brouter-woocommerce.php
git commit -m "Bump version to 1.1.0"
git push origin main
```

#### Step 3: Create and Push Tag

```bash
# Create annotated tag
git tag -a v1.1.0 -m "Release version 1.1.0"

# Push tag (triggers GitHub Actions)
git push origin v1.1.0
```

#### Step 4: Monitor GitHub Actions

1. Go to GitHub → **Actions** tab
2. Watch "Build and Release" workflow
3. Build completes in ~2-3 minutes

**What it does:**
- ✅ Verifies version matches tag
- ✅ Installs production dependencies
- ✅ Creates distribution ZIP with `vendor/`
- ✅ Generates SHA256 checksum
- ✅ Creates GitHub Release
- ✅ Uploads ZIP file
- ✅ Generates release notes

#### Step 5: Verify Release

1. Go to GitHub → **Releases**
2. Download `b2brouter-woocommerce-1.1.0.zip`
3. Test in clean WordPress install

### Method 2: Manual Local Build

For testing before official release.

#### Step 1: Build Locally

```bash
# Run build script
./build-release.sh

# Output: dist/b2brouter-woocommerce-1.1.0.zip
```

**What it does:**
- Extracts version from plugin file
- Creates clean build directory
- Copies plugin files
- Runs `composer install --no-dev --optimize-autoloader`
- Includes `vendor/` directory
- Creates ZIP archive
- Verifies SDK is included

#### Step 2: Test Distribution

```bash
# Verify ZIP contents
unzip -l dist/b2brouter-woocommerce-1.1.0.zip | grep vendor/b2brouter

# Extract and test
unzip dist/b2brouter-woocommerce-1.1.0.zip -d /tmp/test
# ... test in WordPress ...
```

#### Step 3: Create Release

If local testing passes:

```bash
# Commit and tag
git add b2brouter-woocommerce.php
git commit -m "Release version 1.1.0"
git tag -a v1.1.0 -m "Release 1.1.0"
git push origin main v1.1.0

# Option A: Let GitHub Actions rebuild (recommended)
# - Ensures consistent build environment

# Option B: Create manual GitHub Release
# - Upload your local ZIP
# - Write release notes
```

### Version Management

Follow **Semantic Versioning** (https://semver.org/):

- **Major (1.0.0 → 2.0.0):** Breaking changes
- **Minor (1.0.0 → 1.1.0):** New features, backward compatible
- **Patch (1.0.0 → 1.0.1):** Bug fixes, backward compatible

**Required updates per release:**

1. Plugin header (line 6): `Version: 1.1.0`
2. Version constant (line 27): `define('B2BROUTER_WC_VERSION', '1.1.0');`
3. Git tag: `v1.1.0`
4. Optional: `CHANGELOG.md`

### Pre-release Versions

For beta/RC releases:

```bash
# Update version in plugin file
Version: 1.1.0-beta.1

# Create tag
git tag -a v1.1.0-beta.1 -m "Beta release 1.1.0-beta.1"
git push origin v1.1.0-beta.1
```

**Note:** GitHub Actions workflow only triggers on stable versions (`v*.*.*`). Modify workflow for pre-releases if needed.

---

## GitHub Workflow

### GitHub Actions Configuration

File: `.github/workflows/release.yml`

**Triggers on:**
- Version tags: `v1.0.0`, `v1.2.3`, etc.

**Build steps:**
1. Checkout code
2. Setup PHP 7.4
3. Validate composer.json
4. Verify version matches tag
5. Install Composer dependencies (production only)
6. Verify B2Brouter SDK installed
7. Create distribution directory
8. Copy plugin files
9. Create release ZIP
10. Verify vendor/ in ZIP
11. Generate SHA256 checksum
12. Create release notes
13. Create GitHub Release
14. Upload artifacts

**Outputs:**
- `b2brouter-woocommerce-X.Y.Z.zip` - Distribution package
- `b2brouter-woocommerce-X.Y.Z.zip.sha256` - Checksum
- Release notes with installation instructions

### Branch Protection

Recommended settings for `main` branch:

- ✅ Require pull request before merging
- ✅ Require status checks to pass (if CI configured)
- ✅ Require conversation resolution before merging
- ❌ Don't allow force pushes
- ❌ Don't allow deletions

### Pull Request Workflow

1. **Create PR** from feature branch
2. **Review code** - Check for:
   - Code quality
   - Security issues
   - Tests coverage
   - Documentation updates
3. **Run tests** - Verify all pass
4. **Merge** to main when approved
5. **Delete branch** after merge

---

## Best Practices

### Code Style

Follow **WordPress Coding Standards**:

```bash
# Check code style (if phpcs installed)
./vendor/bin/phpcs --standard=WordPress includes/

# Auto-fix issues
./vendor/bin/phpcbf --standard=WordPress includes/
```

### Security

**Always:**
- ✅ Sanitize user input: `sanitize_text_field()`, `sanitize_email()`
- ✅ Escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ Verify nonces: `check_ajax_referer()`, `wp_verify_nonce()`
- ✅ Check capabilities: `current_user_can('manage_options')`
- ✅ Validate API responses before using
- ✅ Use prepared statements (WordPress handles this via WPDB)

**Never:**
- ❌ Trust user input directly
- ❌ Output unescaped data
- ❌ Skip capability checks on admin actions
- ❌ Store sensitive data unencrypted
- ❌ Expose API keys in client-side code

### WordPress Hooks

**Use WordPress hooks properly:**

```php
// Good: Use WordPress hooks
add_action('woocommerce_order_status_completed', array($this, 'generate_invoice'));

// Good: Remove hooks properly
remove_action('woocommerce_order_status_completed', array($this, 'generate_invoice'));

// Avoid: Direct function calls when hooks are available
```

### Error Handling

```php
// Good: Try-catch with user-friendly messages
try {
    $invoice = $this->generate_invoice($order_id);
} catch (\Exception $e) {
    error_log('Invoice generation failed: ' . $e->getMessage());
    return array(
        'success' => false,
        'message' => __('Failed to generate invoice. Please try again.', 'b2brouter-woocommerce')
    );
}

// Good: Add order notes for traceability
$order->add_order_note('Invoice generation failed: ' . $e->getMessage());
```

### Translation

**Make all strings translatable:**

```php
// Good: Translatable
__('Invoice generated successfully', 'b2brouter-woocommerce');
_e('Settings saved', 'b2brouter-woocommerce');
_n('%d invoice', '%d invoices', $count, 'b2brouter-woocommerce');

// Bad: Hardcoded
echo 'Invoice generated';
```

### Documentation

**Document all functions:**

```php
/**
 * Generate invoice from WooCommerce order
 *
 * @since 1.0.0
 * @param int $order_id The WooCommerce order ID
 * @return array{success: bool, invoice_id?: string, message: string} Generation result
 * @throws \Exception If API key is not configured
 */
public function generate_invoice($order_id) {
    // ...
}
```

---

## Troubleshooting

### Common Development Issues

#### Vendor Directory Not Found

**Problem:** `Class 'B2BRouter\B2BRouterClient' not found`

**Solution:**
```bash
# Install dependencies
composer install

# Verify SDK installed
composer show b2brouter/b2brouter-php

# Verify autoloader
php -r "require 'vendor/autoload.php'; echo 'OK\n';"
```

#### Plugin Not Showing in WordPress

**Problem:** Plugin doesn't appear in WordPress admin

**Solution:**
```bash
# Check symbolic link
ls -la /path/to/wordpress/wp-content/plugins/b2brouter-woocommerce

# Check main plugin file exists
cat b2brouter-woocommerce.php | head -20

# Check for PHP errors
tail -f /path/to/wordpress/wp-content/debug.log
```

#### Tests Failing

**Problem:** PHPUnit tests fail

**Solution:**
```bash
# Reinstall dev dependencies
composer install

# Check PHPUnit version
./vendor/bin/phpunit --version

# Run with verbose output
./vendor/bin/phpunit --verbose

# Check specific test
./vendor/bin/phpunit --filter test_specific_method
```

#### Build Script Fails

**Problem:** `./build-release.sh` errors

**Solution:**
```bash
# Check Composer is installed
composer --version

# Make script executable
chmod +x build-release.sh

# Check for syntax errors
bash -n build-release.sh

# Run with debug output
bash -x build-release.sh
```

#### SDK Update Issues

**Problem:** Can't update SDK

**Solution:**
```bash
# Clear Composer cache
composer clear-cache

# Remove vendor and reinstall
rm -rf vendor/ composer.lock
composer install

# Check for conflicts
composer why-not b2brouter/b2brouter-php 0.10.0
```

### GitHub Actions Issues

#### Workflow Doesn't Trigger

**Problem:** Tag pushed but no workflow runs

**Solution:**
```bash
# Check tag format (must be vX.Y.Z)
git tag -l

# Check workflow file exists
cat .github/workflows/release.yml

# Push tag explicitly
git push origin v1.1.0

# Check Actions tab for errors
```

#### Version Mismatch Error

**Problem:** Build fails with version mismatch

**Solution:**
```bash
# Ensure versions match
grep "Version:" b2brouter-woocommerce.php
grep "B2BROUTER_WC_VERSION" b2brouter-woocommerce.php
git describe --tags

# Fix and re-tag
vim b2brouter-woocommerce.php
git add b2brouter-woocommerce.php
git commit --amend
git tag -d v1.1.0
git push origin :refs/tags/v1.1.0
git tag -a v1.1.0 -m "Release 1.1.0"
git push origin main v1.1.0
```

---

## Resources

### Documentation

- **WordPress Plugin Handbook:** https://developer.wordpress.org/plugins/
- **WooCommerce Development:** https://woocommerce.github.io/code-reference/
- **WordPress Coding Standards:** https://developer.wordpress.org/coding-standards/
- **B2Brouter API:** https://developer.b2brouter.net
- **B2Brouter PHP SDK:** https://github.com/B2Brouter/b2brouter-php

### Tools

- **Composer:** https://getcomposer.org/
- **PHPUnit:** https://phpunit.de/
- **GitHub Actions:** https://docs.github.com/en/actions
- **Semantic Versioning:** https://semver.org/

### Internal Docs

- **[LOCAL_DEVELOPMENT_SETUP.md](LOCAL_DEVELOPMENT_SETUP.md)** - WordPress environment setup
- **[DISTRIBUTION.md](../DISTRIBUTION.md)** - Distribution and packaging guide
- **[README.md](../README.md)** - User-facing documentation

---

## Quick Reference

### Daily Development

```bash
# Pull latest changes
git checkout main && git pull origin main

# Create feature branch
git checkout -b feature/my-feature

# Install/update dependencies
composer install

# Run tests
composer test

# Commit changes
git add . && git commit -m "Description"
git push origin feature/my-feature
```

### Creating Release

```bash
# Update version
vim b2brouter-woocommerce.php

# Commit and tag
git add b2brouter-woocommerce.php
git commit -m "Bump version to X.Y.Z"
git push origin main
git tag -a vX.Y.Z -m "Release X.Y.Z"
git push origin vX.Y.Z

# GitHub Actions handles the rest!
```

### Testing

```bash
# Unit tests
./vendor/bin/phpunit

# Specific test
./vendor/bin/phpunit tests/SettingsTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/

# Build and test distribution
./build-release.sh
unzip -t dist/b2brouter-woocommerce-*.zip
```

---

## IDE Setup

For proper autocomplete and type hints, install WordPress and WooCommerce stubs:

```bash
composer require --dev php-stubs/wordpress-stubs php-stubs/woocommerce-stubs
```

Configuration file `.phpactor.json` is included for Neovim/PhpStorm/VSCode.

See [IDE_SETUP.md](../IDE_SETUP.md) for detailed configuration instructions.

---

## Hooks and Extension Points

### Actions

```php
// Triggered after successful invoice generation
do_action('b2brouter_invoice_generated', $order_id, $invoice_data);

// Triggered after PDF is saved
do_action('b2brouter_pdf_saved', $order_id, $pdf_path);
```

### Filters

```php
// Modify invoice data before API submission
apply_filters('b2brouter_invoice_data', $invoice_data, $order);

// Customize invoice number format
apply_filters('b2brouter_invoice_number', $invoice_number, $order);

// Modify PDF storage path
apply_filters('b2brouter_pdf_storage_path', $path, $order_id);
```

---

**Questions?** Check the troubleshooting section or open an issue on GitHub.
