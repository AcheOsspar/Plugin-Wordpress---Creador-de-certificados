<?php
/**
 * Shortcode [zsz_verificador]: consulta por _certificado_codigo y, si falla, por título del post (folios legados).
 *
 * @package Zen_System_Z
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verificador público (solo lectura + salida escapada).
 */
class ZSZ_Verificador {

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registra estilo (carga bajo demanda en el shortcode).
	 */
	public function register_assets() {
		wp_register_style(
			'zsz-verificador',
			ZSZ_URL . 'assets/css/zsz-style.css',
			array(),
			ZSZ_VERSION
		);
	}

	/**
	 * Shortcode.
	 */
	public function register_shortcode() {
		add_shortcode( 'zsz_verificador', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Código solicitado: POST del formulario o GET (compatibilidad ?id= / ?codigo=).
	 *
	 * @return string
	 */
	private function get_requested_code() {
		if ( isset( $_POST['zsz_verify'], $_POST['zsz_code'] ) && $_POST['zsz_code'] !== '' ) {
			return sanitize_text_field( wp_unslash( $_POST['zsz_code'] ) );
		}
		if ( isset( $_GET['codigo'] ) && $_GET['codigo'] !== '' ) {
			return sanitize_text_field( wp_unslash( $_GET['codigo'] ) );
		}
		if ( isset( $_GET['id'] ) && $_GET['id'] !== '' ) {
			return sanitize_text_field( wp_unslash( $_GET['id'] ) );
		}
		return '';
	}

	/**
	 * Busca un certificado individual por meta _certificado_codigo.
	 *
	 * @param string $code Código sanitizado.
	 * @return WP_Post|null
	 */
	private function find_certificado_by_code( $code ) {
		if ( $code === '' ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'certificado',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_certificado_codigo',
						'value' => $code,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		return $query->posts[0];
	}

	/**
	 * Busca certificado cuyo título coincide con el código (sistemas antiguos guardaban el folio como título).
	 * Comparación normalizada: TRIM + mayúsculas/minúsculas. Si hay más de un candidato, no devuelve resultado.
	 *
	 * @param string $code Código sanitizado (mismo criterio que la búsqueda por meta).
	 * @return WP_Post|null
	 */
	private function find_certificado_by_title( $code ) {
		if ( $code === '' ) {
			return null;
		}

		$normalized = trim( $code );
		if ( $normalized === '' ) {
			return null;
		}

		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s AND post_status = %s
				AND LOWER( TRIM( post_title ) ) = LOWER( %s )
				LIMIT 2",
				'certificado',
				'publish',
				$normalized
			)
		);

		if ( count( $ids ) !== 1 ) {
			return null;
		}

		$post = get_post( (int) $ids[0] );
		return ( $post instanceof WP_Post ) ? $post : null;
	}

	/**
	 * Meta de certificado como texto recortado.
	 *
	 * @param int    $post_id ID del post.
	 * @param string $meta_key Clave.
	 * @return string
	 */
	private function get_cert_meta_string( $post_id, $meta_key ) {
		$v = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_string( $v ) ) {
			return '';
		}
		return trim( $v );
	}

	/**
	 * Filas no vacías para una sección de la tarjeta.
	 *
	 * @param array<int, array{0: string, 1: string}> $rows Pares etiqueta, valor.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function filter_nonempty_rows( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row[1] ) || $row[1] === '' ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * HTML de una sección dl dentro de la tarjeta de éxito.
	 *
	 * @param string                               $title Título de sección.
	 * @param array<int, array{0: string, 1: string}> $rows  Etiqueta, valor.
	 */
	private function render_verification_section( $title, array $rows ) {
		$rows = $this->filter_nonempty_rows( $rows );
		if ( empty( $rows ) ) {
			return;
		}
		?>
		<section class="zsz-card-section">
			<h4 class="zsz-card-section-title"><?php echo esc_html( $title ); ?></h4>
			<dl class="zsz-card-dl zsz-card-dl--rows">
				<?php foreach ( $rows as $pair ) : ?>
					<div class="zsz-card-dl-row">
						<dt><?php echo esc_html( $pair[0] ); ?></dt>
						<dd><?php echo esc_html( $pair[1] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>
		<?php
	}

	/**
	 * Salida del shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		wp_enqueue_style( 'zsz-verificador' );

		$code   = $this->get_requested_code();
		$error  = '';
		$post   = null;

		if ( $code !== '' && isset( $_POST['zsz_verify'] ) ) {
			if ( ! isset( $_POST['zsz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zsz_nonce'] ) ), 'zsz_verificador' ) ) {
				$error = __( 'Error de seguridad. Intente de nuevo.', 'zen-system-z' );
			}
		}

		if ( $code !== '' && $error === '' ) {
			$post = $this->find_certificado_by_code( $code );
			if ( ! $post instanceof WP_Post ) {
				$post = $this->find_certificado_by_title( $code );
			}
		}

		$nonce = wp_create_nonce( 'zsz_verificador' );

		ob_start();
		?>
		<div class="zsz-verificador-wrap">
			<form class="zsz-verificador-form" method="post" action="">
				<label class="zsz-label" for="zsz_code"><?php esc_html_e( 'Código de verificación', 'zen-system-z' ); ?></label>
				<input type="text" id="zsz_code" name="zsz_code" class="zsz-input" value="<?php echo esc_attr( $code ); ?>"
					autocomplete="off" />
				<?php wp_nonce_field( 'zsz_verificador', 'zsz_nonce' ); ?>
				<button type="submit" name="zsz_verify" value="1" class="zsz-btn zsz-btn-primary">
					<?php esc_html_e( 'Verificar', 'zen-system-z' ); ?>
				</button>
			</form>

			<?php if ( $error !== '' ) : ?>
				<div class="zsz-card zsz-card-error" role="alert">
					<p class="zsz-card-message"><?php echo esc_html( $error ); ?></p>
				</div>
			<?php elseif ( $code !== '' && ( isset( $_POST['zsz_verify'] ) || isset( $_GET['codigo'] ) || isset( $_GET['id'] ) ) ) : ?>
				<?php if ( $post ) : ?>
					<?php
					$pid     = (int) $post->ID;
					$gm      = array( $this, 'get_cert_meta_string' );
					$pdf_url = $gm( $pid, '_certificado_pdf_url' );
					$listado = get_post_meta( $pid, '_certificado_listado_participantes', true );
					$listado = is_string( $listado ) ? $listado : '';
					$listado = str_replace( "\r\n", "\n", $listado );
					$listado_lines = array_values(
						array_filter(
							array_map( 'trim', explode( "\n", $listado ) ),
							static function ( $line ) {
								return $line !== '';
							}
						)
					);
					$listado_count = count( $listado_lines );
					?>
					<div class="zsz-card zsz-card-success">
						<div class="zsz-card-head">
							<div class="zsz-card-badge"><?php esc_html_e( 'Válido', 'zen-system-z' ); ?></div>
							<h3 class="zsz-card-title"><?php esc_html_e( 'Certificado verificado', 'zen-system-z' ); ?></h3>
							<?php if ( $post->post_title !== '' ) : ?>
								<p class="zsz-card-kicker"><?php echo esc_html( get_the_title( $post ) ); ?></p>
							<?php endif; ?>
						</div>
						<div class="zsz-card-body">
							<?php
							$this->render_verification_section(
								__( 'Identificación', 'zen-system-z' ),
								array(
									array( __( 'Código', 'zen-system-z' ), $gm( $pid, '_certificado_codigo' ) ),
									array( __( 'Participante / alumno', 'zen-system-z' ), $gm( $pid, '_certificado_participante' ) ),
								)
							);
							$this->render_verification_section(
								__( 'Programa y vigencia', 'zen-system-z' ),
								array(
									array( __( 'Curso', 'zen-system-z' ), $gm( $pid, '_certificado_curso' ) ),
									array( __( 'Duración (horas)', 'zen-system-z' ), $gm( $pid, '_certificado_duracion' ) ),
									array( __( 'Fecha de emisión', 'zen-system-z' ), $gm( $pid, '_certificado_fecha' ) ),
									array( __( 'Fecha de realización', 'zen-system-z' ), $gm( $pid, '_certificado_fecha_realizacion' ) ),
									array( __( 'Fecha de expiración', 'zen-system-z' ), $gm( $pid, '_certificado_fecha_expiracion' ) ),
								)
							);
							$this->render_verification_section(
								__( 'Organización', 'zen-system-z' ),
								array(
									array( __( 'Empresa', 'zen-system-z' ), $gm( $pid, '_certificado_empresa' ) ),
									array( __( 'Cliente', 'zen-system-z' ), $gm( $pid, '_certificado_oc_cliente' ) ),
								)
							);
							$this->render_verification_section(
								__( 'Equipo formativo', 'zen-system-z' ),
								array(
									array( __( 'Director', 'zen-system-z' ), $gm( $pid, '_certificado_director' ) ),
									array( __( 'Relator', 'zen-system-z' ), $gm( $pid, '_certificado_instructor' ) ),
								)
							);
							?>
							<?php if ( $listado_count > 0 ) : ?>
								<section class="zsz-card-section zsz-card-section--listado">
									<h4 class="zsz-card-section-title"><?php esc_html_e( 'Acta / participantes (CSV)', 'zen-system-z' ); ?></h4>
									<p class="zsz-card-listado-meta">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of data rows */
												_n( '%d fila en el listado.', '%d filas en el listado.', $listado_count, 'zen-system-z' ),
												$listado_count
											)
										);
										?>
									</p>
									<details class="zsz-details">
										<summary class="zsz-details-summary"><?php esc_html_e( 'Ver contenido del listado', 'zen-system-z' ); ?></summary>
										<pre class="zsz-pre" tabindex="0"><?php echo esc_html( $listado ); ?></pre>
									</details>
								</section>
							<?php endif; ?>
						</div>
						<div class="zsz-card-actions">
							<?php if ( $pdf_url !== '' ) : ?>
								<a class="zsz-btn zsz-btn-download" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Descargar PDF', 'zen-system-z' ); ?>
								</a>
							<?php else : ?>
								<p class="zsz-card-note"><?php esc_html_e( 'No hay archivo PDF asociado en el registro.', 'zen-system-z' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php else : ?>
					<div class="zsz-card zsz-card-error" role="status">
						<p class="zsz-card-message"><?php esc_html_e( 'Código no encontrado.', 'zen-system-z' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
