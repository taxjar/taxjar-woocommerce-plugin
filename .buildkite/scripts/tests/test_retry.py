"""Tests for retry decorator."""
import pytest
from unittest.mock import Mock, patch
from taxjar_release.retry import retry


class TestRetryDecorator:
    """Tests for retry decorator."""

    def test_successful_on_first_attempt(self):
        """Test function succeeds on first attempt."""
        mock_func = Mock(return_value='success')

        @retry(max_attempts=3)
        def test_func():
            return mock_func()

        result = test_func()

        assert result == 'success'
        assert mock_func.call_count == 1

    def test_retry_on_failure(self):
        """Test function retries on failure."""
        mock_func = Mock(side_effect=[Exception('fail'), Exception('fail'), 'success'])

        @retry(max_attempts=3, backoff=[0, 0])
        def test_func():
            return mock_func()

        result = test_func()

        assert result == 'success'
        assert mock_func.call_count == 3

    def test_raises_after_max_attempts(self):
        """Test exception raised after max attempts exhausted."""
        mock_func = Mock(side_effect=Exception('always fails'))

        @retry(max_attempts=3, backoff=[0, 0])
        def test_func():
            return mock_func()

        with pytest.raises(Exception, match='always fails'):
            test_func()

        assert mock_func.call_count == 3

    def test_specific_exceptions(self):
        """Test only specified exceptions trigger retry."""
        mock_func = Mock(side_effect=ValueError('wrong type'))

        @retry(max_attempts=3, exceptions=(KeyError,), backoff=[0, 0])
        def test_func():
            return mock_func()

        with pytest.raises(ValueError):
            test_func()

        assert mock_func.call_count == 1

    def test_backoff_timing(self):
        """Test backoff delays are applied."""
        mock_func = Mock(side_effect=[Exception('fail'), 'success'])

        with patch('taxjar_release.retry.time.sleep') as mock_sleep:
            @retry(max_attempts=3, backoff=[5, 10])
            def test_func():
                return mock_func()

            test_func()

            mock_sleep.assert_called_once_with(5)
