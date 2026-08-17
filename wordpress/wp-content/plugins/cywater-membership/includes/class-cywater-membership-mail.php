<?php
/**
 * Email-safe CYWater presentation for member-facing transactional messages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Mail {
	/**
	 * Send a member-facing transactional message with an HTML body and plain-text alternative.
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject.
	 * @param array  $message Structured message fields.
	 * @return bool
	 */
	public static function send( $to, $subject, array $message ) {
		$html  = self::render_html( $message );
		$plain = self::render_plain( $message );
		$set_alt_body = static function ( $phpmailer ) use ( $plain ) {
			$phpmailer->AltBody = $plain;
		};

		add_action( 'phpmailer_init', $set_alt_body, PHP_INT_MAX );
		try {
			return (bool) wp_mail(
				$to,
				$subject,
				$html,
				array(
					'From: CYWater Accounts <accounts@cywater.org>',
					'Reply-To: CYWater Membership <membership@cywater.org>',
					'Content-Type: text/html; charset=UTF-8',
				)
			);
		} finally {
			remove_action( 'phpmailer_init', $set_alt_body, PHP_INT_MAX );
		}
	}

	private static function render_html( array $message ) {
		$eyebrow     = sanitize_text_field( $message['eyebrow'] ?? __( 'Member services', 'cywater-membership' ) );
		$title       = sanitize_text_field( $message['title'] ?? '' );
		$intro       = sanitize_textarea_field( $message['intro'] ?? '' );
		$body        = sanitize_textarea_field( $message['body'] ?? '' );
		$button      = sanitize_text_field( $message['button_label'] ?? '' );
		$button_url  = esc_url( $message['button_url'] ?? '' );
		$notice      = sanitize_textarea_field( $message['notice'] ?? '' );
		$preheader   = sanitize_text_field( $message['preheader'] ?? $title );
		$website_url = esc_url( home_url( '/' ) );
		$support_url = 'mailto:membership@cywater.org';
		$year        = gmdate( 'Y' );

		$paragraphs = '';
		foreach ( array_filter( array( $intro, $body ) ) as $paragraph ) {
			$paragraphs .= '<p style="margin:0 0 18px;color:#34435b;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;">' . nl2br( esc_html( $paragraph ) ) . '</p>';
		}

		$action = '';
		if ( $button && $button_url ) {
			$action = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 24px;"><tr><td style="border-radius:999px;background:#0f7f77;"><a href="' . esc_url( $button_url ) . '" style="display:inline-block;padding:14px 26px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;line-height:1;text-decoration:none;border-radius:999px;">' . esc_html( $button ) . '</a></td></tr></table>';
			$action .= '<p style="margin:0 0 24px;color:#667085;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;">' . esc_html__( 'If the button does not work, copy and paste this secure link into your browser:', 'cywater-membership' ) . '<br><a href="' . esc_url( $button_url ) . '" style="color:#0f7f77;text-decoration:underline;word-break:break-all;">' . esc_html( $button_url ) . '</a></p>';
		}

		$notice_html = $notice ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:8px;background:#eef5f2;border-left:3px solid #0f7f77;"><tr><td style="padding:15px 17px;color:#34435b;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;">' . nl2br( esc_html( $notice ) ) . '</td></tr></table>' : '';

		return '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><title>' . esc_html( $title ) . '</title></head><body style="margin:0;padding:0;background:#f3f0e7;">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . esc_html( $preheader ) . '</div>'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f0e7;"><tr><td align="center" style="padding:32px 14px;">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e2ddd0;border-radius:16px;overflow:hidden;">'
			. '<tr><td style="height:6px;background:#0f7f77;font-size:0;line-height:0;">&nbsp;</td></tr>'
			. '<tr><td style="padding:30px 38px 24px;border-bottom:1px solid #ece8df;">'
			. '<div style="color:#0b1b30;font-family:Georgia,Times New Roman,serif;font-size:30px;font-weight:700;line-height:1;">CYWater</div>'
			. '<div style="margin-top:9px;color:#667085;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.45;letter-spacing:.08em;text-transform:uppercase;">International Association of Contemporary Young Scholars in Water Sciences</div>'
			. '</td></tr>'
			. '<tr><td style="padding:34px 38px 36px;">'
			. '<div style="margin-bottom:12px;color:#0f7f77;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;">' . esc_html( $eyebrow ) . '</div>'
			. '<h1 style="margin:0 0 22px;color:#0b1b30;font-family:Georgia,Times New Roman,serif;font-size:34px;font-weight:600;line-height:1.18;">' . esc_html( $title ) . '</h1>'
			. $paragraphs . $action . $notice_html
			. '</td></tr>'
			. '<tr><td style="padding:24px 38px;background:#0b1b30;color:#d8e0e7;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;">'
			. '<strong style="color:#ffffff;">CYWater Member Services</strong><br>'
			. '<a href="' . esc_url( $support_url ) . '" style="color:#a8d8d2;text-decoration:none;">membership@cywater.org</a> &nbsp;&middot;&nbsp; <a href="' . esc_url( $website_url ) . '" style="color:#a8d8d2;text-decoration:none;">cywater.org</a><br>'
			. '<span style="color:#9eabb8;">202 E Green St, Suite 2, Champaign, IL 61820, USA</span><br>'
			. '<span style="color:#9eabb8;">&copy; ' . esc_html( $year ) . ' CYWater. ' . esc_html__( 'This is an automated account message.', 'cywater-membership' ) . '</span>'
			. '</td></tr></table></td></tr></table></body></html>';
	}

	private static function render_plain( array $message ) {
		$parts = array(
			'CYWater',
			'International Association of Contemporary Young Scholars in Water Sciences',
			'',
			strtoupper( sanitize_text_field( $message['eyebrow'] ?? __( 'Member services', 'cywater-membership' ) ) ),
			sanitize_text_field( $message['title'] ?? '' ),
			'',
			sanitize_textarea_field( $message['intro'] ?? '' ),
			sanitize_textarea_field( $message['body'] ?? '' ),
		);
		if ( ! empty( $message['button_label'] ) && ! empty( $message['button_url'] ) ) {
			$parts[] = '';
			$parts[] = sanitize_text_field( $message['button_label'] ) . ': ' . esc_url_raw( $message['button_url'] );
		}
		if ( ! empty( $message['notice'] ) ) {
			$parts[] = '';
			$parts[] = sanitize_textarea_field( $message['notice'] );
		}
		$parts[] = '';
		$parts[] = 'CYWater Member Services';
		$parts[] = 'membership@cywater.org | ' . home_url( '/' );
		$parts[] = '202 E Green St, Suite 2, Champaign, IL 61820, USA';
		return implode( "\n", array_filter( $parts, static function ( $part ) { return null !== $part; } ) );
	}
}
