"""Testable subprocess wrapper."""
import subprocess
from typing import List, Optional


class SubprocessRunner:
    """Wrapper around subprocess.run for testability."""

    def run(
        self,
        cmd: List[str],
        check: bool = True,
        capture: bool = True,
        input: Optional[str] = None,
        cwd: Optional[str] = None,
    ) -> subprocess.CompletedProcess:
        """
        Execute a subprocess command.

        Args:
            cmd: Command and arguments as list
            check: Raise CalledProcessError on non-zero exit
            capture: Capture stdout/stderr
            input: String to pass to stdin
            cwd: Working directory for command

        Returns:
            CompletedProcess with stdout, stderr, returncode
        """
        return subprocess.run(
            cmd,
            check=check,
            capture_output=capture,
            text=True,
            input=input,
            cwd=cwd,
        )
