<?php
/**
 * Template: Profile update form.
 * Available vars: $user (WP_User), $errors (array), $success (bool)
 */
defined( 'ABSPATH' ) || exit;

// Helper: get value from POST (on failed submit) or from saved user meta / user field.
$val = function( $field ) use ( $user ) {
    if ( isset( $_POST[ $field ] ) ) {
        return esc_attr( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
    }
    // WP core fields.
    if ( in_array( $field, [ 'first_name', 'last_name', 'user_email' ], true ) ) {
        return esc_attr( $user->$field ?? '' );
    }
    return esc_attr( get_user_meta( $user->ID, $field, true ) );
};

$saved_interests = (array) get_user_meta( $user->ID, 'jb3_interests', true );
$posted_interests = isset( $_POST['interests'] ) && is_array( $_POST['interests'] )
    ? array_map( 'sanitize_text_field', $_POST['interests'] )
    : $saved_interests;
?>

<?php if ( $success ) : ?>
    <div class="jb3-notice jb3-success">
        <?php esc_html_e( 'Tus datos fueron actualizados correctamente.', 'jb3-members' ); ?>
    </div>
<?php endif; ?>

<?php if ( ! jb3_profile_complete( $user->ID ) && ! $success ) : ?>
    <div class="jb3-notice jb3-warning">
        <?php esc_html_e( 'Tu perfil está incompleto. Por favor completá todos los campos obligatorios para acceder al sitio.', 'jb3-members' ); ?>
    </div>
<?php endif; ?>

<?php if ( ! empty( $errors ) ) : ?>
    <div class="jb3-notice jb3-error" role="alert">
        <ul>
            <?php foreach ( $errors as $err ) : ?>
                <li><?php echo esc_html( $err ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="jb3-form" novalidate autocomplete="on">
    <?php wp_nonce_field( 'jb3_profile_' . $user->ID, 'jb3_profile_nonce' ); ?>

    <div class="jb3-row">
        <div class="jb3-field">
            <label for="nb_first_name"><?php esc_html_e( 'Nombre', 'jb3-members' ); ?> <span class="req">*</span></label>
            <input type="text" id="nb_first_name" name="first_name" value="<?php echo $val( 'first_name' ); ?>"
                   required maxlength="100" autocomplete="given-name">
        </div>
        <div class="jb3-field">
            <label for="nb_last_name"><?php esc_html_e( 'Apellido', 'jb3-members' ); ?> <span class="req">*</span></label>
            <input type="text" id="nb_last_name" name="last_name" value="<?php echo $val( 'last_name' ); ?>"
                   required maxlength="100" autocomplete="family-name">
        </div>
    </div>

    <div class="jb3-field">
        <label for="nb_email"><?php esc_html_e( 'Email', 'jb3-members' ); ?></label>
        <input type="email" id="nb_email" value="<?php echo $val( 'user_email' ); ?>"
               readonly autocomplete="email" style="opacity:.6;cursor:not-allowed;">
        <p class="jb3-required-note" style="margin:.2rem 0 0;"><?php esc_html_e( 'El email no puede modificarse.', 'jb3-members' ); ?></p>
    </div>

    <!-- Datos personales -->
    <div class="jb3-row">
        <div class="jb3-field">
            <label for="nb_country"><?php esc_html_e( 'País', 'jb3-members' ); ?> <span class="req">*</span></label>
            <select id="nb_country" name="country" required>
                <option value=""><?php esc_html_e( '— Seleccioná tu país —', 'jb3-members' ); ?></option>
                <?php foreach ( jb3_countries() as $code => $name ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $val( 'jb3_country' ), $code ); ?>>
                        <?php echo esc_html( $name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jb3-field">
            <label for="nb_phone"><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?> <span class="req">*</span></label>
            <input type="tel" id="nb_phone" name="phone" value="<?php echo $val( 'jb3_phone' ); ?>"
                   required maxlength="50" autocomplete="tel" pattern="[0-9+\-\s()]*">
        </div>
    </div>

    <!-- Datos laborales -->
    <div class="jb3-field">
        <label for="nb_company"><?php esc_html_e( 'Empresa', 'jb3-members' ); ?> <span class="req">*</span></label>
        <input type="text" id="nb_company" name="company" value="<?php echo $val( 'jb3_company' ); ?>"
               required maxlength="150" autocomplete="organization">
    </div>

    <div class="jb3-row">
        <div class="jb3-field">
            <label for="nb_sector"><?php esc_html_e( 'Sector / Industria', 'jb3-members' ); ?> <span class="req">*</span></label>
            <select id="nb_sector" name="sector" required>
                <option value=""><?php esc_html_e( '— Seleccioná tu sector —', 'jb3-members' ); ?></option>
                <?php foreach ( jb3_sectors() as $sector ) : ?>
                    <option value="<?php echo esc_attr( $sector ); ?>" <?php selected( $val( 'jb3_sector' ), esc_attr( $sector ) ); ?>>
                        <?php echo esc_html( $sector ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jb3-field">
            <label for="nb_job_title"><?php esc_html_e( 'Cargo', 'jb3-members' ); ?> <span class="req">*</span></label>
            <select id="nb_job_title" name="job_title" required>
                <option value=""><?php esc_html_e( '— Seleccioná tu cargo —', 'jb3-members' ); ?></option>
                <?php foreach ( jb3_job_titles() as $title ) : ?>
                    <option value="<?php echo esc_attr( $title ); ?>" <?php selected( $val( 'jb3_job_title' ), esc_attr( $title ) ); ?>>
                        <?php echo esc_html( $title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="jb3-field">
        <label><?php esc_html_e( 'Temas de interés', 'jb3-members' ); ?></label>
        <div class="jb3-checkboxes">
            <?php foreach ( jb3_interests() as $key => $label ) : ?>
                <label class="jb3-cb-label">
                    <input type="checkbox" name="interests[]" value="<?php echo esc_attr( $key ); ?>"
                           <?php checked( in_array( $key, $posted_interests, true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="jb3-field jb3-field--checkbox">
        <?php
        $newsletter_val = isset( $_POST['newsletter'] ) ? ! empty( $_POST['newsletter'] ) : (bool) get_user_meta( $user->ID, 'jb3_newsletter', true );
        ?>
        <label class="jb3-cb-label">
            <input type="checkbox" name="newsletter" value="1" <?php checked( $newsletter_val ); ?>>
            <?php esc_html_e( 'Suscribirme al Newsletter', 'jb3-members' ); ?>
        </label>
    </div>

    <p class="jb3-required-note"><span class="req">*</span> <?php esc_html_e( 'Campos obligatorios', 'jb3-members' ); ?></p>

    <button type="submit" class="jb3-btn">
        <?php esc_html_e( 'Guardar cambios', 'jb3-members' ); ?>
    </button>
</form>

<!-- Eliminar cuenta -->
<form method="post" class="jb3-form" style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid color-mix(in srgb,currentColor 15%,transparent);"
      onsubmit="return confirm('<?php esc_attr_e( '¿Estás seguro de que querés eliminar tu cuenta? Esta acción no se puede deshacer.', 'jb3-members' ); ?>');">
    <?php wp_nonce_field( 'jb3_delete_account_' . $user->ID, 'jb3_delete_nonce' ); ?>
    <h3 style="margin:0 0 .5rem;"><?php esc_html_e( 'Eliminar cuenta', 'jb3-members' ); ?></h3>
    <p style="margin:0 0 1rem;opacity:.7;"><?php esc_html_e( 'Al eliminar tu cuenta se borrarán todos tus datos del sitio y serás dado de baja de nuestras listas de correo.', 'jb3-members' ); ?></p>
    <button type="submit" name="jb3_delete_account" value="1"
            style="background:#c0392b;color:#fff;border:none;padding:.6rem 1.4rem;border-radius:5px;cursor:pointer;font-size:.95rem;">
        <?php esc_html_e( 'Eliminar mi cuenta', 'jb3-members' ); ?>
    </button>
</form>
