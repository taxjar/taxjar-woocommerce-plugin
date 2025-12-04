"""Buildkite agent wrapper with graceful degradation."""
from typing import Optional
from .subprocess_runner import SubprocessRunner


class BuildkiteClient:
    """Wrapper for buildkite-agent commands."""

    def __init__(
        self,
        runner: Optional[SubprocessRunner] = None,
        available: Optional[bool] = None,
    ):
        """
        Initialize BuildkiteClient.

        Args:
            runner: SubprocessRunner instance (created if not provided)
            available: Override availability check (for testing)
        """
        self.runner = runner or SubprocessRunner()
        self._available = available if available is not None else self._check_availability()

    def _check_availability(self) -> bool:
        """Check if buildkite-agent is available."""
        try:
            self.runner.run(['buildkite-agent', '--version'], check=False)
            return True
        except FileNotFoundError:
            return False

    @property
    def is_available(self) -> bool:
        """Return whether buildkite-agent is available."""
        return self._available

    def annotate(
        self,
        message: str,
        style: str = 'info',
        context: Optional[str] = None,
    ) -> None:
        """
        Create a Buildkite annotation.

        Args:
            message: Annotation message (supports markdown)
            style: Annotation style (info, warning, error, success)
            context: Unique context key for updating annotations
        """
        if not self._available:
            print(f'[{style.upper()}] {message}')
            return

        cmd = ['buildkite-agent', 'annotate', message, '--style', style]
        if context:
            cmd.extend(['--context', context])

        self.runner.run(cmd, check=False)

    def set_metadata(self, key: str, value: str) -> None:
        """
        Set Buildkite meta-data.

        Args:
            key: Meta-data key
            value: Meta-data value
        """
        if not self._available:
            return

        self.runner.run(
            ['buildkite-agent', 'meta-data', 'set', key, value],
            check=False,
        )

    def get_metadata(self, key: str) -> Optional[str]:
        """
        Get Buildkite meta-data.

        Args:
            key: Meta-data key

        Returns:
            Meta-data value or None if unavailable
        """
        if not self._available:
            return None

        try:
            result = self.runner.run(
                ['buildkite-agent', 'meta-data', 'get', key],
                check=True,
            )
            return result.stdout.strip()
        except Exception:
            return None

    def metadata_exists(self, key: str) -> bool:
        """
        Check if Buildkite meta-data key exists.

        Args:
            key: Meta-data key

        Returns:
            True if key exists, False otherwise
        """
        if not self._available:
            return False

        try:
            self.runner.run(
                ['buildkite-agent', 'meta-data', 'exists', key],
                check=True,
            )
            return True
        except Exception:
            return False
