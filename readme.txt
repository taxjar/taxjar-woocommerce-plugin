==== Sales Tax Calculations for WooCommerce ====
Contributors: seanvoss, tonkapark
Tags: woocommerce, taxes, tax calculation, free tax calculation, sales tax, taxjar
Requires at least: 3.0
Tested up to: 3.8.1
Stable tag: 0.3
Donate link: https://blog.seanvoss.com/shop/taxjar/
License: GPLv2 or later

TaxJar for WooCommerce helps you collect accurate sales tax with almost no work! Stop uploading and updating rate tables.

== Description ==

So you want to collect sales tax from your customers but have no idea where to begin? 

TaxJar's Sales Tax Calculations plugin for WooCommerce is the easiest and fastest way to collect sales tax from your customers.

Here’s why customers love this plugin: 

* Setup is easy – all you need to enter is your business address! TaxJar takes care of everything else.
* Collect local rates – if a sales requires you to collect city and county sales tax, we’ll include it!
* Rates are never out-of-date - TaxJar maintains more than 10,000 tax rates, updated monthly. Stop uploading rate tables!
* Simple pricing – based on how many transactions you do each month, TaxJar for WooCommerce starts at $9.95 per month for up to 200 calculation requests.

BE ADVISED: TaxJar for WooCommerce is setup to work for sales in the US only. We do not support international sales at this time.

[TaxJar](http://www.taxjar.com) provides easy & accurate ecommerce sales tax reporting in addition to their sales tax calculation.

== Installation ==

Setting up tax collection with TaxJar is simple. Just follow these steps.

1. Install the WooCommerce plugin
1. Install the Sales Tax Calculation Plugin either via the WordPress.org plugin directory (just search for “TaxJar”), or by uploading the files to your server.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Find TaxJar in your WordPress Settings (left menu bar) 
1. You’ll need an API Token. If you already have a TaxJar account, click the “Click here to get a TaxJar API Token” link. Then click the button in the “API Token” box of your account page. Copy the API Token. If you don’t have a TaxJar account, you’ll be asked to setup one first. Then follow the simple steps above.
1. Paste the API Token into the “Your TaxJar API Token” field.
1. Fill out the rest of your settings. All that TaxJar needs to calculate sales tax is your business address.
1. Check the box next to “Enable TaxJar”.
1. Click “Save Changes”.

== How It Works ==

Here’s how the TaxJar Smart Sales Tax API works.

TaxJar takes the following input from your store:

* Seller’s home state, city, and zip code
* Transaction amount
* The city and zip code where item is being shipped
* Any shipping fees charged

And returns an accurate sales tax rate (including state, county, city, special tax) based on

* Seller’s nexus – does Buyer’s city and zip-code cause nexus?
* Local sales tax sourcing laws (if the buyer’s state is origin-based or destination based)
* Shipping taxability laws (shipping is not taxable in every state)

TaxJar for WooCommerce automatically determines

* Nexus
* Origin vs. Destination sourcing
* Shipping taxability
* Sales tax rate (state county, city, special)

== Frequently Asked Questions ==

= What are the requirements to collect sales Tax with TaxJar for WooCommerce? =

As long as you have a business address, TaxJar’s plugin can calculate a sales tax rate based on your location and the location of your customer.

= How do I get a TaxJar API Token? =

If you already have a TaxJar account, click the “Click here to get a TaxJar API Token” link. Then click the button in the “API Token” box of your account page. Copy the API Token. If you don’t have a TaxJar account, you’ll be asked to setup one first. Then follow the simple steps above.

= How much does TaxJar for WooCommerce cost? =

Our pricing is simple. You pay based on the number of times you use the tax calculation service. Pricing begins at $9.95/month for up to 200 calculations.


== Changelog ==

= 0.3 =
* Documentation cleanup

= 0.2 =
* Move Session_Start Earlier 
= 0.1 =
* Initial Commit

== Screenshots ==

1. TaxJar for WooCommerce Plugin Settings
