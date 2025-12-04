"""Version detection and WordPress.org integration."""
import os
import re
from dataclasses import dataclass
from typing import Optional

from .clients.git import GitClient
from .clients.buildkite import BuildkiteClient
from .clients.wordpress import WordPressClient


@dataclass
class VersionDetectionResult:
    """Result of version detection."""
    success: bool
    version: Optional[str]
    exists_on_wporg: bool
    should_skip: bool
    message: str


class VersionDetector:
    """Detects version and checks WordPress.org."""

    PLUGIN_FILE = 'taxjar-woocommerce.php'
    PLUGIN_SLUG = 'taxjar-simplified-taxes-for-woocommerce'

    def __init__(
        self,
        git_client: GitClient,
        buildkite_client: BuildkiteClient,
        wordpress_client: WordPressClient,
    ):
        """
        Initialize VersionDetector.

        Args:
            git_client: Git client for file operations
            buildkite_client: Buildkite client for metadata
            wordpress_client: WordPress.org API client
        """
        self.git = git_client
        self.buildkite = buildkite_client
        self.wordpress = wordpress_client

    def detect(self) -> VersionDetectionResult:
        """
        Detect version and check WordPress.org.

        Returns:
            VersionDetectionResult with detection status
        """
        # Extract version from plugin file
        version = self._extract_version()

        if not version:
            return VersionDetectionResult(
                success=False,
                version=None,
                exists_on_wporg=False,
                should_skip=False,
                message='Failed to extract version from plugin file',
            )

        print(f'Detected version: {version}')

        # Check if version exists on WordPress.org
        exists = self.wordpress.version_exists(self.PLUGIN_SLUG, version)

        if exists:
            message = f'Version {version} already exists on WordPress.org - skipping release'
            print(f'+++ {message}')

            self.buildkite.set_metadata('SKIP_RELEASE', 'true')
            self.buildkite.annotate(
                f'Version {version} already deployed to WordPress.org',
                style='info',
                context='version-check',
            )

            return VersionDetectionResult(
                success=True,
                version=version,
                exists_on_wporg=True,
                should_skip=True,
                message=message,
            )

        # New version - export for downstream steps
        print(f'+++ New version {version} detected - proceeding with release')
        self._export_version(version)

        return VersionDetectionResult(
            success=True,
            version=version,
            exists_on_wporg=False,
            should_skip=False,
            message=f'New version {version} ready for release',
        )

    def _extract_version(self) -> Optional[str]:
        """Extract version from plugin file."""
        content = self.git.get_file_content(self.PLUGIN_FILE)
        match = re.search(r'\* Version:\s*(\d+\.\d+\.\d+)', content)
        return match.group(1) if match else None

    def _export_version(self, version: str) -> None:
        """Export VERSION for downstream pipeline steps."""
        # Set environment variable
        os.environ['VERSION'] = version

        # Write to BUILDKITE_ENV_FILE if available
        env_file = os.getenv('BUILDKITE_ENV_FILE')
        if env_file:
            try:
                with open(env_file, 'a') as f:
                    f.write(f'VERSION={version}\n')
            except Exception:
                pass

        # Set Buildkite meta-data for reliability
        self.buildkite.set_metadata('release-version', version)

        print(f'Exported VERSION={version}')
