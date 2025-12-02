#!/bin/bash
set -euo pipefail

# TaxJar WooCommerce Plugin - Local Test Runner
# Fast iteration testing for WooCommerce PHPUnit tests

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

show_usage() {
    cat << EOF
Usage: ./local-test.sh [OPTIONS]

Test a TaxJar WooCommerce plugin with specific WooCommerce versions locally.

OPTIONS:
    --wc=VERSION          WooCommerce version (e.g., 8.9.1, 9.3.3, 7.9.0, 10.2.2)
    --test=PATH           Path to specific test file or directory
    --filter=PATTERN      PHPUnit filter pattern (test method name)
    --all                 Run all tests
    --setup               Set up environment and exit (don't run tests)
    --logs                Show WordPress and Apache logs
    --clean               Stop containers and clean up
    --shell               Open shell in WordPress container
    -h, --help            Show this help message

EXAMPLES:
    # Run a specific test with WooCommerce 8.9.1
    ./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

    # Run specific test method
    ./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php --filter=test_get_taxjar_api_key

    # Run all tests with WooCommerce 9.3.3
    ./local-test.sh --wc=9.3.3 --all

    # Set up environment without running tests
    ./local-test.sh --wc=8.9.1 --setup

    # View logs from running container
    ./local-test.sh --logs

    # Open shell for debugging
    ./local-test.sh --shell

    # Clean up containers
    ./local-test.sh --clean

VERSION MATRIX:
    WC 7.9.0  -> PHP 8.0, WP 6.0
    WC 8.9.1  -> PHP 8.1, WP 6.2
    WC 9.3.3  -> PHP 8.2, WP 6.4
    WC 10.2.2 -> PHP 8.3, WP 6.7

EOF
}

# Version matrix mapping
get_php_version() {
    local wc_version=$1
    local major_version=$(echo "$wc_version" | cut -d. -f1)

    case $major_version in
        7)  echo "8.0" ;;
        8)  echo "8.1" ;;
        9)  echo "8.2" ;;
        10) echo "8.3" ;;
        *)  echo "8.2" ;; # Default
    esac
}

get_wp_version() {
    local wc_version=$1
    local major_version=$(echo "$wc_version" | cut -d. -f1)

    case $major_version in
        7)  echo "6.0" ;;
        8)  echo "6.2" ;;
        9)  echo "6.4" ;;
        10) echo "6.7" ;;
        *)  echo "6.4" ;; # Default
    esac
}

# Load TaxJar API token
load_api_token() {
    if [ -f "$HOME/.taxjar.token" ]; then
        source "$HOME/.taxjar.token"
        export TAXJAR_API_TOKEN
        print_status "Loaded TaxJar API token"
    else
        print_warning "TaxJar API token not found at $HOME/.taxjar.token"
        export TAXJAR_API_TOKEN=""
    fi
}

# Parse arguments
WC_VERSION=""
TEST_PATH=""
FILTER=""
RUN_ALL=false
SETUP_ONLY=false
SHOW_LOGS=false
CLEAN=false
OPEN_SHELL=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --wc=*)
            WC_VERSION="${1#*=}"
            shift
            ;;
        --test=*)
            TEST_PATH="${1#*=}"
            shift
            ;;
        --filter=*)
            FILTER="${1#*=}"
            shift
            ;;
        --all)
            RUN_ALL=true
            shift
            ;;
        --setup)
            SETUP_ONLY=true
            shift
            ;;
        --logs)
            SHOW_LOGS=true
            shift
            ;;
        --clean)
            CLEAN=true
            shift
            ;;
        --shell)
            OPEN_SHELL=true
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Clean up and exit
if [ "$CLEAN" = true ]; then
    print_info "Stopping and removing containers..."
    docker-compose -f docker-compose.local.yml down -v
    print_status "Clean up complete"
    exit 0
fi

# Show logs and exit
if [ "$SHOW_LOGS" = true ]; then
    print_info "Showing WordPress container logs..."
    docker-compose -f docker-compose.local.yml logs --tail=100 wordpress
    exit 0
fi

# Open shell and exit
if [ "$OPEN_SHELL" = true ]; then
    print_info "Opening shell in WordPress container..."
    docker-compose -f docker-compose.local.yml exec wordpress /bin/bash
    exit 0
fi

# Validate WC version is provided for test runs
if [ -z "$WC_VERSION" ] && [ "$SHOW_LOGS" = false ] && [ "$CLEAN" = false ] && [ "$OPEN_SHELL" = false ]; then
    print_error "WooCommerce version is required"
    echo ""
    show_usage
    exit 1
fi

# Validate test specification
if [ "$RUN_ALL" = false ] && [ -z "$TEST_PATH" ] && [ "$SETUP_ONLY" = false ]; then
    print_error "Must specify --test=PATH or --all"
    echo ""
    show_usage
    exit 1
fi

# Load API token
load_api_token

# Determine PHP and WP versions
PHP_VERSION=$(get_php_version "$WC_VERSION")
WP_VERSION=$(get_wp_version "$WC_VERSION")

export WC_VERSION
export PHP_VERSION
export WP_VERSION

echo ""
print_info "Test Configuration"
echo "  WooCommerce: $WC_VERSION"
echo "  PHP:         $PHP_VERSION"
echo "  WordPress:   $WP_VERSION"
echo ""

# Start containers
print_info "Starting Docker containers..."
docker-compose -f docker-compose.local.yml up -d

# Wait for WordPress to be healthy
print_info "Waiting for WordPress to be ready..."
max_attempts=60
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if docker-compose -f docker-compose.local.yml exec -T wordpress curl -f http://localhost/wp-admin/install.php >/dev/null 2>&1; then
        print_status "WordPress is ready"
        break
    fi
    printf '.'
    sleep 2
    attempt=$((attempt + 1))
done
echo ""

if [ $attempt -eq $max_attempts ]; then
    print_error "WordPress failed to start"
    print_info "Check logs with: docker-compose -f docker-compose.local.yml logs wordpress"
    exit 1
fi

# Run setup script
print_info "Setting up WordPress + WooCommerce environment..."

SETUP_SCRIPT='
set -euo pipefail

# Colors
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
NC="\033[0m"

print_status() { echo -e "${GREEN}✓${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }

# Copy WordPress files if needed
if [ ! -e /var/www/html/index.php ]; then
    print_status "Copying WordPress files"
    cp -r /usr/src/wordpress/. /var/www/html/ >/dev/null 2>&1
    chown -R www-data:www-data /var/www/html/wp-admin 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/wp-includes 2>/dev/null || true
fi

# Start Apache
service apache2 status >/dev/null 2>&1 || service apache2 start >/dev/null 2>&1

# Install tools
if ! command -v unzip &>/dev/null; then
    apt-get update -qq && apt-get install -qq -y unzip git curl >/dev/null 2>&1
fi

# Install WP-CLI if not present
if ! command -v wp &>/dev/null; then
    print_status "Installing WP-CLI"
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
        --url=http://localhost:8080 \
        --title="TaxJar Test Site" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=test@taxjar.com \
        --skip-email \
        --allow-root >/dev/null 2>&1
fi

# Install WooCommerce if needed
if [ -d "/var/www/html/wp-content/plugins/woocommerce" ]; then
    print_status "WooCommerce ${WC_VERSION} already present"
else
    print_status "Downloading WooCommerce ${WC_VERSION}"
    cd /var/www/html/wp-content/plugins
    curl -sS -o woocommerce.zip "https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip"
    unzip -q woocommerce.zip
    rm woocommerce.zip
    cd /var/www/html
fi

# Activate plugins
print_status "Activating plugins"
wp plugin activate woocommerce --allow-root >/dev/null 2>&1 || true
wp plugin activate taxjar-simplified-taxes-for-woocommerce --allow-root >/dev/null 2>&1 || true

# Configure TaxJar
if [ -n "${TAXJAR_API_TOKEN:-}" ]; then
    print_status "Configuring TaxJar API token"
    wp option update woocommerce_taxjar-integration_settings \
        "{\"api_token\":\"${TAXJAR_API_TOKEN}\",\"enabled\":\"yes\"}" \
        --format=json \
        --allow-root >/dev/null 2>&1
fi

# Create symlinks for test bootstrap
PLUGIN_DIR="/var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce"

if [ ! -e "/var/www/html/wp-content/plugins/taxjar-woocommerce-plugin" ]; then
    ln -s "$PLUGIN_DIR" /var/www/html/wp-content/plugins/taxjar-woocommerce-plugin
fi

if [ ! -e "$PLUGIN_DIR/woocommerce" ]; then
    ln -s /var/www/html/wp-content/plugins/woocommerce "$PLUGIN_DIR/woocommerce"
fi

cd "$PLUGIN_DIR"

# Install Composer dependencies
if [ ! -f "vendor/autoload.php" ]; then
    print_status "Installing Composer"
    if ! command -v composer &>/dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
    fi
    print_status "Installing PHP dependencies"
    composer install --no-interaction --prefer-dist --optimize-autoloader --quiet 2>&1 | grep -v "Warning:" || true
fi

# Set up WordPress test library
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/var/www/html

if [ ! -d "$WP_TESTS_DIR" ]; then
    WP_TEST_BRANCH="${WP_VERSION}"
    print_status "Installing WordPress test library v${WP_TEST_BRANCH}"
    git clone --depth=1 --branch="$WP_TEST_BRANCH" https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop >/dev/null 2>&1

    mkdir -p /tmp/wordpress-tests-lib
    cp -r /tmp/wordpress-develop/tests/phpunit/includes /tmp/wordpress-tests-lib/includes
    cp -r /tmp/wordpress-develop/tests/phpunit/data /tmp/wordpress-tests-lib/data

    cat > $WP_TESTS_DIR/wp-tests-config.php << "EOFCONFIG"
<?php
define( "ABSPATH", "/var/www/html/" );
define( "DB_NAME", "wordpress_test" );
define( "DB_USER", "wordpress" );
define( "DB_PASSWORD", "wordpress" );
define( "DB_HOST", "mysql" );
define( "DB_CHARSET", "utf8" );
define( "DB_COLLATE", "" );
define( "WP_TESTS_DOMAIN", "localhost" );
define( "WP_TESTS_EMAIL", "admin@example.org" );
define( "WP_TESTS_TITLE", "Test Blog" );
define( "WP_PHP_BINARY", "php" );
define( "WPLANG", "" );
$table_prefix = "wptests_";
define( "WP_DEBUG", true );
EOFCONFIG
fi

mkdir -p /test-results
chmod 777 /test-results

print_status "Environment setup complete"
'

docker-compose -f docker-compose.local.yml exec -T wordpress /bin/bash -c "$SETUP_SCRIPT"

if [ "$SETUP_ONLY" = true ]; then
    print_status "Setup complete"
    print_info "Environment is ready for testing"
    print_info "Access WordPress at: http://localhost:8080"
    print_info "Run tests with: ./local-test.sh --wc=$WC_VERSION --test=tests/specs/test-actions.php"
    exit 0
fi

# Build test command
PHPUNIT_CMD="cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce && vendor/bin/phpunit"
PHPUNIT_CMD="$PHPUNIT_CMD --configuration tests/phpunit.xml"

if [ "$RUN_ALL" = true ]; then
    print_info "Running all tests..."
    PHPUNIT_CMD="$PHPUNIT_CMD --testdox --colors=always"
elif [ -n "$TEST_PATH" ]; then
    print_info "Running tests from: $TEST_PATH"
    PHPUNIT_CMD="$PHPUNIT_CMD $TEST_PATH --testdox --colors=always"
fi

if [ -n "$FILTER" ]; then
    print_info "Filtering tests: $FILTER"
    PHPUNIT_CMD="$PHPUNIT_CMD --filter '$FILTER'"
fi

echo ""
print_info "Executing PHPUnit tests..."
echo ""

# Run the tests
docker-compose -f docker-compose.local.yml exec -T wordpress /bin/bash -c "$PHPUNIT_CMD" || TEST_EXIT_CODE=$?

echo ""
if [ "${TEST_EXIT_CODE:-0}" -eq 0 ]; then
    print_status "Tests passed!"
else
    print_error "Tests failed (exit code: ${TEST_EXIT_CODE})"
    echo ""
    print_info "Debugging tips:"
    echo "  - View logs:        ./local-test.sh --logs"
    echo "  - Open shell:       ./local-test.sh --shell"
    echo "  - WordPress admin:  http://localhost:8080/wp-admin (admin/password)"
    echo "  - Test results:     ./test-results/phpunit-output.log"
fi

echo ""
print_info "Containers are still running for inspection"
print_info "Stop with: docker-compose -f docker-compose.local.yml down"

exit ${TEST_EXIT_CODE:-0}
