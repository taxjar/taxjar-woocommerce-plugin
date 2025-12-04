# TaxJar WooCommerce Plugin - Release Tool Guide

## Overview

The `release-tool` CLI manages TaxJar WooCommerce plugin releases. Use it for version validation, GitHub releases, and WordPress.org deployment.

```bash
release-tool validate-version   # Validate version consistency
release-tool detect-version     # Check if version exists on WordPress.org
release-tool github-release     # Create GitHub release
release-tool svn-deploy         # Deploy to WordPress.org SVN
```

## Quick Reference

### Validate Version Consistency
```bash
cd /path/to/taxjar-woocommerce-plugin
.buildkite/scripts/release-tool validate-version
```

Checks version numbers match across plugin files. Run before submitting PRs that bump versions.

### Create GitHub Release
```bash
export GITHUB_TOKEN=your_token
export VERSION=4.2.0
.buildkite/scripts/release-tool github-release
```

Creates GitHub release and git tag. Requires `GITHUB_TOKEN` with `repo` scope.

### Deploy to WordPress.org
```bash
export WORDPRESS_SVN_USERNAME=your_username
export WORDPRESS_SVN_PASSWORD=your_password
export VERSION=4.2.0
.buildkite/scripts/release-tool svn-deploy
```

Deploys to WordPress.org plugin repository via SVN.

## Manual Release Process

### Step 1: Bump Version

Update version in three files:

**taxjar-woocommerce.php:**
```php
/**
 * Version: 4.2.0
 */
class WC_Taxjar_Integration {
    public $version = '4.2.0';
}
```

**readme.txt:**
```
Stable tag: 4.2.0

== Changelog ==

= 4.2.0 =
* Your changes here
```

**CHANGELOG.md:**
```markdown
# 4.2.0 - 2025-12-04

* Your changes here
```

### Step 2: Validate

```bash
.buildkite/scripts/release-tool validate-version
```

Ensures all version numbers match and changelog entries exist.

### Step 3: Check WordPress.org

```bash
.buildkite/scripts/release-tool detect-version
```

Confirms version doesn't already exist on WordPress.org.

### Step 4: Create GitHub Release

```bash
export GITHUB_TOKEN=your_token
export VERSION=4.2.0
.buildkite/scripts/release-tool github-release
```

### Step 5: Deploy to WordPress.org

```bash
export WORDPRESS_SVN_USERNAME=your_username
export WORDPRESS_SVN_PASSWORD=your_password
export VERSION=4.2.0
.buildkite/scripts/release-tool svn-deploy
```

## Environment Variables

| Variable | Command | Description |
|----------|---------|-------------|
| `VERSION` | github-release, svn-deploy | Version to release (e.g., `4.2.0`) |
| `GITHUB_TOKEN` | github-release | GitHub token with `repo` scope |
| `WORDPRESS_SVN_USERNAME` | svn-deploy | WordPress.org account username |
| `WORDPRESS_SVN_PASSWORD` | svn-deploy | WordPress.org account password |

## Validation Rules

### Critical (Must Pass)
- Plugin header `* Version:` matches `$version` property
- Plugin header version matches readme.txt `Stable tag:`
- CHANGELOG.md has entry `# X.Y.Z` for the version
- readme.txt has changelog entry `= X.Y.Z`

### Warnings Only
- WC tested up to field populated
- WC requires at least field populated

## Local Development

### Install Dependencies
```bash
cd .buildkite/scripts
pip3 install -r requirements.txt
```

### Required Tools
- `gh` - GitHub CLI
- `svn` - Subversion client
- Python 3.10+

### Run Tests
```bash
cd .buildkite/scripts
PYTHONPATH=. pytest tests/ -v
```

## Troubleshooting

### Version Mismatch Error
Versions don't match across files. Check:
- `taxjar-woocommerce.php` header `* Version:`
- `taxjar-woocommerce.php` property `$version`
- `readme.txt` field `Stable tag:`

### Missing Changelog Entry
Add entry to both:
- `CHANGELOG.md` with `# X.Y.Z` heading
- `readme.txt` with `= X.Y.Z` in changelog section

### SVN Authentication Failed
Verify `WORDPRESS_SVN_USERNAME` and `WORDPRESS_SVN_PASSWORD` are correct. Test:
```bash
svn info https://plugins.svn.wordpress.org/taxjar-simplified-taxes-for-woocommerce --username YOUR_USERNAME
```

### GitHub Release Failed
Verify `GITHUB_TOKEN` has `repo` scope. Test:
```bash
export GITHUB_TOKEN=your_token
gh auth status
```

### Version Already Exists
The version is already on WordPress.org. Either:
- Bump to a new version
- Delete the existing SVN tag if re-releasing

## Retry Behavior

Network operations retry automatically:
- GitHub: 3 attempts (2, 4, 8 second delays)
- SVN: 3 attempts (5, 10, 20 second delays)

## Security Notes

- SVN password passed via stdin, never in command line
- Credentials not logged in output
- Temporary directories cleaned up after use
