<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="brw-card">
	<h2><?php echo esc_html__( 'Product Selection', 'bulk-review-wizard' ); ?></h2>
	<p><?php echo esc_html__( 'Search products by text and optional category.', 'bulk-review-wizard' ); ?></p>
	<div class="brw-grid">
		<div class="brw-field">
			<label><?php esc_html_e( 'Search Products', 'bulk-review-wizard' ); ?></label>
			<div class="brw-search-wrapper">
				<input type="text" id="brw-search" class="regular-text" placeholder="<?php esc_attr_e( 'Start typing to search products...', 'bulk-review-wizard' ); ?>" autocomplete="off" />
				<div id="brw-autocomplete" class="brw-autocomplete"></div>
			</div>
		</div>
		<div class="brw-field">
			<label><?php esc_html_e( 'Category (slug or ID)', 'bulk-review-wizard' ); ?></label>
			<input type="text" id="brw-cat" class="regular-text" placeholder="e.g. hoodies or 15" />
		</div>
		<div class="brw-field" style="grid-column: 1 / -1;">
			<label style="display: flex; align-items: center; gap: 8px;">
				<input type="checkbox" id="brw-exclude-reviewed" style="margin: 0;" /> 
				<?php esc_html_e( 'Exclude products that already have reviews', 'bulk-review-wizard' ); ?>
			</label>
		</div>
	</div>
	
	<div id="brw-selected-products" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Selected Products', 'bulk-review-wizard' ); ?></h3>
		<div id="brw-selected-list" class="brw-selected-products-list"></div>
	</div>

	<div class="brw-actions">
		<button class="button button-primary" id="brw-next-to-config" disabled><?php esc_html_e( 'Next', 'bulk-review-wizard' ); ?></button>
	</div>
</div>
