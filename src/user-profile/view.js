/**
 * Progressively enhances the Business Type and Services fields on the
 * business form. Without this script (or if a request fails), both fields
 * still degrade to plain HTML that submits normally - Business Type keeps
 * its <select>, and Services falls back to the <noscript> checkbox list -
 * they just can't add a brand new term, since that inherently needs a
 * request to the server.
 *
 * Duplicate terms are avoided in two layers: each field tracks the terms
 * it's already added client-side, and the create endpoints themselves
 * (RBN_REST_Services / RBN_REST_Business_Categories find_or_create_*)
 * return the existing term instead of creating a new row when the name
 * already matches one.
 *
 * @package RoomworksBusinessNetworking
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		document.querySelectorAll( '[data-rbn-tag-field]' ).forEach( initTagField );
		document.querySelectorAll( '[data-rbn-category-field]' ).forEach( initCategoryField );
		stripNoticeFromUrl();
	}
);

/**
 * The success/error banner (e.g. "Your business has been submitted for
 * review.") is rendered from a one-time ?rbn_notice= query arg left in the
 * URL by the redirect-after-POST that produced it (see
 * RBN_*_Forms::redirect_with_notice()) - there's no server-side flash-
 * message store this plugin can clear it from. Left alone, that means the
 * same stale notice reappears on every later reload of that URL (e.g. an
 * approval notice still showing "submitted for review" after an admin has
 * since approved the business). Stripping the query arg from the address
 * bar right after it renders means the banner still shows once, but a
 * refresh lands on the plain URL and shows the page's actual current state.
 */
function stripNoticeFromUrl() {
	if ( ! window.history || ! window.history.replaceState ) {
		return;
	}

	const url = new URL( window.location.href );

	if ( ! url.searchParams.has( 'rbn_notice' ) ) {
		return;
	}

	url.searchParams.delete( 'rbn_notice' );
	window.history.replaceState( {}, '', url.toString() );
}

/**
 * Services: search-as-you-type, multi-select chips, create-on-the-fly.
 */
function initTagField( field ) {
	const chipsEl       = field.querySelector( '[data-rbn-tag-chips]' );
	const searchEl      = field.querySelector( '[data-rbn-tag-search]' );
	const addButton     = field.querySelector( '[data-rbn-tag-add]' );
	const suggestionsEl = field.querySelector( '[data-rbn-tag-suggestions]' );
	const statusEl      = field.querySelector( '[data-rbn-tag-status]' );
	const restUrl       = field.getAttribute( 'data-rest-url' );
	const nonce         = field.getAttribute( 'data-rest-nonce' );
	const inputName     = field.getAttribute( 'data-input-name' );

	if ( ! chipsEl || ! searchEl || ! addButton || ! suggestionsEl || ! restUrl || ! inputName ) {
		return;
	}

	let i18n = {};
	try {
		i18n = JSON.parse( field.getAttribute( 'data-i18n' ) || '{}' );
	} catch {
		i18n = {};
	}

	const selectedIds = new Set(
		Array.from( chipsEl.querySelectorAll( '[data-term-id]' ) ).map(
			function ( chip ) {
				return chip.getAttribute( 'data-term-id' );
			}
		)
	);

	let searchTimer       = null;
	let latestSuggestions = [];

	function setStatus( message ) {
		statusEl.textContent = message || '';
	}

	function hideSuggestions() {
		suggestionsEl.innerHTML = '';
		suggestionsEl.hidden    = true;
		latestSuggestions       = [];
	}

	function addChip( term ) {
		const id = String( term.id );

		if ( selectedIds.has( id ) ) {
			return;
		}

		selectedIds.add( id );

		const chip     = document.createElement( 'span' );
		chip.className = 'rbn-chip rbn-chip--tag';
		chip.setAttribute( 'data-term-id', id );

		const input = document.createElement( 'input' );
		input.type  = 'hidden';
		input.name  = inputName;
		input.value = id;
		chip.appendChild( input );

		const label       = document.createElement( 'span' );
		label.className   = 'rbn-chip__label';
		label.textContent = term.name;
		chip.appendChild( label );

		const remove     = document.createElement( 'button' );
		remove.type      = 'button';
		remove.className = 'rbn-chip__remove';
		remove.setAttribute( 'data-rbn-tag-remove', '' );
		remove.setAttribute(
			'aria-label',
			( i18n.removeLabel || 'Remove %s' ).replace( '%s', term.name )
		);
		remove.textContent = '×';
		chip.appendChild( remove );

		chipsEl.appendChild( chip );
	}

	chipsEl.addEventListener(
		'click',
		function ( event ) {
			const button = event.target.closest( '[data-rbn-tag-remove]' );

			if ( ! button ) {
				return;
			}

			const chip = button.closest( '[data-term-id]' );

			if ( ! chip ) {
				return;
			}

			selectedIds.delete( chip.getAttribute( 'data-term-id' ) );
			chip.remove();
		}
	);

	function renderSuggestions( terms ) {
		const available = terms.filter(
			function ( term ) {
				return ! selectedIds.has( String( term.id ) );
			}
		);

		latestSuggestions       = available;
		suggestionsEl.innerHTML = '';

		if ( ! available.length ) {
			hideSuggestions();
			return;
		}

		available.forEach(
			function ( term ) {
				const li     = document.createElement( 'li' );
				const button = document.createElement( 'button' );
				button.type  = 'button';
				button.setAttribute( 'role', 'option' );
				button.textContent = term.name;
				button.addEventListener(
					'click',
					function () {
						addChip( term );
						searchEl.value = '';
						hideSuggestions();
						searchEl.focus();
					}
				);
				li.appendChild( button );
				suggestionsEl.appendChild( li );
			}
		);

		suggestionsEl.hidden = false;
	}

	function searchTerms( term ) {
		const url = new URL( restUrl );
		url.searchParams.set( 'search', term );

		fetch( url.toString(), { headers: { Accept: 'application/json' } } )
			.then(
				function ( response ) {
					if ( ! response.ok ) {
							throw new Error( 'Request failed' );
					}
					return response.json();
				}
			)
			.then( renderSuggestions )
			.catch(
				function () {
					hideSuggestions();
				}
			);
	}

	function findExactMatch( name ) {
		const lower = name.toLowerCase();
		return latestSuggestions.find(
			function ( term ) {
				return term.name.toLowerCase() === lower;
			}
		);
	}

	function addTypedTerm() {
		const name = searchEl.value.trim();

		if ( ! name ) {
			return;
		}

		const exact = findExactMatch( name );

		if ( exact ) {
			addChip( exact );
			searchEl.value = '';
			hideSuggestions();
			return;
		}

		if ( ! nonce ) {
			return;
		}

		addButton.disabled = true;
		setStatus( i18n.adding || 'Adding…' );

		fetch(
			restUrl,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { name } ),
			}
		)
			.then(
				function ( response ) {
					return response.json().then(
						function ( data ) {
							if ( ! response.ok ) {
									throw new Error(
										data && data.message ? data.message : 'Request failed'
									);
							}
							return data;
						}
					);
				}
			)
			.then(
				function ( term ) {
					addChip( term );
					searchEl.value = '';
					hideSuggestions();
					setStatus( '' );
				}
			)
			.catch(
				function ( error ) {
					setStatus( error.message || i18n.error || 'Something went wrong adding that.' );
				}
			)
			.finally(
				function () {
					addButton.disabled = false;
				}
			);
	}

	searchEl.addEventListener(
		'input',
		function () {
			const term = searchEl.value.trim();

			clearTimeout( searchTimer );
			setStatus( '' );

			if ( term.length < 2 ) {
				hideSuggestions();
				return;
			}

			searchTimer = setTimeout(
				function () {
					searchTerms( term );
				},
				250
			);
		}
	);

	searchEl.addEventListener(
		'keydown',
		function ( event ) {
			if ( 'Escape' === event.key ) {
				hideSuggestions();
			} else if ( 'Enter' === event.key ) {
				event.preventDefault();
				addTypedTerm();
			}
		}
	);

	addButton.addEventListener(
		'click',
		function ( event ) {
			event.preventDefault();
			addTypedTerm();
		}
	);

	document.addEventListener(
		'click',
		function ( event ) {
			if ( ! field.contains( event.target ) ) {
				hideSuggestions();
			}
		}
	);

	// Chips are hidden inputs added/removed at runtime, so there's no
	// single persistent field HTML's native `required` can attach to -
	// this is the "at least one" check for that case.
	const form = field.closest( 'form' );

	if ( form && 'true' === field.getAttribute( 'data-required' ) ) {
		form.addEventListener(
			'submit',
			function ( event ) {
				if ( 0 === chipsEl.children.length ) {
					event.preventDefault();
					setStatus( i18n.required || 'Please add at least one.' );
					searchEl.focus();
				}
			}
		);
	}
}

/**
 * Business Type: plain dropdown of existing categories, plus an "Other"
 * option that reveals a text input for adding one that isn't listed. Kept
 * deliberately more deliberate than the Services field - members were
 * typing anything (including services) into the old free-text version.
 */
function initCategoryField( field ) {
	const select       = field.querySelector( '[data-rbn-category-select]' );
	const otherOption  = field.querySelector( '[data-rbn-other-option]' );
	const addWrap      = field.querySelector( '[data-rbn-category-add]' );
	const input        = field.querySelector( '[data-rbn-category-input]' );
	const submitButton = field.querySelector( '[data-rbn-category-submit]' );
	const statusEl     = field.querySelector( '[data-rbn-category-status]' );
	const restUrl      = field.getAttribute( 'data-rest-url' );
	const nonce        = field.getAttribute( 'data-rest-nonce' );

	if ( ! select || ! otherOption || ! addWrap || ! input || ! submitButton || ! restUrl ) {
		return;
	}

	let i18n = {};
	try {
		i18n = JSON.parse( field.getAttribute( 'data-i18n' ) || '{}' );
	} catch {
		i18n = {};
	}

	function setStatus( message ) {
		statusEl.textContent = message || '';
	}

	function showAdd() {
		addWrap.hidden = false;
		input.value    = '';
		input.focus();
	}

	function hideAdd() {
		addWrap.hidden = true;
		input.value    = '';
		setStatus( '' );
	}

	select.addEventListener(
		'change',
		function () {
			if ( '__other__' === select.value ) {
				showAdd();
			} else {
				hideAdd();
			}
		}
	);

	function findOptionByName( name ) {
		const lower = name.toLowerCase();
		return Array.from( select.options ).find(
			function ( option ) {
				return option !== otherOption && option.text.trim().toLowerCase() === lower;
			}
		);
	}

	function selectTerm( term ) {
		let option = select.querySelector( 'option[value="' + term.id + '"]' );

		if ( ! option ) {
			option       = document.createElement( 'option' );
			option.value = String( term.id );
			option.text  = term.name;
			select.insertBefore( option, otherOption );
		}

		select.value = String( term.id );
		hideAdd();
	}

	function addTypedCategory() {
		const name = input.value.trim();

		if ( ! name ) {
			return;
		}

		const existing = findOptionByName( name );

		if ( existing ) {
			select.value = existing.value;
			hideAdd();
			return;
		}

		if ( ! nonce ) {
			return;
		}

		submitButton.disabled = true;
		setStatus( i18n.adding || 'Adding…' );

		fetch(
			restUrl,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { name } ),
			}
		)
			.then(
				function ( response ) {
					return response.json().then(
						function ( data ) {
							if ( ! response.ok ) {
									throw new Error(
										data && data.message ? data.message : 'Request failed'
									);
							}
							return data;
						}
					);
				}
			)
			.then( selectTerm )
			.catch(
				function ( error ) {
					setStatus( error.message || i18n.error || 'Something went wrong adding that.' );
				}
			)
			.finally(
				function () {
					submitButton.disabled = false;
				}
			);
	}

	input.addEventListener(
		'keydown',
		function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				addTypedCategory();
			} else if ( 'Escape' === event.key ) {
				select.value = '';
				hideAdd();
			}
		}
	);

	submitButton.addEventListener(
		'click',
		function ( event ) {
			event.preventDefault();
			addTypedCategory();
		}
	);
}
