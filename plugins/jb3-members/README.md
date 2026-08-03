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

---

## 1. ¿Qué hace este plugin?

JB3 Members implementa un sistema completo de membresías para WordPress:

- **Registro con aprobación manual**: los usuarios se registran y quedan en estado *pendiente* hasta que un representante los aprueba. La aprobación se hace con un clic desde el email que llega al representante.
- **Login por magic link (sin contraseñas)**: el usuario ingresa su email, recibe un enlace de un solo uso y queda logueado por 120 días.
- **Representantes por país**: cada país tiene uno o más representantes que reciben el email de aprobación. Si no hay representante asignado para un país, recibe el default.
- **Sincronización bidireccional con Mailchimp**: datos del perfil, preferencias de newsletter, intereses y newsletters por país se sincronizan automáticamente.
- **Administradores incluidos**: los usuarios administradores de WordPress pueden loguearse por magic link, tener sus datos en Mailchimp y gestionar todo el sistema.
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
│   ├── class-registration.php        # Shortcode [jb3_register], aprobación por token
│   ├── class-profile-update.php      # Shortcode [jb3_profile], eliminación de cuenta
│   ├── class-login.php               # Shortcode [jb3_login], logout, rate limiting
│   ├── class-auth-guard.php          # Guard de sesión y redirección por perfil incompleto
│   ├── class-cleanup.php             # Cron diario de limpieza de pendientes
│   ├── class-mailchimp.php           # Sync individual, webhook, caché de intereses
│   └── class-sync.php                # Sync masivo (Batch API) + limpieza MC→WP
├── admin/
│   └── class-admin.php               # Backoffice completo: menú, columnas, import/export, borrado
├── public/
│   ├── views/
│   │   ├── registration-form.php     # Template HTML del formulario de registro
│   │   ├── update-form.php           # Template HTML del formulario de perfil
│   │   └── login-form.php            # Template HTML del formulario de login
│   ├── css/jb3-forms.css             # Estilos de formularios (frontend y admin)
│   └── js/jb3-forms.js               # UX: validación inline, anti-doble-submit
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
JB3_Magic_Link::generate_url() → token 64 chars + expiry 120 días en user_meta
        ↓
JB3_Email::send_magic_link() → email con URL ?jb3_magic=TOKEN
        ↓
Usuario hace clic → JB3_Magic_Link::handle_magic_link() (en hook init, priority 5)
        ↓
validate_token(): busca el token por SQL directo en usermeta → verifica expiry → verifica estado
        ↓
Token consumido: delete_user_meta(TOKEN_META, EXPIRY_META) — un solo uso
        ↓
wp_set_auth_cookie($user_id, remember=true) + SESSION_META guardado server-side
        ↓
Filtro auth_cookie_expiration → cookie extendida a 120 días para jb3_member y admins
        ↓
Redirige al dashboard configurado
```

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
| Perfil obligatorio | Siempre activo. Todos los usuarios (miembros y admins) deben completar el perfil antes de navegar |

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

### `[jb3_logout]`
Cierra la sesión y redirige al inicio. Se usa en una página dedicada.
- El shortcode no renderiza nada; la lógica está en `handle_logout_page()` via `template_redirect`

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
- `SESSION_DAYS = 120` — duración de sesión en días
- `TOKEN_META = 'jb3_magic_token'` — meta key del token activo
- `EXPIRY_META = 'jb3_magic_token_expiry'` — meta key del timestamp de expiración
- `SESSION_META = 'jb3_session_expiry'` — meta key de la sesión server-side

**`generate_token($user_id)`**: crea un token alfanumérico de 64 chars con `wp_generate_password()`. Lo guarda en user_meta junto con su timestamp de expiración (ahora + 120 días). Sobreescribe el token anterior si existía (solo puede haber un magic link activo por usuario).

**`generate_url($user_id)`**: llama a `generate_token()` y construye la URL completa: `{URL_pagina_login}?jb3_magic={TOKEN}`.

**`handle_magic_link()`**: se ejecuta en el hook `init` con priority 5 (antes del template). Si detecta `?jb3_magic=TOKEN` en la URL, llama a `validate_token()`. Si el token es válido, hace login y redirige; si no, redirige al login con `?jb3_error=invalid_link`.

**`validate_token($token)`**: busca el token por SQL directo en `wp_usermeta` (más eficiente que iterar usuarios). Verifica: token existe → no expirado → usuario aprobado (o admin). Retorna el objeto `WP_User` o `false`.

**`extend_cookie_expiration()`**: filtro `auth_cookie_expiration` que extiende la cookie a 120 días cuando `$remember=true` para `jb3_member` y administradores.

**`force_logout($user_id)`**: destruye todas las sesiones WP del usuario + borra los tres metas de token/sesión.

### Clase `JB3_Login`

Maneja el shortcode `[jb3_login]` y el rate limiting. El método `process()` implementa:

1. Sanitiza el email del POST
2. Verifica rate limit: si `jb3_rl_{md5(email)}` ≥ 3, retorna error (esperar 10 min)
3. Incrementa el contador con `set_transient(..., 10 * MINUTE_IN_SECONDS)`
4. Busca el usuario por email
5. Determina `$is_admin = user_can(...)` para decidir si aplican los checks de rol/estado
6. Si pasa todos los checks: llama a `JB3_Email::send_magic_link()`
7. Retorna `[[], true]` (éxito sin errores) — incluso para emails no registrados, para prevenir enumeración

---

## 11. Guardias de sesión y perfil

### Clase `JB3_Auth_Guard`

Dos hooks en `template_redirect` (solo en el frontend):

**`check_session()` (priority 1)**:
- Solo actúa para `jb3_member` y administradores logueados
- Lee `jb3_session_expiry` del user_meta
- Si `$expiry > 0` y `time() > $expiry`: llama a `force_logout()`, desloguea y redirige al login con `?jb3_error=session_expired`
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
4. Si `jb3_newsletter = 1`: construye el body con todos los campos + intereses + intereses de newsletter, y hace PUT directo a `/lists/{list_id}/members/{hash}` con `status_if_new: 'subscribed'` y `status: 'subscribed'`

El PUT directo es **sincrónico** — espera la respuesta de Mailchimp. Los errores (HTTP 4xx/5xx) quedan logueados con `error_log('[JB3 Mailchimp] ...')`.

**`archive_contact($email)`**: hace PATCH `status: unsubscribed` + DELETE sobre el contacto. Ambas llamadas tienen `'blocking' => false` (fire-and-forget) para no bloquear al usuario.

**Intereses en el sync**: los arrays `jb3_interests` y `jb3_newsletter_countries` almacenan IDs de Mailchimp. Para el sync se construye un mapa `[mc_id => true/false]` iterando todos los IDs conocidos del caché.

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

**`build_op(WP_User $user, string $list_id)`**: función unificada para miembros y admins.
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
| `Newsletter` | `Sí` / `No` | |
| `Newsletters` | Nombres separados por coma | Nombres de los grupos Mailchimp |
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

**Newsletter para usuarios existentes**: si el CSV dice `No` → se desuscribe (siempre respetado). Si el CSV dice `Sí` pero el usuario ya tiene newsletter desactivado → **no se cambia**. Esta regla protege la baja voluntaria del usuario.

**Campo País**: acepta tanto código ISO (`AR`) como nombre completo (`Argentina`).

**Campo Intereses**: acepta nombre del interés (`Películas`) o su ID de Mailchimp directamente.

**Campo Newsletters**: acepta nombres exactos de los grupos Mailchimp separados por coma (`Argentina, Bolivia`).

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
| `Newsletter` | `jb3_newsletter` | `Sí`, `Si`, `1`, `Yes` = suscripto |
| `Newsletters` | `jb3_newsletter_countries` | Nombres de países separados por coma |
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
- Se elimina del user_meta tras ser usado
- Intentos de doble aprobación (varios representantes reciben el mismo email) son detectados y manejados con mensaje informativo en lugar de error

### Rate limiting en login
- Máximo **3 solicitudes por email cada 10 minutos** (transient `jb3_rl_{md5(email)}`)
- Sin infraestructura adicional (usa transients de WP)
- El mensaje de error revela que el rate limit fue alcanzado, pero el tiempo de expiración no se muestra al usuario

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
| `jb3_approval_token` | string 48 chars | Token one-time para aprobación (se elimina tras su uso) |
| `jb3_magic_token` | string 64 chars | Token one-time del magic link activo |
| `jb3_magic_token_expiry` | int (Unix timestamp) | Expiración del magic link |
| `jb3_session_expiry` | int (Unix timestamp) | Expiración server-side de la sesión (120 días) |

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

### Tabla de base de datos

`{prefix}jb3_representatives`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Autoincremental |
| `country_code` | VARCHAR(2) | Código ISO del país (vacío para el default) |
| `first_name` | VARCHAR(100) | Nombre |
| `last_name` | VARCHAR(100) | Apellido |
| `email` | VARCHAR(150) | Email |
| `phone` | VARCHAR(50) | Teléfono |
| `is_default` | TINYINT(1) | `1` = representante default (fallback para países sin asignación) |
| `created_at` | DATETIME | Fecha de creación |

Índice: `idx_country (country_code)` para búsquedas por país.

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
JB3_Magic_Link::SESSION_DAYS  // 120
JB3_Magic_Link::TOKEN_META    // 'jb3_magic_token'
JB3_Magic_Link::EXPIRY_META   // 'jb3_magic_token_expiry'
JB3_Magic_Link::SESSION_META  // 'jb3_session_expiry'
```

### Helpers globales (`includes/helpers.php`)

```php
jb3_countries()    // array [ 'AR' => 'Argentina', ... ]
jb3_job_titles()   // array de strings
jb3_sectors()      // array de strings
jb3_interests()    // alias de JB3_Mailchimp::get_cached_interests()
jb3_sanitize_phone( $phone )  // permite dígitos, +, -, espacios, paréntesis
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
