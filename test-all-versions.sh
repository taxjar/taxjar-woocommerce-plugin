#!/bin/bash
set -euo pipefail

# Test across all WooCommerce versions
# Useful for validating a fix works across WC 7.x, 8.x, 9.x, 10.x

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${GREEN}✓${NC} $1"; }
print_error() { echo -e "${RED}✗${NC} $1"; }
print_info() { echo -e "${BLUE}ℹ${NC} $1"; }

# Parse arguments
TEST_PATH=""
FILTER=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --test=*)
            TEST_PATH="${1#*=}"
            shift
            ;;
        --filter=*)
            FILTER="${1#*=}"
            shift
            ;;
        *)
            echo "Usage: $0 --test=PATH [--filter=PATTERN]"
            echo ""
            echo "Examples:"
            echo "  $0 --test=tests/specs/test-actions.php"
            echo "  $0 --test=tests/specs/test-actions.php --filter=test_get_taxjar_api_key"
            exit 1
            ;;
    esac
done

if [ -z "$TEST_PATH" ]; then
    echo "Error: --test=PATH is required"
    echo ""
    echo "Usage: $0 --test=PATH [--filter=PATTERN]"
    exit 1
fi

# WooCommerce versions to test
VERSIONS=("7.9.0" "8.9.1" "9.3.3" "10.2.2")

echo ""
print_info "Testing across all WooCommerce versions"
echo "Test: $TEST_PATH"
if [ -n "$FILTER" ]; then
    echo "Filter: $FILTER"
fi
echo ""

RESULTS=()

for version in "${VERSIONS[@]}"; do
    echo "========================================"
    print_info "Testing WooCommerce $version"
    echo "========================================"
    echo ""

    # Build command
    CMD="./local-test.sh --wc=$version --test=$TEST_PATH"
    if [ -n "$FILTER" ]; then
        CMD="$CMD --filter=$FILTER"
    fi

    # Run test
    if $CMD; then
        RESULTS+=("$version: PASS")
        print_status "WooCommerce $version: PASSED"
    else
        RESULTS+=("$version: FAIL")
        print_error "WooCommerce $version: FAILED"
    fi
    echo ""
done

# Summary
echo "========================================"
print_info "Summary"
echo "========================================"
echo ""

PASS_COUNT=0
FAIL_COUNT=0

for result in "${RESULTS[@]}"; do
    if [[ $result == *"PASS"* ]]; then
        print_status "$result"
        PASS_COUNT=$((PASS_COUNT + 1))
    else
        print_error "$result"
        FAIL_COUNT=$((FAIL_COUNT + 1))
    fi
done

echo ""
echo "Passed: $PASS_COUNT / ${#VERSIONS[@]}"
echo "Failed: $FAIL_COUNT / ${#VERSIONS[@]}"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    print_status "All versions passed!"
    exit 0
else
    print_error "Some versions failed"
    exit 1
fi
