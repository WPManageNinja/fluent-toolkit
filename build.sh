#!/usr/bin/env bash
set -e

PLUGIN_SLUG="fluent-toolkit"
VERSION=$(sed -n "s/define('FLUENT_TOOLKIT_VERSION', '\([^']*\)');/\1/p" fluent-toolkit.php)

if [ -z "${VERSION}" ]; then
    echo "Error: could not read FLUENT_TOOLKIT_VERSION from fluent-toolkit.php" >&2
    exit 1
fi

STABLE_TAG=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | tr -d '[:space:]')
if [ "${STABLE_TAG}" != "${VERSION}" ]; then
    echo "Error: readme.txt Stable tag (${STABLE_TAG}) does not match plugin version (${VERSION})" >&2
    exit 1
fi

BUILD_DIR="builds/${PLUGIN_SLUG}"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous build + compiled assets (mix never prunes dist,
# so deleted source assets would otherwise keep shipping)
rm -rf builds/* dist
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

cp -r includes "${BUILD_DIR}/includes"

# PHP modules + Composer dependencies
cp -r libs "${BUILD_DIR}/libs"

# Compiled assets + security index
cp -R dist "${BUILD_DIR}/dist"
cp index.php "${BUILD_DIR}/dist/index.php"

# Create zip
echo "Creating ${ZIP_NAME}..."
cd builds
zip -rq "${ZIP_NAME}" "${PLUGIN_SLUG}" -x "*.DS_Store"
rm -rf "${PLUGIN_SLUG}"
cd ..

echo "Done → builds/${ZIP_NAME}"
