<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="brw-card">
	<h2><?php echo esc_html__( 'Preview & Generate', 'bulk-review-wizard' ); ?></h2>
	<div id="brw-preview-area" class="brw-preview-panels">
		<div class="brw-panel">
			<h3><?php echo esc_html__( 'Awaiting', 'bulk-review-wizard' ); ?></h3>
			<div id="brw-awaiting-log" class="brw-awaiting-log"></div>
		</div>
		<div class="brw-panel">
			<h3><?php echo esc_html__( 'Added', 'bulk-review-wizard' ); ?></h3>
			<div id="brw-added-log" class="brw-progress-log"></div>
		</div>
	</div>
	<div id="brw-progress">
		<div class="bar"><div class="fill" id="brw-progress-bar"></div></div>
		<div id="brw-progress-label" class="brw-progress-label"></div>
	</div>
	<div class="brw-actions">
		<button class="button" id="brw-back-to-config"><?php esc_html_e( 'Back', 'bulk-review-wizard' ); ?></button>
		<button class="button button-primary" id="brw-generate" disabled><?php esc_html_e( 'Generate', 'bulk-review-wizard' ); ?></button>
	</div>
</div>
