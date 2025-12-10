#!/bin/bash
# Set environment variables based on matrix value
# Sources the single version matrix config for consistency

# Get the directory where this script lives
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Source the version matrix (single source of truth)
source "${SCRIPT_DIR}/version-matrix.sh"

echo "Testing WooCommerce ${WC_VERSION} with PHP ${PHP_VERSION} and WordPress ${WP_VERSION}"

# Execute the tests
exec /test-scripts/run-tests-in-container.sh
