#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "=== Testing Validation Script ==="

# Test will compare fixtures as if they're from different branches
# We'll create temp dirs to simulate git branches

TEST_DIR="/tmp/validation-test-$$"
mkdir -p "$TEST_DIR/master"
mkdir -p "$TEST_DIR/pr-branch"

echo "✓ Test infrastructure created"
echo "Test directory: $TEST_DIR"

# Cleanup on exit
trap "rm -rf $TEST_DIR" EXIT

# Test 1: No version change - should skip validation
echo "Test 1: No version change should skip validation"

# Copy same version to both branches
cp "$PROJECT_ROOT/tests/fixtures/sample-plugin.php" "$TEST_DIR/master/taxjar-woocommerce.php"
cp "$PROJECT_ROOT/tests/fixtures/sample-plugin.php" "$TEST_DIR/pr-branch/taxjar-woocommerce.php"

# Mock git diff that shows no version change
export MOCK_GIT_DIFF="false"
export MASTER_DIR="$TEST_DIR/master"
export PR_DIR="$TEST_DIR/pr-branch"

if "$PROJECT_ROOT/.buildkite/scripts/validate-version.sh" | grep -q "No version change detected"; then
    echo "✓ PASS: Skips validation when version unchanged"
else
    echo "✗ FAIL: Should skip validation when version unchanged"
    exit 1
fi

# Test 2: Version change with mismatched versions - should fail validation
echo "Test 2: Version mismatch should fail validation"

mkdir -p "$TEST_DIR/pr-branch-mismatch"
mkdir -p "$TEST_DIR/master-mismatch"

# Create PR branch with mismatched versions (4.2.0 in header, 4.1.0 in property)
cat > "$TEST_DIR/pr-branch-mismatch/taxjar-woocommerce.php" << 'EOF'
<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Version: 4.2.0
 * WC requires at least: 7.0.0
 * WC tested up to: 9.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Taxjar_Integration {
    public $version = '4.1.0';
    public $minimum_woocommerce_version = '7.0.0';
}
EOF

# Create readme.txt with different version
cat > "$TEST_DIR/pr-branch-mismatch/readme.txt" << 'EOF'
=== TaxJar ===
Stable tag: 4.3.0
Tested up to: 6.6.0
WC requires at least: 7.0.0
WC tested up to: 9.0.0

== Changelog ==

= 4.2.0 - 2025-12-01 =
* Updated version
EOF

# Copy master version
cp "$PROJECT_ROOT/tests/fixtures/sample-plugin.php" "$TEST_DIR/master-mismatch/taxjar-woocommerce.php"
cp "$PROJECT_ROOT/tests/fixtures/sample-readme.txt" "$TEST_DIR/master-mismatch/readme.txt"

export PR_DIR="$TEST_DIR/pr-branch-mismatch"
export MASTER_DIR="$TEST_DIR/master-mismatch"

if "$PROJECT_ROOT/.buildkite/scripts/validate-version.sh" 2>&1 | grep -q "ERROR"; then
    echo "✓ PASS: Detects version mismatch and fails validation"
else
    echo "✗ FAIL: Should detect version mismatch and fail"
    exit 1
fi

# Test 3: Missing CHANGELOG entry should fail
echo ""
echo "Test 3: Missing CHANGELOG entry should block merge"

mkdir -p "$TEST_DIR/pr-branch-changelog"
mkdir -p "$TEST_DIR/master-changelog"

# Create PR branch with matching versions but WRONG CHANGELOG version
cat > "$TEST_DIR/pr-branch-changelog/taxjar-woocommerce.php" << 'EOF'
<?php
/**
 * Version: 4.2.0
 */
class WC_Taxjar_Integration {
    public $version = '4.2.0';
}
EOF

cat > "$TEST_DIR/pr-branch-changelog/readme.txt" << 'EOF'
Stable tag: 4.2.0
Tested up to: 6.6.0
WC requires at least: 7.0.0
WC tested up to: 9.0.0
EOF

# CHANGELOG with WRONG version (4.1.0 instead of 4.2.0)
cat > "$TEST_DIR/pr-branch-changelog/CHANGELOG.md" << 'EOF'
# 4.1.0 - 2025-12-01

Old version
EOF

# Create master version
cp "$PROJECT_ROOT/tests/fixtures/sample-plugin.php" "$TEST_DIR/master-changelog/taxjar-woocommerce.php"

export PR_DIR="$TEST_DIR/pr-branch-changelog"
export MASTER_DIR="$TEST_DIR/master-changelog"

if "$PROJECT_ROOT/.buildkite/scripts/validate-version.sh" 2>&1 | grep -q "CHANGELOG.md.*4.2.0"; then
    echo "✓ PASS: Detects missing CHANGELOG entry"
else
    echo "✗ FAIL: Should detect missing CHANGELOG entry"
    exit 1
fi

# Test 4: Missing optional fields should warn but not block
echo ""
echo "Test 4: Missing optional fields should warn but not block"

mkdir -p "$TEST_DIR/pr-branch-warnings"
mkdir -p "$TEST_DIR/master-warnings"

# Create PR branch with only required fields (missing optional fields)
cat > "$TEST_DIR/pr-branch-warnings/taxjar-woocommerce.php" << 'EOF'
<?php
/**
 * Version: 4.2.0
 */
class WC_Taxjar_Integration {
    public $version = '4.2.0';
}
EOF

cat > "$TEST_DIR/pr-branch-warnings/readme.txt" << 'EOF'
Stable tag: 4.2.0

== Changelog ==

= 4.2.0 - 2025-12-03 =
* New version
EOF

cat > "$TEST_DIR/pr-branch-warnings/CHANGELOG.md" << 'EOF'
# 4.2.0 - 2025-12-03
New version
EOF

# Create master version
cp "$PROJECT_ROOT/tests/fixtures/sample-plugin.php" "$TEST_DIR/master-warnings/taxjar-woocommerce.php"

export PR_DIR="$TEST_DIR/pr-branch-warnings"
export MASTER_DIR="$TEST_DIR/master-warnings"

OUTPUT=$("$PROJECT_ROOT/.buildkite/scripts/validate-version.sh" 2>&1)
if echo "$OUTPUT" | grep -q "⚠️.*WC tested up to"; then
    if echo "$OUTPUT" | grep -q "All critical checks passed"; then
        echo "✓ PASS: Warns on optional fields but doesn't block"
    else
        echo "✗ FAIL: Should not block on optional field warnings"
        exit 1
    fi
else
    echo "✗ FAIL: Should warn about missing optional fields"
    exit 1
fi

echo "=== All tests passed ==="
