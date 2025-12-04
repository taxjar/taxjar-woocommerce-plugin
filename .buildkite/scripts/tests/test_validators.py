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

    def test_extract_missing_version(self):
        """Test extracting from content without version."""
        content = '<?php // empty file'
        version = VersionValidator._extract_plugin_version(content)
        assert version is None
