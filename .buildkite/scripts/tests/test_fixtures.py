"""Tests for test fixtures."""
import pytest
from tests.fixtures.plugin_files import generate_plugin_header
from tests.fixtures.readme_files import generate_readme
from tests.fixtures.changelog_files import generate_changelog


class TestPluginFileFixture:
    """Tests for plugin file fixture."""

    def test_generates_valid_php(self):
        """Test generates valid PHP header."""
        content = generate_plugin_header()
        assert '<?php' in content
        assert 'Plugin Name:' in content

    def test_version_configurable(self):
        """Test version is configurable."""
        content = generate_plugin_header(version='5.0.0')
        assert '* Version: 5.0.0' in content
        assert "static $version = '5.0.0'" in content

    def test_wc_fields_configurable(self):
        """Test WC fields are configurable."""
        content = generate_plugin_header(
            wc_tested='9.5.0',
            wc_requires='8.0.0',
        )
        assert 'WC tested up to: 9.5.0' in content
        assert 'WC requires at least: 8.0.0' in content
        assert "public static $minimum_woocommerce_version = '8.0.0'" in content


class TestReadmeFixture:
    """Tests for readme fixture."""

    def test_generates_readme_format(self):
        """Test generates WordPress readme format."""
        content = generate_readme()
        assert '=== ' in content
        assert 'Stable tag:' in content
        assert '== Changelog ==' in content

    def test_stable_tag_configurable(self):
        """Test stable tag is configurable."""
        content = generate_readme(stable_tag='5.0.0')
        assert 'Stable tag: 5.0.0' in content

    def test_changelog_entry_included(self):
        """Test changelog entry included."""
        content = generate_readme(stable_tag='5.0.0')
        assert '= 5.0.0' in content


class TestChangelogFixture:
    """Tests for changelog fixture."""

    def test_generates_markdown(self):
        """Test generates markdown format."""
        content = generate_changelog()
        assert '# ' in content

    def test_version_configurable(self):
        """Test version is configurable."""
        content = generate_changelog(version='5.0.0')
        assert '# 5.0.0' in content
