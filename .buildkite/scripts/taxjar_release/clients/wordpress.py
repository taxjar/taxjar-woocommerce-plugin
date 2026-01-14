"""WordPress.org API client."""
from typing import Optional
import requests


class WordPressClient:
    """Client for WordPress.org plugin API."""

    API_BASE = 'https://api.wordpress.org/plugins/info/1.0'

    def __init__(self, session: Optional[requests.Session] = None):
        """
        Initialize WordPressClient.

        Args:
            session: requests Session instance (created if not provided)
        """
        self.session = session or requests.Session()

    def get_plugin_version(self, slug: str) -> str:
        """
        Get current version of plugin from WordPress.org.

        Args:
            slug: Plugin slug (e.g., 'taxjar-simplified-taxes-for-woocommerce')

        Returns:
            Current version string

        Raises:
            requests.HTTPError: If API request fails
        """
        url = f'{self.API_BASE}/{slug}.json'
        response = self.session.get(url)
        response.raise_for_status()
        data = response.json()
        return data.get('version', '')

    def version_exists(self, slug: str, version: str) -> bool:
        """
        Check if a specific version exists on WordPress.org.

        Args:
            slug: Plugin slug
            version: Version to check

        Returns:
            True if version matches current WordPress.org version
        """
        try:
            current_version = self.get_plugin_version(slug)
            return current_version == version
        except Exception:
            return False
