#!/bin/bash
# Set environment variables based on matrix value
case "${BUILDKITE_MATRIX}" in
  "7.x")
    export WC_VERSION="7.9.1"
    export PHP_VERSION="8.1"
    export WP_VERSION="6.1"
    ;;
  "8.x")
    export WC_VERSION="8.9.3"
    export PHP_VERSION="8.1"
    export WP_VERSION="6.3"
    ;;
  "9.x")
    export WC_VERSION="9.9.5"
    export PHP_VERSION="8.2"
    export WP_VERSION="6.6"
    ;;
  "10.x")
    export WC_VERSION="10.3.6"
    export PHP_VERSION="8.3"
    export WP_VERSION="6.7"
    ;;
esac

echo "Testing WooCommerce ${WC_VERSION} with PHP ${PHP_VERSION} and WordPress ${WP_VERSION}"

# Execute the tests
exec /test-scripts/run-tests-in-container.sh