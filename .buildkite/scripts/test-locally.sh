#!/bin/bash
# TaxJar WooCommerce Plugin - Local Testing Helper
# Run this script from the repository root to test the CI pipeline locally

set -euo pipefail

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}==============================================================================${NC}"
echo -e "${BLUE}TaxJar WooCommerce Plugin - Local CI Testing${NC}"
echo -e "${BLUE}==============================================================================${NC}"
echo ""

# Check if running from repository root
if [ ! -f "taxjar-woocommerce.php" ]; then
  echo -e "${RED}Error: Must run from repository root${NC}"
  echo "Current directory: $(pwd)"
  exit 1
fi

# Check dependencies
echo -e "${YELLOW}Checking dependencies...${NC}"

if ! command -v docker &> /dev/null; then
  echo -e "${RED}❌ Docker not found - please install Docker${NC}"
  exit 1
fi

if ! command -v docker-compose &> /dev/null; then
  echo -e "${RED}❌ docker-compose not found - please install docker-compose${NC}"
  exit 1
fi

if ! command -v jq &> /dev/null; then
  echo -e "${RED}❌ jq not found - please install jq (brew install jq)${NC}"
  exit 1
fi

echo -e "${GREEN}✅ All dependencies found${NC}"
echo ""

# Parse command line arguments
WC_MATRIX="${1:-9.x}"  # Default to 9.x if not specified

# Load version configuration
if [ ! -f ".buildkite/matrix-config.json" ]; then
  echo -e "${RED}Error: .buildkite/matrix-config.json not found${NC}"
  exit 1
fi

WC_VERSION=$(jq -r ".versions.\"${WC_MATRIX}\".woocommerce" .buildkite/matrix-config.json)
PHP_VERSION=$(jq -r ".versions.\"${WC_MATRIX}\".php" .buildkite/matrix-config.json)
WP_VERSION=$(jq -r ".versions.\"${WC_MATRIX}\".wordpress" .buildkite/matrix-config.json)

if [ "$WC_VERSION" == "null" ]; then
  echo -e "${RED}Error: Invalid matrix value '${WC_MATRIX}'${NC}"
  echo "Valid values: 7.x, 8.x, 9.x, 10.x"
  exit 1
fi

echo -e "${BLUE}Test Configuration:${NC}"
echo "  Matrix:       ${WC_MATRIX}"
echo "  WooCommerce:  ${WC_VERSION}"
echo "  PHP:          ${PHP_VERSION}"
echo "  WordPress:    ${WP_VERSION}"
echo ""

# Export environment variables
export WC_VERSION
export PHP_VERSION
export WP_VERSION
export COMPOSE_PROJECT_NAME="taxjar-wc-local-${WC_MATRIX}"
export TAXJAR_API_TOKEN="${TAXJAR_API_TOKEN:-}"

if [ -z "${TAXJAR_API_TOKEN}" ]; then
  echo -e "${YELLOW}⚠️  TAXJAR_API_TOKEN not set - some tests may fail${NC}"
  echo "   Set it with: export TAXJAR_API_TOKEN=your_token"
  echo ""
fi

# Ensure test results directory exists
mkdir -p .buildkite/test-results

# Clean up any existing containers
echo -e "${YELLOW}Cleaning up existing containers...${NC}"
cd .buildkite
docker-compose -f docker-compose.test.yml down -v 2>/dev/null || true

# Start test environment
echo -e "${BLUE}==============================================================================${NC}"
echo -e "${BLUE}Starting test environment...${NC}"
echo -e "${BLUE}==============================================================================${NC}"
docker-compose -f docker-compose.test.yml up -d

# Wait for WordPress to be ready
echo ""
echo -e "${YELLOW}Waiting for WordPress to be ready...${NC}"
WAIT_COUNT=0
MAX_WAIT=60
while ! docker-compose -f docker-compose.test.yml exec -T wordpress curl -sf http://localhost/ > /dev/null 2>&1; do
  sleep 2
  WAIT_COUNT=$((WAIT_COUNT + 1))
  if [ $WAIT_COUNT -ge $MAX_WAIT ]; then
    echo -e "${RED}❌ WordPress failed to start after ${MAX_WAIT} attempts${NC}"
    echo ""
    echo "Container logs:"
    docker-compose -f docker-compose.test.yml logs wordpress
    docker-compose -f docker-compose.test.yml down -v
    exit 1
  fi
  echo -n "."
done

echo ""
echo -e "${GREEN}✅ WordPress is ready${NC}"

# Run tests
echo ""
echo -e "${BLUE}==============================================================================${NC}"
echo -e "${BLUE}Running tests...${NC}"
echo -e "${BLUE}==============================================================================${NC}"
echo ""

docker-compose -f docker-compose.test.yml exec -T wordpress bash /test-scripts/run-tests.sh || TEST_EXIT_CODE=$?

# Show results
echo ""
echo -e "${BLUE}==============================================================================${NC}"
if [ "${TEST_EXIT_CODE:-0}" -eq 0 ]; then
  echo -e "${GREEN}✅ Tests PASSED for WooCommerce ${WC_VERSION}${NC}"
else
  echo -e "${RED}❌ Tests FAILED for WooCommerce ${WC_VERSION} (exit code: ${TEST_EXIT_CODE})${NC}"
fi
echo -e "${BLUE}==============================================================================${NC}"

# Prompt for cleanup
echo ""
read -p "Clean up containers? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
  echo -e "${YELLOW}Cleaning up...${NC}"
  docker-compose -f docker-compose.test.yml down -v
  echo -e "${GREEN}✅ Cleanup complete${NC}"
else
  echo -e "${YELLOW}Containers left running. To clean up later, run:${NC}"
  echo "  cd .buildkite && docker-compose -f docker-compose.test.yml down -v"
fi

echo ""
echo "Test results available at: .buildkite/test-results/junit-wc-${WC_VERSION}.xml"

exit ${TEST_EXIT_CODE:-0}
