# Local Testing - Quick Reference

## Most Common Commands

```bash
# Run specific test (WC 8.9.1)
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# Run one test method
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php --filter=test_name

# Debug with shell
./local-test.sh --shell

# View logs
./local-test.sh --logs

# Clean up
./local-test.sh --clean
```

## Version Quick Picks

```bash
# WooCommerce 7.x (PHP 8.0, WP 6.0)
./local-test.sh --wc=7.9.0 --test=tests/specs/test-actions.php

# WooCommerce 8.x (PHP 8.1, WP 6.2) - THE PROBLEMATIC VERSION
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# WooCommerce 9.x (PHP 8.2, WP 6.4)
./local-test.sh --wc=9.3.3 --test=tests/specs/test-actions.php

# WooCommerce 10.x (PHP 8.3, WP 6.7)
./local-test.sh --wc=10.2.2 --test=tests/specs/test-actions.php
```

## Fast Iteration Workflow

```bash
# 1. Initial setup
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# 2. Edit files: tests/bootstrap.php, includes/*.php, tests/specs/*.php

# 3. Rerun (fast - no rebuild!)
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# 4. Repeat steps 2-3
```

## Debugging the WC 8.x Issue

```bash
# Run test to see failure
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# Open shell to investigate
./local-test.sh --shell

# Inside shell:
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce
vendor/bin/phpunit tests/specs/test-actions.php --debug
wp plugin list --allow-root
cat tests/bootstrap.php

# Exit shell and edit bootstrap.php
# Then rerun test
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
```

## Useful Paths

- **Plugin**: `/var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce`
- **WooCommerce**: `/var/www/html/wp-content/plugins/woocommerce`
- **WordPress**: `/var/www/html`
- **Test bootstrap**: `/var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce/tests/bootstrap.php`
- **WP Tests**: `/tmp/wordpress-tests-lib`

## Access Points

- **WordPress Admin**: http://localhost:8080/wp-admin (admin/password)
- **WordPress Site**: http://localhost:8080
- **MySQL**: `mysql -h 127.0.0.1 -u wordpress -pwordpress wordpress_test`

## Common Test Files

```bash
# Actions tests
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# Customer sync tests
./local-test.sh --wc=8.9.1 --test=tests/specs/test-customer-sync.php

# Integration tests
./local-test.sh --wc=8.9.1 --test=tests/specs/test-wc-taxjar-integration.php

# All tests
./local-test.sh --wc=8.9.1 --all
```

## Troubleshooting One-Liners

```bash
# Container status
docker-compose -f docker-compose.local.yml ps

# Full cleanup
docker-compose -f docker-compose.local.yml down -v && docker-compose -f docker-compose.local.yml up -d

# Restart containers
docker-compose -f docker-compose.local.yml restart

# View WordPress container logs (follow)
docker-compose -f docker-compose.local.yml logs -f wordpress

# Check port conflicts
lsof -i :8080 && lsof -i :3306

# Reinstall composer dependencies
./local-test.sh --shell
cd /var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce
rm -rf vendor composer.lock && composer install
```

## WC 8.x Bootstrap Investigation

The issue with WC 8.x is that classes don't load. Key things to check:

```bash
./local-test.sh --shell

# Check if WC_Taxjar class exists
php -r "require '/var/www/html/wp-content/plugins/taxjar-simplified-taxes-for-woocommerce/taxjar-woocommerce.php'; var_dump(class_exists('WC_Taxjar'));"

# Check hook timing
grep -n "plugins_loaded\|muplugins_loaded" tests/bootstrap.php

# Check if init() was called
grep -n "WC_Taxjar::init" taxjar-woocommerce.php

# Check what classes are loaded
grep -n "include_once\|require_once" includes/class-wc-taxjar.php | head -20
```

## Performance Tips

- **First run**: ~60 seconds (cold start)
- **Subsequent runs**: ~5 seconds (containers running)
- **After container stop**: ~30 seconds

Keep containers running between test runs for fastest iteration!

## See Also

- **Full documentation**: [LOCAL_TESTING.md](LOCAL_TESTING.md)
- **Problem context**: `/Users/kkolk/stripe/kkolk/scratch-pad/taxjar-wc-8x-debugging-state.md`
- **CI configuration**: `.buildkite/docker-compose.test.yml`
