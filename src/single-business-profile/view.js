/**
 * The follow/unfollow notice (e.g. "You're now following this business.")
 * is rendered from a one-time ?rbn_notice= query arg left in the URL by
 * the redirect-after-POST that produced it. Left on screen indefinitely
 * it would sit there until the visitor navigates away, so it's faded out
 * and removed a few seconds after the page loads.
 *
 * @package RoomworksBusinessNetworking
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		const notice = document.querySelector( '.rbn-notice' );

		if ( ! notice ) {
			return;
		}

		setTimeout(
			function () {
				notice.classList.add( 'rbn-notice--dismissed' );
				setTimeout( function () {
					notice.remove();
				}, 400 );
			},
			3000
		);
	}
);
