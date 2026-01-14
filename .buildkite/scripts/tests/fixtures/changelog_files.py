"""Factory for generating CHANGELOG.md content."""


def generate_changelog(
    version: str = '4.1.0',
    date: str = '2025-12-03',
) -> str:
    """Generate CHANGELOG.md with configurable values."""
    return f'''# Changelog

All notable changes to this project will be documented in this file.

# {version} - {date}

* Feature: Added new functionality
* Fix: Resolved issue with tax calculation

# 4.0.0 - 2025-01-01

* Major: Breaking changes
'''
