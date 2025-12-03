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

echo "=== Test infrastructure ready ==="
