"""Tests for CLI interface."""
import pytest
from unittest.mock import Mock, patch, MagicMock
from taxjar_release.cli import main, create_parser


class TestCLI:
    """Tests for CLI interface."""

    def test_parser_has_subcommands(self):
        """Test parser has all required subcommands."""
        parser = create_parser()

        # Should not raise
        parser.parse_args(['validate-version'])
        parser.parse_args(['detect-version'])
        parser.parse_args(['github-release'])
        parser.parse_args(['svn-deploy'])

    def test_validate_version_command(self):
        """Test validate-version command routes correctly."""
        with patch('taxjar_release.cli.VersionValidator') as MockValidator:
            mock_instance = Mock()
            mock_instance.validate.return_value = Mock(success=True)
            MockValidator.return_value = mock_instance

            with patch('taxjar_release.cli.GitClient'):
                with patch('taxjar_release.cli.BuildkiteClient'):
                    result = main(['validate-version'])

            assert result == 0
            mock_instance.validate.assert_called_once()

    def test_detect_version_command(self):
        """Test detect-version command routes correctly."""
        with patch('taxjar_release.cli.VersionDetector') as MockDetector:
            mock_instance = Mock()
            mock_instance.detect.return_value = Mock(success=True, should_skip=False)
            MockDetector.return_value = mock_instance

            with patch('taxjar_release.cli.GitClient'):
                with patch('taxjar_release.cli.BuildkiteClient'):
                    with patch('taxjar_release.cli.WordPressClient'):
                        result = main(['detect-version'])

            assert result == 0

    def test_github_release_requires_version(self):
        """Test github-release fails without version."""
        with patch.dict('os.environ', {}, clear=True):
            result = main(['github-release'])
            assert result == 1

    def test_svn_deploy_requires_credentials(self):
        """Test svn-deploy fails without credentials."""
        with patch.dict('os.environ', {'VERSION': '4.2.0'}, clear=True):
            result = main(['svn-deploy'])
            assert result == 1

    def test_returns_1_on_failure(self):
        """Test returns 1 when command fails."""
        with patch('taxjar_release.cli.VersionValidator') as MockValidator:
            mock_instance = Mock()
            mock_instance.validate.return_value = Mock(success=False)
            MockValidator.return_value = mock_instance

            with patch('taxjar_release.cli.GitClient'):
                with patch('taxjar_release.cli.BuildkiteClient'):
                    result = main(['validate-version'])

            assert result == 1
