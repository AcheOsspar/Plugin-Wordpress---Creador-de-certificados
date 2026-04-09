# Actualizaciones del plugin Zen Certificados

Documento orientado a **usuarios y administradores** del sitio. Resume los últimos cambios técnicos aplicados al plugin y cómo aprovecharlos en el día a día.

---

## 1. Imagen de fondo del diploma

**Qué cambió**  
Se actualizó la dirección (URL) de la imagen que se usa como **fondo del diploma** (formato horizontal). Ahora el sistema usa la versión escalada alojada en la carpeta de medios de abril de 2026.

**Qué debes saber**  
- Los **diplomas nuevos** que se generen usarán automáticamente la nueva imagen.  
- Los PDFs de diplomas **ya generados** no se modifican solos: si necesitas el nuevo diseño, hay que **volver a generar** el diploma desde el certificado (o usar la opción de regeneración masiva si la utilizan en su flujo).

**Acción recomendada**  
Comprobar un diploma de prueba después de publicar el plugin para validar que la imagen se ve bien en pantalla y al imprimir.

---

## 2. Certificados grupales: código QR y pie de página

**Problema que se corregía**  
En algunos certificados **grupales**, el código QR (y el texto de verificación del pie) podía **superponerse** a las firmas o quedar mal ubicado cuando el contenido (tabla de participantes, etc.) ocupaba mucha altura en la página.

**Qué se hizo**  
- El pie de página **ya no se fija** a una posición rígida en la hoja. Se coloca **después de las firmas**, respetando el espacio disponible.  
- Si no cabe todo en la misma página, el sistema **añade una página** solo para el pie (código de validación, enlace y QR), evitando solapes.  
- El QR en certificados grupales se redujo ligeramente de tamaño (**sigue siendo perfectamente escaneable**) para dejar más margen visual respecto al resto del diseño.

**Qué no cambia**  
- El **certificado individual** mantiene el mismo criterio de maquetación que ya tenía.  
- El **contenido** del QR (enlace de verificación) es el mismo.

**Acción recomendada**  
Regenerar o volver a guardar los certificados grupales que deban quedar con el nuevo formato de pie y QR.

---

## 3. Temarios: subir varios PDF de una vez

**Qué es**  
En **Certificados → Temarios** hay una nueva sección para **importar varios archivos PDF** en un solo paso.

**Cómo usarlo**  
1. Entra a **Certificados → Temarios**.  
2. En **“Subir varios PDF a la vez”**, elige uno o más archivos (solo PDF).  
3. Pulsa **Importar a la lista de temarios**.  
4. Cada archivo queda:  
   - guardado en la **biblioteca de medios** de WordPress, y  
   - añadido a la **lista de temarios** con un nombre tomado del **nombre del archivo** (sin la extensión `.pdf`).

**Asignación a participantes / cursos**  
Los temarios de la lista se siguen eligiendo **manualmente** en cada **certificado** (desplegable de temario), igual que antes. La importación masiva solo **carga** opciones nuevas; no asigna automáticamente a nadie.

**Consejos**  
- Usa nombres de archivo claros antes de subir (ej. `Soldadura-Nivel-1.pdf`) para que el nombre en la lista sea útil.  
- Si hace falta, puedes **editar** el nombre o la URL desde la tabla de temarios, como siempre.

---

## Resumen rápido

| Área | Cambio principal |
|------|------------------|
| Diplomas | Nueva URL de imagen de fondo (diplomas nuevos). |
| Cert. grupales | Pie y QR sin solapar firmas; QR un poco más compacto. |
| Temarios | Importación de varios PDF en bloque a la misma lista. |

---

## Soporte técnico (referencia)

- **Plugin:** Zen Certificados (generador de certificados y diplomas).  
- Los cambios descritos están en el archivo principal del plugin (`Plugin-certificados.php`) y en la base de datos de temarios (tabla de temarios del plugin), sin cambios de rutina para el visitante del sitio.

Si necesitáis ampliar este documento con capturas de pantalla o pasos concretos de vuestro flujo interno, se puede añadir una sección “Procedimiento interno” aparte.
