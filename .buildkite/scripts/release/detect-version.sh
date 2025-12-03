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

# Check if version exists on WordPress.org
check_wporg_version() {
    local version="$1"
    local plugin_slug="taxjar-simplified-taxes-for-woocommerce"
    local api_url="https://api.wordpress.org/plugins/info/1.0/${plugin_slug}.json"

    echo "Checking WordPress.org for version $version..."

    # Fetch current version from API
    local wporg_version
    wporg_version=$(curl -sf "$api_url" | jq -r '.version' 2>/dev/null || echo "")

    if [[ -z "$wporg_version" ]]; then
        echo "ERROR: Failed to query WordPress.org API" >&2
        echo "URL: $api_url" >&2
        return 2
    fi

    echo "WordPress.org current version: $wporg_version"

    if [[ "$wporg_version" == "$version" ]]; then
        return 0  # Version exists
    else
        return 1  # Version doesn't exist
    fi
}

# Main logic
if [[ "$1" == "extract" ]]; then
    # Test mode - just extract and print
    extract_version
    exit 0
elif [[ "$1" == "check-wporg" ]]; then
    # Test mode - check if version exists on WordPress.org
    check_wporg_version "$2"
    exit $?
fi

echo "--- Detecting Version"
VERSION=$(extract_version)
echo "Current version in code: $VERSION"

echo ""
echo "--- Checking WordPress.org"

if check_wporg_version "$VERSION"; then
    echo "+++ Version $VERSION already exists on WordPress.org"
    echo "Skipping release pipeline"

    # Set meta-data for pipeline to check
    if command -v buildkite-agent &> /dev/null; then
        buildkite-agent meta-data set "SKIP_RELEASE" "true"
    fi

    exit 0
fi

echo "+++ Version $VERSION not on WordPress.org - proceeding with release"

# Export for downstream steps (both methods for compatibility)
echo "VERSION=$VERSION" >> "$BUILDKITE_ENV_FILE" 2>/dev/null || true
if command -v buildkite-agent &> /dev/null; then
    buildkite-agent meta-data set "release-version" "$VERSION"
fi
echo "Exported VERSION=$VERSION"

exit 0
