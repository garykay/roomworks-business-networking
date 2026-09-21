/**
 * Progressively enhances the server-rendered business directory: intercepts
 * the filter form and pagination links to fetch from the REST endpoint
 * instead of doing a full page reload. Without this script (or if the
 * fetch fails) the form/links still work as plain GET requests, since
 * render.php performs the same query server-side from $_GET.
 *
 * @package RoomworksBusinessNetworking
 */

// Static, hardcoded markup only (mirrors RBN_Templates::icon()) - never
// touched by user input, so building it via innerHTML below is safe.
const PLACEHOLDER_LOGO_SVG =
	'<svg class="rbn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
	'<rect x="2.5" y="4.5" width="19" height="15" rx="1.5"></rect>' +
	'<circle cx="8" cy="9.7" r="1.6" fill="currentColor"></circle>' +
	'<path d="M3.6 17.5 8 11.8l2.6 2.7L15.2 9l5.2 8.5H3.6Z" fill="currentColor"></path>' +
	'</svg>';

const PIN_ICON_SVG =
	'<svg class="rbn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
	'<circle cx="12" cy="10" r="3"></circle>' +
	'<path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12Z"></path>' +
	'</svg>';

// Mirrors RBN_Templates::icon( 'heart' ).
const HEART_ICON_SVG =
	'<svg class="rbn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
	'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path>' +
	'</svg>';

document.addEventListener(
	'DOMContentLoaded',
	function () {
		const roots = document.querySelectorAll( '[data-rbn-directory]' );

		roots.forEach(
			function ( root ) {
				const form          = root.querySelector( '[data-rbn-filter-form]' );
				const resultsEl     = root.querySelector( '[data-rbn-results]' );
				const paginationEl  = root.querySelector( '[data-rbn-pagination]' );
				const filterActions = root.querySelector( '[data-rbn-filter-actions]' );
				const restUrl       = root.getAttribute( 'data-rest-url' );
				const restNonce     = root.getAttribute( 'data-rest-nonce' );
				const followRedirectUrl = root.getAttribute( 'data-follow-redirect-url' ) || '';
				const followNonce       = root.getAttribute( 'data-follow-nonce' ) || '';
				const unfollowNonce     = root.getAttribute( 'data-unfollow-nonce' ) || '';

				if ( ! form || ! resultsEl || ! paginationEl || ! restUrl ) {
						return;
				}

				const statusEl = root.querySelector( '[data-rbn-status]' );

				let i18n = {};
				try {
					i18n = JSON.parse( root.getAttribute( 'data-i18n' ) || '{}' );
				} catch {
					i18n = {};
				}

				function currentFilters() {
					const data = new FormData( form );
					return {
						search: data.get( 'rbn_search' ) || '',
						category: data.get( 'rbn_category' ) || '',
						service: data.get( 'rbn_service' ) || '',
						location: data.get( 'rbn_location' ) || '',
					};
				}

				function hasActiveFilters( filters ) {
					return Boolean( filters.search || filters.category || filters.service || filters.location );
				}

				/**
				 * The "Clear filters" link only exists in the server-rendered markup
				 * when the page first loaded with a filter already applied - since
				 * filtering here never reloads the page, this adds/removes that same
				 * link after every async fetch so it still shows up (or goes away)
				 * as filters are used.
				 */
				function updateClearLink() {
					if ( ! filterActions ) {
						return;
					}

					const active  = hasActiveFilters( currentFilters() );
					let clearLink = filterActions.querySelector( '[data-rbn-filter-clear]' );

					if ( active && ! clearLink ) {
						clearLink           = document.createElement( 'a' );
						clearLink.className = 'rbn-directory__clear rbn-link';
						clearLink.href      = '#';
						clearLink.setAttribute( 'data-rbn-filter-clear', '' );
						clearLink.textContent = i18n.clearFilters || 'Clear filters';
						filterActions.appendChild( clearLink );
					} else if ( ! active && clearLink ) {
						clearLink.remove();
					}
				}

				/**
				 * Mirrors RBN_Templates::business_follow_form() - a plain
				 * POST form, not a fetch()-based toggle, so submitting it
				 * does a normal full-page redirect back to
				 * followRedirectUrl (RBN_Business_Follow_Forms handles the
				 * actual follow/unfollow). Nothing here needs to update
				 * the button's own state on click - the redirect already
				 * re-renders the page with the new state.
				 */
				function createFollowForm( item ) {
					if ( item.is_own || ! followRedirectUrl ) {
						return null;
					}

					const isFollowing = Boolean( item.is_following );
					const action      = isFollowing ? 'rbn_unfollow_business' : 'rbn_follow_business';
					const nonce       = isFollowing ? unfollowNonce : followNonce;

					if ( ! nonce ) {
						return null;
					}

					const form     = document.createElement( 'form' );
					form.className = 'rbn-business-follow-form';
					form.method    = 'post';
					form.action    = followRedirectUrl;

					[
						[ 'rbn_form_action', action ],
						[ 'rbn_business_id', String( item.id ) ],
						[ 'rbn_redirect_to', followRedirectUrl ],
						[ 'rbn_business_follow_nonce', nonce ],
					].forEach(
						function ( pair ) {
							const input = document.createElement( 'input' );
							input.type  = 'hidden';
							input.name  = pair[ 0 ];
							input.value = pair[ 1 ];
							form.appendChild( input );
						}
					);

					const button     = document.createElement( 'button' );
					button.type      = 'submit';
					button.className = 'rbn-business-follow-btn' + ( isFollowing ? ' rbn-business-follow-btn--following' : '' );
					button.setAttribute( 'aria-pressed', isFollowing ? 'true' : 'false' );
					button.setAttribute(
						'aria-label',
						isFollowing ? ( i18n.unfollow || 'Unfollow this business' ) : ( i18n.follow || 'Follow this business' )
					);
					button.innerHTML = HEART_ICON_SVG;
					form.appendChild( button );

					return form;
				}

				function createCard( item ) {
					const card     = document.createElement( 'article' );
					card.className = 'rbn-business-card rbn-card';

					const followForm = createFollowForm( item );
					if ( followForm ) {
						card.appendChild( followForm );
					}

					if ( item.logo ) {
						const img     = document.createElement( 'img' );
						img.className = 'rbn-business-card__logo';
						img.src       = item.logo;
						img.alt       = '';
						img.loading   = 'lazy';
						card.appendChild( img );
					} else {
						const placeholder     = document.createElement( 'span' );
						placeholder.className = 'rbn-business-card__logo rbn-business-card__logo--placeholder';
						placeholder.setAttribute( 'aria-hidden', 'true' );
						placeholder.innerHTML = PLACEHOLDER_LOGO_SVG;
						card.appendChild( placeholder );
					}

					const body     = document.createElement( 'div' );
					body.className = 'rbn-business-card__body';
					card.appendChild( body );

					if ( item.category ) {
						const category       = document.createElement( 'p' );
						category.className   = 'rbn-business-card__category';
						category.textContent = item.category;
						body.appendChild( category );
					}

					const name           = document.createElement( 'h3' );
					name.className       = 'rbn-business-card__name';
					const nameLink       = document.createElement( 'a' );
					nameLink.href        = item.permalink;
					nameLink.textContent = item.name;
					name.appendChild( nameLink );
					body.appendChild( name );

					if ( item.excerpt ) {
						const excerpt       = document.createElement( 'p' );
						excerpt.className   = 'rbn-business-card__excerpt';
						excerpt.textContent = item.excerpt;
						body.appendChild( excerpt );
					}

					const location = [ item.town_city, item.county_region ]
						.filter( Boolean )
						.join( ', ' );

					if ( location ) {
						const locationEl     = document.createElement( 'p' );
						locationEl.className = 'rbn-business-card__location';
						// PIN_ICON_SVG is the only thing set via innerHTML; the
						// location text itself is still a plain, auto-escaped text node.
						locationEl.innerHTML = PIN_ICON_SVG;
						locationEl.appendChild( document.createTextNode( location ) );
						body.appendChild( locationEl );
					}

					if ( item.services && item.services.length ) {
						const services       = document.createElement( 'p' );
						services.className   = 'rbn-business-card__services';
						services.textContent = item.services.join( ', ' );
						body.appendChild( services );
					}

					const linkP      = document.createElement( 'p' );
					linkP.className  = 'rbn-business-card__link';
					const link       = document.createElement( 'a' );
					link.href        = item.permalink;
					link.textContent = i18n.viewProfile || 'View Profile';
					linkP.appendChild( link );
					body.appendChild( linkP );

					return card;
				}

				function renderResults( items ) {
					resultsEl.innerHTML = '';

					if ( ! items.length ) {
						const empty       = document.createElement( 'p' );
						empty.className   = 'rbn-directory__empty';
						empty.textContent = i18n.noResults || 'No businesses found.';
						resultsEl.appendChild( empty );
						return;
					}

					const grid     = document.createElement( 'div' );
					grid.className = 'rbn-directory__grid';
					items.forEach(
						function ( item ) {
							grid.appendChild( createCard( item ) );
						}
					);
					resultsEl.appendChild( grid );
				}

				/**
				 * Mirrors RBN_Templates::pagination_range() in PHP - keep the two in
				 * sync if this changes. Collapses a long run of pages into '...',
				 * always keeping the first/last SIBLING_COUNT pages plus a window of
				 * BOUNDARY_COUNT pages either side of the current page.
				 */
				function paginationRange( current, total ) {
					const siblingCount     = 1;
					const boundaryCount    = 2;
					const totalPageNumbers = boundaryCount * 2 + siblingCount * 2 + 3;

					const range = function ( start, end ) {
						return Array.from(
							{ length: end - start + 1 },
							function ( _, i ) {
								return start + i;
							}
						);
					};

					if ( total <= totalPageNumbers ) {
						return range( 1, total );
					}

					const leftSibling  = Math.max( current - siblingCount, boundaryCount + 1 );
					const rightSibling = Math.min( current + siblingCount, total - boundaryCount );

					const showLeftDots  = leftSibling > boundaryCount + 2;
					const showRightDots = rightSibling < total - boundaryCount - 1;

					const firstPages = range( 1, boundaryCount );
					const lastPages  = range( total - boundaryCount + 1, total );

					if ( ! showLeftDots && showRightDots ) {
						return range( 1, boundaryCount + siblingCount * 2 + 2 ).concat( [ '...' ], lastPages );
					}

					if ( showLeftDots && ! showRightDots ) {
						return firstPages.concat(
							[ '...' ],
							range( total - ( boundaryCount + siblingCount * 2 + 2 ) + 1, total )
						);
					}

					return firstPages.concat( [ '...' ], range( leftSibling, rightSibling ), [ '...' ], lastPages );
				}

				function renderPagination( data ) {
					paginationEl.innerHTML = '';

					if ( ! data.total_pages || data.total_pages <= 1 ) {
						return;
					}

					const nav     = document.createElement( 'nav' );
					nav.className = 'rbn-pagination';
					nav.setAttribute( 'aria-label', 'Directory pagination' );

					paginationRange( data.page, data.total_pages ).forEach(
						function ( page ) {
							if ( '...' === page ) {
										const dots     = document.createElement( 'span' );
										dots.className = 'rbn-pagination__ellipsis';
										dots.setAttribute( 'aria-hidden', 'true' );
										dots.textContent = '…';
										nav.appendChild( dots );
										return;
							}

							if ( page === data.page ) {
									const span     = document.createElement( 'span' );
									span.className = 'rbn-pagination__current';
									span.setAttribute( 'aria-current', 'page' );
									span.textContent = String( page );
									nav.appendChild( span );
							} else {
								const link = document.createElement( 'a' );
								link.href  = '#';
								link.setAttribute( 'data-rbn-page', String( page ) );
								link.textContent = String( page );
								nav.appendChild( link );
							}
						}
					);

					paginationEl.appendChild( nav );
				}

				function fetchResults( page ) {
					const filters = currentFilters();
					const url     = new URL( restUrl );

					if ( filters.search ) {
						url.searchParams.set( 'search', filters.search );
					}
					if ( filters.category ) {
						url.searchParams.set( 'category', filters.category );
					}
					if ( filters.service ) {
						url.searchParams.set( 'service', filters.service );
					}
					if ( filters.location ) {
						url.searchParams.set( 'location', filters.location );
					}
					if ( page > 1 ) {
						url.searchParams.set( 'page', String( page ) );
					}

					statusEl.textContent = i18n.loading || 'Loading businesses…';

					const headers = { Accept: 'application/json' };
					if ( restNonce ) {
						headers[ 'X-WP-Nonce' ] = restNonce;
					}

					fetch( url.toString(), { headers } )
						.then(
							function ( response ) {
								if ( ! response.ok ) {
									throw new Error( 'Request failed' );
								}
									return response.json();
							}
						)
						.then(
							function ( data ) {
								renderResults( data.items || [] );
								renderPagination( data );
								updateClearLink();
								statusEl.textContent = '';
							}
						)
						.catch(
							function () {
								statusEl.textContent =
								i18n.error ||
								'Something went wrong loading businesses. Please try again.';
							}
						);
				}

				form.addEventListener(
					'submit',
					function ( event ) {
						event.preventDefault();
						fetchResults( 1 );
					}
				);

				if ( filterActions ) {
					filterActions.addEventListener(
						'click',
						function ( event ) {
							const clearLink = event.target.closest( '[data-rbn-filter-clear]' );

							if ( ! clearLink ) {
								return;
							}

							event.preventDefault();

							[ 'rbn_search', 'rbn_category', 'rbn_service', 'rbn_location' ].forEach(
								function ( name ) {
									const field = form.elements.namedItem( name );

									if ( field ) {
											field.value = '';
									}
								}
							);

							fetchResults( 1 );
						}
					);
				}

				paginationEl.addEventListener(
					'click',
					function ( event ) {
						const target = event.target.closest( '[data-rbn-page]' );

						if ( ! target ) {
							return;
						}

						event.preventDefault();
						fetchResults(
							parseInt( target.getAttribute( 'data-rbn-page' ), 10 ) || 1
						);
					}
				);
			}
		);
	}
);
