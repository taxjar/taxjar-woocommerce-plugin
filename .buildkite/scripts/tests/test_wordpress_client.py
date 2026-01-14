"""Tests for WordPressClient."""
import pytest
import responses
from taxjar_release.clients.wordpress import WordPressClient


class TestWordPressClient:
    """Tests for WordPressClient."""

    @responses.activate
    def test_get_plugin_version_success(self):
        """Test getting plugin version from API."""
        responses.add(
            responses.GET,
            'https://api.wordpress.org/plugins/info/1.0/test-plugin.json',
            json={'version': '4.1.0', 'name': 'Test Plugin'},
            status=200,
        )

        client = WordPressClient()
        version = client.get_plugin_version('test-plugin')

        assert version == '4.1.0'

    @responses.activate
    def test_get_plugin_version_not_found(self):
        """Test getting version for non-existent plugin."""
        responses.add(
            responses.GET,
            'https://api.wordpress.org/plugins/info/1.0/nonexistent.json',
            json={'error': 'Plugin not found'},
            status=404,
        )

        client = WordPressClient()

        with pytest.raises(Exception):
            client.get_plugin_version('nonexistent')

    @responses.activate
    def test_version_exists_true(self):
        """Test checking if version exists - returns True."""
        responses.add(
            responses.GET,
            'https://api.wordpress.org/plugins/info/1.0/test-plugin.json',
            json={'version': '4.1.0'},
            status=200,
        )

        client = WordPressClient()
        exists = client.version_exists('test-plugin', '4.1.0')

        assert exists is True

    @responses.activate
    def test_version_exists_false(self):
        """Test checking if version exists - returns False."""
        responses.add(
            responses.GET,
            'https://api.wordpress.org/plugins/info/1.0/test-plugin.json',
            json={'version': '4.0.0'},
            status=200,
        )

        client = WordPressClient()
        exists = client.version_exists('test-plugin', '4.1.0')

        assert exists is False

    @responses.activate
    def test_version_exists_api_error(self):
        """Test version_exists returns False on API error."""
        responses.add(
            responses.GET,
            'https://api.wordpress.org/plugins/info/1.0/test-plugin.json',
            body='Internal Server Error',
            status=500,
        )

        client = WordPressClient()
        exists = client.version_exists('test-plugin', '4.1.0')

        assert exists is False
