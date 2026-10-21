# Mudanza a un servidor nuevo — qué configurar

Lista de todo lo que depende del servidor o de cuentas externas. Al pasar el
sitio a otro hosting (o a otro dominio), recorrerla entera. Cada archivo
involucrado tiene en su cabezal un aviso **"MUDANZA DE SERVIDOR"** que apunta
acá.

Este archivo está en el repo (sin datos secretos) y no se sirve por web
(`wp-content/.htaccess` bloquea los `.md`).

---

## 1. Archivos con claves — en la raíz de WordPress, FUERA del repo

Viven al lado de `wp-config.php`, fuera de `wp-content/`, así que **git no
los sube nunca**: hay que copiarlos a mano (FTP / administrador de archivos
del hosting). Si falta alguno, la función correspondiente se apaga sola sin
romper el sitio.

| Archivo | Qué tiene | Lo usa | Qué revisar en el servidor nuevo |
|---|---|---|---|
| `wp-config.php` | Base de datos, SALT, debug, constantes propias | WordPress | Ver sección 2 |
| `jb3-sc-stats.php` | Cuenta de servicio de Google (clave privada) para la planilla de vistas/descargas de Op. Comerciales | `JB3_Google_Sheets` (jb3-members) | Copiar tal cual. **Antes del lanzamiento: generar una clave nueva en Google Cloud y borrar la vieja** (pendiente desde 2026-10-09). La planilla tiene que seguir compartida con el email de la cuenta de servicio (`client_email`) |
| `jb3-search-cfg.php` | Claves de API de Claude y Gemini | Bot de búsqueda (jb3-ai-search) | Copiar tal cual, o poner claves de la cuenta definitiva. Poner un límite de gasto mensual en console.anthropic.com |
| `jb3-recaptcha-cfg.php` | Claves de reCAPTCHA v3 (site + secret) | Formulario de registro (`JB3_Recaptcha`) | En google.com/recaptcha/admin **agregar el dominio nuevo** a la lista de la clave — si no, el registro falla |

Formato de cada uno: ver el docblock de la clase que lo usa
(`class-google-sheets.php`, `jb3_aisearch_api_key()` en `jb3-ai-search.php`,
`class-recaptcha.php`).

## 2. `wp-config.php`

- **DB_NAME / DB_USER / DB_PASSWORD / DB_HOST**: los del hosting nuevo.
- **SALT (`AUTH_KEY`, `AUTH_SALT`, etc.)**: **copiar las mismas** del
  servidor anterior. Si se generan nuevas, todos quedan deslogueados y,
  además, las claves de la verificación en dos pasos de los admins no se
  pueden descifrar → cada admin tiene que reconfigurar Google Authenticator
  (ver README de jb3-members, sección 24).
- `WP_DEBUG` en `false` en producción (`WP_DEBUG_LOG` puede quedar, el
  `.htaccess` de wp-content bloquea los `.log`).
- `define( 'DISALLOW_FILE_EDIT', true );` — tiene que estar.
- **No** tienen que estar: `JB3_PERMITIR_LOGIN_CONTRASENA`,
  `JB3_DESACTIVAR_2FA`, `JB3_REINICIAR_2FA` (son llaves de emergencia).
- El bloque de "Cloudflare Tunnel / túneles" del wp-config local no hace
  falta en producción (igual queda inerte solo fuera de `jbalbi.test`).

## 3. `.htaccess` de la raíz — FUERA del repo, distinto en cada servidor

No copiar el de local tal cual. En el servidor nuevo tiene que tener:

- Bloque **`JB3 Credenciales`**: bloquea por web los archivos de la
  sección 1 y `wp-config.php`. Copiarlo del `.htaccess` actual.
- Bloque **`JB3 GeoIP`**: solo si el servidor es **LiteSpeed** con GeoIP.
  Si no lo es, sacarlo: el sitio pasa solo al modo de respaldo (país por
  JavaScript). Detalle en "CACHÉ DE PÁGINA POR PAÍS", plugin
  jb3-geo-targeting.
- Los bloques de WordPress y LiteSpeed Cache los regeneran sus plugins.
- La contraseña de carpeta del hosting actual (protección de pre-lanzamiento)
  **se saca** el día del lanzamiento.

El `.htaccess` de `wp-content/` sí viaja con el repo y no hay que tocarlo.

## 4. Base de datos — viaja con la exportación, pero revisar

- **Cambio de dominio**: hacer un buscar/reemplazar de la URL vieja por la
  nueva en toda la base (WP-CLI `wp search-replace` o un plugin tipo
  "Better Search Replace"; nunca a mano en SQL, rompe datos serializados).
- **JB3 Members → Configuración**: API key y Audience de Mailchimp, ID de
  la planilla de Google Sheets, email/nombre remitente, páginas de login,
  perfil y dashboard. Confirmar que estén.
- **Representantes** (tabla `wp_jb3_representatives`): los emails que
  aprueban registros.
- **WP Mail SMTP**: servidor y contraseña SMTP. Si cambia el dominio del
  remitente, configurar SPF/DKIM del dominio nuevo o los mails caen en spam
  (y sin mails nadie puede entrar: el login es por magic link).
- **GeoIP Detection**: la license key de MaxMind queda en la base; la base
  de países (`uploads/GeoLite2-City.mmdb`) viaja con `uploads/`.
- **ACF Pro**: reactivar la licencia en el dominio nuevo (Custom Fields →
  Updates).

## 5. Cuentas externas que apuntan al dominio

| Servicio | Qué cambiar |
|---|---|
| **Mailchimp** | Audiencia → Settings → Webhooks: la URL del webhook tiene el dominio (`https://DOMINIO/?jb3_mc_hook=...`). Actualizarla al nuevo (el secret es el mismo, está en la base) |
| **reCAPTCHA** | Agregar el dominio nuevo (sección 1) |
| **Google Analytics** | ID `G-EJZW4LPX6Y` en `themes/generatepress-child/_init_analytics.php` (`JB3_GA_MEASUREMENT_ID`). No es secreto. Cambiarlo solo si se usa otra propiedad (ej. una de Sony); actualizar la URL del flujo de datos en GA |
| **Google Search Console** | Dar de alta el dominio nuevo |
| **LinkedIn** | `JB3_LINKEDIN_EMPRESA_ID` en `_init_footer_menu.php` — es la empresa, no cambia con el servidor |

## 6. Después de subir — verificación

- [ ] `https://DOMINIO/wp-config.php`, `/jb3-sc-stats.php`,
      `/jb3-search-cfg.php`, `/jb3-recaptcha-cfg.php` → **403** (o página en
      blanco). Nunca el contenido.
- [ ] `https://DOMINIO/wp-content/.git/` y `/wp-content/debug.log` → 404/403.
- [ ] Login de un admin: llega el magic link y pide el código de 2 pasos.
- [ ] Registro nuevo: pasa reCAPTCHA y llega el mail al representante.
- [ ] Ver y descargar una Op. Comercial: aparece la fila en la planilla.
- [ ] Bot de búsqueda responde.
- [ ] País: entrar con VPN de dos países y ver contenido distinto (con la
      caché activa).
- [ ] Tiempo Real de Google Analytics registra la visita.
- [ ] LiteSpeed Cache → purgar todo.

## Seguridad de los archivos de claves

- Fuera de `wp-content` → nunca entran al repo (verificado: el historial de
  git no tiene ninguno).
- Cada uno empieza con `defined( 'ABSPATH' ) || exit;` y solo hace
  `return [...]`: abierto por web no muestra nada.
- Además, el bloque `JB3 Credenciales` del `.htaccess` de la raíz los
  bloquea (403).
- Permisos recomendados en el servidor: **600** (solo el dueño lee/escribe)
  o 640. Nunca 644/666/777.
- Quien tenga acceso al hosting (hPanel, FTP, administrador de archivos) los
  puede leer: proteger esa cuenta con verificación en dos pasos.
- Las claves de Mailchimp y SMTP están en la base de datos: los respaldos de
  la base también son sensibles.
