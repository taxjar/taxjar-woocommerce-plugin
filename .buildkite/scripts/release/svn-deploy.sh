#!/bin/bash
set -e

echo "--- SVN Deployment to WordPress.org"

if [[ -z "$VERSION" ]]; then
    echo "ERROR: VERSION environment variable not set"
    exit 1
fi

if [[ -z "$WORDPRESS_SVN_USERNAME" || -z "$WORDPRESS_SVN_PASSWORD" ]]; then
    echo "ERROR: WordPress.org SVN credentials not set"
    echo "Required: WORDPRESS_SVN_USERNAME and WORDPRESS_SVN_PASSWORD"
    exit 1
fi

# Retry logic with longer backoff for SVN (slower than GitHub)
retry_with_backoff() {
    local max_attempts=3
    local timeout=5
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

SVN_URL="https://plugins.svn.wordpress.org/taxjar-simplified-taxes-for-woocommerce"
SVN_DIR="/tmp/taxjar-svn-$$"

echo "SVN Repository: $SVN_URL"
echo "Working directory: $SVN_DIR"

# Cleanup on exit
cleanup() {
    if [[ -d "$SVN_DIR" ]]; then
        echo "Cleaning up $SVN_DIR"
        rm -rf "$SVN_DIR"
    fi
}
trap cleanup EXIT

# Step 1: Checkout SVN repository
echo ""
echo "--- Checking out SVN repository"

mkdir -p "$SVN_DIR"
cd "$SVN_DIR"

if ! svn checkout "$SVN_URL" . --depth immediates; then
    echo "ERROR: Failed to checkout SVN repository"
    exit 1
fi

# Get trunk
svn update trunk --set-depth infinity

echo "✓ SVN repository checked out"

# Step 2: Clear trunk
echo ""
echo "--- Clearing SVN trunk"

cd trunk
rm -rf * .[^.]* 2>/dev/null || true
svn status | grep "^!" | awk '{print $2}' | xargs -r svn delete --force

echo "✓ Trunk cleared"

# Step 3: Clone git tag into trunk
echo ""
echo "--- Cloning Git repository (tag $VERSION)"

if ! git clone --depth 1 --branch "$VERSION" https://github.com/taxjar/taxjar-woocommerce-plugin.git .; then
    echo "ERROR: Failed to clone git repository at tag $VERSION"
    echo "Make sure the GitHub release was created first"
    exit 1
fi

# Remove git metadata
rm -rf .git .gitignore .gitattributes

echo "✓ Git repository cloned into trunk"

# Step 4: Handle file changes in SVN
echo ""
echo "--- Handling file changes"

# Add new files
NEW_FILES=$(svn status | grep "^?" | awk '{print $2}' || true)
if [[ -n "$NEW_FILES" ]]; then
    echo "Adding new files:"
    echo "$NEW_FILES"
    echo "$NEW_FILES" | xargs -I {} svn add "{}"
fi

# Delete removed files (already done above, but check again)
DELETED_FILES=$(svn status | grep "^!" | awk '{print $2}' || true)
if [[ -n "$DELETED_FILES" ]]; then
    echo "Deleting removed files:"
    echo "$DELETED_FILES"
    echo "$DELETED_FILES" | xargs -I {} svn delete "{}"
fi

echo ""
echo "SVN Status:"
svn status

echo "✓ File changes handled"

echo ""
echo "+++ SVN trunk prepared successfully"
echo "Ready for commit"
