#!/bin/bash
set -e

echo "--- Validating Version Consistency"

# Extract version from taxjar-woocommerce.php
extract_version() {
    local file="$1"
    # Use sed for macOS compatibility (grep -P not available on macOS)
    sed -n 's/.*\* Version: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' "$file" | head -1
}

# Compare versions between branches
if [[ -n "$PR_DIR" && -n "$MASTER_DIR" ]]; then
    # Test mode - compare fixture dirs
    PR_VERSION=$(extract_version "$PR_DIR/taxjar-woocommerce.php")
    MASTER_VERSION=$(extract_version "$MASTER_DIR/taxjar-woocommerce.php")
else
    # Real mode - compare git branches
    PR_VERSION=$(extract_version "taxjar-woocommerce.php")
    MASTER_VERSION=$(git show origin/master:taxjar-woocommerce.php | sed -n 's/.*\* Version: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' | head -1 || echo "")
fi

echo "Master version: $MASTER_VERSION"
echo "PR version: $PR_VERSION"

if [[ "$PR_VERSION" == "$MASTER_VERSION" ]]; then
    echo "+++ No version change detected - skipping validation"
    exit 0
fi

echo "+++ Version change detected: $MASTER_VERSION → $PR_VERSION"
echo "Proceeding with validation..."

# Track validation failures
VALIDATION_FAILED=0

# Function to check a version field
check_version_field() {
    local field_name="$1"
    local expected="$2"
    local actual="$3"

    if [[ "$actual" != "$expected" ]]; then
        echo "ERROR: $field_name mismatch - expected $expected, found $actual"
        VALIDATION_FAILED=1
    else
        echo "✓ $field_name: $actual"
    fi
}

# Extract PHP version tag from header comment
if [[ -n "$PR_DIR" ]]; then
    PHP_VERSION=$(sed -n 's/.*\* Version: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' "$PR_DIR/taxjar-woocommerce.php" | head -1)
else
    PHP_VERSION=$(sed -n 's/.*\* Version: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' "taxjar-woocommerce.php" | head -1)
fi

# Extract PHP $version property from class
if [[ -n "$PR_DIR" ]]; then
    PHP_PROPERTY_VERSION=$(sed -n 's/.*public \$version = '\''\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)'\'';.*/\1/p' "$PR_DIR/taxjar-woocommerce.php" | head -1)
else
    PHP_PROPERTY_VERSION=$(sed -n 's/.*public \$version = '\''\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)'\'';.*/\1/p' "taxjar-woocommerce.php" | head -1)
fi

# Extract readme.txt stable tag
if [[ -n "$PR_DIR" ]]; then
    README_VERSION=$(sed -n 's/^Stable tag: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' "$PR_DIR/readme.txt" | head -1)
else
    README_VERSION=$(sed -n 's/^Stable tag: \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' "readme.txt" | head -1)
fi

echo "Checking version consistency (critical checks):"
check_version_field "PHP header Version tag" "$PR_VERSION" "$PHP_VERSION"
check_version_field "PHP \$version property" "$PR_VERSION" "$PHP_PROPERTY_VERSION"
check_version_field "readme.txt Stable tag" "$PR_VERSION" "$README_VERSION"

# Check CHANGELOG.md entry exists
echo ""
echo "Checking CHANGELOG.md for version $PR_VERSION:"

if [[ -n "$PR_DIR" ]]; then
    CHANGELOG_FILE="$PR_DIR/CHANGELOG.md"
else
    CHANGELOG_FILE="CHANGELOG.md"
fi

if [[ ! -f "$CHANGELOG_FILE" ]]; then
    echo "ERROR: CHANGELOG.md not found"
    VALIDATION_FAILED=1
elif ! grep -q "^# $PR_VERSION" "$CHANGELOG_FILE"; then
    echo "ERROR: CHANGELOG.md missing entry for version $PR_VERSION"
    echo "   Expected to find: # $PR_VERSION"
    VALIDATION_FAILED=1
else
    echo "✓ CHANGELOG.md has entry for $PR_VERSION"
fi

# Check readme.txt changelog entry
echo ""
echo "Checking readme.txt changelog for version $PR_VERSION:"

if [[ -n "$PR_DIR" ]]; then
    README_FILE="$PR_DIR/readme.txt"
else
    README_FILE="readme.txt"
fi

if [[ ! -f "$README_FILE" ]]; then
    echo "ERROR: readme.txt not found"
    VALIDATION_FAILED=1
elif ! grep -q "^= $PR_VERSION" "$README_FILE"; then
    echo "ERROR: readme.txt missing changelog entry for version $PR_VERSION"
    echo "   Expected to find: = $PR_VERSION"
    VALIDATION_FAILED=1
else
    echo "✓ readme.txt has changelog entry for $PR_VERSION"
fi

# Exit with failure if validation failed
if [[ $VALIDATION_FAILED -eq 1 ]]; then
    echo "ERROR: Version validation failed"
    exit 1
fi

echo "+++ Version validation passed"
exit 0
