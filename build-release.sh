#!/bin/bash

#######################################
# B2Brouter WooCommerce Plugin
# Release Build Script
#
# This script creates a distribution-ready
# ZIP file with vendor dependencies included.
#######################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}B2Brouter WooCommerce - Release Builder${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer is not installed.${NC}"
    echo "Please install Composer from https://getcomposer.org/"
    exit 1
fi

# Get version from main plugin file
VERSION=$(grep -E "^\s*\*\s*Version:" b2brouter-for-woocommerce.php | awk '{print $3}')
if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from plugin file.${NC}"
    exit 1
fi

echo -e "${YELLOW}Building version: ${VERSION}${NC}"
echo ""

# Define paths
BUILD_DIR="build"
DIST_DIR="dist"
PLUGIN_NAME="b2brouter-for-woocommerce"
RELEASE_DIR="${BUILD_DIR}/${PLUGIN_NAME}"
ARCHIVE_NAME="${PLUGIN_NAME}-${VERSION}.zip"

# Clean previous builds
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}/${ARCHIVE_NAME}"
mkdir -p "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

# Create release directory
mkdir -p "${RELEASE_DIR}"

echo -e "${GREEN}✓ Build directories created${NC}"
echo ""

# Copy plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"

# Required files/directories that must end up in the release
REQUIRED_DIRS=(includes assets languages)
REQUIRED_FILES=(
    b2brouter-for-woocommerce.php
    uninstall.php
    readme.txt
    composer.json
    LICENSE
    README.md
    CHANGELOG.md
)

for dir in "${REQUIRED_DIRS[@]}"; do
    cp -r "${dir}" "${RELEASE_DIR}/"
done
for file in "${REQUIRED_FILES[@]}"; do
    cp "${file}" "${RELEASE_DIR}/"
done

# Copy documentation
if [ -d "docs" ]; then
    mkdir -p "${RELEASE_DIR}/docs"
    cp docs/*.md "${RELEASE_DIR}/docs/" 2>/dev/null || true
fi

# Fail fast if any required file is missing from the staging dir
for dir in "${REQUIRED_DIRS[@]}"; do
    if [ ! -d "${RELEASE_DIR}/${dir}" ]; then
        echo -e "${RED}Error: required directory missing from release: ${dir}${NC}"
        exit 1
    fi
done
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "${RELEASE_DIR}/${file}" ]; then
        echo -e "${RED}Error: required file missing from release: ${file}${NC}"
        exit 1
    fi
done

echo -e "${GREEN}✓ Plugin files copied${NC}"
echo ""

# Install production dependencies
echo -e "${YELLOW}Installing Composer dependencies (production only)...${NC}"
cd "${RELEASE_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Composer install failed.${NC}"
    exit 1
fi

# Remove composer files (optional, keep for transparency)
# rm composer.json composer.lock

cd ../../

echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""

# Verify vendor directory exists
if [ ! -d "${RELEASE_DIR}/vendor" ]; then
    echo -e "${RED}Error: vendor/ directory not found after composer install.${NC}"
    exit 1
fi

# Check SDK is installed
if [ ! -d "${RELEASE_DIR}/vendor/b2brouter/b2brouter-php" ]; then
    echo -e "${RED}Error: B2Brouter PHP SDK not found in vendor/.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ B2Brouter PHP SDK verified${NC}"
echo ""

# Create ZIP archive
echo -e "${YELLOW}Creating ZIP archive...${NC}"
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ARCHIVE_NAME}" "${PLUGIN_NAME}" -q

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Failed to create ZIP archive.${NC}"
    exit 1
fi

cd ..

echo -e "${GREEN}✓ ZIP archive created${NC}"
echo ""

# Get file size
FILE_SIZE=$(du -h "${DIST_DIR}/${ARCHIVE_NAME}" | cut -f1)

# Print summary
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}Build Complete!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "Version:      ${GREEN}${VERSION}${NC}"
echo -e "Archive:      ${GREEN}${DIST_DIR}/${ARCHIVE_NAME}${NC}"
echo -e "Size:         ${GREEN}${FILE_SIZE}${NC}"
echo ""
echo -e "${YELLOW}Contents:${NC}"
echo -e "  - Plugin files"
echo -e "  - Vendor dependencies (including B2Brouter PHP SDK)"
echo -e "  - Documentation"
echo ""

# Verify archive contents — hard check that key entries are present
echo -e "${YELLOW}Verifying archive contents...${NC}"
ZIP_LIST=$(unzip -l "${DIST_DIR}/${ARCHIVE_NAME}")

ZIP_REQUIRED=(
    "${PLUGIN_NAME}/b2brouter-for-woocommerce.php"
    "${PLUGIN_NAME}/uninstall.php"
    "${PLUGIN_NAME}/readme.txt"
    "${PLUGIN_NAME}/vendor/b2brouter/b2brouter-php/"
)
for entry in "${ZIP_REQUIRED[@]}"; do
    if ! echo "${ZIP_LIST}" | grep -q "${entry}"; then
        echo -e "${RED}Error: required entry missing from ZIP: ${entry}${NC}"
        exit 1
    fi
done

echo -e "${GREEN}✓ Archive contents verified${NC}"
echo ""
echo -e "${GREEN}Ready for distribution!${NC}"
echo -e "${YELLOW}Upload ${DIST_DIR}/${ARCHIVE_NAME} to WordPress${NC}"
echo ""
