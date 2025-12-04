"""CLI interface for release automation."""
import argparse
import os
import sys
from typing import List, Optional

from .validators import VersionValidator
from .version import VersionDetector
from .github import GitHubReleaseManager
from .svn import SVNDeployManager
from .clients.git import GitClient
from .clients.buildkite import BuildkiteClient
from .clients.wordpress import WordPressClient
from .clients.subprocess_runner import SubprocessRunner


def create_parser() -> argparse.ArgumentParser:
    """Create argument parser with subcommands."""
    parser = argparse.ArgumentParser(
        description='TaxJar WooCommerce Release Automation',
        prog='release-tool',
    )

    subparsers = parser.add_subparsers(dest='command', required=True)

    # validate-version
    subparsers.add_parser(
        'validate-version',
        help='Validate version consistency in PR',
    )

    # detect-version
    subparsers.add_parser(
        'detect-version',
        help='Detect version and check WordPress.org',
    )

    # github-release
    github_parser = subparsers.add_parser(
        'github-release',
        help='Create GitHub release',
    )
    github_parser.add_argument(
        '--version',
        help='Version to release (uses VERSION env if not provided)',
    )

    # svn-deploy
    svn_parser = subparsers.add_parser(
        'svn-deploy',
        help='Deploy to WordPress.org SVN',
    )
    svn_parser.add_argument(
        '--version',
        help='Version to deploy (uses VERSION env if not provided)',
    )

    return parser


def main(argv: Optional[List[str]] = None) -> int:
    """
    Main entry point.

    Args:
        argv: Command line arguments (uses sys.argv if not provided)

    Returns:
        Exit code (0 for success, 1 for failure)
    """
    parser = create_parser()
    args = parser.parse_args(argv)

    try:
        if args.command == 'validate-version':
            return cmd_validate_version()

        elif args.command == 'detect-version':
            return cmd_detect_version()

        elif args.command == 'github-release':
            version = args.version or os.getenv('VERSION')
            return cmd_github_release(version)

        elif args.command == 'svn-deploy':
            version = args.version or os.getenv('VERSION')
            return cmd_svn_deploy(version)

    except Exception as e:
        print(f'ERROR: {e}', file=sys.stderr)
        return 1

    return 0


def cmd_validate_version() -> int:
    """Run version validation."""
    git_client = GitClient()
    buildkite_client = BuildkiteClient()

    validator = VersionValidator(git_client, buildkite_client)
    result = validator.validate()

    return 0 if result.success else 1


def cmd_detect_version() -> int:
    """Run version detection."""
    git_client = GitClient()
    buildkite_client = BuildkiteClient()
    wordpress_client = WordPressClient()

    detector = VersionDetector(git_client, buildkite_client, wordpress_client)
    result = detector.detect()

    return 0 if result.success else 1


def cmd_github_release(version: Optional[str]) -> int:
    """Create GitHub release."""
    if not version:
        print('ERROR: VERSION not provided', file=sys.stderr)
        return 1

    runner = SubprocessRunner()
    manager = GitHubReleaseManager(runner=runner)
    manager.create_release(version)

    return 0


def cmd_svn_deploy(version: Optional[str]) -> int:
    """Deploy to WordPress.org SVN."""
    if not version:
        print('ERROR: VERSION not provided', file=sys.stderr)
        return 1

    username = os.getenv('WORDPRESS_SVN_USERNAME')
    password = os.getenv('WORDPRESS_SVN_PASSWORD')

    if not username or not password:
        print('ERROR: WORDPRESS_SVN_USERNAME or WORDPRESS_SVN_PASSWORD not set',
              file=sys.stderr)
        return 1

    runner = SubprocessRunner()
    manager = SVNDeployManager(runner=runner)
    manager.deploy(version, username, password)

    return 0


if __name__ == '__main__':
    sys.exit(main())
