* 1.1.3 (2015-09-09)
* Better support for generating API keys in WooCommerce 2.4+
* Warnings for PHP version

# 1.1.2 (2015-07-30)
* Handling Shipping tax more accurately

# 1.1.1 (2015-07-21)
* Fix transient key bug with city (suggest to clear transients in WooCommerce)
* Label text change
* Improve handling of Shipping taxes

# 1.1.0 (2015-06-26)
* Switch to v2 TaxJar API
* Bug fixes and code cleanups

# (2015-04-30)
* WooCommerce compatible note 2.3.x is now required

# 1.0.8 (2015-03-10)
* Bug fixes in the handling of persisted rates

# 1.0.7 (2014-12-24)
## Fixed
* Fixed a bug encountered when local shipping options were selected

## New
* Adds tax calculation support to WooCommerce for local shipping options
* WooCommerce can now calculate taxes for local pickup shipping option

# 1.0.6 (2014-11-17)
* Fixed a bug encountered on some hosting providers

# 1.0.5.2 (2014-11-13)
* Fixed a bug where coupons where being applied on the cart twice

# 1.0.5.1 (2014-11-06)
* Bug fixes

# 1.0.5 (2014-09-26)
## Updated
* New way of handling taxes on orders compatible with WooCommerce 2.2
* Uses new API (with support for Canada): [read the docs](http://www.taxjar.com/api/docs/)

## New
* Ability to download orders easily into TaxJar
* Shortcuts to access TaxJar Settings
* Freezes settings for WooCommerce Tax (we set everything up for your store's sales tax needs)

# 1.0.3 (2014-08-27)
* Fix api url param for woo

# 1.0.2 (2014-08-26)
* use taxable_address from wooCommerce customer

# 1.0.1 (2014-08-25)
* TaxJar calc overrides all other taxes
* Hide order admin calculate tax button

# 1.0 (2014-08-11)
* Initial release
