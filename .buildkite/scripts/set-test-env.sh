#!/bin/bash
# Set environment variables based on matrix value
case "${BUILDKITE_MATRIX}" in
  "7.x")
    export WC_VERSION="7.9.0"
    export PHP_VERSION="8.0"
    export WP_VERSION="6.0"
    ;;
  "8.x")
    export WC_VERSION="8.9.1"
    export PHP_VERSION="8.1"
    export WP_VERSION="6.2"
    ;;
  "9.x")
    export WC_VERSION="9.3.3"
    export PHP_VERSION="8.2"
    export WP_VERSION="6.4"
    ;;
  "10.x")
    export WC_VERSION="10.2.2"
    export PHP_VERSION="8.3"
    export WP_VERSION="6.7"
    ;;
esac

echo "Testing WooCommerce ${WC_VERSION} with PHP ${PHP_VERSION} and WordPress ${WP_VERSION}"

# Execute the tests
exec /test-scripts/run-tests-in-container.sh