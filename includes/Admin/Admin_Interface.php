<?php
namespace WP_LLM_Connector\Admin;

class Admin_Interface {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'WP LLM Connector Settings', 'llm-connector-for-wp' ),
			__( 'LLM Connector', 'llm-connector-for-wp' ),
			'manage_options',
			'llm-connector-for-wp',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wp_llm_connector_settings_group',
			'wp_llm_connector_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled']        = isset( $input['enabled'] ) ? (bool) $input['enabled'] : false;
		$sanitized['read_only_mode'] = isset( $input['read_only_mode'] ) ? (bool) $input['read_only_mode'] : true;
		$sanitized['rate_limit']     = isset( $input['rate_limit'] ) ? absint( $input['rate_limit'] ) : 60;
		$sanitized['log_requests']   = isset( $input['log_requests'] ) ? (bool) $input['log_requests'] : true;

		// Clamp rate limit to valid range.
		$sanitized['rate_limit'] = max( 1, min( 1000, $sanitized['rate_limit'] ) );

		// Handle allowed endpoints.
		$sanitized['allowed_endpoints'] = isset( $input['allowed_endpoints'] ) && is_array( $input['allowed_endpoints'] )
			? array_map( 'sanitize_text_field', $input['allowed_endpoints'] )
			: array();

		// Preserve existing API keys (managed separately).
		$current_settings          = get_option( 'wp_llm_connector_settings', array() );
		$sanitized['api_keys']     = $current_settings['api_keys'] ?? array();

		return $sanitized;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_llm-connector-for-wp' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'llm-connector-for-wp-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_LLM_CONNECTOR_VERSION
		);

		wp_enqueue_script(
			'llm-connector-for-wp-admin',
			WP_LLM_CONNECTOR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_LLM_CONNECTOR_VERSION,
			true
		);

		wp_localize_script(
			'llm-connector-for-wp-admin',
			'wpLlmConnector',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_llm_connector_ajax' ),
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle API key actions.
		$this->handle_api_key_actions();

		$settings            = get_option( 'wp_llm_connector_settings', array() );
		$available_endpoints = array(
			'site_info'     => __( 'Site Information', 'llm-connector-for-wp' ),
			'plugin_list'   => __( 'Plugin List', 'llm-connector-for-wp' ),
			'theme_list'    => __( 'Theme List', 'llm-connector-for-wp' ),
			'user_count'    => __( 'User Count', 'llm-connector-for-wp' ),
			'post_stats'    => __( 'Post Statistics', 'llm-connector-for-wp' ),
			'system_status' => __( 'System Status', 'llm-connector-for-wp' ),
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'wp_llm_connector_messages' ); ?>

			<div class="llm-connector-for-wp-admin-container">
				<div class="llm-connector-for-wp-main-settings">
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_llm_connector_settings_group' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Connector', 'llm-connector-for-wp' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[enabled]" value="1"
											<?php checked( $settings['enabled'] ?? false, true ); ?>>
										<?php esc_html_e( 'Allow LLM connections to this site', 'llm-connector-for-wp' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Master switch to enable/disable all API access', 'llm-connector-for-wp' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Read-Only Mode', 'llm-connector-for-wp' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[read_only_mode]" value="1"
											<?php checked( $settings['read_only_mode'] ?? true, true ); ?>>
										<?php esc_html_e( 'Enforce read-only access (recommended)', 'llm-connector-for-wp' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, LLMs can only read data, not modify anything', 'llm-connector-for-wp' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Rate Limit', 'llm-connector-for-wp' ); ?></th>
								<td>
									<input type="number" name="wp_llm_connector_settings[rate_limit]"
										value="<?php echo esc_attr( $settings['rate_limit'] ?? 60 ); ?>"
										min="1" max="1000" class="small-text">
									<span><?php esc_html_e( 'requests per hour per API key', 'llm-connector-for-wp' ); ?></span>
									<p class="description">
										<?php esc_html_e( 'Limit requests to prevent abuse', 'llm-connector-for-wp' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Logging', 'llm-connector-for-wp' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_llm_connector_settings[log_requests]" value="1"
											<?php checked( $settings['log_requests'] ?? true, true ); ?>>
										<?php esc_html_e( 'Log all API requests', 'llm-connector-for-wp' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Keep an audit trail of all LLM access', 'llm-connector-for-wp' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Allowed Endpoints', 'llm-connector-for-wp' ); ?></th>
								<td>
									<?php foreach ( $available_endpoints as $endpoint => $label ) : ?>
										<label class="wp-llm-endpoint-checkbox">
											<input type="checkbox"
												name="wp_llm_connector_settings[allowed_endpoints][]"
												value="<?php echo esc_attr( $endpoint ); ?>"
												<?php checked( in_array( $endpoint, $settings['allowed_endpoints'] ?? array(), true ) ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description">
										<?php esc_html_e( 'Select which data endpoints LLMs can access', 'llm-connector-for-wp' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Settings', 'llm-connector-for-wp' ) ); ?>
					</form>
				</div>

				<div class="llm-connector-for-wp-api-keys">
					<h2><?php esc_html_e( 'API Keys', 'llm-connector-for-wp' ); ?></h2>

					<?php $this->render_api_keys_section( $settings ); ?>
				</div>

				<div class="llm-connector-for-wp-info">
					<h2><?php esc_html_e( 'Connection Information', 'llm-connector-for-wp' ); ?></h2>
					<div class="info-box">
						<h3><?php esc_html_e( 'API Endpoint', 'llm-connector-for-wp' ); ?></h3>
						<code><?php echo esc_html( rest_url( 'llm-connector-for-wp/v1/' ) ); ?></code>

						<h3><?php esc_html_e( 'Usage Example (cURL)', 'llm-connector-for-wp' ); ?></h3>
						<pre class="wp-llm-code-block">curl -H "X-WP-LLM-API-Key: YOUR_API_KEY" \
     <?php echo esc_html( rest_url( 'llm-connector-for-wp/v1/site-info' ) ); ?></pre>

						<h3><?php esc_html_e( 'Available Endpoints', 'llm-connector-for-wp' ); ?></h3>
						<ul>
							<li><code>/health</code> - <?php esc_html_e( 'Health check (no auth required)', 'llm-connector-for-wp' ); ?></li>
							<li><code>/site-info</code> - <?php esc_html_e( 'Basic site information', 'llm-connector-for-wp' ); ?></li>
							<li><code>/plugins</code> - <?php esc_html_e( 'List all plugins', 'llm-connector-for-wp' ); ?></li>
							<li><code>/themes</code> - <?php esc_html_e( 'List all themes', 'llm-connector-for-wp' ); ?></li>
							<li><code>/system-status</code> - <?php esc_html_e( 'System health and configuration', 'llm-connector-for-wp' ); ?></li>
							<li><code>/user-count</code> - <?php esc_html_e( 'User statistics', 'llm-connector-for-wp' ); ?></li>
							<li><code>/post-stats</code> - <?php esc_html_e( 'Content statistics', 'llm-connector-for-wp' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_api_keys_section( $settings ) {
		$api_keys = $settings['api_keys'] ?? array();
		?>

		<form method="post" action="">
			<?php wp_nonce_field( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ); ?>

			<div class="wp-llm-generate-key">
				<h3><?php esc_html_e( 'Generate New API Key', 'llm-connector-for-wp' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Generate an API key that LLM services (like Claude) will use to authenticate with your WordPress site.', 'llm-connector-for-wp' ); ?>
				</p>
				<input type="text" name="key_name"
					placeholder="<?php echo esc_attr__( 'Key name (e.g., Claude Production)', 'llm-connector-for-wp' ); ?>"
					class="regular-text" required>
				<button type="submit" name="generate_key" class="button button-primary">
					<?php esc_html_e( 'Generate API Key', 'llm-connector-for-wp' ); ?>
				</button>
			</div>

			<h3 class="wp-llm-existing-keys-title"><?php esc_html_e( 'Existing API Keys', 'llm-connector-for-wp' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key Name', 'llm-connector-for-wp' ); ?></th>
						<th><?php esc_html_e( 'Key Prefix', 'llm-connector-for-wp' ); ?></th>
						<th><?php esc_html_e( 'Created', 'llm-connector-for-wp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'llm-connector-for-wp' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'llm-connector-for-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $api_keys ) ) : ?>
						<tr>
							<td colspan="5" class="wp-llm-empty-keys">
								<?php esc_html_e( 'No API keys generated yet. Create one using the form above.', 'llm-connector-for-wp' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $api_keys as $key_id => $key_data ) : ?>
							<tr>
								<td><?php echo esc_html( $key_data['name'] ?? __( 'Unnamed', 'llm-connector-for-wp' ) ); ?></td>
								<td>
									<code class="api-key-display">
										<?php echo esc_html( $key_data['key_prefix'] ?? '****' ); ?>...
									</code>
								</td>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $key_data['created'] ?? time() ) ); ?></td>
								<td>
									<?php if ( $key_data['active'] ?? true ) : ?>
										<span class="status-active"><?php esc_html_e( 'Active', 'llm-connector-for-wp' ); ?></span>
									<?php else : ?>
										<span class="status-inactive"><?php esc_html_e( 'Inactive', 'llm-connector-for-wp' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="submit" name="revoke_key" value="<?php echo esc_attr( $key_id ); ?>"
										class="button button-small button-link-delete"
										onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to revoke this API key?', 'llm-connector-for-wp' ) ); ?>');">
										<?php esc_html_e( 'Revoke', 'llm-connector-for-wp' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</form>

		<?php
	}

	private function handle_api_key_actions() {
		// Generate new key.
		if ( isset( $_POST['generate_key'] ) && check_admin_referer( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ) ) {
			$key_name = sanitize_text_field( wp_unslash( $_POST['key_name'] ?? 'Unnamed' ) );
			$api_key  = \WP_LLM_Connector\Security\Security_Manager::generate_api_key();

			$settings             = get_option( 'wp_llm_connector_settings', array() );
			$settings['api_keys'] = $settings['api_keys'] ?? array();

			$key_id                          = wp_generate_uuid4();
			$settings['api_keys'][ $key_id ] = array(
				'name'       => $key_name,
				'key_hash'   => hash( 'sha256', $api_key ),
				'key_prefix' => substr( $api_key, 0, 12 ),
				'created'    => time(),
				'active'     => true,
			);

			$updated = update_option( 'wp_llm_connector_settings', $settings );

			if ( $updated ) {
				// Store the generated key in a transient so we can show it after redirect.
				set_transient( 'wp_llm_connector_new_key', $api_key, 60 );
				
				// Redirect to avoid form resubmission and ensure fresh data load.
				$redirect_url = add_query_arg(
					array(
						'page'          => 'llm-connector-for-wp',
						'key_generated' => '1',
						'_wpnonce'      => wp_create_nonce( 'wp_llm_connector_key_generated' ),
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			} else {
				add_settings_error(
					'wp_llm_connector_messages',
					'key_generation_failed',
					__( 'Failed to save API key. Please try again.', 'llm-connector-for-wp' ),
					'error'
				);
			}
		}

		// Show the generated key after redirect.
		if ( isset( $_GET['key_generated'] ) && $_GET['key_generated'] === '1' ) {
			// Verify nonce to prevent URL manipulation.
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_key_generated' ) ) {
				return;
			}

			$api_key = get_transient( 'wp_llm_connector_new_key' );
			if ( $api_key ) {
				delete_transient( 'wp_llm_connector_new_key' );
				add_settings_error(
					'wp_llm_connector_messages',
					'key_generated',
					sprintf(
						/* translators: %s: the generated API key */
						__( 'API Key generated successfully: %s — Copy this key now and provide it to your LLM client configuration. It cannot be shown again.', 'llm-connector-for-wp' ),
						'<code>' . esc_html( $api_key ) . '</code>'
					),
					'success'
				);
			}
		}

		// Revoke key.
		if ( isset( $_POST['revoke_key'] ) && check_admin_referer( 'wp_llm_connector_generate_key', 'wp_llm_connector_key_nonce' ) ) {
			$key_id = sanitize_text_field( wp_unslash( $_POST['revoke_key'] ) );

			$settings = get_option( 'wp_llm_connector_settings', array() );
			if ( isset( $settings['api_keys'][ $key_id ] ) ) {
				unset( $settings['api_keys'][ $key_id ] );
				$updated = update_option( 'wp_llm_connector_settings', $settings );

				if ( $updated ) {
					// Redirect to avoid form resubmission.
					$redirect_url = add_query_arg(
						array(
							'page'        => 'llm-connector-for-wp',
							'key_revoked' => '1',
							'_wpnonce'    => wp_create_nonce( 'wp_llm_connector_key_revoked' ),
						),
						admin_url( 'options-general.php' )
					);
					wp_safe_redirect( $redirect_url );
					exit;
				} else {
					add_settings_error(
						'wp_llm_connector_messages',
						'key_revocation_failed',
						__( 'Failed to revoke API key. Please try again.', 'llm-connector-for-wp' ),
						'error'
					);
				}
			}
		}

		// Show revocation success message after redirect.
		if ( isset( $_GET['key_revoked'] ) && $_GET['key_revoked'] === '1' ) {
			// Verify nonce to prevent URL manipulation.
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp_llm_connector_key_revoked' ) ) {
				return;
			}

			add_settings_error(
				'wp_llm_connector_messages',
				'key_revoked',
				__( 'API Key revoked successfully.', 'llm-connector-for-wp' ),
				'success'
			);
		}
	}
}
