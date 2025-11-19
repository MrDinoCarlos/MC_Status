<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCSMD_Frontend {

	/**
	 * Option name used to store plugin settings.
	 *
	 * @var string
	 */
	private $option_name = 'mcsmd_settings';

	public function __construct() {
		add_shortcode( 'mcsmd_status', array( $this, 'shortcode_status' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

	/**
	 * Enqueue frontend CSS/JS.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'mcsmd-status',
			MCSMD_PLUGIN_URL . 'assets/css/mcsmd-status.css',
			array(),
			MCSMD_VERSION
		);

		wp_enqueue_script(
			'mcsmd-status-js',
			MCSMD_PLUGIN_URL . 'assets/js/mcsmd-status.js',
			array(),
			MCSMD_VERSION,
			true
		);
	}

	/**
	 * Measure ping in ms (TCP connect).
	 *
	 * @param string $host Host/IP without port.
	 * @param int    $port Port.
	 * @return int|null Ping in ms or null on failure.
	 */
	private function measure_ping_ms( $host, $port ) {
		$host = trim( $host );
		$port = intval( $port );

		if ( empty( $host ) || $port <= 0 ) {
			return null;
		}

		$timeout = 1.0;

		$start = microtime( true );

		$errno  = 0;
		$errstr = '';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen
		$fp = @fsockopen( $host, $port, $errno, $errstr, $timeout );

		if ( ! $fp ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $fp );

		$end = microtime( true );

		$ms = (int) round( ( $end - $start ) * 1000 );

		if ( $ms < 0 ) {
			$ms = null;
		}

		return $ms;
	}

	/**
	 * Fetch server status from mcsrvstat.us API with optional caching.
	 *
	 * @param string $address       Server address (hostname or IP).
	 * @param int    $port          Server port.
	 * @param int    $cache_seconds Cache duration in seconds.
	 * @param bool   $ignore_cache  If true, skip transients.
	 *
	 * @return array|null
	 */
	private function fetch_server_status( $address, $port, $cache_seconds, $ignore_cache = false ) {
		$address = trim( $address );
		$port    = intval( $port );

		if ( empty( $address ) ) {
			return null;
		}

		$use_cache = ( $cache_seconds > 0 && ! $ignore_cache );
		$cache_key = 'mcsmd_status_' . md5( $address . ':' . $port );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$full_address = $address;
		if ( $port > 0 && 25565 !== $port ) {
			$full_address .= ':' . $port;
		}

		$url = 'https://api.mcsrvstat.us/3/' . rawurlencode( $full_address );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array(
					'User-Agent' => 'MCStatusByMrDino/' . MCSMD_VERSION . ' (https://mrdino.es)',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( $use_cache ) {
			set_transient( $cache_key, $data, max( 5, $cache_seconds ) );
		}

		return $data;
	}

	/**
	 * Normalize players.list from the API into a simple array of player names.
	 *
	 * @param mixed $raw_list Raw players list from the API.
	 *
	 * @return array Array of player name strings.
	 */
	private function normalize_player_list( $raw_list ) {
		if ( ! is_array( $raw_list ) ) {
			return array();
		}

		$names = array();

		foreach ( $raw_list as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['name'] ) ) {
				$names[] = $entry['name'];
			} elseif ( is_string( $entry ) ) {
				$names[] = $entry;
			}
		}

		return $names;
	}

	/**
	 * Shortcode callback for [mcsmd_status].
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Content (unused).
	 *
	 * @return string
	 */
	public function shortcode_status( $atts, $content = '' ) {

		$settings = $this->get_settings();

		// Prevent page cache.
		if ( ! defined( 'MCSMD_DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			define( 'DONOTCACHEPAGE', true );
		}

		if ( empty( $settings['server_address'] ) ) {
			return '<div class="mcsmd-card"><p>' .
				esc_html__( 'Please configure your Minecraft server address in the MC Status settings page.', 'mcsmd' ) .
				'</p></div>';
		}

		// If coming from refresh button, ignore internal cache.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ignore_cache = ! empty( $_GET['mcsmd_refresh'] );

		$status = $this->fetch_server_status(
			$settings['server_address'],
			$settings['server_port'],
			intval( $settings['cache_seconds'] ),
			$ignore_cache
		);

		$online         = false;
		$players_online = 0;
		$players_max    = 0;
		$player_list    = array();
		$version        = '';
		$motd           = '';
		$icon           = '';
		$ping           = null;

		if ( is_array( $status ) && isset( $status['online'] ) ) {
			$online = (bool) $status['online'];

			if ( $online ) {
				if ( ! empty( $status['version'] ) ) {
					$settings['last_version'] = $status['version'];
					update_option( $this->option_name, $settings );
				}

				if ( ! empty( $status['players'] ) ) {
					$players_online = intval( $status['players']['online'] ?? 0 );
					$players_max    = intval( $status['players']['max'] ?? 0 );

					if ( ! empty( $settings['show_player_list'] ) && ! empty( $status['players']['list'] ) ) {
						$player_list = $this->normalize_player_list( $status['players']['list'] );
					}
				}

				if (
					! empty( $settings['show_motd'] ) &&
					! empty( $status['motd']['clean'] ) &&
					is_array( $status['motd']['clean'] )
				) {
					$motd = implode( ' ', $status['motd']['clean'] );
				}

				if ( ! empty( $status['icon'] ) ) {
					$icon = $status['icon'];
				}

				if ( ! empty( $status['version'] ) ) {
					$version = $status['version'];
				}
			} else {
				$version = $settings['last_version'];
			}
		}

		// Title.
		$title = ! empty( $settings['custom_name'] ) ? $settings['custom_name'] : $settings['server_address'];

		// Placeholder initial.
		if ( function_exists( 'mb_substr' ) ) {
			$initial = mb_strtoupper( mb_substr( $title, 0, 1, 'UTF-8' ), 'UTF-8' );
		} else {
			$initial = strtoupper( substr( $title, 0, 1 ) );
		}

		// Full address.
		$full_address = $settings['server_address'];
		if ( ! empty( $settings['server_port'] ) && 25565 !== intval( $settings['server_port'] ) ) {
			$full_address .= ':' . intval( $settings['server_port'] );
		}

		$ip_line = ! empty( $settings['show_ip'] ) ? $full_address : '';

		// Banner.
		$banner_url = '';
		if ( ! empty( $settings['show_banner'] ) ) {
			$banner_url = 'https://api.mcsrvstat.us/banner/3/' . rawurlencode( $full_address );
		}

		// Player list.
		$limit   = max( 1, intval( $settings['player_list_limit'] ) );
		$visible = array_slice( $player_list, 0, $limit );
		$extra   = max( 0, count( $player_list ) - count( $visible ) );

		// Columns.
		$cols = isset( $settings['player_columns'] ) ? intval( $settings['player_columns'] ) : 3;
		if ( $cols < 1 || $cols > 4 ) {
			$cols = 3;
		}

		// PING: always measured when API says online.
		if ( $online ) {
			$ping = $this->measure_ping_ms( $settings['server_address'], $settings['server_port'] );

			// If API says online but socket fails, treat as offline.
			if ( null === $ping ) {
				$online         = false;
				$players_online = 0;
				$players_max    = 0;
				$visible        = array();
				$extra          = 0;
			}
		}

		if ( ! $online || null === $ping ) {
			$ping = null;
		}

		if ( null !== $ping ) {
        	$ping_title = esc_html__( 'Ping:', 'mcsmd' ) . ' ' . intval( $ping ) . ' ms';
        } else {
        	$ping_title = esc_html__( 'Ping not available', 'mcsmd' );
        }


		// Card classes.
		$card_classes = array( 'mcsmd-card' );
		$card_classes[] = $online ? 'online' : 'offline';
		if ( ! empty( $settings['global_dark_mode'] ) ) {
			$card_classes[] = 'dark';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>">

			<div class="mcsmd-header">

				<div class="mcsmd-header-left">
					<div class="mcsmd-icon">
						<?php if ( $icon ) : ?>
							<img src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
						<?php else : ?>
							<span><?php echo esc_html( $initial ); ?></span>
						<?php endif; ?>
					</div>

					<div class="mcsmd-info">
						<div class="mcsmd-title"><?php echo esc_html( $title ); ?></div>

						<?php if ( $ip_line ) : ?>
							<div class="mcsmd-ip"><?php echo esc_html( $ip_line ); ?></div>
						<?php endif; ?>

						<?php if ( $version ) : ?>
							<div class="mcsmd-version">
								<?php esc_html_e( 'Version:', 'mcsmd' ); ?>
								<?php echo ' ' . esc_html( $version ); ?>
							</div>
						<?php endif; ?>

						<div class="mcsmd-players">
							<?php if ( $online ) : ?>
								<?php esc_html_e( 'Players:', 'mcsmd' ); ?>
								<?php echo ' ' . intval( $players_online ) . '/' . intval( $players_max ); ?>
							<?php else : ?>
								<span class="offline-text"><?php esc_html_e( 'Server offline', 'mcsmd' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="mcsmd-right">

					<button class="mcsmd-refresh"
							type="button"
							data-mcsmd-refresh="1"
							aria-label="<?php esc_attr_e( 'Refresh status', 'mcsmd' ); ?>"
							title="<?php esc_attr_e( 'Refresh status', 'mcsmd' ); ?>"></button>

					<div class="mcsmd-status <?php echo $online ? 'on' : 'off'; ?>"
						 title="<?php echo esc_attr( $ping_title ); ?>">
						<span class="dot"></span>
						<span class="label"><?php echo $online ? esc_html__( 'ONLINE', 'mcsmd' ) : esc_html__( 'OFFLINE', 'mcsmd' ); ?></span>
					</div>

				</div>
			</div>

			<?php if ( $banner_url ) : ?>
				<div class="mcsmd-banner">
					<img src="<?php echo esc_url( $banner_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
				</div>
			<?php endif; ?>

			<div class="mcsmd-body">

				<?php if ( $motd ) : ?>
					<div class="mcsmd-motd"><?php echo esc_html( $motd ); ?></div>
				<?php endif; ?>

				<?php if ( $online && ! empty( $visible ) ) : ?>
					<div class="mcsmd-players-box <?php echo 'cols-' . esc_attr( $cols ); ?>">

						<?php foreach ( $visible as $p_name ) : ?>
							<?php
							$p_name = (string) $p_name;
							$head   = 'https://mc-heads.net/avatar/' . rawurlencode( $p_name ) . '/32';
							?>
							<div class="player">
								<img src="<?php echo esc_url( $head ); ?>" alt="<?php echo esc_attr( $p_name ); ?>" />
								<div class="name"><?php echo esc_html( $p_name ); ?></div>
								<span class="player-status-dot"
									  title="<?php echo esc_attr( $ping_title ); ?>"></span>
							</div>
						<?php endforeach; ?>

						<?php if ( $extra > 0 ) : ?>
							<div class="extra">+<?php echo intval( $extra ); ?> <?php esc_html_e( 'more', 'mcsmd' ); ?></div>
						<?php endif; ?>

					</div>
				<?php endif; ?>
			</div>

			<div class="mcsmd-footer">
				<?php
				echo wp_kses_post(
                	esc_html__( 'Powered by', 'mcsmd' ) .
                	' <a href="https://mrdino.es" target="_blank" rel="noopener">MC Status by MrDino</a>'
                );
				?>
			</div>


		</div>
		<?php

		return ob_get_clean();
	}
}
