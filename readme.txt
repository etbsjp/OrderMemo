=== ETBS Order Note Templates for WooCommerce ===
Contributors:      etbsjp
Donate link:       https://etbs.jp/product/donate/
Tags:              woocommerce, order notes, templates, canned responses, shop manager
Requires PHP:      7.4
Tested up to:      7.1
Stable tag:        1.1.0
Requires Plugins:  woocommerce
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Insert saved text templates into WooCommerce order notes with one click, with placeholders such as the customer name and order number.

== Description ==

Adds a template picker to the WooCommerce order edit screen. Choose a saved template, click "Insert", and the text is added to the order note field with the placeholders already filled in from that order. You can review and edit the text before you add the note.

Useful for the messages you type again and again: shipping notices, payment confirmations, cancellation guidance, and so on.

**Features**

* Register templates under WooCommerce, with a title and a body.
* Placeholders are replaced with the order's data at the moment you insert the template.
* Your own typing in the note field is never erased. The template is appended.
* Works with both HPOS and legacy order storage.
* Developers can add their own placeholders with the `ormm_tags` filter.

**Placeholders**

* `{customer_name}` - billing name
* `{order_number}` - order number
* `{order_date}` - order date
* `{order_total}` - order total, with the currency symbol
* `{payment_method}` - payment method
* `{shipping_method}` - shipping method
* `{site_name}` - site name

A paid add-on for Japanese stores is available.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen in WordPress.
3. Activate the plugin through the "Plugins" screen.
4. Register your templates, then open any order to use them.

== Frequently Asked Questions ==

= Where do I create the templates? =

Under WooCommerce in the admin menu. Each template has a title and a body. The "Order" field sets the display order.

= Where do the templates appear? =

On the order edit screen, above the "Add note" field. If no template is registered, nothing is shown there.

= Is a note to the customer sent by email? =

Yes. WooCommerce emails every "Note to customer" as it is. Check the inserted text before you click "Add note".

= Can I use HTML in a template? =

No. The body is saved as plain text, and HTML tags are removed when you save.

= How do I add my own placeholder? =

Use the `ormm_tags` filter. The keys include the curly braces.

`add_filter( 'ormm_tags', function ( $tags, $order ) {
	$tags['{tracking_number}'] = (string) $order->get_meta( '_tracking_number' );
	return $tags;
}, 10, 2 );`

= Are my templates deleted when I uninstall the plugin? =

No. Your templates are kept in the database when you delete the plugin, so they are still there if you install it again.

= I use "ETBS OrderMemo" (the version distributed from GitHub). How do I switch? =

The two plugins cannot be active at the same time. Deactivate "ETBS OrderMemo" first, then activate this plugin. Your templates are shared, so they carry over as they are.

Only deactivate the old plugin. If you want to delete it, first update it to version 1.0.3 or later. Earlier versions delete all of your templates when the plugin is deleted.

== Changelog ==

= 1.1.0 =
* Initial release on WordPress.org.

== Upgrade Notice ==

= 1.1.0 =
Initial release on WordPress.org. If "ETBS OrderMemo" is active, deactivate it first.
