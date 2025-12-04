# Fast Local Testing Environment

A Docker-based environment for rapid WooCommerce PHPUnit test iteration. Mirrors Buildkite CI but optimized for local development.

## Quick Start

```bash
# Run a test with WooCommerce 8.9.1
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php
```

## Documentation

- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Quick command reference (start here!)
- **[LOCAL_TESTING.md](LOCAL_TESTING.md)** - Comprehensive guide with troubleshooting
- `./local-test.sh --help` - Built-in help

## Common Commands

```bash
# Run specific test
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php

# Run one test method
./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php --filter=test_name

# Debug with shell
./local-test.sh --shell

# Test all WC versions
./test-all-versions.sh --test=tests/specs/test-actions.php

# Clean up
./local-test.sh --clean
```

## Why This Exists

CI iteration is too slow for debugging. This environment:
- Starts in ~60 seconds (cold), ~5 seconds (warm)
- Allows instant code changes without rebuild
- Keeps containers running for inspection
- Mirrors CI environment exactly

## Files

- `docker-compose.local.yml` - Docker configuration
- `local-test.sh` - Main test runner
- `test-all-versions.sh` - Test across all WC versions
- `LOCAL_TESTING.md` - Full documentation
- `QUICK_REFERENCE.md` - Quick commands

## Version Matrix

| WooCommerce | PHP | WordPress | Command |
|-------------|-----|-----------|---------|
| 7.9.0       | 8.0 | 6.0       | `--wc=7.9.0` |
| 8.9.1       | 8.1 | 6.2       | `--wc=8.9.1` ⚠️ Problematic |
| 9.3.3       | 8.2 | 6.4       | `--wc=9.3.3` |
| 10.2.2      | 8.3 | 6.7       | `--wc=10.2.2` |

## Next Steps

1. Read [QUICK_REFERENCE.md](QUICK_REFERENCE.md) for common commands
2. Run your first test: `./local-test.sh --wc=8.9.1 --test=tests/specs/test-actions.php`
3. See [LOCAL_TESTING.md](LOCAL_TESTING.md) for advanced usage and troubleshooting
