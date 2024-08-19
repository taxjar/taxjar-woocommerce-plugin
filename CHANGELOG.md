# 4.2.3 (2024-08-19)
* RUN_TJPLAT-1732 - Fix test failures
* #258 Stop displaying the metabox when the order can't be found

# 4.2.2 (2024-08-01)
* WooCommerce tested up to 8.1.0
* WordPress 6.6.1 tested
* Fix for two HPOS related issues
* Small improvement for the readme

# 4.2.1 (2023-10-04)
* WooCommerce tested up to 8.1.0
* WordPress 6.3.1 tested

# 4.2.0 (2023-09-27)
* WooCommerce tested up to 7.8.0
* Updates needed for HPOS compliance
* Flag as HPOS-enabled

# 4.1.5 (2022-10-11)
* WooCommerce tested up to 7.0.0
* Update minimum WooCommerce version to 6.4

# 4.1.4 (2022-08-19)
* WooCommerce tested up to 6.8.1
* Update minimum WooCommerce version to 6.2

# 4.1.3 (2022-06-28)
* Fix conflict with WooCommerce PDF Product Voucher plugin
* Support local pickup shipping method during calculation on admin and API orders
* Add Washington D.C. as exemption region
* Correctly calculate tax on subscription products with updated PTCs
* WooCommerce tested up to 6.6
* Update minimum WooCommerce version to 6.0

# 4.1.2 (2022-03-30)
* Fix for precision issue when syncing some orders to TaxJar
* Fix for persisting inaccurate rates in rates table
* WooCommerce tested up to 6.3
* Update minimum WooCommerce version to 5.8

# 4.1.1 (2022-02-15)
* Fix for issue where incorrect tax was applied on shipping when multiple shipping packages were selected
* Fix to add compatibility for WooCommerce Gift Card plugin
* Added feature to sync customer to TaxJar when created/updated through WooCommerce REST API
* Add feature to add customers to sync queue when created/updated through the Customer/Order/Coupon CSV Import Suite
* Add action after calculating cart totals
* WooCommerce tested up to 6.2
* Update minimum WooCommerce version to 5.7

# 4.1.0 (2022-01-19)
* Add order meta box to give details of calculation and sync statuses
* Remove usage of deprecated function is_ajax
* WooCommerce tested up to 6.1
* Update minimum WooCommerce version to 5.6

# 4.0.2 (2021-12-20)
* Filter invalid PTCs before creating transactions in TaxJar.
* Fix floating point precision issue causing transactions to be rejected by the TaxJar API.

# 4.0.1 (2021-11-19)
* Change dynamic tax rate ID to 999999999 to help prevent issues with 3rd parties ingesting exported WooCommerce order data
* Fix issue where shipping was still utilizing dynamic tax rate when the save rates setting was enabled.

# 4.0.0 (2021-11-11)
* Refactor cart tax calculation to stop calculation events from triggering twice
* Fix issue with tax calculation on dynamically created products and variations
* Fix issue where in some cases fees did not use correct product tax codes during calculation
* Fix issue where multiple products with the same product tax and exemption thresholds may cause incorrect tax
* Update minimum WooCommerce version to 5.4
* WooCommerce tested up to version 5.9
* WordPress tested up to version 5.8.2

# 3.3.0 (2021-09-02)
* Fix php warning when store settings are not set
* Update minimum WordPress version to 5.4
* Update minimum WooCommerce version to 5.1
* WooCommerce tested up to version 5.6
* Update minimum PHP version to 7.0

# 3.2.11 (2021-07-23)
* Fix fatal error when no line items present on tax response.

# 3.2.10 (2021-06-17)
* Minor transaction sync performance improvement.

# 3.2.9 (2021-06-07)
* Add support for tax calculation in WooCommerce cart and checkout blocks.

# 3.2.8 (2021-05-27)
* Fix tax calculation issues on fee only orders when created in admin dashboard or through Woo REST API.
* Fix tax calculation on orders with only shipping in admin dashboard or when created through Woo REST API.
* Fix incorrect tax rate application on orders with multiple line items using the same product tax code when different rates should be applied to each item.
* Fix issue where during order tax calculation, incorrect shipping tax may have been applied.
* Support tax classes on fees for order tax calculation.
* Fix issues with tax calculation of subscription orders created through the Woo REST API.
* Fix VAT exemption not applying on orders created through the admin dashboard or through the Woo REST API.
* Fix fallback to billing address if shipping address not present on an order in the admin dashboard.
* Fix issue where customer exemptions weren't being applied to a subscription order in the admin dashboard.
* Fix issue where changing a customer on an order in the admin dashboard may not have applied the exemption of the newly selected customer.
* Improve logging for order tax calculation.
* Add unit and integration tests to cover order tax calculation.
* WooCommerce 5.3.0 support

# 3.2.7 (2021-04-29)
* WooCommerce 5.2.2 Support
* Remove Action Scheduler Library

# 3.2.6 (2021-03-23)
* WooCommerce 5.1.0 Support
* Fix double subscription tax display issue
* Move calculation functions and settings functions into separate classes

# 3.2.5 (2021-01-28)
* Prevent tax calculation for orders with $0 total
* Add x-api-version header to TaxJar API requests
* WooCommerce 4.9.2 support

# 3.2.4 (2020-12-15)
* Fix occasional missing PTC from subscription orders
* Fix issues that prevented exempt customers from syncing to TaxJar
* WooCommerce 4.8.0 and WordPress 5.6 support

# 3.2.3 (2020-09-28)
* Add filter to nexus check
* Decouple tax calculation and transaction sync settings

# 3.2.2 (2020-09-02)
* Fix international store address validation
* Fix synchronized renewal non applicable message

# 3.2.1 (2020-07-09)
* Fix for undefined settings issue
* Fix for wrong shipping rates
* Increment supported WooCommerce version to 4.3.0
* Increase minimum WooCommerce version to 3.2.0

# 3.2.0 (2020-05-28)
* Add support for tax calculation on orders created through WooCommerce REST API V2 & V3
* Include plugin parameter for requests to TaxJar API
* Increment supported WooCommerce version to 4.1.1

# 3.1.1 (2020-05-15)
* Update WordPress listing content
* Fix bad link in connect TaxJar notification
* Fix TaxJar product exemption codes being altered before sending to TaxJar

# 3.1.0 (2020-04-10)
* Update to settings page using SmartCalcs Connect
* Update User Agent header for requests to TaxJar
* Prevent unnecessary logging when tax calculation not required
* Fix orders created through the WooCommerce API with a fee not syncing
* Confirm compatibility with WooCommerce 4.0.1 and WordPress 5.4.0

# 3.0.15 (2020-03-11)
* Ensure referer and user permissions are validated for ajax methods
* Confirm compatibility with WooCommerce 4.0

# 3.0.14 (2020-01-29)
* Ensure no extra actions are scheduled and clean up unnecessary actions

# 3.0.13 (2020-01-28)
* Update queue processing to fully support Action Scheduler 3.0
* Alter queue processing to handle scheduled actions that fail or timeout

# 3.0.12 (2020-01-06)
* Update supported WooCommerce version to 3.9.0
* Add filter to disable date validation on transaction sync

# 3.0.11 (2019-11-25)
* Fix Action Scheduler load order
* Update WooCommerce supported version to 3.8.0

# 3.0.10 (2019-10-04)
* Fix record stuck in awaiting status in sync queue
* Display last sync error in sync queue
* Clear regions not in nexus from rate table when nexus is updated
* Improve error messaging in logs
* Set synced date on orders when sync is manually triggered
* Display batch ID in sync queue table
* Handle unexpected exemptions during sync

# 3.0.9 (2019-09-18)
* Update validation to support new TaxJar product categories
* Fix missing filter on refund reference IDs

# 3.0.8 (2019-09-06)
* Fix deregister functionality to sent correct store URL
* Remove deregister upon API key update

# 3.0.7 (2019-08-29)
* Fix record sync when product does not exist

# 3.0.6 (2019-08-28)
* Add filter to enabled altering of customer data before sync
* Fix naming of filter to determine if customer should sync

# 3.0.5 (2019-08-21)
* Fix installation issue on multi sites

# 3.0.4 (2019-08-20)
* Fix issue where order can sync without having previously been completed in certain circumstances

# 3.0.3 (2019-08-20)
* Added transaction sync order push to TaxJar
* Added customer sync to TaxJar
* Full support for product exemptions
* Full support for customer exemptions
* Full support for partial refunds
* Full support for fees in tax reporting in TaxJar
* Fix issue syncing refunds with zero quantity line items
* Fix refunds created while order processing not syncing when order completed
* Fix local pickup expected tax reports mismatch in TaxJar
* Fix expected tax mismatch when order contains gift card in TaxJar reports
* Add fallback to billing address when shipping address is empty on sync
* Add filters to allow altering currency and country validation before syncing
* Add filters to allow altering of request data before syncing orders and refunds
* Add hooks to allow setting of order level exemptions during tax calculation and order syncing

# 2.3.1 (2019-08-12)
* Tested up to WooCommerce 3.7
* Tested up to WordPress 5.2.2
* Fix rate lookup when state field contains a space
* Added filters for line items during rate calculations

# 2.3.0 (2019-05-16)
* Added full support for WooCommerce Subscriptions
* Fix performance issue with recalculating shipping

# 2.2.0 (2019-04-25)
* Tested up to WooCommerce 3.6.2
* Fix exemption not applying to large quantity exempt line items
* Add zip code validation before sending SmartCalcs API request

# 2.1.0 (2019-04-04)
* Tested up to WooCommerce 3.5
* Compatibility support for WooCommerce Smart Coupons
* Add filters / actions for custom overrides of plugin functionality
* Check to make sure `enabled` setting exists after installing the plugin
* Fix empty nexus list issue
* Fix exempt products getting taxed on backend
* Fix taxable to fully exempt shipping in same order
* Fix VAT exempt tax removal in Woo < 3.2
* Fix JSON parsing error for backend orders with variable product variations containing special characters

# 2.0.1 (2018-08-23)
* Fix local pickup calculations with street address support

# 2.0.0 (2018-08-16)
* Street address support with rooftop accuracy
* Display native rate tables for custom rates
* Call `woocommerce_after_calculate_totals` after recalculation for other plugins
* Fix backend order calculations in WC 2.6

# 1.7.1 (2018-07-19)
* Tested up to WooCommerce 3.4
* Skip API requests when there are no line items or shipping charges
* Fix backend order tax calculations for deleted products
* Fix calculations for multiple line items with exemption thresholds
* Fix compatibility issues with PHP 5.2 and 5.3
* Fix tax code precedence for "None" tax status and custom tax class products
* Fix error handling when syncing nexus regions with an expired API token

# 1.7.0 (2018-05-10)
* Improve performance by skipping calculations in the mini-cart
* Drop TLC transients library in favor of native WP Transients API
* Fix caching issues with tax calculations

# 1.6.1 (2018-04-05)
* Fix error for WooCommerce stores running on PHP 5.4
* Update "Configure TaxJar" button to point directly to TaxJar integration section

# 1.6.0 (2018-03-22)
* Tested up to WooCommerce 3.3 
* Refactored plugin to better handle total calculations and WC Subscriptions
* Fix nexus overage API issue with expired TaxJar accounts
* Fix rounding issue with line items in WC 3.2
* Add filter hook to TaxJar store settings for developers
* Skip backend calculations for deleted products
* Remove default customer address setting override
* Exempt line items with "Zero rate" tax class applied
* Support UK / GB and EL / GR ISO 3166-1 code exceptions
* Sanitize tax class to handle "Zero Rate" string from Disability VAT Exemption plugin
* Drop WP_DEBUG logging in favor of taxjar.log

# 1.5.4 (2017-12-08)
* Fix sign-up fees and total issues with WC Subscriptions
* Fix tax for duplicate line items with WC Product Add-ons & WC Product Bundles
* Fix minor logging issue on shared hosts

# 1.5.3 (2017-11-17)
* Fix total calculations for origin and modified-origin based states

# 1.5.2 (2017-11-14)
* Recalculate totals in WooCommerce 3.2 instead of updating grand total
* Update "tested up to" for WordPress 4.8.2
* Update integration title

# 1.5.1 (2017-10-22)
* Fix totals calculation issue with WooCommerce 3.2
* Fix plugin action links filter issue with conflicting plugins

# 1.5.0 (2017-10-10)
* WooCommerce 3.2 compatibility
* Improve tax rate override notice under WooCommerce > Settings > Tax
* Improve plugin intro copy for support under "TaxJar Integration"
* Fix "limit usage to X items" discounts in WooCommerce 3.1
* Fix `get_id` method error for discounts in WooCommerce 2.6
* Fix product tax class parsing for multi-word categories such as "Food & Groceries"

# 1.4.0 (2017-08-17)
* Support backend order calculations for both WooCommerce 2.6.x and 3.x
* Fix backend rate display for orders with multiple tax classes

# 1.3.3 (2017-08-01)
* Fix initial calculation for recurring subscriptions with a trial period

# 1.3.2 (2017-07-20)
* Fix local pickup error for WooCommerce < 2.6.2

# 1.3.1 (2017-06-18)
* Include tlc_transient hotfix

# 1.3.0 (2017-06-16)
* Product taxability support for exemptions such as clothing.
* Line item taxability with support for recurring subscriptions.
* Fully exempt non-taxable items when tax status is set to "None".
* Fix calculations to use shipping origin when local pickup selected.
* Fix caching issues with API requests.

# 1.2.4 (2016-10-19)
* Add fallbacks to still calculate sales tax if nexus list is not populated.

# 1.2.3 (2016-09-21)
* Limit API calls for tax calculations to nexus areas.

# 1.2.2 (2016-08-29)
* Fix issue where uncached shipping tax was not displayed

# 1.2.1 (2016-06-27)
* Fix bug causing sales tax to not be calculated when shipping is disabled
* Pass home_url rather than site_url when linking to TaxJar

# 1.2.0 (2016-01-19)
* Changes for WooCommerce 2.5 compatibility around transients

# 1.1.8 (2015-12-30)
* Shipping tax bugfix

# 1.1.7 (2015-12-23)
* Bump version, wordpress.org failed to create 1.1.6 zip file

# 1.1.6 (2015-12-22)
* Change wording for connection

# 1.1.5 (2015-12-14)
* Display Nexus States/Region list on TaxJar panel
* Allow 1-Click TaxJar connection setup
* Bug fixes around order editing in order admin screens.

# 1.1.4 (2015-10-30)
* Better warnings about connection errors on plugin panel

# 1.1.3 (2015-09-09)
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
* Uses new API (with support for Canada): [read the docs](https://www.taxjar.com/api/docs/)

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
