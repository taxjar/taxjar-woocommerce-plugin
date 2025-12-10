# TaxJar WooCommerce Plugin - Buildkite CI

This directory contains the Buildkite CI/CD pipeline configuration for the TaxJar WooCommerce plugin.

## Pipeline Overview

The pipeline implements quality gates for all pull requests and releases:

1. **PHP Lint** - Syntax validation for all PHP files
2. **PHPCS** - WordPress Coding Standards enforcement
3. **PHPUnit Tests** - Automated testing across multiple WooCommerce versions

## Test Matrix

The pipeline tests against multiple WooCommerce versions in parallel:

| WooCommerce | PHP | WordPress | Status |
|-------------|-----|-----------|--------|
| 7.9.1 | 8.1 | 6.1 | Oldest supported |
| 8.9.3 | 8.1 | 6.3 | Previous major |
| 9.9.5 | 8.2 | 6.6 | Current stable |
| 10.3.6 | 8.3 | 6.7 | Latest version |

## Directory Structure

```
.buildkite/
├── pipeline.yml              # Main pipeline configuration
├── docker-compose.test.yml   # Docker test environment
├── hooks/
│   ├── pre-command           # Loads secrets from Chamber, sets version matrix
│   └── post-command          # Cleanup and diagnostics
├── scripts/
│   ├── version-matrix.sh     # Configuration for WC/PHP/WP version matrix for testing
│   ├── run-tests.sh          # Test execution script
│   ├── set-test-env.sh       # Container environment setup (sources version-matrix.sh)
│   └── test-locally.sh       # Local validation script
└── README.md                 # This file
```

## Local Testing

Before pushing changes, validate the pipeline locally:

```bash
# Run local validation
.buildkite/scripts/test-locally.sh

# Run specific test configuration
export WC_VERSION=9.3.3
export PHP_VERSION=8.2
export WP_VERSION=6.4
export TAXJAR_API_TOKEN=your_test_token  # Optional
docker-compose -f .buildkite/docker-compose.test.yml run wordpress
```

## Secrets Management

The pipeline uses Chamber/SSM for secret management:

```bash
# Configure the TaxJar API token (required for integration tests)
chamber write buildkite/taxjar-woocommerce-plugin TAXJAR_API_TOKEN <token>

# Verify secret is accessible
chamber read buildkite/taxjar-woocommerce-plugin TAXJAR_API_TOKEN
```

## Pipeline Configuration in Buildkite

To set up this pipeline in Buildkite:

1. **Create Pipeline**
   - Name: `taxjar-woocommerce-plugin`
   - Repository: `github.com/taxjar/taxjar-woocommerce-plugin`
   - Branch: `master`

2. **Pipeline Settings**
   - Upload pipeline from: `.buildkite/pipeline.yml`
   - Enable: GitHub commit status updates
   - Enable: Build pull requests

3. **Environment**
   - Default agent queue: `default`
   - Cluster: Use TaxJar's standard cluster

4. **GitHub Integration**
   - Enable automatic webhook creation
   - Required status checks: `build`

## Monitoring and Debugging

### Build Artifacts

Each build produces:
- `phpcs-report.xml` - Code standards violations
- `test-results/*.xml` - PHPUnit test results in JUnit format

### Common Issues

**Tests fail with "TAXJAR_API_TOKEN not found"**
- Configure the token in Chamber (see Secrets Management)
- Ensure the pre-command hook has execute permissions

**Docker Compose fails to start**
- Verify Docker daemon is running
- Check for port conflicts (MySQL on 3306)
- Review health check logs

**PHPUnit tests timeout**
- Increase timeout in pipeline.yml (default: 15 minutes)
- Check WordPress/WooCommerce installation logs
- Verify network connectivity for package downloads

### Reproducing Failures Locally

When a build fails, reproduce it locally:

```bash
# Get the exact versions from the failed build
export WC_VERSION=<version>
export PHP_VERSION=<version>
export WP_VERSION=<version>

# Run the same test configuration
docker-compose -f .buildkite/docker-compose.test.yml run wordpress

# Check logs
docker-compose -f .buildkite/docker-compose.test.yml logs
```

## Maintenance

### Updating Test Matrix

Edit `.buildkite/scripts/version-matrix.sh` to update versions. This is the **single source of truth** for all WC/PHP/WP version combinations:

```bash
# In version-matrix.sh, update the case statement:
case "${BUILDKITE_MATRIX:-}" in
  "7.x")
    export WC_VERSION="7.9.1"
    export PHP_VERSION="8.1"
    export WP_VERSION="6.1"
    ;;
  # ... other versions
esac
```

Both `hooks/pre-command` and `scripts/set-test-env.sh` source this file, so you only need to update it in one place.

To add a new WC version to the matrix, also update `pipeline.yml`:

```yaml
matrix:
  - "7.x"
  - "8.x"
  - "9.x"
  - "10.x"
  - "11.x"  # Add new version here
```

### Adding New Test Stages

1. Add the stage to `pipeline.yml`
2. Update dependencies with `depends_on`
3. Test locally before deploying

## Phase 2 Roadmap

Future enhancements planned:

- [ ] Automated release deployment to WordPress.org
- [ ] Performance testing benchmarks
- [ ] Security scanning (SAST)
- [ ] Dependency vulnerability checking
- [ ] Code coverage reporting
- [ ] Visual regression testing

## Support

For issues or questions:
- Check build logs in Buildkite dashboard
- Review this README and troubleshooting section
- Contact the TaxJar platform team
