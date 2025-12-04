# Local Testing Guide

Fast local development environment for running WooCommerce PHPUnit tests against different WooCommerce versions.

## Overview

This setup mirrors the Buildkite CI environment but is optimized for rapid local iteration:

- **Fast**: Changes to test files are immediately available (no rebuild needed)
- **Flexible**: Easily switch between WooCommerce versions
- **Debuggable**: Containers stay running for inspection
- **Isolated**: Uses Docker to avoid polluting your local environment

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- TaxJar API token configured at `~/.taxjar.token`

### Run Your First Test

```bash
# Run a specific test with WooCommerce 8.9.1
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
```

That's it! The script will:
1. Start WordPress + MySQL containers
2. Install WooCommerce 8.9.1
3. Set up the test environment
4. Run your test
5. Show results

## Usage Examples

### Run Specific Test File

```bash
# Test with WooCommerce 8.9.1 (PHP 8.1, WP 6.2)
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# Test with WooCommerce 9.3.3 (PHP 8.2, WP 6.4)
./local-test.sh --wc=9.3.3 --test=tests/specs/test-customer-sync.php
```

### Run Specific Test Method

```bash
# Run just one test method using PHPUnit filter
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php --filter=test_get_taxjar_api_key
```

### Run All Tests

```bash
# Run entire test suite
./local-test.sh --wc=8.9.1 --all
```

### Set Up Environment Without Running Tests

```bash
# Just set up the environment for manual testing
./local-test.sh --wc=8.9.1 --setup

# Then access WordPress at http://localhost:8080
# Admin login: admin / password
```

### Debugging

```bash
# View WordPress logs
./local-test.sh --logs

# Open shell in WordPress container
./local-test.sh --shell

# Once in shell, you can:
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce
vendor/bin/phpunit tests/specs/test-actions.php --filter=test_name
```

### Clean Up

```bash
# Stop and remove containers
./local-test.sh --clean
```

## Version Matrix

The script automatically selects the correct PHP and WordPress versions:

| WooCommerce | PHP   | WordPress |
|-------------|-------|-----------|
| 7.9.0       | 8.0   | 6.0       |
| 8.9.1       | 8.1   | 6.2       |
| 9.3.3       | 8.2   | 6.4       |
| 10.2.2      | 8.3   | 6.7       |

Example:
```bash
./local-test.sh --wc=7.9.0 --test=tests/specs/test-actions.php
# Automatically uses PHP 8.0 and WordPress 6.0
```

## Fast Iteration Workflow

The key to fast iteration is that containers stay running and code is mounted as a volume:

```bash
# 1. Initial setup (takes ~60 seconds)
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# 2. Edit your test file: tests/specs/test-actions.php
# 3. Edit your plugin code: includes/class-wc-taxjar.php
# 4. Edit bootstrap: tests/bootstrap.php

# 5. Run again (takes ~5 seconds - no rebuild!)
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# 6. Repeat steps 2-5 as needed
```

**Why it's fast:**
- Containers are already running
- WordPress is already installed
- WooCommerce is already downloaded
- Dependencies are already installed
- Only the test runs

## Debugging Failed Tests

### View Test Output

Test output is shown directly in your terminal with colors.

### View WordPress Debug Log

```bash
# Option 1: View logs from host
./local-test.sh --logs

# Option 2: View from inside container
./local-test.sh --shell
cat /var/www/html/wp-content/debug.log
```

### Inspect WordPress

```bash
# Access WordPress admin interface
open http://localhost:8080/wp-admin

# Login: admin / password
```

### Interactive Debugging

```bash
# Open shell in container
./local-test.sh --shell

# Navigate to plugin directory
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce

# Run specific test with verbose output
vendor/bin/phpunit tests/specs/test-actions.php --filter=test_name --debug

# Check WordPress installation
wp plugin list --allow-root
wp option get woocommerce_taxjar-integration_settings --allow-root

# Inspect database
mysql -h mysql -u wordpress -pwordpress wordpress_test
```

### Check Container Status

```bash
# View container status
docker-compose -f docker-compose.local.yml ps

# View MySQL logs
docker-compose -f docker-compose.local.yml logs mysql

# View WordPress logs
docker-compose -f docker-compose.local.yml logs wordpress
```

## Troubleshooting

### Container Won't Start

```bash
# Check if ports are already in use
lsof -i :8080  # WordPress
lsof -i :3306  # MySQL

# If ports are in use, stop the containers using them or change ports in docker-compose.local.yml
```

### Tests Can't Find WooCommerce

The bootstrap.php expects WooCommerce to be in a specific location. The setup script creates symlinks:
- `/var/www/html/wp-content/plugins/taxjar-woocommerce-plugin` -> Main plugin
- `./woocommerce` -> WooCommerce plugin (inside plugin directory)

If tests fail to find WooCommerce:

```bash
./local-test.sh --shell

# Check symlinks
ls -la /var/www/html/wp-content/plugins/taxjar-woocommerce-plugin
ls -la /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce/woocommerce

# Verify WooCommerce is installed
ls -la /var/www/html/wp-content/plugins/woocommerce
```

### "Class Not Found" Errors

This usually means the TaxJar plugin classes didn't load. This is the WC 8.x issue you're debugging.

To investigate:

```bash
./local-test.sh --shell
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce

# Run test with bootstrap debugging
vendor/bin/phpunit tests/specs/test-actions.php --filter=test_name

# Check if plugin is activated
wp plugin list --allow-root

# Check plugin initialization
grep "WC_Taxjar::init" taxjar-woocommerce.php
```

### Database Issues

```bash
# Reset database
docker-compose -f docker-compose.local.yml down -v
./local-test.sh --wc=8.9.1 --setup
```

### Composer Dependencies Issues

```bash
./local-test.sh --shell
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce

# Remove and reinstall dependencies
rm -rf vendor composer.lock
composer install
```

## File Structure

```
taxjar-woocommerce-plugin/
├── docker-compose.local.yml   # Docker Compose configuration for local testing
├── local-test.sh               # Test runner script
├── LOCAL_TESTING.md            # This file
├── tests/
│   ├── bootstrap.php           # Test bootstrap (you'll edit this)
│   ├── phpunit.xml             # PHPUnit configuration
│   └── specs/                  # Test files
└── test-results/               # Test output (created by script)
```

## Differences from CI

| Feature           | CI (Buildkite)        | Local                    |
|-------------------|-----------------------|--------------------------|
| Container reuse   | No                    | Yes (fast iteration)     |
| Code mounting     | Copy                  | Volume (live editing)    |
| Container cleanup | Auto-remove           | Manual (for inspection)  |
| Log collection    | Upload to Buildkite   | View with --logs         |
| Artifact upload   | Yes                   | No                       |
| Test analytics    | Buildkite Analytics   | Terminal output          |

## Tips

1. **Keep containers running**: Between test runs, leave containers running for fastest iteration

2. **Use --filter**: When debugging a specific test, use `--filter=test_method_name` to run just that test

3. **Edit and rerun**: You can edit any file (plugin code, tests, bootstrap) and rerun immediately

4. **Switch WC versions**: To test against a different WC version, just change `--wc=X.X.X`

5. **Access WordPress**: The WordPress admin is available at http://localhost:8080/wp-admin for manual testing

6. **Multiple terminals**: Keep one terminal with logs open (`./local-test.sh --logs --follow` doesn't exist, but you can use `docker-compose -f docker-compose.local.yml logs -f wordpress`)

## Environment Variables

The script automatically loads your TaxJar API token from `~/.taxjar.token`.

Format of `~/.taxjar.token`:
```bash
export TAXJAR_API_TOKEN=your_token_here
```

You can also set other environment variables:

```bash
# Custom WooCommerce version
WC_VERSION=8.9.1 ./local-test.sh --test=tests/specs/test-actions.php

# Custom PHP version (overrides auto-detection)
PHP_VERSION=8.1 WP_VERSION=6.2 ./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
```

## Advanced Usage

### Run Tests from Inside Container

```bash
# Start and enter container
./local-test.sh --wc=8.9.1 --setup
./local-test.sh --shell

# Inside container
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce

# Run tests manually with custom options
vendor/bin/phpunit \
    --configuration tests/phpunit.xml \
    --filter=test_method_name \
    --debug \
    --verbose \
    tests/specs/test-actions.php
```

### Test Specific WC/PHP/WP Combination

```bash
# Override auto-detected versions
WC_VERSION=8.9.1 PHP_VERSION=8.2 WP_VERSION=6.4 \
./local-test.sh --test=tests/specs/test-actions.php
```

### Persist Test Results

```bash
# Test results are saved to ./test-results/
./local-test.sh --wc=8.9.1 --all

# View saved results
cat test-results/phpunit-output.log
```

## Integration with Buildkite

This local setup mirrors the Buildkite CI configuration in `.buildkite/docker-compose.test.yml`.

Key differences:
- Local uses `docker-compose.local.yml` (optimized for development)
- CI uses `.buildkite/docker-compose.test.yml` (optimized for CI)
- Both run the same tests with the same dependencies

To replicate a CI failure locally:
1. Note the WC/PHP/WP versions from the failing CI job
2. Run the same test locally: `./local-test.sh --wc=X.X.X --test=path/to/test.php`
3. Debug using the shell: `./local-test.sh --shell`

## Getting Help

If you run into issues:

1. Check container status: `docker-compose -f docker-compose.local.yml ps`
2. View logs: `./local-test.sh --logs`
3. Open shell: `./local-test.sh --shell`
4. Clean up and retry: `./local-test.sh --clean && ./local-test.sh --wc=8.9.1 --setup`

## Performance

Typical execution times on a modern Mac:

- **First run (cold start)**: ~60 seconds
  - Download WordPress image: ~10s
  - Start containers: ~10s
  - Install WordPress: ~15s
  - Download WooCommerce: ~10s
  - Install dependencies: ~10s
  - Run tests: ~5s

- **Subsequent runs (warm)**: ~5 seconds
  - Containers already running
  - WordPress already installed
  - Dependencies already installed
  - Only test execution time

- **Container startup (after stop)**: ~30 seconds
  - Start containers: ~10s
  - Wait for WordPress: ~10s
  - Verify setup: ~5s
  - Run tests: ~5s

## Next Steps

Now that you have a fast local testing environment, you can:

1. **Debug the WC 8.x issue**:
   ```bash
   ./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
   ```

2. **Iterate on bootstrap.php fixes**:
   - Edit `tests/bootstrap.php`
   - Rerun tests immediately (no rebuild needed)

3. **Compare with working versions**:
   ```bash
   # Test WC 7.x (working)
   ./local-test.sh --wc=7.9.0 --test=tests/specs/test-actions.php

   # Test WC 8.x (failing)
   ./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
   ```

4. **Validate your fix works across all versions**:
   ```bash
   for version in 7.9.0 8.9.1 9.3.3 10.2.2; do
       echo "Testing WC $version..."
       ./local-test.sh --wc=$version --all || echo "Failed: $version"
   done
   ```

Happy testing!
