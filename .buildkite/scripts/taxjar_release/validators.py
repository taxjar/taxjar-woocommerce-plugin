"""Version validation logic."""
import re
from dataclasses import dataclass, field
from typing import List, Optional

from .clients.git import GitClient
from .clients.buildkite import BuildkiteClient


@dataclass
class ValidationResult:
    """Result of version validation."""
    success: bool
    errors: List[str] = field(default_factory=list)
    warnings: List[str] = field(default_factory=list)

    @property
    def failed(self) -> bool:
        """Return True if validation failed."""
        return not self.success


class VersionValidator:
    """Validates version consistency across plugin files."""

    PLUGIN_FILE = 'taxjar-woocommerce.php'
    README_FILE = 'readme.txt'
    CHANGELOG_FILE = 'CHANGELOG.md'

    def __init__(self, git_client: GitClient, buildkite_client: BuildkiteClient):
        """
        Initialize VersionValidator.

        Args:
            git_client: Git client for file operations
            buildkite_client: Buildkite client for annotations
        """
        self.git = git_client
        self.buildkite = buildkite_client

    @staticmethod
    def _extract_plugin_version(content: str) -> Optional[str]:
        """Extract version from plugin header."""
        match = re.search(r'\* Version:\s*(\d+\.\d+\.\d+)', content)
        return match.group(1) if match else None

    @staticmethod
    def _extract_version_property(content: str) -> Optional[str]:
        """Extract $version property."""
        match = re.search(r"(?:public\s+)?static \$version = '(\d+\.\d+\.\d+)'", content)
        return match.group(1) if match else None

    @staticmethod
    def _extract_readme_stable(content: str) -> Optional[str]:
        """Extract stable tag from readme.txt."""
        match = re.search(r'Stable tag:\s*(\d+\.\d+\.\d+)', content)
        return match.group(1) if match else None

    @staticmethod
    def _extract_wc_tested(content: str) -> Optional[str]:
        """Extract WC tested up to."""
        match = re.search(r'WC tested up to:\s*(\d+\.\d+\.\d+)', content)
        return match.group(1) if match else None

    @staticmethod
    def _extract_wc_requires(content: str) -> Optional[str]:
        """Extract WC requires at least."""
        match = re.search(r'WC requires at least:\s*(\d+\.\d+\.\d+)', content)
        return match.group(1) if match else None

    @staticmethod
    def _extract_minimum_wc_property(content: str) -> Optional[str]:
        """Extract $minimum_woocommerce_version property."""
        match = re.search(r"(?:public\s+)?static \$minimum_woocommerce_version = '(\d+\.\d+\.\d+)'", content)
        return match.group(1) if match else None
