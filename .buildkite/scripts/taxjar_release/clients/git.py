"""GitPython wrapper for git operations."""
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
        self.repo = repo or git.Repo(repo_path)

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
