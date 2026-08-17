# Email Copy Drafts

These PMPro messages remain content drafts. PMPro remains their mail sender,
Mailpit captures local messages, and no PMPro copy override is activated until
the Board approves the copy and required legal footer.

## Official Account Email Presentation

CYWater Membership `0.8.4` applies a reusable official presentation to the
member-facing email-verification and account-closure confirmation messages. The
email-safe HTML includes the CYWater name, the association's full legal name, a
single primary action, a visible fallback URL, a security notice, and a standard
Member Services footer with the public association address, support mailbox, and
website. Every message also has a plain-text alternative.

These messages are sent as `CYWater Accounts <accounts@cywater.org>` and replies
go to `membership@cywater.org`. Internal administrator notifications stay
concise and plain so that operational records are not copied into a decorative
template. This shared component is the approved technical foundation for later
PMPro transactional-email styling, but it does not yet override the PMPro copy
drafts below.

## Registration Received

**Subject:** We received your CYWater registration

Hello {{display_name}}, we received your registration. Your membership is not
active until payment is confirmed. You can return to {{checkout_url}}.

## Payment Successful

**Subject:** CYWater payment receipt

Payment for {{membership_level}} was successful. Amount: {{amount}}. Order:
{{order_code}}. Keep this message for your records.

## Welcome

**Subject:** Welcome to CYWater

Your {{membership_level}} membership is active through {{end_date}}. Complete
your optional profile and public-directory choices at {{profile_url}}.

## Payment Failed

**Subject:** CYWater payment was not completed

We could not complete the payment. No active membership was created from this
attempt. Return to {{checkout_url}} or contact {{support_channel}}.

## Membership Expiring

**Subject:** Your CYWater membership expires on {{end_date}}

Renew at {{renewal_url}} to continue your membership. This reminder does not
charge a saved payment method automatically.

## Membership Renewed

**Subject:** CYWater membership renewed

Your renewal was confirmed. Your new membership end date is {{end_date}} and
your order reference is {{order_code}}.

## Password Reset

**Subject:** Reset your CYWater password

Use {{reset_url}} to choose a new password. The link expires. Ignore this email
if you did not request a reset.

Every production message must include the verified organization name, mailing
address, privacy link, support channel, and any legally required receipt terms.
