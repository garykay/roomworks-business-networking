<?php
/**
 * Renders the live member/business/city counts from RBN_Stats. Labels and
 * suffixes are editable per-stat via the block's Inspector controls; the
 * numbers themselves always come straight from the database.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = array(
	array(
		'value'  => RBN_Stats::member_count(),
		'suffix' => isset( $attributes['membersSuffix'] ) ? $attributes['membersSuffix'] : '',
		'label'  => isset( $attributes['membersLabel'] ) ? $attributes['membersLabel'] : __( 'Members', 'roomworks-stats-counter' ),
	),
	array(
		'value'  => RBN_Stats::business_count(),
		'suffix' => isset( $attributes['businessesSuffix'] ) ? $attributes['businessesSuffix'] : '+',
		'label'  => isset( $attributes['businessesLabel'] ) ? $attributes['businessesLabel'] : __( 'Businesses Listed', 'roomworks-stats-counter' ),
	),
	array(
		'value'  => RBN_Stats::city_count(),
		'suffix' => isset( $attributes['citiesSuffix'] ) ? $attributes['citiesSuffix'] : '+',
		'label'  => isset( $attributes['citiesLabel'] ) ? $attributes['citiesLabel'] : __( 'UK Cities', 'roomworks-stats-counter' ),
	),
);
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<div class="rbn-stats-counter">
		<?php foreach ( $stats as $index => $stat ) : ?>
			<?php if ( $index > 0 ) : ?>
				<div class="rbn-stats-counter__divider" aria-hidden="true"></div>
			<?php endif; ?>
			<div class="rbn-stats-counter__item">
				<div class="rbn-stats-counter__number">
					<?php echo esc_html( number_format_i18n( $stat['value'] ) . $stat['suffix'] ); ?>
				</div>
				<div class="rbn-stats-counter__label">
					<?php echo esc_html( $stat['label'] ); ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
