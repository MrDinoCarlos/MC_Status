<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCSMD_Admin {

	/**
	 * Option name used to store plugin settings.
	 *
	 * @var string
	 */
	private $option_name = 'mcsmd_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	private $option_group = 'mcsmd_settings_group';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'show_api_delay_notice' ) );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'MC Status by MrDino', 'mcsmd' ),
			__( 'MC Status by MrDino', 'mcsmd' ),
			'manage_options',
			'mcsmd-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Get settings with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'server_address'    => '',
			'server_port'       => 25565,
			'cache_seconds'     => 30,
			'custom_name'       => '',
			'show_ip'           => 1,
			'show_motd'         => 1,
			'show_banner'       => 1,
			'show_player_list'  => 0,
			'player_list_limit' => 10,
			'player_columns'    => 3,
			'global_dark_mode'  => 0,
			'last_version'      => '',
		);

		$options = get_option( $this->option_name, array() );

		return wp_parse_args( $options, $defaults );
	}

	public function register_settings() {
		register_setting(
			$this->option_group,
			$this->option_name,
			array( $this, 'sanitize_settings' )
		);

		// Main section.
		add_settings_section(
			'mcsmd_main_section',
			__( 'Basic server settings', 'mcsmd' ),
			'__return_false',
			'mcsmd-settings'
		);

		add_settings_field(
			'server_address',
			__( 'Server address', 'mcsmd' ),
			array( $this, 'field_server_address' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		add_settings_field(
			'server_port',
			__( 'Server port', 'mcsmd' ),
			array( $this, 'field_server_port' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		add_settings_field(
			'cache_seconds',
			__( 'Status cache (seconds)', 'mcsmd' ),
			array( $this, 'field_cache_seconds' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		// Display section.
		add_settings_section(
			'mcsmd_display_section',
			__( 'Display options', 'mcsmd' ),
			'__return_false',
			'mcsmd-settings'
		);

		add_settings_field(
			'custom_name',
			__( 'Custom server name', 'mcsmd' ),
			array( $this, 'field_custom_name' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_ip',
			__( 'Show IP / address', 'mcsmd' ),
			array( $this, 'field_show_ip' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_motd',
			__( 'Show MOTD', 'mcsmd' ),
			array( $this, 'field_show_motd' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_banner',
			__( 'Show server banner', 'mcsmd' ),
			array( $this, 'field_show_banner' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_player_list',
			__( 'Show player list (if available)', 'mcsmd' ),
			array( $this, 'field_show_player_list' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'player_list_limit',
			__( 'Max players to show in list', 'mcsmd' ),
			array( $this, 'field_player_list_limit' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'player_columns',
			__( 'Player cards per row', 'mcsmd' ),
			array( $this, 'field_player_columns' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'global_dark_mode',
			__( 'Enable dark mode (global)', 'mcsmd' ),
			array( $this, 'field_global_dark_mode' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);
	}

	public function sanitize_settings( $input ) {
		$output                       = array();
		$output['server_address']     = isset( $input['server_address'] ) ? sanitize_text_field( $input['server_address'] ) : '';
		$output['server_port']        = isset( $input['server_port'] ) ? intval( $input['server_port'] ) : 25565;
		$output['cache_seconds']      = isset( $input['cache_seconds'] ) ? intval( $input['cache_seconds'] ) : 30;
		$output['custom_name']        = isset( $input['custom_name'] ) ? sanitize_text_field( $input['custom_name'] ) : '';
		$output['show_ip']            = ! empty( $input['show_ip'] ) ? 1 : 0;
		$output['show_motd']          = ! empty( $input['show_motd'] ) ? 1 : 0;
		$output['show_banner']        = ! empty( $input['show_banner'] ) ? 1 : 0;
		$output['show_player_list']   = ! empty( $input['show_player_list'] ) ? 1 : 0;
		$output['player_list_limit']  = isset( $input['player_list_limit'] ) ? intval( $input['player_list_limit'] ) : 10;
		$output['player_columns']     = isset( $input['player_columns'] ) ? intval( $input['player_columns'] ) : 3;
		$output['global_dark_mode']   = ! empty( $input['global_dark_mode'] ) ? 1 : 0;

		if ( $output['server_port'] <= 0 || $output['server_port'] > 65535 ) {
			$output['server_port'] = 25565;
		}

		if ( $output['cache_seconds'] < 0 ) {
			$output['cache_seconds'] = 0;
		}

		if ( $output['player_list_limit'] < 1 ) {
			$output['player_list_limit'] = 1;
		}

		if ( $output['player_columns'] < 1 ) {
			$output['player_columns'] = 1;
		} elseif ( $output['player_columns'] > 4 ) {
			$output['player_columns'] = 4;
		}

		return $output;
	}

	/* === Fields === */

	public function field_server_address() {
		$settings       = $this->get_settings();
		$server_address = $settings['server_address'];
		?>
		<input type="text"
			   name="<?php echo esc_attr( $this->option_name ); ?>[server_address]"
			   value="<?php echo esc_attr( $server_address ); ?>"
			   class="regular-text"
			   placeholder="play.mrdino.es" />
		<p class="description">
			<?php esc_html_e( 'Minecraft server IP or hostname. Do not include the port here.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_server_port() {
		$settings    = $this->get_settings();
		$server_port = intval( $settings['server_port'] );
		?>
		<input type="number"
			   name="<?php echo esc_attr( $this->option_name ); ?>[server_port]"
			   value="<?php echo esc_attr( $server_port ); ?>"
			   class="small-text"
			   min="1"
			   max="65535" />
		<p class="description">
			<?php esc_html_e( 'Default Minecraft port is 25565.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_cache_seconds() {
		$settings      = $this->get_settings();
		$cache_seconds = intval( $settings['cache_seconds'] );
		?>
		<input type="number"
			   name="<?php echo esc_attr( $this->option_name ); ?>[cache_seconds]"
			   value="<?php echo esc_attr( $cache_seconds ); ?>"
			   class="small-text"
			   min="0"
			   max="600" />
		<p class="description">
			<?php esc_html_e( 'How long the status response should be cached. Set to 0 for no caching.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_custom_name() {
		$settings    = $this->get_settings();
		$custom_name = $settings['custom_name'];
		?>
		<input type="text"
			   name="<?php echo esc_attr( $this->option_name ); ?>[custom_name]"
			   value="<?php echo esc_attr( $custom_name ); ?>"
			   class="regular-text"
			   placeholder="<?php esc_attr_e( 'My Minecraft Server', 'mcsmd' ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Optional custom label to show instead of the raw server address.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_show_ip() {
		$settings = $this->get_settings();
		$show_ip  = ! empty( $settings['show_ip'] ) ? 1 : 0;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->option_name ); ?>[show_ip]"
				   value="1" <?php checked( $show_ip, 1 ); ?> />
			<?php esc_html_e( 'Display the server address under the title.', 'mcsmd' ); ?>
		</label>
		<?php
	}

	public function field_show_motd() {
		$settings  = $this->get_settings();
		$show_motd = ! empty( $settings['show_motd'] ) ? 1 : 0;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->option_name ); ?>[show_motd]"
				   value="1" <?php checked( $show_motd, 1 ); ?> />
			<?php esc_html_e( 'Show the MOTD (message of the day) if provided.', 'mcsmd' ); ?>
		</label>
		<?php
	}

	public function field_show_banner() {
		$settings    = $this->get_settings();
		$show_banner = ! empty( $settings['show_banner'] ) ? 1 : 0;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->option_name ); ?>[show_banner]"
				   value="1" <?php checked( $show_banner, 1 ); ?> />
			<?php esc_html_e( 'Show the server banner image returned by the status API.', 'mcsmd' ); ?>
		</label>
		<?php
	}

	public function field_show_player_list() {
		$settings         = $this->get_settings();
		$show_player_list = ! empty( $settings['show_player_list'] ) ? 1 : 0;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->option_name ); ?>[show_player_list]"
				   value="1" <?php checked( $show_player_list, 1 ); ?> />
			<?php esc_html_e( 'Try to show player list if provided by the status API.', 'mcsmd' ); ?>
		</label>
		<?php
	}

	public function field_player_list_limit() {
		$settings          = $this->get_settings();
		$player_list_limit = intval( $settings['player_list_limit'] );
		?>
		<input type="number"
			   name="<?php echo esc_attr( $this->option_name ); ?>[player_list_limit]"
			   value="<?php echo esc_attr( $player_list_limit ); ?>"
			   class="small-text"
			   min="1"
			   max="200" />
		<p class="description">
			<?php esc_html_e( 'Maximum number of player entries to display. Extra players will be collapsed as “+ X more”.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_player_columns() {
		$settings       = $this->get_settings();
		$player_columns = intval( $settings['player_columns'] );
		if ( $player_columns < 1 || $player_columns > 4 ) {
			$player_columns = 3;
		}
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[player_columns]">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $player_columns, $i ); ?>>
					<?php
					/* translators: %d: number of columns */
					echo esc_html( sprintf( __( '%d columns', 'mcsmd' ), $i ) );
					?>
				</option>
			<?php endfor; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'How many player cards should be displayed per row in the list.', 'mcsmd' ); ?>
		</p>
		<?php
	}

	public function field_global_dark_mode() {
		$settings         = $this->get_settings();
		$global_dark_mode = ! empty( $settings['global_dark_mode'] ) ? 1 : 0;
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( $this->option_name ); ?>[global_dark_mode]"
				   value="1" <?php checked( $global_dark_mode, 1 ); ?> />
			<?php esc_html_e( 'Force dark mode for all visitors.', 'mcsmd' ); ?>
		</label>
		<?php
	}

	/**
	 * Show notice on the plugin settings page
	 */
	public function show_api_delay_notice() {

		$screen = get_current_screen();
		if ( empty( $screen ) || 'settings_page_mcsmd-settings' !== $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'MC Status – Important Notice:', 'mcsmd' ); ?></strong><br />
				<?php
				echo wp_kses_post(
					__( 'If you are using only the WordPress plugin (without the Minecraft plugin installed on your server), server status updates may take up to <strong>1 minute</strong> to appear, because they depend on the public API <code>mcsrvstat.us</code> instead of a direct connection to your Minecraft server.', 'mcsmd' )
				);
				?>
			</p>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MC Status by MrDino', 'mcsmd' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( 'mcsmd-settings' );
				submit_button( __( 'Save changes', 'mcsmd' ) );
				?>
			</form>

			<h2><?php esc_html_e( 'How to use', 'mcsmd' ); ?></h2>
			<p><?php esc_html_e( 'Use the shortcode [mcsmd_status] in any page or post to display the Minecraft server status card.', 'mcsmd' ); ?></p>
		</div>
		<?php
	}
}
