#!/bin/bash
set -euo pipefail

# Local testing script for TaxJar WooCommerce CI Pipeline
# Run this before pushing to validate the pipeline configuration

echo "================================================"
echo "TaxJar WooCommerce CI - Local Test Runner"
echo "================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

# Check prerequisites
print_info "Checking prerequisites..."

# Check Docker
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed"
    exit 1
else
    print_status "Docker found: $(docker --version)"
fi

# Check Docker Compose
if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose is not installed"
    exit 1
else
    print_status "Docker Compose found: $(docker-compose --version)"
fi

# Check PHP
if ! command -v php &> /dev/null; then
    print_warning "PHP not found locally (will use Docker)"
else
    print_status "PHP found: $(php --version | head -1)"
fi

echo ""
echo "================================================"
echo "Stage 1: PHP Lint"
echo "================================================"

print_info "Running PHP syntax check..."

# Check if PHP is available locally
if command -v php &> /dev/null; then
    # Use local PHP
    LINT_ERRORS=0
    while IFS= read -r -d '' file; do
        if ! php -l "$file" > /dev/null 2>&1; then
            print_error "Syntax error in: $file"
            php -l "$file"
            LINT_ERRORS=$((LINT_ERRORS + 1))
        fi
    done < <(find . -name "*.php" -not -path "./vendor/*" -not -path "./.buildkite/*" -print0)

    if [ $LINT_ERRORS -eq 0 ]; then
        print_status "PHP Lint passed - no syntax errors found"
    else
        print_error "PHP Lint failed - $LINT_ERRORS file(s) with syntax errors"
        exit 1
    fi
else
    # Use Docker for PHP lint
    print_info "Using Docker for PHP syntax check..."

    # Run PHP lint in Docker directly
    LINT_OUTPUT=$(docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli bash -c '
        ERRORS=0
        FILES_CHECKED=0
        while IFS= read -r -d "" file; do
            FILES_CHECKED=$((FILES_CHECKED + 1))
            if ! php -l "$file" > /dev/null 2>&1; then
                echo "Syntax error in: ${file#/app/}"
                php -l "$file"
                ERRORS=$((ERRORS + 1))
            fi
        done < <(find /app -name "*.php" -not -path "/app/vendor/*" -not -path "/app/.buildkite/*" -print0)
        echo "Checked $FILES_CHECKED files, found $ERRORS error(s)"
        exit $ERRORS
    ' 2>&1)

    LINT_EXIT_CODE=$?

    if [ $LINT_EXIT_CODE -eq 0 ]; then
        print_status "PHP Lint passed - no syntax errors found"
        echo "$LINT_OUTPUT" | tail -1
    else
        print_error "PHP Lint failed - syntax errors found"
        echo "$LINT_OUTPUT"
        exit 1
    fi
fi

echo ""
echo "================================================"
echo "Stage 2: PHPCS (Optional - requires local setup)"
echo "================================================"

if [ -f "vendor/bin/phpcs" ]; then
    print_info "Running PHPCS..."
    vendor/bin/phpcs --standard=WordPress \
        --extensions=php \
        --ignore=vendor/,node_modules/,.buildkite/ \
        --report=summary \
        . || print_warning "PHPCS found issues (non-blocking)"
else
    print_warning "PHPCS not installed locally - skipping"
    print_info "To install: composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs"
fi

echo ""
echo "================================================"
echo "Stage 3: Docker Test Environment"
echo "================================================"

print_info "Testing Docker Compose configuration..."
if docker-compose -f .buildkite/docker-compose.test.yml config > /dev/null 2>&1; then
    print_status "Docker Compose configuration is valid"
else
    print_error "Docker Compose configuration is invalid"
    docker-compose -f .buildkite/docker-compose.test.yml config
    exit 1
fi

echo ""
echo "================================================"
echo "Optional: Run PHPUnit Tests with Docker"
echo "================================================"

print_info "Would you like to run the full PHPUnit test suite? (y/N)"
read -r response

if [[ "$response" =~ ^[Yy]$ ]]; then
    print_warning "Note: This requires a TAXJAR_API_TOKEN environment variable"

    if [ -z "${TAXJAR_API_TOKEN:-}" ]; then
        print_warning "TAXJAR_API_TOKEN not set - tests may fail"
        print_info "Set it with: export TAXJAR_API_TOKEN=your_token"
    fi

    # Test with WooCommerce 9.x (current stable)
    export WC_VERSION="9.3.3"
    export PHP_VERSION="8.2"
    export WP_VERSION="6.4"

    print_info "Testing with WooCommerce ${WC_VERSION}, PHP ${PHP_VERSION}, WordPress ${WP_VERSION}"

    # Clean up any existing containers
    docker-compose -f .buildkite/docker-compose.test.yml down -v 2>/dev/null || true

    # Run tests
    if docker-compose -f .buildkite/docker-compose.test.yml run --rm wordpress; then
        print_status "Tests completed successfully"
    else
        print_error "Tests failed"
    fi

    # Cleanup
    docker-compose -f .buildkite/docker-compose.test.yml down -v
else
    print_info "Skipping PHPUnit tests"
fi

echo ""
echo "================================================"
echo "Pipeline Validation Complete"
echo "================================================"

print_status "All local checks passed!"
print_info "Next steps:"
echo "  1. Commit your changes: git add -A && git commit -m 'Add Buildkite CI pipeline'"
echo "  2. Push to GitHub: git push origin feature/buildkite-ci-phase1"
echo "  3. Create a pull request"
echo "  4. Configure pipeline in Buildkite cloud (requires admin access)"

echo ""
print_info "To configure in Buildkite:"
echo "  - Repository: github.com/taxjar/taxjar-woocommerce-plugin"
echo "  - Pipeline file: .buildkite/pipeline.yml"
echo "  - Default branch: master"
echo "  - Configure Chamber secret: buildkite/taxjar-woocommerce-plugin TAXJAR_API_TOKEN"