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

    def validate(self) -> ValidationResult:
        """
        Run all validation checks.

        Returns:
            ValidationResult with success status, errors, and warnings
        """
        errors = []
        warnings = []

        # Check if version changed
        if not self._version_changed():
            print('✓ Version unchanged - skipping validation')
            return ValidationResult(success=True)

        print('Version change detected - running validation...')

        # Get current file contents
        plugin_content = self.git.get_file_content(self.PLUGIN_FILE)
        readme_content = self.git.get_file_content(self.README_FILE)

        try:
            changelog_content = self.git.get_file_content(self.CHANGELOG_FILE)
        except Exception:
            changelog_content = ''

        # Extract versions
        plugin_version = self._extract_plugin_version(plugin_content)
        version_property = self._extract_version_property(plugin_content)
        readme_stable = self._extract_readme_stable(readme_content)

        # Critical checks
        if version_property and version_property != plugin_version:
            errors.append(
                f'Version mismatch: header={plugin_version}, '
                f'$version property={version_property}'
            )

        if readme_stable != plugin_version:
            errors.append(
                f'Version mismatch: header={plugin_version}, '
                f'readme stable tag={readme_stable}'
            )

        # Changelog checks
        if plugin_version and f'# {plugin_version}' not in changelog_content:
            errors.append(f'Missing CHANGELOG.md entry for version {plugin_version}')

        if plugin_version and f'= {plugin_version}' not in readme_content:
            errors.append(f'Missing readme.txt changelog entry for version {plugin_version}')

        # Report via Buildkite
        self._report_results(errors, warnings)

        return ValidationResult(
            success=len(errors) == 0,
            errors=errors,
            warnings=warnings,
        )

    def _version_changed(self) -> bool:
        """Check if version changed compared to master."""
        try:
            # On master branch, always run validation (release pipeline case)
            current_branch = self.git.get_current_branch()
            if current_branch == 'master':
                return True

            current = self.git.get_file_content(self.PLUGIN_FILE, 'HEAD')
            master = self.git.get_file_content(self.PLUGIN_FILE, 'origin/master')

            current_version = self._extract_plugin_version(current)
            master_version = self._extract_plugin_version(master)

            return current_version != master_version
        except Exception:
            return True

    def _report_results(self, errors: List[str], warnings: List[str]) -> None:
        """Report results via Buildkite annotations."""
        if errors:
            message = '## ❌ Version Validation Failed\n\n'
            for error in errors:
                message += f'- {error}\n'
            self.buildkite.annotate(message, style='error', context='version-validation')

        if not errors:
            print('✓ Version validation passed')
