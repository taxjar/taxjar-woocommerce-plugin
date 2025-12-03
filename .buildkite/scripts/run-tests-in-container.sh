#!/bin/bash
set -euo pipefail

# TaxJar WooCommerce Plugin Test Runner
# Runs PHPUnit tests in a WordPress + WooCommerce environment
# This script runs INSIDE the WordPress container

echo "+++ :test_tube: TaxJar WooCommerce Plugin Test Runner"
echo "PHP Version: $(php -v | head -n1)"
echo "WordPress Version: ${WP_VERSION:-Not set}"
echo "WooCommerce Version: ${WC_VERSION:-Not set}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

echo "--- :package: Setting up WordPress environment"

# Copy WordPress files from /usr/src/wordpress if not already present
if [ ! -e /var/www/html/index.php ] || [ ! -e /var/www/html/wp-includes/version.php ]; then
    print_status "Copying WordPress files"
    cp -r /usr/src/wordpress/. /var/www/html/ > /dev/null 2>&1
    chown -R www-data:www-data /var/www/html/wp-admin 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/wp-includes 2>/dev/null || true
    chown www-data:www-data /var/www/html/*.php 2>/dev/null || true
    mkdir -p /var/www/html/wp-content
    chown www-data:www-data /var/www/html/wp-content 2>/dev/null || true
fi

# Start Apache in the background
print_status "Starting Apache web server"
service apache2 start > /dev/null 2>&1

# Install necessary tools
print_status "Installing system tools"
apt-get update -qq && apt-get install -qq -y unzip > /dev/null 2>&1

# Wait for WordPress to be ready
print_status "Waiting for WordPress to be ready"
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if curl --output /dev/null --silent --head --fail http://localhost/wp-admin/install.php; then
        print_status "WordPress is ready"
        break
    fi
    printf '.'
    sleep 2
    attempt=$((attempt + 1))
done

if [ $attempt -eq $max_attempts ]; then
    print_error "WordPress failed to start after $max_attempts attempts"
    exit 1
fi

echo "--- :wordpress: Installing and configuring WordPress + WooCommerce"

# Install WP-CLI if not present
if ! command -v wp &> /dev/null; then
    print_status "Installing WP-CLI 2.9.0"
    curl -sS -o /tmp/wp-cli.phar https://github.com/wp-cli/wp-cli/releases/download/v2.9.0/wp-cli-2.9.0.phar
    chmod +x /tmp/wp-cli.phar
    mv /tmp/wp-cli.phar /usr/local/bin/wp
fi

cd /var/www/html

# Install WordPress if needed
if wp core is-installed --allow-root 2>/dev/null; then
    print_status "WordPress already installed"
else
    print_status "Installing WordPress"
    wp core install \
        --url=http://localhost \
        --title="TaxJar Test Site" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=test@taxjar.com \
        --skip-email \
        --allow-root > /dev/null 2>&1
fi

# Install WooCommerce if needed
if [ -d "/var/www/html/wp-content/plugins/woocommerce" ]; then
    print_status "WooCommerce ${WC_VERSION:-latest} already installed"
else
    print_status "Downloading WooCommerce ${WC_VERSION:-latest}"
    if [ -n "${WC_VERSION:-}" ]; then
        WC_DOWNLOAD_URL="https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip"
    else
        WC_DOWNLOAD_URL="https://downloads.wordpress.org/plugin/woocommerce.zip"
    fi

    cd /var/www/html/wp-content/plugins
    curl -sS -o woocommerce.zip "$WC_DOWNLOAD_URL"
    unzip -q woocommerce.zip
    rm woocommerce.zip
    cd /var/www/html
fi

# Install WooCommerce Subscriptions Core if needed
#
# IMPORTANT: We use the ARCHIVED woocommerce-subscriptions-core v8.2.0 library
# (archived May 2025) instead of the full WooCommerce Subscriptions plugin because:
# - We lack access to the paid WooCommerce Subscriptions plugin
# - Testing with core library is better than no subscription testing in CI
# - This enables 16 subscription tests to run instead of being skipped
#
# TECHNICAL DEBT: This is a temporary solution. When WooCommerce Subscriptions
# plugin becomes available, replace this archived core library with the full plugin.
# The archived core provides the necessary WC_Product_Subscription class for tests
# but may not include all features of the full plugin.
if [ -d "/var/www/html/wp-content/plugins/woocommerce-subscriptions" ]; then
    print_status "WooCommerce Subscriptions Core already installed"
else
    print_status "Downloading WooCommerce Subscriptions Core"
    SUBS_VERSION="8.2.0"
    SUBS_DOWNLOAD_URL="https://api.github.com/repos/Automattic/woocommerce-subscriptions-core/zipball/${SUBS_VERSION}"

    cd /var/www/html/wp-content/plugins
    curl -sS -L -o woocommerce-subscriptions.zip "$SUBS_DOWNLOAD_URL"
    unzip -q woocommerce-subscriptions.zip
    # Rename the extracted directory to woocommerce-subscriptions
    mv Automattic-woocommerce-subscriptions-core-* woocommerce-subscriptions
    rm woocommerce-subscriptions.zip
    cd /var/www/html
fi

# Activate plugins
print_status "Activating WooCommerce"
wp plugin activate woocommerce --allow-root > /dev/null 2>&1 || true

print_status "Activating TaxJar plugin"
wp plugin activate taxjar-simplified-taxes-for-woocommerce --allow-root > /dev/null 2>&1 || true

print_status "Activating WooCommerce Subscriptions Core"
wp plugin activate woocommerce-subscriptions --allow-root > /dev/null 2>&1 || true

# Configure TaxJar if API token available
if [ -n "${TAXJAR_API_TOKEN:-}" ]; then
    print_status "Configuring TaxJar API token"
    wp option update woocommerce_taxjar-integration_settings \
        '{"api_token":"'${TAXJAR_API_TOKEN}'","enabled":"yes"}' \
        --format=json \
        --allow-root > /dev/null 2>&1
else
    print_warning "TAXJAR_API_TOKEN not set, skipping API configuration"
fi

# Create symlinks for test bootstrap compatibility
# The bootstrap expects WooCommerce and TaxJar plugin in specific relative paths
PLUGIN_DIR="/var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce"

# Create taxjar-woocommerce-plugin symlink at same level as main plugin
if [ ! -e "/var/www/html/wp-content/plugins/taxjar-woocommerce-plugin" ]; then
    ln -s "$PLUGIN_DIR" \
          /var/www/html/wp-content/plugins/taxjar-woocommerce-plugin
fi

# Create woocommerce symlink inside the plugin directory for bootstrap
if [ ! -e "$PLUGIN_DIR/woocommerce" ]; then
    ln -s /var/www/html/wp-content/plugins/woocommerce \
          "$PLUGIN_DIR/woocommerce"
fi

# Create woocommerce-subscriptions symlink inside the plugin directory for bootstrap
if [ ! -e "$PLUGIN_DIR/woocommerce-subscriptions" ]; then
    ln -s /var/www/html/wp-content/plugins/woocommerce-subscriptions \
          "$PLUGIN_DIR/woocommerce-subscriptions"
fi

cd "$PLUGIN_DIR"

echo "--- :package: Installing test dependencies"

mkdir -p /test-results

# Install Composer dependencies if needed
if [ ! -f "vendor/autoload.php" ]; then
    print_status "Installing Composer"
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1
    fi
    print_status "Installing PHP dependencies"
    composer install --no-interaction --prefer-dist --optimize-autoloader --quiet
fi

# Install PHPUnit if not present
if [ ! -f "vendor/bin/phpunit" ]; then
    print_status "Installing PHPUnit"
    composer require --dev phpunit/phpunit ^9.5 --no-interaction --quiet
fi

# Locate PHPUnit configuration
if [ -f "tests/phpunit.xml" ]; then
    PHPUNIT_CONFIG="tests/phpunit.xml"
elif [ -f "phpunit.xml" ]; then
    PHPUNIT_CONFIG="phpunit.xml"
else
    print_error "No phpunit.xml configuration found"
    exit 1
fi

echo "--- :hammer_and_wrench: Setting up WordPress test library"

export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/var/www/html

# Install WordPress test library if not present
if [ ! -d "$WP_TESTS_DIR" ]; then
    if ! command -v git &> /dev/null; then
        apt-get install -qq -y git > /dev/null 2>&1
    fi

    WP_TEST_BRANCH="${WP_VERSION:-6.4}"
    print_status "Cloning WordPress test library v${WP_TEST_BRANCH}"
    git clone --depth=1 --branch="$WP_TEST_BRANCH" https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop > /dev/null 2>&1

    mkdir -p /tmp/wordpress-tests-lib
    cp -r /tmp/wordpress-develop/tests/phpunit/includes /tmp/wordpress-tests-lib/includes
    cp -r /tmp/wordpress-develop/tests/phpunit/data /tmp/wordpress-tests-lib/data

    # Create wp-tests-config.php
    cat > $WP_TESTS_DIR/wp-tests-config.php << 'EOF'
<?php
define( 'ABSPATH', '/var/www/html/' );
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
EOF

    print_status "WordPress test library installed"
fi

echo "+++ :test_tube: Running PHPUnit tests"

# Ensure test results directory exists with proper permissions
mkdir -p /test-results
chmod 777 /test-results

vendor/bin/phpunit \
    --configuration "$PHPUNIT_CONFIG" \
    --log-junit /test-results/phpunit-results.xml \
    --testdox \
    --colors=always \
    2>&1 | tee /test-results/phpunit-output.log \
    || TEST_EXIT_CODE=$?

# Ensure XML file exists even if PHPUnit failed before creating it
if [ ! -f "/test-results/phpunit-results.xml" ]; then
    print_warning "PHPUnit results XML not found, creating empty test suite"
    cat > /test-results/phpunit-results.xml << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Empty" tests="0" assertions="0" failures="0" errors="0" skipped="0">
  </testsuite>
</testsuites>
EOF
fi

# Note: WordPress and Apache logs are captured by the post-command hook
# using docker-compose logs, which captures all stdout/stderr from the container.
# This provides more complete logging than trying to find specific log files.
print_status "WordPress logs will be captured by post-command hook"

# Parse and display test summary
echo ""
echo "+++ :bar_chart: Test Results Summary"

if [ -f "/test-results/phpunit-results.xml" ]; then
    TESTS=$(grep -o 'tests="[0-9]*"' /test-results/phpunit-results.xml | head -1 | grep -o '[0-9]*' || echo "0")
    FAILURES=$(grep -o 'failures="[0-9]*"' /test-results/phpunit-results.xml | head -1 | grep -o '[0-9]*' || echo "0")
    ERRORS=$(grep -o 'errors="[0-9]*"' /test-results/phpunit-results.xml | head -1 | grep -o '[0-9]*' || echo "0")

    echo "Total Tests: ${TESTS}"
    echo "Failures: ${FAILURES}"
    echo "Errors: ${ERRORS}"
    echo ""

    if [ "${TEST_EXIT_CODE:-0}" -eq 0 ]; then
        print_status "All tests passed"
    else
        print_error "Tests failed (exit code: ${TEST_EXIT_CODE})"
    fi
else
    print_error "Test results file not found"
    exit 1
fi

exit ${TEST_EXIT_CODE:-0}
