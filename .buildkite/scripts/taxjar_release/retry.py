"""Retry decorator with exponential backoff."""
import functools
import time
from typing import Callable, List, Tuple, Type


def retry(
    max_attempts: int = 3,
    backoff: List[int] = None,
    exceptions: Tuple[Type[Exception], ...] = (Exception,),
) -> Callable:
    """
    Retry decorator with configurable backoff.

    Args:
        max_attempts: Maximum number of attempts
        backoff: List of sleep times between attempts (e.g., [2, 4, 8])
        exceptions: Tuple of exceptions to catch and retry

    Returns:
        Decorated function
    """
    if backoff is None:
        backoff = []

    def decorator(func: Callable) -> Callable:
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            for attempt in range(1, max_attempts + 1):
                try:
                    return func(*args, **kwargs)
                except exceptions as e:
                    if attempt >= max_attempts:
                        raise

                    if attempt - 1 < len(backoff):
                        sleep_time = backoff[attempt - 1]
                        print(f'Attempt {attempt} failed: {e}. Retrying in {sleep_time}s...')
                        time.sleep(sleep_time)
                    else:
                        print(f'Attempt {attempt} failed: {e}. Retrying immediately...')

        return wrapper
    return decorator
