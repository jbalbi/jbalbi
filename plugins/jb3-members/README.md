# JB3 Members

Plugin WordPress para registro, aprobación y acceso de miembros con magic link, sincronización bidireccional con Mailchimp y gestión completa desde el panel de administración.

---

## Índice

1. [¿Qué hace este plugin?](#1-qué-hace-este-plugin)
2. [Requisitos](#2-requisitos)
3. [Estructura de archivos](#3-estructura-de-archivos)
4. [Arranque del plugin](#4-arranque-del-plugin)
5. [Flujos del usuario](#5-flujos-del-usuario)
6. [Instalación y configuración inicial](#6-instalación-y-configuración-inicial)
7. [Requisitos en Mailchimp](#7-requisitos-en-mailchimp)
8. [Shortcodes](#8-shortcodes)
9. [Panel de administración](#9-panel-de-administración)
10. [Sistema de login por magic link](#10-sistema-de-login-por-magic-link)
11. [Guardias de sesión y perfil](#11-guardias-de-sesión-y-perfil)
12. [Sincronización con Mailchimp](#12-sincronización-con-mailchimp)
13. [Exportar e Importar usuarios (CSV)](#13-exportar-e-importar-usuarios-csv)
14. [Borrado masivo de miembros](#14-borrado-masivo-de-miembros)
15. [Seguridad](#15-seguridad)
16. [Referencia de datos de usuario](#16-referencia-de-datos-de-usuario)
17. [Cron y mantenimiento automático](#17-cron-y-mantenimiento-automático)
18. [Comportamiento en instalación nueva](#18-comportamiento-en-instalación-nueva)
19. [Notas para desarrolladores](#19-notas-para-desarrolladores)
20. [Debug y logs](#20-debug-y-logs)
21. [Contenido privado](#21-contenido-privado)
22. [Tracking de Op. Comerciales y Google Sheets](#22-tracking-de-op-comerciales-y-google-sheets)
23. [Entrada automática por link de Mailchimp (?e=)](#23-entrada-automática-por-link-de-mailchimp-e)
24. [Verificación en dos pasos (administradores)](#24-verificación-en-dos-pasos-administradores)

---

## 1. ¿Qué hace este plugin?

JB3 Members implementa un sistema completo de membresías para WordPress:

- **Registro con aprobación manual**: los usuarios se registran y quedan en estado *pendiente* hasta que un representante los aprueba. La aprobación se hace con un clic desde el email que llega al representante.
- **Login por magic link (sin contraseñas)**: el usuario ingresa su email, recibe un enlace de un solo uso (clickeable hasta 120 días) y al usarlo queda logueado con una sesión deslizante: se renueva sola mientras entre al menos cada 180 días, con un techo absoluto de 365 días desde el login.
- **Representantes por país**: cada país tiene uno o más representantes que reciben el email de aprobación. Si no hay representante asignado para un país, recibe el default.
- **Sincronización bidireccional con Mailchimp**: datos del perfil, preferencias de newsletter, intereses y newsletters por país se sincronizan automáticamente.
- **Administradores incluidos**: los usuarios administradores de WordPress pueden loguearse por magic link, tener sus datos en Mailchimp y gestionar todo el sistema. Para ellos el magic link vence en 1 hora y, además, piden un **código de Google Authenticator** (verificación en dos pasos, sección 24).
- **Sin login con contraseña**: `wp-login.php` no acepta contraseñas para nadie (ni admins) — solo magic link (sección 10, `JB3_Login`).
- **Exportación e importación por CSV**: gestión masiva con lógica inteligente de actualización.
- **Borrado masivo en chunks**: página dedicada para eliminar miles de miembros sin timeouts del servidor.
- **Limpieza automática**: usuarios pendientes de más de 30 días se eliminan por cron diario.

---

## 2. Requisitos

### WordPress
- WordPress 6.0 o superior
- PHP 7.4 o superior (se usan arrow functions y spread operator)

### Mailchimp
- Una cuenta Mailchimp con al menos una audiencia
- API Key de Mailchimp (formato `xxxxxxxx-us18`)
- Audience ID de la audiencia
- Campos de merge configurados (ver [sección 7](#7-requisitos-en-mailchimp))
- Dos categorías de intereses configuradas (ver [sección 7](#7-requisitos-en-mailchimp))

---

## 3. Estructura de archivos

```
jb3-members/
├── jb3-members.php                   # Punto de entrada: constantes, carga de archivos, hooks de activación
├── includes/
│   ├── helpers.php                   # Funciones globales y listas de datos
│   ├── class-database.php            # Instalación de tabla DB y registro del rol jb3_member
│   ├── class-representatives.php     # CRUD de representantes por país
│   ├── class-email.php               # Envío de emails transaccionales (3 tipos)
│   ├── class-magic-link.php          # Generación, validación y ciclo de vida del magic link
│   ├── class-two-factor.php          # Verificación en dos pasos de administradores (ver sección 24)
│   ├── class-registration.php        # Shortcode [jb3_register], aprobación por token
│   ├── class-profile-update.php      # Shortcode [jb3_profile], eliminación de cuenta
│   ├── class-login.php               # Shortcode [jb3_login], logout, rate limiting
│   ├── class-auth-guard.php          # Guard de sesión y redirección por perfil incompleto
│   ├── class-cleanup.php             # Cron diario de limpieza de pendientes
│   ├── class-mailchimp.php           # Sync individual, webhook, caché de intereses, payload compartido
│   ├── class-sync.php                # Sync masivo (Batch API) + limpieza MC→WP
│   ├── class-recaptcha.php           # reCAPTCHA v3 en el registro (ver sección 15)
│   ├── class-content-privacy.php     # Checkbox "Contenido privado" en cualquier CPT público (ver sección 21)
│   ├── class-mailchimp-entry.php     # Entrada automática por ?e= de campañas Mailchimp (ver sección 23)
│   ├── class-op-comerciales-tracking.php  # Tracking de vistas/descargas a Google Sheets (ver sección 22)
│   └── class-google-sheets.php       # Cliente mínimo de Google Sheets API v4 (ver sección 22)
├── admin/
│   └── class-admin.php               # Backoffice completo: menú, columnas, import/export, borrado
├── public/
│   ├── views/
│   │   ├── registration-form.php     # Template HTML del formulario de registro
│   │   ├── update-form.php           # Template HTML del formulario de perfil
│   │   └── login-form.php            # Template HTML del formulario de login
│   ├── css/jb3-forms.css             # Estilos de formularios (frontend y admin)
│   └── js/
│       ├── jb3-forms.js              # UX: validación inline, anti-doble-submit
│       └── qrcode.min.js             # Librería del QR de la verificación en dos pasos (copia local, MIT)
└── templates/
    ├── email-approval-request.php    # Email a representantes: nuevo registro pendiente
    ├── email-user-approved.php       # Email al usuario: aprobado + magic link inicial
    └── email-magic-link.php          # Email al usuario: enlace de acceso bajo demanda
```

---

## 4. Arranque del plugin

El archivo `jb3-members.php` define tres constantes usadas en todo el plugin:

| Constante | Valor | Uso |
|---|---|---|
| `JB3_VERSION` | `1.0.0` | Cache-busting de assets CSS/JS |
| `JB3_DIR` | ruta absoluta al directorio del plugin | Inclusión de archivos PHP y templates |
| `JB3_URL` | URL pública del directorio del plugin | Enqueue de CSS y JS |

La función `jb3_members_boot()` se engancha a `plugins_loaded` e inicializa todas las clases. La clase `JB3_Sync` y `JB3_Admin` solo se inicializan en contexto admin (`is_admin()`) para no cargar lógica de backoffice en el frontend.

Al **activar** el plugin se llaman:
- `JB3_Database::install()` — crea la tabla de representantes y el rol `jb3_member`
- `JB3_Cleanup::schedule()` — registra el cron diario

Al **desactivar**:
- `JB3_Cleanup::deactivate()` — cancela el cron de limpieza
- `JB3_Sync::deactivate()` — cancela cualquier cron de sync legado

---

## 5. Flujos del usuario

### Registro y aprobación

```
Usuario llena [jb3_register]
        ↓
Validación de campos (server-side)
        ↓
wp_insert_user() con rol jb3_member, contraseña aleatoria (no se usa nunca)
        ↓
Se guardan metas: status=pending, registered_at, todos los campos del perfil
        ↓
Se genera token de aprobación de 48 chars → guardado en jb3_approval_token
        ↓
JB3_Email::send_approval_request() → email a representantes del país del usuario
        ↓
Representante hace clic en "Aprobar" del email
        ↓
GET ?jb3_approve=1&uid=X&token=Y — manejado por JB3_Registration::handle_approval_request()
        ↓
hash_equals() verifica el token → status=approved, approved_at=ahora
        ↓
Token eliminado (delete_user_meta) — un solo uso garantizado
        ↓
JB3_Email::send_user_approved() → email al usuario con su magic link
        ↓
do_action('jb3_sync_to_mailchimp', $user_id) → sync a Mailchimp si newsletter=1
```

### Login posterior

```
Usuario ingresa email en [jb3_login]
        ↓
Rate limit: máx. 3 solicitudes por email cada 10 minutos (transient jb3_rl_*)
        ↓
Verificación: el usuario existe Y es jb3_member O es administrador
        ↓
Si es miembro: además debe estar aprobado (admins bypasean este check)
        ↓
JB3_Magic_Link::generate_url() → token 64 chars + expiry 120 días en user_meta (ventana para hacer clic, no la sesión)
        ↓
JB3_Email::send_magic_link() → email con URL ?jb3_magic=TOKEN
        ↓
Usuario hace clic → JB3_Magic_Link::handle_magic_link() (en hook init, priority 5)
        ↓
validate_token(): busca el token por SQL directo en usermeta → verifica expiry → verifica estado
        ↓
Token consumido: delete_user_meta(TOKEN_META, EXPIRY_META) — un solo uso
        ↓
wp_set_auth_cookie($user_id, remember=true) + SESSION_META (vencimiento +180 días) y SESSION_STARTED_META (ancla del techo) guardados server-side
        ↓
Filtro auth_cookie_expiration → cookie extendida a 180 días (INACTIVITY_DAYS) para jb3_member y admins
        ↓
Redirige al dashboard configurado
```

### Sesión deslizante (inactividad + techo absoluto)

En cada request de un usuario logueado, `JB3_Auth_Guard::check_session()` (hook `template_redirect`, priority 1) controla la sesión:

```
¿Pasó el vencimiento por inactividad (SESSION_META) O el techo absoluto (SESSION_STARTED_META + 365 días)?
        ↓ sí                                              ↓ no
JB3_Magic_Link::force_logout() + wp_logout()      JB3_Magic_Link::maybe_refresh_session()
redirige a login con ?jb3_error=session_expired   ¿queda menos de 1 día (REFRESH_THRESHOLD) de margen?
                                                          ↓ sí                        ↓ no
                                                   Corre SESSION_META otros 180    no hace nada
                                                   días (sin pasar el techo        (evita escribir/
                                                   absoluto) + reemite la          reemitir cookie
                                                   cookie con wp_set_auth_cookie() en cada request)
```

Un usuario que entra seguido nunca ve pedido de login de nuevo (se renueva sola, como mucho una escritura por día gracias al throttle); si deja de entrar 180 días seguidos, o pasan 365 días desde el login sin importar cuánto entró, la sesión se cierra sola. Usuarios que ya estaban logueados antes de esta funcionalidad (sin `SESSION_STARTED_META`) se migran solos: `check_session()` les ancla el techo absoluto la primera vez que se los ve después del update, arrancando desde ese momento.

### Gestión de perfil incompleto

Si `jb3_force_profile_completion` está activo y el usuario logueado (miembro aprobado o admin) tiene campos obligatorios vacíos, `JB3_Auth_Guard::enforce_complete_profile()` lo redirige a la página de perfil con `?redirect_to=URL_actual`. Al completar el perfil y guardar, el formulario redirige de vuelta a la URL original.

---

## 6. Instalación y configuración inicial

### Instalación

1. Subir la carpeta `jb3-members` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. La activación crea automáticamente:
   - Tabla `{prefix}jb3_representatives` con el representante default (juan jose balbi / juanjose@jb3.tv)
   - Rol WordPress `jb3_member` (único permiso: `read`)
   - El cron diario de limpieza

### Configuración obligatoria

Ir a **JB3 Members → Configuración**:

| Campo | Descripción |
|---|---|
| Página de Login | Página con shortcode `[jb3_login]` |
| Página de Perfil | Página con shortcode `[jb3_profile]` |
| Página de Dashboard | Destino post-login |
| API Key Mailchimp | Formato: `xxxxxxxx-usXX` (el sufijo define el servidor) |
| Audience ID | ID de la audiencia Mailchimp |
| Email remitente / Nombre remitente | `jb3_email_from` / `jb3_email_from_name` — remitente de los emails transaccionales (ver `JB3_Email::headers()`); si se dejan vacíos, cae a `admin_email`/nombre del sitio |
| ID de la planilla (Google Sheets) | `jb3_gsheets_id` — planilla donde se registran vistas/descargas de Op. Comerciales (ver sección 22) |
| Perfil obligatorio | Siempre activo. Todos los usuarios (miembros y admins) deben completar el perfil antes de navegar |

Las credenciales de reCAPTCHA (sección 15) y de la cuenta de servicio de Google Sheets (sección 22) **no** se configuran acá — viven en archivos PHP fuera de `wp-content` (fuera del repo git), ver el docblock de `class-recaptcha.php` y `class-google-sheets.php`.

### Sincronización de grupos Mailchimp

Después de guardar API Key + Audience ID, hacer clic en **"Actualizar grupos desde Mailchimp"**. Esto:
1. Borra los transients `jb3_mc_interests_cache` y `jb3_mc_nl_interests_cache`
2. Llama a `JB3_Mailchimp::fetch_interest_groups()` — una petición por cada categoría de intereses
3. Clasifica automáticamente categorías: las que contienen "newsletter" en el nombre → `jb3_mc_nl_interests_cache`; el resto → `jb3_mc_interests_cache`

### Representantes

Ir a **JB3 Members → Representantes**. El representante `is_default=1` (seeded en la instalación) recibe todas las solicitudes de países sin representante asignado. No puede eliminarse ni quitársele el estado default.

---

## 7. Requisitos en Mailchimp

### Campos de merge

Ir a **Audience → Settings → Audience fields and \*|MERGE|\* tags**:

| Merge Tag | Tipo sugerido | Descripción |
|---|---|---|
| `FNAME` | Text (nativo) | Nombre |
| `LNAME` | Text (nativo) | Apellido |
| `PHONE` | Phone | Teléfono |
| `COMPANY` | Text | Empresa |
| `COUNTRY` | Text | País (código ISO: AR, CO…) |
| `MMERGE5` | Text | Sector / Industria |
| `MMERGE6` | Text | Cargo |
| `ESTADO` | Text | Estado en JB3 (`Aprobado` o `Admin`) |

Usar tipo **Text** es más flexible que Dropdown: acepta cualquier valor sin configuración adicional.

#### Valores para COUNTRY (códigos ISO)
```
AR  BO  BR  CL  CO  CR  DO  EC  GT  HN
MX  NI  PA  PE  PY  SV  US  UY  VE
```

#### Valores para MMERGE6 (Cargos)
```
CEO (Chief Executive Officer), Director, Gerente, Supervisor, Coordinador de Proyectos,
Asesor, Administrador, Analista Senior, Especialista, Analista, Técnico, Operador,
Encargado, Representante, Inspector, Asistente, Auxiliar, Empleado, Otro
```

#### Valores para MMERGE5 (Sectores)
```
Servicios Profesionales, Comercio Minorista, Tecnología de la Información (TI),
Alimentación y Bebidas, Salud y Bienestar, Construcción y Arquitectura, Manufactura,
Educación, Medios y Entretenimiento, Transporte y Logística, Servicios Financieros,
Agricultura, Turismo y Hospitalidad, Bienes Raíces, Consultoría de Negocios,
Servicios de Ingeniería, Servicios Legales, Servicios de Marketing y Publicidad,
Servicios de Recursos Humanos, Comercio Electrónico, Otro
```

### Grupos de interés (Interest Categories)

El plugin espera exactamente **dos** categorías de grupos en la audiencia:

**Categoría 1 — Temas de interés general**
Nombre libre (cualquiera que NO contenga "newsletter").
Ejemplos: `Películas`, `Series`, `Producciones locales`, `Oportunidades de negocio`

**Categoría 2 — Newsletters por País**
El nombre **debe contener la palabra "newsletter"** (sin distinguir mayúsculas/minúsculas). El plugin la detecta automáticamente.
Ejemplos de intereses: `Argentina`, `Bolivia`, `Brasil`, `Chile`, `Colombia`, etc.

### Webhook Mailchimp → WordPress

1. Ir a **Audience → Settings → Webhooks → Create New Webhook**
2. **URL**: copiar desde JB3 Members → Configuración (campo "URL del Webhook")
   - Formato: `https://tu-sitio.com/?jb3_mc_hook=SECRETO_32_CHARS`
3. **Eventos a activar**: Subscribes, Unsubscribes, Profile Updates
4. En entornos locales (sin URL pública): usar Cloudflare Tunnel, ngrok, etc.

El secret del webhook se genera automáticamente la primera vez y se guarda en `jb3_mc_webhook_secret`. Peticiones sin el secret correcto reciben HTTP 403.

---

## 8. Shortcodes

### `[jb3_register]`
Formulario de registro de nuevos miembros.
- Muestra mensaje "ya registrado" si el usuario está logueado
- Campos requeridos: Nombre, Apellido, Email, País, Teléfono, Empresa, Sector, Cargo
- Campos opcionales: Intereses (cargados dinámicamente desde Mailchimp), Newsletter
- Al enviar: crea usuario + notifica representantes + NO loguea al usuario (debe esperar aprobación)

### `[jb3_login]`
Formulario de solicitud de magic link.
- Si el usuario ya está logueado como `jb3_member`: redirige al dashboard
- Si está logueado como admin: muestra el formulario (admins pueden visitarlo libremente)
- Al enviar: valida rate limit → busca usuario → envía magic link

### `[jb3_profile]`
Formulario de edición de perfil.
- Redirige al login si no está logueado o no es `jb3_member` ni admin
- Campos editables: todos excepto email
- Soporta `?redirect_to=URL` para volver a la página original tras guardar (usado por el guard de perfil completo)
- Al guardar: dispara `jb3_after_profile_save` → sincroniza con Mailchimp
- Incluye también el formulario de **eliminar cuenta** (`JB3_Profile_Update::handle_delete_account()`, hook `init`): pide confirmación + nonce propio (`jb3_delete_account_{user_id}`), y llama a `wp_delete_user()` — el hook `delete_user` archiva automáticamente el contacto en Mailchimp

### `[jb3_logout]`
Cierra la sesión y redirige al inicio. Se usa en una página dedicada.
- El shortcode no renderiza nada; la lógica está en `handle_logout_page()` via `template_redirect`

### `[jb3_representantes]`
Directorio público de representantes (`class-representatives.php`). Lista los representantes con `visible = 1`, con su foto (`photo_id`), teléfono y link de WhatsApp (`wa.me`) cuando está cargado.

---

## 9. Panel de administración

### JB3 Members → Representantes
CRUD completo. El representante default no puede eliminarse ni perder su estado.
Lógica de fallback: `get_by_country()` devuelve representantes del país exacto; si no hay ninguno, devuelve `get_defaults()`.

### JB3 Members → Configuración
- Páginas del plugin (login, perfil, dashboard)
- Credenciales Mailchimp + URL del webhook
- Botón "Actualizar grupos desde Mailchimp" (AJAX)
- Opción "Perfil obligatorio" (`jb3_force_profile_completion`)

### Lista de usuarios (WP → Usuarios)
Columnas adicionales: **Estado JB3** (Pendiente/Aprobado, con colores), **País**, **Empresa**.
La columna "username" y "posts" han sido eliminadas para mayor claridad.
Acciones por fila:
- **Aprobar**: disponible solo para usuarios pendientes. Aprueba + envía email + sync Mailchimp.
- **Desloguear**: invalida todas las sesiones del usuario via `JB3_Magic_Link::force_logout()`.

### Perfil de usuario (`wp-admin/user-edit.php`)
Sección **Datos JB3 Members** visible para miembros y administradores. Campos:
- Estado (Pendiente/Aprobado) — si pasa de Aprobado a Pendiente: archiva al usuario en Mailchimp
- Empresa, Cargo, Teléfono, País, Sector
- Newsletter (checkbox)
- **Newsletters por País** (checkboxes con IDs de Mailchimp) — solo editable por el admin, no aparece en el frontend
- **Intereses** (checkboxes con IDs de Mailchimp) — si Mailchimp no está disponible, muestra los valores almacenados como texto de solo lectura
- Fechas de registro y aprobación

Al guardar el perfil admin: sincroniza automáticamente con Mailchimp (o archiva si el estado cambió a pendiente).

**Protección del rol administrador**: el hook `set_user_role` detecta si se intenta cambiar el rol de un admin a otro rol via el dropdown nativo de WP, y restaura automáticamente el rol `administrator`. Esto evita que un admin se quede sin acceso por error.

---

## 10. Sistema de login por magic link

### Clase `JB3_Magic_Link`

**Constantes:**
- `TOKEN_VALID_DAYS = 120` — ventana para hacer clic en el magic link emailado (no es la duración de la sesión)
- `ADMIN_TOKEN_VALID_HOURS = 1` — la misma ventana para administradores (un mail viejo olvidado no da acceso de admin por meses)
- `INACTIVITY_DAYS = 180` — la sesión se renueva sola mientras el usuario entre al menos con esta frecuencia
- `MAX_SESSION_DAYS = 365` — techo absoluto desde el login real, sin importar cuánta actividad haya
- `REFRESH_THRESHOLD = DAY_IN_SECONDS` — margen mínimo de vencimiento restante antes de disparar una renovación (throttle, evita reescribir en cada request)
- `TOKEN_META = 'jb3_magic_token'` — meta key del token activo
- `EXPIRY_META = 'jb3_magic_token_expiry'` — meta key del timestamp de expiración del token
- `SESSION_META = 'jb3_session_expiry'` — meta key del vencimiento deslizante de la sesión
- `SESSION_STARTED_META = 'jb3_session_started'` — meta key del ancla fija del techo absoluto (se setea solo en el login real, nunca en una renovación)

**`generate_token($user_id)`**: crea un token alfanumérico de 64 chars con `wp_generate_password()`. Lo guarda en user_meta junto con su timestamp de expiración (ahora + `TOKEN_VALID_DAYS` días). Sobreescribe el token anterior si existía (solo puede haber un magic link activo por usuario).

**`generate_url($user_id)`**: llama a `generate_token()` y construye la URL completa: `{URL_pagina_login}?jb3_magic={TOKEN}`.

**`handle_magic_link()`**: se ejecuta en el hook `init` con priority 5 (antes del template). Si detecta `?jb3_magic=TOKEN` en la URL, llama a `validate_token()`. Si el token es válido, lo borra (un solo uso) y: si es **administrador**, en vez de iniciar sesión pasa al paso del código (`JB3_Two_Factor::iniciar()`, sección 24); si es miembro, llama a `completar_login()`. Si el token no es válido, redirige al login con `?jb3_error=invalid_link`. User-Agents de escáneres de correo conocidos se ignoran (no gastan el token).

**`completar_login($user, $redirect)`**: inicia la sesión (`wp_set_auth_cookie`, `wp_login`), setea `SESSION_META` (+`INACTIVITY_DAYS`) y `SESSION_STARTED_META` (ahora) y redirige. Último paso común del magic link (miembros) y de la verificación en dos pasos (admins).

**`validate_token($token)`**: busca el token por SQL directo en `wp_usermeta` (más eficiente que iterar usuarios). Verifica: token existe → no expirado → usuario aprobado (o admin). Retorna el objeto `WP_User` o `false`.

**`extend_cookie_expiration()`**: filtro `auth_cookie_expiration` que extiende la cookie a `INACTIVITY_DAYS` días cuando `$remember=true` para `jb3_member` y administradores.

**`maybe_refresh_session($user_id, $current_expiry, $session_started)`**: llamado por `JB3_Auth_Guard::check_session()` en cada request de un usuario logueado. Si queda menos de `REFRESH_THRESHOLD` de margen antes del vencimiento, corre `SESSION_META` otros `INACTIVITY_DAYS` hacia adelante (sin pasar `session_started + MAX_SESSION_DAYS`) y reemite la cookie con `wp_set_auth_cookie()`. Si todavía queda margen, no hace nada — así un usuario activo genera como mucho una escritura por día, no una por request.

**`force_logout($user_id)`**: destruye todas las sesiones WP del usuario + borra los cuatro metas de token/sesión (incluyendo `SESSION_STARTED_META`).

### Clase `JB3_Login`

Maneja el shortcode `[jb3_login]` y el rate limiting. El método `process()` implementa:

1. Sanitiza el email del POST
2. Verifica rate limit: si `jb3_rl_{md5(email)}` ≥ 3, retorna error (esperar 10 min)
3. Incrementa el contador con `set_transient(..., 10 * MINUTE_IN_SECONDS)`
4. Busca el usuario por email
5. Determina `$is_admin = user_can(...)` para decidir si aplican los checks de rol/estado
6. Si pasa todos los checks: llama a `JB3_Email::send_magic_link()`
7. Retorna `[[], true]` (éxito sin errores) — incluso para emails no registrados, para prevenir enumeración

**Login con contraseña apagado** (`block_password_login()` + `redirect_wp_login()`): `wp-login.php` redirige a `/login/` y rechaza usuario+contraseña para TODOS (admins incluidos), con el mismo mensaje sea la contraseña correcta o no. También se apagan "¿Olvidaste tu contraseña?" y las contraseñas de aplicación. Siguen andando cerrar sesión y las páginas protegidas con contraseña de WordPress. **Llave de emergencia** (mails caídos): `define( 'JB3_PERMITIR_LOGIN_CONTRASENA', true );` en `wp-config.php` — y sacarla apenas se resuelva. Con la llave puesta, los admins igual tienen que pasar la verificación en dos pasos.

**Pasos especiales del shortcode**: con `?jb3_2fa=1` muestra la pantalla de la verificación en dos pasos (`JB3_Two_Factor::render_paso()`, sección 24) en vez del formulario. Mensajes de `?jb3_error=`: `invalid_link`, `2fa_bloqueado` (demasiados códigos incorrectos), `2fa_requerido` (se cerró una sesión de admin sin verificar).

---

## 11. Guardias de sesión y perfil

### Clase `JB3_Auth_Guard`

Dos hooks en `template_redirect` (solo en el frontend):

**`check_session()` (priority 1)**:
- Solo actúa para `jb3_member` y administradores logueados
- Lee `jb3_session_expiry` (vencimiento deslizante) y `jb3_session_started` (ancla del techo absoluto) del user_meta
- Migración: si hay `$expiry` pero no `$session_started` (sesión de antes de esta funcionalidad), le ancla el techo absoluto ahora mismo (`session_started = time()`)
- Si `time() > $expiry` (venció por inactividad) O `time() > $session_started + MAX_SESSION_DAYS` (venció por techo absoluto): llama a `force_logout()`, desloguea y redirige al login con `?jb3_error=session_expired`
- Si la sesión sigue vigente: delega en `JB3_Magic_Link::maybe_refresh_session()` para renovarla si corresponde (con throttle interno)
- Si `$expiry = 0` (usuario nuevo o sin meta): no fuerza logout (safe)

**`enforce_complete_profile()` (priority 5)**:
- Aplica a todos los usuarios del plugin: `jb3_member` y administradores (sin distinción, sin opción de desactivar)
- Permite pasar libremente la página de perfil y la página de login
- Si `jb3_profile_complete()` retorna false: redirige a `{URL_perfil}?redirect_to={URL_actual}` (URL codificada con `rawurlencode`)

**`jb3_profile_complete($user_id)`** (en `helpers.php`): verifica que `first_name`, `last_name`, `jb3_company`, `jb3_job_title`, `jb3_phone`, `jb3_country` y `jb3_sector` no estén vacíos.

---

## 12. Sincronización con Mailchimp

### Sync individual (`JB3_Mailchimp`)

Se dispara automáticamente cuando:
- Un usuario es aprobado (por representante o por admin desde el panel)
- Un usuario guarda su perfil frontend (`jb3_after_profile_save`)
- Un admin guarda el perfil de un usuario desde el backoffice
- Se llama `do_action('jb3_sync_to_mailchimp', $user_id)` desde cualquier punto

**Lógica de `sync_user($user_id)`**:
1. Verifica credenciales Mailchimp
2. Si no es admin y el estado no es `approved`: retorna sin hacer nada
3. Si `jb3_newsletter = 0`:
   - Miembro: llama a `archive_contact()` — fire-and-forget
   - Admin: simplemente retorna (los admins no se archivan)
4. Si `jb3_newsletter = 1`: arma el payload con `build_member_data()` y hace PUT directo a `/lists/{list_id}/members/{hash}` con `status_if_new: 'subscribed'` y `status: 'subscribed'`

El PUT directo es **sincrónico** — espera la respuesta de Mailchimp. Los errores (HTTP 4xx/5xx) quedan logueados con `error_log('[JB3 Mailchimp] ...')`.

**`build_member_data(WP_User $user, bool $is_admin)`**: arma el payload completo (email, status, merge_fields, interests) para el upsert de UN miembro. Es el builder **compartido** entre `sync_user()` (acá) y `JB3_Sync::build_op()` (sync masivo, sección de abajo) — antes cada uno lo armaba por su cuenta y se habían desincronizado (el campo `ESTADO` quedaba "Aprobado" para admins en el sync individual); unificado en `class-mailchimp.php` (limpieza 2026-09-02).

**`archive_contact($email)`**: hace PATCH `status: unsubscribed` + DELETE sobre el contacto. Ambas llamadas tienen `'blocking' => false` (fire-and-forget) para no bloquear al usuario.

**Intereses en el sync**: los arrays `jb3_interests` y `jb3_newsletter_countries` almacenan IDs de Mailchimp. `build_member_data()` construye un mapa `[mc_id => true/false]` iterando todos los IDs conocidos del caché.

### Sync masivo (`JB3_Sync`)

Operado desde **JB3 Members → Sincronizar Mailchimp**. El navegador llama repetidamente a `ajax_chunk` hasta que la fase es `done`. El estado persiste en `wp_options` (`jb3_sync_state`) entre requests.

**Fase 1 — WP → Mailchimp (`wp_to_mc`)**:
- Itera miembros `jb3_member` en chunks de 500 usando `get_users()` (que pre-cachea objetos WP_User) + `update_meta_cache()` (una sola SQL para todos los metas del chunk)
- Por cada usuario: `build_op()` genera una operación `PUT` (newsletter=1) o `DELETE` (newsletter=0)
- Las operaciones del chunk se envían en un solo request a `/3.0/batches` (Batch API — asíncrona en Mailchimp)
- Una vez procesados todos los miembros: sincroniza admins con **PUT directo** (no Batch API) via `JB3_Mailchimp::sync_user()` — esto garantiza que los contactos archivados se reactiven inmediatamente y los errores sean visibles en el log

**Por qué admins usan PUT directo en lugar de Batch API**: la Batch API de Mailchimp es asíncrona. Para un volumen pequeño (1-2 admins) es más confiable usar la llamada directa, que es inmediata y retorna errores reales. Además reactiva correctamente contactos que estaban archivados.

**Fase 2 — Mailchimp → WP (`mc_to_wp`)**:
- Paginea los contactos activos de la audiencia en chunks de 500 (`/3.0/lists/{id}/members?count=500`)
- Por cada página: una sola SQL `IN()` verifica qué emails existen en WP
- Los emails que no están en WP se archivan en Mailchimp via Batch API DELETE

**`build_op(WP_User $user, string $list_id)`**: función unificada para miembros y admins — usa `JB3_Mailchimp::build_member_data()` (mismo builder que el sync individual) para el body del PUT.
- Admins: bypasean el check de estado, `ESTADO: 'Admin'`
- Miembros: solo si `jb3_status = approved`, `ESTADO: 'Aprobado'`

### Webhook Mailchimp → WordPress

`JB3_Mailchimp::handle_webhook()` intercepta GET/POST en `?jb3_mc_hook={secret}`.

| Evento Mailchimp | Acción en WordPress |
|---|---|
| GET (validación) | HTTP 200 + exit |
| `subscribe` | `jb3_newsletter = 1` |
| `unsubscribe` | `jb3_newsletter = 0` + archiva en MC |
| `cleaned` (hard bounce) | `jb3_newsletter = 0` + archiva en MC |
| `profile` | Actualiza: nombre, apellido, teléfono, empresa, sector, cargo, país |

Los eventos del webhook **no** disparan re-sync hacia Mailchimp (para evitar bucles infinitos).

### Caché de intereses

Transients de WordPress (TTL 1 hora):
- `jb3_mc_interests_cache` — mapa `{mc_id: nombre}` de intereses generales
- `jb3_mc_nl_interests_cache` — mapa `{mc_id: nombre}` de intereses de newsletters por país

Ambos se construyen en `build_all_caches()` con dos peticiones HTTP (una por categoría). Para forzar una actualización: **Configuración → Actualizar grupos desde Mailchimp**.

---

## 13. Exportar e Importar usuarios (CSV)

### Exportar

**JB3 Members → Exportar Usuarios → Descargar CSV**

Genera un CSV con BOM UTF-8 (compatible con Excel). Excluye automáticamente administradores.

#### Columnas del CSV exportado

| Columna | Formato | Notas |
|---|---|---|
| `ID` | Número entero | ID de WordPress |
| `Nombre` | Texto | `first_name` |
| `Apellido` | Texto | `last_name` |
| `Email` | Email | `user_email` |
| `Estado` | `Aprobado` / `Pendiente` | |
| `País` | Código ISO 2 letras | `AR`, `CO`, etc. |
| `Empresa` | Texto | |
| `Cargo` | Texto | |
| `Teléfono` | Texto | |
| `Sector` | Texto | |
| `Suscripto al newsletter` | `Sí` / `No` | |
| `Newsletters que recibe` | Nombres separados por coma | Nombres de los grupos Mailchimp |
| `Intereses` | Nombres separados por coma | Nombres de los grupos Mailchimp |
| `Fecha Registro WP` | `Y-m-d H:i:s` | `user_registered` de WP |
| `Fecha Registro JB3` | `Y-m-d H:i:s` | `jb3_registered_at` |
| `Fecha Aprobación` | `Y-m-d H:i:s` | `jb3_approved_at` |

Los intereses y newsletters se exportan como **nombres** (no IDs), usando el caché de Mailchimp. Si un ID no está en el caché, se exporta el ID crudo (fallback).

---

### Importar

**JB3 Members → Importar Usuarios**

Sube un CSV para crear o actualizar miembros en masa. La única columna obligatoria es `Email`. El proceso es asíncrono: el navegador llama repetidamente a `ajax_import_chunk` hasta procesar todas las filas.

#### Rendimiento

El import procesa **200 filas por chunk** con:
- Una sola SQL `IN()` por chunk para buscar todos los emails existentes (vs N llamadas `get_user_by()`)
- Pre-carga de objetos WP_User + meta cache para todos los IDs encontrados en el chunk
- No sincroniza con Mailchimp durante la importación (el sync masivo debe correr por separado desde la página de Sincronización)

#### Lógica de búsqueda del usuario

1. Si el CSV tiene columna `ID`: busca por ID de WordPress primero
2. Si no encuentra por ID (o no hay columna `ID`): busca por email
3. **Encontrado** → actualiza solo los campos presentes en el CSV
4. **No encontrado** → crea usuario nuevo con estado `approved` (auto-aprobado, sin email de bienvenida)

#### Reglas especiales

**Administradores**: las filas con email de un admin se omiten completamente con mensaje de error. Los admins no se pueden modificar desde el CSV.

**Usuarios existentes**: el CSV solo actualiza los datos personales. `Suscripto al newsletter`, `Newsletters que recibe` e `Intereses` se ignoran, porque son decisión del usuario. Si cambia el país y está suscripto, su grupo de newsletter pasa al país nuevo. Esas tres columnas solo se usan para usuarios nuevos.

**Campo País**: acepta tanto código ISO (`AR`) como nombre completo (`Argentina`).

**Campo Intereses**: acepta nombre del interés (`Películas`) o su ID de Mailchimp directamente.

**Campo Newsletters que recibe**: acepta nombres exactos de los grupos Mailchimp separados por coma (`AR, BO`). Si falta y el usuario nuevo se suscribe, recibe el grupo de su país.

**display_name**: se actualiza automáticamente si el CSV contiene nombre y/o apellido.

**Delimitador**: el import detecta automáticamente si el CSV usa `,` o `;` como separador.

#### Columnas reconocidas en el import

| Columna CSV | Campo interno | Notas |
|---|---|---|
| `Email` | `user_email` | **Obligatorio** |
| `ID` | búsqueda por ID | Opcional; si presente, tiene prioridad sobre email |
| `Nombre` | `first_name` | |
| `Apellido` | `last_name` | |
| `Estado` | `jb3_status` | `Aprobado`/`approved` o `Pendiente`/`pending` |
| `País` | `jb3_country` | Código ISO o nombre completo |
| `Empresa` | `jb3_company` | |
| `Cargo` | `jb3_job_title` | |
| `Teléfono` | `jb3_phone` | |
| `Sector` | `jb3_sector` | |
| `Suscripto al newsletter` | `jb3_newsletter` | `Sí`, `Si`, `1`, `Yes` = suscripto. Solo usuarios nuevos |
| `Newsletters que recibe` | `jb3_newsletter_countries` | Nombres de grupos separados por coma. Solo usuarios nuevos |
| `Intereses` | `jb3_interests` | Nombres o IDs separados por coma |
| `Fecha Registro JB3` | `jb3_registered_at` | `Y-m-d H:i:s` |
| `Fecha Aprobación` | `jb3_approved_at` | `Y-m-d H:i:s` |

Las columnas también se aceptan con sus nombres de meta-key en inglés (ej: `jb3_country` en lugar de `País`).

---

## 14. Borrado masivo de miembros

**JB3 Members → Borrar Miembros**

Página dedicada para eliminar grandes volúmenes de usuarios sin timeouts del servidor. El borrado se hace en chunks de 50 via AJAX.

### Opciones de filtro

Antes de iniciar se puede elegir qué usuarios borrar:
- **Todos / Pendientes / Aprobados**: filtro por rol y estado
- **Lista CSV**: subir un archivo con una columna `Email` y borrar puntualmente esos usuarios

### Funcionamiento técnico

- El estado del borrado persiste en `wp_options` bajo la clave `jb3_delete_state_{user_id}` (una por admin activo)
- Para los filtros por rol/estado, cada chunk consulta siempre los **primeros 50 usuarios** del filtro actual (sin offset). Al borrarlos en cada iteración, la consulta siguiente automáticamente encuentra los siguientes. Este patrón es más robusto que usar offset con filas que se están eliminando.
- El borrado usa `DELETE` SQL directo sobre `wp_users`/`wp_usermeta` (no `wp_delete_user()`), así que **no dispara el hook `delete_user`** (ese hook solo lo dispara `wp_delete_user()`) — no hace falta suprimir nada.
- **Los administradores nunca se borran**: `if ( user_can( $uid, 'manage_options' ) ) continue;`
- Soporta **cancelar** y **reanudar** — si se cierra la ventana o se cancela, el estado queda guardado y se puede retomar

### Lista CSV: subida, previsualización y archivado inmediato

1. Se sube un CSV (mismo detector de delimiter `;`/`,` y de BOM que "Importar Usuarios") con columna `Email` obligatoria, vía `jb3_delete_list_upload`.
2. El backend matchea los emails contra `wp_users`, excluye administradores y guarda el resultado (`list_ids`) en el estado con `phase: 'ready'`. Responde con: cuántos se van a borrar, cuántos admins se excluyeron y qué emails no se encontraron.
3. El admin ve esa previsualización y recién ahí puede presionar "Iniciar borrado" (`jb3_delete_start` pasa el estado a `phase: 'running'`).
4. A diferencia de los filtros por rol/estado, en este modo cada chunk **archiva en Mailchimp de inmediato** (`JB3_Mailchimp::archive_contact()` por cada email borrado) antes de continuar — no se difiere a la sincronización. El volumen esperado de una lista puntual es acotado, por lo que las llamadas fire-and-forget adicionales no son un problema de rendimiento.

### Limitaciones

Los filtros por rol/estado (Todos/Pendientes/Aprobados) no sincronizan con Mailchimp en el momento, para evitar 50+ llamadas HTTP fire-and-forget por chunk cuando el volumen es grande. Para archivar esos usuarios en Mailchimp, correr la sincronización masiva después — la Fase 2 detectará emails que ya no existen en WP y los archivará. El modo **Lista CSV** no tiene esta limitación: archiva en el momento.

---

## 15. Seguridad

### Formularios frontend y admin
- Todos usan **nonces de WordPress** (CSRF protection)
- Todos los inputs se sanitizan antes de guardarse (`sanitize_text_field`, `sanitize_email`, `absint`, `jb3_sanitize_phone`)
- Outputs en templates usan `esc_html()`, `esc_attr()`, `esc_url()` (XSS protection)
- Los handlers AJAX verifican `current_user_can('manage_options')` antes de operar

### Magic links
- Token de **64 caracteres aleatorios** via `wp_generate_password(64, false)`
- **Un solo uso**: se eliminan inmediatamente al consumirse
- Expiración doble: TTL en user_meta (verificado en `validate_token`) + TTL de cookie (extendido via filtro)
- Solo funcionan para usuarios aprobados (o admins)

### Token de aprobación
- 48 caracteres aleatorios
- Comparado con `hash_equals()` — previene timing attacks
- **Dos pasos**: abrir el link (GET) solo muestra los datos y un botón "Aprobar"; recién el POST del botón aprueba — los escáneres de seguridad de correo corporativo abren los links solos y aprobaban usuarios sin que nadie hiciera clic (revisión 2026-10-01)
- **No se borra al aprobar**: el segundo representante que abre el mismo email tiene que pasar el chequeo del token para ver "Usuario ya aprobado" (antes se borraba y veía "token inválido")

### Administradores: verificación en dos pasos
- Además del magic link, un código de app de autenticación (TOTP). Detalle completo, recuperación y qué tocar para cambiarlo: **sección 24**

### Rate limiting en login
- Máximo **3 solicitudes por email cada 10 minutos** (transient `jb3_rl_{md5(email)}`)
- Sin infraestructura adicional (usa transients de WP)
- El mensaje de error revela que el rate limit fue alcanzado, pero el tiempo de expiración no se muestra al usuario

### Rate limiting + reCAPTCHA en registro
- Además del rate limit por email de arriba (compartido con login), `[jb3_register]` tiene su **propio freno por IP**: máx. 5 intentos cada 10 minutos (`jb3_rate_limited('register_' . $ip, 5, 10)`) — evita que se automaticen altas masivas con emails distintos usando el mismo nonce (auditoría 2026-08-27)
- **reCAPTCHA v3** (`JB3_Recaptcha`, invisible, por puntaje ≥ `MIN_SCORE` = 0.5) — solo en el formulario de registro, no en login (que ya tiene su propio rate-limit). Si no hay credenciales configuradas, no bloquea (degrada sin romper, mismo criterio que el resto del sitio con servicios externos caídos)

### Anti-enumeración de email
- `[jb3_register]`: si el email ya existe, responde exactamente igual que un alta exitosa (sin decir "ya registrado") — evita que cualquiera use el formulario como oráculo para saber qué emails son usuarios del sitio (auditoría 2026-08-28)
- `[jb3_login]`: mismo criterio — un email no registrado recibe el mismo mensaje de éxito que uno real

### Webhook de Mailchimp
- URL incluye secret de 32 chars único por instalación, generado automáticamente con `wp_generate_password(32, false)`
- Peticiones sin secret correcto → HTTP 403

### Protección de archivos PHP
```php
defined( 'ABSPATH' ) || exit;
```
Primera línea de todos los archivos PHP del plugin.

### Protección del rol administrador
El hook `set_user_role` detecta si se intenta quitar el rol `administrator` a un admin (por ejemplo via el dropdown nativo de WP) y lo restaura automáticamente. Esto previene que un admin se accidente y pierda acceso al sitio.

---

## 16. Referencia de datos de usuario

### User meta (`wp_usermeta`)

| Meta key | Tipo | Descripción |
|---|---|---|
| `jb3_status` | `pending` / `approved` | Estado de aprobación |
| `jb3_company` | string | Empresa |
| `jb3_job_title` | string | Cargo |
| `jb3_phone` | string | Teléfono |
| `jb3_country` | string (ISO 2 letras) | País |
| `jb3_sector` | string | Sector / Industria |
| `jb3_newsletter` | `0` / `1` | Suscripción al newsletter principal |
| `jb3_newsletter_countries` | array de strings | IDs de intereses Mailchimp (newsletters por país) |
| `jb3_interests` | array de strings | IDs de intereses Mailchimp (temas generales) |
| `jb3_registered_at` | datetime `Y-m-d H:i:s` | Fecha de registro en JB3 |
| `jb3_approved_at` | datetime `Y-m-d H:i:s` | Fecha de aprobación |
| `jb3_approval_token` | string 48 chars | Token de aprobación (se conserva después de aprobar: ver sección 15) |
| `jb3_magic_token` | string 64 chars | Token one-time del magic link activo |
| `jb3_magic_token_expiry` | int (Unix timestamp) | Expiración del magic link |
| `jb3_session_expiry` | int (Unix timestamp) | Vencimiento deslizante de la sesión (se renueva sola con actividad, cada 180 días) |
| `jb3_session_started` | int (Unix timestamp) | Ancla del login real — techo absoluto de la sesión a los 365 días desde este valor |
| `jb3_2fa_secreto` | string (base64) | Solo admins: clave de la app de autenticación, **cifrada** (sección 24). Borrarla = reiniciar la verificación |
| `jb3_2fa_ultimo` | int | Solo admins: último paso de tiempo de código aceptado (impide reusar un código) |
| `jb3_2fa_reinicio` | string (hash) | Solo admins: último valor de `JB3_REINICIAR_2FA` ya aplicado (para no reiniciar en cada ingreso si la línea queda olvidada) |

**Nota sobre intereses**: el array `jb3_interests` almacena IDs de Mailchimp (ej: `abc123`), no nombres. Esto es correcto y es lo que se envía a Mailchimp en el sync. Los nombres de los intereses se resuelven en runtime via el caché.

### WP Options (`wp_options`)

| Option key | Descripción |
|---|---|
| `jb3_login_page_id` | ID de la página de login |
| `jb3_profile_page_id` | ID de la página de perfil |
| `jb3_dashboard_page_id` | ID de la página de dashboard |
| `jb3_mc_api_key` | API Key de Mailchimp |
| `jb3_mc_list_id` | Audience ID de Mailchimp |
| `jb3_mc_webhook_secret` | Secret del webhook (generado automáticamente) |
| `jb3_db_version` | Versión del esquema de base de datos (`1.0`) |
| `jb3_sync_state` | Estado del sync masivo en curso (se borra al completar) |
| `jb3_import_state_{user_id}` | Estado del import en curso por usuario admin |
| `jb3_delete_state_{user_id}` | Estado del borrado en curso por usuario admin |

### Transients

| Transient key | TTL | Descripción |
|---|---|---|
| `jb3_mc_interests_cache` | 1 hora | Mapa `{mc_id: nombre}` de intereses generales |
| `jb3_mc_nl_interests_cache` | 1 hora | Mapa `{mc_id: nombre}` de intereses de newsletters |
| `jb3_rl_{md5(email)}` | 10 minutos | Contador de solicitudes de magic link (rate limiting) |
| `jb3_rep_errors_{user_id}` | 60 segundos | Errores de validación de representantes (PRG pattern) |
| `jb3_rep_data_{user_id}` | 60 segundos | Datos del form de representantes (PRG pattern) |

### Rol WordPress

El plugin crea el rol `jb3_member` con el único permiso `read`. Se asigna a todos los usuarios registrados vía el formulario de registro.

Los administradores de WordPress **no reciben** el rol `jb3_member`. Son reconocidos en el código siempre por `user_can($user_id, 'manage_options')`, no por `jb3_is_member()`.

### Tablas de base de datos (schema v1.2, `JB3_Database::DB_VER`)

`{prefix}jb3_representatives`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Autoincremental |
| `first_name` | VARCHAR(100) | Nombre |
| `last_name` | VARCHAR(100) | Apellido |
| `email` | VARCHAR(150) | Email |
| `phone` | VARCHAR(50) | Teléfono |
| `whatsapp` | VARCHAR(50) | Número de WhatsApp (enlace `wa.me` en el directorio público) |
| `photo_id` | BIGINT UNSIGNED | ID de adjunto (Media Library) con la foto del representante, `0` = sin foto (agregado en v1.2) |
| `is_default` | TINYINT(1) | `1` = representante default (fallback para países sin asignación) |
| `visible` | TINYINT(1) | `1` = aparece en el directorio público `[jb3_representantes]` |
| `notify_downloads` | TINYINT(1) | `1` = recibe el aviso por email cuando alguien de su país descarga una Op. Comercial |
| `created_at` | DATETIME | Fecha de creación |

`{prefix}jb3_representative_countries` — un representante puede cubrir varios países (checkboxes en el admin), de ahí la tabla aparte en vez de una columna.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Autoincremental |
| `representative_id` | BIGINT UNSIGNED | FK lógica a `jb3_representatives.id` (sin constraint de DB, se limpia a mano en `JB3_Representatives::delete()`) |
| `country_code` | VARCHAR(2) | Código ISO del país |

Índices: `idx_rep (representative_id)`, `idx_country (country_code)`.

Migración v1.0 → v1.1: `JB3_Database::maybe_upgrade()` corre en cada `plugins_loaded` (compara `jb3_db_version` contra `JB3_Database::DB_VER`); si difieren, copia los valores de la vieja columna `country_code` a la tabla de join y la elimina. Ver `migrate_country_code_column()`.

Migración v1.1 → v1.2: solo agrega la columna `photo_id` — `dbDelta()` la suma sola a la tabla existente, sin función de migración dedicada (no hay datos viejos que mover).

`JB3_Representatives::get_by_country()` sigue devolviendo TODOS los representantes de un país (usado por el email de aprobación de registro); `get_notifiable_by_country()` filtra además por `notify_downloads = 1` (usado por el aviso de descarga de Op. Comerciales).

---

## 17. Cron y mantenimiento automático

### `JB3_Cleanup`

Hook WordPress cron: `jb3_cleanup_pending_users`, frecuencia `daily`.

**Qué hace**: busca usuarios con rol `jb3_member`, `jb3_status = pending` y `jb3_registered_at < 30 días atrás`. Los elimina con `wp_delete_user()` (que dispara el hook `delete_user` → archiva en Mailchimp vía `handle_user_delete`).

Límite de 200 usuarios por ejecución para evitar timeouts. Si hay más de 200 pendientes viejos, se procesan 200 por día hasta limpiarlos todos.

Al **desactivar** el plugin: `wp_clear_scheduled_hook()` cancela el cron automáticamente.

---

## 18. Comportamiento en instalación nueva

En una instalación nueva (sin datos previos), el plugin funciona correctamente de inmediato:
- Los usuarios que se registran vía `[jb3_register]` reciben solo el rol `jb3_member`
- Los administradores de WordPress NO tienen el rol `jb3_member`
- Las funciones `jb3_is_member()` y `user_can($id, 'manage_options')` son mutuamente excluyentes en una instalación limpia
- El sync masivo separa correctamente miembros (fase WP→MC) de admins (PUT directo al final)

El script de limpieza de roles (que unificó un entorno de desarrollo donde los admins habían recibido accidentalmente el rol `jb3_member`) solo fue necesario una vez para ese entorno específico. No es necesario en instalaciones nuevas.

---

## 19. Notas para desarrolladores

### Hooks disponibles

| Hook | Tipo | Parámetros | Descripción |
|---|---|---|---|
| `jb3_after_profile_save` | action | `$user_id` | Después de guardar el formulario de perfil frontend |
| `jb3_sync_to_mailchimp` | action | `$user_id` | Fuerza sincronización individual del usuario con Mailchimp |

### Disparar sync manualmente

```php
do_action( 'jb3_sync_to_mailchimp', $user_id );
```

Esto llama a `JB3_Mailchimp::sync_user()` directamente — es sincrónico y loguea errores.

### Verificar estado de un usuario

```php
jb3_is_member( $user_id )     // true si tiene rol jb3_member
jb3_is_approved( $user_id )   // true si jb3_status === 'approved'
jb3_get_status( $user_id )    // 'approved' | 'pending' (default: 'pending')
jb3_profile_complete( $user_id ) // true si todos los campos obligatorios están llenos

// Patrón típico para verificar acceso:
if ( jb3_is_member( $user_id ) && jb3_is_approved( $user_id ) ) {
    // usuario miembro activo
}
if ( user_can( $user_id, 'manage_options' ) ) {
    // administrador
}
```

### Limpiar caché de Mailchimp desde código

```php
delete_transient( 'jb3_mc_interests_cache' );
delete_transient( 'jb3_mc_nl_interests_cache' );
// El próximo acceso a get_cached_interests() o get_newsletter_interests() reconstruye el caché
```

### Constantes de `JB3_Magic_Link`

```php
JB3_Magic_Link::TOKEN_VALID_DAYS      // 120 — ventana para clickear el link emailado
JB3_Magic_Link::INACTIVITY_DAYS       // 180 — la sesión se renueva sola con esta frecuencia de uso
JB3_Magic_Link::MAX_SESSION_DAYS      // 365 — techo absoluto desde el login real
JB3_Magic_Link::REFRESH_THRESHOLD     // DAY_IN_SECONDS — throttle de la renovación
JB3_Magic_Link::TOKEN_META            // 'jb3_magic_token'
JB3_Magic_Link::EXPIRY_META           // 'jb3_magic_token_expiry'
JB3_Magic_Link::SESSION_META          // 'jb3_session_expiry'
JB3_Magic_Link::SESSION_STARTED_META  // 'jb3_session_started'
```

### Helpers globales (`includes/helpers.php`)

```php
jb3_countries()    // array [ 'AR' => 'Argentina', ... ]
jb3_job_titles()   // array de strings
jb3_sectors()      // array de strings
jb3_interests()    // alias de JB3_Mailchimp::get_cached_interests()
jb3_sanitize_phone( $phone )  // permite dígitos, +, -, espacios, paréntesis
jb3_sanitize_and_validate_profile_fields()  // [$data, $errors] — campos de perfil compartidos entre [jb3_register] y [jb3_profile] (nombre, apellido, empresa, cargo, teléfono, país, sector, newsletter, intereses); el email NO está acá, solo se pide en el registro
```

### Por qué el borrado masivo (Todos/Pendientes/Aprobados) no archiva en Mailchimp al momento

`ajax_delete_chunk()` borra usuarios con `DELETE` SQL directo sobre `wp_users`/`wp_usermeta`, no con `wp_delete_user()`. El hook `delete_user` (al que está enganchado `JB3_Mailchimp::handle_user_delete`) solo lo dispara `wp_delete_user()`, así que con SQL directo simplemente nunca se ejecuta — no hace falta `remove_action()`. Es una decisión de performance: evita 50+ llamadas HTTP fire-and-forget por chunk cuando el volumen es de miles de usuarios. Para archivar en Mailchimp esos usuarios borrados, correr la sincronización masiva después.

El modo **Lista CSV** de la misma página (`jb3_delete_list_upload` + filtro `list` en `ajax_delete_chunk()`) es la excepción: ahí sí se llama `JB3_Mailchimp::archive_contact()` explícitamente por cada usuario borrado, porque el volumen esperado de una lista puntual es acotado y el archivado inmediato es el objetivo de ese flujo.

---

## 20. Debug y logs

Para habilitar el log de errores de WordPress:

```php
// En wp-config.php, antes de "That's all, stop editing!"
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // No mostrar errores en pantalla
```

El log se guarda en:
```
/wp-content/debug.log
```

El plugin escribe en el log bajo el prefijo `[JB3 Mailchimp]` para errores de API, y `[JB3 Webhook]` para eventos del webhook:

```
[JB3 Mailchimp] Error sincronizando usuario 42: Connection timeout
[JB3 Mailchimp] API error 400 usuario 1: {"detail":"..."}
[JB3 Webhook] POST recibido. type=unsubscribe email=usuario@ejemplo.com
[JB3 Webhook] Secret inválido.
```

Causas comunes de errores en el log:

| Error | Causa más probable |
|---|---|
| `API error 400` | Merge field no configurado en Mailchimp, o email bloqueado por anti-spam |
| `API error 401` | API Key incorrecta o expirada |
| `API error 403` | API Key sin permisos para esa audiencia |
| `API error 404` | Audience ID incorrecto |
| `Connection timeout` | Mailchimp no accesible desde el servidor (firewall, DNS) |
| `has signed up to a lot of lists` | Email bloqueado temporalmente por Mailchimp (24-48h) por muchos signups recientes |

---

## 21. Contenido privado

`JB3_Content_Privacy` agrega un checkbox **"Contenido privado"** (meta `_jb3_privado`) disponible en **cualquier tipo de contenido público** — se registra dinámicamente contra `get_post_types(['public' => true])`, sin lista fija, así un CPT nuevo del tema lo tiene disponible solo con existir.

A propósito **no** usa el estado nativo "Privada" de WordPress ni toca `post_status` — el post sigue "Publicado" como siempre, para no romper ninguna consulta existente del sitio (`[jb3_modulo]`, grillas, geo-targeting, etc.). La restricción vive aparte, en un guard de `template_redirect` (mismo criterio que `jb3-geo-targeting`).

**Quién puede ver el contenido igual**:
- El autor/editor del post, o un admin (`current_user_can('edit_post', $post_id)`)
- Un usuario ya logueado que sea `jb3_member` o admin

**Visitante sin sesión ni permiso**: en vez del contenido, ve una pantalla con botones "Ingresar"/"Registrarte" (`render_bloqueado()`). Si llegó con un link de campaña de Mailchimp válido (`?e=`, ver sección 23), en cambio recibe un magic link directo a su email (`render_magic_link_enviado()`) — no el formulario genérico.

---

## 22. Tracking de Op. Comerciales y Google Sheets

`JB3_Op_Comerciales_Tracking` registra en Google Sheets (vía `JB3_Google_Sheets`, un cliente mínimo de la API v4 sin el SDK oficial) quién ve y quién descarga cada Op. Comercial, en dos hojas separadas: **"oportunidades vistas"** y **"oportunidades descargadas"**.

- **Vistas**: automático, en `template_redirect` — no requiere cambios en el tema.
- **Descargas**: el botón "Descargar PDF" del tema tiene que apuntar a `JB3_Op_Comerciales_Tracking::download_url($post_id)` en vez del PDF directo — un `<a href="pdf.pdf" download">` nunca pasa por PHP, así que sin este link intermedio no hay forma de loguear el click.
- Al registrar una descarga, avisa por email a los representantes del país del **usuario que descarga** (no del contenido) — `notify_downloads = 1` en `jb3_representatives`.
- **"Posible bot"**: heurística best-effort contra `JB3_BOT_UA_SIGNATURES` (helpers.php) — marca Sí/No en la planilla para poder filtrar a mano escáneres de seguridad de correo (Microsoft Safe Links, Mimecast, etc.) que abren links automáticamente y generan vistas/descargas falsas.

**Credenciales de Google Sheets**: cuenta de servicio, JWT bearer, archivo `jb3-sc-stats.php` fuera de `wp-content` (nunca se commitea) — mismo criterio que las credenciales de reCAPTCHA. El `ID de la planilla` se configura en **JB3 Members → Configuración** (`jb3_gsheets_id`, ver sección 6).

---

## 23. Entrada automática por link de Mailchimp (`?e=`)

Los links de campaña de Mailchimp pueden llevar el `unique_email_id` del contacto como parámetro `e`. `JB3_Mailchimp_Entry::verificar()` lo resuelve contra la API de Mailchimp (no hay forma de leer el email desde ese ID sin la API) para saber a quién mandarle un magic link — **únicamente** cuando el visitante **no tiene sesión activa**.

Alcance a propósito acotado: si ya hay sesión (sea cual sea, venga o no de un link reenviado de otra persona), `e` se ignora por completo — el acceso lo deciden solo los permisos normales de la página (contenido privado, geo-targeting, etc.), nunca la identidad de quién generó el link.

Hoy el único consumidor es el guard de **contenido privado** (sección 21): un visitante sin sesión que llega con `?e=` válido a una página privada recibe el magic link directo, en vez del formulario genérico de Ingresar/Registrarte. Cachea resultados (`CACHE_TTL_HIT` = 1 día, `CACHE_TTL_MISS` = 1 hora) para no pegarle a la API de Mailchimp en cada request.

---

## 24. Verificación en dos pasos (administradores)

**Archivo**: `includes/class-two-factor.php` (clase `JB3_Two_Factor`). Agregado el 2026-10-20.

Los **administradores** (`manage_options`), y solo ellos, además del magic link tienen que ingresar el código de 6 dígitos de una app de autenticación: Google Authenticator, Microsoft Authenticator, Authy, 1Password… (estándar **TOTP**, RFC 6238: HMAC-SHA1, 30 segundos, 6 dígitos). Los miembros no ven ningún cambio.

**Por qué propio y no un plugin** (tipo "WP 2FA" o "Two Factor"): esos plugins se enganchan al login con contraseña de `wp-login.php`, y acá los admins entran por magic link (`JB3_Magic_Link`), que esos plugins nunca ven. El TOTP está implementado sin dependencias y validado con los 6 vectores de prueba del RFC 6238.

### Flujo

1. El admin pide su magic link en `/login/` y lo abre (o, con la llave `JB3_PERMITIR_LOGIN_CONTRASENA`, ingresa con contraseña — también pasa por acá, `interceptar_login_contrasena()`).
2. `JB3_Two_Factor::iniciar()` **no inicia la sesión**: guarda un *login pendiente* (transient `jb3_2fa_p_{hash}`, 10 minutos, con usuario, destino e intentos), referenciado por la cookie HttpOnly `jb3_2fa_pendiente` (no viaja en la URL), y redirige a `/login/?jb3_2fa=1`.
3. El shortcode `[jb3_login]` muestra `render_paso()`:
   - **sin app configurada** (primera vez, o después de un reinicio): QR para escanear + la clave para cargar a mano + campo del código. El QR se arma **en el navegador** con `public/js/qrcode.min.js` (copia local): la clave nunca pasa por un servicio externo. La clave nueva vive en el login pendiente y se guarda en el usuario recién cuando confirma un código válido;
   - **con app configurada**: solo el campo del código, y una línea de ayuda para quien cambió de celular.
4. `procesar()` (hook `init` prioridad 6, antes de cualquier salida) valida el código y, si es correcto, llama a `JB3_Magic_Link::completar_login()` con `$GLOBALS['jb3_2fa_verificado'] = true`.
5. `marcar_sesion()` (filtro `attach_session_information`) guarda `'jb3_2fa' => true` en la sesión que se crea. `exigir_en_sesiones()` (`init` prioridad 1) revisa en **cada pedido** que la sesión de un admin tenga esa marca; si no la tiene, la cierra (página → `/login/?jb3_error=2fa_requerido`; AJAX/API → 401). Cubre las sesiones abiertas antes de activar esto y cualquier otro camino de login.
6. La renovación de la sesión deslizante (`JB3_Magic_Link::maybe_refresh_session()`) crea una sesión nueva: si la actual estaba verificada, la nueva hereda la marca (si no, el admin quedaría afuera en el pedido siguiente).

### Protecciones

| Qué | Cómo |
|---|---|
| Adivinar el código | 5 intentos por login pendiente (`MAX_INTENTOS`); después hay que pedir un magic link nuevo (`?jb3_error=2fa_bloqueado`) |
| Reusar un código ya usado | Se guarda el último paso de tiempo aceptado (`jb3_2fa_ultimo`); solo valen pasos posteriores. Ventana: ±30 segundos |
| Robo de la base de datos | La clave se guarda cifrada (AES-256-GCM) con una llave derivada de `wp_salt('auth')` (las SALT de `wp-config.php`) |
| Caché de página | La pantalla `?jb3_2fa=1` se marca `no-cache` (LiteSpeed y navegador): depende de una cookie propia |
| Sesión vieja de admin | Se cierra sola si no está verificada (paso 5) |

**OJO — SALT de `wp-config.php`**: si alguna vez se cambian (`AUTH_KEY`, `AUTH_SALT`…), las claves guardadas dejan de poder descifrarse y **todos los admins tienen que volver a configurar la app** (reinicio, abajo).

### Recuperación (celular robado/perdido, o código borrado de la app)

**No hay códigos de respaldo**, a propósito (decisión 2026-10-20: "nadie los guarda y los pierden"). **Tampoco hay recuperación por email**: el primer factor ya ES el email (magic link), así que "perdí el celular, mandame un link" dejaría a quien robe el email entrar con un solo factor.

Siempre se **reinicia** la verificación de ese admin (`reiniciar()`: borra `jb3_2fa_secreto` y `jb3_2fa_ultimo`) y en su próximo ingreso configura la app de cero con un QR nuevo. Tres formas, todas cierran las sesiones abiertas de ese admin (ej. la del celular robado):

| Situación | Cómo | Dónde en el código |
|---|---|---|
| Hay otro administrador | **Usuarios → (el admin) → "Reiniciar verificación en dos pasos"** | `render_perfil()` + `procesar_reinicio()` |
| Un solo admin, sin acceso a la app | En `wp-config.php`: `define( 'JB3_REINICIAR_2FA', 'su@email.com' );` — se aplica al ingresar ese admin, **una vez por cada valor** (queda anotado en `jb3_2fa_reinicio`, así que si la línea queda olvidada no reinicia en cada ingreso). Después, sacar la línea. Requiere acceso al hosting: es un factor distinto del email | `aplicar_reinicio_wpconfig()` |
| Cambio de celular con el viejo a mano | El propio admin, logueado: **Perfil → "Volver a configurar (celular nuevo)"** (cierra sus otras sesiones, la actual sigue) | `render_perfil()` + `procesar_reinicio()` |

Alternativa sin tocar `wp-config.php` (también desde el hosting, phpMyAdmin): `DELETE FROM wp_usermeta WHERE meta_key IN ('jb3_2fa_secreto','jb3_2fa_ultimo') AND user_id = <ID>;`

**Prevención recomendada**: en Google Authenticator, iniciar sesión con la cuenta de Google (ícono de la nube) — los códigos quedan respaldados y un celular nuevo los recupera solo (no sirve si el código se borró a mano: la nube también lo borra). La pantalla de configuración lo recomienda.

**Apagado total** (solo si esta función misma fallara — apaga la verificación para TODOS los admins): `define( 'JB3_DESACTIVAR_2FA', true );` en `wp-config.php`, y sacarlo apenas se resuelva.

### Qué tocar para cambiar algo

| Cambio | Dónde |
|---|---|
| Quiénes la necesitan (hoy: `manage_options`) | `JB3_Two_Factor::requerido()` |
| Intentos permitidos / vida del login pendiente | constantes `MAX_INTENTOS` / `PENDING_TTL` |
| Nombre que muestra la app ("Conectados") | `uri_otpauth()`, variable `$emisor` |
| Textos de la pantalla del código | `render_paso()` |
| Mensajes de error en `/login/` | `JB3_Login::shortcode()` (`2fa_bloqueado`, `2fa_requerido`) |
| Estilos (tamaño del QR, etc.) | `public/css/jb3-forms.css`, sección "Verificación en dos pasos" |
| Cómo se reconoce una sesión verificada | `marcar_sesion()` / `sesion_actual_verificada()` (clave `'jb3_2fa'` en la sesión de WordPress) |

### Cómo se probó (2026-10-20, local)

Código contra los 6 vectores del RFC 6238; cifrado ida y vuelta y rechazo de datos alterados; de punta a punta con Playwright: primer ingreso con QR, código incorrecto/correcto, reuso rechazado, bloqueo a los 5 intentos, sesión vieja de admin cerrada (página y AJAX → 401), "Volver a configurar" propio (cierra las otras sesiones), reinicio hecho por otro admin (cierra las sesiones del reiniciado y pide QR nuevo), reinicio por `JB3_REINICIAR_2FA` (una sola vez por valor, no toca a otros usuarios) y miembro que entra sin código.
