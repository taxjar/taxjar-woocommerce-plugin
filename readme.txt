==== TaxJar - Sales Tax Automation for WooCommerce ====
Contributors: tonkapark, taxjar
Tags: woocommerce, taxes, tax calculation, free tax calculation, sales tax, taxjar
Requires at least: 4.0
Tested up to: 4.4.0
Stable tag: 1.2.0
License: GPLv2 or later

Save hours every month by putting your sales tax on autopilot. Automated, multi-state sales tax calculation, collection, and filing.

== Description ==

[TaxJar](http://www.taxjar.com) eliminates the need for you to even think about sales tax with your WooCommerce store. This powerful extension calculates how much sales tax should be collected at checkout. Then we update your sales tax collected by state, county, and city on a daily basis for you to view at any time. Finally, we’ll file what you owe to the states for you.

Here’s why customers love this plugin:

* Rates are never out-of-date - TaxJar maintains more than 10,000 tax rates, updated monthly. No more manually uploading rate tables. Ever.
* US and Canada Support - Not only do you get US tax rates, this powerful extension also supports Canadian tax collection as well.
* Multi-state sales tax collection - Collect sales tax accurately in not just the state where you’re based, but in every state you have nexus.
* Daily tax preparation to avoid surprises - Every day we’ll download your sales and compile it by state, county, city, and special tax – exactly what you need to be able to file sales tax returns. No more last minute scrambling to get the data you need.
* AutoFile – automated sales tax filing - As deadlines approach, we’ll even file your returns and make your payments to the states you enroll in. No more forgetting and annoying penalties.
* Dead Simple pricing – No contracts. No activations fees. No credit card required. Use it as much as you want for 30 days for free. Beyond 30 days, pay based on the number of API calls you make each month.

Other Notable Features:

* Simple install – starting collecting sales tax in minutes
* Compatible with Woocommerce 2.3+ and Wordpress 4.2+
* Avoid sales tax surprises
* Automated configuration – all of your tax settings are done automatically!
* Supports tax exempt items set by store manager
* If you also sell on other platforms, get your sales tax data all in a single place


[TaxJar](http://www.taxjar.com) provides easy & accurate ecommerce sales tax reporting in addition to their sales tax calculation.

== Installation ==

Setting up tax collection with TaxJar is simple. [Full configuration steps detailed in our Knowledge Base](https://taxjar.groovehq.com/knowledge_base/topics/how-to-install-the-woocommerce-sales-tax-plugin-from-taxjar).

Or you can follow these steps to install the plugin:

1. Install the WooCommerce plugin
1. Install the Sales Tax Calculation Plugin either via the WordPress.org plugin directory (just search for “TaxJar”), or by uploading the files to your server.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Find TaxJar in your WooCommerce -> Settings -> Integrations tab or in your Admin Side Menu under WooCommerce.
1. You’ll need an API Token. If you already have a TaxJar account, click the “Click here to get a TaxJar API Token” link. Then click the button in the “API Token” box of your account page. Copy the API Token. If you don’t have a TaxJar account, you’ll be asked to setup one first. Then follow the simple steps above.
1. Paste the API Token into the “API Token” field.
1. Fill out the rest of your settings. All that TaxJar needs to calculate sales tax is the zip code and city from which you ship products. We automatically detect your country and state based on your WooCommerce configuration.
1. Check the box next to “Enable TaxJar Calculations”.
1. If you have a TaxJar Reporting subscription, you may check the box next to "Enable order downloads to TaxJar" to allow TaxJar to connect and download the transactions on your store for TaxJar's [AutoFile](http://www.taxjar.com/autofile/) and Reporting features.
1. Click “Save Changes”.

Suggested WooCommerce Tax Settings

1. We automatically setup your store's tax settings to work with our API. There is no need to configure WooCommerce Taxes.
1. Full reporting of your sales tax collected, AutoFile and more available in your TaxJar account.

== Frequently Asked Questions ==

= What are the requirements to collect sales tax with TaxJar for WooCommerce? =

As long as you have a business address, TaxJar’s plugin can calculate a sales tax rate based on your location and the location of your customer.

= How do I get a TaxJar API Token? =

If you already have a TaxJar account, click the “Click here to get a TaxJar API Token” link. Then click the button in the “API Token” box of your account page. Copy the API Token. If you don’t have a TaxJar account, you’ll be asked to setup one first. Then follow the simple steps above.

= What does this extension cost? =

It’s free to use as much as you want for 30 days. If you want your sales tax rates updated monthly or if you want automated reporting and filing, then our pricing is based on the number of calls you make to our API (in other words how many time you request a calculation). Pricing starts at $19 per month for 1,000 calculations.

= Does this cost more if I have nexus in more than one state? =

Nope. The cost is the same no matter if you have nexus in one state or 40 states.

= Can TaxJar file my sales tax returns automatically for me? =

Yes. We can file sales tax returns for you in more than 25 states.

= Is there a separate fee to file my sales tax returns for me? =

Yes. The fee is $19.95 per state, per filing.

== Screenshots ==

1. TaxJar for WooCommerce Plugin Settings

== Changelog ==
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
* Use new v2 TaxJar API (http://developers.taxjar.com/api/)
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
* Uses new API (with support for Canada): [read the docs](http://www.taxjar.com/api/docs/)
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

= 1.1.2 =
Please make sure you have PHP 5.3+ installed (goto WooCommerce->System Status to check warnings)

= 1.1.1 =
When upgrading we recommend clearing transients under WooCommerce->System Status->Tools

== How It Works ==

Here’s how the TaxJar Smart Sales Tax API works.

TaxJar takes the following input from your store:

* Seller’s home state, city, and zip code
* Transaction amount
* The city, state, and zip code where item is being shipped
* Any shipping fees charged

And returns an accurate sales tax rate (including state, county, city, and special taxes) based on

* Seller's nexus based on your WooCommerce ship from settings
* Any nexus with other addresses stored in your TaxJar account
* Local sales tax sourcing laws (origin-based or destination-based)
* Shipping taxability laws (shipping is not taxable in every state)

TaxJar for WooCommerce automatically determines

* Nexus
* Origin vs. Destination sourcing
* Shipping taxability
* Sales tax rate (state county, city, special)
