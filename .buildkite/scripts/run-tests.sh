#!/bin/bash
# TaxJar WooCommerce Plugin - Test Execution Script
# Runs inside WordPress Docker container

set -euo pipefail

echo "--- :package: Setting up test environment"

# Environment variables expected:
# - WC_VERSION: WooCommerce version to test
# - WP_VERSION: WordPress version (for test library)
# - TAXJAR_API_TOKEN: API token (optional)

WC_VERSION="${WC_VERSION:-9.3.3}"
WP_VERSION="${WP_VERSION:-6.4}"
WP_TESTS_DIR="/tmp/wordpress-tests-lib"

echo "Testing with:"
echo "  WooCommerce: ${WC_VERSION}"
echo "  WordPress: ${WP_VERSION}"
echo "  PHP: $(php -v | head -n 1)"

# =============================================================================
# Install System Dependencies
# =============================================================================
echo "--- :package: Installing system dependencies"
apt-get update -qq
apt-get install -y -qq \
  subversion \
  git \
  unzip \
  wget \
  curl \
  default-mysql-client \
  > /dev/null

# Configure git safe directory
git config --global --add safe.directory /var/www/html/wp-content/plugins/taxjar-woocommerce-plugin

# =============================================================================
# Install WordPress Test Library
# =============================================================================
echo "--- :wordpress: Installing WordPress test library"

# Create test library directory
mkdir -p "${WP_TESTS_DIR}"

# Download WordPress test library (version-specific)
if [[ "${WP_VERSION}" == "latest" ]]; then
  WP_BRANCH="trunk"
else
  WP_BRANCH="${WP_VERSION}"
fi

echo "Downloading WordPress ${WP_BRANCH} test library..."
svn co --quiet "https://develop.svn.wordpress.org/branches/${WP_BRANCH}/tests/phpunit/includes" "${WP_TESTS_DIR}/includes" || {
  echo "Failed to download from branches/${WP_BRANCH}, trying trunk..."
  svn co --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/includes" "${WP_TESTS_DIR}/includes"
}

svn co --quiet "https://develop.svn.wordpress.org/branches/${WP_BRANCH}/tests/phpunit/data" "${WP_TESTS_DIR}/data" 2>/dev/null || \
  svn co --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/data" "${WP_TESTS_DIR}/data"

# Create wp-tests-config.php
cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<'EOF'
<?php
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

$table_prefix = 'wptests_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/var/www/html/' );
}
EOF

echo "✅ WordPress test library installed"

# =============================================================================
# Download and Install WooCommerce
# =============================================================================
echo "--- :shopping_cart: Installing WooCommerce ${WC_VERSION}"

cd /var/www/html/wp-content/plugins/

# Download WooCommerce from WordPress.org
if [[ "${WC_VERSION}" == "latest" ]]; then
  echo "Downloading latest WooCommerce..."
  svn co --quiet "https://plugins.svn.wordpress.org/woocommerce/trunk" woocommerce
else
  echo "Downloading WooCommerce ${WC_VERSION}..."
  svn co --quiet "https://plugins.svn.wordpress.org/woocommerce/tags/${WC_VERSION}" woocommerce
fi

if [ ! -d "woocommerce" ]; then
  echo "❌ Failed to download WooCommerce"
  exit 1
fi

echo "✅ WooCommerce ${WC_VERSION} installed"

# =============================================================================
# Create Plugin Symlink for Test Bootstrap Compatibility
# =============================================================================
echo "--- :link: Creating plugin symlink"

# The test bootstrap expects plugin directory to match WordPress.org slug
# Repository: taxjar-woocommerce-plugin
# WordPress.org: taxjar-simplified-taxes-for-woocommerce

if [ ! -L "taxjar-simplified-taxes-for-woocommerce" ]; then
  ln -s taxjar-woocommerce-plugin taxjar-simplified-taxes-for-woocommerce
  echo "✅ Created symlink: taxjar-simplified-taxes-for-woocommerce -> taxjar-woocommerce-plugin"
fi

# =============================================================================
# Install Composer Dependencies
# =============================================================================
echo "--- :php: Installing Composer dependencies"

cd /var/www/html/wp-content/plugins/taxjar-woocommerce-plugin

# Install Composer if not present
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --quiet
  mv composer.phar /usr/local/bin/composer
fi

# Allow Composer plugins required by PHPCS
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

composer install --no-interaction --prefer-dist --no-progress --quiet

echo "✅ Composer dependencies installed"

# =============================================================================
# Run PHPUnit Tests
# =============================================================================
echo "--- :test_tube: Running PHPUnit tests"

# Set environment variables for tests
export WP_TESTS_DIR="${WP_TESTS_DIR}"
export WP_CORE_DIR="/var/www/html"

if [ -n "${TAXJAR_API_TOKEN:-}" ]; then
  echo "✅ TAXJAR_API_TOKEN is set"
  export TAXJAR_API_TOKEN
else
  echo "⚠️  TAXJAR_API_TOKEN not set - some tests may fail"
fi

# Run PHPUnit with JUnit XML output
cd tests
../vendor/bin/phpunit \
  --configuration phpunit.xml \
  --log-junit /test-results/junit-wc-${WC_VERSION}.xml \
  --colors=always \
  || TEST_EXIT_CODE=$?

# Store exit code
EXIT_CODE=${TEST_EXIT_CODE:-0}

echo ""
echo "--- :bar_chart: Test Results Summary"
if [ ${EXIT_CODE} -eq 0 ]; then
  echo "✅ All tests passed for WooCommerce ${WC_VERSION}"
else
  echo "❌ Tests failed for WooCommerce ${WC_VERSION} (exit code: ${EXIT_CODE})"
fi

exit ${EXIT_CODE}
