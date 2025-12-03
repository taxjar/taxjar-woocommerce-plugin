#!/bin/bash
set -e

# Extract version from taxjar-woocommerce.php
extract_version() {
    if [[ ! -f "taxjar-woocommerce.php" ]]; then
        echo "ERROR: taxjar-woocommerce.php not found" >&2
        exit 1
    fi

    local version
    # Use sed instead of grep -P for macOS compatibility
    version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' taxjar-woocommerce.php | head -1)

    if [[ -z "$version" ]]; then
        echo "ERROR: Could not extract version from taxjar-woocommerce.php" >&2
        exit 1
    fi

    echo "$version"
}

# Main logic
if [[ "$1" == "extract" ]]; then
    # Test mode - just extract and print
    extract_version
    exit 0
fi

echo "--- Detecting Version"
VERSION=$(extract_version)
echo "Current version in code: $VERSION"

# Export for downstream steps
echo "VERSION=$VERSION" >> "$BUILDKITE_ENV_FILE" 2>/dev/null || true
echo "Exported VERSION=$VERSION"

exit 0
