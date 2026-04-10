<?php
/**
 * Plugin Name:       Zen Certificados Software 2
 * Description:       Certificados PDF/QR, importación y validación. Paquete Software 2 (mismo núcleo, nombre de archivo distinto para despliegue).
 * Version:           8.6.1
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
// PARTE 0: SISTEMA DE GESTIÓN DE TEMARIOS
// =============================================================================

// Crear tabla de temarios al activar plugin
register_activation_hook(__FILE__, 'zc_crear_tabla_temarios');
function zc_crear_tabla_temarios() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'zc_temarios';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        nombre varchar(255) NOT NULL,
        archivo_url text NOT NULL,
        fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Añadir página de gestión de temarios
add_action('admin_menu', 'zc_agregar_pagina_temarios');
function zc_agregar_pagina_temarios() {
    add_submenu_page(
        'edit.php?post_type=certificado',
        'Gestión de Temarios',
        '📚 Temarios',
        'manage_options',
        'zc-gestion-temarios',
        'zc_mostrar_pagina_temarios'
    );
}

// Funciones para gestionar temarios
function zc_obtener_todos_los_temarios() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zc_temarios';
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY nombre ASC");
}

function zc_obtener_temario_por_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zc_temarios';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

function zc_crear_temario($nombre, $archivo_url) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zc_temarios';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'nombre' => sanitize_text_field($nombre),
            'archivo_url' => esc_url_raw($archivo_url)
        ),
        array('%s', '%s')
    );
    
    return $result ? $wpdb->insert_id : false;
}

function zc_eliminar_temario($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zc_temarios';
    return $wpdb->delete($table_name, array('id' => $id), array('%d'));
}

function zc_actualizar_temario($id, $nombre, $archivo_url) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'zc_temarios';
    
    return $wpdb->update(
        $table_name,
        array(
            'nombre' => sanitize_text_field($nombre),
            'archivo_url' => esc_url_raw($archivo_url)
        ),
        array('id' => $id),
        array('%s', '%s'),
        array('%d')
    );
}

// Función helper para obtener URL del temario por ID
function zc_obtener_url_temario($temario_id) {
    if (empty($temario_id)) return '';
    
    $temario = zc_obtener_temario_por_id($temario_id);
    return $temario ? $temario->archivo_url : '';
}

// Página de gestión de temarios
function zc_mostrar_pagina_temarios() {
    // Procesar acciones
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'crear' && wp_verify_nonce($_POST['zc_temarios_nonce'], 'zc_temarios_action')) {
            $nombre = sanitize_text_field($_POST['nombre_temario']);
            $archivo_url = esc_url_raw($_POST['archivo_url']);
            
            if (!empty($nombre) && !empty($archivo_url)) {
                $resultado = zc_crear_temario($nombre, $archivo_url);
                if ($resultado) {
                    echo '<div class="notice notice-success"><p>✅ Temario creado exitosamente.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Error al crear el temario.</p></div>';
                }
            }
        }
        
        if ($_POST['accion'] === 'eliminar' && wp_verify_nonce($_POST['zc_temarios_nonce'], 'zc_temarios_action')) {
            $id = intval($_POST['temario_id']);
            $resultado = zc_eliminar_temario($id);
            if ($resultado) {
                echo '<div class="notice notice-success"><p>✅ Temario eliminado exitosamente.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Error al eliminar el temario.</p></div>';
            }
        }
        
        if ($_POST['accion'] === 'editar' && wp_verify_nonce($_POST['zc_temarios_nonce'], 'zc_temarios_action')) {
            $id = intval($_POST['temario_id']);
            $nombre = sanitize_text_field($_POST['nombre_temario']);
            $archivo_url = esc_url_raw($_POST['archivo_url']);
            
            if (!empty($nombre) && !empty($archivo_url)) {
                $resultado = zc_actualizar_temario($id, $nombre, $archivo_url);
                if ($resultado !== false) {
                    echo '<div class="notice notice-success"><p>✅ Temario actualizado exitosamente.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Error al actualizar el temario.</p></div>';
                }
            }
        }

        if ($_POST['accion'] === 'subir_multiples' && wp_verify_nonce($_POST['zc_temarios_nonce'], 'zc_temarios_action')) {
            if (empty($_FILES['temarios_pdf']['name']) || !is_array($_FILES['temarios_pdf']['name'])) {
                echo '<div class="notice notice-error"><p>❌ No se seleccionaron archivos.</p></div>';
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $ok = 0;
                $errores = array();
                $n = count($_FILES['temarios_pdf']['name']);
                for ($i = 0; $i < $n; $i++) {
                    if (empty($_FILES['temarios_pdf']['name'][$i]) || $_FILES['temarios_pdf']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $ftype = wp_check_filetype($_FILES['temarios_pdf']['name'][$i]);
                    if (empty($ftype['ext']) || strtolower($ftype['ext']) !== 'pdf') {
                        $errores[] = sprintf('No es PDF: %s', sanitize_file_name($_FILES['temarios_pdf']['name'][$i]));
                        continue;
                    }

                    $single = array(
                        'name'     => $_FILES['temarios_pdf']['name'][$i],
                        'type'     => $_FILES['temarios_pdf']['type'][$i],
                        'tmp_name' => $_FILES['temarios_pdf']['tmp_name'][$i],
                        'error'    => $_FILES['temarios_pdf']['error'][$i],
                        'size'     => $_FILES['temarios_pdf']['size'][$i],
                    );

                    $movefile = wp_handle_upload($single, array('test_form' => false));
                    if (isset($movefile['error'])) {
                        $errores[] = sanitize_file_name($single['name']) . ': ' . $movefile['error'];
                        continue;
                    }

                    $attachment = array(
                        'post_mime_type' => $movefile['type'],
                        'post_title'     => sanitize_text_field(pathinfo($single['name'], PATHINFO_FILENAME)),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                        'guid'           => $movefile['url'],
                    );
                    $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
                    if (is_wp_error($attachment_id) || !$attachment_id) {
                        $errores[] = sanitize_file_name($single['name']) . ': error al registrar en medios.';
                        continue;
                    }

                    $meta = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                    wp_update_attachment_metadata($attachment_id, $meta);

                    $url = wp_get_attachment_url($attachment_id);
                    if (empty($url)) {
                        $errores[] = sanitize_file_name($single['name']) . ': sin URL de adjunto.';
                        continue;
                    }

                    $base = pathinfo($single['name'], PATHINFO_FILENAME);
                    $nombre = sanitize_text_field($base);
                    if ($nombre === '') {
                        $nombre = 'Temario ' . $attachment_id;
                    }

                    if (zc_crear_temario($nombre, $url)) {
                        $ok++;
                    } else {
                        $errores[] = sanitize_file_name($single['name']) . ': no se pudo guardar en la lista.';
                    }
                }

                if ($ok > 0) {
                    echo '<div class="notice notice-success"><p>✅ Se agregaron <strong>' . (int) $ok . '</strong> temario(s) a la lista. Asígnalos desde cada certificado.</p></div>';
                }
                if (!empty($errores)) {
                    echo '<div class="notice notice-warning"><p><strong>Advertencias:</strong></p><ul style="list-style:disc;margin-left:1.5em;">';
                    foreach ($errores as $msg) {
                        echo '<li>' . esc_html($msg) . '</li>';
                    }
                    echo '</ul></div>';
                }
                if ($ok === 0 && empty($errores)) {
                    echo '<div class="notice notice-error"><p>❌ No se pudo importar ningún PDF válido.</p></div>';
                }
            }
        }
    }
    
    $temarios = zc_obtener_todos_los_temarios();
    ?>
    <div class="wrap">
        <h1>📚 Gestión de Temarios</h1>
        <p>Administra los temarios PDF que aparecerán como opciones en los certificados.</p>

        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <h2>📤 Subir varios PDF a la vez</h2>
            <p class="description">Los archivos se guardan en la biblioteca de medios y se añaden a la lista con el <strong>nombre del archivo</strong> (sin extensión). Luego puedes editar el nombre o asignar cada temario manualmente en cada certificado.</p>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('zc_temarios_action', 'zc_temarios_nonce'); ?>
                <input type="hidden" name="accion" value="subir_multiples">
                <table class="form-table">
                    <tr>
                        <th scope="row">Archivos PDF</th>
                        <td>
                            <input type="file" name="temarios_pdf[]" accept=".pdf,application/pdf" multiple required>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="📤 Importar a la lista de temarios">
                </p>
            </form>
        </div>
        
        <!-- Formulario para crear nuevo temario -->
        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <h2>➕ Crear Nuevo Temario</h2>
            <form method="post" action="">
                <?php wp_nonce_field('zc_temarios_action', 'zc_temarios_nonce'); ?>
                <input type="hidden" name="accion" value="crear">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Nombre del Temario</th>
                        <td>
                            <input type="text" name="nombre_temario" class="regular-text" placeholder="Ej: Curso Soldadura Básica" required>
                            <p class="description">Nombre descriptivo que aparecerá en el dropdown de certificados.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL del Archivo PDF</th>
                        <td>
                            <input type="url" name="archivo_url" class="regular-text" placeholder="https://validador.zenactivospa.cl/wp-content/uploads/..." required>
                            <p class="description">
                                <strong>💡 Cómo obtener la URL:</strong><br>
                                1. Ve a <strong>Medios → Añadir nuevo</strong><br>
                                2. Sube tu archivo PDF<br>
                                3. Copia la URL del archivo y pégala aquí
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="➕ Crear Temario">
                </p>
            </form>
        </div>
        
        <!-- Lista de temarios existentes -->
        <div class="card">
            <h2>📋 Temarios Disponibles (<?php echo count($temarios); ?>)</h2>
            
            <?php if (empty($temarios)): ?>
                <p>No hay temarios creados aún. ¡Crea el primero usando el formulario de arriba!</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Archivo PDF</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($temarios as $temario): ?>
                            <tr>
                                <td><?php echo esc_html($temario->id); ?></td>
                                <td><strong><?php echo esc_html($temario->nombre); ?></strong></td>
                                <td>
                                    <a href="<?php echo esc_url($temario->archivo_url); ?>" target="_blank" class="button button-small">
                                        📄 Ver PDF
                                    </a>
                                </td>
                                <td><?php echo esc_html(date('d/m/Y H:i', strtotime($temario->fecha_creacion))); ?></td>
                                <td>
                                    <button onclick="editarTemario(<?php echo $temario->id; ?>, '<?php echo esc_js($temario->nombre); ?>', '<?php echo esc_js($temario->archivo_url); ?>')" class="button button-small">
                                        ✏️ Editar
                                    </button>
                                    
                                    <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este temario?');">
                                        <?php wp_nonce_field('zc_temarios_action', 'zc_temarios_nonce'); ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="temario_id" value="<?php echo $temario->id; ?>">
                                        <input type="submit" class="button button-small" value="🗑️ Eliminar">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Modal para editar temario -->
        <div id="modal-editar-temario" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div style="background: white; margin: 5% auto; padding: 20px; width: 80%; max-width: 600px; border-radius: 5px;">
                <h3>✏️ Editar Temario</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('zc_temarios_action', 'zc_temarios_nonce'); ?>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="temario_id" id="editar_temario_id">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Nombre del Temario</th>
                            <td><input type="text" name="nombre_temario" id="editar_nombre" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row">URL del Archivo PDF</th>
                            <td><input type="url" name="archivo_url" id="editar_url" class="regular-text" required></td>
                        </tr>
                    </table>
                    
                    <p>
                        <input type="submit" class="button-primary" value="💾 Guardar Cambios">
                        <button type="button" onclick="cerrarModal()" class="button">❌ Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function editarTemario(id, nombre, url) {
        document.getElementById('editar_temario_id').value = id;
        document.getElementById('editar_nombre').value = nombre;
        document.getElementById('editar_url').value = url;
        document.getElementById('modal-editar-temario').style.display = 'block';
    }
    
    function cerrarModal() {
        document.getElementById('modal-editar-temario').style.display = 'none';
    }
    </script>
    <?php
}

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
    
    // 🔧 NUEVOS CAMPOS PARA FIRMAS PERSONALIZADAS
    $firma_director_url = get_post_meta($post->ID, '_certificado_firma_director_url', true);
    $firma_instructor_url = get_post_meta($post->ID, '_certificado_firma_instructor_url', true);
    
    // 📚 NUEVO CAMPO PARA TEMARIO DEL CURSO - SISTEMA DROPDOWN
    $temario_id = get_post_meta($post->ID, '_certificado_temario_id', true);
    
    wp_nonce_field('zc_final_guardar_datos', 'zc_final_nonce');

    // Se mantiene el campo de participante individual, que ahora puede ser opcional o usarse para el diploma
    $participante = get_post_meta($post->ID, '_certificado_participante', true);
    
    echo '<div class="zc-admin-section">';
    echo '<h3 class="datos-generales">Datos Generales</h3>';
    echo '<p><label><strong>Código de Verificación:</strong><br><input type="text" name="certificado_codigo" value="' . esc_attr($codigo) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Participante:</strong><br><input type="text" name="certificado_empresa" value="' . esc_attr($empresa) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Curso:</strong><br><input type="text" name="certificado_curso" value="' . esc_attr($curso) . '" style="width:100%;"></label></p>';
    echo '</div>';
    
    echo '<div class="zc-admin-section">';
    echo '<h3 class="datos-curso">Datos del Curso</h3>';
    echo '<p><label><strong>Duración (ej: 03 horas cronológicas):</strong><br><input type="text" name="certificado_duracion" value="' . esc_attr($duracion) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Fecha de Emisión:</strong><br><input type="date" name="certificado_fecha" value="' . esc_attr($fecha) . '"></label></p>';
    echo '<p><label><strong>Fecha de Realización:</strong><br><input type="date" name="certificado_fecha_realizacion" value="' . esc_attr($fecha_realizacion) . '"></label></p>';
    echo '<p><label><strong>Fecha de Expiración:</strong><br><input type="date" name="certificado_fecha_expiracion" value="' . esc_attr($fecha_expiracion) . '"></label></p>';
    echo '<p class="description">Si dejas la expiración vacía, se calculará sola (' . (int) zc_certificado_anos_vigencia_default() . ' años desde la realización; si no hay realización, desde la fecha de emisión). Aparece en el certificado y en el diploma.</p>';
    echo '<p><label><strong>Cliente:</strong><br><input type="text" name="certificado_oc_cliente" value="' . esc_attr($oc_cliente) . '" style="width:100%;"></label></p>';
    echo '</div>';

    echo '<div class="zc-admin-section">';
    echo '<h3 class="participantes">Participantes</h3>';
    echo '<p><label for="listado_participantes"><strong>Lista de Participantes:</strong></label><br>';
    echo '<textarea name="certificado_listado_participantes" id="listado_participantes" rows="10" style="width:100%; font-family: monospace;">' . esc_textarea($listado_participantes) . '</textarea>';
    echo '<div class="zc-participantes-excel-import" data-textarea-id="listado_participantes" style="margin:10px 0;padding:10px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">';
    echo '<p style="margin:0 0 8px;"><strong>Importar desde Excel:</strong> las columnas deben ir en este orden (A→G): <code>Nombre completo, RUT, Asistencia, Nota T., Nota S., Nota final, Aprobación</code>. Se usa la primera hoja del archivo.</p>';
    echo '<p style="margin:0 0 8px;">';
    echo '<input type="file" class="zc-pexi-file" accept=".xlsx,.xls" style="display:none" aria-hidden="true" />';
    echo '<button type="button" class="button button-secondary zc-pexi-trigger">📥 Cargar Excel (.xlsx / .xls)</button> ';
    echo '<span class="zc-pexi-msg" style="margin-left:8px;font-weight:600;"></span>';
    echo '</p>';
    echo '<p style="margin:0;font-size:12px;color:#50575e;">';
    echo '<label style="margin-right:16px;"><input type="checkbox" class="zc-pexi-skipheader" checked /> Primera fila es encabezado (omitir)</label>';
    echo '<label><input type="checkbox" class="zc-pexi-append" /> Agregar al final (si no está marcado, reemplaza el listado)</label>';
    echo '</p></div>';
    echo '<div class="zc-warning-box"><strong>Instrucciones:</strong> Ingrese un participante por línea. Use comas (,) para separar las columnas en el siguiente orden:<br><code>Nombre Completo,RUT,Asistencia,Nota T.,Nota S.,Nota Final,Aprobación</code><br>Ejemplo: <code>Apolinar Andrés Mendoza Cuno,22.626.949-9,100%,7.0,7.0,7.0,A</code></div>';

    echo '<p><label><strong>Nombre del Participante (para diploma individual):</strong><br><input type="text" name="certificado_participante" value="' . esc_attr($participante) . '" style="width:100%;"></label></p>';
    echo '<div class="zc-info-box"><h3>Información</h3>Este nombre se usará para generar el diploma individual. Si el certificado es solo para la empresa, puede dejarlo vacío.</div>';
    
    // 🎓 CAMPOS ESPECÍFICOS PARA EL DIPLOMA DEL PARTICIPANTE
    $participante_rut = get_post_meta($post->ID, '_certificado_participante_rut', true);
    $participante_asistencia = get_post_meta($post->ID, '_certificado_participante_asistencia', true);
    $participante_nota_final = get_post_meta($post->ID, '_certificado_participante_nota_final', true);
    $participante_aprobacion = get_post_meta($post->ID, '_certificado_participante_aprobacion', true);
    
    echo '<h3 class="diploma-data">📋 Datos del Diploma</h3>';
    echo '<div class="zc-diploma-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">';
    echo '<p><label><strong>RUT del Participante:</strong><br><input type="text" name="certificado_participante_rut" value="' . esc_attr($participante_rut) . '" style="width:100%;" placeholder="12.345.678-9"></label></p>';
    echo '<p><label><strong>Asistencia:</strong><br><input type="text" name="certificado_participante_asistencia" value="' . esc_attr($participante_asistencia) . '" style="width:100%;" placeholder="100%"></label></p>';
    echo '<p><label><strong>Nota Final:</strong><br><input type="text" name="certificado_participante_nota_final" value="' . esc_attr($participante_nota_final) . '" style="width:100%;" placeholder="7.0"></label></p>';
    echo '<p><label><strong>Estado de Aprobación:</strong><br><input type="text" name="certificado_participante_aprobacion" value="' . esc_attr($participante_aprobacion) . '" style="width:100%;" placeholder="Aprobado"></label></p>';
    echo '</div>';
    echo '<div class="zc-info-box"><h3>💡 Nota</h3>Estos datos aparecerán en el diploma del participante. Si están vacíos, se usarán valores por defecto (100%, 7.0, Aprobado).</div>';
    echo '</div>';

    echo '<div class="zc-admin-section">';
    echo '<h3 class="firmas">Firmas</h3>';
    echo '<p><label><strong>Nombre del Director:</strong><br><input type="text" name="certificado_director" value="' . esc_attr($director) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Instructor:</strong><br><input type="text" name="certificado_instructor" value="' . esc_attr($instructor) . '" style="width:100%;"></label></p>';
    
    echo '<div class="zc-info-box">';
    echo '<h3>📝 Firmas Personalizadas (Opcional)</h3>';
    echo '<p>Puedes agregar URLs de imágenes PNG para personalizar las firmas en los PDFs. Si no especificas URLs, se usará la firma por defecto.</p>';
    echo '</div>';
    
    echo '<p><label><strong>URL Firma del Director (PNG):</strong><br><input type="url" name="certificado_firma_director_url" value="' . esc_attr($firma_director_url) . '" style="width:100%;" placeholder="https://ejemplo.com/firma-director.png"></label></p>';
    echo '<p><label><strong>URL Firma del Relator (PNG):</strong><br><input type="url" name="certificado_firma_instructor_url" value="' . esc_attr($firma_instructor_url) . '" style="width:100%;" placeholder="https://ejemplo.com/firma-relator.png"></label></p>';
    
    echo '<div class="zc-warning-box">';
    echo '<strong>💡 URLs de Firmas por Defecto:</strong><br>';
    echo '• <strong>Firma Director:</strong> <code>https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-director-final-1.png</code><br>';
    echo '• <strong>Firma Relator:</strong> <code>https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-relator-fina.png</code><br>';
    echo '• Para cambiar las firmas globalmente, reemplaza estas URLs en el código o usa los campos de arriba para certificados específicos.<br>';
    echo '• <strong>Tamaño recomendado:</strong> 400x200 píxeles, fondo transparente PNG';
    echo '</div>';
    
    // 📚 SECCIÓN DE TEMARIO DEL CURSO - NUEVO SISTEMA DROPDOWN
    echo '<div class="zc-admin-section">';
    echo '<h3>📚 Temario del Curso (Opcional)</h3>';
    echo '<p>Selecciona un temario de la lista o gestiona temarios desde <strong>Certificados → 📚 Temarios</strong>.</p>';
    
    // Obtener temarios disponibles y temario seleccionado actual
    $temarios_disponibles = zc_obtener_todos_los_temarios();
    $temario_seleccionado = get_post_meta($post->ID, '_certificado_temario_id', true);
    
    echo '<p><label><strong>Temario:</strong><br>';
    echo '<select name="certificado_temario_id" style="width:100%;">';
    echo '<option value="">Sin temario</option>';
    
    foreach ($temarios_disponibles as $temario) {
        $selected = ($temario_seleccionado == $temario->id) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($temario->id) . '" ' . $selected . '>' . esc_html($temario->nombre) . '</option>';
    }
    
    echo '</select></label></p>';
    
    echo '<div class="zc-info-box">';
    echo '<strong>💡 Gestión de temarios:</strong><br>';
    echo '1. Ve a <strong>Certificados → 📚 Temarios</strong><br>';
    echo '2. Sube tus archivos PDF y asígnales nombres<br>';
    echo '3. Regresa aquí y selecciona el temario apropiado<br>';
    echo '4. Los visitantes verán un botón "📚 Ver Temario del Curso" en el verificador';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    
    echo '<div class="zc-url-section">';
    echo '<h3>URLs de Documentos Generados</h3>';
    echo '<p><label><strong>URL del Certificado (Automático):</strong><br><input type="url" value="' . esc_attr($pdf_url) . '" style="width:100%;" readonly></label></p>';
    echo '<p><label><strong>URL del Diploma (Automático):</strong><br><input type="url" value="' . esc_attr($diploma_url) . '" style="width:100%;" readonly></label></p>';
    echo '</div>';
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
    
    // 🔧 NUEVOS CAMPOS PARA FIRMAS PERSONALIZADAS
    if (isset($_POST['certificado_firma_director_url'])) update_post_meta($post_id, '_certificado_firma_director_url', esc_url_raw($_POST['certificado_firma_director_url']));
    if (isset($_POST['certificado_firma_instructor_url'])) update_post_meta($post_id, '_certificado_firma_instructor_url', esc_url_raw($_POST['certificado_firma_instructor_url']));
    
    // 📚 Guardar ID del temario seleccionado
    if (isset($_POST['certificado_temario_id'])) {
        $temario_id = intval($_POST['certificado_temario_id']);
        update_post_meta($post_id, '_certificado_temario_id', $temario_id);
        
        // Migración: limpiar URL antigua si existe
        delete_post_meta($post_id, '_certificado_temario_url');
    }
    
    // 🎓 CAMPOS ESPECÍFICOS PARA EL DIPLOMA DEL PARTICIPANTE
    if (isset($_POST['certificado_participante_rut'])) update_post_meta($post_id, '_certificado_participante_rut', sanitize_text_field($_POST['certificado_participante_rut']));
    if (isset($_POST['certificado_participante_asistencia'])) update_post_meta($post_id, '_certificado_participante_asistencia', sanitize_text_field($_POST['certificado_participante_asistencia']));
    if (isset($_POST['certificado_participante_nota_final'])) update_post_meta($post_id, '_certificado_participante_nota_final', sanitize_text_field($_POST['certificado_participante_nota_final']));
    if (isset($_POST['certificado_participante_aprobacion'])) update_post_meta($post_id, '_certificado_participante_aprobacion', sanitize_text_field($_POST['certificado_participante_aprobacion']));
    
    zc_certificado_autocompletar_meta_expiracion($post_id, '_certificado_fecha_realizacion', '_certificado_fecha_expiracion', '_certificado_fecha');
    
    // 🔄 SINCRONIZACIÓN INDIVIDUAL → GRUPAL: Si este certificado proviene de un grupo, sincronizar datos comunes
    $post_id_grupal = get_post_meta($post_id, '_certificado_origen_grupal', true);
    if (!empty($post_id_grupal)) {
        zc_individual_sincronizar_certificado_grupal($post_id, $post_id_grupal);
    }
}

function zc_individual_sincronizar_certificado_grupal($post_id_individual, $post_id_grupal) {
    // 🛡️ Protección contra loops infinitos de sincronización
    global $zc_sincronizando;
    if (!empty($zc_sincronizando[$post_id_grupal])) {
        return; // Ya se está sincronizando este certificado grupal
    }
    
    // Verificar que el certificado grupal existe
    if (!get_post($post_id_grupal)) {
        return; // El certificado grupal no existe
    }
    
    // Obtener datos del certificado individual que pueden afectar al grupal
    $curso = get_post_meta($post_id_individual, '_certificado_curso', true);
    $fecha = get_post_meta($post_id_individual, '_certificado_fecha', true);
    $empresa = get_post_meta($post_id_individual, '_certificado_empresa', true);
    $director = get_post_meta($post_id_individual, '_certificado_director', true);
    $instructor = get_post_meta($post_id_individual, '_certificado_instructor', true);
    $duracion = get_post_meta($post_id_individual, '_certificado_duracion', true);
    $fecha_realizacion = get_post_meta($post_id_individual, '_certificado_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post_id_individual, '_certificado_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post_id_individual, '_certificado_oc_cliente', true);
    $temario_individual_id = get_post_meta($post_id_individual, '_certificado_temario_id', true);
    
    // Actualizar certificado grupal solo con datos comunes (no específicos del participante)
    if (!empty($curso)) update_post_meta($post_id_grupal, '_certificado_grupal_curso', $curso);
    if (!empty($fecha)) update_post_meta($post_id_grupal, '_certificado_grupal_fecha', $fecha);
    if (!empty($empresa)) update_post_meta($post_id_grupal, '_certificado_grupal_empresa', $empresa);
    if (!empty($director)) update_post_meta($post_id_grupal, '_certificado_grupal_director', $director);
    if (!empty($instructor)) update_post_meta($post_id_grupal, '_certificado_grupal_instructor', $instructor);
    if (!empty($duracion)) update_post_meta($post_id_grupal, '_certificado_grupal_duracion', $duracion);
    if (!empty($fecha_realizacion)) update_post_meta($post_id_grupal, '_certificado_grupal_fecha_realizacion', $fecha_realizacion);
    if (!empty($fecha_expiracion)) update_post_meta($post_id_grupal, '_certificado_grupal_fecha_expiracion', $fecha_expiracion);
    if (!empty($oc_cliente)) update_post_meta($post_id_grupal, '_certificado_grupal_oc_cliente', $oc_cliente);
    
    // 📚 Sincronizar temario si está especificado
    if (!empty($temario_individual_id)) update_post_meta($post_id_grupal, '_certificado_grupal_temario_id', $temario_individual_id);
    
    // 🔄 ACTUALIZAR LISTA DE PARTICIPANTES EN EL CERTIFICADO GRUPAL
    zc_grupal_actualizar_lista_participantes($post_id_grupal);
    
    // Regenerar PDF del certificado grupal con datos actualizados
    $post_grupal = get_post($post_id_grupal);
    if ($post_grupal) {
        zc_grupal_generar_pdf_manual($post_id_grupal, $post_grupal);
    }
}

function zc_grupal_actualizar_lista_participantes($post_id_grupal) {
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
        return; // No hay certificados individuales
    }

    // Reconstruir la lista de participantes basada en los certificados individuales actuales
    $nueva_lista_participantes = array();
    
    foreach ($certificados_individuales as $cert_individual) {
        $nombre = get_post_meta($cert_individual->ID, '_certificado_participante', true);
        $rut = get_post_meta($cert_individual->ID, '_certificado_participante_rut', true) ?: '';
        $asistencia = get_post_meta($cert_individual->ID, '_certificado_participante_asistencia', true) ?: '100%';
        $nota_teorica = get_post_meta($cert_individual->ID, '_certificado_participante_nota_teorica', true) ?: '7.0';
        $nota_practica = get_post_meta($cert_individual->ID, '_certificado_participante_nota_practica', true) ?: '7.0';
        $nota_final = get_post_meta($cert_individual->ID, '_certificado_participante_nota_final', true) ?: '7.0';
        $aprobacion = get_post_meta($cert_individual->ID, '_certificado_participante_aprobacion', true) ?: 'Aprobado';
        
        // Crear línea en formato CSV
        if (!empty($nombre)) {
            $linea_participante = sprintf(
                '%s,%s,%s,%s,%s,%s,%s',
                $nombre,
                $rut,
                $asistencia,
                $nota_teorica,
                $nota_practica,
                $nota_final,
                $aprobacion
            );
            $nueva_lista_participantes[] = $linea_participante;
        }
    }
    
    // Actualizar la lista de participantes en el certificado grupal
    $lista_actualizada = implode("\n", $nueva_lista_participantes);
    update_post_meta($post_id_grupal, '_certificado_grupal_listado_participantes', $lista_actualizada);
}

// =============================================================================
// PARTE 2.5: LIMPIEZA AUTOMÁTICA DE CERTIFICADOS INDIVIDUALES
// =============================================================================

// Limpiar certificados individuales cuando se elimina un certificado grupal
add_action('before_delete_post', 'zc_grupal_limpiar_certificados_individuales');
function zc_grupal_limpiar_certificados_individuales($post_id) {
    // Solo aplicar a certificados grupales
    if (get_post_type($post_id) !== 'certificado_grupal') {
        return;
    }
    
    // Buscar y eliminar todos los certificados individuales de este grupo
    $certificados_individuales = get_posts(array(
        'post_type' => 'certificado',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_certificado_origen_grupal',
                'value' => $post_id,
                'compare' => '='
            )
        )
    ));
    
    foreach ($certificados_individuales as $cert_individual) {
        // Eliminar certificado individual (esto también eliminará sus meta automáticamente)
        wp_delete_post($cert_individual->ID, true);
    }
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
            // 🚀 OPTIMIZACIONES PARA ARCHIVOS GRANDES
            ini_set('max_execution_time', 1800); // 30 minutos
            ini_set('memory_limit', '1024M'); // 1GB RAM
            
            $csv_file = $_FILES['csv_file']['tmp_name']; 
            $file_handle = fopen($csv_file, 'r'); 
            $headers = fgetcsv($file_handle, 1024, ','); 
            $header_map = array_flip($headers); 
            $importados = 0; 
            $errores = 0;
            
            // Mostrar progreso
            echo '<div id="import-progress" style="background:#f1f1f1;padding:10px;margin:10px 0;">';
            echo '<div style="background:#0073aa;height:20px;width:0%;transition:width 0.3s;" id="progress-bar"></div>';
            echo '<p id="progress-text">Iniciando importación...</p></div>';
            echo '<script>
                function updateProgress(current, total) {
                    const percent = Math.round((current / total) * 100);
                    document.getElementById("progress-bar").style.width = percent + "%";
                    document.getElementById("progress-text").innerHTML = "Procesando: " + current + "/" + total + " (" + percent + "%)";
                }
            </script>';
            
            // Contar total de registros
            $total_registros = 0;
            while (fgetcsv($file_handle, 1024, ',') !== FALSE) {
                $total_registros++;
            }
            rewind($file_handle);
            fgetcsv($file_handle, 1024, ','); // Saltar headers nuevamente 
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
                    
                    // 🚀 OPTIMIZACIONES PARA ARCHIVOS GRANDES
                    // Mostrar progreso cada 10 registros
                    if ($importados % 10 == 0) {
                        echo '<script>updateProgress(' . $importados . ', ' . $total_registros . ');</script>';
                        echo str_pad('', 4096) . "\n"; // Forzar flush del buffer
                        flush();
                        ob_flush();
                    }
                    
                    // Limpiar memoria cada 25 registros
                    if ($importados % 25 == 0) {
                        wp_cache_flush();
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                        // Pausa de 1 segundo cada 25 registros para no sobrecargar
                        sleep(1);
                    }
                } else { 
                    $errores++; 
                } 
            } 
            fclose($file_handle); 
            echo '<script>updateProgress(' . $total_registros . ', ' . $total_registros . ');</script>';
            echo '<div class="notice notice-success is-dismissible"><p><strong>🎉 Importación completada:</strong> ' . $importados . ' certificados importados exitosamente. ' . $errores . ' errores.</p></div>'; 
        } else { 
            echo '<div class="notice notice-error is-dismissible"><p>Por favor, selecciona un archivo CSV.</p></div>';
        } 
    } 
    echo '<div class="notice notice-warning"><p><strong>⚠️ Archivos grandes (>100 registros):</strong> La importación puede tomar mucho tiempo. Para archivos de 500+ registros, se recomienda dividir en lotes de 50 registros.</p></div>';
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

// Registro en init (prioridad tardía) para no quedar por debajo de otros plugins que
// registren el mismo tag vacío, y sin "ejecutar solo una vez": un segundo do_shortcode
// en la misma petición (widget + contenido, page builder) dejaba solo un comentario HTML.
add_action('init', 'zc_register_shortcode_verificador_certificados', 20);
function zc_register_shortcode_verificador_certificados() {
    add_shortcode('verificador_de_certificados', 'zc_final_funcion_verificadora');
}

function zc_final_funcion_verificadora() { 
    ob_start(); 
    
    // 🎨 AGREGAR ESTILOS CSS PARA EL VERIFICADOR
    ?>
    <style>
    .zc-validador-container {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .zc-validador-container h2, 
    .zc-validador-container h3, 
    .zc-validador-container h4 {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        color: #2c3e50;
        margin-bottom: 15px;
    }
    
    .zc-validador-container p {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        line-height: 1.6;
        color: #555;
    }
    
    .zc-form-verificacion {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 8px;
        margin: 20px 0;
        border-left: 4px solid #28a745;
    }
    
    .zc-input-codigo {
        width: 100%;
        padding: 12px 15px;
        font-size: 16px !important;
        font-family: 'Courier New', monospace !important;
        border: 2px solid #ddd;
        border-radius: 5px;
        margin: 0;
        text-transform: uppercase;
        display: block;
        box-sizing: border-box;
    }
    
    .zc-btn-verificar {
        background: #28a745;
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 5px;
        font-size: 16px !important;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        cursor: pointer;
        transition: background 0.3s;
        display: inline-block;
        width: 100%;
    }
    
    .zc-btn-verificar:hover {
        background: #218838;
    }
    
    .zc-resultado-valido {
        background: #ffffffff;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .zc-resultado-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .zc-info-certificado {
        background: white;
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
        border-left: 4px solid #007bff;
    }
    
    .zc-instrucciones {
        background: #e9ecef;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .zc-instrucciones h4 {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 18px;
        font-weight: 600;
    }
    
    .zc-instrucciones ul {
        padding-left: 20px;
    }
    
    .zc-instrucciones li {
        margin-bottom: 8px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }
    
    .zc-botones-descarga {
        margin: 20px 0;
        text-align: center;
    }
    
    .zc-btn-descargar {
        display: inline-block;
        padding: 12px 25px;
        margin: 5px 10px;
        background: #007bff;
        color: white !important;
        text-decoration: none !important;
        border-radius: 5px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        transition: background 0.3s;
    }
    
    .zc-btn-descargar:hover {
        background: #0056b3;
        color: white !important;
    }
    
    .zc-pdf-viewer {
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .zc-pdf-viewer h4 {
        background: #f8f9fa;
        margin: 0;
        padding: 15px;
        border-bottom: 1px solid #ddd;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }
    
    .zc-pdf-viewer iframe {
        border: none;
        display: block;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .zc-validador-container {
            padding: 10px;
            margin: 5px;
        }
        
        .zc-form-verificacion {
            padding: 15px;
            margin: 15px 0;
        }
        
        .zc-instrucciones {
            padding: 15px;
            margin-top: 15px;
        }
        
        .zc-input-codigo {
            width: 100%;
            max-width: none;
            margin: 0 0 15px 0;
        }
        
        .zc-btn-verificar {
            width: 100%;
        }
    }
    </style>
    <?php
    
    // 🔧 DEBUG: Mostrar información de debug para URLs (solo para administradores)
    if (current_user_can('administrator') && isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo '<div style="background:#f0f0f0;padding:10px;margin:10px 0;border:1px solid #ddd;">';
        echo '<h4>🔧 Información de Debug (solo administradores)</h4>';
        echo '<p><strong>GET parameter "id":</strong> ' . (isset($_GET['id']) ? esc_html($_GET['id']) : 'No definido') . '</p>';
        echo '<p><strong>URL actual:</strong> ' . esc_html($_SERVER['REQUEST_URI']) . '</p>';
        echo '<p><strong>URL completa:</strong> ' . esc_html('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '</p>';
        echo '</div>';
    }
    ?>
    <div class="zc-validador-container">
    <?php
    
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
                ?>
                
                <div class="zc-resultado-valido">
                    <h2>¡Certificado de capacitación válido!</h2>
                    <div class="zc-info-certificado">
                        <p><strong>Participante:</strong> <?php echo esc_html($participante); ?></p>
                        <p><strong>Curso:</strong> <?php echo esc_html($curso); ?></p>
                        <p><strong>Fecha de Emisión:</strong> <?php echo esc_html($fecha); ?></p>
                        <p><strong>Código de Verificación:</strong> <?php echo esc_html($codigo_certificado); ?></p>
                    </div>
                    
                    <?php if (!empty($pdf_url) || !empty($diploma_url)): ?>
                        <div class="zc-botones-descarga">
                            <?php if (!empty($pdf_url)): ?>
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-certificado">
                                    Descargar Certificado
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($diploma_url)): ?>
                                <a href="<?php echo esc_url($diploma_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-diploma">
                                    Descargar Diploma
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // 📚 Botón de temario - NUEVO SISTEMA DROPDOWN
                            $temario_id = get_post_meta(get_the_ID(), '_certificado_temario_id', true);
                            $temario_url = zc_obtener_url_temario($temario_id);
                            
                            // Migración: Si no hay ID pero hay URL antigua, usarla
                            if (empty($temario_url)) {
                                $temario_url = get_post_meta(get_the_ID(), '_certificado_temario_url', true);
                            }
                            
                            if (!empty($temario_url)): ?>
                                <a href="<?php echo esc_url($temario_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-temario" style="background-color: #28a745; border-color: #28a745;">
                                    📚 Ver Temario del Curso
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($pdf_url)): ?>
                            <div class="zc-pdf-viewer">
                                <h4><i class="fas fa-file-pdf"></i> Visualización del Certificado</h4>
                                <iframe src="https://docs.google.com/gview?url=<?php echo urlencode($pdf_url); ?>&embedded=true" width="100%" height="600"></iframe>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php
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
                    
                    $diplomas_url = zc_grupal_obtener_url_diplomas_compilados(get_the_ID());
                    ?>
                    
                    <div class="zc-resultado-grupal">
                        <h2>¡Certificado Grupal Válido!</h2>
                        <div class="zc-info-certificado">
                            <p><strong>Empresa:</strong> <?php echo esc_html($empresa); ?></p>
                            <p><strong>Curso:</strong> <?php echo esc_html($curso); ?></p>
                            <p><strong>Fecha de Emisión:</strong> <?php echo esc_html($fecha); ?></p>
                            <p><strong>Participantes:</strong> <?php echo $num_participantes; ?> personas</p>
                            <p><strong>Código de Verificación:</strong> <?php echo esc_html($codigo_certificado); ?></p>
                        </div>
                        
                        <?php if (!empty($pdf_url)): ?>
                            <div class="zc-botones-descarga">
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-grupal">
                                    Descargar Certificado Grupal
                                </a>
                                
                                <?php if (!empty($diplomas_url)): ?>
                                    <a href="<?php echo esc_url($diplomas_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-diploma">
                                        Ver Diplomas del Grupo
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                // 📚 Botón de temario grupal - NUEVO SISTEMA DROPDOWN
                                $temario_grupal_id = get_post_meta(get_the_ID(), '_certificado_grupal_temario_id', true);
                                $temario_grupal_url = zc_obtener_url_temario($temario_grupal_id);
                                
                                // Migración: Si no hay ID pero hay URL antigua, usarla
                                if (empty($temario_grupal_url)) {
                                    $temario_grupal_url = get_post_meta(get_the_ID(), '_certificado_grupal_temario_url', true);
                                }
                                
                                if (!empty($temario_grupal_url)): ?>
                                    <a href="<?php echo esc_url($temario_grupal_url); ?>" target="_blank" class="zc-btn-descargar zc-btn-temario" style="background-color: #28a745; border-color: #28a745;">
                                        📚 Ver Temario del Curso
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="zc-pdf-viewer">
                                <h4><i class="fas fa-file-pdf"></i> Visualización del Certificado Grupal</h4>
                                <iframe src="https://docs.google.com/gview?url=<?php echo urlencode($pdf_url); ?>&embedded=true" width="100%" height="600"></iframe>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // 🎯 MOSTRAR CERTIFICADOS INDIVIDUALES DE LOS PARTICIPANTES
                        $certificados_individuales = get_posts(array(
                            'post_type' => 'certificado',
                            'posts_per_page' => -1,
                            'meta_query' => array(
                                array(
                                    'key' => '_certificado_origen_grupal',
                                    'value' => get_the_ID(),
                                    'compare' => '='
                                )
                            ),
                            'orderby' => 'meta_value',
                            'meta_key' => '_certificado_codigo',
                            'order' => 'ASC'
                        ));
                        
                        // 🔧 Si no hay certificados individuales, generarlos automáticamente
                        if (empty($certificados_individuales)) {
                            zc_grupal_procesar_participantes(get_the_ID());
                            
                            // Volver a buscar después de generar
                            $certificados_individuales = get_posts(array(
                                'post_type' => 'certificado',
                                'posts_per_page' => -1,
                                'meta_query' => array(
                                    array(
                                        'key' => '_certificado_origen_grupal',
                                        'value' => get_the_ID(),
                                        'compare' => '='
                                    )
                                ),
                                'orderby' => 'meta_value',
                                'meta_key' => '_certificado_codigo',
                                'order' => 'ASC'
                            ));
                        }
                        
                        if (!empty($certificados_individuales)): ?>
                            <div class="zc-certificados-individuales" style="margin-top: 30px;">
                                <h3>📋 Certificados Individuales de los Participantes</h3>
                                <div class="zc-lista-participantes" style="display: grid; gap: 15px; margin-top: 20px;">
                                    <?php foreach ($certificados_individuales as $cert_individual): 
                                        $participante = get_post_meta($cert_individual->ID, '_certificado_participante', true);
                                        $codigo_individual = get_post_meta($cert_individual->ID, '_certificado_codigo', true);
                                        $pdf_individual = get_post_meta($cert_individual->ID, '_certificado_pdf_url', true);
                                        $diploma_individual = get_post_meta($cert_individual->ID, '_certificado_diploma_url', true);
                                    ?>
                                        <div class="zc-participante-item" style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <strong>👤 <?php echo esc_html($participante); ?></strong>
                                                    <br><small>Código: <?php echo esc_html($codigo_individual); ?></small>
                                                </div>
                                                <div style="display: flex; gap: 10px;">
                                                    <?php if (!empty($pdf_individual)): ?>
                                                        <a href="<?php echo esc_url($pdf_individual); ?>" target="_blank" 
                                                           class="zc-btn-mini" style="padding: 5px 10px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                            📄 Certificado
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($diploma_individual)): ?>
                                                        <a href="<?php echo esc_url($diploma_individual); ?>" target="_blank" 
                                                           class="zc-btn-mini" style="padding: 5px 10px; background: #d63638; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                            🎓 Diploma
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                } 
            } else {
                // ❌ NO ENCONTRADO EN NINGÚN LUGAR
                ?>
                <div class="zc-resultado-error">
                    <h2>Código No Encontrado</h2>
                    <p>El código de verificación <strong><?php echo esc_html($codigo_certificado); ?></strong> no corresponde a ningún certificado válido en nuestro sistema.</p>
                    <p>Por favor, verifique que el código esté escrito correctamente.</p>
                </div>
                <?php
            }
            wp_reset_postdata(); 
        }
        wp_reset_postdata(); 
    } 
    ?>
    
    <div class="zc-form-verificacion">
        <h3>🔍 Verificador de Certificados</h3>
        <p>Ingresa el código único de tu certificado para verificar su autenticidad</p>
        
        <form action="<?php echo esc_url(get_permalink()); ?>" method="get" style="margin-top: 20px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <input type="text" 
                       id="id" 
                       name="id" 
                       value="<?php echo isset($_GET['id']) ? esc_attr($_GET['id']) : ''; ?>" 
                       placeholder="Ej: ZA2024-001, base-1, etc." 
                       class="zc-input-codigo"
                       required>
            </div>
            <div style="text-align: center;">
                <button type="submit" class="zc-btn-verificar">✓ Verificar Certificado</button>
            </div>
        </form>
        
        <?php if (!isset($_GET['id']) || empty($_GET['id'])): ?>
            <div class="zc-instrucciones">
                <h4>• Instrucciones de Uso:</h4>
                <ul>
                    <li>📱<strong>Escanea el código QR</strong> de tu certificado con tu teléfono</li>
                    <li>⌨<strong>O ingresa manualmente</strong> el código que aparece en tu certificado</li>
                    <li>✓<strong>El sistema verificará automáticamente</strong> la autenticidad del documento</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Fin del Verificador de Certificados Zen Activo -->
    <?php
    
    $output = ob_get_clean();
    
    // 🔧 LIMPIAR ESPACIOS EN BLANCO EXTRAS
    $output = trim($output);
    
    return $output;
}

// =============================================================================
// Fechas de vigencia: realización, expiración (+N años si no hay expiración manual)
// =============================================================================

function zc_certificado_anos_vigencia_default() {
    $n = (int) apply_filters('zc_certificado_anos_vigencia', 2);
    return $n >= 1 ? $n : 2;
}

function zc_certificado_formato_fecha_pdf($fecha_ymd) {
    $fecha_ymd = trim((string) $fecha_ymd);
    if ($fecha_ymd === '') {
        return '';
    }
    $ts = strtotime($fecha_ymd . ' 12:00:00');
    if ($ts === false) {
        return $fecha_ymd;
    }
    return date_i18n('d/m/Y', $ts);
}

/**
 * @return array{realizacion_ymd:string,expiracion_ymd:string,realizacion_txt:string,expiracion_txt:string,exp_automatica:bool,vigencia_estandar:bool,anos_vigencia:int}
 */
function zc_certificado_resolver_fechas_vigencia($fecha_realizacion, $fecha_expiracion, $fecha_emision = '') {
    $real = trim((string) $fecha_realizacion);
    if ($real === '') {
        $real = trim((string) $fecha_emision);
    }
    $exp = trim((string) $fecha_expiracion);
    $ts_real = ($real !== '') ? strtotime($real . ' 12:00:00') : false;
    $anos = zc_certificado_anos_vigencia_default();
    $exp_automatica = false;
    if ($exp === '' && $ts_real) {
        $exp = date('Y-m-d', strtotime('+' . $anos . ' years', $ts_real));
        $exp_automatica = true;
    }
    $ts_exp = ($exp !== '') ? strtotime($exp . ' 12:00:00') : false;
    $vigencia_estandar = false;
    if ($ts_real && $ts_exp) {
        $expected = strtotime('+' . $anos . ' years', $ts_real);
        $vigencia_estandar = (abs($ts_exp - $expected) < 86400);
    }
    return array(
        'realizacion_ymd'   => $real,
        'expiracion_ymd'    => $exp,
        'realizacion_txt'   => zc_certificado_formato_fecha_pdf($real),
        'expiracion_txt'    => zc_certificado_formato_fecha_pdf($exp),
        'exp_automatica'    => $exp_automatica,
        'vigencia_estandar' => $vigencia_estandar,
        'anos_vigencia'     => $anos,
    );
}

function zc_certificado_fechas_desde_post_id($post_id) {
    return zc_certificado_resolver_fechas_vigencia(
        get_post_meta($post_id, '_certificado_fecha_realizacion', true),
        get_post_meta($post_id, '_certificado_fecha_expiracion', true),
        get_post_meta($post_id, '_certificado_fecha', true)
    );
}

function zc_certificado_grupal_fechas_desde_post_id($post_id) {
    return zc_certificado_resolver_fechas_vigencia(
        get_post_meta($post_id, '_certificado_grupal_fecha_realizacion', true),
        get_post_meta($post_id, '_certificado_grupal_fecha_expiracion', true),
        get_post_meta($post_id, '_certificado_grupal_fecha', true)
    );
}

/** Si la expiración está vacía, guarda la calculada desde realización (o emisión). */
function zc_certificado_autocompletar_meta_expiracion($post_id, $key_real, $key_exp, $key_emision) {
    $exp = trim((string) get_post_meta($post_id, $key_exp, true));
    if ($exp !== '') {
        return;
    }
    $res = zc_certificado_resolver_fechas_vigencia(
        get_post_meta($post_id, $key_real, true),
        '',
        get_post_meta($post_id, $key_emision, true)
    );
    if ($res['expiracion_ymd'] !== '') {
        update_post_meta($post_id, $key_exp, $res['expiracion_ymd']);
    }
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
        $image_file = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/hoja-membretada-FINAL-scaled.png';
        
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
    $director = get_post_meta($post_id, '_certificado_director', true) ?: '';
    $instructor = get_post_meta($post_id, '_certificado_instructor', true) ?: '';
    $duracion = get_post_meta($post_id, '_certificado_duracion', true);
    $fechas_cert = zc_certificado_fechas_desde_post_id($post_id);
    $fecha_realizacion = $fechas_cert['realizacion_txt'];
    $fecha_expiracion = $fechas_cert['expiracion_txt'];
    $oc_cliente = get_post_meta($post_id, '_certificado_oc_cliente', true);
    $listado_participantes_raw = get_post_meta($post_id, '_certificado_listado_participantes', true);
    $verification_url = 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/?id=' . urlencode($codigo);
    
    // 🔧 OBTENER URLs DE FIRMAS PERSONALIZADAS (con fallback a firma por defecto)
    $firma_director_url = get_post_meta($post_id, '_certificado_firma_director_url', true);
    $firma_instructor_url = get_post_meta($post_id, '_certificado_firma_instructor_url', true);
    
    // URLs por defecto específicas para director e instructor
    $firma_director_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-director-final-1.png';
    $firma_instructor_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-relator-fina.png';
    
    if (empty($firma_director_url)) $firma_director_url = $firma_director_default;
    if (empty($firma_instructor_url)) $firma_instructor_url = $firma_instructor_default;

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
    $texto_dinamico = "Se certifica que <b>$empresa</b> participó en la Capacitación de <b>$curso</b>, cumpliendo con los requisitos de asistencia y aprobación establecidos.";
    $pdf->writeHTMLCell(0, 0, '', '', $texto_dinamico, 0, 1, 0, true, 'L', true);
    $pdf->Ln(10);

    // --- TABLA DE DATOS PRINCIPALES ---
    $pdf->SetFont($font, '', 10);
    $tbl_datos = <<<EOD
    <table border="1" cellpadding="6" cellspacing="0" style="border-color:#000;">
        <tr>
            <td width="30%" style="background-color:#e0e0e0;"><b>Nombre del Participante</b></td>
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
            <td style="background-color:#e0e0e0;"><b>Cliente</b></td>
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

    // --- SECCIÓN DE FIRMAS CON IMAGEN PNG Y ALINEACIÓN VERTICAL MEJORADA ---
    // Se asegura de que las firmas no queden cortadas al final de una página
    if ($pdf->GetY() > ($pdf->getPageHeight() - 80)) {
        $pdf->AddPage();
    }
    $pdf->Ln(20);

    $yFirmas = $pdf->GetY();
    $firmaDirectorX = 30;
    $firmaInstructorX = 210 - 30 - 80;
    $firmaWidth = 80;
    $firmaHeight = 25;
    
    // 🎯 USAR FIRMAS PERSONALIZADAS O POR DEFECTO
    // Las URLs ya están definidas arriba con fallback automático
    
    // FIRMA DEL DIRECTOR (lado izquierdo) - Imagen siempre se muestra
    $pdf->SetFont($font, 'B', 11);
    $pdf->SetTextColor(50, 50, 50);
    
    // Imagen de firma del director (personalizada o por defecto) - Ancho fijo, alto proporcional
    $pdf->Image($firma_director_url, $firmaDirectorX + 10, $yFirmas, 60, 0, '', '', '', false, 300, '', false, false, 0);
    
    // Posicionar línea debajo de la firma
    $yLinea = $yFirmas + 22;
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + $firmaWidth, $yLinea);
    
    // Texto "Firma Director" centrado debajo de la línea
    $pdf->SetXY($firmaDirectorX, $yLinea + 2);
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell($firmaWidth, 6, 'Firma Director', 0, 0, 'C');
    
    // Nombre del director centrado debajo - SOLO SI NO ESTÁ VACÍO
    if (!empty($director)) {
        $pdf->SetXY($firmaDirectorX, $yLinea + 8);
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($firmaWidth, 6, $director, 0, 0, 'C');
    }

    // FIRMA DEL INSTRUCTOR (lado derecho) - Imagen siempre se muestra
    // Imagen de firma del instructor (personalizada o por defecto) - Ancho fijo, alto proporcional
    $pdf->Image($firma_instructor_url, $firmaInstructorX + 10, $yFirmas, 60, 0, '', '', '', false, 300, '', false, false, 0);
    
    // Línea debajo de la firma del instructor
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + $firmaWidth, $yLinea);
    
    // Texto "Firma Relator" centrado debajo de la línea
    $pdf->SetXY($firmaInstructorX, $yLinea + 2);
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell($firmaWidth, 6, 'Firma Relator', 0, 0, 'C');
    
    // Nombre del instructor centrado debajo - SOLO SI NO ESTÁ VACÍO
    if (!empty($instructor)) {
        $pdf->SetXY($firmaInstructorX, $yLinea + 8);
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($firmaWidth, 6, $instructor, 0, 1, 'C');
    }

    // --- PIE DE PÁGINA CON TEXTO Y QR (POSICIÓN ÓPTIMA CALCULADA) ---
    // 🔧 SOLUCIÓN AL PROBLEMA DEL QR: Calcular posición dinámica en lugar de fija
    $pdf->Ln(10); // Espacio después de las firmas
    $yCurrentPosition = $pdf->GetY();
    
    // Verificar si hay espacio suficiente para el footer (necesitamos ~30mm)
    $espacioNecesario = 30;
    $limitePagina = $pdf->getPageHeight() - $pdf->getBreakMargin();
    
    if (($yCurrentPosition + $espacioNecesario) > $limitePagina) {
        // No hay espacio suficiente, hacer salto de página
        $pdf->AddPage();
        $yCurrentPosition = $pdf->GetY() + 20; // Margen superior en nueva página
    }
    
    // Posicionar footer
    $pdf->SetY($yCurrentPosition);
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
    $pdf->Cell(0, 5, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/', 0, 1, 'L', false, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/');
    
    // QR Code en posición fija relativa al texto
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
    $director = get_post_meta($post_id, '_certificado_director', true) ?: '';
    $instructor = get_post_meta($post_id, '_certificado_instructor', true) ?: '';
    $empresa = get_post_meta($post_id, '_certificado_empresa', true); // Obtener el dato de la empresa
    $verification_url = 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/?id=' . urlencode($codigo);
    
    // 🔧 OBTENER URLs DE FIRMAS PERSONALIZADAS PARA DIPLOMA (con fallback)
    $firma_director_url = get_post_meta($post_id, '_certificado_firma_director_url', true);
    $firma_instructor_url = get_post_meta($post_id, '_certificado_firma_instructor_url', true);
    
    // URLs por defecto específicas para director e instructor
    $firma_director_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-director-final-1.png';
    $firma_instructor_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-relator-fina.png';
    
    if (empty($firma_director_url)) $firma_director_url = $firma_director_default;
    if (empty($firma_instructor_url)) $firma_instructor_url = $firma_instructor_default;

    // --- DISEÑO DEL DIPLOMA (HORIZONTAL) ---
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // 'L' para Landscape (Horizontal)
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->AddPage();
    $font = 'opensans';

    // --- INICIO: AÑADIR IMAGEN DE FONDO DEL DIPLOMA ---
    // !! IMPORTANTE: Reemplaza esta URL con la URL de tu imagen de fondo para el diploma horizontal.
    $imagen_fondo_diploma_url = 'https://validador.zenactivospa.cl/wp-content/uploads/2026/04/diploma-zenactivo-scaled.png'; 
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
    // Nota layout: writeHTMLCell(0,0,...) en TCPDF reserva alto = resto de página (hueco enorme). Usar alto fijo + autopadding false.

    // Título "DIPLOMA DE APROBACIÓN" (bloque algo más arriba para ganar aire hacia firmas)
    $pdf->SetY(18);
    $pdf->SetFont($font, 'B', 28);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 11, 'DIPLOMA DE APROBACIÓN', 0, 1, 'C');

    // Subtítulo "ZEN ACTIVO"
    $pdf->SetFont($font, 'B', 17);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->Cell(0, 7, 'ZEN ACTIVO', 0, 1, 'C');
    $pdf->Ln(2);

    // Barra verde con texto (ya está centrada)
    $pdf->SetFillColor(132, 188, 65);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($font, 'B', 12);
    $pdf->Cell(0, 8, 'POR LA PRESENTE CERTIFICA QUE', 0, 1, 'C', true);
    $pdf->Ln(3);

    // Nombre del Participante
    $pdf->SetFont($font, 'B', 26);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(0, 11, $participante, 0, 1, 'C');

    // 🎯 Empresa (solo mostrar si viene de un certificado grupal)
    $origen_grupal = get_post_meta($post_id, '_certificado_origen_grupal', true);
    if (!empty($empresa) && !empty($origen_grupal)) {
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $empresa_html = '<div style="margin:0;padding:0;line-height:1.15;text-align:center;">Cliente: <b>' . esc_html($empresa) . '</b></div>';
        $pdf->writeHTMLCell(0, 10, '', '', $empresa_html, 0, 1, 0, true, 'C', false);
        $pdf->Ln(0);
    }

    // Texto "Por haber completado..."
    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 6, 'Por haber completado satisfactoriamente el curso de:', 0, 1, 'C');
    
    // Nombre del curso
    $pdf->SetFont($font, 'B', 17);
    $pdf->SetTextColor(132, 188, 65);
    $pdf->MultiCell(0, 10, $curso, 0, 'C');
    $pdf->Ln(3);
    
    // 🎯 INFORMACIÓN DINÁMICA DEL DIPLOMA BASADA EN DATOS DEL PARTICIPANTE
    $pdf->SetFont($font, '', 11);
    $pdf->SetTextColor(80, 80, 80);
    
    // Obtener datos específicos del participante para el diploma
    $duracion_participante = get_post_meta($post_id, '_certificado_duracion', true) ?: '40 horas académicas';
    $asistencia_participante = get_post_meta($post_id, '_certificado_participante_asistencia', true) ?: '100%';
    $nota_final_participante = get_post_meta($post_id, '_certificado_participante_nota_final', true) ?: '7.0';
    $aprobacion_participante = get_post_meta($post_id, '_certificado_participante_aprobacion', true) ?: 'Aprobado';
    
    $pdf->Cell(0, 5, 'Duración: ' . $duracion_participante, 0, 1, 'C');
    $pdf->Cell(0, 5, 'Asistencia: ' . $asistencia_participante, 0, 1, 'C');
    $pdf->Cell(0, 5, 'Nota Final: ' . $nota_final_participante . ' (' . $aprobacion_participante . ')', 0, 1, 'C');
    $fechas_diploma = zc_certificado_fechas_desde_post_id($post_id);
    if ($fechas_diploma['realizacion_txt'] !== '') {
        $pdf->Cell(0, 5, 'Fecha de realización: ' . $fechas_diploma['realizacion_txt'], 0, 1, 'C');
    }
    if ($fechas_diploma['expiracion_txt'] !== '') {
        $anos_txt = (int) $fechas_diploma['anos_vigencia'];
        if (!empty($fechas_diploma['vigencia_estandar']) || !empty($fechas_diploma['exp_automatica'])) {
            $pdf->MultiCell(0, 5, 'Vigencia del certificado: ' . $anos_txt . ' años desde la realización. Expira el ' . $fechas_diploma['expiracion_txt'], 0, 'C');
        } else {
            $pdf->Cell(0, 5, 'Fecha de expiración del certificado: ' . $fechas_diploma['expiracion_txt'], 0, 1, 'C');
        }
    }
    $pdf->Ln(3);

    // --- SECCIÓN DE FIRMAS CON IMAGEN PNG (CENTRADO HORIZONTAL) ---
    $yFirmas = 136;
    $pdf->SetY($yFirmas);

    // Posiciones X para las firmas
    $firmaDirectorX = 60;
    $firmaInstructorX = 297 - 60 - 80; // Margen derecho
    $firmaWidth = 80;
    $firmaHeight = 20;
    
    // 🎯 USAR FIRMAS PERSONALIZADAS O POR DEFECTO PARA DIPLOMA
    // Las URLs ya están definidas arriba con fallback automático

    // FIRMA DEL DIRECTOR (lado izquierdo)
    // Imagen de firma del director (personalizada o por defecto) - Ancho fijo, alto proporcional
    $pdf->Image($firma_director_url, $firmaDirectorX + 10, $yFirmas - 3, 60, 0, '', '', '', false, 300, '', false, false, 0);
    
    // FIRMA DEL INSTRUCTOR (lado derecho)
    // Imagen de firma del instructor (personalizada o por defecto) - Ancho fijo, alto proporcional
    $pdf->Image($firma_instructor_url, $firmaInstructorX + 10, $yFirmas - 3, 60, 0, '', '', '', false, 300, '', false, false, 0);

    // Líneas debajo de las firmas
    $yLinea = $yFirmas + 18;
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + $firmaWidth, $yLinea);
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + $firmaWidth, $yLinea);
    
    // Títulos debajo de las líneas
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($firmaDirectorX, $yLinea + 2);
    $pdf->Cell($firmaWidth, 6, 'Firma Director', 0, 0, 'C');
    $pdf->SetXY($firmaInstructorX, $yLinea + 2);
    $pdf->Cell($firmaWidth, 6, 'Firma Relator', 0, 0, 'C');
    
    // Nombres debajo de los títulos - SOLO SI NO ESTÁN VACÍOS
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(100, 100, 100);
    if (!empty($director)) {
        $pdf->SetXY($firmaDirectorX, $yLinea + 8);
        $pdf->Cell($firmaWidth, 6, $director, 0, 0, 'C');
    }
    if (!empty($instructor)) {
        $pdf->SetXY($firmaInstructorX, $yLinea + 8);
        $pdf->Cell($firmaWidth, 6, $instructor, 0, 1, 'C');
    }

    // --- QR Y DATOS DE VERIFICACIÓN (ESQUINA INFERIOR DERECHA) ---
    $yFooter = 180;
    $xFooter = 220;
    $pdf->SetXY($xFooter, $yFooter);
    $pdf->SetFont($font, 'B', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 4, 'Valida este diploma en:', 0, 1, 'L');
    $pdf->SetFont($font, 'U', 8);
    $pdf->SetTextColor(40, 80, 150);
    $pdf->Cell(0, 4, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/', 0, 1, 'L', false, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/');
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
// PARTE 7: ESTILOS MODERNOS Y INTERFAZ VISUAL MEJORADA
// =============================================================================
function zc_final_enqueue_styles() {
    // Google Fonts
    wp_enqueue_style('zc-final-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap', array(), null);
    
    // Font Awesome para iconos
    wp_enqueue_style('zc-final-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
    
    // Estilos personalizados del plugin
    wp_add_inline_style('zc-final-google-fonts', zc_get_custom_css());
}
add_action('wp_enqueue_scripts', 'zc_final_enqueue_styles');

// Estilos para el admin
function zc_final_admin_enqueue_styles($hook) {
    // Solo cargar en páginas del plugin
    if (strpos($hook, 'certificado') !== false || strpos($hook, 'edit.php') !== false || strpos($hook, 'post.php') !== false || strpos($hook, 'post-new.php') !== false) {
        wp_enqueue_style('zc-final-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap', array(), null);
        wp_enqueue_style('zc-final-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        wp_add_inline_style('zc-final-google-fonts', zc_get_admin_css());
    }
}
add_action('admin_enqueue_scripts', 'zc_final_admin_enqueue_styles');

/**
 * Excel → listado de participantes (misma convención que el textarea CSV).
 * Solo en edición de certificado individual o grupal.
 */
function zc_admin_enqueue_participantes_excel($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->post_type, array('certificado', 'certificado_grupal'), true)) {
        return;
    }
    wp_enqueue_script(
        'sheetjs',
        'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
        array(),
        '0.18.5',
        true
    );
    $inline = <<<'JS'
(function(){'use strict';
var NUM=7;
function escCSV(v){v=String(v==null?'':v).trim();if(/[",\n\r]/.test(v))return'"'+v.replace(/"/g,'""')+'"';return v;}
function rowToLine(row){var a=[],i;for(i=0;i<NUM;i++){a.push(escCSV(row[i]));}return a.join(',');}
function isRowEmpty(row){var i,s;for(i=0;i<NUM;i++){s=String(row[i]!=null?row[i]:'').trim();if(s!=='')return false;}return true;}
document.addEventListener('click',function(e){if(!e.target.classList.contains('zc-pexi-trigger'))return;var w=e.target.closest('.zc-participantes-excel-import');if(!w)return;w.querySelector('.zc-pexi-file').click();});
document.addEventListener('change',function(e){if(!e.target.classList.contains('zc-pexi-file'))return;var file=e.target.files[0];e.target.value='';if(!file){return;}if(typeof XLSX==='undefined'){return;}var wrap=e.target.closest('.zc-participantes-excel-import');var tid=wrap.getAttribute('data-textarea-id');var ta=document.getElementById(tid);var msg=wrap.querySelector('.zc-pexi-msg');if(!ta||!msg)return;var skipHeader=wrap.querySelector('.zc-pexi-skipheader').checked;var append=wrap.querySelector('.zc-pexi-append').checked;var reader=new FileReader();reader.onload=function(ev){try{var data=new Uint8Array(ev.target.result);var wb=XLSX.read(data,{type:'array'});var ws=wb.Sheets[wb.SheetNames[0]];var rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:false});var start=skipHeader?1:0;var lines=[],r,row;for(r=start;r<rows.length;r++){row=rows[r]||[];if(isRowEmpty(row))continue;lines.push(rowToLine(row));}if(!lines.length){msg.textContent='No se encontraron filas de datos.';msg.style.color='#b32d2e';return;}var block=lines.join('\n');if(append&&ta.value.trim()){ta.value=ta.value.replace(/\s*$/,'')+'\n'+block;}else{ta.value=block;}msg.textContent='Importadas '+lines.length+' fila(s). Revise el listado y guarde.';msg.style.color='#2271b1';}catch(err){msg.textContent='No se pudo leer el archivo.';msg.style.color='#b32d2e';}};reader.readAsArrayBuffer(file);});
})();
JS;
    wp_add_inline_script('sheetjs', $inline, 'after');
}
add_action('admin_enqueue_scripts', 'zc_admin_enqueue_participantes_excel', 20);

function zc_get_custom_css() {
    return '
    /* =================================================================
       🎨 ESTILOS FRONTEND - VALIDADOR MODERNO
       ================================================================= */
    
    /* Contenedor principal del validador */
    .zc-validador-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        font-family: "Inter", sans-serif;
    }
    
    /* Formulario de verificación moderno */
    .zc-form-verificacion {
        background: linear-gradient(145deg, #ffffff, #f8fafc);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }
    
    .zc-form-verificacion:hover {
        transform: translateY(-2px);
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    }
    
    .zc-form-verificacion h3 {
        color: #1e293b;
        font-family: "Poppins", sans-serif;
        font-weight: 700;
        font-size: 28px;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .zc-form-verificacion p {
        color: #64748b;
        font-size: 16px;
        text-align: center;
        margin-bottom: 30px;
    }
    
    /* Input de código */
    .zc-input-codigo {
        width: 100% !important;
        padding: 18px 25px !important;
        border: 2px solid #e2e8f0 !important;
        border-radius: 15px !important;
        font-size: 18px !important;
        font-family: "Inter", sans-serif !important;
        background: #ffffff !important;
        transition: all 0.3s ease !important;
        margin-bottom: 25px !important;
    }
    
    .zc-input-codigo:focus {
        outline: none !important;
        border-color: #84BC41 !important;
        box-shadow: 0 0 0 4px rgba(132, 188, 65, 0.1) !important;
        transform: translateY(-1px) !important;
    }
    
    /* Botón de verificación moderno */
    .zc-btn-verificar {
        background: linear-gradient(135deg, #84BC41, #6ba32f) !important;
        color: white !important;
        padding: 18px 20px !important;
        border: none !important;
        border-radius: 15px !important;
        font-size: 18px !important;
        font-weight: 600 !important;
        font-family: "Poppins", sans-serif !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
    }
    
    .zc-btn-verificar:hover {
        background: linear-gradient(135deg, #6ba32f, #5a8c26) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 25px rgba(132, 188, 65, 0.3) !important;
    }
    
    /* Resultados de verificación */
    .zc-resultado-valido {
        background: linear-gradient(145deg, #ffffff, #f0fdf4);
        border: 2px solid #84BC41 !important;
        border-radius: 20px !important;
        padding: 20px !important;
        margin-bottom: 30px !important;
        box-shadow: 0 20px 40px rgba(132, 188, 65, 0.1) !important;
        font-family: "Inter", sans-serif !important;
    }
    
    .zc-resultado-valido h2 {
        color: #84BC41 !important;
        font-family: "Poppins", sans-serif !important;
        font-weight: 700 !important;
        font-size: 32px !important;
        margin-bottom: 25px !important;
        text-align: center !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 15px !important;
    }
    
    .zc-resultado-valido h2:before {
        content: "\\f058";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 36px;
    }
    
    /* Información del certificado */
    .zc-info-certificado p {
        background: rgba(132, 188, 65, 0.05);
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 12px;
        border-left: 4px solid #84BC41;
        font-size: 16px;
    }
    
    .zc-info-certificado strong {
        color: #1e293b;
        font-weight: 600;
    }
    
    /* Botones de descarga modernos */
    .zc-botones-descarga {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
        margin: 30px 0;
    }
    
    .zc-btn-descargar {
        display: inline-flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 15px 30px !important;
        border-radius: 12px !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-family: "Poppins", sans-serif !important;
        font-size: 16px !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
    }
    
    .zc-btn-certificado {
        background: linear-gradient(135deg, #84BC41, #6ba32f) !important;
        color: white !important;
    }
    
    .zc-btn-certificado:hover {
        background: linear-gradient(135deg, #6ba32f, #5a8c26) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 25px rgba(132, 188, 65, 0.3) !important;
        color: white !important;
    }
    
    .zc-btn-certificado:before {
        content: "\\f1c1";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
    }
    
    .zc-btn-diploma {
        background: linear-gradient(135deg, #337ab7, #2563eb) !important;
        color: white !important;
    }
    
    .zc-btn-diploma:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3) !important;
        color: white !important;
    }
    
    .zc-btn-diploma:before {
        content: "\\f19c";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
    }
    
    .zc-btn-grupal {
        background: linear-gradient(135deg, #2e7d32, #1976d2) !important;
        color: white !important;
    }
    
    .zc-btn-grupal:hover {
        background: linear-gradient(135deg, #1976d2, #1565c0) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 25px rgba(25, 118, 210, 0.3) !important;
        color: white !important;
    }
    
    .zc-btn-grupal:before {
        content: "\\f0c0";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
    }
    
    /* Certificado grupal */
    .zc-resultado-grupal {
        background: linear-gradient(145deg, #ffffff, #e8f5e8);
        border: 2px solid #2e7d32 !important;
        border-radius: 20px !important;
        padding: 20px !important;
        margin-bottom: 30px !important;
        box-shadow: 0 20px 40px rgba(46, 125, 50, 0.1) !important;
        font-family: "Inter", sans-serif !important;
    }
    
    .zc-resultado-grupal h2 {
        color: #2e7d32 !important;
        font-family: "Poppins", sans-serif !important;
        font-weight: 700 !important;
        font-size: 32px !important;
        margin-bottom: 25px !important;
        text-align: center !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 15px !important;
    }
    
    .zc-resultado-grupal h2:before {
        content: "\\f0c0";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 36px;
    }
    
    /* Error/No encontrado */
    .zc-resultado-error {
        background: linear-gradient(145deg, #ffffff, #fef2f2);
        border: 2px solid #F44336 !important;
        border-radius: 20px !important;
        padding: 20px !important;
        margin-bottom: 30px !important;
        box-shadow: 0 20px 40px rgba(244, 67, 54, 0.1) !important;
        font-family: "Inter", sans-serif !important;
        text-align: center !important;
    }
    
    .zc-resultado-error h2 {
        color: #F44336 !important;
        font-family: "Poppins", sans-serif !important;
        font-weight: 700 !important;
        font-size: 32px !important;
        margin-bottom: 15px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 15px !important;
    }
    
    .zc-resultado-error h2:before {
        content: "\\f057";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 36px;
    }
    
    /* Visor de PDF mejorado */
    .zc-pdf-viewer {
        margin-top: 30px;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .zc-pdf-viewer h4 {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white;
        padding: 20px;
        margin: 0;
        font-family: "Poppins", sans-serif;
        font-weight: 600;
        font-size: 18px;
        text-align: center;
    }
    
    .zc-pdf-viewer iframe {
        border: none !important;
        border-radius: 0 0 15px 15px !important;
        width: 100% !important;
        min-height: 600px !important;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .zc-validador-container {
            padding: 10px;
        }
        
        .zc-form-verificacion {
            padding: 25px;
            border-radius: 15px;
        }
        
        .zc-form-verificacion h3 {
            font-size: 24px;
        }
        
        .zc-botones-descarga {
            flex-direction: column;
        }
        
        .zc-btn-descargar {
            width: 100%;
            justify-content: center;
        }
        
        .zc-resultado-valido,
        .zc-resultado-grupal,
        .zc-resultado-error {
            padding: 25px;
        }
        
        .zc-resultado-valido h2,
        .zc-resultado-grupal h2,
        .zc-resultado-error h2 {
            font-size: 24px;
            flex-direction: column;
            gap: 10px;
        }
    }
    ';
}

function zc_get_admin_css() {
    return '
    /* =================================================================
       🎨 ESTILOS ADMIN - INTERFAZ MODERNA WORDPRESS
       ================================================================= */
    
    /* Meta boxes modernos */
    #datos_certificado_metabox,
    #datos_certificado_grupal_metabox {
        border-radius: 12px !important;
        border: 1px solid #e1e5e9 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
    }
    
    .postbox-header h2 {
        font-family: "Poppins", sans-serif !important;
        font-weight: 600 !important;
        color: #1e293b !important;
    }
    
    /* Campos de formulario mejorados */
    .zc-admin-section {
        background: #f8fafc;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 4px solid #84BC41;
    }
    
    .zc-admin-section h3 {
        color: #1e293b !important;
        font-family: "Poppins", sans-serif !important;
        font-weight: 600 !important;
        margin-top: 0 !important;
        margin-bottom: 20px !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }
    
    .zc-admin-section h3:before {
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        color: #84BC41;
        font-size: 18px;
    }
    
    .zc-admin-section h3.datos-generales:before { content: "\\f1c0"; }
    .zc-admin-section h3.datos-curso:before { content: "\\f19d"; }
    .zc-admin-section h3.participantes:before { content: "\\f0c0"; }
    .zc-admin-section h3.diploma-data:before { content: "\\f19d"; }
    .zc-admin-section h3.firmas:before { content: "\\f304"; }
    
    /* Grid para campos del diploma */
    .zc-diploma-fields {
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    /* Inputs modernos */
    .zc-admin-section input[type="text"],
    .zc-admin-section input[type="date"],
    .zc-admin-section textarea {
        border: 2px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 12px 16px !important;
        font-size: 14px !important;
        font-family: "Inter", sans-serif !important;
        transition: all 0.3s ease !important;
        background: white !important;
    }
    
    .zc-admin-section input[type="text"]:focus,
    .zc-admin-section input[type="date"]:focus,
    .zc-admin-section textarea:focus {
        outline: none !important;
        border-color: #84BC41 !important;
        box-shadow: 0 0 0 3px rgba(132, 188, 65, 0.1) !important;
    }
    
    /* Labels mejorados */
    .zc-admin-section label {
        font-weight: 500 !important;
        color: #374151 !important;
        font-family: "Inter", sans-serif !important;
        font-size: 14px !important;
        display: block !important;
        margin-bottom: 6px !important;
    }
    
    /* Alertas informativas */
    .zc-info-box {
        background: linear-gradient(135deg, #e8f5e8, #f0f9f0) !important;
        border: 1px solid #84BC41 !important;
        border-radius: 12px !important;
        padding: 20px !important;
        margin-bottom: 20px !important;
        position: relative !important;
    }
    
    .zc-info-box h3 {
        color: #2e7d32 !important;
        margin-top: 0 !important;
        margin-bottom: 10px !important;
        font-family: "Poppins", sans-serif !important;
        font-weight: 600 !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }
    
    .zc-info-box h3:before {
        content: "\\f05a";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        color: #84BC41;
    }
    
    .zc-warning-box {
        background: linear-gradient(135deg, #fff3cd, #fef3cd) !important;
        border: 1px solid #ffc107 !important;
        border-radius: 12px !important;
        padding: 15px !important;
        margin-bottom: 15px !important;
        border-left: 4px solid #ffc107 !important;
    }
    
    .zc-warning-box strong {
        color: #856404 !important;
    }
    
    /* URLs de documentos */
    .zc-url-section {
        background: #f0f6fc !important;
        padding: 20px !important;
        border-radius: 12px !important;
        border: 1px solid #c1d7f0 !important;
    }
    
    .zc-url-section input[type="url"] {
        background-color: #e9ecef !important;
        color: #6c757d !important;
        font-family: monospace !important;
    }
    
    /* Columnas de administrador mejoradas */
    .wp-list-table .column-participantes span {
        background: linear-gradient(135deg, #e3f2fd, #f3e5f5) !important;
        color: #1976d2 !important;
        padding: 4px 12px !important;
        border-radius: 20px !important;
        font-weight: 600 !important;
        font-size: 12px !important;
    }
    
    .wp-list-table .column-procesado span[style*="green"] {
        background: linear-gradient(135deg, #e8f5e8, #f1f8e9) !important;
        color: #2e7d32 !important;
        padding: 4px 12px !important;
        border-radius: 20px !important;
        font-weight: 600 !important;
        font-size: 12px !important;
    }
    
    .wp-list-table .column-procesado span[style*="orange"] {
        background: linear-gradient(135deg, #fff3e0, #fef7e0) !important;
        color: #f57500 !important;
        padding: 4px 12px !important;
        border-radius: 20px !important;
        font-weight: 600 !important;
        font-size: 12px !important;
    }
    
    /* Iconos en menús */
    .wp-menu-image.dashicons-awards:before,
    .wp-menu-image.dashicons-groups:before {
        font-size: 20px !important;
        width: 20px !important;
        height: 20px !important;
    }
    
    /* Botones de submit mejorados */
    #submit {
        background: linear-gradient(135deg, #84BC41, #6ba32f) !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 12px 24px !important;
        font-weight: 600 !important;
        font-family: "Poppins", sans-serif !important;
        text-shadow: none !important;
        box-shadow: 0 4px 12px rgba(132, 188, 65, 0.3) !important;
        transition: all 0.3s ease !important;
    }
    
    #submit:hover {
        background: linear-gradient(135deg, #6ba32f, #5a8c26) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(132, 188, 65, 0.4) !important;
    }
    
    /* Responsive Admin */
    @media (max-width: 768px) {
        .zc-admin-section {
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .zc-admin-section h3 {
            font-size: 16px;
            flex-direction: column;
            gap: 5px;
            text-align: center;
        }
    }
    ';
}

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

    echo '<div class="zc-info-box">';
    echo '<h3>Certificado Grupal - Auto-generación Individual</h3>';
    echo '<p><strong>Funcionalidad:</strong> Al publicar este certificado grupal, se crearán automáticamente certificados individuales para cada participante de la lista, con códigos únicos basados en el código principal.</p>';
    echo '<p><strong>Ejemplo:</strong> Si el código es "CURSO2025", se generarán: CURSO2025-1, CURSO2025-2, CURSO2025-3, etc.</p>';
    echo '</div>';

    echo '<div class="zc-admin-section">';
    echo '<h3 class="datos-generales">Datos Generales</h3>';
    echo '<p><label><strong>Código de Verificación (Base):</strong><br><input type="text" name="certificado_grupal_codigo" value="' . esc_attr($codigo) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre de la Empresa:</strong><br><input type="text" name="certificado_grupal_empresa" value="' . esc_attr($empresa) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Curso:</strong><br><input type="text" name="certificado_grupal_curso" value="' . esc_attr($curso) . '" style="width:100%;"></label></p>';
    echo '</div>';
    
    echo '<div class="zc-admin-section">';
    echo '<h3 class="datos-curso">Datos del Curso</h3>';
    echo '<p><label><strong>Duración (ej: 03 horas cronológicas):</strong><br><input type="text" name="certificado_grupal_duracion" value="' . esc_attr($duracion) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Fecha de Emisión:</strong><br><input type="date" name="certificado_grupal_fecha" value="' . esc_attr($fecha) . '"></label></p>';
    echo '<p><label><strong>Fecha de Realización:</strong><br><input type="date" name="certificado_grupal_fecha_realizacion" value="' . esc_attr($fecha_realizacion) . '"></label></p>';
    echo '<p><label><strong>Fecha de Expiración:</strong><br><input type="date" name="certificado_grupal_fecha_expiracion" value="' . esc_attr($fecha_expiracion) . '"></label></p>';
    echo '<p class="description">Si la expiración queda vacía, se calcula automáticamente (' . (int) zc_certificado_anos_vigencia_default() . ' años desde la realización o desde la fecha de emisión del grupo).</p>';
    echo '<p><label><strong>Cliente:</strong><br><input type="text" name="certificado_grupal_oc_cliente" value="' . esc_attr($oc_cliente) . '" style="width:100%;"></label></p>';
    echo '</div>';

    echo '<div class="zc-admin-section">';
    echo '<h3 class="participantes">Listado de Participantes</h3>';
    echo '<p><label for="listado_participantes_grupal"><strong>Participantes:</strong></label><br>';
    echo '<textarea name="certificado_grupal_listado_participantes" id="listado_participantes_grupal" rows="10" style="width:100%; font-family: monospace;">' . esc_textarea($listado_participantes) . '</textarea>';
    echo '<div class="zc-participantes-excel-import" data-textarea-id="listado_participantes_grupal" style="margin:10px 0;padding:10px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">';
    echo '<p style="margin:0 0 8px;"><strong>Importar desde Excel:</strong> las columnas deben ir en este orden (A→G): <code>Nombre completo, RUT, Asistencia, Nota T., Nota S., Nota final, Aprobación</code>. Se usa la primera hoja del archivo.</p>';
    echo '<p style="margin:0 0 8px;">';
    echo '<input type="file" class="zc-pexi-file" accept=".xlsx,.xls" style="display:none" aria-hidden="true" />';
    echo '<button type="button" class="button button-secondary zc-pexi-trigger">📥 Cargar Excel (.xlsx / .xls)</button> ';
    echo '<span class="zc-pexi-msg" style="margin-left:8px;font-weight:600;"></span>';
    echo '</p>';
    echo '<p style="margin:0;font-size:12px;color:#50575e;">';
    echo '<label style="margin-right:16px;"><input type="checkbox" class="zc-pexi-skipheader" checked /> Primera fila es encabezado (omitir)</label>';
    echo '<label><input type="checkbox" class="zc-pexi-append" /> Agregar al final (si no está marcado, reemplaza el listado)</label>';
    echo '</p></div>';
    echo '<div class="zc-warning-box"><strong>Instrucciones:</strong> Ingrese un participante por línea. Use comas (,) para separar las columnas en el siguiente orden:<br><code>Nombre Completo,RUT,Asistencia,Nota T.,Nota S.,Nota Final,Aprobación</code><br>Ejemplo: <code>Apolinar Andrés Mendoza Cuno,22.626.949-9,100%,7.0,7.0,7.0,A</code></div>';
    echo '<div class="zc-info-box"><h3>Auto-generación</h3>Al publicar, cada participante será registrado como certificado individual con código único.</div>';
    echo '</div>';

    echo '<div class="zc-admin-section">';
    echo '<h3 class="firmas">Firmas</h3>';
    echo '<p><label><strong>Nombre del Director:</strong><br><input type="text" name="certificado_grupal_director" value="' . esc_attr($director) . '" style="width:100%;"></label></p>';
    echo '<p><label><strong>Nombre del Instructor:</strong><br><input type="text" name="certificado_grupal_instructor" value="' . esc_attr($instructor) . '" style="width:100%;"></label></p>';
    
    // 🖼️ Campos para firmas personalizadas PNG
    $firma_director_url = get_post_meta($post->ID, '_certificado_grupal_firma_director_url', true);
    $firma_instructor_url = get_post_meta($post->ID, '_certificado_grupal_firma_instructor_url', true);
    
    // 📚 Campo para temario del curso grupal - SISTEMA DROPDOWN
    $temario_grupal_id = get_post_meta($post->ID, '_certificado_grupal_temario_id', true);
    
    echo '<hr style="margin: 15px 0;">';
    echo '<h4>🖼️ Firmas Personalizadas (PNG)</h4>';
    echo '<p style="color: #666;">Si no se especifican URLs, se usarán las firmas predeterminadas del sistema.</p>';
    echo '<p><label><strong>URL Firma Director (PNG):</strong><br>';
    echo '<input type="url" name="certificado_grupal_firma_director_url" value="' . esc_attr($firma_director_url) . '" style="width:100%;" placeholder="https://ejemplo.com/firma-director.png"></label></p>';
    echo '<p><label><strong>URL Firma Relator (PNG):</strong><br>';
    echo '<input type="url" name="certificado_grupal_firma_instructor_url" value="' . esc_attr($firma_instructor_url) . '" style="width:100%;" placeholder="https://ejemplo.com/firma-relator.png"></label></p>';
    
    // 📚 Sección de temario para grupales - NUEVO SISTEMA DROPDOWN
    echo '<hr style="margin: 15px 0;">';
    echo '<h4>📚 Temario del Curso (Opcional)</h4>';
    echo '<p style="color: #666;">Selecciona un temario de la lista o gestiona temarios desde <strong>Certificados → 📚 Temarios</strong>.</p>';
    
    // Obtener temarios disponibles
    $temarios_disponibles_grupal = zc_obtener_todos_los_temarios();
    
    echo '<p><label><strong>Temario:</strong><br>';
    echo '<select name="certificado_grupal_temario_id" style="width:100%;">';
    echo '<option value="">Sin temario</option>';
    
    foreach ($temarios_disponibles_grupal as $temario) {
        $selected = ($temario_grupal_id == $temario->id) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($temario->id) . '" ' . $selected . '>' . esc_html($temario->nombre) . '</option>';
    }
    
    echo '</select></label></p>';
    echo '</div>';
    
    echo '<div class="zc-url-section">';
    echo '<h3>URL del Certificado Grupal Generado</h3>';
    echo '<p><label><strong>URL del Certificado Grupal (Automático):</strong><br><input type="url" value="' . esc_attr($pdf_url) . '" style="width:100%;" readonly></label></p>';
    echo '</div>';
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
    
    // 🖼️ Guardar URLs de firmas personalizadas
    if (isset($_POST['certificado_grupal_firma_director_url'])) {
        update_post_meta($post_id, '_certificado_grupal_firma_director_url', esc_url_raw($_POST['certificado_grupal_firma_director_url']));
    }
    if (isset($_POST['certificado_grupal_firma_instructor_url'])) {
        update_post_meta($post_id, '_certificado_grupal_firma_instructor_url', esc_url_raw($_POST['certificado_grupal_firma_instructor_url']));
    }
    
    // 📚 Guardar ID del temario grupal seleccionado
    if (isset($_POST['certificado_grupal_temario_id'])) {
        $temario_grupal_id = intval($_POST['certificado_grupal_temario_id']);
        update_post_meta($post_id, '_certificado_grupal_temario_id', $temario_grupal_id);
        
        // Migración: limpiar URL antigua si existe
        delete_post_meta($post_id, '_certificado_grupal_temario_url');
    }

    zc_certificado_autocompletar_meta_expiracion($post_id, '_certificado_grupal_fecha_realizacion', '_certificado_grupal_fecha_expiracion', '_certificado_grupal_fecha');

    // 🎯 MAGIA: Auto-generar certificados individuales solo si está publicado
    if ($post->post_status === 'publish') {
        zc_grupal_procesar_participantes($post_id);
        
        // Generar PDF del certificado grupal después de procesar participantes
        zc_grupal_generar_pdf_manual($post_id, $post);
        
        // 🔄 SINCRONIZACIÓN: Actualizar certificados individuales existentes con nuevos datos
        zc_grupal_sincronizar_certificados_individuales($post_id);
    }
}

// =============================================================================
// PARTE 10.5: FUNCIÓN DE SINCRONIZACIÓN CERTIFICADOS GRUPALES ↔ INDIVIDUALES
// =============================================================================

function zc_grupal_sincronizar_certificados_individuales($post_id_grupal) {
    // 🛡️ Protección contra loops infinitos de sincronización
    global $zc_sincronizando;
    if (!empty($zc_sincronizando[$post_id_grupal])) {
        return; // Ya se está sincronizando este certificado
    }
    $zc_sincronizando[$post_id_grupal] = true;
    
    // Buscar todos los certificados individuales existentes de este grupo
    $certificados_individuales = get_posts(array(
        'post_type' => 'certificado',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_certificado_origen_grupal',
                'value' => $post_id_grupal,
                'compare' => '='
            )
        )
    ));

    if (empty($certificados_individuales)) {
        return; // No hay certificados individuales que sincronizar
    }

    // Obtener datos actualizados del certificado grupal
    $curso = get_post_meta($post_id_grupal, '_certificado_grupal_curso', true);
    $fecha = get_post_meta($post_id_grupal, '_certificado_grupal_fecha', true);
    $empresa = get_post_meta($post_id_grupal, '_certificado_grupal_empresa', true);
    $director = get_post_meta($post_id_grupal, '_certificado_grupal_director', true);
    $instructor = get_post_meta($post_id_grupal, '_certificado_grupal_instructor', true);
    $duracion = get_post_meta($post_id_grupal, '_certificado_grupal_duracion', true);
    $fecha_realizacion = get_post_meta($post_id_grupal, '_certificado_grupal_fecha_realizacion', true);
    $fecha_expiracion = get_post_meta($post_id_grupal, '_certificado_grupal_fecha_expiracion', true);
    $oc_cliente = get_post_meta($post_id_grupal, '_certificado_grupal_oc_cliente', true);
    $firma_director_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_firma_director_url', true);
    $firma_instructor_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_firma_instructor_url', true);
    $temario_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_temario_id', true);

    // Actualizar cada certificado individual con los datos del grupal
    foreach ($certificados_individuales as $certificado_individual) {
        $post_id_individual = $certificado_individual->ID;
        
        // Actualizar datos comunes (mantener datos específicos del participante)
        update_post_meta($post_id_individual, '_certificado_curso', $curso);
        update_post_meta($post_id_individual, '_certificado_fecha', $fecha);
        update_post_meta($post_id_individual, '_certificado_empresa', $empresa);
        update_post_meta($post_id_individual, '_certificado_director', $director);
        update_post_meta($post_id_individual, '_certificado_instructor', $instructor);
        update_post_meta($post_id_individual, '_certificado_duracion', $duracion);
        update_post_meta($post_id_individual, '_certificado_fecha_realizacion', $fecha_realizacion);
        update_post_meta($post_id_individual, '_certificado_fecha_expiracion', $fecha_expiracion);
        update_post_meta($post_id_individual, '_certificado_oc_cliente', $oc_cliente);
        
        // Actualizar URLs de firmas si están especificadas
        if (!empty($firma_director_grupal)) {
            update_post_meta($post_id_individual, '_certificado_firma_director_url', $firma_director_grupal);
        }
        if (!empty($firma_instructor_grupal)) {
            update_post_meta($post_id_individual, '_certificado_firma_instructor_url', $firma_instructor_grupal);
        }
        
        // 📚 Actualizar ID del temario si está especificado
        if (!empty($temario_grupal)) {
            update_post_meta($post_id_individual, '_certificado_temario_id', $temario_grupal);
        }
        
        // Regenerar PDFs del certificado individual con datos actualizados
        $post_object = get_post($post_id_individual);
        if ($post_object) {
            zc_final_generar_pdf($post_id_individual, $post_object);
            zc_final_generar_diploma($post_id_individual, $post_object);
        }
    }
    
    // 🛡️ Limpiar protección de sincronización
    global $zc_sincronizando;
    unset($zc_sincronizando[$post_id_grupal]);
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

        // Extraer datos del participante - formato: Nombre,RUT,Asistencia,Nota T.,Nota S.,Nota Final,Aprobación
        $nombre_participante = '';
        $rut_participante = '';
        $asistencia_participante = '100%';
        $nota_teorica = '7.0';
        $nota_practica = '7.0';
        $nota_final = '7.0';
        $aprobacion = 'Aprobado';
        
        if (strpos($participante_line, ',') !== false) {
            $partes = str_getcsv($participante_line, ',');
            $nombre_participante = trim($partes[0] ?? '');
            $rut_participante = trim($partes[1] ?? '');
            $asistencia_participante = trim($partes[2] ?? '100%');
            $nota_teorica = trim($partes[3] ?? '7.0');
            $nota_practica = trim($partes[4] ?? '7.0');
            $nota_final = trim($partes[5] ?? '7.0');
            $aprobacion = trim($partes[6] ?? 'Aprobado');
        } else {
            // Si no hay comas, toda la línea es el nombre del participante (usar valores por defecto)
            $nombre_participante = trim($participante_line);
        }
        
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
            
            // 🎯 GUARDAR DATOS ESPECÍFICOS DEL PARTICIPANTE PARA EL DIPLOMA
            update_post_meta($post_id_individual, '_certificado_participante_rut', $rut_participante);
            update_post_meta($post_id_individual, '_certificado_participante_asistencia', $asistencia_participante);
            update_post_meta($post_id_individual, '_certificado_participante_nota_teorica', $nota_teorica);
            update_post_meta($post_id_individual, '_certificado_participante_nota_practica', $nota_practica);
            update_post_meta($post_id_individual, '_certificado_participante_nota_final', $nota_final);
            update_post_meta($post_id_individual, '_certificado_participante_aprobacion', $aprobacion);
            
            // Para el certificado individual, solo una línea de participante
            update_post_meta($post_id_individual, '_certificado_listado_participantes', $participante_line);
            
            // 🎯 COPIAR URLS DE FIRMAS DEL CERTIFICADO GRUPAL AL INDIVIDUAL
            $firma_director_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_firma_director_url', true);
            $firma_instructor_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_firma_instructor_url', true);
            
            if (!empty($firma_director_grupal)) {
                update_post_meta($post_id_individual, '_certificado_firma_director_url', $firma_director_grupal);
            }
            if (!empty($firma_instructor_grupal)) {
                update_post_meta($post_id_individual, '_certificado_firma_instructor_url', $firma_instructor_grupal);
            }
            
            // 📚 COPIAR ID DEL TEMARIO DEL CERTIFICADO GRUPAL AL INDIVIDUAL
            $temario_grupal = get_post_meta($post_id_grupal, '_certificado_grupal_temario_id', true);
            if (!empty($temario_grupal)) {
                update_post_meta($post_id_individual, '_certificado_temario_id', $temario_grupal);
            }

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
    $director = get_post_meta($post_id, '_certificado_grupal_director', true) ?: '';
    $instructor = get_post_meta($post_id, '_certificado_grupal_instructor', true) ?: '';
    $duracion = get_post_meta($post_id, '_certificado_grupal_duracion', true);
    $fechas_grupal = zc_certificado_grupal_fechas_desde_post_id($post_id);
    $fecha_realizacion = $fechas_grupal['realizacion_txt'];
    $fecha_expiracion = $fechas_grupal['expiracion_txt'];
    $oc_cliente = get_post_meta($post_id, '_certificado_grupal_oc_cliente', true);
    $listado_participantes_raw = get_post_meta($post_id, '_certificado_grupal_listado_participantes', true);
    $verification_url = 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/?id=' . urlencode($codigo);

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
            <td style="background-color:#e0e0e0;"><b>Cliente</b></td>
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

    // --- TABLA DE PARTICIPANTES CON PAGINACIÓN AUTOMÁTICA (MÁXIMO 15 POR PÁGINA) ---
    $participantes = explode("\n", trim($listado_participantes_raw));
    
    // Filtrar participantes vacíos y preparar datos
    $participantes_validos = array();
    foreach ($participantes as $participante_line) {
        $participante_line = trim($participante_line);
        if (!empty($participante_line)) {
            $participantes_validos[] = $participante_line;
        }
    }
    
    $total_participantes = count($participantes_validos);
    $participantes_por_pagina = 15; // Máximo 15 participantes por página
    $total_paginas = ceil($total_participantes / $participantes_por_pagina);
    
    $style_th = 'border: 1px solid #000; background-color:#000; color:#fff; font-weight:bold; vertical-align:middle; padding:5px;';
    $style_td = 'border: 1px solid #000; vertical-align:middle; padding:5px;';
    
    // Procesar participantes en bloques de 15
    for ($pagina = 0; $pagina < $total_paginas; $pagina++) {
        $inicio = $pagina * $participantes_por_pagina;
        $fin = min($inicio + $participantes_por_pagina, $total_participantes);
        
        // Si no es la primera página, agregar nueva página
        if ($pagina > 0) {
            $pdf->AddPage();
            
            // Repetir encabezado en páginas adicionales
            $pdf->SetFont($font, 'B', 12);
            $pdf->Cell(0, 10, 'LISTADO DE PARTICIPANTES (Continuación)', 0, 1, 'C');
            $pdf->Ln(2);
        }
        
        // Crear tabla para este bloque de participantes
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
        
        // Agregar participantes de este bloque
        for ($i = $inicio; $i < $fin; $i++) {
            $numero = $i + 1; // Número continuo desde 1
            $participante_line = $participantes_validos[$i];
            
            $partes = str_getcsv($participante_line, ',');
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
        
        $tbl_participantes .= '</tbody></table>';
        
        // Renderizar tabla de esta página
        $pdf->writeHTML($tbl_participantes, true, false, true, false, '');
        
        // Solo agregar firmas y footer en la ÚLTIMA página
        if ($pagina == $total_paginas - 1) {
            // Continuar con firmas y footer (código existente)
        } else {
            // En páginas intermedias, agregar nota de continuación
            $pdf->Ln(10);
            $pdf->SetFont($font, 'I', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Continúa en la siguiente página...', 0, 1, 'R');
        }
    }

    // --- SECCIÓN DE FIRMAS CON PNG PERSONALIZABLES ---
    if ($pdf->GetY() > ($pdf->getPageHeight() - 60)) {
        $pdf->AddPage();
    }
    $pdf->Ln(15);

    $yFirmas = $pdf->GetY();
    $firmaDirectorX = 30;
    $firmaInstructorX = 210 - 30 - 80;
    
    // 🖼️ Obtener URLs de firmas personalizadas
    $firma_director_url = get_post_meta($post_id, '_certificado_grupal_firma_director_url', true);
    $firma_instructor_url = get_post_meta($post_id, '_certificado_grupal_firma_instructor_url', true);
    
    // Firmas predeterminadas
    $firma_director_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-director-final-1.png';
    $firma_instructor_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-relator-fina.png';
    
    // Usar firma personalizada o predeterminada
    $firma_director_final = !empty($firma_director_url) ? $firma_director_url : $firma_director_default;
    $firma_instructor_final = !empty($firma_instructor_url) ? $firma_instructor_url : $firma_instructor_default;
    
    // Insertar firmas PNG (60mm ancho, alto automático para mantener proporciones)
    $firmaWidth = 60;
    $firmaDirectorCenteredX = $firmaDirectorX + (80 - $firmaWidth) / 2;
    $firmaInstructorCenteredX = $firmaInstructorX + (80 - $firmaWidth) / 2;
    
    $pdf->Image($firma_director_final, $firmaDirectorCenteredX, $yFirmas, $firmaWidth, 0, '', '', '', false, 300, '', false, false, 0);
    $pdf->Image($firma_instructor_final, $firmaInstructorCenteredX, $yFirmas, $firmaWidth, 0, '', '', '', false, 300, '', false, false, 0);
    
    // Ajustar posición Y después de las firmas (estimando altura máxima de 20mm)
    $yAfterFirmas = $yFirmas + 20;
    $pdf->SetY($yAfterFirmas);
    
    // Líneas bajo las firmas
    $yLinea = $pdf->GetY();
    $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
    $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
    
    // Textos bajo las líneas
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetY($yLinea + 2);
    $pdf->SetX($firmaDirectorX);
    $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
    $pdf->SetX($firmaInstructorX);
    $pdf->Cell(80, 10, 'Firma Relator', 0, 1, 'C');
    
    // Nombres debajo de los títulos - SOLO SI NO ESTÁN VACÍOS
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(100, 100, 100);
    if (!empty($director)) {
        $pdf->SetY($yLinea + 8);
        $pdf->SetX($firmaDirectorX);
        $pdf->Cell(80, 6, $director, 0, 0, 'C');
    }
    if (!empty($instructor)) {
        $pdf->SetY($yLinea + 8);
        $pdf->SetX($firmaInstructorX);
        $pdf->Cell(80, 6, $instructor, 0, 1, 'C');
    }

    // --- PIE DE PÁGINA CON TEXTO Y QR (dinámico tras firmas; evita solaparse con firmas del grupal) ---
    $pdf->Ln(10);
    $yCurrentPosition = $pdf->GetY();
    $espacioNecesario = 42;
    $limitePagina = $pdf->getPageHeight() - $pdf->getBreakMargin();
    if (($yCurrentPosition + $espacioNecesario) > $limitePagina) {
        $pdf->AddPage();
        $yCurrentPosition = $pdf->GetY() + 20;
    }
    $pdf->SetY($yCurrentPosition);
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
    $pdf->Cell(0, 5, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/', 0, 1, 'L', false, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/');

    // QR algo más compacto en grupal (35mm invadía la zona visual de firmas cuando el pie quedaba alto)
    $qrSize = 28;
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
            // 🚀 OPTIMIZACIONES PARA ARCHIVOS GRANDES
            ini_set('max_execution_time', 3600); // 60 minutos para grupales
            ini_set('memory_limit', '2048M'); // 2GB RAM para grupales
            
            $csv_file = $_FILES['csv_file']['tmp_name']; 
            $file_handle = fopen($csv_file, 'r'); 
            $headers = fgetcsv($file_handle, 1024, ','); 
            $header_map = array_flip($headers); 
            $importados = 0; 
            $errores = 0;
            
            // Mostrar progreso para grupales
            echo '<div id="import-progress" style="background:#f1f1f1;padding:10px;margin:10px 0;">';
            echo '<div style="background:#d63638;height:20px;width:0%;transition:width 0.3s;" id="progress-bar"></div>';
            echo '<p id="progress-text">Iniciando importación grupal...</p></div>';
            echo '<script>
                function updateProgress(current, total) {
                    const percent = Math.round((current / total) * 100);
                    document.getElementById("progress-bar").style.width = percent + "%";
                    document.getElementById("progress-text").innerHTML = "Procesando grupo: " + current + "/" + total + " (" + percent + "%) - Generando certificados individuales...";
                }
            </script>';
            
            // Contar total de registros grupales
            $total_registros = 0;
            while (fgetcsv($file_handle, 1024, ',') !== FALSE) {
                $total_registros++;
            }
            rewind($file_handle);
            fgetcsv($file_handle, 1024, ','); // Saltar headers nuevamente 
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
                        zc_grupal_generar_pdf_manual($post_id, $post_object);
                        // La auto-generación de individuales se activa automáticamente por el hook save_post
                    }

                    $importados++; 
                    
                    // 🚀 OPTIMIZACIONES PARA CERTIFICADOS GRUPALES
                    // Mostrar progreso cada 2 registros (ya que grupales son más pesados)
                    if ($importados % 2 == 0) {
                        echo '<script>updateProgress(' . $importados . ', ' . $total_registros . ');</script>';
                        echo str_pad('', 4096) . "\n"; // Forzar flush del buffer
                        flush();
                        ob_flush();
                    }
                    
                    // Limpiar memoria cada 5 registros grupales
                    if ($importados % 5 == 0) {
                        wp_cache_flush();
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                        // Pausa de 3 segundos cada 5 registros grupales
                        sleep(3);
                    }
                } else { 
                    $errores++; 
                } 
            } 
            fclose($file_handle); 
            echo '<script>updateProgress(' . $total_registros . ', ' . $total_registros . ');</script>';
            echo '<div class="notice notice-success is-dismissible"><p><strong>🎉 Importación grupal completada:</strong> ' . $importados . ' certificados grupales importados exitosamente. ' . $errores . ' errores.</p></div>'; 
        } else { 
            echo '<div class="notice notice-error is-dismissible"><p>Por favor, selecciona un archivo CSV.</p></div>';
        } 
    } 
    echo '<div class="notice notice-warning"><p><strong>⚠️ IMPORTANTE para certificados grupales:</strong> Cada registro grupal genera múltiples PDFs individuales. Para archivos grandes, el proceso puede tomar HORAS. Se recomienda fuertemente dividir en lotes de 10-20 grupos.</p></div>';
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
        // 🔧 Si no existen certificados individuales, generarlos automáticamente
        zc_grupal_procesar_participantes($post_id_grupal);
        
        // Volver a buscar después de generar
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
        
        // Si aún no hay certificados después de generar, retornar vacío
        if (empty($certificados_individuales)) {
            return '';
        }
    }
    
    // Crear PDF compilado con todos los diplomas
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Horizontal para diplomas
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $font = 'opensans';
    
    $imagen_fondo_diploma_url = 'https://validador.zenactivospa.cl/wp-content/uploads/2026/04/diploma-zenactivo-scaled.png';
    
    foreach ($certificados_individuales as $index => $certificado_individual) {
        $post_id_individual = $certificado_individual->ID;
        
        // Obtener datos del certificado individual
        $codigo = get_post_meta($post_id_individual, '_certificado_codigo', true);
        $participante = get_post_meta($post_id_individual, '_certificado_participante', true);
        $curso_individual = get_post_meta($post_id_individual, '_certificado_curso', true);
        $director = get_post_meta($post_id_individual, '_certificado_director', true) ?: '';
        $instructor = get_post_meta($post_id_individual, '_certificado_instructor', true) ?: '';
        $empresa_individual = get_post_meta($post_id_individual, '_certificado_empresa', true);
        $verification_url = 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/?id=' . urlencode($codigo);
        
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
        
        // Título "DIPLOMA DE APROBACIÓN" (alineado con diploma individual)
        $pdf->SetY(18);
        $pdf->SetFont($font, 'B', 28);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 11, 'DIPLOMA DE APROBACIÓN', 0, 1, 'C');
        
        // Subtítulo "ZEN ACTIVO"
        $pdf->SetFont($font, 'B', 17);
        $pdf->SetTextColor(132, 188, 65);
        $pdf->Cell(0, 7, 'ZEN ACTIVO', 0, 1, 'C');
        $pdf->Ln(2);
        
        // Barra verde
        $pdf->SetFillColor(132, 188, 65);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 12);
        $pdf->Cell(0, 8, 'POR LA PRESENTE CERTIFICA QUE', 0, 1, 'C', true);
        $pdf->Ln(3);
        
        // Nombre del Participante
        $pdf->SetFont($font, 'B', 26);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 11, $participante, 0, 1, 'C');
        
        // Empresa (altura fija: evita hueco gigante con writeHTMLCell h=0)
        if (!empty($empresa_individual)) {
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(80, 80, 80);
            $empresa_html = '<div style="margin:0;padding:0;line-height:1.15;text-align:center;">Cliente: <b>' . esc_html($empresa_individual) . '</b></div>';
            $pdf->writeHTMLCell(0, 10, '', '', $empresa_html, 0, 1, 0, true, 'C', false);
            $pdf->Ln(0);
        }
        
        // Texto "Por haber completado..."
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 6, 'Por haber completado satisfactoriamente el curso de:', 0, 1, 'C');
        
        // Nombre del curso
        $pdf->SetFont($font, 'B', 17);
        $pdf->SetTextColor(132, 188, 65);
        $pdf->MultiCell(0, 10, $curso_individual, 0, 'C');
        $pdf->Ln(3);
        
        // 🎓 LÍNEAS DINÁMICAS DEL DIPLOMA BASADAS EN DATOS REALES DEL PARTICIPANTE
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        
        // Obtener datos específicos de este participante individual
        $duracion_individual = get_post_meta($post_id_individual, '_certificado_duracion', true) ?: '40 horas académicas';
        $asistencia_individual = get_post_meta($post_id_individual, '_certificado_participante_asistencia', true) ?: '100%';
        $nota_final_individual = get_post_meta($post_id_individual, '_certificado_participante_nota_final', true) ?: '7.0';
        $aprobacion_individual = get_post_meta($post_id_individual, '_certificado_participante_aprobacion', true) ?: 'Aprobado';
        
        $pdf->Cell(0, 5, 'Duración: ' . $duracion_individual, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Asistencia: ' . $asistencia_individual, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Nota Final: ' . $nota_final_individual . ' (' . $aprobacion_individual . ')', 0, 1, 'C');
        $fechas_diploma_g = zc_certificado_fechas_desde_post_id($post_id_individual);
        if ($fechas_diploma_g['realizacion_txt'] !== '') {
            $pdf->Cell(0, 5, 'Fecha de realización: ' . $fechas_diploma_g['realizacion_txt'], 0, 1, 'C');
        }
        if ($fechas_diploma_g['expiracion_txt'] !== '') {
            $anos_g = (int) $fechas_diploma_g['anos_vigencia'];
            if (!empty($fechas_diploma_g['vigencia_estandar']) || !empty($fechas_diploma_g['exp_automatica'])) {
                $pdf->MultiCell(0, 5, 'Vigencia del certificado: ' . $anos_g . ' años desde la realización. Expira el ' . $fechas_diploma_g['expiracion_txt'], 0, 'C');
            } else {
                $pdf->Cell(0, 5, 'Fecha de expiración del certificado: ' . $fechas_diploma_g['expiracion_txt'], 0, 1, 'C');
            }
        }
        $pdf->Ln(3);
        
        // Firmas con PNG personalizables
        $yFirmas = 136;
        $firmaDirectorX = 60;
        $firmaInstructorX = 297 - 60 - 80;
        
        // 🖼️ Obtener URLs de firmas personalizadas del certificado grupal
        $firma_director_url = get_post_meta($post_id_grupal, '_certificado_grupal_firma_director_url', true);
        $firma_instructor_url = get_post_meta($post_id_grupal, '_certificado_grupal_firma_instructor_url', true);
        
        // Firmas predeterminadas  
        $firma_director_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-director-final-1.png';
        $firma_instructor_default = 'https://validador.zenactivospa.cl/wp-content/uploads/2025/09/Firma-relator-fina.png';
        
        // Usar firma personalizada o predeterminada
        $firma_director_final = !empty($firma_director_url) ? $firma_director_url : $firma_director_default;
        $firma_instructor_final = !empty($firma_instructor_url) ? $firma_instructor_url : $firma_instructor_default;
        
        // Insertar firmas PNG (60mm ancho, alto automático para mantener proporciones)
        $firmaWidth = 60;
        $firmaDirectorCenteredX = $firmaDirectorX + (80 - $firmaWidth) / 2;
        $firmaInstructorCenteredX = $firmaInstructorX + (80 - $firmaWidth) / 2;
        
        $pdf->Image($firma_director_final, $firmaDirectorCenteredX, $yFirmas - 3, $firmaWidth, 0, '', '', '', false, 300, '', false, false, 0);
        $pdf->Image($firma_instructor_final, $firmaInstructorCenteredX, $yFirmas - 3, $firmaWidth, 0, '', '', '', false, 300, '', false, false, 0);
        
        // Líneas bajo las firmas (estimando altura máxima de 15mm para diplomas)
        $yLinea = $yFirmas + 15;
        $pdf->Line($firmaDirectorX, $yLinea, $firmaDirectorX + 80, $yLinea);
        $pdf->Line($firmaInstructorX, $yLinea, $firmaInstructorX + 80, $yLinea);
        
        // Textos bajo las líneas
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($firmaDirectorX, $yLinea + 2);
        $pdf->Cell(80, 10, 'Firma Director', 0, 0, 'C');
        $pdf->SetXY($firmaInstructorX, $yLinea + 2);
        $pdf->Cell(80, 10, 'Firma Relator', 0, 1, 'C');
        
        // Nombres debajo de los títulos - SOLO SI NO ESTÁN VACÍOS
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(100, 100, 100);
        if (!empty($director)) {
            $pdf->SetXY($firmaDirectorX, $yLinea + 8);
            $pdf->Cell(80, 6, $director, 0, 0, 'C');
        }
        if (!empty($instructor)) {
            $pdf->SetXY($firmaInstructorX, $yLinea + 8);
            $pdf->Cell(80, 6, $instructor, 0, 1, 'C');
        }
        
        // QR y datos de verificación
        $yFooter = 180;
        $xFooter = 220;
        $pdf->SetXY($xFooter, $yFooter);
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 4, 'Valida este diploma en:', 0, 1, 'L');
        $pdf->SetFont($font, 'U', 8);
        $pdf->SetTextColor(40, 80, 150);
        $pdf->Cell(0, 4, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/', 0, 1, 'L', false, 'https://validador.zenactivospa.cl/pagina-de-verificacion-zen/');
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

/*
=============================================================================
📋 DOCUMENTACIÓN TÉCNICA - GESTIÓN DE FIRMAS Y REFERENCIAS
=============================================================================

🔧 PROBLEMA RESUELTO: QR QUE SE MOVÍA A OTRA PÁGINA
   ------------------------------------------------
   ✅ Solución aplicada: Cálculo dinámico de posición en lugar de Y fija
   ✅ El footer ahora verifica si hay espacio suficiente (30mm)
   ✅ Si no hay espacio, automáticamente crea nueva página
   ✅ QR Code se mantiene siempre en la misma página que el texto

🎯 SISTEMA DE FIRMAS PERSONALIZADAS
   ---------------------------------
   
   1. CAMPOS AGREGADOS AL FORMULARIO:
      • "URL Firma del Director (PNG)"
      • "URL Firma del Instructor (PNG)"
      • Validación automática de URLs
      • Fallback a firma por defecto
   
   2. CAMPOS EN BASE DE DATOS:
      • _certificado_firma_director_url
      • _certificado_firma_instructor_url
      • Se guardan con esc_url_raw() para seguridad
   
   3. SISTEMA DE FALLBACK:
      • Si campo vacío → usa firma por defecto
      • Firma por defecto: https://validador.zenactivospa.cl/wp-content/uploads/2025/09/firma-muestra-scaled.png

📁 URLs Y ARCHIVOS DE REFERENCIA
   ------------------------------
   
   🖼️ FIRMAS:
   • Firma por defecto: https://validador.zenactivospa.cl/wp-content/uploads/2025/09/firma-muestra-scaled.png
   • Tamaño recomendado: 400x200 píxeles
   • Formato: PNG con fondo transparente
   • Ubicación en PDF: 60x20mm (certificado), 60x15mm (diploma)
   
   🎨 FONDOS:
   • Certificado individual: https://validador.zenactivospa.cl/wp-content/uploads/2025/09/hoja-membretada-FINAL-scaled.png
   • Diploma horizontal: https://validador.zenactivospa.cl/wp-content/uploads/2026/04/diploma-zenactivo-scaled.png
   
   🔗 VERIFICACIÓN:
   • URL base: https://validador.zenactivospa.cl/pagina-de-verificacion-zen/
   • Parámetro: ?id=[codigo_certificado]

⚙️ PARA CAMBIAR FIRMAS GLOBALMENTE (SIN FORMULARIO)
   ------------------------------------------------
   
   1. UBICAR EN EL CÓDIGO:
      Buscar: $firma_default_url = 'https://validador.zenactivospa.cl/...'
      Líneas aproximadas: 460 (certificado) y 710 (diploma)
   
   2. REEMPLAZAR URL:
      $firma_default_url = 'https://tu-sitio.com/tu-nueva-firma.png';
   
   3. APLICAR EN AMBAS FUNCIONES:
      • zc_final_generar_pdf() - Para certificados
      • zc_final_generar_diploma() - Para diplomas

🎛️ PARA USAR FIRMAS DIFERENTES POR CARGO
   ---------------------------------------
   
   Ejemplo de modificación avanzada:
   
   // En lugar de una sola firma por defecto, usar diferentes:
   $firma_director_url = $firma_director_url ?: 'https://sitio.com/firma-director.png';
   $firma_instructor_url = $firma_instructor_url ?: 'https://sitio.com/firma-instructor.png';

📊 CAMPOS DEL FORMULARIO DISPONIBLES
   ----------------------------------
   
   CERTIFICADO INDIVIDUAL:
   • certificado_codigo (Código único)
   • certificado_empresa (Nombre del participante)
   • certificado_curso (Nombre del curso)
   • certificado_director (Nombre del director)
   • certificado_instructor (Nombre del instructor)
   • certificado_firma_director_url (🆕 URL firma director)
   • certificado_firma_instructor_url (🆕 URL firma instructor)
   • [+ otros campos de fechas, duración, etc.]

   CERTIFICADO GRUPAL:
   • certificado_grupal_codigo (Código base para el grupo)
   • certificado_grupal_empresa (Empresa participante)
   • certificado_grupal_curso (Nombre del curso)
   • certificado_grupal_director (Nombre del director)
   • certificado_grupal_instructor (Nombre del instructor)
   • certificado_grupal_firma_director_url (🆕 URL firma director)
   • certificado_grupal_firma_instructor_url (🆕 URL firma instructor)
   • certificado_grupal_listado_participantes (Lista CSV de participantes)
   • [+ otros campos específicos del grupo]

🔄 PROCESO DE GENERACIÓN
   ----------------------
   
   CERTIFICADOS INDIVIDUALES:
   1. Usuario crea/edita certificado individual
   2. Al guardar/publicar:
      • Se obtienen URLs de firmas personalizadas
      • Si están vacías, usa firmas predeterminadas
      • Se genera PDF con firmas PNG insertadas (60mm ancho, alto proporcional)
      • QR posicionado dinámicamente para evitar saltos de página
   
   CERTIFICADOS GRUPALES:
   1. Usuario crea certificado grupal con lista de participantes
   2. Al publicar:
      • Se generan certificados individuales automáticamente para cada participante
      • Se obtienen URLs de firmas personalizadas del grupo
      • Se aplican las mismas firmas PNG a todos los certificados del grupo
      • Se genera PDF compilado con diplomas individuales
      • Tanto certificado grupal como diplomas compilados usan las firmas personalizadas

📐 ADAPTACIÓN AUTOMÁTICA DE TAMAÑOS DE IMAGEN
   ------------------------------------------
   
   ✅ PROPORCIONES AUTOMÁTICAS:
   • Ancho fijo: 60mm (para mantener uniformidad visual)
   • Alto automático: TCPDF calcula según proporciones originales
   • Sin deformación: Las imágenes mantienen su aspecto original
   
   🎯 RECOMENDACIONES DE IMAGEN:
   • Formato: PNG (preferido) o JPG
   • Proporción ideal: 3:1 (ancho:alto) para mejor ajuste
   • Resolución mínima: 300x100 píxeles
   • Máximo recomendado: 900x300 píxeles
   • Fondo transparente: PNG para mejor integración
   
   ⚠️ IMÁGENES PROBLEMÁTICAS:
   • Muy altas (cuadradas): Ocuparán mucho espacio vertical
   • Muy anchas: Se verán muy delgadas al escalar a 60mm
   • Baja resolución: Pixeladas al imprimir
   
   🔧 COMPORTAMIENTO AUTOMÁTICO:
   Si subes una imagen de 600x200px (proporción 3:1):
   • Ancho final: 60mm
   • Alto final: 20mm (calculado automáticamente)
   • Sin deformación ni estiramiento
      • Si están vacías → se usa firma por defecto
      • Se genera PDF con firmas apropiadas
      • Se genera diploma con las mismas firmas
   3. Ambos documentos quedan almacenados en wp-content/uploads/

🚀 VENTAJAS DEL SISTEMA ACTUAL
   ----------------------------
   
   ✅ Flexibilidad: Firmas diferentes por certificado
   ✅ Simplicidad: Fallback automático a firma por defecto
   ✅ Seguridad: Validación de URLs
   ✅ Mantenibilidad: Fácil cambio de firma global
   ✅ Usabilidad: Interfaz clara en el admin
   ✅ Consistencia: Mismas firmas en certificado y diploma

=============================================================================
*/

// =============================================================================
// REGENERADOR MASIVO DE PDFs - FUNCIONALIDAD SEGURA PARA ACTUALIZACIONES
// =============================================================================

// Agregar página de regeneración masiva de PDFs
add_action('admin_menu', 'zc_agregar_pagina_regenerador');
function zc_agregar_pagina_regenerador() {
    add_submenu_page(
        'edit.php?post_type=certificado',
        'Regenerar todos los PDFs',
        '🔄 Regenerar PDFs',
        'manage_options',
        'zc-regenerador-pdfs',
        'zc_mostrar_pagina_regenerador'
    );
}

// Mostrar la página del regenerador
function zc_mostrar_pagina_regenerador() {
    ?>
    <div class="wrap">
        <h1>🔄 Regenerar todos los PDFs</h1>
        <div class="notice notice-info">
            <p><strong>¿Para qué sirve esta herramienta?</strong></p>
            <ul>
                <li>✅ Aplica automáticamente las mejoras más recientes a todos los certificados existentes</li>
                <li>✅ Actualiza textos (ej: "Empresa o Particular" → "Cliente")</li>
                <li>✅ Actualiza firmas, formatos y otros cambios visuales</li>
                <li>✅ Procesa de forma segura sin perder datos</li>
            </ul>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <h2>Regenerar Certificados y Diplomas</h2>
            <p>Esta acción regenerará todos los PDFs (certificados y diplomas) con las configuraciones más recientes.</p>
            
            <div id="zc-regeneracion-progreso" style="display: none;">
                <h3>🔄 Procesando...</h3>
                <div style="background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <div id="zc-barra-progreso" style="background: #0073aa; height: 20px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="zc-estado-actual">Preparando...</div>
                <div id="zc-log-regeneracion" style="max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;"></div>
            </div>
            
            <div id="zc-botones-regeneracion">
                <button type="button" id="zc-btn-regenerar-individuales" class="button button-primary">
                    🔄 Regenerar Certificados Individuales
                </button>
                <button type="button" id="zc-btn-regenerar-grupales" class="button button-primary" style="margin-left: 10px;">
                    🔄 Regenerar Certificados Grupales
                </button>
                <br><br>
                <button type="button" id="zc-btn-regenerar-todos" class="button button-hero button-primary">
                    ⚡ Regenerar TODOS los PDFs
                </button>
            </div>
            
            <div id="zc-resultado-final" style="display: none;">
                <h3>✅ Regeneración Completada</h3>
                <div id="zc-resumen-resultados"></div>
                <button type="button" onclick="location.reload()" class="button button-secondary">🔄 Regenerar más PDFs</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let procesandoActualmente = false;
        
        // Regenerar solo certificados individuales
        $('#zc-btn-regenerar-individuales').click(function() {
            if (procesandoActualmente) return;
            if (confirm('¿Regenerar todos los certificados individuales? Esto puede tomar varios minutos.')) {
                iniciarRegeneracion('individuales');
            }
        });
        
        // Regenerar solo certificados grupales
        $('#zc-btn-regenerar-grupales').click(function() {
            if (procesandoActualmente) return;
            if (confirm('¿Regenerar todos los certificados grupales? Esto puede tomar varios minutos.')) {
                iniciarRegeneracion('grupales');
            }
        });
        
        // Regenerar todos los PDFs
        $('#zc-btn-regenerar-todos').click(function() {
            if (procesandoActualmente) return;
            if (confirm('¿Regenerar TODOS los PDFs (individuales y grupales)? Esto puede tomar bastante tiempo.')) {
                iniciarRegeneracion('todos');
            }
        });
        
        function iniciarRegeneracion(tipo) {
            procesandoActualmente = true;
            $('#zc-botones-regeneracion').hide();
            $('#zc-regeneracion-progreso').show();
            $('#zc-resultado-final').hide();
            $('#zc-estado-actual').text('Obteniendo lista de certificados...');
            
            // Primero obtener el conteo total
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 30000, // 30 segundos timeout
                data: {
                    action: 'zc_contar_certificados',
                    tipo: tipo,
                    nonce: '<?php echo wp_create_nonce("zc_regenerar_pdfs"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        procesarPorLotes(tipo, response.data.total, 0, {
                            individuales: 0,
                            diplomas_individuales: 0,
                            grupales: 0,
                            diplomas_grupales: 0,
                            total: 0,
                            errores: []
                        });
                    } else {
                        alert('Error al obtener certificados: ' + response.data);
                        resetearInterfaz();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error:', xhr, status, error);
                    alert('Error de conexión al obtener certificados. Revisa la consola para más detalles.');
                    resetearInterfaz();
                }
            });
        }
        
        function procesarPorLotes(tipo, totalCertificados, offset, resultadosAcumulados) {
            const loteSize = 5; // Procesar de 5 en 5 para evitar timeouts
            
            $('#zc-estado-actual').text('Procesando lote ' + Math.floor(offset/loteSize + 1) + '...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 60000, // 60 segundos por lote
                data: {
                    action: 'zc_regenerar_pdfs_lote',
                    tipo: tipo,
                    offset: offset,
                    limit: loteSize,
                    nonce: '<?php echo wp_create_nonce("zc_regenerar_pdfs"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Acumular resultados
                        resultadosAcumulados.individuales += response.data.individuales || 0;
                        resultadosAcumulados.diplomas_individuales += response.data.diplomas_individuales || 0;
                        resultadosAcumulados.grupales += response.data.grupales || 0;
                        resultadosAcumulados.diplomas_grupales += response.data.diplomas_grupales || 0;
                        resultadosAcumulados.total += response.data.total || 0;
                        
                        if (response.data.errores) {
                            resultadosAcumulados.errores = resultadosAcumulados.errores.concat(response.data.errores);
                        }
                        
                        // Actualizar progreso
                        const progreso = Math.min(((offset + loteSize) / totalCertificados) * 100, 100);
                        $('#zc-barra-progreso').css('width', progreso + '%');
                        
                        // Si hay más certificados, continuar con el siguiente lote
                        if (offset + loteSize < totalCertificados) {
                            setTimeout(() => {
                                procesarPorLotes(tipo, totalCertificados, offset + loteSize, resultadosAcumulados);
                            }, 1000); // Pausa de 1 segundo entre lotes
                        } else {
                            // Terminado
                            mostrarResultadoFinal(resultadosAcumulados);
                        }
                    } else {
                        alert('Error en lote: ' + response.data);
                        mostrarResultadoFinal(resultadosAcumulados); // Mostrar resultados parciales
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error en lote:', xhr, status, error);
                    resultadosAcumulados.errores.push('Error de conexión en lote ' + Math.floor(offset/loteSize + 1));
                    
                    // Intentar continuar con el siguiente lote
                    if (offset + loteSize < totalCertificados) {
                        setTimeout(() => {
                            procesarPorLotes(tipo, totalCertificados, offset + loteSize, resultadosAcumulados);
                        }, 2000); // Pausa más larga después de error
                    } else {
                        mostrarResultadoFinal(resultadosAcumulados);
                    }
                }
            });
        }
        

        
        function mostrarResultadoFinal(resultados) {
            procesandoActualmente = false;
            $('#zc-regeneracion-progreso').hide();
            $('#zc-resultado-final').show();
            $('#zc-barra-progreso').css('width', '100%');
            
            let resumen = '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;">';
            resumen += '<h4>📊 Resumen de regeneración:</h4>';
            resumen += '<ul>';
            resumen += '<li><strong>Certificados individuales:</strong> ' + (resultados.individuales || 0) + ' regenerados</li>';
            resumen += '<li><strong>Diplomas individuales:</strong> ' + (resultados.diplomas_individuales || 0) + ' regenerados</li>';
            resumen += '<li><strong>Certificados grupales:</strong> ' + (resultados.grupales || 0) + ' regenerados</li>';
            resumen += '<li><strong>Diplomas grupales:</strong> ' + (resultados.diplomas_grupales || 0) + ' regenerados</li>';
            resumen += '<li><strong>Total PDFs actualizados:</strong> ' + (resultados.total || 0) + '</li>';
            resumen += '</ul>';
            if (resultados.errores && resultados.errores.length > 0) {
                resumen += '<h4 style="color: #dc3545;">⚠️ Errores encontrados:</h4>';
                resumen += '<ul>';
                resultados.errores.forEach(function(error) {
                    resumen += '<li style="color: #dc3545;">' + error + '</li>';
                });
                resumen += '</ul>';
            }
            resumen += '</div>';
            
            $('#zc-resumen-resultados').html(resumen);
        }
        
        function resetearInterfaz() {
            procesandoActualmente = false;
            $('#zc-botones-regeneracion').show();
            $('#zc-regeneracion-progreso').hide();
            $('#zc-resultado-final').hide();
        }
    });
    </script>
    <?php
}

// AJAX handler para contar certificados
add_action('wp_ajax_zc_contar_certificados', 'zc_contar_certificados_ajax');
function zc_contar_certificados_ajax() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['nonce'], 'zc_regenerar_pdfs')) {
        wp_send_json_error('Token de seguridad inválido');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos suficientes');
    }
    
    $tipo = sanitize_text_field($_POST['tipo']);
    $total = 0;
    
    if ($tipo === 'individuales' || $tipo === 'todos') {
        $individuales = get_posts(array(
            'post_type' => 'certificado',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        $total += count($individuales);
    }
    
    if ($tipo === 'grupales' || $tipo === 'todos') {
        $grupales = get_posts(array(
            'post_type' => 'certificado_grupal',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        $total += count($grupales);
    }
    
    wp_send_json_success(array('total' => $total));
}

// AJAX handler para regeneración por lotes
add_action('wp_ajax_zc_regenerar_pdfs_lote', 'zc_procesar_regeneracion_lote');
function zc_procesar_regeneracion_lote() {
    // Verificar nonce de seguridad
    if (!wp_verify_nonce($_POST['nonce'], 'zc_regenerar_pdfs')) {
        wp_send_json_error('Token de seguridad inválido');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos suficientes');
    }
    
    $tipo = sanitize_text_field($_POST['tipo']);
    $offset = intval($_POST['offset']);
    $limit = intval($_POST['limit']);
    
    $resultados = array(
        'individuales' => 0,
        'diplomas_individuales' => 0,
        'grupales' => 0,
        'diplomas_grupales' => 0,
        'total' => 0,
        'errores' => array()
    );
    
    // Aumentar límites de tiempo y memoria
    set_time_limit(120); // 2 minutos por lote
    ini_set('memory_limit', '256M');
    
    try {
        if ($tipo === 'individuales' || $tipo === 'todos') {
            $resultados_individuales = zc_regenerar_certificados_individuales_lote($offset, $limit);
            $resultados['individuales'] = $resultados_individuales['certificados'];
            $resultados['diplomas_individuales'] = $resultados_individuales['diplomas'];
            if (!empty($resultados_individuales['errores'])) {
                $resultados['errores'] = array_merge($resultados['errores'], $resultados_individuales['errores']);
            }
        }
        
        if ($tipo === 'grupales' || $tipo === 'todos') {
            $resultados_grupales = zc_regenerar_certificados_grupales_lote($offset, $limit);
            $resultados['grupales'] = $resultados_grupales['certificados'];
            $resultados['diplomas_grupales'] = $resultados_grupales['diplomas'];
            if (!empty($resultados_grupales['errores'])) {
                $resultados['errores'] = array_merge($resultados['errores'], $resultados_grupales['errores']);
            }
        }
        
        $resultados['total'] = $resultados['individuales'] + $resultados['diplomas_individuales'] + 
                              $resultados['grupales'] + $resultados['diplomas_grupales'];
        
        wp_send_json_success($resultados);
        
    } catch (Exception $e) {
        wp_send_json_error('Error durante la regeneración: ' . $e->getMessage());
    }
}

// Regenerar certificados individuales por lotes
function zc_regenerar_certificados_individuales_lote($offset, $limit) {
    $resultados = array('certificados' => 0, 'diplomas' => 0, 'errores' => array());
    
    // Obtener certificados individuales del lote actual
    $certificados = get_posts(array(
        'post_type' => 'certificado',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'post_status' => 'publish'
    ));
    
    foreach ($certificados as $certificado) {
        try {
            // Regenerar certificado PDF
            zc_final_generar_pdf($certificado->ID, $certificado);
            $resultados['certificados']++;
            
            // Regenerar diploma PDF
            zc_final_generar_diploma($certificado->ID, $certificado);
            $resultados['diplomas']++;
            
        } catch (Exception $e) {
            $resultados['errores'][] = "Error en certificado {$certificado->ID}: " . $e->getMessage();
        }
    }
    
    return $resultados;
}

// Regenerar certificados grupales por lotes
function zc_regenerar_certificados_grupales_lote($offset, $limit) {
    $resultados = array('certificados' => 0, 'diplomas' => 0, 'errores' => array());
    
    // Obtener certificados grupales del lote actual
    $certificados_grupales = get_posts(array(
        'post_type' => 'certificado_grupal',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'post_status' => 'publish'
    ));
    
    foreach ($certificados_grupales as $certificado_grupal) {
        try {
            // Regenerar certificado grupal PDF
            zc_grupal_generar_pdf_manual($certificado_grupal->ID, $certificado_grupal);
            $resultados['certificados']++;
            
            // Regenerar diplomas compilados del grupo
            zc_grupal_generar_diplomas_compilados($certificado_grupal->ID);
            $resultados['diplomas']++;
            
        } catch (Exception $e) {
            $resultados['errores'][] = "Error en certificado grupal {$certificado_grupal->ID}: " . $e->getMessage();
        }
    }
    
    return $resultados;
}

