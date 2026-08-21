<?php
/**
 * Email template: aviso a representantes cuando un usuario descarga una Op. Comercial.
 * Available vars: $user (WP_User), $user_id (int), $post_id (int)
 */
defined( 'ABSPATH' ) || exit;

$countries    = jb3_countries();
$country      = get_user_meta( $user_id, 'jb3_country',   true );
$company      = get_user_meta( $user_id, 'jb3_company',   true );
$job_title    = get_user_meta( $user_id, 'jb3_job_title', true );
$phone        = get_user_meta( $user_id, 'jb3_phone',     true );
$country_name = isset( $countries[ $country ] ) ? $countries[ $country ] : $country;
$site_name    = get_bloginfo( 'name' );
$post_title   = get_the_title( $post_id );
$post_url     = get_permalink( $post_id );
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 10px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">

    <!-- Header -->
    <tr><td style="background:#730687;padding:28px 32px;">
        <h1 style="margin:0;color:#fff;font-size:22px;"><?php echo esc_html( $site_name ); ?></h1>
        <p style="margin:4px 0 0;color:#e3c6ec;font-size:14px;"><?php esc_html_e( 'Descarga de Oportunidad Comercial', 'jb3-members' ); ?></p>
    </td></tr>

    <!-- Body -->
    <tr><td style="padding:32px;">
        <p style="font-size:16px;">Hola,</p>
        <p>
            <strong><?php echo esc_html( $user->display_name ); ?></strong>
            descargó el material de <strong><?php echo esc_html( $post_title ); ?></strong>.
            Te dejamos sus datos por si querés contactarlo:
        </p>

        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin:20px 0;font-size:14px;">
            <?php
            $rows = [
                [ 'Nombre completo', esc_html( $user->display_name ) ],
                [ 'Email',           esc_html( $user->user_email ) ],
                [ 'Empresa',         esc_html( $company ) ],
                [ 'Cargo',           esc_html( $job_title ) ],
                [ 'Teléfono',        esc_html( $phone ) ],
                [ 'País',            esc_html( $country_name ) ],
                [ 'Descargó',        esc_html( $post_title ) ],
                [ 'Fecha y hora',    esc_html( current_time( 'd/m/Y H:i' ) ) ],
            ];
            foreach ( $rows as $i => [ $label, $value ] ) :
                $bg = ( 0 === $i % 2 ) ? '#f9f9f9' : '#fff';
            ?>
            <tr style="background:<?php echo $bg; ?>;border-bottom:1px solid #eee;">
                <td style="font-weight:600;color:#555;width:35%;padding:10px 12px;"><?php echo esc_html( $label ); ?></td>
                <td style="padding:10px 12px;"><?php echo $value; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <table cellpadding="0" cellspacing="0"><tr><td style="background:#730687;">
            <a href="<?php echo esc_url( $post_url ); ?>"
               style="display:inline-block;padding:14px 32px;color:#fff;text-decoration:none;font-size:16px;font-weight:700;">
                <?php esc_html_e( 'Ver la Oportunidad Comercial', 'jb3-members' ); ?>
            </a>
        </td></tr></table>

        <p style="margin-top:24px;font-size:12px;color:#999;">
            <?php esc_html_e( 'Podés responderle directo a este usuario escribiendo a su email de arriba.', 'jb3-members' ); ?>
        </p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
