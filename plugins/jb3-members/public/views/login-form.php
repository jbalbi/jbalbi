<?php
/**
 * Template: Login form (magic-link request).
 * Available vars: $errors (array), $sent (bool)
 */
defined( 'ABSPATH' ) || exit;
?>

<?php if ( $sent ) : ?>
    <div class="jb3-notice jb3-success">
        <strong><?php esc_html_e( '¡Revisá tu correo!', 'jb3-members' ); ?></strong>
        <p><?php esc_html_e( 'Si tu email está registrado y tu cuenta está activa, recibirás un enlace de acceso en los próximos minutos.', 'jb3-members' ); ?></p>
    </div>
<?php else : ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="jb3-notice jb3-error" role="alert">
            <ul>
                <?php foreach ( $errors as $err ) : ?>
                    <li><?php echo esc_html( $err ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="jb3-form jb3-form--login" novalidate>
        <?php wp_nonce_field( 'jb3_login', 'jb3_login_nonce' ); ?>

        <div class="jb3-field">
            <label for="nb_login_email"><?php esc_html_e( 'Tu email registrado', 'jb3-members' ); ?></label>
            <input type="email" id="nb_login_email" name="email"
                   value="<?php echo esc_attr( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) ); ?>"
                   required maxlength="150" autocomplete="email"
                   placeholder="nombre@empresa.com">
        </div>

        <button type="submit" class="jb3-btn">
            <?php esc_html_e( 'Enviarme mi enlace de acceso', 'jb3-members' ); ?>
        </button>
    </form>

<?php endif; ?>
