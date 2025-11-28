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
			__( 'Server Status for MC by MrDino', 'server-status-for-mc-by-mrdino' ),
			__( 'Server Status for MC by MrDino', 'server-status-for-mc-by-mrdino' ),
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
			'custom_banner_url' => '',
			'show_port_in_ip'   => 1,
			'player_count_mode' => 'online_max',
			'show_credit'       => 0,
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
			__( 'Basic server settings', 'server-status-for-mc-by-mrdino' ),
			'__return_false',
			'mcsmd-settings'
		);

		add_settings_field(
			'server_address',
			__( 'Server address', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_server_address' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		add_settings_field(
			'server_port',
			__( 'Server port', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_server_port' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		add_settings_field(
			'cache_seconds',
			__( 'Status cache (seconds)', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_cache_seconds' ),
			'mcsmd-settings',
			'mcsmd_main_section'
		);

		// Display section.
		add_settings_section(
			'mcsmd_display_section',
			__( 'Display options', 'server-status-for-mc-by-mrdino' ),
			'__return_false',
			'mcsmd-settings'
		);

		add_settings_field(
        	'show_credit',
        	__( 'Show credit link', 'server-status-for-mc-by-mrdino' ),
        	array( $this, 'field_show_credit' ),
        	'mcsmd-settings',
        	'mcsmd_display_section'
        );

		add_settings_field(
			'custom_name',
			__( 'Custom server name', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_custom_name' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_ip',
			__( 'Show IP / address', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_show_ip' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
        	'show_port_in_ip',
        	__( 'Show port in IP', 'server-status-for-mc-by-mrdino' ),
        	array( $this, 'field_show_port_in_ip' ),
        	'mcsmd-settings',
        	'mcsmd_display_section'
        );

		add_settings_field(
			'show_motd',
			__( 'Show MOTD', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_show_motd' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_banner',
			__( 'Show server banner', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_show_banner' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		// NUEVO: campo para URL personalizada de banner.
		add_settings_field(
			'custom_banner_url',
			__( 'Custom banner URL', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_custom_banner_url' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'show_player_list',
			__( 'Show player list (if available)', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_show_player_list' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'player_list_limit',
			__( 'Max players to show in list', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_player_list_limit' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
        	'player_count_mode',
        	__( 'Player count display', 'server-status-for-mc-by-mrdino' ),
        	array( $this, 'field_player_count_mode' ),
        	'mcsmd-settings',
        	'mcsmd_display_section'
        );

		add_settings_field(
			'player_columns',
			__( 'Player cards per row', 'server-status-for-mc-by-mrdino' ),
			array( $this, 'field_player_columns' ),
			'mcsmd-settings',
			'mcsmd_display_section'
		);

		add_settings_field(
			'global_dark_mode',
			__( 'Enable dark mode (global)', 'server-status-for-mc-by-mrdino' ),
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
		$output['custom_banner_url']  = isset( $input['custom_banner_url'] ) ? esc_url_raw( $input['custom_banner_url'] ) : '';
		$output['show_port_in_ip']    = ! empty( $input['show_port_in_ip'] ) ? 1 : 0;
		$output['show_credit']        = ! empty( $input['show_credit'] ) ? 1 : 0;

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

        // Modo de conteo de jugadores.
        $allowed_modes = array( 'online_max', 'online_only', 'online_percent' );
        if ( isset( $input['player_count_mode'] ) && in_array( $input['player_count_mode'], $allowed_modes, true ) ) {
        	$output['player_count_mode'] = $input['player_count_mode'];
        } else {
        	$output['player_count_mode'] = 'online_max';
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
			<?php esc_html_e( 'Minecraft server IP or hostname. Do not include the port here.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Default Minecraft port is 25565.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'How long the status response should be cached. Set to 0 for no caching.', 'server-status-for-mc-by-mrdino' ); ?>
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
			   placeholder="<?php esc_attr_e( 'My Minecraft Server', 'server-status-for-mc-by-mrdino' ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Optional custom label to show instead of the raw server address.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Display the server address under the title.', 'server-status-for-mc-by-mrdino' ); ?>
		</label>
		<?php
	}

    public function field_show_credit() {
    	$settings     = $this->get_settings();
    	$show_credit  = ! empty( $settings['show_credit'] ) ? 1 : 0;
    	?>
    	<label>
    		<input type="checkbox"
    			   name="<?php echo esc_attr( $this->option_name ); ?>[show_credit]"
    			   value="1" <?php checked( $show_credit, 1 ); ?> />
    		<?php esc_html_e( 'Display a small “Powered by Server Status for MC by MrDino” link in the status cards.', 'server-status-for-mc-by-mrdino' ); ?>
    	</label>
    	<p class="description">
    		<?php esc_html_e( 'This is completely optional and disabled by default.', 'server-status-for-mc-by-mrdino' ); ?>
    	</p>
    	<?php
    }

    public function field_show_port_in_ip() {
    	$settings       = $this->get_settings();
    	$show_port_in_ip = ! empty( $settings['show_port_in_ip'] ) ? 1 : 0;
    	?>
    	<label>
    		<input type="checkbox"
    			   name="<?php echo esc_attr( $this->option_name ); ?>[show_port_in_ip]"
    			   value="1" <?php checked( $show_port_in_ip, 1 ); ?> />
    		<?php esc_html_e( 'Include the server port when displaying the IP (e.g. play.example.com:25565).', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Show the MOTD (message of the day) if provided.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Show the server banner image returned by the status API or the custom banner URL.', 'server-status-for-mc-by-mrdino' ); ?>
		</label>
		<?php
	}

	/**
	 * NUEVO: campo para URL de banner personalizado
	 */
	public function field_custom_banner_url() {
		$settings          = $this->get_settings();
		$custom_banner_url = isset( $settings['custom_banner_url'] ) ? $settings['custom_banner_url'] : '';
		?>
		<input type="url"
			   name="<?php echo esc_attr( $this->option_name ); ?>[custom_banner_url]"
			   value="<?php echo esc_attr( $custom_banner_url ); ?>"
			   class="regular-text"
			   placeholder="https://example.com/mi-banner.gif" />
		<p class="description">
        	<?php esc_html_e( 'Optional. If set, this image URL will be used as the banner (for example, a banner from a server list). Leave empty to use the generated banner from mcsrvstat.us.', 'server-status-for-mc-by-mrdino' ); ?>
        </p>
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
			<?php esc_html_e( 'Try to show player list if provided by the status API.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Maximum number of player entries to display. Extra players will be collapsed as “+ X more”.', 'server-status-for-mc-by-mrdino' ); ?>
		</p>
		<?php
	}

    public function field_player_count_mode() {
    	$settings          = $this->get_settings();
    	$player_count_mode = isset( $settings['player_count_mode'] ) ? $settings['player_count_mode'] : 'online_max';
    	?>
    	<select name="<?php echo esc_attr( $this->option_name ); ?>[player_count_mode]">
    		<option value="online_max" <?php selected( $player_count_mode, 'online_max' ); ?>>
    			<?php esc_html_e( 'Show “online / max” (e.g. 294/500)', 'server-status-for-mc-by-mrdino' ); ?>
    		</option>
    		<option value="online_only" <?php selected( $player_count_mode, 'online_only' ); ?>>
    			<?php esc_html_e( 'Show only online players (e.g. 294)', 'server-status-for-mc-by-mrdino' ); ?>
    		</option>
    		<option value="online_percent" <?php selected( $player_count_mode, 'online_percent' ); ?>>
    			<?php esc_html_e( 'Show online players with percent (e.g. 294 (59%))', 'server-status-for-mc-by-mrdino' ); ?>
    		</option>
    	</select>
    	<p class="description">
    		<?php esc_html_e( 'Choose how the Players line is displayed in the status card header.', 'server-status-for-mc-by-mrdino' ); ?>
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
					echo esc_html( sprintf( __( '%d columns', 'server-status-for-mc-by-mrdino' ), $i ) );
					?>
				</option>
			<?php endfor; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'How many player cards should be displayed per row in the list.', 'server-status-for-mc-by-mrdino' ); ?>
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
			<?php esc_html_e( 'Force dark mode for all visitors.', 'server-status-for-mc-by-mrdino' ); ?>
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
				<strong><?php esc_html_e( 'MC Status – Important Notice:', 'server-status-for-mc-by-mrdino' ); ?></strong><br />
				<?php
				echo wp_kses_post(
                	__( 'If you are using only the WordPress plugin (without the Minecraft plugin installed on your server), server status updates may take up to <strong>1 minute</strong> to appear, because they depend on the public API <code>mcsrvstat.us</code> instead of a direct connection to your Minecraft server.', 'server-status-for-mc-by-mrdino' )
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
            <h1><?php esc_html_e( 'Server Status for MC by MrDino', 'server-status-for-mc-by-mrdino' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( 'mcsmd-settings' );
                submit_button( __( 'Save changes', 'server-status-for-mc-by-mrdino' ) );
                ?>
            </form>

            <h2><?php esc_html_e( 'How to use', 'server-status-for-mc-by-mrdino' ); ?></h2>

            <p><?php esc_html_e( 'After saving your server settings above, you can use the following shortcodes in any page or post:', 'server-status-for-mc-by-mrdino' ); ?></p>

            <ul>
                <li>
                    <code>[mcsmd_status]</code>
                    – <?php esc_html_e( 'shows the main Minecraft server status card (IP, version, MOTD, banner, players, ping, etc.).', 'server-status-for-mc-by-mrdino' ); ?>
                </li>
                <li>
                    <code>[mcsmd_players]</code>
                    – <?php esc_html_e( 'shows only the online players list, with search box and sorting options.', 'server-status-for-mc-by-mrdino' ); ?>
                </li>
            </ul>

            <h3><?php esc_html_e( 'Quick start', 'server-status-for-mc-by-mrdino' ); ?></h3>
            <ol>
                <li><?php esc_html_e( 'Enter your Minecraft server address and port in the “Basic server settings” section above and save the changes.', 'server-status-for-mc-by-mrdino' ); ?></li>
                <li><?php esc_html_e( 'Optionally adjust the display options (banner, MOTD, dark mode, player list, etc.).', 'server-status-for-mc-by-mrdino' ); ?></li>
                <li><?php esc_html_e( 'Create or edit a page, paste the shortcode you want to use and publish the page.', 'server-status-for-mc-by-mrdino' ); ?></li>
            </ol>

            <p>
                <?php esc_html_e( 'Tip: the status and players cards auto-refresh in the background, so visitors will see updated information without reloading the whole page.', 'server-status-for-mc-by-mrdino' ); ?>
            </p>
        </div>
        <?php
    }
}
