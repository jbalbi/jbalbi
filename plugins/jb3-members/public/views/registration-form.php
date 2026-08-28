<?php
/**
 * Template: Registration form.
 * Available vars: $errors (array), $success (bool)
 */
defined( 'ABSPATH' ) || exit;

$p = function( $key ) { return esc_attr( sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) ); };
?>

<?php if ( $success ) : ?>
    <div class="jb3-notice jb3-success">
        <strong><?php esc_html_e( '¡Registro recibido!', 'jb3-members' ); ?></strong>
        <p><?php esc_html_e( 'Tu solicitud fue enviada. Un representante de tu región revisará tu registro y recibirás un correo cuando sea aprobado.', 'jb3-members' ); ?></p>
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

    <form method="post" class="jb3-form jb3-form--recaptcha" novalidate autocomplete="on">
        <?php wp_nonce_field( 'jb3_register', 'jb3_register_nonce' ); ?>

        <!-- Datos personales -->
        <div class="jb3-row">
            <div class="jb3-field">
                <label for="nb_first_name"><?php esc_html_e( 'Nombre', 'jb3-members' ); ?> <span class="req">*</span></label>
                <input type="text" id="nb_first_name" name="first_name" value="<?php echo $p( 'first_name' ); ?>"
                       required maxlength="100" autocomplete="given-name">
            </div>
            <div class="jb3-field">
                <label for="nb_last_name"><?php esc_html_e( 'Apellido', 'jb3-members' ); ?> <span class="req">*</span></label>
                <input type="text" id="nb_last_name" name="last_name" value="<?php echo $p( 'last_name' ); ?>"
                       required maxlength="100" autocomplete="family-name">
            </div>
        </div>

        <div class="jb3-field">
            <label for="nb_email"><?php esc_html_e( 'Email', 'jb3-members' ); ?> <span class="req">*</span></label>
            <input type="email" id="nb_email" name="email" value="<?php echo $p( 'email' ); ?>"
                   required maxlength="150" autocomplete="email">
        </div>

        <div class="jb3-row">
            <div class="jb3-field">
                <label for="nb_country"><?php esc_html_e( 'País', 'jb3-members' ); ?> <span class="req">*</span></label>
                <select id="nb_country" name="country" required>
                    <option value=""><?php esc_html_e( '— Seleccioná tu país —', 'jb3-members' ); ?></option>
                    <?php foreach ( jb3_countries() as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $p( 'country' ), $code ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="jb3-field">
                <label for="nb_phone"><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?> <span class="req">*</span></label>
                <input type="tel" id="nb_phone" name="phone" value="<?php echo $p( 'phone' ); ?>"
                       required maxlength="50" autocomplete="tel"
                       pattern="[0-9+\-\s()]*">
            </div>
        </div>

        <!-- Datos laborales -->
        <div class="jb3-field">
            <label for="nb_company"><?php esc_html_e( 'Empresa', 'jb3-members' ); ?> <span class="req">*</span></label>
            <input type="text" id="nb_company" name="company" value="<?php echo $p( 'company' ); ?>"
                   required maxlength="150" autocomplete="organization">
        </div>

        <div class="jb3-row">
            <div class="jb3-field">
                <label for="nb_sector"><?php esc_html_e( 'Sector / Industria', 'jb3-members' ); ?> <span class="req">*</span></label>
                <select id="nb_sector" name="sector" required>
                    <option value=""><?php esc_html_e( '— Seleccioná tu sector —', 'jb3-members' ); ?></option>
                    <?php foreach ( jb3_sectors() as $sector ) : ?>
                        <option value="<?php echo esc_attr( $sector ); ?>" <?php selected( $p( 'sector' ), esc_attr( $sector ) ); ?>>
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
                        <option value="<?php echo esc_attr( $title ); ?>" <?php selected( $p( 'job_title' ), esc_attr( $title ) ); ?>>
                            <?php echo esc_html( $title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Intereses y newsletter -->
        <div class="jb3-field">
            <label><?php esc_html_e( 'Temas de interés', 'jb3-members' ); ?></label>
            <div class="jb3-checkboxes">
                <?php
                $posted_interests = isset( $_POST['interests'] ) && is_array( $_POST['interests'] )
                    ? array_map( 'sanitize_text_field', $_POST['interests'] )
                    : [];
                foreach ( jb3_interests() as $key => $label ) :
                ?>
                    <label class="jb3-cb-label">
                        <input type="checkbox" name="interests[]" value="<?php echo esc_attr( $key ); ?>"
                               <?php checked( in_array( $key, $posted_interests, true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="jb3-field jb3-field--checkbox">
            <label class="jb3-cb-label">
                <?php
                // Checked by default on first load; respects user choice on re-display after errors.
                $newsletter_checked = isset( $_POST['jb3_register_nonce'] ) ? ! empty( $_POST['newsletter'] ) : true;
                ?>
                <input type="checkbox" name="newsletter" value="1" <?php checked( $newsletter_checked ); ?>>
                <?php esc_html_e( 'Quiero suscribirme al Newsletter', 'jb3-members' ); ?>
            </label>
        </div>

        <p class="jb3-required-note"><span class="req">*</span> <?php esc_html_e( 'Campos obligatorios', 'jb3-members' ); ?></p>

        <button type="submit" class="jb3-btn">
            <?php esc_html_e( 'Registrarme', 'jb3-members' ); ?>
        </button>
    </form>
<?php endif; ?>
