<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap brw-wrap">
	<h1><?php echo esc_html__( 'Bulk Review Wizard', 'bulk-review-wizard' ); ?></h1>
	<div class="brw-shell">
		<aside class="brw-nav">
			<a href="#" data-target="generate" class="active">⚙️ <?php echo esc_html__( 'Generate', 'bulk-review-wizard' ); ?></a>
			<a href="#" data-target="jobs">🗂 <?php echo esc_html__( 'Jobs', 'bulk-review-wizard' ); ?></a>
			<a href="#" data-target="settings">🔧 <?php echo esc_html__( 'Settings', 'bulk-review-wizard' ); ?></a>
		</aside>
		<main class="brw-main">
			<section id="brw-section-generate">
				<ol class="brw-steps">
					<li class="active">1. <?php echo esc_html__( 'Select Products', 'bulk-review-wizard' ); ?></li>
					<li>2. <?php echo esc_html__( 'Configure Reviews', 'bulk-review-wizard' ); ?></li>
					<li>3. <?php echo esc_html__( 'Preview & Generate', 'bulk-review-wizard' ); ?></li>
				</ol>
				<div class="brw-step" id="brw-step-products">
					<?php include BRW_PLUGIN_DIR . 'templates/admin/product-selector.php'; ?>
				</div>
				<div class="brw-step" id="brw-step-config" style="display:none;">
					<?php include BRW_PLUGIN_DIR . 'templates/admin/review-configurator.php'; ?>
				</div>
				<div class="brw-step" id="brw-step-progress" style="display:none;">
					<?php include BRW_PLUGIN_DIR . 'templates/admin/progress-tracker.php'; ?>
				</div>
			</section>

			<section id="brw-section-jobs" style="display:none;">
				<div class="brw-card">
					<h2><?php echo esc_html__( 'Jobs History', 'bulk-review-wizard' ); ?></h2>
					<div id="brw-jobs-list"></div>
				</div>
			</section>

			<section id="brw-section-settings" style="display:none;">
				<div class="brw-card">
					<h2><?php echo esc_html__( 'AI Provider Settings', 'bulk-review-wizard' ); ?></h2>
					<p><?php echo esc_html__( 'You can use OpenAI, Google Gemini, Anthropic Claude, OpenRouter, or any OpenAI-compatible provider by setting a Base URL and API key. Leave empty to use templates only.', 'bulk-review-wizard' ); ?></p>
					<div class="brw-grid">
						<div class="brw-field">
							<label for="brw-provider"><?php esc_html_e( 'Provider', 'bulk-review-wizard' ); ?></label>
							<select id="brw-provider">
								<option value="openai">OpenAI / Compatible</option>
								<option value="google">Google Gemini</option>
								<option value="anthropic">Anthropic Claude</option>
								<option value="openrouter">OpenRouter</option>
								<option value="custom">Custom</option>
							</select>
						</div>
						<div class="brw-field"><label><?php esc_html_e( 'Model', 'bulk-review-wizard' ); ?></label><input type="text" id="brw-model" placeholder="gpt-4o-mini, gemini-1.5-pro, claude-3.5, llama-3.1-70b, deepseek-chat" /></div>
						<div class="brw-field"><label><?php esc_html_e( 'API Key', 'bulk-review-wizard' ); ?></label><input type="password" id="brw-api-key" placeholder="sk-..." /></div>
						<div class="brw-field"><label><?php esc_html_e( 'Base URL (for compatible/custom)', 'bulk-review-wizard' ); ?></label><input type="text" id="brw-base-url" placeholder="https://api.openai.com/v1 or https://api.deepseek.com/v1" /></div>
						<div class="brw-field"><label><?php esc_html_e( 'Temperature', 'bulk-review-wizard' ); ?></label><input type="number" id="brw-temperature" step="0.1" min="0" max="1" value="0.7" /></div>
						<div class="brw-field"><label><?php esc_html_e( 'Max Tokens', 'bulk-review-wizard' ); ?></label><input type="number" id="brw-max-tokens" min="1" value="150" /></div>
						<div class="brw-field"><label><?php esc_html_e( 'Rate limit (req/min)', 'bulk-review-wizard' ); ?></label><input type="number" id="brw-rate-limit" min="1" value="60" /></div>
					</div>
					<div class="brw-actions">
						<button class="button button-primary" id="brw-save-settings"><?php esc_html_e( 'Save', 'bulk-review-wizard' ); ?></button>
					</div>
				</div>
			</section>
		</main>
	</div>
</div>
