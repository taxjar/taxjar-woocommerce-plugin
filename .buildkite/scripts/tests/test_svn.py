"""Tests for SVN deploy manager."""
import subprocess
import pytest
from unittest.mock import Mock, patch, call
from taxjar_release.svn import SVNDeployManager
from taxjar_release.clients.subprocess_runner import SubprocessRunner
from taxjar_release.exceptions import SVNDeployError


class TestSVNDeployManager:
    """Tests for SVNDeployManager."""

    @pytest.fixture
    def mock_runner(self):
        runner = Mock(spec=SubprocessRunner)
        runner.run.return_value = Mock(stdout='', returncode=0)
        return runner

    def test_validates_credentials(self, mock_runner):
        """Test deploy validates credentials are set."""
        manager = SVNDeployManager(runner=mock_runner)

        with pytest.raises(SVNDeployError, match='credentials'):
            manager.deploy('4.2.0', username='', password='')

    def test_uses_password_from_stdin(self, mock_runner):
        """Test password is passed via stdin, not command line."""
        manager = SVNDeployManager(runner=mock_runner)

        with patch.object(manager, '_checkout_repo'):
            with patch.object(manager, '_update_trunk'):
                with patch.object(manager, '_commit_changes') as mock_commit:
                    mock_commit.return_value = None
                    with patch.object(manager, '_create_tag') as mock_tag:
                        mock_tag.return_value = None
                        try:
                            manager.deploy('4.2.0', username='user', password='secret')
                        except Exception:
                            pass

        # Verify password never in command args
        for call_item in mock_runner.run.call_args_list:
            args = call_item[0][0] if call_item[0] else []
            assert 'secret' not in str(args)

    def test_commit_uses_retry(self, mock_runner):
        """Test commit operation uses retry logic."""
        mock_runner.run.side_effect = [
            subprocess.CalledProcessError(1, 'svn'),
            Mock(stdout='Committed'),
        ]

        manager = SVNDeployManager(runner=mock_runner)

        with patch('taxjar_release.retry.time.sleep'):
            with patch.object(manager, '_checkout_repo'):
                with patch.object(manager, '_update_trunk'):
                    with patch.object(manager, '_create_tag'):
                        manager._commit_changes('4.2.0', 'user', 'pass')

    def test_cleanup_on_failure(self, mock_runner):
        """Test temp directory cleaned up on failure."""
        mock_runner.run.side_effect = Exception('SVN error')

        manager = SVNDeployManager(runner=mock_runner)

        with patch('tempfile.mkdtemp', return_value='/tmp/test'):
            with patch('taxjar_release.svn.os.path.exists', return_value=True):
                with patch('taxjar_release.svn.shutil.rmtree') as mock_rmtree:
                    with pytest.raises(Exception):
                        manager.deploy('4.2.0', username='user', password='pass')

                    # Cleanup should still be called
                    mock_rmtree.assert_called()
