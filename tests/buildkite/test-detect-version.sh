#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "=== Testing Version Detection Script ==="

# Test 1: Extract version from plugin file
echo "Test 1: Version extraction"

TEST_DIR="/tmp/detect-test-$$"
mkdir -p "$TEST_DIR"
trap "rm -rf $TEST_DIR" EXIT

cat > "$TEST_DIR/taxjar-woocommerce.php" << 'EOF'
<?php
/**
 * Version: 4.2.0
 */
EOF

cd "$TEST_DIR"
VERSION=$("$PROJECT_ROOT/.buildkite/scripts/release/detect-version.sh" extract)

if [[ "$VERSION" == "4.2.0" ]]; then
    echo "✓ PASS: Version extracted correctly"
else
    echo "✗ FAIL: Expected 4.2.0, got: $VERSION"
    exit 1
fi

# Test 2: Check WordPress.org has version
echo ""
echo "Test 2: WordPress.org API check"

# Known existing version
if "$PROJECT_ROOT/.buildkite/scripts/release/detect-version.sh" check-wporg "4.2.6"; then
    echo "✓ PASS: Detects existing version on WordPress.org"
else
    echo "✗ FAIL: Should detect existing version"
    exit 1
fi

# Version that doesn't exist yet
if ! "$PROJECT_ROOT/.buildkite/scripts/release/detect-version.sh" check-wporg "99.99.99"; then
    echo "✓ PASS: Detects non-existing version"
else
    echo "✗ FAIL: Should detect version doesn't exist"
    exit 1
fi

echo "=== All detection tests passed ==="
