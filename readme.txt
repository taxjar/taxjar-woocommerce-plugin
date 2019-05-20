==== TaxJar - Sales Tax Automation for WooCommerce ====
Contributors: taxjar, tonkapark, fastdivision
Tags: woocommerce, taxjar, tax, taxes, sales tax, tax calculation, sales tax compliance, sales tax filing
Requires at least: 4.2
Tested up to: 5.1.1
Stable tag: 2.3.0
License: GPLv2 or later
URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.6.0
WC tested up to: 3.6.2

Save hours every month by putting your sales tax on autopilot. Automated, multi-state sales tax calculations, reporting, and filing.

== Description ==

Painless sales tax calculations, reporting and filing for WooCommerce!

Get accurate sales tax calculations and return-ready reports. [TaxJar for WooCommerce](https://www.taxjar.com/woocommerce-sales-tax-plugin/) takes care of all your sales tax needs. Trusted by over 15,000 eCommerce businesses each month.

*Why WooCommerce Customers Love TaxJar:*

* Rates are never out-of-date - TaxJar maintains more than 10,000 tax rates, updated monthly and all of your tax settings are updated automatically!
* With TaxJar, you can prepare and file sales tax returns in minutes, not hours. View your sales and sales tax collected by state, city, county, and local jurisdictions. See exactly what you need to file.
* US, Canada, Australia, and EU Calculations - We provide sales tax calculations worldwide. [Get the full list](https://developers.taxjar.com/api/reference/#countries) of countries we currently support.
* Automatic filing - For an additional fee, let TaxJar handle your sales tax filings for you and we'll automatically submit your returns to the state. Enroll once, never miss a due date again! [Learn more about AutoFile](https://www.taxjar.com/autofile).

*Pricing:*

* No contracts. No activations fees. Ever.
* Enjoy a free 30 day trial with no credit card required.
* After your trial, pay based on your number of transactions and API calls [starting at $19 per month](https://www.taxjar.com/pricing/). You only make API calls in the states where you need to calculate sales tax!

*Other Notable Features:*

* Simple install – Start collecting sales tax in minutes.
* Compatible with WooCommerce 2.6+ and WordPress 4.2+.
* Exempt non-taxable products and take advantage of TaxJar's built-in [sales tax categories](https://developers.taxjar.com/api/reference/#categories) for product exemptions such as clothing, food, software, and more.
* If you sell on other marketplaces or platforms beyond WooCommerce, get your sales tax data all in one spot.

== Installation ==

Setting up sales tax with TaxJar is simple. [Read the documentation](https://docs.woocommerce.com/document/taxjar/) to learn more!

Or you can follow these steps to install the TaxJar plugin:

1. Install the plugin in your WordPress admin panel under *Plugins > Add New* (search for “TaxJar”) or [download the zip](https://downloads.wordpress.org/plugin/taxjar-simplified-taxes-for-woocommerce.zip) and upload to `/wp-content/plugins` on your server.
2. Activate the plugin under *Plugins > Installed Plugins*.
3. Find the TaxJar configuration under *WooCommerce > TaxJar*.
4. You’ll need a TaxJar API token to get started. If you already have a TaxJar account, [click here](https://app.taxjar.com/account#api-access) to get an API token. In the TaxJar app, generate a new API token and copy it to your clipboard. If you don’t have a TaxJar account, you’ll be asked to sign up first. After signing up, follow the steps above.
5. Paste your TaxJar API Token into the “API Token” field.
6. Fill out the rest of your settings. TaxJar requires your city and zip code from which you ship products to calculate sales tax. We automatically detect your country and state based on your WooCommerce configuration.
7. If you have multiple [nexus](https://blog.taxjar.com/sales-tax-nexus-definition/) states where you need to collect sales tax, make sure they're [added to your TaxJar account](https://app.taxjar.com/account#states). Click the “Sync Nexus Addresses” button to import your nexus addresses into WooCommerce. 
8. Check the box next to “Enable TaxJar Calculations”.
9. If you plan to use TaxJar for sales tax reporting and filing, check the box next to "Enable order downloads to TaxJar" for TaxJar to connect and download the transactions on your store for [AutoFile](https://www.taxjar.com/autofile/) and reporting features.
10. Click “Save changes”. You're now up and running with TaxJar!

Suggested WooCommerce Tax Settings

1. We automatically set up your store's tax settings to work with our API. There is no need to configure WooCommerce taxes.
2. Full reporting of your sales tax collected, AutoFile and more available in your [TaxJar account](https://app.taxjar.com/).

== Frequently Asked Questions ==

= What are the requirements to collect sales tax with TaxJar for WooCommerce? =

As long as you have a business address, TaxJar’s plugin can calculate sales tax based on your location and the location of your customer.

= How do I get a TaxJar API Token? =

If you already have a TaxJar account, [click here](https://app.taxjar.com/account#api-access) to get a TaxJar API token. After generating a new token, copy it to your clipboard and paste it into the "API Token" field in the TaxJar plugin configuration. If you don’t have a TaxJar account, you’ll be asked to sign up first. After signing up, follow the steps above.

= What does this plugin cost? =

It’s free to use as much as you want for 30 days. After your free 30 day trial, our pricing is based on the number of WooCommerce transactions you import into TaxJar and the number of calls you make to our API (in other words how many times you calculate sales tax at checkout for your nexus states). [Pricing](https://www.taxjar.com/pricing/) starts at $19 per month.

= Does this cost more if I have nexus in more than one state? =

Nope. The cost is the same no matter if you have nexus in one state or 40 states.

= Can TaxJar file my sales tax returns automatically for me? =

Yes. We can file sales tax returns for you in any US state.

= Is there a separate fee to file my sales tax returns for me? =

Yes. The fee is $19.95 per state, per filing.

== Screenshots ==

1. TaxJar for WooCommerce Plugin Settings

== Changelog ==

= 2.3.0 (2019-05-16)
* Added full support for WooCommerce Subscriptions
* Fix performance issue with recalculating shipping

= 2.2.0 (2019-04-25)
* Tested up to WooCommerce 3.6.2
* Fix exemption not applying to large quantity exempt line items
* Add zip code validation before sending SmartCalcs API request

= 2.1.0 (2019-04-04)
* Tested up to WooCommerce 3.5
* Compatibility support for WooCommerce Smart Coupons
* Add filters / actions for custom overrides of plugin functionality
* Check to make sure `enabled` setting exists after installing the plugin
* Fix empty nexus list issue
* Fix exempt products getting taxed on backend
* Fix taxable to fully exempt shipping in same order
* Fix VAT exempt tax removal in Woo < 3.2
* Fix JSON parsing error for backend orders with variable product variations containing special characters

= 2.0.1 (2018-08-23) =
* Fix local pickup calculations with street address support

= 2.0.0 (2018-08-16) =
* Street address support with rooftop accuracy
* Display native rate tables for custom rates
* Call `woocommerce_after_calculate_totals` after recalculation for other plugins
* Fix backend order calculations in WC 2.6

= 1.7.1 (2018-07-19) =
* Tested up to WooCommerce 3.4
* Skip API requests when there are no line items or shipping charges
* Fix backend order tax calculations for deleted products
* Fix calculations for multiple line items with exemption thresholds
* Fix compatibility issues with PHP 5.2 and 5.3
* Fix tax code precedence for "None" tax status and custom tax class products
* Fix error handling when syncing nexus regions with an expired API token

= 1.7.0 (2018-05-10) =
* Improve performance by skipping calculations in the mini-cart
* Drop TLC transients library in favor of native WP Transients API
* Fix caching issues with tax calculations

= 1.6.1 (2018-04-05) =
* Fix error for WooCommerce stores running on PHP 5.4
* Update "Configure TaxJar" button to point directly to TaxJar integration section

= 1.6.0 (2018-03-22) =
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

= 1.5.4 (2017-12-08) =
* Fix sign-up fees and total issues with WC Subscriptions
* Fix tax for duplicate line items with WC Product Add-ons & WC Product Bundles
* Fix minor logging issue on shared hosts
 
= 1.5.3 (2017-11-17) =
* Fix total calculations for origin and modified-origin based states

= 1.5.2 (2017-11-14) =
* Recalculate totals in WooCommerce 3.2 instead of updating grand total
* Update "tested up to" for WordPress 4.8.2
* Update integration title

= 1.5.1 (2017-10-22) =
* Fix totals calculation issue with WooCommerce 3.2
* Fix plugin action links filter issue with conflicting plugins

= 1.5.0 (2017-10-10) =
* WooCommerce 3.2 compatibility
* Improve tax rate override notice under WooCommerce > Settings > Tax
* Improve plugin intro copy for support under "TaxJar Integration"
* Fix "limit usage to X items" discounts in WooCommerce 3.1
* Fix `get_id` method error for discounts in WooCommerce 2.6
* Fix product tax class parsing for multi-word categories such as "Food & Groceries"

= 1.4.0 (2017-08-17) =
* Support backend order calculations for both WooCommerce 2.6.x and 3.x
* Fix backend rate display for orders with multiple tax classes

= 1.3.3 (2017-08-01) =
* Fix initial calculation for recurring subscriptions with a trial period

= 1.3.2 (2017-07-20) =
* Fix local pickup error for WooCommerce < 2.6.2

= 1.3.1 (2017-06-18) =
* Include tlc_transient hotfix

= 1.3.0 (2017-06-16) =
* Product taxability support for exemptions such as clothing.
* Line item taxability with support for recurring subscriptions.
* Fully exempt non-taxable items when tax status is set to "None".
* Fix calculations to use shipping origin when local pickup selected.
* Fix caching issues with API requests.

= 1.2.4 (2016-10-19) =
* Add fallbacks to still calculate sales tax if nexus list is not populated.

= 1.2.3 (2016-09-21) =
* Limit API calls for tax calculations to nexus areas.

= 1.2.2 (2016-08-29) =
* Fix issue where uncached shipping tax was not displayed

= 1.2.1 (2016-06-27) =
* Fix bug causing sales tax to not be calculated when shipping is disabled
* Pass home_url rather than site_url when linking to TaxJar

= 1.2.0 (2016-01-19) =
* Changes for WooCommerce 2.5 compatibility around transients

= 1.1.8 (2015-12-30) =
* Shipping tax bugfix

= 1.1.7 (2015-12-23) =
* Bump version, wordpress.org failed to create 1.1.6 zip file

= 1.1.6 (2015-12-22) =
* Change wording for connection

= 1.1.5 (2015-12-14) =
* Display Nexus States/Region list on TaxJar panel
* Allow 1-Click TaxJar connection setup
* Bug fixes around order editing in order admin screens.

= 1.1.4 (2015-10-30) =
* Better warnings about connection errors on plugin panel

= 1.1.3 (2015-09-09) =
* Better support for generating API keys in WooCommerce 2.4+

= 1.1.2 (2015-07-30) =
* Handling Shipping tax more accurately

= 1.1.1 (2015-07-21) =
* Fix transient key bug with city (suggest to clear transients in WooCommerce)
* Label text change
* Improve handling of Shipping taxes

= 1.1.0 (2015-06-26) =
* Code cleanup
* Use new v2 TaxJar API (https://developers.taxjar.com/api/)
* New TaxJar graphic

= 1.0.8 (2015-03-10) =
* Bug fixes in the handling of persisted rates

= 1.0.7 (2014-12-24) =
* Fixed
* Fixed a bug encountered when local shipping options were selected for some users
* New
* Adds tax calculation support to WooCommerce for local shipping options
* WooCommerce can now calculate taxes for local pickup shipping option

= 1.0.6 (2014-11-17) =
* Fixed a bug encountered on some hosting providers

= 1.0.5.2 (2014-11-13) =
* Fixed a bug where coupons where being applied on the cart twice

= 1.0.5.1 (2014-11-06) =
* Bug fixes

= 1.0.5 (2014-09-26) =
* Updated
* New way of handling taxes on orders compatible with WooCommerce 2.2
* Uses new API (with support for Canada): [read the docs](https://www.taxjar.com/api/docs/)
* New
* Ability to download orders easily into TaxJar
* Shortcuts to access TaxJar Settings
* Freezes settings for WooCommerce Tax (we set everything up for your store's sales tax needs)

= 1.0.3 (2014-08-27) =
* Fix api url param for woo

= 1.0.2 (2014-08-26) =
* use taxable_address from wooCommerce customer

= 1.0.1 (2014-08-25) =
* TaxJar calc overrides all other taxes
* Hide order admin calculate tax button

= 1.0 (2014-08-11) =
* Initial release

== Upgrade Notice ==

= 1.5.0 =
1.5.0 is a compatibility update for WooCommerce 3.2 and also resolves several issues around discounts and product tax classes. After upgrading, please test your checkout process to ensure sales tax is calculated properly. If you run into any issues, email [TaxJar support](mailto:support@taxjar.com) for help.

= 1.4.0 =
1.4.0 is an update to support backend order calculations for both WooCommerce 2.6.x and 3.x. After upgrading, please test your checkout process to ensure sales tax is calculated properly. If you run into any issues, email [TaxJar support](mailto:support@taxjar.com) for help.

= 1.3.3 =
1.3.3 is a minor update to ensure sales tax isn't collected upfront for recurring subscriptions with a trial period. After upgrading, please test your checkout process to ensure sales tax is calculated properly. If you run into any issues, email [TaxJar support](mailto:support@taxjar.com) for help.

= 1.3.2 =
1.3.2 is a minor compatibility update for WooCommerce < 2.6.2. After upgrading, please test your checkout process to ensure sales tax is calculated properly. If you run into any issues, email [TaxJar support](mailto:support@taxjar.com) for help.

= 1.3.1 =
1.3 is a major update to our plugin and requires WooCommerce 2.6+. After upgrading, please test your checkout process to ensure sales tax is calculated properly. If you run into any issues, email [TaxJar support](mailto:support@taxjar.com) for help.

= 1.1.2 =
Please make sure you have PHP 5.3+ installed (goto WooCommerce->System Status to check warnings)

= 1.1.1 =
When upgrading we recommend clearing transients under WooCommerce->System Status->Tools

== How It Works ==

Here’s how the TaxJar for WooCommerce plugin works:

At the cart and checkout page, TaxJar takes the following input from your store:

* Your store address
* Your customer's address
* Order details such as line items and shipping

And returns accurate sales tax (including state, county, city, and special taxes) based on...

* Your store address
* Any nexus locations stored in your TaxJar account
* Local sales tax sourcing laws (origin-based or destination-based)
* Shipping taxability laws (shipping is not taxable in every state)
* Product exemptions if configured for specific products
* Itemized discounts for coupons
* Sales tax holidays
