# Distribution & Release Process

This document outlines the automated release process for B2Brouter for WooCommerce.

## Prerequisites

- Write access to the repository
- Clean working directory (`git status` shows no uncommitted changes)
- All tests passing (`composer test`)
- Version numbers updated in:
  - `b2brouter-woocommerce.php` (header `Version:` and constant `B2BROUTER_WC_VERSION`)
  - `README.md` (version badge)
  - `CHANGELOG.md` (new version entry)

## Automated Release Process

### 1. Update Version Numbers

```bash
# Example: Releasing v1.2.3
vim b2brouter-woocommerce.php  # Update Version: 1.2.3 and B2BROUTER_WC_VERSION
vim README.md                   # Update version badge
vim CHANGELOG.md               # Add new version section
```

### 2. Run Tests

```bash
composer test
```

Ensure all tests pass before proceeding.

### 3. Commit Changes

```bash
git add b2brouter-woocommerce.php README.md CHANGELOG.md
git commit -m "Bump version to 1.2.3"
git push origin main
```

### 4. Create and Push Tag

```bash
# Create annotated tag
git tag -a v1.2.3 -m "Release v1.2.3"

# Push tag to trigger GitHub Actions workflow
git push origin v1.2.3
```

### 5. Automated Workflow Execution

The `.github/workflows/release.yml` workflow automatically:

1. **Validates** version consistency between git tag and plugin file
2. **Installs** production dependencies via Composer (`--no-dev --optimize-autoloader`)
3. **Verifies** B2Brouter PHP SDK is installed
4. **Packages** plugin files into ZIP:
   - Main plugin file and includes/
   - Assets (CSS, JS)
   - Vendor dependencies (including B2Brouter SDK)
   - Documentation (README, CHANGELOG, LICENSE)
5. **Generates** SHA256 checksum for security verification
6. **Creates** GitHub Release with:
   - Release notes
   - Distribution ZIP file
   - SHA256 checksum file
7. **Uploads** build artifacts (retained for 90 days)

### 6. Verify Release

1. Navigate to [Releases](https://github.com/B2Brouter/b2brouter-woocommerce/releases)
2. Verify the new release is published
3. Download and test the ZIP file on a WordPress test instance

## Release Workflow Details

### Trigger Condition

```yaml
on:
  push:
    tags:
      - 'v*.*.*'  # Matches v1.0.0, v1.2.3, etc.
```

### Version Validation

The workflow fails if the git tag version doesn't match the plugin file version:

```bash
# Tag: v1.2.3
# Plugin file must contain: Version: 1.2.3
```

This prevents version mismatches in releases.

### Build Output

- **ZIP File**: `b2brouter-woocommerce-{VERSION}.zip`
- **Checksum**: `b2brouter-woocommerce-{VERSION}.zip.sha256`
- **Location**: GitHub Release assets

### Release Notes

Auto-generated release notes include:
- Installation instructions
- Requirements (WordPress, WooCommerce, PHP versions)
- What's included (plugin version, SDK version, dependencies)
- SHA256 checksum for verification
- Support links

## Manual Build Script

### Purpose

The `build-release.sh` script provides local build capabilities for:

- **Pre-release testing**: Test the distribution package before pushing tags
- **Local validation**: Verify ZIP structure and contents locally
- **Development workflow**: Quick builds during development without CI/CD
- **Offline builds**: Create distribution packages without GitHub Actions
- **Troubleshooting**: Debug packaging issues locally before release

### Usage

```bash
./build-release.sh
```

### Output

```
dist/b2brouter-woocommerce-{VERSION}.zip
```

The script automatically extracts the version from `b2brouter-woocommerce.php`.

### Build Process

1. Cleans previous builds (`build/` and `dist/`)
2. Creates release directory structure
3. Copies plugin files (includes/, assets/, main plugin file, docs)
4. Runs `composer install --no-dev --optimize-autoloader`
5. Verifies B2Brouter PHP SDK is present in vendor/
6. Creates ZIP archive
7. Validates vendor dependencies in ZIP
8. Displays build summary (version, file size, contents)

### Verification

After running the build script:

```bash
# Check ZIP contents
unzip -l dist/b2brouter-woocommerce-{VERSION}.zip

# Verify B2Brouter SDK
unzip -l dist/b2brouter-woocommerce-{VERSION}.zip | grep "vendor/b2brouter/b2brouter-php"

# Test installation on local WordPress
# Upload ZIP via WordPress Admin → Plugins → Add New → Upload Plugin
```

Expected ZIP structure:

```
b2brouter-woocommerce/
├── b2brouter-woocommerce.php
├── includes/
├── assets/
├── vendor/
│   └── b2brouter/
│       └── b2brouter-php/
├── composer.json
├── LICENSE
├── README.md
└── CHANGELOG.md
```

## Version Strategy

- **0.x.x**: Beta/pre-release versions (current: 0.9.0)
- **1.0.0**: First stable release
- **1.x.x**: Feature additions (minor versions)
- **x.x.1**: Bug fixes (patch versions)
- **2.0.0**: Breaking changes (major versions)
