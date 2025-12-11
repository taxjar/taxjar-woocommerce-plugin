#!/bin/bash
# Version matrix for WooCommerce test environments
# This is the SINGLE SOURCE OF TRUTH for WC/PHP/WP version combinations
#
# Usage: source this file after setting BUILDKITE_MATRIX, then use the exported variables
#
# Example:
#   export BUILDKITE_MATRIX="8.x"
#   source .buildkite/scripts/version-matrix.sh
#   echo "WC: $WC_VERSION, PHP: $PHP_VERSION, WP: $WP_VERSION"

set_version_matrix() {
  case "${BUILDKITE_MATRIX:-}" in
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
    *)
      # Default/fallback - useful for local testing
      if [ -z "${WC_VERSION:-}" ]; then
        export WC_VERSION="9.9.5"
        export PHP_VERSION="8.2"
        export WP_VERSION="6.6"
      fi
      ;;
  esac
}

# Auto-run if sourced with BUILDKITE_MATRIX set
if [ -n "${BUILDKITE_MATRIX:-}" ]; then
  set_version_matrix
fi
