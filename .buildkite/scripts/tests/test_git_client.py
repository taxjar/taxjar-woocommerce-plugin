"""Tests for GitClient."""
import pytest
from unittest.mock import Mock, MagicMock, patch
from taxjar_release.clients.git import GitClient


class TestGitClient:
    """Tests for GitClient."""

    def test_get_file_content(self):
        """Test getting file content at ref."""
        mock_repo = Mock()
        mock_repo.git.show.return_value = 'file content here'

        client = GitClient(repo=mock_repo)
        content = client.get_file_content('path/to/file.txt', 'HEAD')

        assert content == 'file content here'
        mock_repo.git.show.assert_called_once_with('HEAD:path/to/file.txt')

    def test_get_file_content_default_ref(self):
        """Test getting file content uses HEAD by default."""
        mock_repo = Mock()
        mock_repo.git.show.return_value = 'content'

        client = GitClient(repo=mock_repo)
        client.get_file_content('file.txt')

        mock_repo.git.show.assert_called_once_with('HEAD:file.txt')

    def test_get_current_branch(self):
        """Test getting current branch name."""
        mock_repo = Mock()
        mock_repo.active_branch.name = 'feature/test-branch'

        client = GitClient(repo=mock_repo)
        branch = client.get_current_branch()

        assert branch == 'feature/test-branch'

    def test_file_changed_between_refs_true(self):
        """Test detecting file changed between refs."""
        mock_repo = Mock()
        mock_repo.git.diff.return_value = 'diff output here'

        client = GitClient(repo=mock_repo)
        changed = client.file_changed('file.txt', 'ref1', 'ref2')

        assert changed is True
        mock_repo.git.diff.assert_called_once_with('ref1', 'ref2', '--', 'file.txt')

    def test_file_changed_between_refs_false(self):
        """Test detecting file not changed between refs."""
        mock_repo = Mock()
        mock_repo.git.diff.return_value = ''

        client = GitClient(repo=mock_repo)
        changed = client.file_changed('file.txt', 'ref1', 'ref2')

        assert changed is False
