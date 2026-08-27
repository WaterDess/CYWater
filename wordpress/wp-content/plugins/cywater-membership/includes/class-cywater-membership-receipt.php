<?php
/**
 * Branded PMPro membership-dues receipts and paid-checkout email.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Receipt {
	public const ORGANIZATION_NAME = 'International Association of Contemporary Young Scholars in Water Sciences';
	public const ORGANIZATION_SHORT_NAME = 'CYWater';
	public const BILLING_EMAIL = 'billing@cywater.org';
	public const MAILING_ADDRESS = '202 E. Green St, Suite 2, Champaign, IL 61820, USA';

	public static function register() {
		// Run before PMPro's required KSES pass at priority 11.
		add_filter( 'pmpro_email_subject', array( __CLASS__, 'email_subject' ), 10, 2 );
		add_filter( 'pmpro_email_body', array( __CLASS__, 'email_body' ), 10, 2 );
	}

	/**
	 * Build the one canonical receipt data model used by web and email views.
	 *
	 * @param MemberOrder $order PMPro order.
	 * @return array<string,mixed>
	 */
	public static function view_model( $order ) {
		if ( ! is_object( $order ) ) {
			return array();
		}

		if ( method_exists( $order, 'getUser' ) ) {
			$order->getUser();
		}
		if ( method_exists( $order, 'getMembershipLevel' ) ) {
			$order->getMembershipLevel();
		}

		$user       = ! empty( $order->user ) && $order->user instanceof WP_User ? $order->user : null;
		$level      = ! empty( $order->membership_level ) ? $order->membership_level : null;
		$currency   = strtoupper( sanitize_key( (string) ( $order->currency ?? get_option( 'pmpro_currency', 'USD' ) ) ) );
		$currency   = $currency ?: 'USD';
		$order_date = method_exists( $order, 'getTimestamp' ) ? (int) $order->getTimestamp() : 0;
		$order_date = $order_date ?: current_time( 'timestamp' );
		$total      = (float) ( $order->total ?? 0 );
		$subtotal   = isset( $order->subtotal ) ? (float) $order->subtotal : $total;
		$tax        = isset( $order->tax ) ? (float) $order->tax : max( 0, $total - $subtotal );
		$status     = sanitize_key( (string) ( $order->status ?? '' ) );

		$bill_to = array();
		$billing = ! empty( $order->billing ) && is_object( $order->billing ) ? $order->billing : null;
		$name    = $billing && ! empty( $billing->name ) ? (string) $billing->name : ( $user ? $user->display_name : '' );
		if ( $name ) {
			$bill_to[] = $name;
		}
		if ( $user ) {
			$institution = trim( (string) get_user_meta( $user->ID, 'cyw_institution_name', true ) );
			if ( $institution && ! in_array( $institution, $bill_to, true ) ) {
				$bill_to[] = $institution;
			}
			$bill_to[] = $user->user_email;
		}
		if ( $billing ) {
			$street = trim( implode( ', ', array_filter( array( $billing->street ?? '', $billing->street2 ?? '' ) ) ) );
			$city   = trim( implode( ', ', array_filter( array( $billing->city ?? '', $billing->state ?? '', $billing->zip ?? '' ) ) ) );
			foreach ( array( $street, $city, $billing->country ?? '' ) as $line ) {
				$line = trim( (string) $line );
				if ( $line ) {
					$bill_to[] = $line;
				}
			}
		}

		$term_end = '';
		if ( function_exists( 'pmpro_get_subscription_period_end_date_for_order' ) ) {
			$term_end = (string) pmpro_get_subscription_period_end_date_for_order( $order, 'F j, Y' );
		}
		$term = date_i18n( 'F j, Y', $order_date );
		if ( $term_end && $term_end !== $term ) {
			$term .= ' – ' . $term_end;
		}

		$payment_method = self::payment_method( $order );

		return array(
			'order_number'       => sanitize_text_field( (string) ( $order->code ?? '' ) ),
			'order_date'         => date_i18n( 'F j, Y', $order_date ),
			'status'             => self::status_label( $status ),
			'status_key'         => $status,
			'membership_name'    => sanitize_text_field( (string) ( $level->name ?? 'Membership dues' ) ),
			'membership_term'    => $term,
			'bill_to'            => array_values( array_unique( array_filter( $bill_to ) ) ),
			'payment_method'     => $payment_method,
			'currency'           => $currency,
			'subtotal'           => self::money( $subtotal, $currency ),
			'tax'                => self::money( $tax, $currency ),
			'total'              => self::money( $total, $currency ),
			'amount_paid'        => self::money( $total, $currency ),
			'balance_due'        => self::money( in_array( $status, array( 'success', 'cancelled', 'refunded' ), true ) ? 0 : $total, $currency ),
			'currency_note'      => self::currency_note( $currency ),
			'organization_name'  => self::ORGANIZATION_NAME,
			'organization_short' => self::ORGANIZATION_SHORT_NAME,
			'organization_email' => self::BILLING_EMAIL,
			'organization_site'  => home_url( '/' ),
			'organization_address' => self::MAILING_ADDRESS,
			'order_url'          => function_exists( 'pmpro_url' ) ? pmpro_url( 'invoice', '?invoice=' . rawurlencode( (string) ( $order->code ?? '' ) ) ) : '',
		);
	}

	public static function email_subject( $subject, $email ) {
		if ( ! is_object( $email ) || 'checkout_paid' !== ( $email->template ?? '' ) ) {
			return $subject;
		}
		$order_number = sanitize_text_field( (string) ( $email->data['order_id'] ?? '' ) );
		return $order_number
			? sprintf( 'CYWater membership payment receipt — Order #%s', $order_number )
			: 'CYWater membership payment receipt';
	}

	public static function email_body( $body, $email ) {
		if ( ! is_object( $email ) || 'checkout_paid' !== ( $email->template ?? '' ) || ! class_exists( 'MemberOrder' ) ) {
			return $body;
		}

		$order_number = sanitize_text_field( (string) ( $email->data['order_id'] ?? '' ) );
		$order        = $order_number ? new MemberOrder( $order_number ) : null;
		$model        = $order && ! empty( $order->id ) ? self::view_model( $order ) : array();
		if ( ! $model ) {
			return $body;
		}

		$recipient = ! empty( $model['bill_to'][0] ) ? $model['bill_to'][0] : 'Member';
		$bill_to   = implode( '<br>', array_map( 'esc_html', $model['bill_to'] ) );
		$order_url = esc_url( $model['order_url'] );

		ob_start();
		?>
		<div style="margin:0;padding:24px;background:#f5f2ea;color:#14233a;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45">
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">
				<table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #dedbd2;border-radius:10px;overflow:hidden">
					<tr><td style="padding:22px 26px 18px;border-bottom:3px solid #14857d">
						<div style="font-size:22px;font-weight:700;color:#0d1d31">CYWater</div>
						<div style="margin-top:3px;color:#56657a;font-size:11px;line-height:1.35"><?php echo esc_html( self::ORGANIZATION_NAME ); ?></div>
					</td></tr>
					<tr><td style="padding:22px 26px">
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
							<td valign="top"><div style="color:#14857d;font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase">Membership confirmed</div><h1 style="margin:5px 0 4px;color:#0d1d31;font-family:Georgia,serif;font-size:26px;line-height:1.15">Membership Dues Receipt</h1></td>
							<td valign="top" align="right"><span style="display:inline-block;padding:5px 10px;border-radius:999px;background:#dff3e9;color:#16613d;font-size:11px;font-weight:700"><?php echo esc_html( strtoupper( $model['status'] ) ); ?></span></td>
						</tr></table>
						<p style="margin:14px 0 18px">Thank you, <?php echo esc_html( $recipient ); ?>. Your CYWater <?php echo esc_html( $model['membership_name'] ); ?> is active. This email is your payment confirmation and receipt.</p>
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:18px;background:#f8f7f2;border:1px solid #e5e1d8">
							<tr><td style="padding:10px 12px;color:#667286;font-size:11px">RECEIPT / ORDER NO.</td><td style="padding:10px 12px;color:#667286;font-size:11px">PAYMENT DATE</td></tr>
							<tr><td style="padding:0 12px 11px;font-weight:700">#<?php echo esc_html( $model['order_number'] ); ?></td><td style="padding:0 12px 11px;font-weight:700"><?php echo esc_html( $model['order_date'] ); ?></td></tr>
						</table>
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin-bottom:15px">
							<tr style="background:#0d1d31;color:#fff"><th align="left" style="padding:9px 10px;font-size:11px">DESCRIPTION</th><th align="right" style="padding:9px 10px;font-size:11px">AMOUNT</th></tr>
							<tr><td style="padding:11px 10px;border-bottom:1px solid #e5e1d8"><strong><?php echo esc_html( $model['membership_name'] ); ?></strong><br><span style="color:#667286;font-size:12px"><?php echo esc_html( $model['membership_term'] ); ?></span></td><td align="right" style="padding:11px 10px;border-bottom:1px solid #e5e1d8;white-space:nowrap"><?php echo esc_html( $model['subtotal'] ); ?></td></tr>
							<tr><td align="right" style="padding:7px 10px">Tax</td><td align="right" style="padding:7px 10px;white-space:nowrap"><?php echo esc_html( $model['tax'] ); ?></td></tr>
							<tr><td align="right" style="padding:8px 10px;border-top:1px solid #0d1d31;font-weight:700">Total paid</td><td align="right" style="padding:8px 10px;border-top:1px solid #0d1d31;font-weight:700;white-space:nowrap"><?php echo esc_html( $model['amount_paid'] ); ?></td></tr>
						</table>
						<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:14px"><tr>
							<td valign="top" width="50%" style="padding-right:12px"><div style="color:#667286;font-size:11px;font-weight:700">PAYMENT</div><div style="margin-top:4px"><?php echo esc_html( $model['payment_method'] ); ?><br>Transaction currency: <?php echo esc_html( $model['currency'] ); ?><br>Balance due: <?php echo esc_html( $model['balance_due'] ); ?></div></td>
							<td valign="top" width="50%" style="padding-left:12px"><div style="color:#667286;font-size:11px;font-weight:700">BILL TO</div><div style="margin-top:4px"><?php echo wp_kses_post( $bill_to ); ?></div></td>
						</tr></table>
						<p style="margin:12px 0;padding:10px 12px;background:#f2f7f6;border-left:3px solid #14857d;color:#4b5a6f;font-size:11px"><?php echo esc_html( $model['currency_note'] ); ?></p>
						<p style="margin:18px 0 5px"><a href="<?php echo $order_url; ?>" style="display:inline-block;padding:10px 16px;border-radius:999px;background:#14857d;color:#fff;text-decoration:none;font-weight:700">View, print, or save receipt</a></p>
					</td></tr>
					<tr><td style="padding:15px 26px;background:#0d1d31;color:#dce4e8;font-size:11px;line-height:1.5">
						<?php echo esc_html( self::ORGANIZATION_NAME ); ?><br><?php echo esc_html( self::MAILING_ADDRESS ); ?><br>Billing questions: <a href="mailto:<?php echo esc_attr( self::BILLING_EMAIL ); ?>" style="color:#8fd3ca"><?php echo esc_html( self::BILLING_EMAIL ); ?></a>
					</td></tr>
				</table>
			</td></tr></table>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function payment_method( $order ) {
		$card_type = strtolower( sanitize_text_field( (string) ( $order->cardtype ?? '' ) ) );
		$labels    = array(
			'unionpay' => 'UnionPay',
			'amex'     => 'American Express',
			'mastercard' => 'Mastercard',
			'visa'     => 'Visa',
		);
		$label = $labels[ $card_type ] ?? ucwords( str_replace( array( '_', '-' ), ' ', $card_type ) );
		if ( ! $label ) {
			$label = sanitize_text_field( (string) ( $order->payment_type ?? 'Card payment' ) );
		}

		$account = preg_replace( '/\D+/', '', (string) ( $order->accountnumber ?? '' ) );
		return $account ? sprintf( '%s ending in %s', $label, substr( $account, -4 ) ) : $label;
	}

	private static function status_label( $status ) {
		if ( in_array( $status, array( '', 'success', 'cancelled' ), true ) ) {
			return 'Paid';
		}
		if ( 'refunded' === $status ) {
			return 'Refunded';
		}
		return $status ? ucwords( str_replace( '_', ' ', $status ) ) : 'Pending';
	}

	private static function money( $amount, $currency ) {
		return sprintf( '%s %s', strtoupper( $currency ), number_format( (float) $amount, 2, '.', ',' ) );
	}

	private static function currency_note( $currency ) {
		if ( 'CNY' === $currency ) {
			return 'This order was charged and recorded in CNY.';
		}
		return sprintf(
			'This order was charged and recorded in %1$s. If your card issuer converted the payment to another currency, including CNY, the exact converted amount and exchange rate are determined by the issuer and appear on your card or bank statement. CYWater does not receive that issuer-side conversion amount.',
			$currency
		);
	}
}
