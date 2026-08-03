<?php
/**
 * Página de diagnóstico standalone.
 * Acceso: jbalbi.test/wp-content/themes/generatepress-child/userinfo.php
 *
 * Bootstrapea WordPress solo para acceder a sus funciones,
 * sin cargar ningún template ni theme.
 */

// Cargar WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Solo administradores
if ( ! current_user_can( 'manage_options' ) ) {
    http_response_code( 403 );
    die( 'Acceso restringido.' );
}

// Headers de IP (para detectar proxies y túneles)
$ip_headers = [
    'REMOTE_ADDR'            => $_SERVER['REMOTE_ADDR']            ?? '',
    'HTTP_X_FORWARDED_FOR'   => $_SERVER['HTTP_X_FORWARDED_FOR']   ?? '',
    'HTTP_X_REAL_IP'         => $_SERVER['HTTP_X_REAL_IP']         ?? '',
    'HTTP_CF_CONNECTING_IP'  => $_SERVER['HTTP_CF_CONNECTING_IP']  ?? '',
    'HTTP_X_FORWARDED_HOST'  => $_SERVER['HTTP_X_FORWARDED_HOST']  ?? '',
];

$ip        = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
$cache_key = 'jb3_geo_' . md5( $ip );

// Acciones
if ( isset( $_GET['clear_cache'] ) ) {
    delete_transient( $cache_key );
    wp_safe_redirect( strtok( $_SERVER['REQUEST_URI'], '?' ) );
    exit;
}

// Override manual de país para testing (se guarda en cookie de sesión)
if ( isset( $_GET['set_country'] ) ) {
    $force = strtolower( sanitize_text_field( $_GET['set_country'] ) );
    if ( preg_match( '/^[a-z]{2}$/', $force ) ) {
        setcookie( 'jb3_country_override', $force, 0, '/' );
        $_COOKIE['jb3_country_override'] = $force;
    }
}
if ( isset( $_GET['clear_country'] ) ) {
    setcookie( 'jb3_country_override', '', time() - 3600, '/' );
    unset( $_COOKIE['jb3_country_override'] );
}

$our_country = jb3_get_visitor_country();
$cached      = get_transient( $cache_key );
$pais_term   = get_term_by( 'slug', $our_country, 'pais' );
$pais_all    = get_terms( [ 'taxonomy' => 'pais', 'hide_empty' => false ] );
$override    = $_COOKIE['jb3_country_override'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>JB3 User Info Debug</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', monospace; font-size: 13px; background: #f0f0f0; margin: 0; padding: 30px; color: #222; }
    h1   { font-family: sans-serif; font-size: 22px; margin: 0 0 4px; }
    .sub { font-family: sans-serif; font-size: 12px; color: #888; margin-bottom: 30px; }
    section { background: #fff; border-radius: 8px; padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    h2   { font-family: sans-serif; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #555; margin: 0 0 12px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    td   { padding: 6px 10px; border-bottom: 1px solid #f4f4f4; vertical-align: top; }
    td:first-child { color: #777; width: 220px; white-space: nowrap; }
    code { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
    .ok   { color: #2e7d32; font-weight: bold; }
    .warn { color: #e65100; font-weight: bold; }
    .match { background: #e8f5e9; }
    a.btn { display: inline-block; margin-top: 12px; padding: 6px 14px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; font-family: sans-serif; font-size: 12px; }
</style>
</head>
<body>

<h1>🔍 JB3 User Info Debug</h1>
<p class="sub"><?php echo esc_html( home_url() ); ?> — <?php echo esc_html( date( 'Y-m-d H:i:s' ) ); ?></p>

<!-- RED -->
<section>
    <h2>Red — Headers de IP</h2>
    <table>
        <?php foreach ( $ip_headers as $header => $value ) :
            $is_local = in_array( $value, [ '127.0.0.1', '::1', '' ], true ); ?>
            <tr>
                <td><code><?php echo esc_html( $header ); ?></code></td>
                <td>
                    <?php if ( $value === '' ) : ?>
                        <em style="color:#aaa">(vacío)</em>
                    <?php else : ?>
                        <code><?php echo esc_html( $value ); ?></code>
                        <?php echo $is_local ? ' <span class="warn">← localhost</span>' : ''; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>

<!-- OVERRIDE MANUAL -->
<section>
    <h2>Override manual de país (solo para testing)</h2>
    <?php if ( $override ) : ?>
        <p>País forzado activo: <code><strong><?php echo esc_html( $override ); ?></strong></code>
        — <a href="?clear_country=1">Quitar override</a></p>
    <?php else : ?>
        <p style="color:#888;margin:0 0 10px">Sin override activo — usando detección real.</p>
    <?php endif; ?>
    <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="text" name="set_country" placeholder="ej: ar" maxlength="2"
               style="padding:6px 10px;font-family:monospace;border:1px solid #ccc;border-radius:4px;width:80px">
        <button type="submit" style="padding:6px 14px;background:#333;color:#fff;border:none;border-radius:4px;cursor:pointer;font-family:sans-serif;font-size:12px">
            Forzar país
        </button>
    </form>
    <p style="font-size:11px;color:#aaa;margin-top:8px">
        Ingresá el código ISO de 2 letras (ar, cl, mx, us…). Se guarda en cookie de sesión y afecta al menú geotargetizado.
    </p>
</section>

<!-- PLUGIN GEOLOCATION -->
<section>
    <h2>Plugin: Geolocation IP Detection</h2>
    <table>
    <?php if ( function_exists( 'geoip_detect2_get_info_from_current_ip' ) ) :
        $geo  = geoip_detect2_get_info_from_current_ip();
        $iso  = $geo->country->isoCode ?? '';
        $name = $geo->country->name ?? '';
        ?>
        <tr><td>Estado</td><td><span class="ok">✅ Activo</span></td></tr>
        <tr><td>isEmpty</td><td><?php echo $geo->isEmpty
            ? '<span class="warn">⚠️ Sí — no detectó la IP (normal en localhost)</span>'
            : '<span class="ok">No</span>'; ?></td></tr>
        <tr><td>Country ISO Code</td><td><code><?php echo esc_html( $iso ?: '(vacío)' ); ?></code></td></tr>
        <tr><td>Country Name</td><td><?php echo esc_html( $name ?: '(vacío)' ); ?></td></tr>
    <?php else : ?>
        <tr><td>Estado</td><td><span class="warn">⚠️ No instalado o no activo</span></td></tr>
    <?php endif; ?>
    </table>
</section>

<!-- NUESTRA DETECCIÓN -->
<section>
    <h2>jb3_get_visitor_country()</h2>
    <table>
        <tr><td>Resultado</td><td><code><strong><?php echo esc_html( $our_country ); ?></strong></code></td></tr>
        <tr><td>Caché transient</td><td><?php echo false !== $cached
            ? '<code>' . esc_html( $cached ) . '</code>'
            : '<em>(sin caché)</em>'; ?></td></tr>
        <tr><td>Término pais match</td><td><?php echo ( $pais_term && ! is_wp_error( $pais_term ) )
            ? '<span class="ok">✅ ' . esc_html( $pais_term->name ) . ' (ID: ' . (int) $pais_term->term_id . ')</span>'
            : '<span class="warn">⚠️ No encontrado para slug "<code>' . esc_html( $our_country ) . '</code>"</span>'; ?>
        </td></tr>
    </table>
    <a class="btn" href="?clear_cache=1">🗑 Limpiar caché de geolocalización</a>
</section>

<!-- TÉRMINOS PAIS -->
<section>
    <h2>Términos de la taxonomía pais</h2>
    <table>
        <tr>
            <td><strong>Nombre</strong></td>
            <td><strong>Slug</strong></td>
            <td><strong>Posts</strong></td>
            <td><strong>Match</strong></td>
        </tr>
        <?php if ( ! is_wp_error( $pais_all ) ) :
            foreach ( $pais_all as $t ) :
                $is_match = ( $t->slug === $our_country ); ?>
                <tr class="<?php echo $is_match ? 'match' : ''; ?>">
                    <td><?php echo esc_html( $t->name ); ?></td>
                    <td><code><?php echo esc_html( $t->slug ); ?></code></td>
                    <td><?php echo (int) $t->count; ?></td>
                    <td><?php echo $is_match ? '<span class="ok">← MATCH</span>' : ''; ?></td>
                </tr>
            <?php endforeach;
        endif; ?>
    </table>
</section>

<!-- USUARIO -->
<section>
    <h2>Usuario logueado</h2>
    <?php if ( is_user_logged_in() ) :
        $user        = wp_get_current_user();
        $jb3_country = get_user_meta( $user->ID, 'jb3_country', true ); ?>
    <table>
        <tr><td>Nombre</td><td><?php echo esc_html( $user->display_name ); ?></td></tr>
        <tr><td>Email</td><td><?php echo esc_html( $user->user_email ); ?></td></tr>
        <tr><td>Roles</td><td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td></tr>
        <tr><td>jb3_country (meta)</td><td><code><?php echo esc_html( $jb3_country ?: '(vacío)' ); ?></code></td></tr>
    </table>
    <?php else : ?>
    <p style="color:#888;margin:0">No hay usuario logueado.</p>
    <?php endif; ?>
</section>

</body>
</html>
