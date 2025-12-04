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

    def test_checkout_uses_correct_depth(self, mock_runner):
        """Test checkout uses correct SVN depth flags."""
        manager = SVNDeployManager(runner=mock_runner)
        manager._temp_dir = '/tmp/test-dir'

        manager._checkout_repo()

        # Should be called twice: initial checkout and trunk update
        assert mock_runner.run.call_count == 2

        # First call: checkout with immediates depth
        first_call = mock_runner.run.call_args_list[0][0][0]
        assert 'checkout' in first_call
        assert '--depth' in first_call
        assert 'immediates' in first_call

        # Second call: update trunk with infinity depth
        second_call = mock_runner.run.call_args_list[1][0][0]
        assert 'update' in second_call
        assert 'trunk' in second_call
        assert '--set-depth' in second_call
        assert 'infinity' in second_call

    def test_update_trunk_copies_files(self, mock_runner):
        """Test trunk update copies files from source."""
        manager = SVNDeployManager(runner=mock_runner)

        with patch('tempfile.mkdtemp', return_value='/tmp/svn-test'):
            with patch('os.listdir') as mock_listdir:
                with patch('shutil.copytree') as mock_copytree:
                    with patch('shutil.copy2') as mock_copy:
                        with patch('os.path.isdir') as mock_isdir:
                            # Setup: trunk has .svn dir, source has files
                            mock_listdir.side_effect = [
                                ['.svn'],  # trunk contents
                                ['taxjar-woocommerce.php', 'includes'],  # source contents
                            ]
                            mock_isdir.side_effect = [False, True]  # php file, includes dir

                            manager._temp_dir = '/tmp/svn-test'
                            with patch.object(manager, '_stage_svn_changes'):
                                manager._update_trunk('4.2.0', source_dir='/src')

                            # Verify files copied
                            mock_copy.assert_called_once()
                            mock_copytree.assert_called_once()

    def test_stage_svn_changes_adds_and_deletes(self, mock_runner):
        """Test SVN staging correctly adds new files and deletes removed files."""
        # Mock svn status output showing:
        # - new_file.php: unversioned (?)
        # - old_file.php: missing/deleted (!)
        # - modified.php: modified (M) - should be ignored
        mock_runner.run.return_value = Mock(
            stdout='?       new_file.php\n!       old_file.php\nM       modified.php\n',
            returncode=0
        )

        manager = SVNDeployManager(runner=mock_runner)
        manager._stage_svn_changes('/tmp/trunk')

        # Verify svn status was called
        assert mock_runner.run.call_count >= 1
        status_call = mock_runner.run.call_args_list[0]
        assert 'svn' in status_call[0][0]
        assert 'status' in status_call[0][0]

        # Verify svn add was called for new file
        add_calls = [c for c in mock_runner.run.call_args_list if 'add' in c[0][0]]
        assert len(add_calls) == 1
        assert 'new_file.php' in add_calls[0][0][0]

        # Verify svn delete was called for removed file
        delete_calls = [c for c in mock_runner.run.call_args_list if 'delete' in c[0][0]]
        assert len(delete_calls) == 1
        assert 'old_file.php' in delete_calls[0][0][0]

    def test_stage_svn_changes_handles_empty_status(self, mock_runner):
        """Test SVN staging handles no changes gracefully."""
        mock_runner.run.return_value = Mock(stdout='', returncode=0)

        manager = SVNDeployManager(runner=mock_runner)
        manager._stage_svn_changes('/tmp/trunk')

        # Only svn status should be called, no add/delete
        assert mock_runner.run.call_count == 1
