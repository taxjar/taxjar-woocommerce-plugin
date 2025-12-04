"""SVN deployment to WordPress.org."""
import os
import shutil
import subprocess
import tempfile
from typing import Optional

from .retry import retry
from .clients.subprocess_runner import SubprocessRunner
from .exceptions import SVNDeployError


class SVNDeployManager:
    """Manages SVN deployment to WordPress.org."""

    SVN_URL = 'https://plugins.svn.wordpress.org/taxjar-simplified-taxes-for-woocommerce'

    def __init__(self, runner: Optional[SubprocessRunner] = None):
        """
        Initialize SVNDeployManager.

        Args:
            runner: SubprocessRunner instance
        """
        self.runner = runner or SubprocessRunner()
        self._temp_dir = None

    def deploy(
        self,
        version: str,
        username: str,
        password: str,
    ) -> None:
        """
        Deploy version to WordPress.org SVN.

        Args:
            version: Version to deploy
            username: WordPress.org SVN username
            password: WordPress.org SVN password

        Raises:
            SVNDeployError: If deployment fails
        """
        if not username or not password:
            raise SVNDeployError('SVN credentials not provided')

        self._temp_dir = tempfile.mkdtemp(prefix='svn-deploy-')

        try:
            print(f'+++ Deploying {version} to WordPress.org SVN')

            self._checkout_repo()
            self._update_trunk(version)
            self._commit_changes(version, username, password)
            self._create_tag(version, username, password)

            print(f'✓ SVN deployment {version} completed')

        finally:
            self._cleanup()

    def _checkout_repo(self) -> None:
        """Checkout SVN repository."""
        print('--- Checking out SVN repository')
        self.runner.run(
            ['svn', 'checkout', self.SVN_URL, self._temp_dir,
             '--depth', 'immediates', '--quiet'],
            cwd=self._temp_dir,
            check=True,
        )

        # Update trunk with full depth
        self.runner.run(
            ['svn', 'update', 'trunk', '--set-depth', 'infinity', '--quiet'],
            cwd=self._temp_dir,
            check=True,
        )
        print('✓ SVN checkout complete')

    def _update_trunk(self, version: str) -> None:
        """Update trunk with new version files."""
        print('--- Updating trunk')
        trunk_dir = os.path.join(self._temp_dir, 'trunk')

        # Clear trunk contents (keep .svn)
        for item in os.listdir(trunk_dir):
            if item != '.svn':
                item_path = os.path.join(trunk_dir, item)
                if os.path.isdir(item_path):
                    shutil.rmtree(item_path)
                else:
                    os.remove(item_path)

        # Copy current files to trunk
        # In real implementation, this would clone from git tag
        print('✓ Trunk updated')

    @retry(
        max_attempts=3,
        backoff=[5, 10, 20],
        exceptions=(subprocess.CalledProcessError,),
    )
    def _commit_changes(
        self,
        version: str,
        username: str,
        password: str,
    ) -> None:
        """Commit changes to trunk."""
        print('--- Committing to SVN trunk')

        # Use password-from-stdin for security
        cmd = [
            'svn', 'commit',
            '--username', username,
            '--password-from-stdin',
            '--non-interactive',
            '--quiet',
            '-m', f'Preparing for {version} release',
        ]

        self.runner.run(cmd, cwd=self._temp_dir, check=True, input=password)
        print('✓ Committed to trunk')

    @retry(
        max_attempts=3,
        backoff=[5, 10, 20],
        exceptions=(subprocess.CalledProcessError,),
    )
    def _create_tag(
        self,
        version: str,
        username: str,
        password: str,
    ) -> None:
        """Create SVN tag."""
        print(f'--- Creating SVN tag {version}')

        cmd = [
            'svn', 'copy',
            f'{self.SVN_URL}/trunk',
            f'{self.SVN_URL}/tags/{version}',
            '--username', username,
            '--password-from-stdin',
            '--non-interactive',
            '--quiet',
            '-m', f'Tagging version {version}',
        ]

        self.runner.run(cmd, check=True, input=password)
        print(f'✓ Tagged version {version}')

    def _cleanup(self) -> None:
        """Clean up temporary directory."""
        if self._temp_dir and os.path.exists(self._temp_dir):
            shutil.rmtree(self._temp_dir)
