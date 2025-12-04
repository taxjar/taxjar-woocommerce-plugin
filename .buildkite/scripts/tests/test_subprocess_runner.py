"""Tests for SubprocessRunner client."""
import subprocess
import pytest
from taxjar_release.clients.subprocess_runner import SubprocessRunner


class TestSubprocessRunner:
    """Tests for SubprocessRunner."""

    def test_run_successful_command(self):
        """Test running a simple command."""
        runner = SubprocessRunner()
        result = runner.run(['echo', 'hello'])
        assert result.returncode == 0
        assert 'hello' in result.stdout

    def test_run_with_input(self):
        """Test passing stdin input."""
        runner = SubprocessRunner()
        result = runner.run(['cat'], input='test input')
        assert result.stdout.strip() == 'test input'

    def test_run_failing_command_raises(self):
        """Test that failing command raises when check=True."""
        runner = SubprocessRunner()
        with pytest.raises(subprocess.CalledProcessError):
            runner.run(['false'], check=True)

    def test_run_failing_command_no_raise(self):
        """Test that failing command doesn't raise when check=False."""
        runner = SubprocessRunner()
        result = runner.run(['false'], check=False)
        assert result.returncode != 0
