"""Tests for BuildkiteClient."""
import pytest
from unittest.mock import Mock, patch
from taxjar_release.clients.buildkite import BuildkiteClient
from taxjar_release.clients.subprocess_runner import SubprocessRunner


class TestBuildkiteClient:
    """Tests for BuildkiteClient."""

    def test_annotate_when_available(self):
        """Test annotation when buildkite-agent is available."""
        mock_runner = Mock(spec=SubprocessRunner)
        client = BuildkiteClient(runner=mock_runner, available=True)

        client.annotate('Test message', style='error', context='test')

        mock_runner.run.assert_called_once()
        call_args = mock_runner.run.call_args[0][0]
        assert 'buildkite-agent' in call_args
        assert 'annotate' in call_args
        assert 'Test message' in call_args
        assert '--style' in call_args
        assert 'error' in call_args

    def test_annotate_when_unavailable(self, capsys):
        """Test annotation falls back to print when agent unavailable."""
        client = BuildkiteClient(runner=Mock(), available=False)

        client.annotate('Test message', style='error')

        captured = capsys.readouterr()
        assert '[ERROR]' in captured.out
        assert 'Test message' in captured.out

    def test_set_metadata_when_available(self):
        """Test setting metadata when buildkite-agent is available."""
        mock_runner = Mock(spec=SubprocessRunner)
        client = BuildkiteClient(runner=mock_runner, available=True)

        client.set_metadata('key', 'value')

        mock_runner.run.assert_called_once()
        call_args = mock_runner.run.call_args[0][0]
        assert 'meta-data' in call_args
        assert 'set' in call_args
        assert 'key' in call_args
        assert 'value' in call_args

    def test_set_metadata_when_unavailable(self):
        """Test setting metadata is no-op when agent unavailable."""
        mock_runner = Mock(spec=SubprocessRunner)
        client = BuildkiteClient(runner=mock_runner, available=False)

        client.set_metadata('key', 'value')

        mock_runner.run.assert_not_called()

    def test_get_metadata_when_available(self):
        """Test getting metadata when buildkite-agent is available."""
        mock_runner = Mock(spec=SubprocessRunner)
        mock_runner.run.return_value = Mock(stdout='test-value\n')
        client = BuildkiteClient(runner=mock_runner, available=True)

        result = client.get_metadata('key')

        assert result == 'test-value'

    def test_get_metadata_when_unavailable(self):
        """Test getting metadata returns None when agent unavailable."""
        client = BuildkiteClient(runner=Mock(), available=False)

        result = client.get_metadata('key')

        assert result is None
