#!/bin/bash
set -e

echo "--- Validating Version Consistency"

# Extract version from taxjar-woocommerce.php
extract_version() {
    local file="$1"
    # Use sed for macOS compatibility (grep -P not available on macOS)
    sed -n 's/.*\* Version: \([0-9]\+\.[0-9]\+\.[0-9]\+\).*/\1/p' "$file" | head -1
}

# Compare versions between branches
if [[ -n "$PR_DIR" && -n "$MASTER_DIR" ]]; then
    # Test mode - compare fixture dirs
    PR_VERSION=$(extract_version "$PR_DIR/taxjar-woocommerce.php")
    MASTER_VERSION=$(extract_version "$MASTER_DIR/taxjar-woocommerce.php")
else
    # Real mode - compare git branches
    PR_VERSION=$(extract_version "taxjar-woocommerce.php")
    MASTER_VERSION=$(git show origin/master:taxjar-woocommerce.php | sed -n 's/.*\* Version: \([0-9]\+\.[0-9]\+\.[0-9]\+\).*/\1/p' | head -1 || echo "")
fi

echo "Master version: $MASTER_VERSION"
echo "PR version: $PR_VERSION"

if [[ "$PR_VERSION" == "$MASTER_VERSION" ]]; then
    echo "+++ No version change detected - skipping validation"
    exit 0
fi

echo "+++ Version change detected: $MASTER_VERSION → $PR_VERSION"
echo "Proceeding with validation..."

# Validation logic will go here in next task
exit 0
