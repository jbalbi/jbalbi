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
