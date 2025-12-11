"""Tests for version validators."""
import pytest
from unittest.mock import Mock
from taxjar_release.validators import VersionValidator
from tests.fixtures.plugin_files import generate_plugin_header
from tests.fixtures.readme_files import generate_readme
from tests.fixtures.changelog_files import generate_changelog


class TestVersionExtraction:
    """Tests for version extraction methods."""

    def test_extract_plugin_version(self):
        """Test extracting version from plugin header."""
        content = generate_plugin_header(version='4.2.0')
        version = VersionValidator._extract_plugin_version(content)
        assert version == '4.2.0'

    def test_extract_plugin_version_property(self):
        """Test extracting $version property."""
        content = generate_plugin_header(version='4.2.0')
        version = VersionValidator._extract_version_property(content)
        assert version == '4.2.0'

    def test_extract_readme_stable_tag(self):
        """Test extracting stable tag from readme."""
        content = generate_readme(stable_tag='4.2.0')
        version = VersionValidator._extract_readme_stable(content)
        assert version == '4.2.0'

    def test_extract_wc_tested(self):
        """Test extracting WC tested up to."""
        content = generate_plugin_header(wc_tested='9.5.0')
        version = VersionValidator._extract_wc_tested(content)
        assert version == '9.5.0'

    def test_extract_wc_requires(self):
        """Test extracting WC requires at least."""
        content = generate_plugin_header(wc_requires='8.0.0')
        version = VersionValidator._extract_wc_requires(content)
        assert version == '8.0.0'

    def test_extract_minimum_wc_property(self):
        """Test extracting minimum WooCommerce version property."""
        content = generate_plugin_header(wc_requires='8.0.0')
        version = VersionValidator._extract_minimum_wc_property(content)
        assert version == '8.0.0'

    def test_extract_missing_version(self):
        """Test extracting from content without version."""
        content = '<?php // empty file'
        version = VersionValidator._extract_plugin_version(content)
        assert version is None


class TestVersionValidation:
    """Tests for validation logic."""

    @pytest.fixture
    def mock_git(self):
        """Create mock git client."""
        return Mock()

    @pytest.fixture
    def mock_buildkite(self):
        """Create mock buildkite client."""
        return Mock()

    def test_skip_when_version_unchanged(self, mock_git, mock_buildkite):
        """Test validation skipped when version hasn't changed."""
        plugin_content = generate_plugin_header(version='4.1.0')
        mock_git.get_file_content.return_value = plugin_content

        validator = VersionValidator(mock_git, mock_buildkite)
        result = validator.validate()

        assert result.success is True
        assert len(result.errors) == 0

    def test_version_mismatch_fails(self, mock_git, mock_buildkite):
        """Test validation fails on version mismatch."""
        plugin_content = generate_plugin_header(version='4.2.0')
        readme_content = generate_readme(stable_tag='4.1.0')
        changelog_content = generate_changelog(version='4.2.0')

        def get_content(filepath, ref='HEAD'):
            if ref == 'origin/master':
                return generate_plugin_header(version='4.1.0')
            if 'taxjar-woocommerce.php' in filepath:
                return plugin_content
            if 'readme.txt' in filepath:
                return readme_content
            if 'CHANGELOG' in filepath:
                return changelog_content
            return ''

        mock_git.get_file_content.side_effect = get_content

        validator = VersionValidator(mock_git, mock_buildkite)
        result = validator.validate()

        assert result.success is False
        assert any('mismatch' in e.lower() for e in result.errors)

    def test_all_versions_match_passes(self, mock_git, mock_buildkite):
        """Test validation passes when all versions match."""
        plugin_content = generate_plugin_header(version='4.2.0')
        readme_content = generate_readme(stable_tag='4.2.0')
        changelog_content = generate_changelog(version='4.2.0')

        def get_content(filepath, ref='HEAD'):
            if ref == 'origin/master':
                return generate_plugin_header(version='4.1.0')
            if 'taxjar-woocommerce.php' in filepath:
                return plugin_content
            if 'readme.txt' in filepath:
                return readme_content
            if 'CHANGELOG' in filepath:
                return changelog_content
            return ''

        mock_git.get_file_content.side_effect = get_content

        validator = VersionValidator(mock_git, mock_buildkite)
        result = validator.validate()

        assert result.success is True
        assert len(result.errors) == 0
