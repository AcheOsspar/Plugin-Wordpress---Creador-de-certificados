<?php
/**
 * Plugin Name:       Zen Certificados (Versión Definitiva)
 * Description:       Plugin a medida para crear, gestionar, importar y validar certificados con PDFs y QR.
 * Version:           8.2 - Corrección de Pie de Página
 * Author:            Alain Ossandon
 */

if (!defined('ABSPATH')) exit;

// =============================================================================
// PARTE 1: CREAR EL CUSTOM POST TYPE "CERTIFICADO"
// =============================================================================
add_action('init', 'zc_final_crear_cpt_certificado');
function zc_final_crear_cpt_certificado() {
    $labels = array('name' => 'Certificados', 'singular_name' => 'Certificado', 'menu_name' => 'Certificados', 'add_new_item' => 'Añadir Nuevo Certificado', 'edit_item' => 'Editar Certificado', 'all_items' => 'Todos los Certificados');
    $args = array('labels' => $labels, 'public' => true, 'has_archive' => false, 'supports' => array('title'), 'menu_icon' => 'dashicons-awards', 'rewrite' => array('slug' => 'certificados'));
    register_post_type('certificado', $args);
}

// =============================================================================
// PARTE 2: CREAR LOS CAMPOS PERSONALIZADOS
// =============================================================================
add_action('add_meta_boxes', 'zc_final_agregar_campos_metabox');
function zc_final_agregar_campos_metabox() {
    add_meta_box('datos_certificado_metabox', 'Datos del Certificado', 'zc_final_mostrar_campos_html', 'certificado', 'normal', 'high');
}
function zc_final_mostrar_campos_html($post) {
    $codigo = get_post_meta($post->ID, '_certificado_codigo', true);
    $participante = get_post_meta($post->ID, '_certificado_participante', true);
    $curso = get_post_meta($post->ID, '_certificado_curso', true);
    $fecha = get_post_meta($post->ID, '_certificado_fecha', true);
    $director = get_post_meta($post->ID, '_certificado_director', true);
    $instructor = get_post_meta($post->ID, '_certificado_instructor', true);
    $pdf_url = get_post_meta($post->ID, '_certificado_pdf_url', true);
    wp_nonce_field('zc_final_guardar_datos', 'zc_final_nonce');
    echo '<p><label><strong>Código de Verificación:</strong><br><input type="text" name="certificado_codigo" value="' . esc_attr($codigo) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Participante:</strong><br><input type="text" name="certificado_participante" value="' . esc_attr($participante) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Curso:</strong><br><input type="text" name="certificado_curso" value="' . esc_attr($curso) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Fecha de Emisión:</strong><br><input type="date" name="certificado_fecha" value="' . esc_attr($fecha) . '"></label></p>';
    echo '<h3>Firmas</h3>';
    echo '<p><label><strong>Nombre del Director:</strong><br><input type="text" name="certificado_director" value="' . esc_attr($director) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Instructor:</strong><br><input type="text" name="certificado_instructor" value="' . esc_attr($instructor) . '" style="width:100%;"></label></p>';
    echo '<hr>';
    echo '<p style="background-color: #f0f6fc; padding: 15px; border-radius: 4px;"><label><strong>URL del PDF (Automático):</strong><br><input type="url" value="' . esc_attr($pdf_url) . '" style="width:100%; background-color: #eee;" readonly></label></p>';
}
add_action('save_post_certificado', 'zc_final_guardar_datos', 10, 2);
function zc_final_guardar_datos($post_id, $post) {
    if (!isset($_POST['zc_final_nonce']) || !wp_verify_nonce($_POST['zc_final_nonce'], 'zc_final_guardar_datos')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['certificado_codigo'])) update_post_meta($post_id, '_certificado_codigo', sanitize_text_field($_POST['certificado_codigo']));
    if (isset($_POST['certificado_participante'])) update_post_meta($post_id, '_certificado_participante', sanitize_text_field($_POST['certificado_participante']));
    if (isset($_POST['certificado_curso'])) update_post_meta($post_id, '_certificado_curso', sanitize_text_field($_POST['certificado_curso']));
    if (isset($_POST['certificado_fecha'])) update_post_meta($post_id, '_certificado_fecha', sanitize_text_field($_POST['certificado_fecha']));
    if (isset($_POST['certificado_director'])) update_post_meta($post_id, '_certificado_director', sanitize_text_field($_POST['certificado_director']));
    if (isset($_POST['certificado_instructor'])) update_post_meta($post_id, '_certificado_instructor', sanitize_text_field($_POST['certificado_instructor']));
}

// =============================================================================
// PARTE 3: COLUMNAS PERSONALIZADAS EN EL ADMIN
// =============================================================================
add_filter('manage_certificado_posts_columns', 'zc_final_añadir_columnas');
function zc_final_añadir_columnas($columns) { $new_columns = array(); $new_columns['cb'] = $columns['cb']; $new_columns['title'] = 'Título de Referencia'; $new_columns['participante'] = 'Participante'; $new_columns['curso'] = 'Curso'; $new_columns['codigo'] = 'Código'; $new_columns['fecha'] = 'Fecha de Emisión'; return $new_columns; }
add_action('manage_certificado_posts_custom_column', 'zc_final_mostrar_columnas', 10, 2);
function zc_final_mostrar_columnas($column_name, $post_id) { switch ($column_name) { case 'participante': echo esc_html(get_post_meta($post_id, '_certificado_participante', true)); break; case 'curso': echo esc_html(get_post_meta($post_id, '_certificado_curso', true)); break; case 'codigo': echo esc_html(get_post_meta($post_id, '_certificado_codigo', true)); break; case 'fecha': echo esc_html(get_post_meta($post_id, '_certificado_fecha', true)); break; } }
add_filter('manage_edit-certificado_sortable_columns', 'zc_final_hacer_columnas_sortables' );
function zc_final_hacer_columnas_sortables($columns) { $columns['codigo'] = '_certificado_codigo'; $columns['fecha'] = '_certificado_fecha'; $columns['participante'] = '_certificado_participante'; return $columns; }
add_action('pre_get_posts', 'zc_final_ordenar_por_columnas_personalizadas');
function zc_final_ordenar_por_columnas_personalizadas($query) { if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'certificado') return; $orderby = $query->get('orderby'); if ('_certificado_codigo' === $orderby || '_certificado_fecha' === $orderby || '_certificado_participante' === $orderby) { $query->set('meta_key', $orderby); $query->set('orderby', 'meta_value'); } }

// =============================================================================
// PARTE 4: IMPORTADOR DE CSV
// =============================================================================
add_action('admin_menu', 'zc_final_agregar_pagina_importador');
function zc_final_agregar_pagina_importador() { add_submenu_page('edit.php?post_type=certificado', 'Importar Certificados', 'Importar', 'manage_options', 'zc-importador-certificados', 'zc_final_mostrar_pagina_importador'); }
function zc_final_mostrar_pagina_importador() { echo '<div class="wrap"><h1>Importar Certificados desde CSV</h1>'; if (isset($_POST['zc_import_nonce']) && wp_verify_nonce($_POST['zc_import_nonce'], 'zc_import_action')) { if (!empty($_FILES['csv_file']['tmp_name'])) { $csv_file = $_FILES['csv_file']['tmp_name']; $file_handle = fopen($csv_file, 'r'); $headers = fgetcsv($file_handle, 1024, ','); $header_map = array_flip($headers); $importados = 0; $errores = 0; while (($row = fgetcsv($file_handle, 1024, ',')) !== FALSE) { $data = array(); foreach($header_map as $key => $index) { if (isset($row[$index])) { $data[$key] = $row[$index]; } else { $data[$key] = ''; }} $post_data = array('post_title' => sanitize_text_field($data['titulo']), 'post_type' => 'certificado', 'post_status' => 'publish'); $post_id = wp_insert_post($post_data); if ($post_id > 0) { update_post_meta($post_id, '_certificado_codigo', sanitize_text_field($data['codigo'])); update_post_meta($post_id, '_certificado_participante', sanitize_text_field($data['participante'])); update_post_meta($post_id, '_certificado_curso', sanitize_text_field($data['curso'])); update_post_meta($post_id, '_certificado_fecha', sanitize_text_field($data['fecha'])); $importados++; } else { $errores++; } } fclose($file_handle); echo '<div class="notice notice-success is-dismissible"><p><strong>Proceso completado:</strong> ' . $importados . ' certificados importados. ' . $errores . ' errores.</p></div>'; } else { echo '<div class="notice notice-error is-dismissible"><p>Por favor, selecciona un archivo CSV.</p></div>';} } echo '<p>Sube un archivo CSV con las columnas: <strong>titulo, codigo, participante, curso, fecha</strong>.</p><form method="post" enctype="multipart/form-data">'; wp_nonce_field('zc_import_action', 'zc_import_nonce'); echo '<table class="form-table"><tr valign="top"><th scope="row"><label for="csv_file">Archivo CSV:</label></th><td><input type="file" id="csv_file" name="csv_file" accept=".csv" required></td></tr></table>'; submit_button('Subir e Importar Certificados'); echo '</form></div>'; }

// =============================================================================
// PARTE 5: SHORTCODE DE VERIFICACIÓN
// =============================================================================
add_shortcode('verificador_de_certificados', 'zc_final_funcion_verificadora');
function zc_final_funcion_verificadora() { ob_start(); if (isset($_GET['id']) && !empty($_GET['id'])) { $codigo_certificado = sanitize_text_field($_GET['id']); $args = array('post_type' => 'certificado', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_certificado_codigo', 'value' => $codigo_certificado, 'compare' => '='))); $query = new WP_Query($args); if ($query->have_posts()) { while ($query->have_posts()) { $query->the_post(); $participante = get_post_meta(get_the_ID(), '_certificado_participante', true); $curso = get_post_meta(get_the_ID(), '_certificado_curso', true); $fecha = get_post_meta(get_the_ID(), '_certificado_fecha', true); $pdf_url = get_post_meta(get_the_ID(), '_certificado_pdf_url', true); echo '<div style="border: 2px solid #84BC41; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h2 style="color: #84BC41; font-family: \'Open Sans\', sans-serif;">✅ Certificado Válido</h2>'; echo '<p><strong>Participante:</strong> ' . esc_html($participante) . '</p>'; echo '<p><strong>Curso:</strong> ' . esc_html($curso) . '</p>'; echo '<p><strong>Fecha de Emisión:</strong> ' . esc_html($fecha) . '</p>'; echo '<p><strong>Código de Verificación:</strong> ' . esc_html($codigo_certificado) . '</p>'; if (!empty($pdf_url)) { echo '<hr style="margin: 20px 0;">'; echo '<a href="' . esc_url($pdf_url) . '" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #84BC41; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 20px; font-weight: bold;">Descargar Certificado en PDF</a>'; echo '<h4 style="margin-top: 10px; font-family: \'Open Sans\', sans-serif;">Visualización del Certificado</h4>'; $google_viewer_url = 'https://docs.google.com/gview?url=' . urlencode($pdf_url) . '&embedded=true'; echo '<iframe src="' . esc_url($google_viewer_url) . '" width="100%" style="border: 1px solid #ddd; aspect-ratio: 1 / 1.414;"></iframe>'; } echo '</div>'; } } else { echo '<div style="border: 2px solid #F44336; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h2 style="color: #F44336;">❌ Certificado no encontrado o inválido</h2><p>El código de verificación "' . esc_html($codigo_certificado) . '" no es válido.</p></div>'; } wp_reset_postdata(); } echo '<div style="padding: 20px; border: 1px solid #ccc; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h3 style="font-family: \'Open Sans\', sans-serif;">Formulario de Verificación</h3><p>Ingrese el código del certificado para verificar su validez.</p>'; echo '<form action="' . esc_url(get_permalink()) . '" method="get"><p><label for="id">Número de Certificado:</label><br><input type="text" id="id" name="id" value="" style="width: 100%; padding: 8px;" required></p>'; echo '<p><input type="submit" value="Verificar Certificado" style="padding: 10px 15px; background-color: #84BC41; color: white; border: none; cursor: pointer; font-weight: bold;"></p></form></div>'; return ob_get_clean(); }

// =============================================================================
// PARTE 6: AUTOMATIZACIÓN DE PDF CON TCPDF (VERSIÓN FINAL CON FONDO PNG)
// =============================================================================
add_action('wp_after_insert_post', 'zc_final_generar_pdf', 20, 2);
function zc_final_generar_pdf($post_id, $post) {
    if ($post->post_type !== 'certificado' || ($post->post_status !== 'publish' && $post->post_status !== 'draft')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $tcpdf_path = plugin_dir_path(__FILE__) . 'TCPDF/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        update_post_meta($post_id, '_certificado_pdf_url', 'Error: Libreria TCPDF no encontrada.');
        return;
    }
    require_once $tcpdf_path;

    // Obtener datos
    $codigo = get_post_meta($post_id, '_certificado_codigo', true);
    $participante = get_post_meta($post_id, '_certificado_participante', true);
    $curso = get_post_meta($post_id, '_certificado_curso', true);
    $fecha = get_post_meta($post_id, '_certificado_fecha', true);
    $director = get_post_meta($post_id, '_certificado_director', true) ?: 'Nombre Director'; // Valor por defecto si está vacío
    $instructor = get_post_meta($post_id, '_certificado_instructor', true) ?: 'Nombre Instructor'; // Valor por defecto
    $verification_url = home_url('/pagina-de-verificacion-test/?id=' . urlencode($codigo));

    // --- DISEÑO DEL CERTIFICADO ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(25, 20, 25);
    $pdf->AddPage();
        $font = 'opensans';
    
    // --- INICIO: AÑADIR IMAGEN DE FONDO (ÚNICO CAMBIO) ---
    $imagen_fondo_url = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/hoja-membretada-a4.png'; // URL de la imagen de fondo
    if ($imagen_fondo_url) {
        $pdf->Image($imagen_fondo_url, 0, 0, 210, 297, '', '', '', false, 300, 'C', false, false, 0);
    }
    // --- FIN: AÑADIR IMAGEN DE FONDO ---

    // El resto de este código es EXACTAMENTE el que tú me pasaste.
    // Dibuja la cabecera verde, el logo, textos, firmas y QR sobre el fondo.
    $headerAlto = 25;
    $logoAncho = 22; // Tu ajuste
    $logoAlto = 22;  // Tu ajuste
    $logoUrl = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Logo-zen-v2-02-1.png'; // URL del logo corregida
    $pdf->SetFillColor(132, 188, 65);
    $pdf->Rect(0, 0, $pdf->getPageWidth(), $headerAlto, 'F');
    $logoX = ($pdf->getPageWidth() - $logoAncho) / 2;
    $logoY = ($headerAlto - $logoAlto) / 2;
    if ($logoUrl) { $pdf->Image($logoUrl, $logoX, $logoY, $logoAncho, $logoAlto, 'PNG'); }
    
    $pdf->SetY($headerAlto + 20);
    $pdf->SetFont($font, 'B', 24);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->Cell(0, 15, 'Certificado de Logro', 0, 1, 'L'); // Tu texto
    $pdf->Line(25, $pdf->GetY() + 2, 185, $pdf->GetY() + 2);
    $pdf->Ln(15);

    $pdf->SetFont($font, 'B', 22);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 15, $participante, 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 10, 'ha completado satisfactoriamente el curso de:', 0, 1, 'L'); // Tu texto
    $pdf->SetFont($font, 'B', 16);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->MultiCell(0, 10, $curso, 0, 'L');
    $pdf->Ln(5);
    
    $pdf->SetFont($font, '', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->MultiCell(0, 5, "Este certificado se otorga al titular por haber completado con éxito el curso, alcanzando las competencias y habilidades impartidas.", 0, 'L'); // Tu texto
    $pdf->Ln(40); // Espacio generoso antes de las firmas
    
    // --- SECCIÓN DE FIRMAS (CON NOMBRES Y LÍNEAS) ---
    $pdf->SetFont($font, 'B', 12);
    $pdf->SetTextColor(50, 50, 50);

    // Nombre del Director
    $pdf->SetX(25);
    $pdf->Cell(60, 10, $director, 0, 0, 'C');

    // Nombre del Instructor
    $pdf->SetX(125);
    $pdf->Cell(60, 10, $instructor, 0, 1, 'C');

    // Líneas de firma
    $yPositionFirmas = $pdf->GetY();
    $pdf->Line(25, $yPositionFirmas, 85, $yPositionFirmas);
    $pdf->Line(125, $yPositionFirmas, 185, $yPositionFirmas);
    $pdf->Ln(2);

    // Títulos debajo de la línea
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX(25);
    $pdf->Cell(60, 10, 'Firma Director', 0, 0, 'C');
    $pdf->SetX(125);
    $pdf->Cell(60, 10, 'Firma Instructor', 0, 1, 'C');
    
    // --- PIE DE PÁGINA CON TEXTO Y QR (POSICIÓN CORREGIDA Y ALINEADA) ---
    $yPositionFooter = 220; // Posición Y fija para el inicio de este bloque
    $pdf->SetY($yPositionFooter); 
    
    $yPositionForQr = $pdf->GetY();
    
    $pdf->SetFont($font, 'B', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Código de validación:', 0, 1, 'L'); // Tu texto
    $pdf->SetFont($font, '', 9);
    $pdf->Cell(0, 5, $codigo, 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont($font, 'B', 9);
    $pdf->Cell(0, 5, 'Verifica este certificado en:', 0, 1, 'L'); // Tu texto
    $pdf->SetFont($font, 'U', 9);
    $pdf->SetTextColor(40, 80, 150);
    $pdf->Cell(0, 5, home_url('/pagina-de-verificacion-test/'), 0, 1, 'L', false, home_url('/pagina-de-verificacion-test/'));
    
    $qrSize = 35;
    $qrX = 150;
    $pdf->write2DBarcode($verification_url, 'QRCODE,M', $qrX, $yPositionForQr, $qrSize, $qrSize);
    
    // --- FIN DEL DISEÑO ---
    $pdf_content = $pdf->Output('', 'S');
    
    // Subir a Medios y guardar URL
    $file_name = 'certificado-' . sanitize_title($participante) . '-' . $post_id . '.pdf';
    $upload = wp_upload_bits($file_name, null, $pdf_content);
    if (empty($upload['error'])) {
        $file_url = $upload['url'];
        remove_action('wp_after_insert_post', 'zc_final_generar_pdf', 20);
        update_post_meta($post_id, '_certificado_pdf_url', $file_url);
        add_action('wp_after_insert_post', 'zc_final_generar_pdf', 20, 2);
    }
}

// =============================================================================
// PARTE 7: ENCOLAR HOJA DE ESTILOS DE GOOGLE FONTS
// =============================================================================
function zc_final_enqueue_styles() {
    wp_enqueue_style('zc-final-google-fonts', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'zc_final_enqueue_styles');

