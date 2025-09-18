<?php
/**
 * Plugin Name:       Zen Certificados (Generador de Certificados)
 * Description:       Plugin a medida para crear, gestionar, importar y validar certificados con PDFs y QR.
 * Version:           8.5 - Corrige error fatal en activación
 * Author:            Alain Ossandon
 */

if (!defined('ABSPATH')) exit;

// Carga global de la librería TCPDF para que esté disponible en todo el plugin
$tcpdf_path = plugin_dir_path(__FILE__) . 'TCPDF/tcpdf.php';
if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;
}

/*
================================================================
🔒 SECCIÓN 1: CERTIFICADOS INDIVIDUALES - NO MODIFICAR
================================================================
Esta sección contiene el código original que funciona perfectamente.
Incluye: CPT 'certificado', meta boxes, generación PDF individual
⚠️ NO TOCAR ESTA SECCIÓN - ESTÁ FUNCIONANDO CORRECTAMENTE
================================================================
*/

// =============================================================================
// PARTE 1: CREAR EL CUSTOM POST TYPE "CERTIFICADO" (INDIVIDUAL)
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
    // Campos existentes
    $codigo = get_post_meta($post->ID, '_certificado_codigo', true);
    $curso = get_post_meta($post->ID, '_certificado_curso', true);
    $fecha = get_post_meta($post->ID, '_certificado_fecha', true);
    $empresa = get_post_meta($post->ID, '_certificado_empresa', true);
    $director = get_post_meta($post->ID, '_certificado_director', true);
    $instructor = get_post_meta($post->ID, '_certificado_instructor', true);
    
    // Nuevos campos
    $duracion = get_post_meta($post->ID, '_certificado_duracion', true);
    $fecha_realizacion = get_post_meta($post->ID, '_certificado_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post->ID, '_certificado_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post->ID, '_certificado_oc_cliente', true);
    $listado_participantes = get_post_meta($post->ID, '_certificado_listado_participantes', true);

    // URLs de PDFs generados
    $pdf_url = get_post_meta($post->ID, '_certificado_pdf_url', true);
    $diploma_url = get_post_meta($post->ID, '_certificado_diploma_url', true);
    
    wp_nonce_field('zc_final_guardar_datos', 'zc_final_nonce');

    // Se mantiene el campo de participante individual, que ahora puede ser opcional o usarse para el diploma
    $participante = get_post_meta($post->ID, '_certificado_participante', true);
    echo '<h3>Datos Generales</h3>';
    echo '<p><label><strong>Código de Verificación:</strong><br><input type="text" name="certificado_codigo" value="' . esc_attr($codigo) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre de la Empresa:</strong><br><input type="text" name="certificado_empresa" value="' . esc_attr($empresa) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Curso:</strong><br><input type="text" name="certificado_curso" value="' . esc_attr($curso) . '" style="width:100%;"></label></p>';
    
    echo '<h3>Datos del Curso (para Certificado de Asistencia)</h3>';
    echo '<p><label><strong>Duración (ej: 03 horas cronológicas):</strong><br><input type="text" name="certificado_duracion" value="' . esc_attr($duracion) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Fecha de Emisión:</strong><br><input type="date" name="certificado_fecha" value="' . esc_attr($fecha) . '"></label></p>';
    echo '<p><label><strong>Fecha de Realización:</strong><br><input type="date" name="certificado_fecha_realizacion" value="' . esc_attr($fecha_realizacion) . '"></label></p>';
    echo '<p><label><strong>Fecha de Expiración:</strong><br><input type="date" name="certificado_fecha_expiracion" value="' . esc_attr($fecha_expiracion) . '"></label></p>';
    echo '<p><label><strong>O.C. Cliente:</strong><br><input type="text" name="certificado_oc_cliente" value="' . esc_attr($oc_cliente) . '" style="width:100%;"></label></p>';

    echo '<h3>Participante (individual)</h3>';
    echo '<p><label for="listado_participantes"><strong>Participantes:</strong></label><br>';
    echo '<textarea name="certificado_listado_participantes" id="listado_participantes" rows="10" style="width:100%; font-family: monospace;">' . esc_textarea($listado_participantes) . '</textarea>';
    echo '<p class="description"><strong>Instrucciones:</strong> Ingrese un participante por línea. Use comas (,) para separar las columnas en el siguiente orden:<br><code>Nombre Completo,RUT,Asistencia,Nota T.,Nota S.,Nota Final,Aprobación</code><br>Ejemplo: <code>Apolinar Andrés Mendoza Cuno,22.626.949-9,100%,7.0,7.0,7.0,A</code></p>';

    echo '<h3>Datos para Diploma Individual (Opcional)</h3>';
    echo '<p><label><strong>Nombre del Participante (para diploma individual):</strong><br><input type="text" name="certificado_participante" value="' . esc_attr($participante) . '" style="width:100%;"></label></p>';
    echo '<p class="description">Este nombre se usará para generar el diploma individual. Si el certificado es solo para la empresa, puede dejarlo vacío.</p>';

    echo '<h3>Firmas</h3>';
    echo '<p><label><strong>Nombre del Director:</strong><br><input type="text" name="certificado_director" value="' . esc_attr($director) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Instructor:</strong><br><input type="text" name="certificado_instructor" value="' . esc_attr($instructor) . '" style="width:100%;"></label></p>';
    
    echo '<hr>';
    echo '<h3>URLs de Documentos Generados</h3>';
    echo '<p style="background-color: #f0f6fc; padding: 15px; border-radius: 4px;"><label><strong>URL del Certificado (Automático):</strong><br><input type="url" value="' . esc_attr($pdf_url) . '" style="width:100%; background-color: #eee;" readonly></label></p>';
    echo '<p style="background-color: #eaf2fa; padding: 15px; border-radius: 4px;"><label><strong>URL del Diploma (Automático):</strong><br><input type="url" value="' . esc_attr($diploma_url) . '" style="width:100%; background-color: #eee;" readonly></label></p>';
}
add_action('save_post_certificado', 'zc_final_guardar_datos', 10, 2);
function zc_final_guardar_datos($post_id, $post) {
    if (!isset($_POST['zc_final_nonce']) || !wp_verify_nonce($_POST['zc_final_nonce'], 'zc_final_guardar_datos')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Campos existentes
    if (isset($_POST['certificado_codigo'])) update_post_meta($post_id, '_certificado_codigo', sanitize_text_field($_POST['certificado_codigo']));
    if (isset($_POST['certificado_participante'])) update_post_meta($post_id, '_certificado_participante', sanitize_text_field($_POST['certificado_participante']));
    if (isset($_POST['certificado_curso'])) update_post_meta($post_id, '_certificado_curso', sanitize_text_field($_POST['certificado_curso']));
    if (isset($_POST['certificado_fecha'])) update_post_meta($post_id, '_certificado_fecha', sanitize_text_field($_POST['certificado_fecha']));
    if (isset($_POST['certificado_empresa'])) update_post_meta($post_id, '_certificado_empresa', sanitize_text_field($_POST['certificado_empresa']));
    if (isset($_POST['certificado_director'])) update_post_meta($post_id, '_certificado_director', sanitize_text_field($_POST['certificado_director']));
    if (isset($_POST['certificado_instructor'])) update_post_meta($post_id, '_certificado_instructor', sanitize_text_field($_POST['certificado_instructor']));

    // Nuevos campos
    if (isset($_POST['certificado_duracion'])) update_post_meta($post_id, '_certificado_duracion', sanitize_text_field($_POST['certificado_duracion']));
    if (isset($_POST['certificado_fecha_realizacion'])) update_post_meta($post_id, '_certificado_fecha_realizacion', sanitize_text_field($_POST['certificado_fecha_realizacion']));
    if (isset($_POST['certificado_fecha_expiracion'])) update_post_meta($post_id, '_certificado_fecha_expiracion', sanitize_text_field($_POST['certificado_fecha_expiracion']));
    if (isset($_POST['certificado_oc_cliente'])) update_post_meta($post_id, '_certificado_oc_cliente', sanitize_text_field($_POST['certificado_oc_cliente']));
    if (isset($_POST['certificado_listado_participantes'])) update_post_meta($post_id, '_certificado_listado_participantes', sanitize_textarea_field($_POST['certificado_listado_participantes']));
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
function zc_final_ordenar_por_columnas_personalizadas($query) { 
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'certificado') return; 
    $orderby = $query->get('orderby'); 
    if ('_certificado_codigo' === $orderby || '_certificado_fecha' === $orderby || '_certificado_participante' === $orderby) { 
        $query->set('meta_key', $orderby); $query->set('orderby', 'meta_value'); 
    } 
}

// =============================================================================
// PARTE 4: IMPORTADOR DE CSV
// =============================================================================
add_action('admin_menu', 'zc_final_agregar_pagina_importador');
function zc_final_agregar_pagina_importador() { add_submenu_page('edit.php?post_type=certificado', 'Importar Certificados', 'Importar', 'manage_options', 'zc-importador-certificados', 'zc_final_mostrar_pagina_importador'); }
function zc_final_mostrar_pagina_importador() { 
    echo '<div class="wrap"><h1>Importar Certificados desde CSV</h1>'; 
    if (isset($_POST['zc_import_nonce']) && wp_verify_nonce($_POST['zc_import_nonce'], 'zc_import_action')) { 
        if (!empty($_FILES['csv_file']['tmp_name'])) { 
            $csv_file = $_FILES['csv_file']['tmp_name']; 
            $file_handle = fopen($csv_file, 'r'); 
            $headers = fgetcsv($file_handle, 1024, ','); 
            $header_map = array_flip($headers); 
            $importados = 0; 
            $errores = 0; 
            while (($row = fgetcsv($file_handle, 1024, ',')) !== FALSE) { 
                $data = array(); 
                foreach($header_map as $key => $index) { 
                    if (isset($row[$index])) { 
                        $data[$key] = $row[$index]; 
                    } else { 
                        $data[$key] = ''; 
                    }
                } 
                $post_data = array(
                    'post_title' => isset($data['titulo']) ? sanitize_text_field($data['titulo']) : 'Certificado sin título', 
                    'post_type' => 'certificado', 
                    'post_status' => 'publish'
                ); 
                $post_id = wp_insert_post($post_data); 
                if ($post_id > 0) { 
                    if (isset($data['codigo'])) update_post_meta($post_id, '_certificado_codigo', sanitize_text_field($data['codigo'])); 
                    if (isset($data['participante'])) update_post_meta($post_id, '_certificado_participante', sanitize_text_field($data['participante'])); 
                    if (isset($data['curso'])) update_post_meta($post_id, '_certificado_curso', sanitize_text_field($data['curso'])); 
                    if (isset($data['fecha'])) update_post_meta($post_id, '_certificado_fecha', sanitize_text_field($data['fecha'])); 
                    if (isset($data['director'])) update_post_meta($post_id, '_certificado_director', sanitize_text_field($data['director'])); 
                    if (isset($data['instructor'])) update_post_meta($post_id, '_certificado_instructor', sanitize_text_field($data['instructor'])); 
                    
                    // Forzar la (re)generación de ambos PDFs con los datos completos
                    $post_object = get_post($post_id);
                    if ($post_object) {
                        zc_final_generar_pdf($post_id, $post_object);
                        zc_final_generar_diploma($post_id, $post_object);
                    }

                    $importados++; 
                } else { 
                    $errores++; 
                } 
            } 
            fclose($file_handle); 
            echo '<div class="notice notice-success is-dismissible"><p><strong>Proceso completado:</strong> ' . $importados . ' certificados importados. ' . $errores . ' errores.</p></div>'; 
        } else { 
            echo '<div class="notice notice-error is-dismissible"><p>Por favor, selecciona un archivo CSV.</p></div>';
        } 
    } 
    echo '<p>Sube un archivo CSV con las columnas: <strong>titulo, codigo, participante, curso, fecha, director, instructor</strong>.</p>'; 
    echo '<form method="post" enctype="multipart/form-data">'; 
    wp_nonce_field('zc_import_action', 'zc_import_nonce'); 
    echo '<table class="form-table"><tr valign="top"><th scope="row"><label for="csv_file">Archivo CSV:</label></th><td><input type="file" id="csv_file" name="csv_file" accept=".csv" required></td></tr></table>'; 
    submit_button('Subir e Importar Certificados'); 
    echo '</form></div>'; 
}

// =============================================================================
// PARTE 5: SHORTCODE DE VERIFICACIÓN (EXPANDIDO PARA GRUPALES)
// =============================================================================
add_shortcode('verificador_de_certificados', 'zc_final_funcion_verificadora');
function zc_final_funcion_verificadora() { 
    ob_start(); 
    
    if (isset($_GET['id']) && !empty($_GET['id'])) { 
        $codigo_certificado = sanitize_text_field($_GET['id']); 
        
        // 🎯 PASO 1: Buscar primero en certificados individuales
        $args_individual = array(
            'post_type' => 'certificado', 
            'posts_per_page' => 1, 
            'meta_query' => array(
                array(
                    'key' => '_certificado_codigo', 
                    'value' => $codigo_certificado, 
                    'compare' => '='
                )
            )
        ); 
        $query_individual = new WP_Query($args_individual); 
        
        if ($query_individual->have_posts()) {
            // ✅ CERTIFICADO INDIVIDUAL ENCONTRADO
            while ($query_individual->have_posts()) { 
                $query_individual->the_post(); 
                $participante = get_post_meta(get_the_ID(), '_certificado_participante', true); 
                $curso = get_post_meta(get_the_ID(), '_certificado_curso', true); 
                $fecha = get_post_meta(get_the_ID(), '_certificado_fecha', true); 
                $pdf_url = get_post_meta(get_the_ID(), '_certificado_pdf_url', true); 
                $diploma_url = get_post_meta(get_the_ID(), '_certificado_diploma_url', true); 
                
                echo '<div style="border: 2px solid #84BC41; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h2 style="color: #84BC41; font-family: \'Open Sans\', sans-serif;">✅ Certificado Individual Válido</h2>'; 
                echo '<p><strong>Participante:</strong> ' . esc_html($participante) . '</p>'; 
                echo '<p><strong>Curso:</strong> ' . esc_html($curso) . '</p>'; 
                echo '<p><strong>Fecha de Emisión:</strong> ' . esc_html($fecha) . '</p>'; 
                echo '<p><strong>Código de Verificación:</strong> ' . esc_html($codigo_certificado) . '</p>'; 
                
                if (!empty($pdf_url) || !empty($diploma_url)) { 
                    echo '<hr style="margin: 20px 0;">'; 
                    if (!empty($pdf_url)) { 
                        echo '<a href="' . esc_url($pdf_url) . '" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #84BC41; color: white; text-decoration: none; border-radius: 4px; margin: 5px; font-weight: bold;">Descargar Certificado</a>'; 
                    } 
                    if (!empty($diploma_url)) { 
                        echo '<a href="' . esc_url($diploma_url) . '" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #337ab7; color: white; text-decoration: none; border-radius: 4px; margin: 5px; font-weight: bold;">Descargar Diploma</a>'; 
                    } 
                    echo '<h4 style="margin-top: 20px; font-family: \'Open Sans\', sans-serif;">Visualización del Certificado</h4>'; 
                    $google_viewer_url = 'https://docs.google.com/gview?url=' . urlencode($pdf_url) . '&embedded=true'; 
                    echo '<iframe src="' . esc_url($google_viewer_url) . '" width="100%" style="border: 1px solid #ddd; aspect-ratio: 1 / 1.414;"></iframe>'; 
                } 
                echo '</div>'; 
            } 
        } else {
            // 🎯 PASO 2: Buscar en certificados grupales
            $args_grupal = array(
                'post_type' => 'certificado_grupal', 
                'posts_per_page' => 1, 
                'meta_query' => array(
                    array(
                        'key' => '_certificado_grupal_codigo', 
                        'value' => $codigo_certificado, 
                        'compare' => '='
                    )
                )
            ); 
            $query_grupal = new WP_Query($args_grupal); 
            
            if ($query_grupal->have_posts()) {
                // ✅ CERTIFICADO GRUPAL ENCONTRADO
                while ($query_grupal->have_posts()) { 
                    $query_grupal->the_post(); 
                    $empresa = get_post_meta(get_the_ID(), '_certificado_grupal_empresa', true); 
                    $curso = get_post_meta(get_the_ID(), '_certificado_grupal_curso', true); 
                    $fecha = get_post_meta(get_the_ID(), '_certificado_grupal_fecha', true); 
                    $pdf_url = get_post_meta(get_the_ID(), '_certificado_grupal_pdf_url', true); 
                    $listado_participantes = get_post_meta(get_the_ID(), '_certificado_grupal_listado_participantes', true);
                    
                    // Contar participantes
                    $participantes_array = explode("\n", trim($listado_participantes));
                    $num_participantes = count(array_filter($participantes_array, 'trim'));
                    
                    echo '<div style="border: 2px solid #2e7d32; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h2 style="color: #2e7d32; font-family: \'Open Sans\', sans-serif;">✅ Certificado Grupal Válido</h2>'; 
                    echo '<p><strong>Empresa:</strong> ' . esc_html($empresa) . '</p>'; 
                    echo '<p><strong>Curso:</strong> ' . esc_html($curso) . '</p>'; 
                    echo '<p><strong>Fecha de Emisión:</strong> ' . esc_html($fecha) . '</p>'; 
                    echo '<p><strong>Participantes:</strong> ' . $num_participantes . ' personas</p>'; 
                    echo '<p><strong>Código de Verificación:</strong> ' . esc_html($codigo_certificado) . '</p>'; 
                    
                    if (!empty($pdf_url)) { 
                        echo '<hr style="margin: 20px 0;">'; 
                        echo '<a href="' . esc_url($pdf_url) . '" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #2e7d32; color: white; text-decoration: none; border-radius: 4px; margin: 5px; font-weight: bold;">Descargar Certificado Grupal</a>'; 
                        
                        // 🎯 BOTÓN PARA VER DIPLOMAS COMPILADOS
                        $diplomas_url = zc_grupal_obtener_url_diplomas_compilados(get_the_ID());
                        if (!empty($diplomas_url)) {
                            echo '<a href="' . esc_url($diplomas_url) . '" target="_blank" style="display: inline-block; padding: 10px 20px; background-color: #1976d2; color: white; text-decoration: none; border-radius: 4px; margin: 5px; font-weight: bold;">Ver Diplomas del Grupo</a>'; 
                        }
                        
                        echo '<h4 style="margin-top: 20px; font-family: \'Open Sans\', sans-serif;">Visualización del Certificado Grupal</h4>'; 
                        $google_viewer_url = 'https://docs.google.com/gview?url=' . urlencode($pdf_url) . '&embedded=true'; 
                        echo '<iframe src="' . esc_url($google_viewer_url) . '" width="100%" style="border: 1px solid #ddd; aspect-ratio: 1 / 1.414;"></iframe>'; 
                    } 
                    echo '</div>'; 
                } 
            } else {
                // ❌ NO ENCONTRADO EN NINGÚN LUGAR
                echo '<div style="border: 2px solid #F44336; padding: 20px; text-align: center; margin-bottom: 30px; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h2 style="color: #F44336;">❌ Certificado no encontrado o inválido</h2><p>El código de verificación "' . esc_html($codigo_certificado) . '" no es válido.</p></div>'; 
            }
            wp_reset_postdata(); 
        }
        wp_reset_postdata(); 
    } 
    
    echo '<div style="padding: 20px; border: 1px solid #ccc; border-radius: 5px; font-family: \'Open Sans\', sans-serif;"><h3 style="font-family: \'Open Sans\', sans-serif;">Formulario de Verificación</h3><p>Ingrese el código del certificado para verificar su validez.</p>'; 
    echo '<form action="' . esc_url(get_permalink()) . '" method="get"><p><label for="id">Número de Certificado:</label><br><input type="text" id="id" name="id" value="" style="width: 100%; padding: 8px;" required></p>'; 
    echo '<p><input type="submit" value="Verificar Certificado" style="padding: 10px 15px; background-color: #84BC41; color: white; border: none; cursor: pointer; font-weight: bold;"></p></form></div>'; 
    
    return ob_get_clean(); 
}

// =============================================================================
// PARTE 6: AUTOMATIZACIÓN DE PDF CON TCPDF (VERSIÓN FINAL CON FONDO PNG)
// =============================================================================
add_action('wp_after_insert_post', 'zc_final_generar_pdf', 20, 2);
add_action('wp_after_insert_post', 'zc_final_generar_diploma', 20, 2);

class ZC_PDF_Certificado extends TCPDF {
    // Sobrescribimos el método Header
    public function Header() {
        // Imagen de fondo que se repetirá en cada página
        $image_file = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/hoja-membretada-a4-2.png';
        
        // Guardar el estado actual de AutoPageBreak y márgenes
        $bMargin = $this->getBreakMargin();
        $auto_page_break = $this->AutoPageBreak;
        
        // Desactivar AutoPageBreak para dibujar el fondo
        $this->SetAutoPageBreak(false, 0);
        
        // Dibujar la imagen de fondo cubriendo toda la página
        $this->Image($image_file, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        
        // Restaurar el estado de AutoPageBreak
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        
        // Establecer el punto de inicio del contenido después del fondo
        $this->setPageMark();
    }
}

function zc_final_generar_pdf($post_id, $post) {
    if ($post->post_type !== 'certificado' || ($post->post_status !== 'publish' && $post->post_status !== 'draft')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // La librería ya está cargada globalmente, no se necesita require_once aquí.

    // --- OBTENER TODOS LOS DATOS ---
    $codigo = get_post_meta($post_id, '_certificado_codigo', true);
    $curso = get_post_meta($post_id, '_certificado_curso', true);
    $empresa = get_post_meta($post_id, '_certificado_empresa', true);
    $director = get_post_meta($post_id, '_certificado_director', true) ?: 'Nombre Director';
    $instructor = get_post_meta($post_id, '_certificado_instructor', true) ?: 'Nombre Instructor';
    $duracion = get_post_meta($post_id, '_certificado_duracion', true);
    $fecha_realizacion = get_post_meta($post_id, '_certificado_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post_id, '_certificado_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post_id, '_certificado_oc_cliente', true);
    $listado_participantes_raw = get_post_meta($post_id, '_certificado_listado_participantes', true);
    $verification_url = home_url('/pagina-de-verificacion-test/?id=' . urlencode($codigo));

    // --- INICIALIZAR CON LA CLASE PDF PERSONALIZADA ---
    $pdf = new ZC_PDF_Certificado('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->setPrintHeader(true); // Habilitar nuestro Header personalizado
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 25, 15); // Margen superior más grande para no solapar con el header
    $pdf->SetAutoPageBreak(true, 25); // Habilitar salto de página automático con margen inferior
    
    $pdf->AddPage();
    $font = 'helvetica';

    // --- TEXTO DINÁMICO INICIAL ---
    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $texto_dinamico = "Se certifica que la empresa <b>$empresa</b> participó en la Capacitación de <b>$curso</b>, cumpliendo con los requisitos de asistencia y aprobación establecidos.";
    $pdf->writeHTMLCell(0, 0, '', '', $texto_dinamico, 0, 1, 0, true, 'L', true);
    $pdf->Ln(10);

    // --- TABLA DE DATOS PRINCIPALES ---
    $pdf->SetFont($font, '', 10);
    $tbl_datos = <<<EOD
    <table border="1" cellpadding="6" cellspacing="0" style="border-color:#000;">
        <tr>
            <td width="30%" style="background-color:#e0e0e0;"><b>Empresa o Razón Social</b></td>
            <td width="70%">$empresa</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Curso</b></td>
            <td>$curso</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Duración</b></td>
            <td>$duracion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Fecha de Realización</b></td>
            <td>$fecha_realizacion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Fecha de Expiración</b></td>
            <td>$fecha_expiracion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>O.C. Cliente</b></td>
            <td>$oc_cliente</td>
        </tr>
    </table>
EOD;
    $pdf->writeHTML($tbl_datos, true, false, false, false, '');
    $pdf->Ln(10);

    // --- TÍTULO LISTADO DE PARTICIPANTES ---
    $pdf->SetFont($font, 'B', 12);
    $pdf->Cell(0, 10, 'LISTADO DE PARTICIPANTES', 0, 1, 'C');
    $pdf->Ln(2);

    // --- TABLA DE PARTICIPANTES (AJUSTE DE PRECISIÓN EN PADDING) ---
    $participantes = explode("\n", trim($listado_participantes_raw));
    
    // Se define el padding explícitamente en los estilos para forzar una alineación idéntica.
    $style_th = 'border: 1px solid #000; background-color:#000; color:#fff; font-weight:bold; vertical-align:middle; padding:5px;';
    $style_td = 'border: 1px solid #000; vertical-align:middle; padding:5px;';

    // Se quita el cellpadding del HTML y se confía en los estilos CSS.
    $tbl_participantes = <<<EOD
    <table cellspacing="0" style="font-size:8pt;">
        <thead>
            <tr>
                <th width="5%" align="left" style="$style_th">Nº</th>
                <th width="35%" align="left" style="$style_th">Nombre completo</th>
                <th width="15%" align="left" style="$style_th">RUT</th>
                <th width="10%" align="left" style="$style_th">Asistencia</th>
                <th width="8%" align="left" style="$style_th">Nota T.</th>
                <th width="8%" align="left" style="$style_th">Nota S.</th>
                <th width="9%" align="left" style="$style_th">Nota Final</th>
                <th width="10%" align="left" style="$style_th">Aprobación</th>
            </tr>
        </thead>
        <tbody>
EOD;

    if (!empty($participantes)) {
        $i = 0;
        foreach ($participantes as $participante_line) {
            $i++;
            $participante_line = trim($participante_line);
            if (empty($participante_line)) continue;
            
            $partes = str_getcsv($participante_line, ',');
            $numero = $i;
            $nombre = $partes[0] ?? '';
            $rut = $partes[1] ?? '';
            $asistencia = $partes[2] ?? '';
            $nota_t = $partes[3] ?? '';
            $nota_s = $partes[4] ?? '';
            $nota_final = $partes[5] ?? '';
            $aprobacion = $partes[6] ?? '';

            $tbl_participantes .= '<tr>';
            $tbl_participantes .= '<td width="5%" align="left" style="' . $style_td . '">' . $numero . '</td>';
            $tbl_participantes .= '<td width="35%" align="left" style="' . $style_td . '">' . esc_html($nombre) . '</td>';
            $tbl_participantes .= '<td width="15%" align="left" style="' . $style_td . '">' . esc_html($rut) . '</td>';
            $tbl_participantes .= '<td width="10%" align="left" style="' . $style_td . '">' . esc_html($asistencia) . '</td>';
            $tbl_participantes .= '<td width="8%" align="left" style="' . $style_td . '">' . esc_html($nota_t) . '</td>';
            $tbl_participantes .= '<td width="8%" align="left" style="' . $style_td . '">' . esc_html($nota_s) . '</td>';
            $tbl_participantes .= '<td width="9%" align="left" style="' . $style_td . '">' . esc_html($nota_final) . '</td>';
            $tbl_participantes .= '<td width="10%" align="left" style="' . $style_td . '">' . esc_html($aprobacion) . '</td>';
            $tbl_participantes .= '</tr>';
        }
    }
    $tbl_participantes .= '</tbody></table>';
    
    // El flag 'true' en writeHTML es clave para que repita los headers
    $pdf->writeHTML($tbl_participantes, true, false, true, false, '');

    // --- SECCIÓN DE FIRMAS (POSICIONAMIENTO INTELIGENTE) ---
    // Se asegura de que las firmas no queden cortadas al final de una página
    if ($pdf->GetY() > ($pdf->getPageHeight() - 60)) {
        $pdf->AddPage();
    }
    $pdf->Ln(15);

    $yFirmas = $pdf->GetY();
    $pdf->SetFont($font, 'B', 11);
    $pdf->SetTextColor(50, 50, 50);
    $firmaDirectorX = 30;
    $firmaInstructorX = 210 - 30 - 80;

    $pdf->Cell(80, 10, $director, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    $pdf->SetX($firmaInstructorX);
    $pdf->Cell(80, 10, $instructor, 0, 1, 'C', false, '', 0, false, 'T', 'M');

    $yLinea = $pdf->GetY() - 2;
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
    
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX($firmaDirectorX);
    $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
    $pdf->SetX($firmaInstructorX);
    $pdf->Cell(80, 10, 'Firma Instructor', 0, 1, 'C');

    // --- PIE DE PÁGINA CON TEXTO Y QR (POSICIÓN CORREGIDA Y ALINEADA) ---
    $yPositionFooter = 220; // Posición Y fija para el inicio de este bloque
    $pdf->SetY($yPositionFooter); 
    
    $yPositionForQr = $pdf->GetY();
    
    $pdf->SetFont($font, 'B', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Código de validación:', 0, 1, 'L');
    $pdf->SetFont($font, '', 9);
    $pdf->Cell(0, 5, $codigo, 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont($font, 'B', 9);
    $pdf->Cell(0, 5, 'Verifica este certificado en:', 0, 1, 'L');
    $pdf->SetFont($font, 'U', 9);
    $pdf->SetTextColor(40, 80, 150);
    $pdf->Cell(0, 5, home_url('/pagina-de-verificacion-test/'), 0, 1, 'L', false, home_url('/pagina-de-verificacion-test/'));
    
    $qrSize = 35;
    $qrX = 150;
    $pdf->write2DBarcode($verification_url, 'QRCODE,M', $qrX, $yPositionForQr, $qrSize, $qrSize);

    // Limpiar el buffer de salida
    ob_end_clean();
    
    $pdf_content = $pdf->Output('', 'S');
    
    $file_name = 'certificado-' . sanitize_title($empresa) . '-' . $post_id . '.pdf';
    $upload = wp_upload_bits($file_name, null, $pdf_content);
    if (empty($upload['error'])) {
        update_post_meta($post_id, '_certificado_pdf_url', $upload['url']);
    } else {
        update_post_meta($post_id, '_certificado_pdf_url', 'Error al subir PDF: ' . $upload['error']);
    }
}


function zc_final_generar_diploma($post_id, $post) {
    if ($post->post_type !== 'certificado' || ($post->post_status !== 'publish' && $post->post_status !== 'draft')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // La librería ya está cargada globalmente, no se necesita require_once aquí.

    // Obtener datos
    $codigo = get_post_meta($post_id, '_certificado_codigo', true);
    $participante = get_post_meta($post_id, '_certificado_participante', true);
    $curso = get_post_meta($post_id, '_certificado_curso', true);
    $fecha = get_post_meta($post_id, '_certificado_fecha', true);
    $director = get_post_meta($post_id, '_certificado_director', true) ?: 'Nombre Director';
    $instructor = get_post_meta($post_id, '_certificado_instructor', true) ?: 'Nombre Instructor';
    $empresa = get_post_meta($post_id, '_certificado_empresa', true); // Obtener el dato de la empresa
    $verification_url = home_url('/pagina-de-verificacion-test/?id=' . urlencode($codigo));

    // --- DISEÑO DEL DIPLOMA (HORIZONTAL) ---
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // 'L' para Landscape (Horizontal)
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->AddPage();
    $font = 'opensans';

    // --- INICIO: AÑADIR IMAGEN DE FONDO DEL DIPLOMA ---
    // !! IMPORTANTE: Reemplaza esta URL con la URL de tu imagen de fondo para el diploma horizontal.
    $imagen_fondo_diploma_url = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/diploma-zenactivo.png'; 
    if ($imagen_fondo_diploma_url) {
        $pdf->SetAutoPageBreak(false, 0);
        // Las dimensiones para A4 horizontal son 297x210 mm
        $pdf->Image($imagen_fondo_diploma_url, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetAutoPageBreak(true, 15); // Margen inferior
    }
    // --- FIN: AÑADIR IMAGEN DE FONDO ---

    // Coordenada X inicial para el contenido (para dejar espacio al logo de la izquierda)
    $contentX = 85;
    $pdf->SetX($contentX);

    // --- INICIO: CONTENIDO DEL DIPLOMA AJUSTADO Y CENTRADO ---

    // Título "CERTIFICADO"
    $pdf->SetY(25);
    $pdf->SetFont($font, 'B', 36);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 15, 'CERTIFICADO', 0, 1, 'C');

    // Subtítulo "ZEN ACTIVO"
    $pdf->SetFont($font, 'B', 18);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->Cell(0, 10, 'ZEN ACTIVO', 0, 1, 'C');
    $pdf->Ln(5);

    // Barra verde con texto (ya está centrada)
    $pdf->SetFillColor(132, 188, 65);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($font, 'B', 12);
    $pdf->Cell(0, 10, 'POR LA PRESENTE CERTIFICA QUE', 0, 1, 'C', true);
    $pdf->Ln(8);

    // Nombre del Participante
    $pdf->SetFont($font, 'B', 26);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 15, $participante, 0, 1, 'C');

    // Empresa (si existe)
    if (!empty($empresa)) {
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        // Se usa HTML para poder centrar el texto mixto (normal y negrita)
        $empresa_html = 'Empresa o Particular: <b>' . esc_html($empresa) . '</b>';
        $pdf->writeHTMLCell(0, 0, '', '', $empresa_html, 0, 1, 0, true, 'C', true);
        $pdf->Ln(8);
    }

    // Texto "Por haber completado..."
    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 10, 'Por haber completado satisfactoriamente el curso de:', 0, 1, 'C');
    
    // Nombre del curso
    $pdf->SetFont($font, 'B', 18);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->MultiCell(0, 12, $curso, 0, 'C');
    $pdf->Ln(15);

    // --- SECCIÓN DE FIRMAS (CENTRADO HORIZONTAL) ---
    $yFirmas = 150;
    $pdf->SetY($yFirmas);

    $pdf->SetFont($font, 'B', 12);
    $pdf->SetTextColor(50, 50, 50);

    // Posiciones X para las firmas
    $firmaDirectorX = 60;
    $firmaInstructorX = 297 - 60 - 80; // Margen derecho

    // Nombres
    $pdf->SetXY($firmaDirectorX, $yFirmas);
    $pdf->Cell(80, 10, $director, 0, 0, 'C');
    $pdf->SetXY($firmaInstructorX, $yFirmas);
    $pdf->Cell(80, 10, $instructor, 0, 1, 'C');

    // Líneas
    $yLinea = $yFirmas + 8;
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
    
    // Títulos
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($firmaDirectorX, $yLinea);
    $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
    $pdf->SetXY($firmaInstructorX, $yLinea);
    $pdf->Cell(80, 10, 'Firma Instructor', 0, 1, 'C');

    // --- QR Y DATOS DE VERIFICACIÓN (ESQUINA INFERIOR DERECHA) ---
    $yFooter = 180;
    $xFooter = 220;
    $pdf->SetXY($xFooter, $yFooter);
    $pdf->SetFont($font, 'B', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 4, 'Valida este diploma en:', 0, 1, 'L');
    $pdf->SetFont($font, 'U', 8);
    $pdf->SetTextColor(40, 80, 150);
    $pdf->Cell(0, 4, home_url('/pagina-de-verificacion-test/'), 0, 1, 'L', false, home_url('/pagina-de-verificacion-test/'));
    $pdf->SetFont($font, '', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 4, 'Código: ' . $codigo, 0, 1, 'L');

    $qrSize = 25;
    $qrX = 297 - $qrSize - 15; // A la derecha
    $qrY = 210 - $qrSize - 15; // Abajo
    $pdf->write2DBarcode($verification_url, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize);

    // --- FIN DEL DISEÑO ---
    $pdf_content = $pdf->Output('', 'S');
    
    // Subir a Medios y guardar URL
    $file_name = 'diploma-' . sanitize_title($participante) . '-' . $post_id . '.pdf';
    $upload = wp_upload_bits($file_name, null, $pdf_content);
    if (empty($upload['error'])) {
        $file_url = $upload['url'];
        // No es necesario remover y añadir la acción aquí si se llama explícitamente
        update_post_meta($post_id, '_certificado_diploma_url', $file_url);
    }
}

// =============================================================================
// PARTE 7: ENCOLAR HOJA DE ESTILOS DE GOOGLE FONTS
// =============================================================================
function zc_final_enqueue_styles() {
    wp_enqueue_style('zc-final-google-fonts', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'zc_final_enqueue_styles');

/*
================================================================
🆕 SECCIÓN 2: CERTIFICADOS GRUPALES - NUEVA FUNCIONALIDAD
================================================================
Esta sección contiene código duplicado y modificado para manejar
certificados grupales que auto-generan diplomas individuales.
✨ AQUÍ SE IMPLEMENTA LA NUEVA LÓGICA GRUPAL

FUNCIONALIDAD:
- CPT 'certificado_grupal' separado del individual
- Al publicar: Auto-crea certificados individuales 
- Códigos únicos dinámicos: base-1, base-2, base-3, etc.
- Mismo diseño PDF, diferente comportamiento
================================================================
*/

// =============================================================================
// PARTE 8: CREAR EL CUSTOM POST TYPE "CERTIFICADO GRUPAL"
// =============================================================================
add_action('init', 'zc_grupal_crear_cpt_certificado');
function zc_grupal_crear_cpt_certificado() {
    $labels = array(
        'name' => 'Certificados Grupales', 
        'singular_name' => 'Certificado Grupal', 
        'menu_name' => 'Certificados Grupales', 
        'add_new_item' => 'Añadir Nuevo Certificado Grupal', 
        'edit_item' => 'Editar Certificado Grupal', 
        'all_items' => 'Todos los Certificados Grupales'
    );
    $args = array(
        'labels' => $labels, 
        'public' => true, 
        'has_archive' => false, 
        'supports' => array('title'), 
        'menu_icon' => 'dashicons-groups', 
        'rewrite' => array('slug' => 'certificados-grupales')
    );
    register_post_type('certificado_grupal', $args);
}

// =============================================================================
// PARTE 9: CREAR LOS CAMPOS PERSONALIZADOS PARA CERTIFICADOS GRUPALES
// =============================================================================
add_action('add_meta_boxes', 'zc_grupal_agregar_campos_metabox');
function zc_grupal_agregar_campos_metabox() {
    add_meta_box('datos_certificado_grupal_metabox', 'Datos del Certificado Grupal', 'zc_grupal_mostrar_campos_html', 'certificado_grupal', 'normal', 'high');
}

function zc_grupal_mostrar_campos_html($post) {
    // Campos existentes (duplicados del individual)
    $codigo = get_post_meta($post->ID, '_certificado_grupal_codigo', true);
    $curso = get_post_meta($post->ID, '_certificado_grupal_curso', true);
    $fecha = get_post_meta($post->ID, '_certificado_grupal_fecha', true);
    $empresa = get_post_meta($post->ID, '_certificado_grupal_empresa', true);
    $director = get_post_meta($post->ID, '_certificado_grupal_director', true);
    $instructor = get_post_meta($post->ID, '_certificado_grupal_instructor', true);
    
    // Nuevos campos
    $duracion = get_post_meta($post->ID, '_certificado_grupal_duracion', true);
    $fecha_realizacion = get_post_meta($post->ID, '_certificado_grupal_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post->ID, '_certificado_grupal_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post->ID, '_certificado_grupal_oc_cliente', true);
    $listado_participantes = get_post_meta($post->ID, '_certificado_grupal_listado_participantes', true);

    // URLs de PDFs generados
    $pdf_url = get_post_meta($post->ID, '_certificado_grupal_pdf_url', true);
    
    wp_nonce_field('zc_grupal_guardar_datos', 'zc_grupal_nonce');

    echo '<div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
    echo '<h3 style="color: #2e7d32; margin-top: 0;">🎯 Certificado Grupal - Auto-generación Individual</h3>';
    echo '<p><strong>Funcionalidad:</strong> Al publicar este certificado grupal, se crearán automáticamente certificados individuales para cada participante de la lista, con códigos únicos basados en el código principal.</p>';
    echo '<p><strong>Ejemplo:</strong> Si el código es "CURSO2025", se generarán: CURSO2025-1, CURSO2025-2, CURSO2025-3, etc.</p>';
    echo '</div>';

    echo '<h3>Datos Generales</h3>';
    echo '<p><label><strong>Código de Verificación (Base):</strong><br><input type="text" name="certificado_grupal_codigo" value="' . esc_attr($codigo) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre de la Empresa:</strong><br><input type="text" name="certificado_grupal_empresa" value="' . esc_attr($empresa) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Curso:</strong><br><input type="text" name="certificado_grupal_curso" value="' . esc_attr($curso) . '" style="width:100%;"></label></p>';
    
    echo '<h3>Datos del Curso</h3>';
    echo '<p><label><strong>Duración (ej: 03 horas cronológicas):</strong><br><input type="text" name="certificado_grupal_duracion" value="' . esc_attr($duracion) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Fecha de Emisión:</strong><br><input type="date" name="certificado_grupal_fecha" value="' . esc_attr($fecha) . '"></label></p>';
    echo '<p><label><strong>Fecha de Realización:</strong><br><input type="date" name="certificado_grupal_fecha_realizacion" value="' . esc_attr($fecha_realizacion) . '"></label></p>';
    echo '<p><label><strong>Fecha de Expiración:</strong><br><input type="date" name="certificado_grupal_fecha_expiracion" value="' . esc_attr($fecha_expiracion) . '"></label></p>';
    echo '<p><label><strong>O.C. Cliente:</strong><br><input type="text" name="certificado_grupal_oc_cliente" value="' . esc_attr($oc_cliente) . '" style="width:100%;"></label></p>';

    echo '<h3>Listado de Participantes (Grupal)</h3>';
    echo '<p><label for="listado_participantes_grupal"><strong>Participantes:</strong></label><br>';
    echo '<textarea name="certificado_grupal_listado_participantes" id="listado_participantes_grupal" rows="10" style="width:100%; font-family: monospace;">' . esc_textarea($listado_participantes) . '</textarea>';
    echo '<p class="description"><strong>Instrucciones:</strong> Ingrese un participante por línea. Use comas (,) para separar las columnas en el siguiente orden:<br><code>Nombre Completo,RUT,Asistencia,Nota T.,Nota S.,Nota Final,Aprobación</code><br>Ejemplo: <code>Apolinar Andrés Mendoza Cuno,22.626.949-9,100%,7.0,7.0,7.0,A</code></p>';
    echo '<div style="background-color: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;"><strong>⚡ Auto-generación:</strong> Al publicar, cada participante será registrado como certificado individual con código único.</div>';

    echo '<h3>Firmas</h3>';
    echo '<p><label><strong>Nombre del Director:</strong><br><input type="text" name="certificado_grupal_director" value="' . esc_attr($director) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Instructor:</strong><br><input type="text" name="certificado_grupal_instructor" value="' . esc_attr($instructor) . '" style="width:100%;"></label></p>';
    
    echo '<hr>';
    echo '<h3>URL del Certificado Grupal Generado</h3>';
    echo '<p style="background-color: #f0f6fc; padding: 15px; border-radius: 4px;"><label><strong>URL del Certificado Grupal (Automático):</strong><br><input type="url" value="' . esc_attr($pdf_url) . '" style="width:100%; background-color: #eee;" readonly></label></p>';
}

// =============================================================================
// PARTE 10: GUARDAR DATOS Y AUTO-GENERACIÓN DE CERTIFICADOS INDIVIDUALES
// =============================================================================
add_action('save_post_certificado_grupal', 'zc_grupal_guardar_datos', 10, 2);
function zc_grupal_guardar_datos($post_id, $post) {
    if (!isset($_POST['zc_grupal_nonce']) || !wp_verify_nonce($_POST['zc_grupal_nonce'], 'zc_grupal_guardar_datos')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Guardar todos los campos del certificado grupal
    if (isset($_POST['certificado_grupal_codigo'])) update_post_meta($post_id, '_certificado_grupal_codigo', sanitize_text_field($_POST['certificado_grupal_codigo']));
    if (isset($_POST['certificado_grupal_curso'])) update_post_meta($post_id, '_certificado_grupal_curso', sanitize_text_field($_POST['certificado_grupal_curso']));
    if (isset($_POST['certificado_grupal_fecha'])) update_post_meta($post_id, '_certificado_grupal_fecha', sanitize_text_field($_POST['certificado_grupal_fecha']));
    if (isset($_POST['certificado_grupal_empresa'])) update_post_meta($post_id, '_certificado_grupal_empresa', sanitize_text_field($_POST['certificado_grupal_empresa']));
    if (isset($_POST['certificado_grupal_director'])) update_post_meta($post_id, '_certificado_grupal_director', sanitize_text_field($_POST['certificado_grupal_director']));
    if (isset($_POST['certificado_grupal_instructor'])) update_post_meta($post_id, '_certificado_grupal_instructor', sanitize_text_field($_POST['certificado_grupal_instructor']));
    if (isset($_POST['certificado_grupal_duracion'])) update_post_meta($post_id, '_certificado_grupal_duracion', sanitize_text_field($_POST['certificado_grupal_duracion']));
    if (isset($_POST['certificado_grupal_fecha_realizacion'])) update_post_meta($post_id, '_certificado_grupal_fecha_realizacion', sanitize_text_field($_POST['certificado_grupal_fecha_realizacion']));
    if (isset($_POST['certificado_grupal_fecha_expiracion'])) update_post_meta($post_id, '_certificado_grupal_fecha_expiracion', sanitize_text_field($_POST['certificado_grupal_fecha_expiracion']));
    if (isset($_POST['certificado_grupal_oc_cliente'])) update_post_meta($post_id, '_certificado_grupal_oc_cliente', sanitize_text_field($_POST['certificado_grupal_oc_cliente']));
    if (isset($_POST['certificado_grupal_listado_participantes'])) update_post_meta($post_id, '_certificado_grupal_listado_participantes', sanitize_textarea_field($_POST['certificado_grupal_listado_participantes']));

    // 🎯 MAGIA: Auto-generar certificados individuales solo si está publicado
    if ($post->post_status === 'publish') {
        zc_grupal_procesar_participantes($post_id);
        
        // Generar PDF del certificado grupal después de procesar participantes
        zc_grupal_generar_pdf_manual($post_id, $post);
    }
}

// =============================================================================
// PARTE 11: FUNCIÓN DE AUTO-GENERACIÓN DE CERTIFICADOS INDIVIDUALES
// =============================================================================
function zc_grupal_procesar_participantes($post_id_grupal) {
    // Obtener datos del certificado grupal
    $codigo_base = get_post_meta($post_id_grupal, '_certificado_grupal_codigo', true);
    $curso = get_post_meta($post_id_grupal, '_certificado_grupal_curso', true);
    $fecha = get_post_meta($post_id_grupal, '_certificado_grupal_fecha', true);
    $empresa = get_post_meta($post_id_grupal, '_certificado_grupal_empresa', true);
    $director = get_post_meta($post_id_grupal, '_certificado_grupal_director', true);
    $instructor = get_post_meta($post_id_grupal, '_certificado_grupal_instructor', true);
    $duracion = get_post_meta($post_id_grupal, '_certificado_grupal_duracion', true);
    $fecha_realizacion = get_post_meta($post_id_grupal, '_certificado_grupal_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post_id_grupal, '_certificado_grupal_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post_id_grupal, '_certificado_grupal_oc_cliente', true);
    $listado_participantes_raw = get_post_meta($post_id_grupal, '_certificado_grupal_listado_participantes', true);

    if (empty($listado_participantes_raw) || empty($codigo_base)) {
        return; // No hay participantes o código base
    }

    // Procesar cada participante
    $participantes = explode("\n", trim($listado_participantes_raw));
    $contador = 1;

    foreach ($participantes as $participante_line) {
        $participante_line = trim($participante_line);
        if (empty($participante_line)) continue;

        // Extraer datos del participante
        $partes = str_getcsv($participante_line, ',');
        $nombre_participante = $partes[0] ?? '';
        
        if (empty($nombre_participante)) continue;

        // 🎯 GENERAR CÓDIGO ÚNICO DINÁMICO
        $codigo_individual = $codigo_base . '-' . $contador;

        // Verificar si ya existe un certificado con este código
        $existing = get_posts(array(
            'post_type' => 'certificado',
            'meta_query' => array(
                array(
                    'key' => '_certificado_codigo',
                    'value' => $codigo_individual,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));

        // Si ya existe, saltarlo
        if (!empty($existing)) {
            $contador++;
            continue;
        }

        // Crear título descriptivo
        $titulo_certificado = "Certificado Individual: {$nombre_participante} - {$curso}";

        // Crear el post del certificado individual
        $post_data = array(
            'post_title' => $titulo_certificado,
            'post_type' => 'certificado',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );

        $post_id_individual = wp_insert_post($post_data);

        if ($post_id_individual > 0) {
            // Copiar todos los datos del certificado grupal al individual
            update_post_meta($post_id_individual, '_certificado_codigo', $codigo_individual);
            update_post_meta($post_id_individual, '_certificado_participante', $nombre_participante);
            update_post_meta($post_id_individual, '_certificado_curso', $curso);
            update_post_meta($post_id_individual, '_certificado_fecha', $fecha);
            update_post_meta($post_id_individual, '_certificado_empresa', $empresa);
            update_post_meta($post_id_individual, '_certificado_director', $director);
            update_post_meta($post_id_individual, '_certificado_instructor', $instructor);
            update_post_meta($post_id_individual, '_certificado_duracion', $duracion);
            update_post_meta($post_id_individual, '_certificado_fecha_realizacion', $fecha_realizacion);
            update_post_meta($post_id_individual, '_certificado_fecha_expiracion', $fecha_expiracion);
            update_post_meta($post_id_individual, '_certificado_oc_cliente', $oc_cliente);
            
            // Para el certificado individual, solo una línea de participante
            update_post_meta($post_id_individual, '_certificado_listado_participantes', $participante_line);

            // Agregar metadato de referencia al certificado grupal
            update_post_meta($post_id_individual, '_certificado_origen_grupal', $post_id_grupal);

            // 🎯 GENERAR PDFs AUTOMÁTICAMENTE
            $post_object = get_post($post_id_individual);
            if ($post_object) {
                zc_final_generar_pdf($post_id_individual, $post_object);
                zc_final_generar_diploma($post_id_individual, $post_object);
            }
        }

        $contador++;
    }

    // Agregar metadato al certificado grupal indicando que ya se procesó
    update_post_meta($post_id_grupal, '_certificado_grupal_procesado', current_time('mysql'));
    
    // 🎯 GENERAR DIPLOMAS COMPILADOS AUTOMÁTICAMENTE
    zc_grupal_generar_diplomas_compilados($post_id_grupal);
}

// =============================================================================
// PARTE 12: COLUMNAS PERSONALIZADAS PARA CERTIFICADOS GRUPALES
// =============================================================================
add_filter('manage_certificado_grupal_posts_columns', 'zc_grupal_añadir_columnas');
function zc_grupal_añadir_columnas($columns) { 
    $new_columns = array(); 
    $new_columns['cb'] = $columns['cb']; 
    $new_columns['title'] = 'Título de Referencia'; 
    $new_columns['empresa'] = 'Empresa'; 
    $new_columns['curso'] = 'Curso'; 
    $new_columns['codigo'] = 'Código Base'; 
    $new_columns['participantes'] = 'Participantes'; 
    $new_columns['fecha'] = 'Fecha de Emisión'; 
    $new_columns['procesado'] = 'Estado'; 
    return $new_columns; 
}

add_action('manage_certificado_grupal_posts_custom_column', 'zc_grupal_mostrar_columnas', 10, 2);
function zc_grupal_mostrar_columnas($column_name, $post_id) { 
    switch ($column_name) { 
        case 'empresa': 
            echo esc_html(get_post_meta($post_id, '_certificado_grupal_empresa', true)); 
            break; 
        case 'curso': 
            echo esc_html(get_post_meta($post_id, '_certificado_grupal_curso', true)); 
            break; 
        case 'codigo': 
            echo esc_html(get_post_meta($post_id, '_certificado_grupal_codigo', true)); 
            break; 
        case 'participantes':
            $listado = get_post_meta($post_id, '_certificado_grupal_listado_participantes', true);
            $count = !empty($listado) ? count(explode("\n", trim($listado))) : 0;
            echo '<span style="background-color: #e3f2fd; padding: 3px 8px; border-radius: 3px;">' . $count . '</span>';
            break;
        case 'fecha': 
            echo esc_html(get_post_meta($post_id, '_certificado_grupal_fecha', true)); 
            break; 
        case 'procesado':
            $procesado = get_post_meta($post_id, '_certificado_grupal_procesado', true);
            if ($procesado) {
                echo '<span style="color: green;">✅ Procesado</span>';
            } else {
                echo '<span style="color: orange;">⏳ Pendiente</span>';
            }
            break;
    } 
}

// =============================================================================
// PARTE 13: GENERACIÓN DE PDF PARA CERTIFICADOS GRUPALES
// =============================================================================
function zc_grupal_generar_pdf_manual($post_id, $post) {
    if ($post->post_type !== 'certificado_grupal' || ($post->post_status !== 'publish' && $post->post_status !== 'draft')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // --- OBTENER TODOS LOS DATOS DEL CERTIFICADO GRUPAL ---
    $codigo = get_post_meta($post_id, '_certificado_grupal_codigo', true);
    $curso = get_post_meta($post_id, '_certificado_grupal_curso', true);
    $empresa = get_post_meta($post_id, '_certificado_grupal_empresa', true);
    $director = get_post_meta($post_id, '_certificado_grupal_director', true) ?: 'Nombre Director';
    $instructor = get_post_meta($post_id, '_certificado_grupal_instructor', true) ?: 'Nombre Instructor';
    $duracion = get_post_meta($post_id, '_certificado_grupal_duracion', true);
    $fecha_realizacion = get_post_meta($post_id, '_certificado_grupal_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post_id, '_certificado_grupal_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post_id, '_certificado_grupal_oc_cliente', true);
    $listado_participantes_raw = get_post_meta($post_id, '_certificado_grupal_listado_participantes', true);
    $verification_url = home_url('/pagina-de-verificacion-test/?id=' . urlencode($codigo));

    // --- INICIALIZAR CON LA CLASE PDF PERSONALIZADA (IGUAL QUE INDIVIDUAL) ---
    $pdf = new ZC_PDF_Certificado('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->setPrintHeader(true); // Habilitar nuestro Header personalizado
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 25, 15); // Margen superior más grande para no solapar con el header
    $pdf->SetAutoPageBreak(true, 25); // Habilitar salto de página automático con margen inferior
    
    $pdf->AddPage();
    $font = 'helvetica';

    // --- TEXTO DINÁMICO INICIAL (ADAPTADO PARA GRUPAL) ---
    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $texto_dinamico = "Se certifica que la empresa <b>$empresa</b> participó en la Capacitación de <b>$curso</b>, cumpliendo con los requisitos de asistencia y aprobación establecidos.";
    $pdf->writeHTMLCell(0, 0, '', '', $texto_dinamico, 0, 1, 0, true, 'L', true);
    $pdf->Ln(10);

    // --- TABLA DE DATOS PRINCIPALES (IGUAL QUE INDIVIDUAL) ---
    $pdf->SetFont($font, '', 10);
    $tbl_datos = <<<EOD
    <table border="1" cellpadding="6" cellspacing="0" style="border-color:#000;">
        <tr>
            <td width="30%" style="background-color:#e0e0e0;"><b>Empresa o Razón Social</b></td>
            <td width="70%">$empresa</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Curso</b></td>
            <td>$curso</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Duración</b></td>
            <td>$duracion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Fecha de Realización</b></td>
            <td>$fecha_realizacion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>Fecha de Expiración</b></td>
            <td>$fecha_expiracion</td>
        </tr>
        <tr>
            <td style="background-color:#e0e0e0;"><b>O.C. Cliente</b></td>
            <td>$oc_cliente</td>
        </tr>
    </table>
EOD;
    $pdf->writeHTML($tbl_datos, true, false, false, false, '');
    $pdf->Ln(10);

    // --- TÍTULO LISTADO DE PARTICIPANTES (MANTIENE TEXTO ORIGINAL PARA GRUPAL) ---
    $pdf->SetFont($font, 'B', 12);
    $pdf->Cell(0, 10, 'LISTADO DE PARTICIPANTES', 0, 1, 'C');
    $pdf->Ln(2);

    // --- TABLA DE PARTICIPANTES (LÓGICA IDÉNTICA) ---
    $participantes = explode("\n", trim($listado_participantes_raw));
    
    $style_th = 'border: 1px solid #000; background-color:#000; color:#fff; font-weight:bold; vertical-align:middle; padding:5px;';
    $style_td = 'border: 1px solid #000; vertical-align:middle; padding:5px;';

    $tbl_participantes = <<<EOD
    <table cellspacing="0" style="font-size:8pt;">
        <thead>
            <tr>
                <th width="5%" align="left" style="$style_th">Nº</th>
                <th width="35%" align="left" style="$style_th">Nombre completo</th>
                <th width="15%" align="left" style="$style_th">RUT</th>
                <th width="10%" align="left" style="$style_th">Asistencia</th>
                <th width="8%" align="left" style="$style_th">Nota T.</th>
                <th width="8%" align="left" style="$style_th">Nota S.</th>
                <th width="9%" align="left" style="$style_th">Nota Final</th>
                <th width="10%" align="left" style="$style_th">Aprobación</th>
            </tr>
        </thead>
        <tbody>
EOD;

    if (!empty($participantes)) {
        $i = 0;
        foreach ($participantes as $participante_line) {
            $i++;
            $participante_line = trim($participante_line);
            if (empty($participante_line)) continue;
            
            $partes = str_getcsv($participante_line, ',');
            $numero = $i;
            $nombre = $partes[0] ?? '';
            $rut = $partes[1] ?? '';
            $asistencia = $partes[2] ?? '';
            $nota_t = $partes[3] ?? '';
            $nota_s = $partes[4] ?? '';
            $nota_final = $partes[5] ?? '';
            $aprobacion = $partes[6] ?? '';

            $tbl_participantes .= '<tr>';
            $tbl_participantes .= '<td width="5%" align="left" style="' . $style_td . '">' . $numero . '</td>';
            $tbl_participantes .= '<td width="35%" align="left" style="' . $style_td . '">' . esc_html($nombre) . '</td>';
            $tbl_participantes .= '<td width="15%" align="left" style="' . $style_td . '">' . esc_html($rut) . '</td>';
            $tbl_participantes .= '<td width="10%" align="left" style="' . $style_td . '">' . esc_html($asistencia) . '</td>';
            $tbl_participantes .= '<td width="8%" align="left" style="' . $style_td . '">' . esc_html($nota_t) . '</td>';
            $tbl_participantes .= '<td width="8%" align="left" style="' . $style_td . '">' . esc_html($nota_s) . '</td>';
            $tbl_participantes .= '<td width="9%" align="left" style="' . $style_td . '">' . esc_html($nota_final) . '</td>';
            $tbl_participantes .= '<td width="10%" align="left" style="' . $style_td . '">' . esc_html($aprobacion) . '</td>';
            $tbl_participantes .= '</tr>';
        }
    }
    $tbl_participantes .= '</tbody></table>';
    
    $pdf->writeHTML($tbl_participantes, true, false, true, false, '');

    // --- SECCIÓN DE FIRMAS (IDÉNTICA) ---
    if ($pdf->GetY() > ($pdf->getPageHeight() - 60)) {
        $pdf->AddPage();
    }
    $pdf->Ln(15);

    $yFirmas = $pdf->GetY();
    $pdf->SetFont($font, 'B', 11);
    $pdf->SetTextColor(50, 50, 50);
    $firmaDirectorX = 30;
    $firmaInstructorX = 210 - 30 - 80;

    $pdf->Cell(80, 10, $director, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    $pdf->SetX($firmaInstructorX);
    $pdf->Cell(80, 10, $instructor, 0, 1, 'C', false, '', 0, false, 'T', 'M');

    $yLinea = $pdf->GetY() - 2;
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
    
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX($firmaDirectorX);
    $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
    $pdf->SetX($firmaInstructorX);
    $pdf->Cell(80, 10, 'Firma Instructor', 0, 1, 'C');

    // --- PIE DE PÁGINA CON TEXTO Y QR (IDÉNTICO AL INDIVIDUAL) ---
    $yPositionFooter = 220;
    $pdf->SetY($yPositionFooter); 
    
    $yPositionForQr = $pdf->GetY();
    
    $pdf->SetFont($font, 'B', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Código de validación:', 0, 1, 'L');
    $pdf->SetFont($font, '', 9);
    $pdf->Cell(0, 5, $codigo, 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont($font, 'B', 9);
    $pdf->Cell(0, 5, 'Verifica este certificado en:', 0, 1, 'L');
    $pdf->SetFont($font, 'U', 9);
    $pdf->SetTextColor(40, 80, 150);
    $pdf->Cell(0, 5, home_url('/pagina-de-verificacion-test/'), 0, 1, 'L', false, home_url('/pagina-de-verificacion-test/'));
    
    $qrSize = 35;
    $qrX = 150;
    $pdf->write2DBarcode($verification_url, 'QRCODE,M', $qrX, $yPositionForQr, $qrSize, $qrSize);

    // Generar y subir PDF
    ob_end_clean();
    
    $pdf_content = $pdf->Output('', 'S');
    
    $file_name = 'certificado-grupal-' . sanitize_title($empresa) . '-' . $post_id . '.pdf';
    $upload = wp_upload_bits($file_name, null, $pdf_content);
    if (empty($upload['error'])) {
        update_post_meta($post_id, '_certificado_grupal_pdf_url', $upload['url']);
    } else {
        update_post_meta($post_id, '_certificado_grupal_pdf_url', 'Error al subir PDF: ' . $upload['error']);
    }
}

// =============================================================================
// PARTE 14: IMPORTADOR CSV PARA CERTIFICADOS GRUPALES
// =============================================================================
add_action('admin_menu', 'zc_grupal_agregar_pagina_importador');
function zc_grupal_agregar_pagina_importador() { 
    add_submenu_page('edit.php?post_type=certificado_grupal', 'Importar Certificados Grupales', 'Importar', 'manage_options', 'zc-importador-certificados-grupales', 'zc_grupal_mostrar_pagina_importador'); 
}

function zc_grupal_mostrar_pagina_importador() { 
    echo '<div class="wrap"><h1>Importar Certificados Grupales desde CSV</h1>'; 
    if (isset($_POST['zc_grupal_import_nonce']) && wp_verify_nonce($_POST['zc_grupal_import_nonce'], 'zc_grupal_import_action')) { 
        if (!empty($_FILES['csv_file']['tmp_name'])) { 
            $csv_file = $_FILES['csv_file']['tmp_name']; 
            $file_handle = fopen($csv_file, 'r'); 
            $headers = fgetcsv($file_handle, 1024, ','); 
            $header_map = array_flip($headers); 
            $importados = 0; 
            $errores = 0; 
            while (($row = fgetcsv($file_handle, 1024, ',')) !== FALSE) { 
                $data = array(); 
                foreach($header_map as $key => $index) { 
                    if (isset($row[$index])) { 
                        $data[$key] = $row[$index]; 
                    } else { 
                        $data[$key] = ''; 
                    }
                } 
                $post_data = array(
                    'post_title' => isset($data['titulo']) ? sanitize_text_field($data['titulo']) : 'Certificado Grupal sin título', 
                    'post_type' => 'certificado_grupal', 
                    'post_status' => 'publish'
                ); 
                $post_id = wp_insert_post($post_data); 
                if ($post_id > 0) { 
                    if (isset($data['codigo'])) update_post_meta($post_id, '_certificado_grupal_codigo', sanitize_text_field($data['codigo'])); 
                    if (isset($data['empresa'])) update_post_meta($post_id, '_certificado_grupal_empresa', sanitize_text_field($data['empresa'])); 
                    if (isset($data['curso'])) update_post_meta($post_id, '_certificado_grupal_curso', sanitize_text_field($data['curso'])); 
                    if (isset($data['fecha'])) update_post_meta($post_id, '_certificado_grupal_fecha', sanitize_text_field($data['fecha'])); 
                    if (isset($data['director'])) update_post_meta($post_id, '_certificado_grupal_director', sanitize_text_field($data['director'])); 
                    if (isset($data['instructor'])) update_post_meta($post_id, '_certificado_grupal_instructor', sanitize_text_field($data['instructor'])); 
                    if (isset($data['participantes'])) update_post_meta($post_id, '_certificado_grupal_listado_participantes', sanitize_textarea_field($data['participantes'])); 
                    
                    // Generar PDF del certificado grupal
                    $post_object = get_post($post_id);
                    if ($post_object) {
                        zc_grupal_generar_pdf($post_id, $post_object);
                        // La auto-generación de individuales se activa automáticamente por el hook save_post
                    }

                    $importados++; 
                } else { 
                    $errores++; 
                } 
            } 
            fclose($file_handle); 
            echo '<div class="notice notice-success is-dismissible"><p><strong>Proceso completado:</strong> ' . $importados . ' certificados grupales importados. ' . $errores . ' errores.</p></div>'; 
        } else { 
            echo '<div class="notice notice-error is-dismissible"><p>Por favor, selecciona un archivo CSV.</p></div>';
        } 
    } 
    echo '<p>Sube un archivo CSV con las columnas: <strong>titulo, codigo, empresa, curso, fecha, director, instructor, participantes</strong>.</p>'; 
    echo '<form method="post" enctype="multipart/form-data">'; 
    wp_nonce_field('zc_grupal_import_action', 'zc_grupal_import_nonce'); 
    echo '<table class="form-table"><tr valign="top"><th scope="row"><label for="csv_file">Archivo CSV:</label></th><td><input type="file" id="csv_file" name="csv_file" accept=".csv" required></td></tr></table>'; 
    submit_button('Subir e Importar Certificados Grupales'); 
    echo '</form></div>'; 
}

// =============================================================================
// PARTE 15: GENERACIÓN DE DIPLOMAS COMPILADOS PARA CERTIFICADOS GRUPALES
// =============================================================================

function zc_grupal_obtener_url_diplomas_compilados($post_id_grupal) {
    // Verificar si ya existe el PDF compilado
    $diplomas_compilados_url = get_post_meta($post_id_grupal, '_certificado_grupal_diplomas_compilados_url', true);
    
    if (!empty($diplomas_compilados_url)) {
        return $diplomas_compilados_url;
    }
    
    // Si no existe, generarlo
    return zc_grupal_generar_diplomas_compilados($post_id_grupal);
}

function zc_grupal_generar_diplomas_compilados($post_id_grupal) {
    // Obtener datos del certificado grupal
    $codigo_base = get_post_meta($post_id_grupal, '_certificado_grupal_codigo', true);
    $curso = get_post_meta($post_id_grupal, '_certificado_grupal_curso', true);
    $empresa = get_post_meta($post_id_grupal, '_certificado_grupal_empresa', true);
    
    if (empty($codigo_base)) {
        return '';
    }
    
    // Buscar todos los certificados individuales de este grupo
    $certificados_individuales = get_posts(array(
        'post_type' => 'certificado',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_certificado_origen_grupal',
                'value' => $post_id_grupal,
                'compare' => '='
            )
        ),
        'orderby' => 'meta_value',
        'meta_key' => '_certificado_codigo',
        'order' => 'ASC'
    ));
    
    if (empty($certificados_individuales)) {
        return '';
    }
    
    // Crear PDF compilado con todos los diplomas
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Horizontal para diplomas
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $font = 'opensans';
    
    $imagen_fondo_diploma_url = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/diploma-zenactivo.png';
    
    foreach ($certificados_individuales as $index => $certificado_individual) {
        $post_id_individual = $certificado_individual->ID;
        
        // Obtener datos del certificado individual
        $codigo = get_post_meta($post_id_individual, '_certificado_codigo', true);
        $participante = get_post_meta($post_id_individual, '_certificado_participante', true);
        $curso_individual = get_post_meta($post_id_individual, '_certificado_curso', true);
        $fecha = get_post_meta($post_id_individual, '_certificado_fecha', true);
        $director = get_post_meta($post_id_individual, '_certificado_director', true) ?: 'Nombre Director';
        $instructor = get_post_meta($post_id_individual, '_certificado_instructor', true) ?: 'Nombre Instructor';
        $empresa_individual = get_post_meta($post_id_individual, '_certificado_empresa', true);
        $verification_url = home_url('/pagina-de-verificacion-test/?id=' . urlencode($codigo));
        
        // Agregar nueva página para cada diploma
        $pdf->AddPage();
        
        // 🎯 GENERAR DIPLOMA INDIVIDUAL (MISMO CÓDIGO QUE LA FUNCIÓN ORIGINAL)
        if ($imagen_fondo_diploma_url) {
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->Image($imagen_fondo_diploma_url, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
            $pdf->SetAutoPageBreak(true, 15);
        }
        
        $contentX = 85;
        $pdf->SetX($contentX);
        
        // Título "CERTIFICADO"
        $pdf->SetY(25);
        $pdf->SetFont($font, 'B', 36);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 15, 'CERTIFICADO', 0, 1, 'C');
        
        // Subtítulo "ZEN ACTIVO"
        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(132, 188, 65);
        $pdf->Cell(0, 10, 'ZEN ACTIVO', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Barra verde
        $pdf->SetFillColor(132, 188, 65);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 12);
        $pdf->Cell(0, 10, 'POR LA PRESENTE CERTIFICA QUE', 0, 1, 'C', true);
        $pdf->Ln(8);
        
        // Nombre del Participante
        $pdf->SetFont($font, 'B', 26);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 15, $participante, 0, 1, 'C');
        
        // Empresa
        if (!empty($empresa_individual)) {
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(80, 80, 80);
            $empresa_html = 'Empresa o Particular: <b>' . esc_html($empresa_individual) . '</b>';
            $pdf->writeHTMLCell(0, 0, '', '', $empresa_html, 0, 1, 0, true, 'C', true);
            $pdf->Ln(8);
        }
        
        // Texto "Por haber completado..."
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 10, 'Por haber completado satisfactoriamente el curso de:', 0, 1, 'C');
        
        // Nombre del curso
        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(132, 188, 65);
        $pdf->MultiCell(0, 12, $curso_individual, 0, 'C');
        $pdf->Ln(15);
        
        // Firmas
        $yFirmas = 150;
        $pdf->SetY($yFirmas);
        $pdf->SetFont($font, 'B', 12);
        $pdf->SetTextColor(50, 50, 50);
        
        $firmaDirectorX = 60;
        $firmaInstructorX = 297 - 60 - 80;
        
        $pdf->SetXY($firmaDirectorX, $yFirmas);
        $pdf->Cell(80, 10, $director, 0, 0, 'C');
        $pdf->SetXY($firmaInstructorX, $yFirmas);
        $pdf->Cell(80, 10, $instructor, 0, 1, 'C');
        
        $yLinea = $yFirmas + 8;
        $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
        $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
        
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($firmaDirectorX, $yLinea);
        $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
        $pdf->SetXY($firmaInstructorX, $yLinea);
        $pdf->Cell(80, 10, 'Firma Instructor', 0, 1, 'C');
        
        // QR y datos de verificación
        $yFooter = 180;
        $xFooter = 220;
        $pdf->SetXY($xFooter, $yFooter);
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 4, 'Valida este diploma en:', 0, 1, 'L');
        $pdf->SetFont($font, 'U', 8);
        $pdf->SetTextColor(40, 80, 150);
        $pdf->Cell(0, 4, home_url('/pagina-de-verificacion-test/'), 0, 1, 'L', false, home_url('/pagina-de-verificacion-test/'));
        $pdf->SetFont($font, '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 4, 'Código: ' . $codigo, 0, 1, 'L');
        
        $qrSize = 25;
        $qrX = 297 - $qrSize - 15;
        $qrY = 210 - $qrSize - 15;
        $pdf->write2DBarcode($verification_url, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize);
    }
    
    // Generar y subir PDF compilado
    ob_end_clean();
    $pdf_content = $pdf->Output('', 'S');
    
    $file_name = 'diplomas-compilados-' . sanitize_title($empresa) . '-' . $post_id_grupal . '.pdf';
    $upload = wp_upload_bits($file_name, null, $pdf_content);
    
    if (empty($upload['error'])) {
        $file_url = $upload['url'];
        update_post_meta($post_id_grupal, '_certificado_grupal_diplomas_compilados_url', $file_url);
        return $file_url;
    }
    
    return '';
}