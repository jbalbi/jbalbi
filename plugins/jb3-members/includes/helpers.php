<?php
defined( 'ABSPATH' ) || exit;

// ─── Data lists ──────────────────────────────────────────────────────────────

function jb3_countries() {
    return [
        'AR' => 'Argentina',
        'BO' => 'Bolivia',
        'BR' => 'Brasil',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'CR' => 'Costa Rica',
        'EC' => 'Ecuador',
        'SV' => 'El Salvador',
        'GT' => 'Guatemala',
        'HN' => 'Honduras',
        'MX' => 'México',
        'NI' => 'Nicaragua',
        'PA' => 'Panamá',
        'PY' => 'Paraguay',
        'PE' => 'Perú',
        'DO' => 'República Dominicana',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'VE' => 'Venezuela',
    ];
}

function jb3_job_titles() {
    return [
        'CEO (Chief Executive Officer)',
        'Director',
        'Gerente',
        'Supervisor',
        'Coordinador de Proyectos',
        'Asesor',
        'Administrador',
        'Analista Senior',
        'Especialista',
        'Analista',
        'Técnico',
        'Operador',
        'Encargado',
        'Representante',
        'Inspector',
        'Asistente',
        'Auxiliar',
        'Empleado',
        'Otro',
    ];
}

function jb3_sectors() {
    return [
        'Servicios Profesionales',
        'Comercio Minorista',
        'Tecnología de la Información (TI)',
        'Alimentación y Bebidas',
        'Salud y Bienestar',
        'Construcción y Arquitectura',
        'Manufactura',
        'Educación',
        'Medios y Entretenimiento',
        'Transporte y Logística',
        'Servicios Financieros',
        'Agricultura',
        'Turismo y Hospitalidad',
        'Bienes Raíces',
        'Consultoría de Negocios',
        'Servicios de Ingeniería',
        'Servicios Legales',
        'Servicios de Marketing y Publicidad',
        'Servicios de Recursos Humanos',
        'Comercio Electrónico',
        'Otro',
    ];
}

function jb3_interests() {
    return JB3_Mailchimp::get_cached_interests();
}

/**
 * Mantiene "Newsletter por País" (jb3_newsletter_countries, IDs de interés
 * de Mailchimp) coherente con el estado de suscripción — se llama SOLO en
 * la transición de jb3_newsletter (pasa a suscripto o pasa a desuscripto),
 * nunca en cada guardado, para no pisar una selección manual del admin
 * mientras el usuario sigue en el mismo estado:
 *   - Al SUSCRIBIRSE: tilda ÚNICAMENTE el interés del país propio del
 *     usuario (jb3_country) — pisa cualquier selección vieja.
 *   - Al DESUSCRIBIRSE: borra TODOS los países tildados — así una
 *     resuscripción futura arranca limpia (solo el país propio), en vez de
 *     resucitar una selección vieja que nadie volvió a revisar.
 */
function jb3_sync_newsletter_countries( int $user_id, bool $newsletter ): void {
    if ( ! $newsletter ) {
        delete_user_meta( $user_id, 'jb3_newsletter_countries' );
        return;
    }

    $country_code = strtoupper( (string) get_user_meta( $user_id, 'jb3_country', true ) );
    if ( ! $country_code ) {
        delete_user_meta( $user_id, 'jb3_newsletter_countries' );
        return;
    }

    $mc_id = null;
    foreach ( JB3_Mailchimp::get_newsletter_interests() as $id => $name ) {
        if ( strtoupper( $name ) === $country_code ) {
            $mc_id = $id;
            break;
        }
    }

    if ( $mc_id ) {
        update_user_meta( $user_id, 'jb3_newsletter_countries', [ $mc_id ] );
    } else {
        delete_user_meta( $user_id, 'jb3_newsletter_countries' );
    }
}

// ─── User helpers ─────────────────────────────────────────────────────────────

function jb3_is_member( $user_id ) {
    $user = get_userdata( $user_id );
    return $user && in_array( 'jb3_member', (array) $user->roles, true );
}

function jb3_get_status( $user_id ) {
    return get_user_meta( $user_id, 'jb3_status', true ) ?: 'pending';
}

function jb3_is_approved( $user_id ) {
    return jb3_get_status( $user_id ) === 'approved';
}

/**
 * Returns true only if all required profile fields are present.
 */
function jb3_profile_complete( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }
    $wp_fields   = [ 'first_name', 'last_name' ];
    $meta_fields = [ 'jb3_company', 'jb3_job_title', 'jb3_phone', 'jb3_country', 'jb3_sector' ];

    foreach ( $wp_fields as $f ) {
        if ( empty( $user->$f ) ) {
            return false;
        }
    }
    foreach ( $meta_fields as $f ) {
        if ( empty( get_user_meta( $user_id, $f, true ) ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Sanitise a phone number: keep only digits, +, -, spaces, parentheses.
 */
function jb3_sanitize_phone( $phone ) {
    return preg_replace( '/[^0-9+\-\s()]/', '', $phone ?? '' );
}

/**
 * Cache-busting query string for a plugin asset, based on the file's own
 * mtime instead of the static JB3_VERSION constant — JB3_VERSION doesn't
 * change on every CSS/JS edit during development, so the browser (and any
 * HTTP cache) kept serving a stale cached copy after edits. Same pattern
 * the theme already uses for style.css (functions.php).
 */
function jb3_asset_ver( $relative_path ) {
    $file = JB3_DIR . ltrim( $relative_path, '/' );
    return file_exists( $file ) ? (string) filemtime( $file ) : JB3_VERSION;
}

// ─── Detección de bots (best-effort) ────────────────────────────────────────
// Escáneres de seguridad de correo (Microsoft Safe Links, Mimecast,
// Proofpoint, Barracuda, etc.) abren los links de un mail automáticamente
// para revisarlos antes de que el destinatario real los vea. NO hay forma
// 100% confiable de detectar esto del lado del servidor — es una lista de
// firmas de User-Agent conocidas, best-effort, no exhaustiva. Compartida
// entre el tracking de vistas/descargas (class-op-comerciales-tracking.php,
// donde solo se usa para marcar "posible bot" a mano en la planilla) y los
// magic links (class-magic-link.php, donde además evita que un escáner
// gaste el token de un solo uso antes de que lo use la persona real) —
// antes vivía duplicada solo en el primero.
const JB3_BOT_UA_SIGNATURES = [
	'ips-agent', 'safelinks', 'microsoft office', 'outlook-safelinks',
	'barracuda', 'mimecast', 'proofpoint', 'symantec', 'trendmicro',
	'fortinet', 'fortimail', 'mcafee', 'zscaler', 'cisco security',
	'googleimageproxy', 'bot', 'crawler', 'spider', 'scanner', 'preview',
];

function jb3_es_posible_bot( string $user_agent ): bool {
	if ( '' === trim( $user_agent ) ) {
		return true;
	}
	$ua_lower = strtolower( $user_agent );
	foreach ( JB3_BOT_UA_SIGNATURES as $signature ) {
		if ( false !== strpos( $ua_lower, $signature ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Rate limit compartido por email — mismo criterio que ya usaba
 * class-login.php a mano (ahora factorizado acá para que la entrada
 * automática vía Mailchimp ?e= use el MISMO presupuesto que el
 * formulario de login manual, y no se puedan combinar los dos caminos
 * para mandar más magic links de los debidos a un mismo email).
 *
 * @return bool true si YA se alcanzó el límite (hay que bloquear); false
 *              si todavía hay margen (esta llamada ya lo contabilizó).
 */
function jb3_rate_limited( string $email, int $max = 3, int $window_minutes = 10 ): bool {
    $key   = 'jb3_rl_' . md5( strtolower( $email ) );
    $count = (int) get_transient( $key );
    if ( $count >= $max ) {
        return true;
    }
    set_transient( $key, $count + 1, $window_minutes * MINUTE_IN_SECONDS );
    return false;
}
