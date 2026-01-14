"""GitPython wrapper for git operations."""
import os
import subprocess
from typing import Optional
import git


class GitClient:
    """Wrapper around GitPython for testability."""

    def __init__(self, repo_path: str = '.', repo: Optional[git.Repo] = None):
        """
        Initialize GitClient.

        Args:
            repo_path: Path to git repository
            repo: GitPython Repo instance (for testing)
        """
        if repo is None:
            self._configure_safe_directory(repo_path)
        self.repo = repo or git.Repo(repo_path)

    @staticmethod
    def _configure_safe_directory(repo_path: str) -> None:
        """
        Configure git safe.directory for CI environments.

        In Docker containers (e.g., Buildkite), the repo may be owned by
        a different user. Git 2.35.2+ requires safe.directory config.
        """
        abs_path = os.path.abspath(repo_path)
        try:
            subprocess.run(
                ['git', 'config', '--global', '--add', 'safe.directory', abs_path],
                check=False,
                capture_output=True,
            )
        except FileNotFoundError:
            pass

    def get_file_content(self, filepath: str, ref: str = 'HEAD') -> str:
        """
        Get file content at specific git ref.

        Args:
            filepath: Path to file relative to repo root
            ref: Git ref (branch, tag, commit)

        Returns:
            File content as string
        """
        return self.repo.git.show(f'{ref}:{filepath}')

    def get_current_branch(self) -> str:
        """
        Get name of current branch.

        Returns:
            Branch name
        """
        return self.repo.active_branch.name

    def file_changed(self, filepath: str, ref1: str, ref2: str) -> bool:
        """
        Check if file changed between two refs.

        Args:
            filepath: Path to file relative to repo root
            ref1: First git ref
            ref2: Second git ref

        Returns:
            True if file differs between refs
        """
        diff = self.repo.git.diff(ref1, ref2, '--', filepath)
        return bool(diff)
