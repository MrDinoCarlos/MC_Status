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

		// AJAX refresh (status card)
		add_action( 'wp_ajax_mcsmd_refresh_status', array( $this, 'ajax_refresh_status' ) );
		add_action( 'wp_ajax_nopriv_mcsmd_refresh_status', array( $this, 'ajax_refresh_status' ) );
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
            'last_icon'         => '',
            'last_motd_html'    => '',
            'last_motd_clean'   => '',
            'show_credit'       => 0,
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

		// Datos para AJAX (URL, nonce y tiempo de auto-refresh).
		wp_localize_script(
			'mcsmd-status-js',
			'MCSMD_AJAX',
			array(
				'url'          => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'mcsmd_refresh' ),
				'autoInterval' => 15000, // auto-refresh cada 15s (ajusta si quieres)
			)
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
	public function shortcode_status( $atts = array(), $content = '' ) {
		$settings = $this->get_settings();

		$atts = shortcode_atts(
			array(
				'force_refresh' => 0,
			),
			(array) $atts,
			'mcsmd_status'
		);

		// Si el shortcode viene con force_refresh="1" o con ?mcsmd_refresh=1 en la URL,
		// ignoramos la caché de transients.
		$ignore_cache = ! empty( $atts['force_refresh'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['mcsmd_refresh'] ) ) {
			$ignore_cache = true;
		}

		// Desactivar caché de página (plugins tipo LiteSpeed, WP Rocket, etc).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress global constant.

		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( empty( $settings['server_address'] ) ) {
			return '<div class="mcsmd-card"><p>' .
				esc_html__( 'Please configure your Minecraft server address in the MC Status settings page.', 'server-status-for-mc-by-mrdino' ) .
				'</p></div>';
		}

		$status = $this->fetch_server_status(
			$settings['server_address'],
			$settings['server_port'],
			intval( $settings['cache_seconds'] ),
			$ignore_cache
		);

		// Si venimos de caché y el servidor está online pero no hay icono,
		// hacemos una petición fresca automática para completar los datos.
		if ( ! $ignore_cache && is_array( $status ) && ! empty( $status['online'] ) && empty( $status['icon'] ) ) {

			$fresh_status = $this->fetch_server_status(
				$settings['server_address'],
				$settings['server_port'],
				intval( $settings['cache_seconds'] ),
				true // ignorar caché de transients
			);

			if ( is_array( $fresh_status ) && ! empty( $fresh_status['online'] ) && ! empty( $fresh_status['icon'] ) ) {
				// Usamos los datos frescos
				$status = $fresh_status;

				// Y actualizamos el transient para las siguientes visitas
				$cache_key = 'mcsmd_status_' . md5(
					trim( $settings['server_address'] ) . ':' . intval( $settings['server_port'] )
				);
				set_transient( $cache_key, $fresh_status, max( 5, intval( $settings['cache_seconds'] ) ) );
			}
		}

		$online         = false;
		$players_online = 0;
		$players_max    = 0;
		$player_list    = array();
		$version        = '';

		// separamos MOTD limpio y MOTD HTML
		$motd_clean     = '';
		$motd_html      = '';

		$icon           = '';
		$ping           = null;

		// datos de porcentaje
		$percent_full   = null;
		$percent_class  = '';

		if ( is_array( $status ) && isset( $status['online'] ) ) {
			$online = (bool) $status['online'];

			    if ( $online ) {
                    $settings_changed = false;

                    // Versión
                    if ( ! empty( $status['version'] ) ) {
                        $version = $status['version'];
                        $settings['last_version'] = $version;
                        $settings_changed = true;
                    }

                    // Jugadores
                    if ( ! empty( $status['players'] ) ) {
                        $players_online = intval( $status['players']['online'] ?? 0 );
                        $players_max    = intval( $status['players']['max'] ?? 0 );

                        if ( ! empty( $settings['show_player_list'] ) && ! empty( $status['players']['list'] ) ) {
                            $player_list = $this->normalize_player_list( $status['players']['list'] );
                        }
                    }

                    // MOTD (guardamos último conocido)
                    if ( ! empty( $settings['show_motd'] ) ) {
                        if ( ! empty( $status['motd']['html'] ) && is_array( $status['motd']['html'] ) ) {
                            $motd_html = implode( '<br>', $status['motd']['html'] );
                            $motd_clean = '';
                            $settings['last_motd_html']  = $motd_html;
                            $settings['last_motd_clean'] = '';
                            $settings_changed = true;

                        } elseif ( ! empty( $status['motd']['clean'] ) && is_array( $status['motd']['clean'] ) ) {
                            $motd_clean = implode( ' ', $status['motd']['clean'] );
                            $motd_html  = '';
                            $settings['last_motd_clean'] = $motd_clean;
                            $settings['last_motd_html']  = '';
                            $settings_changed = true;

                        } elseif ( ! empty( $status['motd']['raw'] ) && is_array( $status['motd']['raw'] ) ) {
                            $motd_clean = implode( ' ', $status['motd']['raw'] );
                            $motd_html  = '';
                            $settings['last_motd_clean'] = $motd_clean;
                            $settings['last_motd_html']  = '';
                            $settings_changed = true;
                        }
                    }

                    // Icono (guardamos último conocido)
                    if ( ! empty( $status['icon'] ) ) {
                        $icon = $status['icon'];
                        $settings['last_icon'] = $icon;
                        $settings_changed = true;
                    }

                    // Si hemos cambiado algo, actualizamos opciones 1 sola vez
                    if ( $settings_changed ) {
                        update_option( $this->option_name, $settings );
                    }

                } else {
                    // OFFLINE: usar datos guardados
                    $version = $settings['last_version'];

                    if ( ! empty( $settings['show_motd'] ) ) {
                        $motd_html  = $settings['last_motd_html'];
                        $motd_clean = $settings['last_motd_clean'];
                    }

                    $icon = $settings['last_icon'];
                }
            }

		// Calcular porcentaje de ocupación si tenemos datos válidos.
		if ( $online && $players_max > 0 ) {
			$percent_full = (int) round( ( $players_online / $players_max ) * 100 );

			if ( $percent_full < 0 ) {
				$percent_full = 0;
			} elseif ( $percent_full > 100 ) {
				$percent_full = 100;
			}

			if ( $percent_full <= 50 ) {
				$percent_class = 'mcsmd-percent-low';
			} elseif ( $percent_full <= 85 ) {
				$percent_class = 'mcsmd-percent-medium';
			} else {
				$percent_class = 'mcsmd-percent-high';
			}
		} else {
			$percent_full  = null;
			$percent_class = '';
		}

		// Title.
		$title = ! empty( $settings['custom_name'] ) ? $settings['custom_name'] : $settings['server_address'];

		// Placeholder initial.
		if ( function_exists( 'mb_substr' ) ) {
			$initial = mb_strtoupper( mb_substr( $title, 0, 1, 'UTF-8' ), 'UTF-8' );
		} else {
			$initial = strtoupper( substr( $title, 0, 1 ) );
		}

		// Full address (para API/banner, siempre con puerto si es distinto).
		$full_address = $settings['server_address'];
		if ( ! empty( $settings['server_port'] ) && 25565 !== intval( $settings['server_port'] ) ) {
			$full_address .= ':' . intval( $settings['server_port'] );
		}

		// Dirección para mostrar al usuario.
		$display_address = $settings['server_address'];
		if ( ! empty( $settings['show_port_in_ip'] ) && ! empty( $settings['server_port'] ) ) {
			// Ahora SIEMPRE añadimos el puerto si la opción está marcada,
			// aunque sea 25565.
			$display_address .= ':' . intval( $settings['server_port'] );
		}

		$ip_line = ! empty( $settings['show_ip'] ) ? $display_address : '';

		// Banner.
		$banner_url = '';
		if ( ! empty( $settings['show_banner'] ) ) {

			if ( ! empty( $settings['custom_banner_url'] ) ) {
				// Usa el banner personalizado (lista de servidores, etc.)
				$banner_url = esc_url( $settings['custom_banner_url'] );
			} else {
				// Banner generado por mcsrvstat.us como fallback
				$banner_url = 'https://api.mcsrvstat.us/banner/3/' . rawurlencode( $full_address );
			}
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
			$ping_title = esc_html__( 'Ping:', 'server-status-for-mc-by-mrdino' ) . ' ' . intval( $ping ) . ' ms';
		} else {
			$ping_title = esc_html__( 'Ping not available', 'server-status-for-mc-by-mrdino' );
		}

		$player_count_mode = isset( $settings['player_count_mode'] ) ? $settings['player_count_mode'] : 'online_max';

		// Card classes.
		$card_classes   = array( 'mcsmd-card' );
		$card_classes[] = $online ? 'online' : 'offline';
		if ( ! empty( $settings['global_dark_mode'] ) ) {
			$card_classes[] = 'dark';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
		     data-mcsmd-card="status">
			<div class="mcsmd-header">

				<div class="mcsmd-header-left">
					<div class="mcsmd-icon">
                    	<?php if ( $icon ) : ?>
                    		<img src="<?php echo esc_attr( $icon ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
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
								<?php esc_html_e( 'Version:', 'server-status-for-mc-by-mrdino' ); ?>
								<?php echo ' ' . esc_html( $version ); ?>
							</div>
						<?php endif; ?>

						<div class="mcsmd-players">
                            <?php if ( $online ) : ?>
                                <?php
                                if ( 'online_only' === $player_count_mode || $players_max <= 0 ) {

                                    // Solo jugadores online: "Online Players: 314"
                                    esc_html_e( 'Online Players:', 'server-status-for-mc-by-mrdino' );
                                    echo ' ' . intval( $players_online );

                                } elseif ( 'online_percent' === $player_count_mode && $players_max > 0 && null !== $percent_full ) {

                                    // Online + porcentaje: "Online Players: 314 (63%)"
                                    esc_html_e( 'Online Players:', 'server-status-for-mc-by-mrdino' );
                                    echo ' ' . intval( $players_online );
                                    echo ' <span class="mcsmd-players-percent ' . esc_attr( $percent_class ) . '">(' . intval( $percent_full ) . '%)</span>';

                                } else {

                                    // Modo por defecto: "Players: 314/500"
                                    esc_html_e( 'Players:', 'server-status-for-mc-by-mrdino' );
                                    echo ' ' . intval( $players_online ) . '/' . intval( $players_max );
                                }
                                ?>
                            <?php else : ?>
                                <span class="offline-text"><?php esc_html_e( 'Server offline', 'server-status-for-mc-by-mrdino' ); ?></span>
                            <?php endif; ?>
                        </div>
					</div>
				</div>

				<div class="mcsmd-right">

					<button class="mcsmd-refresh"
							type="button"
							data-mcsmd-refresh="status"
							aria-label="<?php esc_attr_e( 'Refresh status', 'server-status-for-mc-by-mrdino' ); ?>"
							title="<?php esc_attr_e( 'Refresh status', 'server-status-for-mc-by-mrdino' ); ?>"></button>

					<div class="mcsmd-status <?php echo $online ? 'on' : 'off'; ?>"
						 title="<?php echo esc_attr( $ping_title ); ?>">
						<span class="dot"></span>
						<span class="label"><?php echo $online ? esc_html__( 'ONLINE', 'server-status-for-mc-by-mrdino' ) : esc_html__( 'OFFLINE', 'server-status-for-mc-by-mrdino' ); ?></span>
					</div>

				</div>
			</div>

			<?php if ( $banner_url ) : ?>
				<div class="mcsmd-banner">
					<img src="<?php echo esc_url( $banner_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
				</div>
			<?php endif; ?>

			<div class="mcsmd-body">

				<?php if ( $motd_html || $motd_clean ) : ?>
					<div class="mcsmd-motd">
						<?php
						// Si tenemos MOTD en HTML, lo usamos (con colores).
						if ( $motd_html ) {
							echo wp_kses_post( $motd_html );
						} else {
							// Si no, texto limpio como fallback.
							echo esc_html( $motd_clean );
						}
						?>
					</div>
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
							<div class="extra">+<?php echo intval( $extra ); ?> <?php esc_html_e( 'more', 'server-status-for-mc-by-mrdino' ); ?></div>
						<?php endif; ?>

					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $settings['show_credit'] ) ) : ?>
            	<div class="mcsmd-footer">
            		<?php
            		echo wp_kses_post(
            			esc_html__( 'Powered by', 'server-status-for-mc-by-mrdino' ) .
            			' <a href="https://mrdino.es" target="_blank" rel="noopener">Server Status for MC by MrDino</a>'
            		);
            		?>
            	</div>
            <?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * AJAX handler para refrescar la tarjeta grande vía fetch().
	 */
	public function ajax_refresh_status() {
		check_ajax_referer( 'mcsmd_refresh', 'nonce' );

		// Evitar caché de página en la respuesta AJAX.
		// Desactivar caché de página también aquí.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Usamos la constante global estándar de WordPress.
            define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress global constant.

        }


		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is already properly escaped.
        echo $this->shortcode_status(
            array(
                'force_refresh' => 1,
            )
        );

		wp_die();
	}
}
