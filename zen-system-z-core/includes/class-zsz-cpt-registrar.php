<?php
/**
 * Registro de CPTs y meta boxes (capa de datos).
 *
 * @package Zen_System_Z
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT certificado y certificado_grupal + meta del individual.
 */
class ZSZ_CPT_Registrar {

	/**
	 * Engancha hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_certificado', array( $this, 'save_certificado_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_certificado_admin_assets' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_certificado_title_placeholder' ), 10, 2 );
	}

	/**
	 * Estilos del formulario de certificado en el escritorio.
	 *
	 * @param string $hook_suffix Pantalla actual.
	 */
	public function enqueue_certificado_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'certificado' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style(
			'zsz-admin-certificado',
			ZSZ_URL . 'assets/css/zsz-admin-certificado.css',
			array(),
			ZSZ_VERSION
		);
	}

	/**
	 * Placeholder del campo título en certificados.
	 *
	 * @param string  $text Texto por defecto.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function filter_certificado_title_placeholder( $text, $post ) {
		if ( $post instanceof WP_Post && 'certificado' === $post->post_type ) {
			return __( 'Folio o identificador visible (p. ej. coincide con código o título legado)', 'zen-system-z' );
		}
		return $text;
	}

	/**
	 * Registra los tipos de contenido (nombres exactos para datos existentes).
	 */
	public function register_post_types() {
		$labels_individual = array(
			'name'               => __( 'Certificados Individuales', 'zen-system-z' ),
			'singular_name'      => __( 'Certificado Individual', 'zen-system-z' ),
			'menu_name'          => __( 'Certificados Individuales', 'zen-system-z' ),
			'add_new_item'       => __( 'Añadir certificado individual', 'zen-system-z' ),
			'edit_item'          => __( 'Editar certificado individual', 'zen-system-z' ),
			'all_items'          => __( 'Todos los certificados individuales', 'zen-system-z' ),
			'search_items'       => __( 'Buscar certificados', 'zen-system-z' ),
			'not_found'          => __( 'No se encontraron certificados', 'zen-system-z' ),
		);

		register_post_type(
			'certificado',
			array(
				'labels'             => $labels_individual,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'has_archive'        => false,
				'supports'           => array( 'title', 'custom-fields' ),
				'menu_icon'          => 'dashicons-awards',
				'rewrite'            => array( 'slug' => 'certificados' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
			)
		);

		$labels_grupal = array(
			'name'               => __( 'Certificados Grupales', 'zen-system-z' ),
			'singular_name'      => __( 'Certificado Grupal', 'zen-system-z' ),
			'menu_name'          => __( 'Certificados Grupales', 'zen-system-z' ),
			'add_new_item'       => __( 'Añadir certificado grupal', 'zen-system-z' ),
			'edit_item'          => __( 'Editar certificado grupal', 'zen-system-z' ),
			'all_items'          => __( 'Todos los certificados grupales', 'zen-system-z' ),
			'search_items'       => __( 'Buscar certificados grupales', 'zen-system-z' ),
			'not_found'          => __( 'No se encontraron certificados grupales', 'zen-system-z' ),
		);

		register_post_type(
			'certificado_grupal',
			array(
				'labels'             => $labels_grupal,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'has_archive'        => false,
				'supports'           => array( 'title', 'custom-fields' ),
				'menu_icon'          => 'dashicons-groups',
				'rewrite'            => array( 'slug' => 'certificados-grupales' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
			)
		);
	}

	/**
	 * Meta box en certificado individual.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'zsz_certificado_datos',
			__( 'Datos del certificado', 'zen-system-z' ),
			array( $this, 'render_meta_box_certificado' ),
			'certificado',
			'normal',
			'high'
		);
	}

	/**
	 * Meta alineada con wp_postmeta del plugin anterior (compatibilidad registros existentes).
	 *
	 * @param WP_Post $post Post actual.
	 */
	public function render_meta_box_certificado( $post ) {
		wp_nonce_field( 'zsz_save_certificado_meta', 'zsz_certificado_nonce' );

		$codigo               = get_post_meta( $post->ID, '_certificado_codigo', true );
		$nombre               = get_post_meta( $post->ID, '_certificado_participante', true );
		$curso                = get_post_meta( $post->ID, '_certificado_curso', true );
		$fecha                = get_post_meta( $post->ID, '_certificado_fecha', true );
		$empresa              = get_post_meta( $post->ID, '_certificado_empresa', true );
		$pdf_url              = get_post_meta( $post->ID, '_certificado_pdf_url', true );
		$duracion             = get_post_meta( $post->ID, '_certificado_duracion', true );
		$fecha_realizacion    = get_post_meta( $post->ID, '_certificado_fecha_realizacion', true );
		$fecha_expiracion     = get_post_meta( $post->ID, '_certificado_fecha_expiracion', true );
		$oc_cliente           = get_post_meta( $post->ID, '_certificado_oc_cliente', true );
		$listado_participantes  = get_post_meta( $post->ID, '_certificado_listado_participantes', true );
		$director             = get_post_meta( $post->ID, '_certificado_director', true );
		$instructor           = get_post_meta( $post->ID, '_certificado_instructor', true );
		$firma_director_url   = get_post_meta( $post->ID, '_certificado_firma_director_url', true );
		$firma_instructor_url = get_post_meta( $post->ID, '_certificado_firma_instructor_url', true );

		?>
		<div class="zsz-cert-metabox">
		<div class="zsz-meta-section">
			<h3 class="zsz-meta-section-title">
				<span class="dashicons dashicons-id" aria-hidden="true"></span>
				<?php esc_html_e( 'Identificación y documento', 'zen-system-z' ); ?>
			</h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="zsz_certificado_codigo"><?php esc_html_e( 'Código', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_codigo" name="zsz_certificado_codigo"
								value="<?php echo esc_attr( $codigo ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_participante"><?php esc_html_e( 'Participante', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_participante" name="zsz_certificado_participante"
								value="<?php echo esc_attr( $nombre ); ?>" />
							<p class="description"><?php esc_html_e( 'Alumno principal del certificado (cuando no usás solo el listado grupal).', 'zen-system-z' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_curso"><?php esc_html_e( 'Curso', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_curso" name="zsz_certificado_curso"
								value="<?php echo esc_attr( $curso ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_fecha"><?php esc_html_e( 'Fecha de emisión', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_fecha" name="zsz_certificado_fecha"
								value="<?php echo esc_attr( $fecha ); ?>" placeholder="<?php echo esc_attr__( 'YYYY-MM-DD o texto', 'zen-system-z' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_empresa"><?php esc_html_e( 'Empresa', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_empresa" name="zsz_certificado_empresa"
								value="<?php echo esc_attr( $empresa ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_pdf_url"><?php esc_html_e( 'URL del PDF', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="zsz_certificado_pdf_url" name="zsz_certificado_pdf_url"
								value="<?php echo esc_attr( $pdf_url ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="zsz-meta-section">
			<h3 class="zsz-meta-section-title">
				<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Datos generales', 'zen-system-z' ); ?>
			</h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="zsz_certificado_duracion"><?php esc_html_e( 'Duración', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_duracion" name="zsz_certificado_duracion"
								value="<?php echo esc_attr( $duracion ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_fecha_realizacion"><?php esc_html_e( 'Fecha de realización', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_fecha_realizacion" name="zsz_certificado_fecha_realizacion"
								value="<?php echo esc_attr( $fecha_realizacion ); ?>" placeholder="<?php echo esc_attr__( 'YYYY-MM-DD', 'zen-system-z' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_fecha_expiracion"><?php esc_html_e( 'Fecha de expiración', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_fecha_expiracion" name="zsz_certificado_fecha_expiracion"
								value="<?php echo esc_attr( $fecha_expiracion ); ?>" placeholder="<?php echo esc_attr__( 'YYYY-MM-DD', 'zen-system-z' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_oc_cliente"><?php esc_html_e( 'Cliente', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_oc_cliente" name="zsz_certificado_oc_cliente"
								value="<?php echo esc_attr( $oc_cliente ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="zsz-meta-section zsz-meta-section--listado">
			<h3 class="zsz-meta-section-title">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<?php esc_html_e( 'Alumnos / listado (CSV)', 'zen-system-z' ); ?>
			</h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="zsz_certificado_listado_participantes"><?php esc_html_e( 'Listado', 'zen-system-z' ); ?></label></th>
						<td>
							<strong class="zsz-csv-legend"><?php echo esc_html( __( 'Nombre, RUT, Asistencia, Nota T, Nota S, Nota Final, Aprobación', 'zen-system-z' ) ); ?></strong>
							<textarea class="large-text code zsz-csv-editor" rows="14" cols="50" id="zsz_certificado_listado_participantes"
								name="zsz_certificado_listado_participantes" spellcheck="false"
								placeholder="<?php echo esc_attr__( 'Una fila por alumno, valores separados por coma…', 'zen-system-z' ); ?>"><?php echo esc_textarea( $listado_participantes ); ?></textarea>
							<p class="zsz-meta-hint">
								<?php
								echo esc_html(
									sprintf(
										/* translators: CSV column names hint */
										__( 'Formato CSV por fila, columnas en este orden: %s', 'zen-system-z' ),
										'Nombre, RUT, Asistencia, Nota T, Nota S, Nota Final, Aprobación'
									)
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="zsz-meta-section">
			<h3 class="zsz-meta-section-title">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Firmas', 'zen-system-z' ); ?>
			</h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="zsz_certificado_director"><?php esc_html_e( 'Nombre director', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_director" name="zsz_certificado_director"
								value="<?php echo esc_attr( $director ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_firma_director_url"><?php esc_html_e( 'URL firma director (PNG)', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="zsz_certificado_firma_director_url" name="zsz_certificado_firma_director_url"
								value="<?php echo esc_attr( $firma_director_url ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_instructor"><?php esc_html_e( 'Nombre relator', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="zsz_certificado_instructor" name="zsz_certificado_instructor"
								value="<?php echo esc_attr( $instructor ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zsz_certificado_firma_instructor_url"><?php esc_html_e( 'URL firma relator (PNG)', 'zen-system-z' ); ?></label></th>
						<td>
							<input type="url" class="large-text" id="zsz_certificado_firma_instructor_url" name="zsz_certificado_firma_instructor_url"
								value="<?php echo esc_attr( $firma_instructor_url ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		</div>
		<?php
	}

	/**
	 * Persistencia segura de meta.
	 *
	 * @param int     $post_id ID del post.
	 * @param WP_Post $post    Objeto post.
	 */
	public function save_certificado_meta( $post_id, $post ) {
		if ( ! isset( $_POST['zsz_certificado_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zsz_certificado_nonce'] ) ), 'zsz_save_certificado_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( 'certificado' !== $post->post_type ) {
			return;
		}

		$text_fields = array(
			'zsz_certificado_codigo'              => '_certificado_codigo',
			'zsz_certificado_participante'        => '_certificado_participante',
			'zsz_certificado_curso'               => '_certificado_curso',
			'zsz_certificado_fecha'             => '_certificado_fecha',
			'zsz_certificado_empresa'             => '_certificado_empresa',
			'zsz_certificado_duracion'            => '_certificado_duracion',
			'zsz_certificado_fecha_realizacion'   => '_certificado_fecha_realizacion',
			'zsz_certificado_fecha_expiracion'    => '_certificado_fecha_expiracion',
			'zsz_certificado_oc_cliente'          => '_certificado_oc_cliente',
			'zsz_certificado_director'            => '_certificado_director',
			'zsz_certificado_instructor'          => '_certificado_instructor',
		);

		foreach ( $text_fields as $post_key => $meta_key ) {
			if ( ! isset( $_POST[ $post_key ] ) ) {
				continue;
			}
			update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
		}

		$url_fields = array(
			'zsz_certificado_pdf_url'             => '_certificado_pdf_url',
			'zsz_certificado_firma_director_url'    => '_certificado_firma_director_url',
			'zsz_certificado_firma_instructor_url' => '_certificado_firma_instructor_url',
		);

		foreach ( $url_fields as $post_key => $meta_key ) {
			if ( ! isset( $_POST[ $post_key ] ) ) {
				continue;
			}
			update_post_meta( $post_id, $meta_key, esc_url_raw( wp_unslash( $_POST[ $post_key ] ) ) );
		}

		if ( isset( $_POST['zsz_certificado_listado_participantes'] ) ) {
			update_post_meta(
				$post_id,
				'_certificado_listado_participantes',
				sanitize_textarea_field( wp_unslash( $_POST['zsz_certificado_listado_participantes'] ) )
			);
		}
	}
}
