"""Tests for version detector."""
import os
import pytest
from unittest.mock import Mock, patch
from taxjar_release.version import VersionDetector, VersionDetectionResult
from taxjar_release.clients.git import GitClient
from taxjar_release.clients.buildkite import BuildkiteClient
from taxjar_release.clients.wordpress import WordPressClient
from tests.fixtures.plugin_files import generate_plugin_header


class TestVersionDetector:
    """Tests for VersionDetector."""

    @pytest.fixture
    def mock_git(self):
        return Mock(spec=GitClient)

    @pytest.fixture
    def mock_buildkite(self):
        return Mock(spec=BuildkiteClient)

    @pytest.fixture
    def mock_wordpress(self):
        return Mock(spec=WordPressClient)

    def test_detect_extracts_version(self, mock_git, mock_buildkite, mock_wordpress):
        """Test version is extracted from plugin file."""
        mock_git.get_file_content.return_value = generate_plugin_header(version='4.2.0')
        mock_wordpress.version_exists.return_value = False

        detector = VersionDetector(mock_git, mock_buildkite, mock_wordpress)
        result = detector.detect()

        assert result.version == '4.2.0'
        assert result.success is True

    def test_detect_version_exists_on_wporg(self, mock_git, mock_buildkite, mock_wordpress):
        """Test detection when version exists on WordPress.org."""
        mock_git.get_file_content.return_value = generate_plugin_header(version='4.2.0')
        mock_wordpress.version_exists.return_value = True

        detector = VersionDetector(mock_git, mock_buildkite, mock_wordpress)
        result = detector.detect()

        assert result.version == '4.2.0'
        assert result.exists_on_wporg is True
        assert result.should_skip is True
        mock_buildkite.set_metadata.assert_called()

    def test_detect_new_version(self, mock_git, mock_buildkite, mock_wordpress):
        """Test detection when version is new."""
        mock_git.get_file_content.return_value = generate_plugin_header(version='4.2.0')
        mock_wordpress.version_exists.return_value = False

        detector = VersionDetector(mock_git, mock_buildkite, mock_wordpress)
        result = detector.detect()

        assert result.version == '4.2.0'
        assert result.exists_on_wporg is False
        assert result.should_skip is False

    def test_detect_exports_version(self, mock_git, mock_buildkite, mock_wordpress):
        """Test version is exported to metadata."""
        mock_git.get_file_content.return_value = generate_plugin_header(version='4.2.0')
        mock_wordpress.version_exists.return_value = False

        detector = VersionDetector(mock_git, mock_buildkite, mock_wordpress)
        detector.detect()

        mock_buildkite.set_metadata.assert_called_with('release-version', '4.2.0')

    def test_detect_fails_on_missing_version(self, mock_git, mock_buildkite, mock_wordpress):
        """Test detection fails when version cannot be extracted."""
        mock_git.get_file_content.return_value = '<?php // no version'

        detector = VersionDetector(mock_git, mock_buildkite, mock_wordpress)
        result = detector.detect()

        assert result.success is False
        assert result.version is None
