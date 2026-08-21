<?php
/**
 * Email template: magic link for returning users.
 * Available vars: $user (WP_User), $magic_url (string)
 */
defined( 'ABSPATH' ) || exit;

$site_name = get_bloginfo( 'name' );
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
        <p style="margin:4px 0 0;color:#b3daf0;font-size:14px;"><?php esc_html_e( 'Tu enlace de acceso', 'jb3-members' ); ?></p>
    </td></tr>

    <!-- Body -->
    <tr><td style="padding:36px 32px;">
        <p style="font-size:16px;">Hola <strong><?php echo esc_html( $user->first_name ?: $user->display_name ); ?></strong>,</p>

        <p style="font-size:15px;line-height:1.6;">
            Solicitaste acceso a <strong><?php echo esc_html( $site_name ); ?></strong>.
            Hacé clic en el botón para ingresar:
        </p>

        <div style="text-align:center;margin:32px 0;">
            <table cellpadding="0" cellspacing="0" align="center"><tr><td style="background:#0073aa;">
                <a href="<?php echo esc_url( $magic_url ); ?>"
                   style="display:inline-block;padding:16px 40px;color:#fff;text-decoration:none;font-size:18px;font-weight:700;">
                    Ingresar al sitio
                </a>
            </td></tr></table>
        </div>

        <p style="font-size:14px;color:#555;line-height:1.6;">
            Este enlace es de un solo uso y te mantendrá logueado por <strong>120 días</strong>.
            Si no solicitaste este correo, simplemente ignoralo.
        </p>

        <p style="margin-top:24px;font-size:12px;color:#999;">
            Si el botón no funciona, copiá y pegá este enlace:<br>
            <a href="<?php echo esc_url( $magic_url ); ?>" style="color:#0073aa;word-break:break-all;"><?php echo esc_url( $magic_url ); ?></a>
        </p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
