<?php
/**
 * Stand-in for WC_Order, used by tests when WooCommerce is not loaded.
 * WooCommerce が読み込まれていないときにテストが使う、WC_Order の代役。
 *
 * It answers just the methods ormm_get_tags() calls.
 * ormm_get_tags() が呼ぶメソッドにだけ答える。
 *
 * @package etbs-order-note-templates
 */

class WC_Order {
	/**
	 * Creation date; none, so {order_date} is empty.
	 * 作成日。無しにして、{order_date} を空にする。
	 *
	 * @return null
	 */
	public function get_date_created() {
		return null;
	}

	/**
	 * Billing name.
	 * 請求先の氏名。
	 *
	 * @return string
	 */
	public function get_formatted_billing_full_name() {
		return 'Test Customer';
	}

	/**
	 * Order number.
	 * 注文番号。
	 *
	 * @return string
	 */
	public function get_order_number() {
		return '1001';
	}

	/**
	 * Order total, as HTML.
	 * 注文合計（HTML）。
	 *
	 * @return string
	 */
	public function get_formatted_order_total() {
		return '&yen;1,000';
	}

	/**
	 * Payment method title.
	 * 支払方法名。
	 *
	 * @return string
	 */
	public function get_payment_method_title() {
		return 'Bank transfer';
	}

	/**
	 * Shipping method.
	 * 配送方法。
	 *
	 * @return string
	 */
	public function get_shipping_method() {
		return 'Flat rate';
	}
}
