#!/usr/bin/env bash
set -e

PLUGIN_SLUG="fluent-toolkit"
VERSION=$(sed -n "s/define('FLUENT_TOOLKIT_VERSION', '\([^']*\)');/\1/p" fluent-toolkit.php)
BUILD_DIR="builds/${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous build
rm -rf builds/*
mkdir -p "${BUILD_DIR}"

# Run asset build
echo "Compiling assets..."
npx mix --production

# Copy root plugin files
echo "Copying files..."
cp fluent-toolkit.php "${BUILD_DIR}/"
cp readme.txt         "${BUILD_DIR}/"
cp index.php          "${BUILD_DIR}/"

# Whole Classes dir (index.php included)
cp -r Classes "${BUILD_DIR}/Classes"

# PHP modules + Composer dependencies
cp -r libs "${BUILD_DIR}/libs"

# Compiled assets + security index
mkdir -p "${BUILD_DIR}/dist"
cp dist/app.js "${BUILD_DIR}/dist/"
[ -f dist/app.css ] && cp dist/app.css "${BUILD_DIR}/dist/"
cp index.php "${BUILD_DIR}/dist/index.php"

# Create zip
echo "Creating ${ZIP_NAME}..."
cd builds
zip -rq "${ZIP_NAME}" "${PLUGIN_SLUG}"
rm -rf "${PLUGIN_SLUG}"
cd ..

echo "Done → builds/${ZIP_NAME}"
