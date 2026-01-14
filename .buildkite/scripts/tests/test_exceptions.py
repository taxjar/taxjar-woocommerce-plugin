"""Tests for custom exceptions."""
import pytest
from taxjar_release.exceptions import (
    ReleaseError,
    VersionMismatchError,
    GitHubReleaseError,
    SVNDeployError,
)


class TestExceptions:
    """Tests for custom exceptions."""

    def test_release_error_is_base(self):
        """Test ReleaseError is base exception."""
        error = ReleaseError('test error')
        assert str(error) == 'test error'
        assert isinstance(error, Exception)

    def test_version_mismatch_inherits(self):
        """Test VersionMismatchError inherits from ReleaseError."""
        error = VersionMismatchError('version mismatch')
        assert isinstance(error, ReleaseError)
        assert isinstance(error, Exception)

    def test_github_release_inherits(self):
        """Test GitHubReleaseError inherits from ReleaseError."""
        error = GitHubReleaseError('github error')
        assert isinstance(error, ReleaseError)

    def test_svn_deploy_inherits(self):
        """Test SVNDeployError inherits from ReleaseError."""
        error = SVNDeployError('svn error')
        assert isinstance(error, ReleaseError)

    def test_catch_all_release_errors(self):
        """Test catching all release errors with base class."""
        errors = [
            VersionMismatchError('v'),
            GitHubReleaseError('g'),
            SVNDeployError('s'),
        ]

        for error in errors:
            with pytest.raises(ReleaseError):
                raise error
