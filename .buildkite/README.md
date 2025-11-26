# TaxJar WooCommerce Plugin - CI/CD Pipeline

This directory contains the Buildkite CI/CD pipeline configuration for automated quality gates and testing.

## Overview

**Phase 1: Quality Gates** - Automated testing on every pull request and master branch commit:

1. **PHP Lint** (30 seconds) - Fast syntax validation
2. **PHPCS** (1-2 minutes) - WordPress Coding Standards enforcement
3. **PHPUnit Matrix** (3-5 minutes parallel) - Tests across 4 WooCommerce versions

**Total Pipeline Time:** 5-10 minutes

## Pipeline Structure

```
.buildkite/
├── pipeline.yml              # Main pipeline definition
├── docker-compose.test.yml   # Test environment configuration
├── matrix-config.json        # WooCommerce version mappings
├── hooks/
│   └── pre-command          # Chamber/SSM secret loading
└── scripts/
    ├── run-tests.sh         # Test execution script (runs in container)
    └── test-locally.sh      # Local testing helper
```

## Test Matrix

Tests run in parallel across these WooCommerce/PHP/WordPress combinations:

| Matrix | WooCommerce | PHP | WordPress |
|--------|-------------|-----|-----------|
| 7.x    | 7.9.0       | 8.0 | 6.0       |
| 8.x    | 8.9.1       | 8.1 | 6.2       |
| 9.x    | 9.3.3       | 8.2 | 6.4       |
| 10.x   | 10.2.2      | 8.3 | 6.7       |

## Local Testing

Test the CI pipeline locally before pushing:

```bash
# From repository root
./.buildkite/scripts/test-locally.sh

# Test specific WooCommerce version
./.buildkite/scripts/test-locally.sh 9.x
```

**Requirements:**
- Docker
- docker-compose
- jq (for JSON parsing)

The script will:
1. Load version configuration from matrix-config.json
2. Start MySQL and WordPress containers
3. Run the full test suite
4. Display results and optionally clean up

## Updating WooCommerce Versions

When WooCommerce releases new versions:

1. **Update `.buildkite/matrix-config.json`:**
   ```json
   {
     "versions": {
       "10.x": {
         "woocommerce": "10.3.0",  # Update this
         "php": "8.3",
         "wordpress": "6.7"
       }
     }
   }
   ```

2. **Update `readme.txt`:**
   - Update "WC tested up to" tag
   - Update "Tested up to" (WordPress version)

3. **Commit both changes together:**
   ```bash
   git add .buildkite/matrix-config.json readme.txt
   git commit -m "Update tested WooCommerce version to 10.3.0"
   ```

4. **Verify tests pass:**
   ```bash
   ./.buildkite/scripts/test-locally.sh 10.x
   ```

## Secrets Management

The pipeline uses TaxJar's standard Chamber/SSM pattern for secrets.

### Required Secrets

**Path:** `/buildkite/taxjar-woocommerce-plugin/`

| Parameter Name (SSM) | Environment Variable | Purpose |
|---------------------|---------------------|---------|
| `taxjar_api_token` | `TAXJAR_API_TOKEN` | API token for live tax calculation tests |

**Important:** Parameter names in SSM **must be lowercase**, but are exported as uppercase environment variables.

### Setting Secrets

```bash
# Store secret (requires IAM permissions)
chamber write buildkite/taxjar-woocommerce-plugin taxjar_api_token YOUR_TOKEN_HERE

# Verify secret
chamber read buildkite/taxjar-woocommerce-plugin taxjar_api_token
```

### Local Testing with Secrets

```bash
# Export token before running tests
export TAXJAR_API_TOKEN=your_token_here
./.buildkite/scripts/test-locally.sh
```

Tests will run without the token but some will fail (expected behavior).

## Pipeline Configuration

### Agent Queues

- **docker** queue - Used for all steps (lint, PHPCS, PHPUnit)
  - Requires Docker runtime
  - Requires docker-compose

### Timeouts

- PHP Lint: 2 minutes
- PHPCS: 5 minutes
- PHPUnit (each matrix job): 15 minutes

### Soft Fail Strategy

- **PHPCS** - Initially soft-fails (doesn't block merges)
- **PHPUnit** - Hard fails (blocks merges)

Once PHPCS violations are addressed, remove `soft_fail: true` from pipeline.yml.

## Test Results

### JUnit XML Output

Test results are saved in JUnit XML format for Buildkite UI integration:

- **Location:** `.buildkite/test-results/junit-wc-{version}.xml`
- **Artifacts:** Automatically uploaded to Buildkite
- **Annotations:** Test failures appear inline in Buildkite UI

### Viewing Results

1. **Buildkite UI:** Failed tests show as annotations on the build
2. **Artifacts Tab:** Download full JUnit XML for analysis
3. **Local:** Check `.buildkite/test-results/` after local test runs

## Troubleshooting

### Common Issues

#### 1. MySQL Health Check Timeout

**Symptom:** Tests timeout waiting for MySQL

**Solutions:**
- Increase health check retries in docker-compose.test.yml
- Check tmpfs size is adequate (currently 1GB)
- Verify Docker has sufficient resources

#### 2. WooCommerce Download Fails

**Symptom:** "Failed to download WooCommerce" error

**Cause:** WordPress.org SVN service unavailable or version doesn't exist

**Solutions:**
- Verify version exists: https://plugins.svn.wordpress.org/woocommerce/tags/
- Check WordPress.org status page
- Wait and retry (transient network issues)

#### 3. Tests Pass Locally But Fail in CI

**Common Causes:**
- Missing `TAXJAR_API_TOKEN` secret in Chamber
- Different PHP version locally vs CI
- Docker image differences
- File permission issues

**Debug Steps:**
1. Check Buildkite logs for exact error
2. Compare environment variables (local vs CI)
3. Verify Docker image matches (check PHP version)
4. Run with same docker-compose setup locally

#### 4. Chamber Secret Not Loading

**Symptom:** Pre-command hook fails with "secret not found"

**Solutions:**
- Verify secret exists: `chamber read buildkite/taxjar-woocommerce-plugin taxjar_api_token`
- Check parameter name is **lowercase** in SSM
- Verify agent IAM role has SSM read permissions
- Check Chamber CLI is installed on agents

#### 5. WordPress Container Not Starting

**Symptom:** WordPress health check fails

**Debug:**
```bash
# From .buildkite directory
docker-compose -f docker-compose.test.yml logs wordpress
```

**Common Causes:**
- Port 80 already in use
- Insufficient memory
- MySQL not healthy
- Volume mount issues

## Architecture Notes

### Docker Compose Approach

The pipeline uses `docker-compose up` + `exec` pattern (not `docker-compose run`):

**Why:**
- `docker-compose run` bypasses the WordPress container entrypoint
- `docker-compose up` preserves normal WordPress initialization
- `exec` runs commands in an already-running container

### Test Isolation

Each matrix job uses a unique Docker Compose project name:

```bash
COMPOSE_PROJECT_NAME="taxjar-wc-{matrix}-{build_number}"
```

This prevents:
- Network name collisions between parallel jobs
- Database conflicts
- Resource contention

### WordPress Test Library

The test suite requires the WordPress test library:

- **Location:** `/tmp/wordpress-tests-lib/`
- **Version:** Matches WordPress version being tested
- **Source:** https://develop.svn.wordpress.org/

The run-tests.sh script automatically downloads the correct version.

## Buildkite Setup (Admin Only)

These steps require Buildkite admin access:

1. **Create Pipeline in Buildkite UI:**
   - Organization: taxjar-internal
   - Name: taxjar-woocommerce-plugin
   - Repository: github.com:taxjar-internal/taxjar-woocommerce-plugin

2. **Configure GitHub Webhook:**
   - Settings → Webhooks → Add webhook
   - Payload URL: (from Buildkite pipeline settings)
   - Events: Push, Pull Request

3. **Set Pipeline Steps:**
   - Point to: `.buildkite/pipeline.yml`
   - Branch pattern: `*` (all branches)

4. **Configure Agent Tags:**
   - Ensure agents with `queue=docker` have Docker installed
   - Verify agents have Chamber CLI available

5. **Store Secrets:**
   ```bash
   chamber write buildkite/taxjar-woocommerce-plugin taxjar_api_token TOKEN
   ```

6. **Verify IAM Permissions:**
   - Agents need SSM read access to `/buildkite/taxjar-woocommerce-plugin/*`

## Phase 2 (Future)

Phase 2 will add release automation:

- VERSION file as single source of truth
- Automatic git tagging on VERSION change
- WordPress.org SVN deployment
- GitHub release creation
- Slack notifications

The `.distignore` file is already in place for Phase 2 deployment.

## Support

**Issues:**
- CI/CD questions: #platform-engineering (Slack)
- Plugin questions: #taxjar-developers (Slack)
- Buildkite access: ops-team@taxjar.com

**Documentation:**
- TaxJar Engineering Guide: https://trailhead.corp.stripe.com/docs/taxjar-engineering-guide/
- Buildkite: https://buildkite.com/taxjar-internal
