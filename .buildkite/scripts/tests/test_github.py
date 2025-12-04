"""Tests for GitHub release manager."""
import subprocess
import pytest
from unittest.mock import Mock, patch
from taxjar_release.github import GitHubReleaseManager
from taxjar_release.clients.subprocess_runner import SubprocessRunner
from taxjar_release.exceptions import GitHubReleaseError


class TestGitHubReleaseManager:
    """Tests for GitHubReleaseManager."""

    @pytest.fixture
    def mock_runner(self):
        return Mock(spec=SubprocessRunner)

    def test_create_release_calls_gh(self, mock_runner):
        """Test create_release calls gh CLI correctly."""
        mock_runner.run.return_value = Mock(stdout='Release created')

        manager = GitHubReleaseManager(runner=mock_runner)
        manager.create_release('4.2.0')

        mock_runner.run.assert_called_once()
        call_args = mock_runner.run.call_args[0][0]
        assert 'gh' in call_args
        assert 'release' in call_args
        assert 'create' in call_args
        assert '4.2.0' in call_args

    def test_create_release_with_target(self, mock_runner):
        """Test create_release uses target branch."""
        mock_runner.run.return_value = Mock(stdout='Release created')

        manager = GitHubReleaseManager(runner=mock_runner)
        manager.create_release('4.2.0', target='main')

        call_args = mock_runner.run.call_args[0][0]
        assert '--target' in call_args
        assert 'main' in call_args

    def test_create_release_with_notes(self, mock_runner):
        """Test create_release uses provided release notes."""
        mock_runner.run.return_value = Mock(stdout='Release created')

        manager = GitHubReleaseManager(runner=mock_runner)
        manager.create_release('4.2.0', notes='Fixed bug XYZ')

        call_args = mock_runner.run.call_args[0][0]
        assert '--notes' in call_args
        assert 'Fixed bug XYZ' in call_args
        assert '--generate-notes' not in call_args

    def test_create_release_without_notes_generates(self, mock_runner):
        """Test create_release auto-generates notes when not provided."""
        mock_runner.run.return_value = Mock(stdout='Release created')

        manager = GitHubReleaseManager(runner=mock_runner)
        manager.create_release('4.2.0')  # No notes parameter

        call_args = mock_runner.run.call_args[0][0]
        assert '--generate-notes' in call_args
        assert '--notes' not in call_args

    def test_create_release_retries_on_failure(self, mock_runner):
        """Test create_release retries on failure."""
        mock_runner.run.side_effect = [
            subprocess.CalledProcessError(1, 'gh'),
            Mock(stdout='Release created'),
        ]

        with patch('taxjar_release.retry.time.sleep'):
            manager = GitHubReleaseManager(runner=mock_runner)
            manager.create_release('4.2.0')

        assert mock_runner.run.call_count == 2

    def test_create_release_raises_after_retries(self, mock_runner):
        """Test create_release raises GitHubReleaseError after max retries."""
        mock_runner.run.side_effect = subprocess.CalledProcessError(1, 'gh')

        with patch('taxjar_release.retry.time.sleep'):
            manager = GitHubReleaseManager(runner=mock_runner)
            with pytest.raises(GitHubReleaseError) as exc_info:
                manager.create_release('4.2.0')

            assert 'Failed to create release 4.2.0' in str(exc_info.value)

        assert mock_runner.run.call_count == 3
