# JB3 Members

Plugin WordPress para registro, aprobación y acceso de miembros con magic link, sincronización bidireccional con Mailchimp y gestión completa desde el panel de administración.

---

## Índice

1. [¿Qué hace este plugin?](#1-qué-hace-este-plugin)
2. [Requisitos](#2-requisitos)
3. [Estructura de archivos](#3-estructura-de-archivos)
4. [Flujo completo del usuario](#4-flujo-completo-del-usuario)
5. [Instalación y configuración inicial](#5-instalación-y-configuración-inicial)
6. [Requisitos en Mailchimp](#6-requisitos-en-mailchimp)
7. [Shortcodes](#7-shortcodes)
8. [Panel de administración](#8-panel-de-administración)
9. [Sincronización con Mailchimp](#9-sincronización-con-mailchimp)
10. [Exportar e Importar usuarios (CSV)](#10-exportar-e-importar-usuarios-csv)
11. [Seguridad](#11-seguridad)
12. [Referencia de datos de usuario](#12-referencia-de-datos-de-usuario)
13. [Cron y mantenimiento automático](#13-cron-y-mantenimiento-automático)

---

## 1. ¿Qué hace este plugin?

JB3 Members implementa un sistema completo de membresías para WordPress:

- **Registro con aprobación**: los usuarios se registran y quedan en estado *pendiente* hasta que un representante los aprueba manualmente.
- **Login por magic link**: no hay contraseñas. El usuario ingresa su email, recibe un enlace de un solo uso y queda logueado por 120 días.
- **Representantes por país**: cada país tiene uno o más representantes que reciben el email de aprobación y pueden aprobar al nuevo miembro con un clic.
- **Sincronización bidireccional con Mailchimp**: los datos del perfil, las preferencias de newsletter, los intereses y los newsletters por país se sincronizan automáticamente en ambas direcciones.
- **Exportación e importación por CSV**: gestión masiva de usuarios con lógica inteligente de actualización.
- **Limpieza automática**: los usuarios pendientes de más de 30 días se eliminan automáticamente por cron.

---

## 2. Requisitos

### WordPress
- WordPress 6.0 o superior
- PHP 7.4 o superior

### Mailchimp
- Una cuenta Mailchimp con al menos una audiencia creada
- API Key de Mailchimp
- Audience ID de la audiencia
- Campos de merge configurados (ver [sección 6](#6-requisitos-en-mailchimp))
- Dos categorías de intereses/grupos configuradas (ver [sección 6](#6-requisitos-en-mailchimp))

---

## 3. Estructura de archivos

```
jb3-members/
├── jb3-members.php                  # Entrada del plugin
├── includes/
│   ├── helpers.php                  # Listas de países, cargos, sectores, intereses
│   ├── class-database.php           # Instalación de tabla y rol WP
│   ├── class-representatives.php    # CRUD de representantes por país
│   ├── class-email.php              # Envío de emails transaccionales
│   ├── class-magic-link.php         # Generación y validación de magic links
│   ├── class-registration.php       # Shortcode [jb3_register] y aprobación
│   ├── class-profile-update.php     # Shortcode [jb3_profile] y eliminación de cuenta
│   ├── class-login.php              # Shortcode [jb3_login] y logout
│   ├── class-auth-guard.php         # Redirección de perfiles incompletos y sesiones
│   ├── class-cleanup.php            # Cron diario de limpieza
│   └── class-mailchimp.php          # Sincronización con API de Mailchimp
├── admin/
│   └── class-admin.php              # Todo el backoffice: menú, usuarios, import/export
├── public/
│   ├── views/
│   │   ├── registration-form.php    # Template del formulario de registro
│   │   ├── update-form.php          # Template del formulario de perfil
│   │   └── login-form.php           # Template del formulario de login
│   ├── css/jb3-forms.css            # Estilos de formularios frontend
│   └── js/jb3-forms.js              # UX: validación, anti-doble-submit
└── templates/
    ├── email-approval-request.php   # Email a representantes: nuevo registro
    ├── email-user-approved.php      # Email al usuario: aprobado + magic link
    └── email-magic-link.php         # Email al usuario: enlace de acceso
```

---

## 4. Flujo completo del usuario

### Registro y aprobación

```
Usuario llena [jb3_register]
        ↓
Se crea cuenta con rol jb3_member y estado "pending"
        ↓
Se envía email a los representantes del país del usuario
        ↓
Representante hace clic en "Aprobar usuario" del email
        ↓
Estado cambia a "approved" + se elimina el token de aprobación
        ↓
Se envía email al usuario con su magic link de bienvenida
        ↓
Se sincroniza el usuario a Mailchimp (si tiene newsletter = Sí)
```

### Login posterior

```
Usuario ingresa email en [jb3_login]
        ↓
Rate limit: máx. 3 solicitudes por email cada 10 minutos
        ↓
Se genera un token de 64 caracteres (one-time use)
        ↓
Se envía email con el magic link
        ↓
Usuario hace clic → token validado → sesión de 120 días
        ↓
Redirige al dashboard configurado
```

### Gestión de perfil incompleto

Si un usuario aprobado intenta navegar el sitio con campos obligatorios faltantes (nombre, apellido, empresa, cargo, teléfono, país, sector), el Auth Guard lo redirige automáticamente a la página de perfil con un aviso para completar sus datos.

---

## 5. Instalación y configuración inicial

### Instalación

1. Subir la carpeta `jb3-members` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. La activación crea automáticamente:
   - Tabla `{prefix}jb3_representatives` en la base de datos
   - Rol WordPress `jb3_member`
   - Un representante default (configurable)
   - El cron diario de limpieza

### Configuración obligatoria

Ir a **JB3 Members → Configuración** y asignar:

| Campo | Descripción |
|---|---|
| Página de Login | Página que contiene el shortcode `[jb3_login]` |
| Página de Perfil | Página que contiene el shortcode `[jb3_profile]` |
| Página de Dashboard | Página a la que se redirige tras el login |
| API Key de Mailchimp | Se obtiene en Mailchimp → Account → Extras → API Keys |
| Audience ID | Se obtiene en Mailchimp → Audience → Settings → Audience name and defaults |

### Configuración de Mailchimp

Después de guardar la API Key y el Audience ID, hacer clic en **"Actualizar grupos desde Mailchimp"**. Esto:
- Detecta automáticamente la categoría de newsletters por país (cualquier categoría cuyo nombre contenga la palabra "newsletter")
- Carga los grupos de intereses generales
- Limpia y reconstruye el caché de intereses

### Representantes por país

Ir a **JB3 Members → Representantes** para gestionar quién recibe los emails de aprobación de cada país. El representante *default* recibe las solicitudes de países sin representante asignado.

---

## 6. Requisitos en Mailchimp

### Campos de merge (Audience fields)

Ir a **Audience → Settings → Audience fields and \*|MERGE|\* tags** y crear o verificar los siguientes campos:

| Merge Tag | Tipo sugerido | Descripción |
|---|---|---|
| `FNAME` | Text (nativo) | Nombre |
| `LNAME` | Text (nativo) | Apellido |
| `PHONE` | Phone | Teléfono |
| `COMPANY` | Text | Empresa |
| `COUNTRY` | Dropdown o Text | País (código ISO: AR, CO, PE…) |
| `MMERGE5` | Dropdown o Text | Sector / Industria |
| `MMERGE6` | Dropdown o Text | Cargo |
| `ESTADO` | Text | Estado del miembro (Aprobado / Pendiente) |

**Importante**: si se usan campos de tipo **Dropdown**, los valores deben coincidir exactamente con los que usa el plugin (ver listas abajo). Una alternativa más flexible es configurarlos como **Text**, lo que acepta cualquier valor.

#### Valores para COUNTRY (códigos ISO)

```
AR  BO  BR  CL  CO  CR  DO  EC  GT  HN
MX  NI  PA  PE  PY  SV  US  UY  VE
```

#### Valores para MMERGE6 (Cargos)

```
CEO (Chief Executive Officer)
Director
Gerente
Supervisor
Coordinador de Proyectos
Asesor
Administrador
Analista Senior
Especialista
Analista
Técnico
Operador
Encargado
Representante
Inspector
Asistente
Auxiliar
Empleado
Otro
```

#### Valores para MMERGE5 (Sectores)

```
Servicios Profesionales
Comercio Minorista
Tecnología de la Información (TI)
Alimentación y Bebidas
Salud y Bienestar
Construcción y Arquitectura
Manufactura
Educación
Medios y Entretenimiento
Transporte y Logística
Servicios Financieros
Agricultura
Turismo y Hospitalidad
Bienes Raíces
Consultoría de Negocios
Servicios de Ingeniería
Servicios Legales
Servicios de Marketing y Publicidad
Servicios de Recursos Humanos
Comercio Electrónico
Otro
```

### Grupos de interés (Interest Categories)

El plugin espera dos categorías de grupos en la audiencia:

**Categoría 1 — Temas de interés**  
Nombre libre (cualquiera que NO contenga la palabra "newsletter").  
Opciones recomendadas: `Películas`, `Series`, `Producciones locales`, `Oportunidades de negocio`

**Categoría 2 — Newsletters por País**  
El nombre de esta categoría **debe contener la palabra "newsletter"** (sin distinguir mayúsculas/minúsculas). El plugin la detecta automáticamente por el nombre.  
Opciones (nombres de países en español):
```
Argentina, Bolivia, Brasil, Chile, Colombia, Costa Rica, Ecuador,
El Salvador, Guatemala, Honduras, México, Nicaragua, Panamá, Paraguay,
Perú, República Dominicana, United States, Uruguay, Venezuela
```

### Webhook Mailchimp → WordPress

Para sincronizar cambios de Mailchimp hacia WordPress:

1. Ir a **Audience → Settings → Webhooks → Create New Webhook**
2. **URL**: copiar desde JB3 Members → Configuración (campo "URL del Webhook")
3. **Activar eventos**: Subscribes, Unsubscribes, Profile Updates
4. Si WordPress no es públicamente accesible (local, detrás de firewall), usar Cloudflare Tunnel, ngrok o similar para exponer la URL

---

## 7. Shortcodes

### `[jb3_register]`
Formulario de registro. Redirige al dashboard si el usuario ya está logueado.

Campos requeridos: Nombre, Apellido, Email, País, Teléfono, Empresa, Sector, Cargo.  
Campos opcionales: Temas de interés (dinámico desde Mailchimp), Suscripción al newsletter.

### `[jb3_login]`
Formulario de solicitud de magic link. Redirige al dashboard si el usuario ya está logueado.

### `[jb3_profile]`
Formulario de edición del perfil. Solo visible para `jb3_member` y administradores. Usuarios no logueados son redirigidos al login.

Campos editables: todos los del registro excepto email.

### `[jb3_logout]`
Cierra la sesión y redirige al inicio. Puede usarse en cualquier página.

---

## 8. Panel de administración

### JB3 Members → Representantes
CRUD de representantes por país. El representante *default* (`is_default = 1`) recibe solicitudes de países sin representante asignado y no puede eliminarse.

### Lista de usuarios (WP → Usuarios)
Columnas adicionales: Estado JB3, País, Empresa. Acciones por usuario: Aprobar, Forzar logout.

### Perfil de usuario (`wp-admin/user-edit.php`)
Sección **Datos JB3 Members** con:
- Estado (Pendiente / Aprobado)
- Todos los campos del perfil
- **Newsletters por País**: checkboxes de países — solo el admin puede modificar esto, no es visible para el usuario en el frontend
- Intereses generales
- Fechas de registro y aprobación

Los administradores de WordPress también ven esta sección. Al guardar por primera vez se les añade el rol `jb3_member` con estado `approved`.

### JB3 Members → Configuración
- Páginas (Login, Perfil, Dashboard)
- Credenciales Mailchimp
- Botón "Actualizar grupos desde Mailchimp"

---

## 9. Sincronización con Mailchimp

### WordPress → Mailchimp

Se dispara automáticamente cuando:
- Un usuario es aprobado
- Un usuario guarda su perfil (frontend o admin)
- Se importa un CSV

**Solo se sincronizan usuarios con estado `approved`.** Los usuarios `pending` no se envían a Mailchimp.

**Lógica de newsletter:**

| Preferencia en WP | Estado en Mailchimp |
|---|---|
| `jb3_newsletter = 1` | Contacto `subscribed` (se desarchiva si estaba archivado) |
| `jb3_newsletter = 0` | Contacto `unsubscribed` + **archivado** (sale de la audiencia activa) |

Archivar ≠ eliminar. Los datos se conservan y el contacto puede volver a la audiencia si se reactiva su newsletter.

**Newsletters por País**: los intereses de la categoría "Newsletters" se sincronizan según los países asignados por el admin al usuario.

### Mailchimp → WordPress (webhook)

| Evento | Acción en WP |
|---|---|
| `subscribe` | `jb3_newsletter = 1` |
| `unsubscribe` | `jb3_newsletter = 0` + archiva contacto en Mailchimp |
| `cleaned` (hard bounce) | `jb3_newsletter = 0` + archiva contacto en Mailchimp |
| `profile` (edición en MC) | Actualiza: nombre, apellido, teléfono, empresa, sector, cargo, país |

Los cambios del webhook **no** disparan una re-sincronización hacia Mailchimp (para evitar bucles).

### Caché

Los intereses de Mailchimp se cachean en transients de WordPress (TTL: 1 hora):
- `jb3_mc_interests_cache` — intereses generales
- `jb3_mc_nl_interests_cache` — intereses de newsletters por país

Para forzar una actualización: **Configuración → Actualizar grupos desde Mailchimp**.

---

## 10. Exportar e Importar usuarios (CSV)

### Exportar

**JB3 Members → Exportar usuarios → Descargar CSV**

El archivo incluye todos los campos del perfil de cada miembro. Los administradores (`manage_options`) quedan excluidos automáticamente.

#### Columnas del CSV exportado

| Columna | Formato | Ejemplo |
|---|---|---|
| `ID` | Número entero | `42` |
| `Nombre` | Texto | `María` |
| `Apellido` | Texto | `García` |
| `Email` | Email | `maria@empresa.com` |
| `Estado` | `Aprobado` / `Pendiente` | `Aprobado` |
| `País` | Código ISO de 2 letras | `AR` |
| `Empresa` | Texto | `Empresa S.A.` |
| `Cargo` | Texto | `Gerente` |
| `Teléfono` | Texto | `+54 11 1234-5678` |
| `Sector` | Texto | `Medios y Entretenimiento` |
| `Newsletter` | `Sí` / `No` | `Sí` |
| `Newsletters` | Nombres separados por coma | `Argentina, Bolivia` |
| `Intereses` | Nombres separados por coma | `Películas, Series` |
| `Fecha Registro WP` | `Y-m-d H:i:s` | `2024-03-15 10:30:00` |
| `Fecha Registro JB3` | `Y-m-d H:i:s` | `2024-03-15 10:30:00` |
| `Fecha Aprobación` | `Y-m-d H:i:s` | `2024-03-16 09:00:00` |

---

### Importar

**JB3 Members → Importar usuarios**

Sube un CSV para crear o actualizar miembros en masa. La única columna obligatoria es `Email`.

#### Lógica de búsqueda del usuario

1. Si el CSV tiene columna `ID`, busca por ID de WordPress primero
2. Si no encuentra por ID (o no hay columna), busca por email
3. **Si lo encuentra** → actualiza los campos presentes en el CSV
4. **Si no lo encuentra** → crea un nuevo usuario con estado `approved` (auto-aprobado)

#### Reglas especiales de importación

**Administradores**: las filas con email de un admin se saltean con un mensaje de error. Los admins no pueden modificarse desde el CSV.

**Campo Newsletter para usuarios existentes**:
- CSV dice `No` → se desuscribe (siempre se respeta)
- CSV dice `Sí` pero el usuario tiene newsletter desactivado → **no se cambia nada**

Esta regla protege la baja voluntaria del usuario. Si alguien se desuscribió desde un email de Mailchimp o desde su perfil, un CSV con datos viejos no puede re-suscribirlo automáticamente.

**Campo País**: acepta tanto el código ISO (`AR`) como el nombre completo (`Argentina`).

**Campo Intereses**: acepta el nombre del interés (`Películas`) o su ID de Mailchimp directamente.

**Campo Newsletters**: acepta los nombres exactos de los grupos de Mailchimp separados por coma (`Argentina, Bolivia, Perú`).

**display_name**: se actualiza automáticamente cuando el CSV contiene nombre y/o apellido.

**Fechas**: si no se incluyen, se usan las fechas actuales para usuarios nuevos.

#### Columnas reconocidas en el import

| Columna CSV | Campo WP/meta | Notas |
|---|---|---|
| `Email` | `user_email` | **Obligatorio** |
| `Nombre` | `first_name` | |
| `Apellido` | `last_name` | |
| `Estado` | `jb3_status` | `Aprobado` / `Pendiente` o `approved` / `pending` |
| `País` | `jb3_country` | Código ISO o nombre completo |
| `Empresa` | `jb3_company` | |
| `Cargo` | `jb3_job_title` | |
| `Teléfono` | `jb3_phone` | |
| `Sector` | `jb3_sector` | |
| `Newsletter` | `jb3_newsletter` | `Sí`, `Si`, `1`, `Yes` = suscripto |
| `Newsletters` | `jb3_newsletter_countries` | Nombres de países separados por coma |
| `Intereses` | `jb3_interests` | Nombres separados por coma |
| `Fecha Registro JB3` | `jb3_registered_at` | `Y-m-d H:i:s` |
| `Fecha Aprobación` | `jb3_approved_at` | `Y-m-d H:i:s` |

#### Sincronización post-importación

Al completar cada fila, el plugin dispara automáticamente la sincronización con Mailchimp siguiendo las mismas reglas de la sección 9.

---

## 11. Seguridad

### Formularios frontend

- Todos usan **nonces de WordPress** para prevenir CSRF
- Todos los inputs se sanitizan antes de guardarse (`sanitize_text_field`, `sanitize_email`, `absint`, etc.)
- Los outputs en templates usan `esc_html()`, `esc_attr()`, `esc_url()` para prevenir XSS

### Magic links

- Tokens de **64 caracteres aleatorios** generados con `wp_generate_password()`
- **Un solo uso**: se eliminan inmediatamente al ser consumidos
- TTL de 120 días verificado server-side
- Solo funcionan para usuarios con estado `approved`

### Token de aprobación

- 48 caracteres aleatorios
- Comparado con `hash_equals()` para prevenir timing attacks
- Se elimina del meta del usuario después de ser usado
- Intentos de doble aprobación son detectados y rechazados con mensaje informativo

### Rate limiting en login

- Máximo **3 solicitudes de magic link por email cada 10 minutos**
- Implementado con transients de WordPress (sin infraestructura adicional)
- Previene el spam de emails hacia usuarios del sistema

### Webhook de Mailchimp

- La URL incluye un **secret de 32 caracteres** único por instalación, generado automáticamente
- Requests sin el secret correcto → HTTP 403

### Panel de administración

- Todas las acciones verifican `current_user_can('manage_options')`
- Todos los formularios del admin usan nonces
- Los emails de admins no pueden modificarse desde el import CSV

### Protección de archivos PHP

Todos los archivos PHP incluyen como primera línea:
```php
defined( 'ABSPATH' ) || exit;
```

---

## 12. Referencia de datos de usuario

### User meta

| Meta key | Tipo | Descripción |
|---|---|---|
| `jb3_status` | `pending` / `approved` | Estado de aprobación |
| `jb3_company` | string | Empresa |
| `jb3_job_title` | string | Cargo |
| `jb3_phone` | string | Teléfono |
| `jb3_country` | string (ISO 2 letras) | País |
| `jb3_sector` | string | Sector / Industria |
| `jb3_newsletter` | `0` / `1` | Suscripción al newsletter |
| `jb3_newsletter_countries` | array | IDs de intereses Mailchimp (países de newsletter) |
| `jb3_interests` | array | IDs de intereses Mailchimp (temas generales) |
| `jb3_registered_at` | datetime | Fecha de registro en JB3 |
| `jb3_approved_at` | datetime | Fecha de aprobación |
| `jb3_approval_token` | string | Token one-time para aprobación (se elimina tras su uso) |
| `jb3_magic_token` | string | Token one-time del magic link activo |
| `jb3_magic_token_expiry` | int (timestamp) | Expiración del magic link |
| `jb3_session_expiry` | int (timestamp) | Expiración de la sesión server-side |

### WP Options

| Option key | Descripción |
|---|---|
| `jb3_login_page_id` | ID de la página de login |
| `jb3_profile_page_id` | ID de la página de perfil |
| `jb3_dashboard_page_id` | ID de la página de dashboard |
| `jb3_mc_api_key` | API Key de Mailchimp |
| `jb3_mc_list_id` | Audience ID de Mailchimp |
| `jb3_mc_webhook_secret` | Secret del webhook (generado automáticamente) |
| `jb3_db_version` | Versión del esquema de base de datos |

### Rol WordPress

El plugin crea el rol `jb3_member` con el único permiso `read`. Se asigna a todos los usuarios registrados. Los admins reciben este rol adicionalmente al guardar sus datos JB3 por primera vez.

### Tabla de base de datos

`{prefix}jb3_representatives`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK autoincremental |
| `country_code` | VARCHAR(2) | Código ISO del país |
| `first_name` | VARCHAR(100) | Nombre |
| `last_name` | VARCHAR(100) | Apellido |
| `email` | VARCHAR(150) | Email |
| `phone` | VARCHAR(50) | Teléfono |
| `is_default` | TINYINT(1) | 1 = representante default |
| `created_at` | DATETIME | Fecha de creación |

---

## 13. Cron y mantenimiento automático

El plugin registra el cron `jb3_cleanup_pending_users` con frecuencia diaria.

**Qué hace**: elimina usuarios con `jb3_status = pending` y `jb3_registered_at` de más de 30 días atrás. Previene la acumulación de registros abandonados.

Al **desactivar** el plugin el cron se cancela automáticamente.

---

## Notas para desarrolladores

### Hooks disponibles

| Hook | Tipo | Parámetros | Descripción |
|---|---|---|---|
| `jb3_after_profile_save` | action | `$user_id` | Se dispara tras guardar el formulario de perfil frontend |
| `jb3_sync_to_mailchimp` | action | `$user_id` | Fuerza sincronización del usuario con Mailchimp |

### Disparar sync manualmente

```php
do_action( 'jb3_sync_to_mailchimp', $user_id );
```

### Verificar estado de un usuario

```php
// ¿Es miembro activo?
if ( jb3_is_member( $user_id ) && jb3_is_approved( $user_id ) ) {
    // usuario activo
}

// ¿Tiene perfil completo?
if ( jb3_profile_complete( $user_id ) ) {
    // todos los campos obligatorios están completados
}
```

### Limpiar caché de Mailchimp desde código

```php
delete_transient( 'jb3_mc_interests_cache' );
delete_transient( 'jb3_mc_nl_interests_cache' );
// El próximo acceso a get_cached_interests() o get_newsletter_interests() lo reconstruye
```
