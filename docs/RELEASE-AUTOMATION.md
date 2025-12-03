# Release Automation

> **Status**: Phase 1 Complete (December 2024)
> This documentation describes the automated release system implemented in Phase 1. Full WC version matrix testing (8.x, 10.x) is planned for Phase 2.

## Overview

The TaxJar WooCommerce plugin uses automated Buildkite pipelines for releases to WordPress.org. Engineers manually create version bump PRs, and automation handles validation, testing, and deployment.

## Architecture

The release automation uses two Buildkite pipelines configured with GitHub webhooks:

1. **CI Pipeline** - Triggers on all PRs and branches
   - Runs linting and tests
   - Validates version consistency for version-changing PRs

2. **Release Pipeline** - Triggers only on master branch commits
   - Automatically detects new versions
   - Deploys to GitHub and WordPress.org

**Pipeline Configuration**: Both pipelines are configured in the Buildkite dashboard to automatically trigger on GitHub events (no GitHub Actions workflows required).

## How It Works

### PR Validation (Every PR)

When you create a PR, Buildkite automatically runs `.buildkite/scripts/validate-version.sh` which:

1. Detects if the PR changes the version number
2. If yes, validates that ALL version fields are consistent:
   - ✅ `taxjar-woocommerce.php` Version tag
   - ✅ `taxjar-woocommerce.php` `$version` property
   - ✅ `readme.txt` Stable tag
   - ✅ `CHANGELOG.md` has entry for new version
   - ✅ `readme.txt` has changelog entry

3. Checks optional fields (validated but non-blocking):
   - ⚠️ WC tested up to
   - ⚠️ WP tested up to
   - ⚠️ WC requires at least
   - ⚠️ `$minimum_woocommerce_version` property

   **Optional fields show warnings but won't block the merge.**

**Critical checks BLOCK merge. Warnings don't.**

### Automatic Release (Master Builds)

When a version bump PR merges to master, the Buildkite Release pipeline automatically triggers via GitHub webhook and:

1. **Detects** if version is new (checks WordPress.org API)
   - If version already exists, pipeline skips with success annotation
   - If version is new, continues with deployment
2. **Tests** - runs full test suite (WC 7.x and 9.x; 8.x and 10.x planned)
3. **GitHub** - creates release and tag
4. **SVN** - deploys to WordPress.org trunk and creates tag
5. **Verifies** - checks WordPress.org shows new version

**This happens automatically on merge. No manual steps required.**

## Creating a Release

### Step 1: Prepare Version Bump PR

Update these files with new version (e.g., `4.2.0`):

**CHANGELOG.md** - Add entry at top:
```markdown
# 4.2.0 - 2025-12-03

* Feature: Added new functionality
* Fix: Resolved issue with...
```

**readme.txt** - Update these lines:
```
Stable tag: 4.2.0
WC tested up to: 9.5.0
Tested up to: 6.7.0
WC requires at least: 7.5.0

== Changelog ==

= 4.2.0 - 2025-12-03 =
* Feature: Added new functionality
* Fix: Resolved issue with...
```

**taxjar-woocommerce.php** - Update these lines:
```php
/**
 * Version: 4.2.0
 * WC tested up to: 9.5.0
 * WC requires at least: 7.5.0
 */

class WC_Taxjar_Integration {
    public $version = '4.2.0';
    public $minimum_woocommerce_version = '7.5.0';
}
```

### Step 2: Create PR

```bash
git checkout -b release/v4.2.0
git add CHANGELOG.md readme.txt taxjar-woocommerce.php
git commit -m "Preparing for 4.2.0 release"
git push origin release/v4.2.0
```

Create PR on GitHub. Buildkite will automatically validate version consistency.

### Step 3: Review and Merge

If validation passes (green check):
1. Review PR
2. Approve PR
3. Merge to master

**That's it! Automation handles the rest.**

### Step 4: Monitor Release Build

Watch Buildkite "Release" pipeline on master:
- Should trigger automatically within ~1 minute
- Takes ~15-20 minutes total
- Deploys to WordPress.org automatically
- You'll get notification when complete

## Troubleshooting

### PR Validation Fails

**"Version mismatch" error:**
- Check all version numbers match exactly
- Update any fields showing in red
- Push new commit

**"Missing CHANGELOG entry" error:**
- Add `# 4.2.0` entry to CHANGELOG.md
- Add `= 4.2.0` entry to readme.txt changelog section

### Release Pipeline Fails

**Tests fail:**
- Fix failing tests on master
- Create hotfix PR
- Re-merge to trigger new release build

**GitHub release fails:**
- Check GitHub API status
- Retry build in Buildkite

**SVN deployment fails:**
- Check WordPress.org SVN status
- Verify credentials in Buildkite secrets
- Retry build (will resume from GitHub release)

**Diagnosing failures:**

Check Buildkite logs for specific error messages. Common issues:

```bash
# Check detected version
buildkite-agent meta-data get "release-version"

# Verify GitHub token permissions
GH_HOST=github.com gh auth status

# Test SVN credentials
svn info https://plugins.svn.wordpress.org/taxjar-simplified-taxes-for-woocommerce/trunk
```

### Version Already Exists

If you see "Version already exists on WordPress.org":
- You may have already released this version
- Check https://wordpress.org/plugins/taxjar-simplified-taxes-for-woocommerce/
- If release is incomplete, manually complete SVN steps from Confluence docs

## Manual Release (Emergency Fallback)

If automation is completely down, you can release manually:

**1. Create GitHub release:**
```bash
GH_HOST=github.com gh release create 4.2.0 --target master --title "4.2.0" --notes ""
```

**2. Deploy to WordPress.org SVN:**
```bash
# Checkout SVN repository
svn checkout https://plugins.svn.wordpress.org/taxjar-simplified-taxes-for-woocommerce

# Follow remaining manual steps from Confluence
```

**Full manual process:** https://confluence.corp.stripe.com/spaces/PI/pages/362618363/WooCommerce+Release+Process

## Buildkite Configuration

### Pipeline Setup in Buildkite

**CI Pipeline** (taxjar-woocommerce-plugin):
- Repository: `github.com/taxjar/taxjar-woocommerce-plugin`
- Upload pipeline from: `.buildkite/pipeline.yml`
- Branch filter: All branches
- Webhooks: Enable for pull requests and branch commits
- Provides: Linting, testing, version validation

**Release Pipeline** (taxjar-woocommerce-plugin-release):
- Repository: `github.com/taxjar/taxjar-woocommerce-plugin`
- Upload pipeline from: `.buildkite/pipeline-release.yml`
- Branch filter: `master` only
- Webhooks: Enable for branch commits (not PRs)
- Provides: Automated deployment to GitHub and WordPress.org

Both pipelines require GitHub webhook integration configured in Buildkite dashboard settings.

### Pipelines

- **CI** (`.buildkite/pipeline.yml`) - Runs on all PRs and branches
- **Release** (`.buildkite/pipeline-release.yml`) - Runs on master only

**Required Secrets (in Buildkite):**
- `GITHUB_TOKEN` - For gh CLI
- `WORDPRESS_SVN_USERNAME` - From 1Password "TaxJar - Developers"
- `WORDPRESS_SVN_PASSWORD` - From 1Password "TaxJar - Developers"

## Benefits

✅ **Prevents errors** - Validates version consistency before merge
✅ **Saves time** - 20-30 minutes saved per release
✅ **Reliable** - Retry logic handles transient failures
✅ **Auditable** - All releases tracked in Buildkite with logs
✅ **Safe** - WordPress.org API prevents duplicate releases

## Questions?

Contact the DevOps team or check #taxjar-engineering on Slack.
