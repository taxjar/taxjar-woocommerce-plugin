"""Custom exceptions for release automation."""


class ReleaseError(Exception):
    """Base exception for release operations."""
    pass


class VersionMismatchError(ReleaseError):
    """Version consistency validation failed."""
    pass


class GitHubReleaseError(ReleaseError):
    """GitHub release creation failed."""
    pass


class SVNDeployError(ReleaseError):
    """SVN deployment failed."""
    pass
