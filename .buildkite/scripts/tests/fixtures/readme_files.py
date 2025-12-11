"""Factory for generating readme.txt content."""


def generate_readme(
    stable_tag: str = '4.1.0',
    wc_tested: str = '9.0.0',
    wp_tested: str = '6.4',
    wc_requires: str = '7.0.0',
) -> str:
    """Generate WordPress readme.txt with configurable values."""
    return f'''=== TaxJar - Sales Tax Automation for WooCommerce ===
Contributors: taxjar
Tags: woocommerce, taxes, sales tax, tax calculation
Requires at least: 5.0
Tested up to: {wp_tested}
Stable tag: {stable_tag}
Requires PHP: 7.4
WC tested up to: {wc_tested}
WC requires at least: {wc_requires}
License: GPLv2 or later

Save hours every month by putting your sales tax on autopilot.

== Description ==

TaxJar for WooCommerce brings the power of TaxJar to your store.

== Changelog ==

= {stable_tag} =
* Feature: Added new functionality
* Fix: Resolved issue with tax calculation
'''
