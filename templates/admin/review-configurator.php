<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="brw-card">
	<h2><?php echo esc_html__( 'Review Configuration', 'bulk-review-wizard' ); ?></h2>
	<div class="brw-grid">
		<div class="brw-field">
			<label><?php esc_html_e( 'Reviews per product', 'bulk-review-wizard' ); ?></label>
			<input type="number" id="brw-count" min="1" value="5" />
		</div>
		<div class="brw-field">
			<label><?php esc_html_e( 'Rating distribution (%)', 'bulk-review-wizard' ); ?></label>
			<div class="brw-rating-dist">
				<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
					<label><?php echo esc_html( $i ); ?>★ <input type="number" class="brw-rate" data-rate="<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i === 5 ? 40 : ( $i === 4 ? 35 : ( $i === 3 ? 15 : ( $i === 2 ? 7 : 3 ) ) ) ); ?>" /></label>
				<?php endfor; ?>
			</div>
		</div>
		<div class="brw-field">
			<label><?php esc_html_e( 'Date range', 'bulk-review-wizard' ); ?></label>
			<input type="date" id="brw-date-start" />
			<input type="date" id="brw-date-end" />
		</div>
		<div class="brw-field">
			<label for="brw-language"><?php esc_html_e( 'Language', 'bulk-review-wizard' ); ?></label>
			<select id="brw-language">
				<?php foreach ( BRW_Localization_Handler::get_languages() as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="brw-field">
			<label for="brw-verified"><?php esc_html_e( 'Verified purchases (%)', 'bulk-review-wizard' ); ?></label>
			<input type="number" id="brw-verified" min="0" max="100" value="80" />
		</div>
		<div class="brw-field" style="grid-column: 1 / -1;">
			<label for="brw-authors"><?php esc_html_e( 'Manual Authors (one per line, optional)', 'bulk-review-wizard' ); ?></label>
			<textarea id="brw-authors" rows="6" class="large-text" placeholder="Aadarsh
Aiden
Alan
…"></textarea>
		</div>
		<div class="brw-field" style="grid-column: 1 / -1;">
			<label for="brw-reviews"><?php esc_html_e( 'Manual Review Phrases (one per line, optional)', 'bulk-review-wizard' ); ?></label>
			<textarea id="brw-reviews" rows="8" class="large-text" placeholder="Good quality.
Good service.
The product is firmly packed.
Very fast delivery.
Very well worth the money."></textarea>
		</div>
	</div>
	<div class="brw-actions">
		<button class="button" id="brw-back-to-products"><?php esc_html_e( 'Back', 'bulk-review-wizard' ); ?></button>
		<button class="button button-primary" id="brw-preview"><?php esc_html_e( 'Preview', 'bulk-review-wizard' ); ?></button>
	</div>
</div>
