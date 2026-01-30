<?php
/**
 * API Debug Page (Temporary).
 *
 * @package CompetitorKnowledge\Admin
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

/**
 * Class ApiDebug
 *
 * Temporary debug page for testing API connections.
 *
 * @package CompetitorKnowledge\Admin
 */
class ApiDebug {

	/**
	 * Initialize the debug page.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 99 );
	}

	/**
	 * Add submenu page.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'CK API Debug', 'competitor-knowledge' ),
			__( '🔧 CK API Debug', 'competitor-knowledge' ),
			'manage_options',
			'ck-api-debug',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the debug page.
	 */
	public function render_page(): void {
		$options = get_option( Settings::OPTION_NAME, array() );
		$model   = $options['model_name'] ?? 'glm-4.7';
		$api_key = Settings::get_decrypted( 'zai_api_key' );
		$has_key = ! empty( $api_key );
		?>
		<div class="wrap">
			<h1>🔧 API Debug Tool</h1>
			<p>Use this page to test your API connections. Results will be shown below.</p>

			<div class="card" style="max-width: 800px; padding: 20px;">
				<h2>Z.AI API Test</h2>
				<p><strong>Model:</strong> <?php echo esc_html( $model ); ?></p>
				<p><strong>API Key:</strong> <?php echo $has_key ? '✅ Configured' : '❌ Not configured'; ?></p>
				<p><strong>Endpoint:</strong> <code>https://api.z.ai/api/paas/v4/chat/completions</code></p>

				<button type="button" id="ck-test-zai" class="button button-primary" <?php disabled( ! $has_key ); ?>>
					Test Z.AI Connection
				</button>

				<div id="ck-test-result" style="margin-top: 20px; display: none;">
					<h3>Result:</h3>
					<pre id="ck-test-output" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 500px;"></pre>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#ck-test-zai').on('click', function() {
				var $btn = $(this);
				var $result = $('#ck-test-result');
				var $output = $('#ck-test-output');

				$btn.prop('disabled', true).text('Testing...');
				$result.show();
				$output.text('Sending request to Z.AI...');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ck_test_zai_api',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ck_nonce' ) ); ?>'
					},
					success: function(response) {
						$output.text(JSON.stringify(response, null, 2));
					},
					error: function(xhr, status, error) {
						$output.text('AJAX Error: ' + error + '\n\nStatus: ' + status);
					},
					complete: function() {
						$btn.prop('disabled', false).text('Test Z.AI Connection');
					}
				});
			});
		});
		</script>
		<?php
	}
}
