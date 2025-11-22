<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCSMD_Players_List {

	/**
	 * Option name used to store plugin settings.
	 *
	 * @var string
	 */
	private $option_name = 'mcsmd_settings';

	public function __construct() {
		add_shortcode( 'mcsmd_players', array( $this, 'shortcode_players_list' ) );

		// AJAX refresh (lista de jugadores)
		add_action( 'wp_ajax_mcsmd_refresh_players', array( $this, 'ajax_refresh_players' ) );
		add_action( 'wp_ajax_nopriv_mcsmd_refresh_players', array( $this, 'ajax_refresh_players' ) );
	}

	/**
	 * Get settings with defaults.
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'server_address'        => '',
			'server_port'           => 25565,
			'cache_seconds'         => 30,
			'player_columns'        => 3,
			'global_dark_mode'      => 0,
			'players_cache_seconds' => 5,
		);

		$options = get_option( $this->option_name, array() );

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Fetch server status from mcsrvstat.us API (compartiendo caché con el otro shortcode).
	 *
	 * @param string $address
	 * @param int    $port
	 * @param int    $cache_seconds
	 * @param bool   $ignore_cache Si true, ignora transients.
	 *
	 * @return array|null
	 */
	private function fetch_status( $address, $port, $cache_seconds, $ignore_cache = false ) {
		$address       = trim( (string) $address );
		$port          = intval( $port );
		$cache_seconds = intval( $cache_seconds );

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

		$full = $address;
		if ( $port > 0 && 25565 !== $port ) {
			$full .= ':' . $port;
		}

		$url      = 'https://api.mcsrvstat.us/3/' . rawurlencode( $full );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array(
					'User-Agent' => 'MCStatusByMrDino-Players/' . ( defined( 'MCSMD_VERSION' ) ? MCSMD_VERSION : '1.0.0' ),
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
	 * @param mixed $raw Raw players list.
	 * @return array
	 */
	private function normalize_player_list( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $entry ) {
			if ( is_array( $entry ) && isset( $entry['name'] ) ) {
				$out[] = (string) $entry['name'];
			} elseif ( is_string( $entry ) ) {
				$out[] = $entry;
			}
		}

		return $out;
	}

	/**
	 * Medir ping igual que en la tarjeta grande.
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

		// Usamos fsockopen solo para medir la latencia del puerto, no para el sistema de archivos.
        $fp = @fsockopen( $host, $port, $errno, $errstr, $timeout ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen
        if ( ! $fp ) {
            return null;
        }

        fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose


		$end = microtime( true );

		$ms = (int) round( ( $end - $start ) * 1000 );
		return ( $ms < 0 ) ? null : $ms;
	}

	/**
	 * Shortcode [mcsmd_players] – lista de jugadores online con buscador y orden.
	 */
	public function shortcode_players_list( $atts = array() ) {
		$settings       = $this->get_settings();
		$is_dark_global = ! empty( $settings['global_dark_mode'] );

		$atts = shortcode_atts(
			array(
				'force_refresh' => 0,
			),
			(array) $atts,
			'mcsmd_players'
		);

		$ignore_cache = ! empty( $atts['force_refresh'] );

		// Desactivar caché de página también aquí.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress global constant.
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( empty( $settings['server_address'] ) ) {
			return '<div class="mcsmd-card"><p>' .
				esc_html__( 'Please configure your Minecraft server address in the MC Status settings page.', 'mcsmd' ) .
				'</p></div>';
		}

		$status = $this->fetch_status(
			$settings['server_address'],
			$settings['server_port'],
			intval( $settings['players_cache_seconds'] ),
			$ignore_cache
		);

		$ping = $this->measure_ping_ms( $settings['server_address'], $settings['server_port'] );

		if ( ! is_array( $status ) || empty( $status['online'] ) || null === $ping ) {
			// tratamos como OFFLINE igual que la tarjeta grande
			$card_classes = array( 'mcsmd-card', 'offline' );
			if ( $is_dark_global ) {
				$card_classes[] = 'dark';
			}

			ob_start();
			?>
			<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
			     data-mcsmd-card="players">
				<div class="mcsmd-offline-msg">
					<span class="status-dot-red"></span>
					<?php esc_html_e( 'Server is offline – no player list available.', 'mcsmd' ); ?>
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

		// ---------------------------------------------------------------------

		$list = $this->normalize_player_list( $status['players']['list'] ?? array() );

		// Columnas desde ajustes
		$cols = isset( $settings['player_columns'] ) ? intval( $settings['player_columns'] ) : 3;
		if ( $cols < 1 ) {
			$cols = 1;
		} elseif ( $cols > 4 ) {
			$cols = 4;
		}

		// Clases de tarjeta ONLINE (con dark si toca)
		$card_classes = array( 'mcsmd-card', 'online' );
		if ( $is_dark_global ) {
			$card_classes[] = 'dark';
		}

		// --- NO HAY JUGADORES ONLINE -----------------------------------------
		if ( empty( $list ) ) {

			ob_start();
			?>
			<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
			     data-mcsmd-card="players">

				<div class="mcsmd-players-title">
                    <?php
                    printf(
                        /* translators: %d: number of online players. */
                        esc_html__( 'Online Players (%d)', 'mcsmd' ),
                        0
                    );
                    ?>
                </div>


					<button
						type="button"
						class="mcsmd-refresh-title"
						data-mcsmd-refresh="players"
						title="<?php esc_attr_e( 'Refresh players', 'mcsmd' ); ?>"
					>
						⟳
					</button>
				</div>

				<div class="mcsmd-empty-msg">
					<span class="status-dot-green"></span>
					<?php esc_html_e( 'No players online right now.', 'mcsmd' ); ?>
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
		// ---------------------------------------------------------------------

		// ID único para JS (por si hay varios shortcodes en la misma página)
		$container_id = 'mcsmd-pl-' . wp_rand( 1000, 999999 );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $container_id ); ?>"
		     class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
		     data-mcsmd-card="players">

			<div class="mcsmd-players-header">
				<div class="mcsmd-players-title">
                    <?php
                    printf(
                        /* translators: %d: number of online players. */
                        esc_html__( 'Online Players (%d)', 'mcsmd' ),
                        count( $list )
                    );
                    ?>
                </div>

				<button
					type="button"
					class="mcsmd-refresh-title"
					data-mcsmd-refresh="players"
					title="<?php esc_attr_e( 'Refresh players', 'mcsmd' ); ?>"
				>
					⟳
				</button>
			</div>

			<div class="mcsmd-player-toolbar">
				<input
					type="text"
					class="mcsmd-player-search-input"
					placeholder="<?php esc_attr_e( 'Search player...', 'mcsmd' ); ?>"
				/>

				<select class="mcsmd-player-sort">
					<option value="name_az"><?php esc_html_e( 'Name A → Z', 'mcsmd' ); ?></option>
					<option value="name_za"><?php esc_html_e( 'Name Z → A', 'mcsmd' ); ?></option>
				</select>
			</div>

			<div class="mcsmd-players-box cols-<?php echo esc_attr( $cols ); ?> mcsmd-players-list-searchable">
				<?php foreach ( $list as $name ) : ?>
					<?php
					$name   = (string) $name;
					$avatar = 'https://mc-heads.net/avatar/' . rawurlencode( $name ) . '/32';
					?>
					<div class="player" data-player="<?php echo esc_attr( strtolower( $name ) ); ?>">
						<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
						<div class="name"><?php echo esc_html( $name ); ?></div>
					</div>
				<?php endforeach; ?>
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

		<script>
		(function(){
			const container = document.getElementById('<?php echo esc_js( $container_id ); ?>');
			if (!container) return;

			const searchInput = container.querySelector('.mcsmd-player-search-input');
			const sortSelect  = container.querySelector('.mcsmd-player-sort');
			const box         = container.querySelector('.mcsmd-players-box');

			if (!searchInput || !sortSelect || !box) return;

			const players = Array.from(box.querySelectorAll('.player'));

			function applyFilters() {
				const term = searchInput.value.toLowerCase();
				const mode = sortSelect.value;

				let list = players.slice();

				// Ordenar
				list.sort(function(a, b){
					const na = a.dataset.player;
					const nb = b.dataset.player;

					if (mode === 'name_za') {
						return nb.localeCompare(na);
					}
					// name_az por defecto
					return na.localeCompare(nb);
				});

				// Aplicar filtro + reordenar en el DOM
				list.forEach(function(player){
					const matches = player.dataset.player.indexOf(term) !== -1;
					player.style.display = matches ? 'flex' : 'none';
					box.appendChild(player);
				});
			}

			searchInput.addEventListener('input', applyFilters);
			sortSelect.addEventListener('change', applyFilters);

			// Primera pasada
			applyFilters();
		})();
		</script>

		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler para refrescar la lista de jugadores vía fetch().
	 */
	public function ajax_refresh_players() {
		check_ajax_referer( 'mcsmd_refresh', 'nonce' );

		// Desactivar caché de página también aquí.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Usamos la constante global estándar de WordPress.
            define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress global constant.
        }


		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is already properly escaped.
        echo $this->shortcode_players_list(
            array(
                'force_refresh' => 1,
            )
        );


		wp_die();
	}
}
