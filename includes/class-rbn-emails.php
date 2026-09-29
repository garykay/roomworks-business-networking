<?php
/**
 * HTML templates for every transactional email this plugin sends. Before
 * this class existed each notification was a single plain-text line built
 * inline at its call site (see the old RBN_Member_Approval::notify_*
 * methods) - this is the one place that wraps a subject + body in a
 * consistent branded layout and actually sends it, so every email handler
 * (RBN_Member_Approval, RBN_Business_Forms, ...) has one thing to call
 * instead of composing its own message string.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Emails {

	const ACCENT_COLOR  = '#1d4ed8';
	const TEXT_COLOR    = '#1f2937';
	const MUTED_COLOR   = '#6b7280';
	const BORDER_COLOR  = '#e5e7eb';
	const BG_COLOR      = '#f3f4f6';

	/**
	 * Sends an HTML email using the shared layout, temporarily switching
	 * wp_mail() to text/html and setting a From name of the site's own name
	 * (rather than the "WordPress" default) for the duration of the call
	 * only - so this never changes the content type/from name of any other
	 * plugin's mail sent later in the same request.
	 */
	public static function send( $to, $subject, $preheader, $body_html ) {
		$set_content_type = static function () {
			return 'text/html';
		};
		$set_from_name    = static function () {
			return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		};

		add_filter( 'wp_mail_content_type', $set_content_type );
		add_filter( 'wp_mail_from_name', $set_from_name );

		$sent = wp_mail( $to, $subject, self::layout( $subject, $preheader, $body_html ) );

		remove_filter( 'wp_mail_content_type', $set_content_type );
		remove_filter( 'wp_mail_from_name', $set_from_name );

		return $sent;
	}

	/**
	 * The shared header/footer chrome every email is wrapped in. Inline
	 * styles only (no <style> block) since that's the one thing that
	 * reliably survives every major email client's HTML sanitizer.
	 * $preheader is the short hidden preview text shown next to the subject
	 * line in most inbox lists.
	 */
	private static function layout( $title, $preheader, $body_html ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_url  = home_url( '/' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:<?php echo esc_attr( self::BG_COLOR ); ?>; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
	<span style="display:none; max-height:0; overflow:hidden; opacity:0;"><?php echo esc_html( $preheader ); ?></span>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:<?php echo esc_attr( self::BG_COLOR ); ?>; padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid <?php echo esc_attr( self::BORDER_COLOR ); ?>;">
					<tr>
						<td style="background-color:<?php echo esc_attr( self::ACCENT_COLOR ); ?>; padding:24px 32px;">
							<a href="<?php echo esc_url( $site_url ); ?>" style="color:#ffffff; font-size:18px; font-weight:600; text-decoration:none;"><?php echo esc_html( $site_name ); ?></a>
						</td>
					</tr>
					<tr>
						<td style="padding:32px; color:<?php echo esc_attr( self::TEXT_COLOR ); ?>; font-size:15px; line-height:1.6;">
							<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from trusted, already-escaped template methods below. ?>
						</td>
					</tr>
					<tr>
						<td style="padding:20px 32px; background-color:<?php echo esc_attr( self::BG_COLOR ); ?>; color:<?php echo esc_attr( self::MUTED_COLOR ); ?>; font-size:12px; line-height:1.5;">
							<?php
							printf(
								/* translators: %s: site name. */
								esc_html__( 'You are receiving this email because of your account on %s.', 'roomworks-business-networking' ),
								esc_html( $site_name )
							);
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * A heading + paragraphs + optional CTA button, the shape shared by
	 * every email below - kept as one helper so they all look identical
	 * rather than each hand-rolling slightly different markup.
	 *
	 * @param string   $heading
	 * @param string[] $paragraphs Already translated, plain text lines - escaped here.
	 * @param string   $button_text
	 * @param string   $button_url
	 */
	private static function body( $heading, array $paragraphs, $button_text = '', $button_url = '' ) {
		ob_start();
		?>
		<h1 style="margin:0 0 16px; font-size:20px; color:<?php echo esc_attr( self::TEXT_COLOR ); ?>;"><?php echo esc_html( $heading ); ?></h1>
		<?php foreach ( $paragraphs as $paragraph ) : ?>
			<p style="margin:0 0 16px;"><?php echo esc_html( $paragraph ); ?></p>
		<?php endforeach; ?>
		<?php if ( $button_text && $button_url ) : ?>
			<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
				<tr>
					<td style="border-radius:6px; background-color:<?php echo esc_attr( self::ACCENT_COLOR ); ?>;">
						<a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-block; padding:12px 24px; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none;"><?php echo esc_html( $button_text ); ?></a>
					</td>
				</tr>
			</table>
			<p style="margin:0; font-size:12px; color:<?php echo esc_attr( self::MUTED_COLOR ); ?>; word-break:break-all;"><?php echo esc_html( $button_url ); ?></p>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sent immediately on registration. The only thing standing between a
	 * new signup and an active account now that admins no longer review
	 * every application - see RBN_Member_Approval::send_activation_email().
	 */
	public static function activation_email( WP_User $user, $activation_url ) {
		$subject   = sprintf(
			/* translators: %s: site name. */
			__( 'Activate your %s account', 'roomworks-business-networking' ),
			get_bloginfo( 'name' )
		);
		$preheader = __( 'Confirm your email to activate your account.', 'roomworks-business-networking' );

		$body = self::body(
			/* translators: %s: first name. */
			sprintf( __( 'Hi %s,', 'roomworks-business-networking' ), $user->first_name ? $user->first_name : $user->display_name ),
			array(
				__( "Thanks for signing up! Please confirm this is your email address to activate your account - you'll be able to log in as soon as you do.", 'roomworks-business-networking' ),
				__( 'This link is valid for 48 hours.', 'roomworks-business-networking' ),
			),
			__( 'Activate my account', 'roomworks-business-networking' ),
			$activation_url
		);

		return self::send( $user->user_email, $subject, $preheader, $body );
	}

	/**
	 * Sent once the activation link above is followed (or an admin manually
	 * activates an account) - the account's actual "welcome" moment, since
	 * activation now happens automatically instead of after an admin
	 * review. Names the community (origin country -> current country pair)
	 * the member has been placed in, per the community's own naming
	 * convention (RBN_Communities::get_or_create_for_pair()).
	 */
	public static function welcome_email( WP_User $user, $community, $login_url ) {
		$subject   = sprintf(
			/* translators: %s: site name. */
			__( 'Welcome to %s!', 'roomworks-business-networking' ),
			get_bloginfo( 'name' )
		);
		$preheader = __( 'Your account is active - come say hello.', 'roomworks-business-networking' );

		$paragraphs = array(
			__( 'Your account is now active and you can log in at any time.', 'roomworks-business-networking' ),
		);

		if ( $community ) {
			$paragraphs[] = sprintf(
				/* translators: %s: community name, e.g. "South Africans in the United Kingdom". */
				__( "You've been added to %s - browse the directory to find and connect with other members there.", 'roomworks-business-networking' ),
				$community->name
			);
		}

		$paragraphs[] = __( 'You can also list your own business in the directory from your account dashboard - once your account is active, listings go live immediately, no waiting on review.', 'roomworks-business-networking' );

		$body = self::body(
			/* translators: %s: first name. */
			sprintf( __( 'Hi %s,', 'roomworks-business-networking' ), $user->first_name ? $user->first_name : $user->display_name ),
			$paragraphs,
			__( 'Log in', 'roomworks-business-networking' ),
			$login_url
		);

		return self::send( $user->user_email, $subject, $preheader, $body );
	}

	/**
	 * Sent to the configured notification address (RBN_Settings::notification_email())
	 * whenever someone registers. Purely informational now - unlike before,
	 * nothing is waiting on the admin to act on this, since the activation
	 * email above is what gates the account. Links to the Users list (rather
	 * than a dedicated review screen - there isn't one any more, see
	 * RBN_Approvals) where the "Account Status" column RBN_Member_Approval
	 * adds shows this signup as Pending activation.
	 */
	public static function admin_new_registration_email( WP_User $user, $users_url ) {
		$subject   = sprintf(
			/* translators: %s: new member's display name. */
			__( 'New member: %s', 'roomworks-business-networking' ),
			$user->display_name
		);
		$preheader = __( 'A new member has registered.', 'roomworks-business-networking' );

		$body = self::body(
			__( 'New member registration', 'roomworks-business-networking' ),
			array(
				sprintf(
					/* translators: 1: display name, 2: email address. */
					__( '%1$s (%2$s) just registered. They will be able to log in automatically once they confirm their email address - no action is needed from you.', 'roomworks-business-networking' ),
					$user->display_name,
					$user->user_email
				),
			),
			__( 'View members', 'roomworks-business-networking' ),
			$users_url
		);

		return self::send( RBN_Settings::notification_email(), $subject, $preheader, $body );
	}

	/**
	 * Sent when an admin explicitly rejects a pending account (e.g. an
	 * obvious spam/abuse signup) via the Approvals screen or Users row
	 * action - the one decision that still requires a human, since
	 * activation itself is now automatic.
	 */
	public static function member_rejected_email( WP_User $user ) {
		$subject   = __( 'Your account registration', 'roomworks-business-networking' );
		$preheader = __( "We're sorry, your registration was not approved.", 'roomworks-business-networking' );

		$body = self::body(
			/* translators: %s: first name. */
			sprintf( __( 'Hi %s,', 'roomworks-business-networking' ), $user->first_name ? $user->first_name : $user->display_name ),
			array(
				__( "We're sorry, your account registration was not approved. If you believe this is a mistake, please get in touch with us.", 'roomworks-business-networking' ),
			)
		);

		return self::send( $user->user_email, $subject, $preheader, $body );
	}

	/**
	 * Sent to every active member of a newly posted request's target
	 * community/communities (except the poster) - see
	 * RBN_Job_Notifications::send_new_request_notification().
	 */
	public static function new_request_email( WP_User $recipient, WP_Post $job ) {
		$poster      = get_userdata( $job->post_author );
		$poster_name = $poster ? ( $poster->first_name ? $poster->first_name : $poster->display_name ) : __( 'A member', 'roomworks-business-networking' );

		$subject   = sprintf(
			/* translators: %s: request title. */
			__( 'New request on the notice board: %s', 'roomworks-business-networking' ),
			$job->post_title
		);
		$preheader = __( 'A new request was just posted in one of your communities.', 'roomworks-business-networking' );

		$body = self::body(
			/* translators: %s: first name. */
			sprintf( __( 'Hi %s,', 'roomworks-business-networking' ), $recipient->first_name ? $recipient->first_name : $recipient->display_name ),
			array(
				sprintf(
					/* translators: 1: poster's name, 2: request title. */
					__( '%1$s just posted a new request on the notice board: "%2$s".', 'roomworks-business-networking' ),
					$poster_name,
					$job->post_title
				),
				__( "Take a look if you're able to help.", 'roomworks-business-networking' ),
			),
			__( 'View request', 'roomworks-business-networking' ),
			get_permalink( $job )
		);

		return self::send( $recipient->user_email, $subject, $preheader, $body );
	}

	/**
	 * Sent once to a request's own poster roughly 24 hours before it stops
	 * showing on the board (see RBN_Job_Query::not_closed_clause()) - only
	 * ever scheduled for a request that has a closing date set, see
	 * RBN_Job_Notifications::reschedule_expiring_reminder().
	 */
	public static function request_expiring_soon_email( WP_User $poster, WP_Post $job ) {
		$closing_date = get_post_meta( $job->ID, 'rbn_closing_date', true );

		$subject   = sprintf(
			/* translators: %s: request title. */
			__( 'Your request closes soon: %s', 'roomworks-business-networking' ),
			$job->post_title
		);
		$preheader = __( 'Your request is about to stop showing on the notice board.', 'roomworks-business-networking' );

		$paragraphs = array(
			sprintf(
				/* translators: %s: request title. */
				__( 'Your request "%s" is about to stop showing on the notice board.', 'roomworks-business-networking' ),
				$job->post_title
			),
		);

		if ( $closing_date ) {
			$paragraphs[] = sprintf(
				/* translators: %s: closing date. */
				__( "It's set to close on %s. If you'd like to keep it visible for longer, log in and update its closing date.", 'roomworks-business-networking' ),
				date_i18n( get_option( 'date_format' ), strtotime( $closing_date ) )
			);
		}

		$body = self::body(
			/* translators: %s: first name. */
			sprintf( __( 'Hi %s,', 'roomworks-business-networking' ), $poster->first_name ? $poster->first_name : $poster->display_name ),
			$paragraphs,
			__( 'View my request', 'roomworks-business-networking' ),
			get_permalink( $job )
		);

		return self::send( $poster->user_email, $subject, $preheader, $body );
	}
}
