<?php
/**
 * Email template: approval request sent to representatives.
 * Available vars: $user (WP_User), $user_id (int), $approve_url (string)
 */
defined( 'ABSPATH' ) || exit;

$countries   = jb3_countries();
$country     = get_user_meta( $user_id, 'jb3_country',   true );
$company     = get_user_meta( $user_id, 'jb3_company',   true );
$job_title   = get_user_meta( $user_id, 'jb3_job_title', true );
$phone       = get_user_meta( $user_id, 'jb3_phone',     true );
$sector      = get_user_meta( $user_id, 'jb3_sector',    true );
$newsletter  = get_user_meta( $user_id, 'jb3_newsletter', true ) ? 'Sí' : 'No';
$raw_int     = (array) get_user_meta( $user_id, 'jb3_interests', true );
$all_int     = jb3_interests();
$int_labels  = array_filter( array_map( fn( $k ) => $all_int[ $k ] ?? null, $raw_int ) );
$interests   = ! empty( $int_labels ) ? implode( ', ', $int_labels ) : '—';
$country_name = isset( $countries[ $country ] ) ? $countries[ $country ] : $country;
$registered  = get_user_meta( $user_id, 'jb3_registered_at', true );
$site_name   = get_bloginfo( 'name' );
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
    <tr><td style="background:#0073aa;padding:28px 32px;">
        <h1 style="margin:0;color:#fff;font-size:22px;"><?php echo esc_html( $site_name ); ?></h1>
        <p style="margin:4px 0 0;color:#b3daf0;font-size:14px;"><?php esc_html_e( 'Nuevo registro pendiente de aprobación', 'jb3-members' ); ?></p>
    </td></tr>

    <!-- Body -->
    <tr><td style="padding:32px;">
        <p style="font-size:16px;">Hola,</p>
        <p>Un nuevo usuario se ha registrado en <strong><?php echo esc_html( $site_name ); ?></strong> y requiere tu aprobación:</p>

        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin:20px 0;font-size:14px;">
            <?php
            $rows = [
                [ 'Nombre completo', esc_html( $user->display_name ) ],
                [ 'Email',           esc_html( $user->user_email ) ],
                [ 'Empresa',         esc_html( $company ) ],
                [ 'Cargo',           esc_html( $job_title ) ],
                [ 'Teléfono',        esc_html( $phone ) ],
                [ 'País',            esc_html( $country_name ) ],
                [ 'Sector',          esc_html( $sector ) ],
                [ 'Intereses',       esc_html( $interests ) ],
                [ 'Newsletter',      esc_html( $newsletter ) ],
                [ 'Fecha registro',  esc_html( $registered ) ],
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

        <p style="margin:28px 0 8px;font-size:15px;">¿Querés aprobar a este usuario?</p>

        <table cellpadding="0" cellspacing="0"><tr><td style="background:#2e7d32;">
            <a href="<?php echo esc_url( $approve_url ); ?>"
               style="display:inline-block;padding:14px 32px;color:#fff;text-decoration:none;font-size:16px;font-weight:700;">
                ✓ &nbsp;<?php esc_html_e( 'Aprobar usuario', 'jb3-members' ); ?>
            </a>
        </td></tr></table>

        <p style="margin-top:24px;font-size:12px;color:#999;">
            <?php esc_html_e( 'Si no podés hacer clic en el botón, copiá y pegá este enlace en tu navegador:', 'jb3-members' ); ?><br>
            <a href="<?php echo esc_url( $approve_url ); ?>" style="color:#0073aa;word-break:break-all;"><?php echo esc_url( $approve_url ); ?></a>
        </p>
    </td></tr>

    <!-- Footer -->
    <tr><td style="background:#f4f4f4;padding:16px 32px;text-align:center;font-size:12px;color:#999;border-top:1px solid #e0e0e0;">
        <?php echo esc_html( $site_name ); ?> &mdash; <?php echo esc_html( home_url() ); ?>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
