## FAQ

__Which WooCommerce versions are supported?__

WooCommerce 2 and WooCommerce 3 are supported.

__Which WordPress versions are supported?__

The plugin has been tested with WordPress 4.5 and higher. It also supports WordPress 5.X.

__Does the plugin support product variations / variables?__

Yes, product variations are supported.

__Does the plugin support WooCommerce Subscriptions?__

Yes, the WooCommerce Subscription plugin is supported and renewals are tracked as well.

__How is this plugin different to other WooCommerce Matomo / Piwik plugins?__

Other plugins don't really work at all, and if they work they don't track the data correctly.

As we are using WooCommerce ourselves on the matomo.org Marketplace we can ensure it works very well, is stable and it doesn't have any known security issues.

This plugin tracks the data in a special way to ensure very accurate tracking of cart updates and orders that you won't get anywhere else. This allows you for example to much better find out where your users abandon your cart. As the creators of Matomo, we can also ensure that the data is tracked correctly.

Another benefit of our solution is, that it tracks orders and cart updates even if a user is using an ad-blocker. In this case you might not see any page views, but still be able to analyze all ecommerce related information.

__Do I still need a plugin to track regular page views, events, etc?__

Yes, you will still need a plugin do track regular pageviews, outlinks, downloads and more. We recommend to use our WooCommerce plugin in combination with [WP-Matomo/WP-Piwik](https://wordpress.org/plugins/wp-piwik/).

__How do I install and update the plugin on WooCommerce?__

Once you have installed this plugin on your Matomo, go to "Administration" and then "WooCommerce" in Matomo. There you will find straight forward installation instructions and the download of the WooCommerce plugin.

You will be able to update the WooCommerce plugin with just one click.

__Are there any known issues?__

It currently may create once a new visitor as soon as a cart is updated. If this is the case, all following pageviews and actions will be tracked into the newly created visitor. This happens only when for example a user visited your shop in the past, deletes all the cookies and then visits your shop again. It may also happen when opening the website in an "Incognito window" in your browser. We will be solving this issue in Matomo itself.

__Are there any other requirements?__

The WooCommerce server needs to be able to ping (via HTTP/S) your Matomo installation in order to track orders and cart updates.

If you don't know what that means, you very likely don't need to worry about it and it will just work.

__Where do I find the logs of the WooCommerce plugin?__

If you enable the logging of all tracking requests to a file, you will find the logs under `wp-content/uploads/wc-logs/woo-piwik-tracking-yyyy-dd-mm-*.log`.
