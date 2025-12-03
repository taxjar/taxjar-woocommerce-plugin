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

echo "=== All detection tests passed ==="
