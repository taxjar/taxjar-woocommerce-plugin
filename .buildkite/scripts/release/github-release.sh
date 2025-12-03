#!/bin/bash
set -e

echo "--- Creating GitHub Release"

if [[ -z "$VERSION" ]]; then
    echo "ERROR: VERSION environment variable not set"
    exit 1
fi

# Retry logic
retry_with_backoff() {
    local max_attempts=3
    local timeout=2
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        echo "Attempt $attempt of $max_attempts..."

        if "$@"; then
            return 0
        fi

        if [[ $attempt -lt $max_attempts ]]; then
            echo "Failed, waiting ${timeout}s before retry..."
            sleep $timeout
            timeout=$((timeout * 2))
        fi

        attempt=$((attempt + 1))
    done

    echo "ERROR: Failed after $max_attempts attempts"
    return 1
}

# Ensure we're on latest master
echo "Fetching latest master..."
git fetch origin master
git checkout master
git pull origin master

# Create release (with retry)
echo ""
echo "Creating release $VERSION..."

create_release() {
    GH_HOST=github.com gh release create "$VERSION" \
        --target master \
        --title "$VERSION" \
        --notes "" \
        --repo taxjar/taxjar-woocommerce-plugin
}

if retry_with_backoff create_release; then
    echo ""
    echo "+++ GitHub release created successfully"
    echo "URL: https://github.com/taxjar/taxjar-woocommerce-plugin/releases/tag/$VERSION"
    echo "Git tag $VERSION automatically created"
else
    echo ""
    echo "ERROR: Failed to create GitHub release"
    exit 1
fi
