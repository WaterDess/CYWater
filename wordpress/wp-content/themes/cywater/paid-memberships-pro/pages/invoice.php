<?php
/**
 * Template: Invoice
 * Version: 3.1
 *
 * CYWater single-order receipt. PMPro's maintained template remains the
 * authoritative order-history view when no individual order is selected.
 */

global $pmpro_invoice, $pmpro_msg, $pmpro_msgt;

if ( empty( $pmpro_invoice ) || ! class_exists( 'CYWater_Membership_Receipt' ) ) {
	include PMPRO_DIR . '/pages/invoice.php';
	return;
}

$receipt = CYWater_Membership_Receipt::view_model( $pmpro_invoice );
if ( ! $receipt ) {
	include PMPRO_DIR . '/pages/invoice.php';
	return;
}

$logo_url = function_exists( 'cywater_brand_logo_url' ) ? cywater_brand_logo_url() : '';
?>
<div class="pmpro cywater-receipt-shell">
	<?php if ( $pmpro_msg ) : ?>
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_message ' . $pmpro_msgt, $pmpro_msgt ) ); ?>"><?php echo wp_kses_post( $pmpro_msg ); ?></div>
	<?php endif; ?>

	<div class="cywater-receipt-actions">
		<button class="btn btn--outline" type="button" onclick="window.print()">Print or save as PDF</button>
		<a class="link" href="<?php echo esc_url( pmpro_url( 'invoice' ) ); ?>">View all membership orders</a>
	</div>

	<article class="cywater-receipt" aria-labelledby="cywater-receipt-title">
		<header class="cywater-receipt__header">
			<div class="cywater-receipt__brand">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" width="493" height="600">
				<?php endif; ?>
				<div>
					<strong>CYWater</strong>
					<span><?php echo esc_html( $receipt['organization_name'] ); ?></span>
				</div>
			</div>
			<div class="cywater-receipt__document">
				<p>Official payment receipt</p>
				<h1 id="cywater-receipt-title">Membership Dues Receipt</h1>
			</div>
		</header>

		<section class="cywater-receipt__summary" aria-label="Receipt summary">
			<div><span>Receipt / order no.</span><strong>#<?php echo esc_html( $receipt['order_number'] ); ?></strong></div>
			<div><span>Payment date</span><strong><?php echo esc_html( $receipt['order_date'] ); ?></strong></div>
			<div><span>Status</span><strong class="cywater-receipt__status cywater-receipt__status--<?php echo esc_attr( $receipt['status_key'] ); ?>"><?php echo esc_html( $receipt['status'] ); ?></strong></div>
		</section>

		<section class="cywater-receipt__parties" aria-label="Payee and member">
			<div>
				<h2>Pay to</h2>
				<p><strong><?php echo esc_html( $receipt['organization_name'] ); ?></strong><br><?php echo esc_html( $receipt['organization_address'] ); ?><br><a href="mailto:<?php echo esc_attr( $receipt['organization_email'] ); ?>"><?php echo esc_html( $receipt['organization_email'] ); ?></a><br><a href="<?php echo esc_url( $receipt['organization_site'] ); ?>">cywater.org</a></p>
			</div>
			<div>
				<h2>Bill to</h2>
				<p><?php echo implode( '<br>', array_map( 'esc_html', $receipt['bill_to'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>
		</section>

		<table class="cywater-receipt__items">
			<thead><tr><th>Description</th><th>Term</th><th>Qty</th><th>Amount</th></tr></thead>
			<tbody><tr><td><strong><?php echo esc_html( $receipt['membership_name'] ); ?></strong><br><span>CYWater individual membership dues</span></td><td><?php echo esc_html( $receipt['membership_term'] ); ?></td><td>1</td><td><?php echo esc_html( $receipt['subtotal'] ); ?></td></tr></tbody>
			<tfoot>
				<tr><th colspan="3">Subtotal</th><td><?php echo esc_html( $receipt['subtotal'] ); ?></td></tr>
				<tr><th colspan="3">Tax</th><td><?php echo esc_html( $receipt['tax'] ); ?></td></tr>
				<tr class="cywater-receipt__total"><th colspan="3">Total paid</th><td><?php echo esc_html( $receipt['amount_paid'] ); ?></td></tr>
			</tfoot>
		</table>

		<section class="cywater-receipt__payment" aria-label="Payment details">
			<div><span>Payment method</span><strong><?php echo esc_html( $receipt['payment_method'] ); ?></strong></div>
			<div><span>Transaction currency</span><strong><?php echo esc_html( $receipt['currency'] ); ?></strong></div>
			<div><span>Balance due</span><strong><?php echo esc_html( $receipt['balance_due'] ); ?></strong></div>
		</section>

		<aside class="cywater-receipt__currency-note">
			<strong>Currency information</strong>
			<p><?php echo esc_html( $receipt['currency_note'] ); ?></p>
		</aside>

		<footer class="cywater-receipt__footer">
			<p>This document confirms payment of CYWater membership dues. Tax treatment depends on the payer's jurisdiction.</p>
			<p>Billing questions: <a href="mailto:<?php echo esc_attr( $receipt['organization_email'] ); ?>"><?php echo esc_html( $receipt['organization_email'] ); ?></a></p>
		</footer>
	</article>
</div>
