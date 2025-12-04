"""GitHub release operations."""
import subprocess
from typing import Optional

from .retry import retry
from .clients.subprocess_runner import SubprocessRunner
from .exceptions import GitHubReleaseError


class GitHubReleaseManager:
    """Manages GitHub release creation."""

    def __init__(self, runner: Optional[SubprocessRunner] = None):
        """
        Initialize GitHubReleaseManager.

        Args:
            runner: SubprocessRunner instance
        """
        self.runner = runner or SubprocessRunner()

    @retry(
        max_attempts=3,
        backoff=[2, 4, 8],
        exceptions=(GitHubReleaseError,),
    )
    def create_release(
        self,
        version: str,
        target: str = 'master',
        notes: str = '',
    ) -> str:
        """
        Create a GitHub release.

        Args:
            version: Version tag to create
            target: Target branch for release
            notes: Release notes

        Returns:
            Release URL

        Raises:
            GitHubReleaseError: If gh command fails after retries
        """
        cmd = [
            'gh', 'release', 'create', version,
            '--target', target,
            '--title', version,
        ]

        if notes:
            cmd.extend(['--notes', notes])
        else:
            cmd.append('--generate-notes')

        try:
            result = self.runner.run(cmd, check=True)
            print(f'✓ GitHub release {version} created')
            return result.stdout.strip()
        except subprocess.CalledProcessError as e:
            raise GitHubReleaseError(f"Failed to create release {version}: {e}") from e
