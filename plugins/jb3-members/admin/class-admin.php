<?php
defined( 'ABSPATH' ) || exit;

class JB3_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Form processing must run at admin_init — before any output is sent.
        add_action( 'admin_init', [ __CLASS__, 'handle_rep_actions' ] );

        // CSV export / import — must run at admin_init before any output.
        add_action( 'admin_init', [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_import' ] );

        // Users list – custom columns.
        add_filter( 'manage_users_columns',       [ __CLASS__, 'user_columns' ] );
        add_filter( 'manage_users_custom_column', [ __CLASS__, 'user_column_value' ], 10, 3 );
        add_filter( 'user_row_actions',           [ __CLASS__, 'user_row_actions' ], 10, 2 );

        // AJAX handlers for user actions (run via admin-ajax.php — headers not yet sent).
        add_action( 'wp_ajax_jb3_approve_user',       [ __CLASS__, 'ajax_approve_user' ] );
        add_action( 'wp_ajax_jb3_logout_user',        [ __CLASS__, 'ajax_logout_user' ] );
        add_action( 'wp_ajax_jb3_fetch_mc_interests', [ __CLASS__, 'ajax_fetch_mc_interests' ] );

        // Bulk Mailchimp sync (AJAX handlers live in JB3_Sync).
        JB3_Sync::init();

        // Settings registration.
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

        // Admin notices.
        add_action( 'admin_notices', [ __CLASS__, 'admin_notices' ] );

        // User profile – JB3 fields section.
        add_action( 'show_user_profile',        [ __CLASS__, 'render_user_profile' ] );
        add_action( 'edit_user_profile',        [ __CLASS__, 'render_user_profile' ] );
        add_action( 'edit_user_profile_update', [ __CLASS__, 'save_user_profile' ] );
        add_action( 'personal_options_update',  [ __CLASS__, 'save_user_profile' ] );
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public static function register_menu() {
        add_menu_page(
            __( 'JB3 Members', 'jb3-members' ),
            __( 'JB3 Members', 'jb3-members' ),
            'manage_options',
            'jb3-members',
            [ __CLASS__, 'page_representatives' ],
            'dashicons-groups',
            30
        );
        add_submenu_page(
            'jb3-members',
            __( 'Representantes', 'jb3-members' ),
            __( 'Representantes', 'jb3-members' ),
            'manage_options',
            'jb3-members',
            [ __CLASS__, 'page_representatives' ]
        );
        add_submenu_page(
            'jb3-members',
            __( 'Configuración', 'jb3-members' ),
            __( 'Configuración', 'jb3-members' ),
            'manage_options',
            'jb3-settings',
            [ __CLASS__, 'page_settings' ]
        );
        add_submenu_page(
            'jb3-members',
            __( 'Exportar Usuarios', 'jb3-members' ),
            __( 'Exportar Usuarios', 'jb3-members' ),
            'manage_options',
            'jb3-export',
            [ __CLASS__, 'page_export' ]
        );
        add_submenu_page(
            'jb3-members',
            __( 'Importar Usuarios', 'jb3-members' ),
            __( 'Importar Usuarios', 'jb3-members' ),
            'manage_options',
            'jb3-import',
            [ __CLASS__, 'page_import' ]
        );
        add_submenu_page(
            'jb3-members',
            __( 'Sincronizar Mailchimp', 'jb3-members' ),
            __( 'Sincronizar Mailchimp', 'jb3-members' ),
            'manage_options',
            'jb3-sync',
            [ __CLASS__, 'page_sync' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue( $hook ) {
        $allowed = [
            'toplevel_page_jb3-members',
            'jb3-members_page_jb3-settings',
            'jb3-members_page_jb3-sync',
        ];
        if ( ! in_array( $hook, $allowed, true ) ) {
            return;
        }
        wp_enqueue_style( 'jb3-admin', JB3_URL . 'public/css/jb3-forms.css', [], JB3_VERSION );
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public static function register_settings() {
        register_setting( 'jb3_settings', 'jb3_login_page_id',        [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_profile_page_id',      [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_dashboard_page_id',    [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_mc_api_key',  [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'jb3_settings', 'jb3_mc_list_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
    }

    // -------------------------------------------------------------------------
    // Admin notices
    // -------------------------------------------------------------------------

    public static function admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'jb3' ) === false ) {
            return;
        }
        if ( ! get_option( 'jb3_login_page_id' ) || ! get_option( 'jb3_profile_page_id' ) || ! get_option( 'jb3_dashboard_page_id' ) ) {
            echo '<div class="notice notice-warning"><p>'
                . wp_kses(
                    sprintf(
                        __( 'JB3 Members: Falta configurar las páginas del plugin. <a href="%s">Ir a Configuración</a>.', 'jb3-members' ),
                        esc_url( admin_url( 'admin.php?page=jb3-settings' ) )
                    ),
                    [ 'a' => [ 'href' => [] ] ]
                )
                . '</p></div>';
        }
    }

    // -------------------------------------------------------------------------
    // Representatives: early form / action processing (admin_init)
    // -------------------------------------------------------------------------

    public static function handle_rep_actions() {
        if ( empty( $_GET['page'] ) || 'jb3-members' !== $_GET['page'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );
        $uid    = get_current_user_id();

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['jb3_rep_nonce'] ) ) {

            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_rep_nonce'] ) ), 'jb3_rep_save' ) ) {
                wp_die( esc_html__( 'Verificación de seguridad fallida.', 'jb3-members' ) );
            }

            $rep_id = absint( $_POST['rep_id'] ?? 0 );

            $data = [
                'country_code' => strtoupper( sanitize_text_field( wp_unslash( $_POST['country_code'] ?? '' ) ) ),
                'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name']  ?? '' ) ),
                'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name']   ?? '' ) ),
                'email'        => sanitize_email(      wp_unslash( $_POST['email']       ?? '' ) ),
                'phone'        => jb3_sanitize_phone( wp_unslash( $_POST['phone']        ?? '' ) ),
                'is_default'   => ! empty( $_POST['is_default'] ) ? 1 : 0,
            ];

            $errors = self::validate_rep( $data );

            if ( ! empty( $errors ) ) {
                set_transient( 'jb3_rep_errors_' . $uid, $errors, 60 );
                set_transient( 'jb3_rep_data_'   . $uid, $data,   60 );

                $back = add_query_arg(
                    array_filter( [
                        'page'   => 'jb3-members',
                        'action' => $action,
                        'rep_id' => $rep_id ?: null,
                    ] ),
                    admin_url( 'admin.php' )
                );
                wp_safe_redirect( $back );
                exit;
            }

            $result = $rep_id
                ? JB3_Representatives::update( $rep_id, $data )
                : JB3_Representatives::insert( $data );

            if ( is_wp_error( $result ) ) {
                set_transient( 'jb3_rep_errors_' . $uid, [ $result->get_error_message() ], 60 );
                set_transient( 'jb3_rep_data_'   . $uid, $data, 60 );

                $back = add_query_arg(
                    array_filter( [
                        'page'   => 'jb3-members',
                        'action' => $action,
                        'rep_id' => $rep_id ?: null,
                    ] ),
                    admin_url( 'admin.php' )
                );
                wp_safe_redirect( $back );
                exit;
            }

            wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-members', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( 'delete' === $action ) {
            $rep_id = absint( $_GET['rep_id'] ?? 0 );
            if ( ! $rep_id ) {
                return;
            }

            check_admin_referer( 'jb3_delete_rep_' . $rep_id );

            $result = JB3_Representatives::delete( $rep_id );

            if ( is_wp_error( $result ) ) {
                set_transient( 'jb3_delete_error_' . $uid, $result->get_error_message(), 60 );
                wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-members', 'delete_error' => '1' ], admin_url( 'admin.php' ) ) );
            } else {
                wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-members', 'deleted' => '1' ], admin_url( 'admin.php' ) ) );
            }
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Representatives page
    // -------------------------------------------------------------------------

    public static function page_representatives() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tenés permiso para acceder a esta página.', 'jb3-members' ) );
        }

        $action  = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );
        $rep_id  = absint( $_GET['rep_id'] ?? 0 );
        $uid     = get_current_user_id();

        $errors   = (array) get_transient( 'jb3_rep_errors_' . $uid );
        $old_data = (array) get_transient( 'jb3_rep_data_'   . $uid );
        delete_transient( 'jb3_rep_errors_' . $uid );
        delete_transient( 'jb3_rep_data_'   . $uid );

        $delete_error = get_transient( 'jb3_delete_error_' . $uid );
        delete_transient( 'jb3_delete_error_' . $uid );

        $message = '';
        $error   = '';

        if ( ! empty( $_GET['saved'] ) ) {
            $message = __( 'Representante guardado correctamente.', 'jb3-members' );
        } elseif ( ! empty( $_GET['deleted'] ) ) {
            $message = __( 'Representante eliminado.', 'jb3-members' );
        }

        if ( ! empty( $errors ) ) {
            $error = implode( '<br>', array_map( 'esc_html', $errors ) );
        } elseif ( $delete_error ) {
            $error = esc_html( $delete_error );
        }

        $rep = null;
        if ( 'edit' === $action && $rep_id ) {
            $rep = JB3_Representatives::get( $rep_id );
            if ( $rep && ! empty( $old_data ) ) {
                foreach ( $old_data as $key => $val ) {
                    $rep->$key = $val;
                }
            }
        } elseif ( 'add' === $action && ! empty( $old_data ) ) {
            $rep = (object) array_merge( [
                'id' => 0, 'country_code' => '', 'first_name' => '', 'last_name' => '',
                'email' => '', 'phone' => '', 'is_default' => 0,
            ], $old_data );
        }

        $reps = JB3_Representatives::get_all();
        ?>
        <div class="wrap jb3-admin">
            <h1><?php esc_html_e( 'Representantes por País', 'jb3-members' ); ?>
                <?php if ( 'list' === $action ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'jb3-members', 'action' => 'add' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
                        <?php esc_html_e( '+ Agregar representante', 'jb3-members' ); ?>
                    </a>
                <?php endif; ?>
            </h1>

            <?php if ( $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <?php if ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo wp_kses( $error, [ 'br' => [] ] ); ?></p></div>
            <?php endif; ?>

            <?php if ( in_array( $action, [ 'add', 'edit' ], true ) ) : ?>
                <?php self::render_rep_form( $rep, $action ); ?>
            <?php else : ?>
                <?php self::render_rep_list( $reps ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private static function validate_rep( $data ) {
        $errors = [];
        if ( empty( $data['first_name'] ) ) {
            $errors[] = __( 'El nombre es obligatorio.', 'jb3-members' );
        }
        if ( empty( $data['last_name'] ) ) {
            $errors[] = __( 'El apellido es obligatorio.', 'jb3-members' );
        }
        if ( ! is_email( $data['email'] ) ) {
            $errors[] = __( 'El email no es válido.', 'jb3-members' );
        }
        if ( empty( $data['is_default'] ) && ! array_key_exists( $data['country_code'], jb3_countries() ) ) {
            $errors[] = __( 'El país seleccionado no es válido.', 'jb3-members' );
        }
        return $errors;
    }

    // -------------------------------------------------------------------------
    // Representatives form render
    // -------------------------------------------------------------------------

    private static function render_rep_form( $rep = null, $action = 'add' ) {
        $is_edit  = ( 'edit' === $action && $rep && ! empty( $rep->id ) );
        $form_url = add_query_arg(
            array_filter( [
                'page'   => 'jb3-members',
                'action' => $action,
                'rep_id' => $is_edit ? (int) $rep->id : null,
            ] ),
            admin_url( 'admin.php' )
        );
        ?>
        <h2><?php echo $is_edit
            ? esc_html__( 'Editar Representante', 'jb3-members' )
            : esc_html__( 'Agregar Representante', 'jb3-members' ); ?>
        </h2>
        <form method="post" action="<?php echo esc_url( $form_url ); ?>" class="jb3-admin-form">
            <?php wp_nonce_field( 'jb3_rep_save', 'jb3_rep_nonce' ); ?>
            <?php if ( $is_edit ) : ?>
                <input type="hidden" name="rep_id" value="<?php echo (int) $rep->id; ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="nb_country"><?php esc_html_e( 'País', 'jb3-members' ); ?></label></th>
                    <td>
                        <select id="nb_country" name="country_code">
                            <option value=""><?php esc_html_e( '-- Representante por defecto --', 'jb3-members' ); ?></option>
                            <?php foreach ( jb3_countries() as $code => $name ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $rep->country_code ?? '', $code ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Dejá vacío para que sea el representante por defecto.', 'jb3-members' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="nb_first_name"><?php esc_html_e( 'Nombre *', 'jb3-members' ); ?></label></th>
                    <td><input type="text" id="nb_first_name" name="first_name" value="<?php echo esc_attr( $rep->first_name ?? '' ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="nb_last_name"><?php esc_html_e( 'Apellido *', 'jb3-members' ); ?></label></th>
                    <td><input type="text" id="nb_last_name" name="last_name" value="<?php echo esc_attr( $rep->last_name ?? '' ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="nb_email"><?php esc_html_e( 'Email *', 'jb3-members' ); ?></label></th>
                    <td><input type="email" id="nb_email" name="email" value="<?php echo esc_attr( $rep->email ?? '' ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="nb_phone"><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?></label></th>
                    <td><input type="tel" id="nb_phone" name="phone" value="<?php echo esc_attr( $rep->phone ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <?php if ( $is_edit && ! empty( $rep->is_default ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Por defecto', 'jb3-members' ); ?></th>
                    <td>
                        <input type="hidden" name="is_default" value="1">
                        <span class="dashicons dashicons-yes" style="color:#2e7d32;"></span>
                        <?php esc_html_e( 'Este es el representante por defecto (no se puede quitar).', 'jb3-members' ); ?>
                    </td>
                </tr>
                <?php else : ?>
                <tr>
                    <th><?php esc_html_e( 'Por defecto', 'jb3-members' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_default" value="1" <?php checked( ! empty( $rep->is_default ) ); ?>>
                            <?php esc_html_e( 'Marcar como representante por defecto', 'jb3-members' ); ?>
                        </label>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <p>
                <?php submit_button(
                    $is_edit ? __( 'Guardar cambios', 'jb3-members' ) : __( 'Agregar representante', 'jb3-members' ),
                    'primary', 'submit', false
                ); ?>
                &nbsp;
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jb3-members' ) ); ?>" class="button">
                    <?php esc_html_e( 'Cancelar', 'jb3-members' ); ?>
                </a>
            </p>
        </form>
        <?php
    }

    // -------------------------------------------------------------------------
    // Representatives list render
    // -------------------------------------------------------------------------

    private static function render_rep_list( $reps ) {
        $countries = jb3_countries();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'País', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Nombre', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Apellido', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Tipo', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'jb3-members' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $reps ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No hay representantes cargados.', 'jb3-members' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $reps as $rep ) : ?>
                        <tr>
                            <td><?php echo $rep->is_default
                                ? '<em>' . esc_html__( 'Por defecto', 'jb3-members' ) . '</em>'
                                : esc_html( $countries[ $rep->country_code ] ?? $rep->country_code ); ?>
                            </td>
                            <td><?php echo esc_html( $rep->first_name ); ?></td>
                            <td><?php echo esc_html( $rep->last_name ); ?></td>
                            <td><?php echo esc_html( $rep->email ); ?></td>
                            <td><?php echo esc_html( $rep->phone ); ?></td>
                            <td><?php echo $rep->is_default
                                ? '<strong>' . esc_html__( 'Default', 'jb3-members' ) . '</strong>'
                                : esc_html__( 'País', 'jb3-members' ); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg(
                                    [ 'page' => 'jb3-members', 'action' => 'edit', 'rep_id' => $rep->id ],
                                    admin_url( 'admin.php' )
                                ) ); ?>"><?php esc_html_e( 'Editar', 'jb3-members' ); ?></a>

                                <?php if ( ! $rep->is_default ) : ?>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        add_query_arg(
                                            [ 'page' => 'jb3-members', 'action' => 'delete', 'rep_id' => $rep->id ],
                                            admin_url( 'admin.php' )
                                        ),
                                        'jb3_delete_rep_' . $rep->id
                                    ) ); ?>"
                                       onclick="return confirm('<?php esc_attr_e( '¿Confirmas que querés eliminar este representante?', 'jb3-members' ); ?>')"
                                       style="color:#a00;">
                                        <?php esc_html_e( 'Eliminar', 'jb3-members' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    // -------------------------------------------------------------------------
    // Settings page
    // -------------------------------------------------------------------------

    public static function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }
        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
        $sel   = fn( $opt, $page ) => selected( (int) get_option( $opt ), $page->ID, false );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Configuración — JB3 Members', 'jb3-members' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'jb3_settings' ); ?>

                <!-- Páginas -->
                <h2><?php esc_html_e( 'Páginas del plugin', 'jb3-members' ); ?></h2>
                <table class="form-table">
                    <?php foreach ( [
                        [ 'jb3_login_page_id',     __( 'Página de Login',                 'jb3-members' ), __( 'Debe contener el shortcode [jb3_login].',   'jb3-members' ) ],
                        [ 'jb3_profile_page_id',   __( 'Página de Perfil / Actualización', 'jb3-members' ), __( 'Debe contener el shortcode [jb3_profile].', 'jb3-members' ) ],
                        [ 'jb3_dashboard_page_id', __( 'Página de Inicio (post-login)',    'jb3-members' ), __( 'Adónde se redirige al usuario después de loguearse.', 'jb3-members' ) ],
                    ] as [ $opt, $label, $desc ] ) : ?>
                    <tr>
                        <th><label><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <select name="<?php echo esc_attr( $opt ); ?>">
                                <option value=""><?php esc_html_e( '— Seleccionar página —', 'jb3-members' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo (int) $page->ID; ?>" <?php echo $sel( $opt, $page ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html( $desc ); ?></p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <!-- Shortcodes (informativo, sin campos) -->
                <hr>
                <h2><?php esc_html_e( 'Shortcodes disponibles', 'jb3-members' ); ?></h2>
                <table class="widefat" style="max-width:600px;margin-bottom:24px;">
                    <tr><td><code>[jb3_register]</code></td><td><?php esc_html_e( 'Formulario de registro', 'jb3-members' ); ?></td></tr>
                    <tr><td><code>[jb3_login]</code></td><td><?php esc_html_e( 'Formulario de login (magic link)', 'jb3-members' ); ?></td></tr>
                    <tr><td><code>[jb3_profile]</code></td><td><?php esc_html_e( 'Formulario de actualización de datos', 'jb3-members' ); ?></td></tr>
                    <tr><td><code>[jb3_logout]</code></td><td><?php esc_html_e( 'Cierra la sesión y redirige al inicio', 'jb3-members' ); ?></td></tr>
                </table>

                <!-- Mailchimp -->
                <hr>
                <h2><?php esc_html_e( 'Mailchimp', 'jb3-members' ); ?></h2>
                <?php if ( get_option( 'jb3_mc_api_key' ) ) : ?>
                <div class="notice notice-info inline" style="margin:0 0 16px;">
                    <p>
                        <strong><?php esc_html_e( 'URL del Webhook (Mailchimp → WordPress):', 'jb3-members' ); ?></strong><br>
                        <code style="user-select:all;"><?php echo esc_url( JB3_Mailchimp::webhook_url() ); ?></code><br>
                        <span class="description"><?php esc_html_e( 'Copiá esta URL en Mailchimp → Audience → Settings → Webhooks. Eventos: Unsubscribes y Subscribe.', 'jb3-members' ); ?></span>
                    </p>
                </div>
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="jb3_mc_api_key"><?php esc_html_e( 'API Key', 'jb3-members' ); ?></label></th>
                        <td>
                            <input type="text" id="jb3_mc_api_key" name="jb3_mc_api_key" value="<?php echo esc_attr( get_option( 'jb3_mc_api_key' ) ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Ej: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us18', 'jb3-members' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="jb3_mc_list_id"><?php esc_html_e( 'Audience ID', 'jb3-members' ); ?></label></th>
                        <td><input type="text" id="jb3_mc_list_id" name="jb3_mc_list_id" value="<?php echo esc_attr( get_option( 'jb3_mc_list_id' ) ); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <?php if ( get_option( 'jb3_mc_api_key' ) && get_option( 'jb3_mc_list_id' ) ) : ?>
                <div style="margin:0 0 20px;">
                    <button type="button" id="jb3-refresh-interests" class="button">
                        Actualizar grupos desde Mailchimp
                    </button>
                    <span id="jb3-refresh-msg" style="margin-left:10px;display:none;"></span>
                </div>
                <script>
                document.getElementById('jb3-refresh-interests').addEventListener('click', function () {
                    var btn = this;
                    var msg = document.getElementById('jb3-refresh-msg');
                    btn.disabled = true;
                    btn.textContent = 'Actualizando…';
                    msg.style.display = 'none';
                    var fd = new FormData();
                    fd.append('action', 'jb3_fetch_mc_interests');
                    fd.append('_ajax_nonce', '<?php echo esc_js( wp_create_nonce( 'jb3_fetch_interests' ) ); ?>');
                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {
                            btn.disabled = false;
                            btn.textContent = 'Actualizar grupos desde Mailchimp';
                            if (!resp.success) {
                                msg.style.display = 'inline';
                                msg.style.color = '#c0392b';
                                msg.textContent = resp.data;
                            } else {
                                location.reload();
                            }
                        })
                        .catch(function (e) {
                            btn.disabled = false;
                            btn.textContent = 'Actualizar grupos desde Mailchimp';
                            msg.style.display = 'inline';
                            msg.style.color = '#c0392b';
                            msg.textContent = 'Error: ' + e.message;
                        });
                });
                </script>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Users list columns
    // -------------------------------------------------------------------------

    public static function user_columns( $columns ) {
        $columns['jb3_status']  = __( 'Estado JB3', 'jb3-members' );
        $columns['jb3_country'] = __( 'País', 'jb3-members' );
        $columns['jb3_company'] = __( 'Empresa', 'jb3-members' );
        return $columns;
    }

    public static function user_column_value( $value, $column, $user_id ) {
        if ( ! jb3_is_member( $user_id ) ) {
            return '—';
        }
        switch ( $column ) {
            case 'jb3_status':
                $status = jb3_get_status( $user_id );
                $labels = [
                    'pending'  => '<span style="color:#e65100;font-weight:600;">' . esc_html__( 'Pendiente', 'jb3-members' ) . '</span>',
                    'approved' => '<span style="color:#2e7d32;font-weight:600;">' . esc_html__( 'Aprobado',  'jb3-members' ) . '</span>',
                ];
                return $labels[ $status ] ?? esc_html( $status );

            case 'jb3_country':
                $code = get_user_meta( $user_id, 'jb3_country', true );
                $all  = jb3_countries();
                return esc_html( $all[ $code ] ?? $code );

            case 'jb3_company':
                return esc_html( get_user_meta( $user_id, 'jb3_company', true ) );
        }
        return $value;
    }

    // -------------------------------------------------------------------------
    // Users list row actions
    // -------------------------------------------------------------------------

    public static function user_row_actions( $actions, $user ) {
        if ( ! jb3_is_member( $user->ID ) ) {
            return $actions;
        }

        $approve_url = wp_nonce_url(
            add_query_arg( [ 'action' => 'jb3_approve_user', 'user_id' => $user->ID ], admin_url( 'admin-ajax.php' ) ),
            'jb3_approve_' . $user->ID
        );
        $logout_url = wp_nonce_url(
            add_query_arg( [ 'action' => 'jb3_logout_user', 'user_id' => $user->ID ], admin_url( 'admin-ajax.php' ) ),
            'jb3_logout_' . $user->ID
        );

        if ( ! jb3_is_approved( $user->ID ) ) {
            $actions['jb3_approve'] = '<a href="' . esc_url( $approve_url ) . '" style="color:#2e7d32;font-weight:600;">'
                . esc_html__( 'Aprobar', 'jb3-members' ) . '</a>';
        }

        $actions['jb3_logout'] = '<a href="' . esc_url( $logout_url ) . '">'
            . esc_html__( 'Desloguear', 'jb3-members' ) . '</a>';

        return $actions;
    }

    // -------------------------------------------------------------------------
    // AJAX: approve user
    // -------------------------------------------------------------------------

    public static function ajax_approve_user() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'jb3_approve_' . $user_id );

        if ( ! $user_id || ! jb3_is_member( $user_id ) ) {
            wp_die( esc_html__( 'Usuario no válido.', 'jb3-members' ) );
        }

        if ( ! jb3_is_approved( $user_id ) ) {
            update_user_meta( $user_id, 'jb3_status',      'approved' );
            update_user_meta( $user_id, 'jb3_approved_at', current_time( 'mysql' ) );
            JB3_Email::send_user_approved( $user_id );
            do_action( 'jb3_sync_to_mailchimp', $user_id );
        }

        wp_safe_redirect( add_query_arg( [ 'approved' => '1' ], admin_url( 'users.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: force logout
    // -------------------------------------------------------------------------

    public static function ajax_logout_user() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        check_admin_referer( 'jb3_logout_' . $user_id );

        if ( ! $user_id || ! jb3_is_member( $user_id ) ) {
            wp_die( esc_html__( 'Usuario no válido.', 'jb3-members' ) );
        }

        JB3_Magic_Link::force_logout( $user_id );

        wp_safe_redirect( add_query_arg( [ 'loggedout' => '1' ], admin_url( 'users.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: fetch Mailchimp interest groups
    // -------------------------------------------------------------------------

    public static function ajax_fetch_mc_interests() {
        check_ajax_referer( 'jb3_fetch_interests' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }
        delete_transient( 'jb3_mc_interests_cache' );
        delete_transient( 'jb3_mc_nl_interests_cache' );
        $groups = JB3_Mailchimp::fetch_interest_groups();
        if ( is_wp_error( $groups ) ) {
            wp_send_json_error( $groups->get_error_message() );
        }
        // Reconstruir ambos cachés separados (general vs newsletters).
        $general = [];
        $nl      = [];
        foreach ( $groups as $cat ) {
            $is_nl = stripos( $cat['title'], 'newsletter' ) !== false;
            foreach ( $cat['interests'] as $interest ) {
                if ( $is_nl ) {
                    $nl[ $interest['id'] ] = $interest['name'];
                } else {
                    $general[ $interest['id'] ] = $interest['name'];
                }
            }
        }
        set_transient( 'jb3_mc_interests_cache',    $general, HOUR_IN_SECONDS );
        set_transient( 'jb3_mc_nl_interests_cache', $nl,      HOUR_IN_SECONDS );
        wp_send_json_success( $groups );
    }

    // -------------------------------------------------------------------------
    // User profile: render JB3 fields
    // -------------------------------------------------------------------------

    public static function render_user_profile( $user ) {
        if ( ! jb3_is_member( $user->ID ) && ! user_can( $user->ID, 'manage_options' ) ) {
            return;
        }

        $status      = jb3_get_status( $user->ID );
        $company     = get_user_meta( $user->ID, 'jb3_company',    true );
        $job_title   = get_user_meta( $user->ID, 'jb3_job_title',  true );
        $phone       = get_user_meta( $user->ID, 'jb3_phone',      true );
        $country     = get_user_meta( $user->ID, 'jb3_country',    true );
        $sector      = get_user_meta( $user->ID, 'jb3_sector',     true );
        $newsletter     = (bool) get_user_meta( $user->ID, 'jb3_newsletter',          true );
        $interests      = (array) get_user_meta( $user->ID, 'jb3_interests',           true );
        $nl_countries   = (array) get_user_meta( $user->ID, 'jb3_newsletter_countries', true );
        $registered  = get_user_meta( $user->ID, 'jb3_registered_at', true );
        $approved_at = get_user_meta( $user->ID, 'jb3_approved_at',   true );

        wp_nonce_field( 'jb3_save_profile_' . $user->ID, 'jb3_profile_nonce' );
        ?>
        <h2><?php esc_html_e( 'Datos JB3 Members', 'jb3-members' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="jb3_status"><?php esc_html_e( 'Estado', 'jb3-members' ); ?></label></th>
                <td>
                    <select id="jb3_status" name="jb3_status">
                        <option value="pending"  <?php selected( $status, 'pending'  ); ?>><?php esc_html_e( 'Pendiente', 'jb3-members' ); ?></option>
                        <option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Aprobado',  'jb3-members' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="jb3_company"><?php esc_html_e( 'Empresa', 'jb3-members' ); ?></label></th>
                <td><input type="text" id="jb3_company" name="jb3_company" value="<?php echo esc_attr( $company ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="jb3_job_title"><?php esc_html_e( 'Cargo', 'jb3-members' ); ?></label></th>
                <td>
                    <select id="jb3_job_title" name="jb3_job_title">
                        <option value=""><?php esc_html_e( '— Seleccionar —', 'jb3-members' ); ?></option>
                        <?php foreach ( jb3_job_titles() as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $job_title, $t ); ?>><?php echo esc_html( $t ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="jb3_phone"><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?></label></th>
                <td><input type="tel" id="jb3_phone" name="jb3_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="jb3_country"><?php esc_html_e( 'País', 'jb3-members' ); ?></label></th>
                <td>
                    <select id="jb3_country" name="jb3_country">
                        <option value=""><?php esc_html_e( '— Seleccionar —', 'jb3-members' ); ?></option>
                        <?php foreach ( jb3_countries() as $code => $name ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="jb3_sector"><?php esc_html_e( 'Sector / Industria', 'jb3-members' ); ?></label></th>
                <td>
                    <select id="jb3_sector" name="jb3_sector">
                        <option value=""><?php esc_html_e( '— Seleccionar —', 'jb3-members' ); ?></option>
                        <?php foreach ( jb3_sectors() as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $sector, $s ); ?>><?php echo esc_html( $s ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Newsletter', 'jb3-members' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="jb3_newsletter" value="1" <?php checked( $newsletter ); ?>>
                        <?php esc_html_e( 'Suscripto al newsletter', 'jb3-members' ); ?>
                    </label>
                </td>
            </tr>
            <?php
            $nl_interests = JB3_Mailchimp::get_newsletter_interests();
            if ( ! empty( $nl_interests ) ) :
            ?>
            <tr>
                <th><?php esc_html_e( 'Newsletters por País', 'jb3-members' ); ?></th>
                <td>
                    <?php foreach ( $nl_interests as $id => $name ) : ?>
                        <label style="display:inline-block;margin-right:20px;margin-bottom:6px;">
                            <input type="checkbox" name="jb3_newsletter_countries[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $nl_countries, true ) ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Solo asignable por el admin.', 'jb3-members' ); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Intereses', 'jb3-members' ); ?></th>
                <td>
                    <?php foreach ( jb3_interests() as $key => $label ) : ?>
                        <label style="display:inline-block;margin-right:20px;margin-bottom:6px;">
                            <input type="checkbox" name="jb3_interests[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $interests, true ) ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Registrado el', 'jb3-members' ); ?></th>
                <td><span><?php echo $registered ? esc_html( $registered ) : '—'; ?></span></td>
            </tr>
            <?php if ( $approved_at ) : ?>
            <tr>
                <th><?php esc_html_e( 'Aprobado el', 'jb3-members' ); ?></th>
                <td><span><?php echo esc_html( $approved_at ); ?></span></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    // -------------------------------------------------------------------------
    // User profile: save JB3 fields
    // -------------------------------------------------------------------------

    public static function save_user_profile( $user_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['jb3_profile_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_profile_nonce'] ) ), 'jb3_save_profile_' . $user_id ) ) {
            return;
        }
        if ( ! jb3_is_member( $user_id ) ) {
            if ( user_can( $user_id, 'manage_options' ) ) {
                $u = new WP_User( $user_id );
                $u->add_role( 'jb3_member' );
                update_user_meta( $user_id, 'jb3_status', 'approved' );
            } else {
                return;
            }
        }

        $old_status = get_user_meta( $user_id, 'jb3_status', true ) ?: 'pending';
        $status     = in_array( $_POST['jb3_status'] ?? '', [ 'pending', 'approved' ], true )
                     ? $_POST['jb3_status'] : 'pending';
        $company   = sanitize_text_field( wp_unslash( $_POST['jb3_company']   ?? '' ) );
        $job_title = sanitize_text_field( wp_unslash( $_POST['jb3_job_title'] ?? '' ) );
        $phone     = jb3_sanitize_phone( wp_unslash( $_POST['jb3_phone']      ?? '' ) );
        $country   = strtoupper( sanitize_text_field( wp_unslash( $_POST['jb3_country'] ?? '' ) ) );
        $sector    = sanitize_text_field( wp_unslash( $_POST['jb3_sector']    ?? '' ) );
        $newsletter = ! empty( $_POST['jb3_newsletter'] ) ? 1 : 0;
        $raw        = isset( $_POST['jb3_interests'] ) && is_array( $_POST['jb3_interests'] )
                      ? $_POST['jb3_interests'] : [];
        $interests  = array_values( array_filter(
            array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) ),
            fn( $i ) => array_key_exists( $i, jb3_interests() )
        ) );
        $raw_nlc      = isset( $_POST['jb3_newsletter_countries'] ) && is_array( $_POST['jb3_newsletter_countries'] )
                        ? $_POST['jb3_newsletter_countries'] : [];
        $valid_nl_ids = array_keys( JB3_Mailchimp::get_newsletter_interests() );
        $nl_countries = array_values( array_filter(
            array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw_nlc ) ),
            fn( $id ) => in_array( $id, $valid_nl_ids, true )
        ) );

        update_user_meta( $user_id, 'jb3_status',     $status );
        update_user_meta( $user_id, 'jb3_company',    $company );
        update_user_meta( $user_id, 'jb3_job_title',  $job_title );
        update_user_meta( $user_id, 'jb3_phone',      $phone );
        update_user_meta( $user_id, 'jb3_country',    $country );
        update_user_meta( $user_id, 'jb3_sector',     $sector );
        update_user_meta( $user_id, 'jb3_newsletter',          $newsletter );
        update_user_meta( $user_id, 'jb3_interests',           $interests );
        update_user_meta( $user_id, 'jb3_newsletter_countries', $nl_countries );

        if ( $status === 'approved' && ! get_user_meta( $user_id, 'jb3_approved_at', true ) ) {
            update_user_meta( $user_id, 'jb3_approved_at', current_time( 'mysql' ) );
        }

        if ( $old_status === 'approved' && $status === 'pending' ) {
            $user_data = get_userdata( $user_id );
            if ( $user_data ) {
                JB3_Mailchimp::archive_contact( $user_data->user_email );
            }
        } else {
            do_action( 'jb3_sync_to_mailchimp', $user_id );
        }
    }

    // -------------------------------------------------------------------------
    // Export: handle CSV download (admin_init)
    // -------------------------------------------------------------------------

    public static function handle_export() {
        if ( empty( $_GET['page'] ) || 'jb3-export' !== $_GET['page'] ) {
            return;
        }
        if ( empty( $_GET['action'] ) || 'download' !== $_GET['action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        check_admin_referer( 'jb3_export_users' );

        $interests_map = jb3_interests();
        $nl_interests  = JB3_Mailchimp::get_newsletter_interests();

        $users = get_users( [
            'role'    => 'jb3_member',
            'number'  => -1,
            'orderby' => 'ID',
            'order'   => 'ASC',
        ] );

        $filename = 'jb3-members-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.

        fputcsv( $out, [
            'ID', 'Nombre', 'Apellido', 'Email', 'Estado', 'País', 'Empresa',
            'Cargo', 'Teléfono', 'Sector', 'Newsletter', 'Newsletters', 'Intereses',
            'Fecha Registro WP', 'Fecha Registro JB3', 'Fecha Aprobación',
        ] );

        foreach ( $users as $user ) {
            $uid = $user->ID;

            // Excluir administradores generales.
            if ( user_can( $uid, 'manage_options' ) ) {
                continue;
            }

            $status     = jb3_get_status( $uid );
            $raw_int    = (array) get_user_meta( $uid, 'jb3_interests', true );
            $int_labels = array_map( fn( $k ) => $interests_map[ $k ] ?? $k, array_filter( $raw_int ) );

            $raw_nlc    = (array) get_user_meta( $uid, 'jb3_newsletter_countries', true );
            $nlc_labels = array_map( fn( $id ) => $nl_interests[ $id ] ?? $id, array_filter( $raw_nlc ) );

            fputcsv( $out, [
                $uid,
                $user->first_name,
                $user->last_name,
                $user->user_email,
                $status === 'approved' ? 'Aprobado' : 'Pendiente',
                get_user_meta( $uid, 'jb3_country', true ),
                get_user_meta( $uid, 'jb3_company',   true ),
                get_user_meta( $uid, 'jb3_job_title', true ),
                get_user_meta( $uid, 'jb3_phone',     true ),
                get_user_meta( $uid, 'jb3_sector',    true ),
                get_user_meta( $uid, 'jb3_newsletter', true ) ? 'Sí' : 'No',
                implode( ', ', $nlc_labels ),
                implode( ', ', $int_labels ),
                $user->user_registered,
                get_user_meta( $uid, 'jb3_registered_at', true ),
                get_user_meta( $uid, 'jb3_approved_at',   true ),
            ] );
        }

        fclose( $out );
        exit;
    }

    // -------------------------------------------------------------------------
    // Export: admin page
    // -------------------------------------------------------------------------

    public static function page_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $total = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );

        $export_url = wp_nonce_url(
            add_query_arg( [ 'page' => 'jb3-export', 'action' => 'download' ], admin_url( 'admin.php' ) ),
            'jb3_export_users'
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Exportar Usuarios', 'jb3-members' ); ?></h1>
            <p>
                <?php
                printf(
                    esc_html( _n( 'Hay %d miembro registrado.', 'Hay %d miembros registrados.', $total, 'jb3-members' ) ),
                    $total
                );
                ?>
            </p>
            <p><?php esc_html_e( 'El archivo CSV incluye todos los campos del perfil JB3: estado, país, empresa, cargo, teléfono, sector, newsletter, intereses y fechas.', 'jb3-members' ); ?></p>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary button-large">
                <?php esc_html_e( 'Descargar CSV', 'jb3-members' ); ?>
            </a>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Import: handle CSV upload (admin_init)
    // -------------------------------------------------------------------------

    public static function handle_import() {
        if ( empty( $_GET['page'] ) || 'jb3-import' !== $_GET['page'] ) {
            return;
        }
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['jb3_import_nonce'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jb3_import_nonce'] ) ), 'jb3_import_users' ) ) {
            wp_die( esc_html__( 'Verificación de seguridad fallida.', 'jb3-members' ) );
        }

        $uid     = get_current_user_id();
        $results = [ 'created' => 0, 'updated' => 0, 'errors' => [] ];

        $upload = $_FILES['jb3_csv'] ?? null;

        if ( ! $upload || $upload['error'] !== UPLOAD_ERR_OK ) {
            $results['errors'][] = __( 'Error al subir el archivo.', 'jb3-members' );
            self::redirect_after_import( $results, $uid );
        }

        if ( strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) ) !== 'csv' ) {
            $results['errors'][] = __( 'El archivo debe tener extensión .csv', 'jb3-members' );
            self::redirect_after_import( $results, $uid );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $upload['tmp_name'], 'r' );
        if ( ! $handle ) {
            $results['errors'][] = __( 'No se pudo leer el archivo.', 'jb3-members' );
            self::redirect_after_import( $results, $uid );
        }

        $first_line = fgets( $handle );
        if ( $first_line === false || trim( $first_line ) === '' ) {
            fclose( $handle );
            $results['errors'][] = __( 'El archivo está vacío.', 'jb3-members' );
            self::redirect_after_import( $results, $uid );
        }

        $first_line = ltrim( $first_line, "\xEF\xBB\xBF" ); // Strip UTF-8 BOM.
        $delimiter  = substr_count( $first_line, ';' ) >= substr_count( $first_line, ',' ) ? ';' : ',';
        $headers    = array_map( 'trim', str_getcsv( $first_line, $delimiter ) );

        if ( array_search( 'Email', $headers, true ) === false ) {
            fclose( $handle );
            $results['errors'][] = __( 'El CSV debe tener una columna "Email".', 'jb3-members' );
            self::redirect_after_import( $results, $uid );
        }

        $col_map = [
            'Nombre'             => 'first_name',
            'Apellido'           => 'last_name',
            'Estado'             => 'jb3_status',
            'País'               => 'jb3_country',
            'Empresa'            => 'jb3_company',
            'Cargo'              => 'jb3_job_title',
            'Teléfono'           => 'jb3_phone',
            'Sector'             => 'jb3_sector',
            'Newsletter'               => 'jb3_newsletter',
            'Newsletters'              => 'jb3_newsletter_countries',
            'Intereses'                => 'jb3_interests',
            'Fecha Registro JB3'       => 'jb3_registered_at',
            'Fecha Aprobación'         => 'jb3_approved_at',
            'first_name'               => 'first_name',
            'last_name'                => 'last_name',
            'jb3_status'               => 'jb3_status',
            'jb3_country'              => 'jb3_country',
            'jb3_company'              => 'jb3_company',
            'jb3_job_title'            => 'jb3_job_title',
            'jb3_phone'                => 'jb3_phone',
            'jb3_sector'               => 'jb3_sector',
            'jb3_newsletter'           => 'jb3_newsletter',
            'jb3_newsletter_countries' => 'jb3_newsletter_countries',
            'jb3_interests'            => 'jb3_interests',
            'jb3_registered_at'        => 'jb3_registered_at',
            'jb3_approved_at'          => 'jb3_approved_at',
        ];

        $countries_by_name  = array_flip( jb3_countries() );
        $interests_by_label = array_flip( jb3_interests() );
        $nl_interests       = JB3_Mailchimp::get_newsletter_interests();
        $nl_by_name         = array_flip( $nl_interests );

        $row_num = 1;
        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $row_num++;

            $data = [];
            foreach ( $headers as $i => $col ) {
                $data[ $col ] = isset( $row[ $i ] ) ? trim( $row[ $i ] ) : '';
            }

            $email = sanitize_email( $data['Email'] ?? '' );
            if ( ! is_email( $email ) ) {
                $results['errors'][] = sprintf(
                    __( 'Fila %1$d: email inválido «%2$s».', 'jb3-members' ),
                    $row_num, esc_html( $data['Email'] ?? '' )
                );
                continue;
            }

            $wp_fields   = [];
            $meta_fields = [];

            foreach ( $col_map as $csv_col => $field ) {
                if ( ! in_array( $csv_col, $headers, true ) ) {
                    continue;
                }
                $val = $data[ $csv_col ] ?? '';
                if ( $val === '' ) {
                    continue;
                }

                switch ( $field ) {
                    case 'first_name':
                    case 'last_name':
                        $wp_fields[ $field ] = sanitize_text_field( $val );
                        break;

                    case 'jb3_status':
                        $status_map = [ 'Aprobado' => 'approved', 'Pendiente' => 'pending' ];
                        $meta_fields[ $field ] = $status_map[ $val ] ?? ( in_array( $val, [ 'approved', 'pending' ], true ) ? $val : 'pending' );
                        break;

                    case 'jb3_country':
                        if ( isset( jb3_countries()[ strtoupper( $val ) ] ) ) {
                            $meta_fields[ $field ] = strtoupper( $val );
                        } elseif ( isset( $countries_by_name[ $val ] ) ) {
                            $meta_fields[ $field ] = $countries_by_name[ $val ];
                        }
                        break;

                    case 'jb3_newsletter':
                        $meta_fields[ $field ] = in_array( strtolower( $val ), [ 'sí', 'si', '1', 'yes' ], true ) ? 1 : 0;
                        break;

                    case 'jb3_newsletter_countries':
                        $parts = array_filter( array_map( 'trim', explode( ',', $val ) ) );
                        $ids   = [];
                        foreach ( $parts as $part ) {
                            if ( isset( $nl_by_name[ $part ] ) ) {
                                $ids[] = $nl_by_name[ $part ]; // nombre → ID Mailchimp
                            } elseif ( isset( $nl_interests[ $part ] ) ) {
                                $ids[] = $part; // ya es un ID válido
                            }
                        }
                        if ( ! empty( $ids ) ) {
                            $meta_fields[ $field ] = $ids;
                        }
                        break;

                    case 'jb3_interests':
                        $parts = array_filter( array_map( 'trim', explode( ',', $val ) ) );
                        $keys  = [];
                        foreach ( $parts as $part ) {
                            if ( isset( $interests_by_label[ $part ] ) ) {
                                $keys[] = $interests_by_label[ $part ];
                            } elseif ( isset( jb3_interests()[ $part ] ) ) {
                                $keys[] = $part;
                            }
                        }
                        if ( ! empty( $keys ) ) {
                            $meta_fields[ $field ] = $keys;
                        }
                        break;

                    case 'jb3_phone':
                        $meta_fields[ $field ] = jb3_sanitize_phone( $val );
                        break;

                    default:
                        $meta_fields[ $field ] = sanitize_text_field( $val );
                }
            }

            // Look up existing user: by ID column first, then by email.
            $existing   = null;
            $id_col_idx = array_search( 'ID', $headers, true );
            if ( $id_col_idx !== false ) {
                $csv_id = absint( $row[ $id_col_idx ] ?? 0 );
                if ( $csv_id ) {
                    $existing = get_userdata( $csv_id );
                }
            }
            if ( ! $existing ) {
                $existing = get_user_by( 'email', $email );
            }

            if ( $existing ) {
                if ( user_can( $existing->ID, 'manage_options' ) ) {
                    $results['errors'][] = sprintf(
                        __( 'Fila %1$d: "%2$s" es administrador/a y no puede modificarse desde la importación.', 'jb3-members' ),
                        $row_num, $email
                    );
                    continue;
                }

                $wp_fields['ID']         = $existing->ID;
                $wp_fields['user_email'] = $email;
                if ( isset( $wp_fields['first_name'] ) || isset( $wp_fields['last_name'] ) ) {
                    $wp_fields['display_name'] = trim(
                        ( $wp_fields['first_name'] ?? $existing->first_name ) . ' ' .
                        ( $wp_fields['last_name']  ?? $existing->last_name )
                    );
                }
                $result = wp_update_user( $wp_fields );
                if ( is_wp_error( $result ) ) {
                    $results['errors'][] = sprintf( __( 'Fila %1$d: %2$s', 'jb3-members' ), $row_num, $result->get_error_message() );
                    // Don't skip meta updates — phone, sector, etc. must still be saved.
                }

                // Newsletter: si el CSV intenta re-suscribir a alguien que optó por no recibir, se respeta su elección.
                if ( isset( $meta_fields['jb3_newsletter'] ) && $meta_fields['jb3_newsletter'] === 1 ) {
                    if ( (int) get_user_meta( $existing->ID, 'jb3_newsletter', true ) === 0 ) {
                        unset( $meta_fields['jb3_newsletter'] );
                    }
                }

                foreach ( $meta_fields as $key => $val ) {
                    update_user_meta( $existing->ID, $key, $val );
                }

                if ( ! isset( $meta_fields['jb3_status'] ) ) {
                    update_user_meta( $existing->ID, 'jb3_status', 'approved' );
                    if ( ! get_user_meta( $existing->ID, 'jb3_approved_at', true ) ) {
                        update_user_meta( $existing->ID, 'jb3_approved_at', current_time( 'mysql' ) );
                    }
                }

                do_action( 'jb3_sync_to_mailchimp', $existing->ID );
                $results['updated']++;

            } else {
                $user_data = array_merge( $wp_fields, [
                    'user_login' => 'jb3_tmp_' . wp_generate_password( 8, false ),
                    'user_email' => $email,
                    'user_pass'  => wp_generate_password( 20 ),
                    'role'       => 'jb3_member',
                ] );

                $user_id = wp_insert_user( $user_data );
                if ( is_wp_error( $user_id ) ) {
                    $results['errors'][] = sprintf( __( 'Fila %1$d: %2$s', 'jb3-members' ), $row_num, $user_id->get_error_message() );
                    continue;
                }

                // Set username to the WordPress-assigned ID.
                global $wpdb;
                $wpdb->update( $wpdb->users, [ 'user_login' => (string) $user_id ], [ 'ID' => $user_id ] );
                clean_user_cache( $user_id );
                update_user_meta( $user_id, 'nickname', (string) $user_id );

                foreach ( $meta_fields as $key => $val ) {
                    update_user_meta( $user_id, $key, $val );
                }

                if ( ! isset( $meta_fields['jb3_status'] ) ) {
                    update_user_meta( $user_id, 'jb3_status', 'approved' );
                }
                if ( ! isset( $meta_fields['jb3_approved_at'] ) ) {
                    update_user_meta( $user_id, 'jb3_approved_at', current_time( 'mysql' ) );
                }
                if ( ! isset( $meta_fields['jb3_newsletter'] ) ) {
                    update_user_meta( $user_id, 'jb3_newsletter', 1 );
                }
                if ( ! isset( $meta_fields['jb3_registered_at'] ) ) {
                    update_user_meta( $user_id, 'jb3_registered_at', current_time( 'mysql' ) );
                }

                do_action( 'jb3_sync_to_mailchimp', $user_id );
                $results['created']++;
            }
        }

        fclose( $handle );
        set_transient( 'jb3_import_results_' . $uid, $results, 60 );
        wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-import', 'imported' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function redirect_after_import( array $results, int $uid ): void {
        set_transient( 'jb3_import_results_' . $uid, $results, 60 );
        wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-import' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Sync Mailchimp page
    // -------------------------------------------------------------------------

    public static function page_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $has_mc = get_option( 'jb3_mc_api_key' ) && get_option( 'jb3_mc_list_id' );
        $state  = JB3_Sync::get_state();
        $nonce  = wp_create_nonce( 'jb3_sync_nonce' );

        // Count totals for informational display
        $wp_members = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );
        $wp_admins  = count( get_users( [ 'role' => 'administrator', 'number' => -1, 'fields' => 'ID' ] ) );
        ?>
        <div class="wrap" id="jb3-sync-wrap">
            <h1><?php esc_html_e( 'Sincronizar con Mailchimp', 'jb3-members' ); ?></h1>

            <?php if ( ! $has_mc ) : ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        echo wp_kses(
                            sprintf(
                                __( 'Mailchimp no está configurado. <a href="%s">Ir a Configuración</a>.', 'jb3-members' ),
                                esc_url( admin_url( 'admin.php?page=jb3-settings' ) )
                            ),
                            [ 'a' => [ 'href' => [] ] ]
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>

                <div class="notice notice-info inline" style="margin:16px 0;padding:12px 16px;">
                    <p style="margin:0 0 8px;"><strong><?php esc_html_e( '¿Qué hace esta sincronización?', 'jb3-members' ); ?></strong></p>
                    <ul style="list-style:disc;margin:0 0 0 20px;">
                        <li><?php esc_html_e( 'Fase 1 — WordPress → Mailchimp: sube todos los miembros aprobados con newsletter activo. Los que tienen newsletter inactivo quedan archivados en Mailchimp.', 'jb3-members' ); ?></li>
                        <li><?php esc_html_e( 'Los usuarios administradores se sincronizan siempre (para recibir emails de prueba).', 'jb3-members' ); ?></li>
                        <li><?php esc_html_e( 'Fase 2 — Mailchimp → Archivo: los contactos en Mailchimp que no tienen cuenta en WordPress quedan archivados (no se borran definitivamente; son recuperables desde Mailchimp).', 'jb3-members' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 0;">
                        <?php
                        printf(
                            esc_html__( 'Base de datos actual: %1$d miembros · %2$d administradores.', 'jb3-members' ),
                            $wp_members,
                            $wp_admins
                        );
                        ?>
                    </p>
                </div>

                <div id="jb3-sync-ui" style="margin:20px 0;">

                    <!-- Progress area (hidden until sync starts or state exists) -->
                    <div id="jb3-sync-progress" style="display:none;margin-bottom:20px;">

                        <p style="margin:0 0 4px;font-weight:600;"><?php esc_html_e( 'Fase 1: WordPress → Mailchimp', 'jb3-members' ); ?></p>
                        <div style="background:#e0e0e0;border-radius:4px;height:22px;width:100%;max-width:520px;overflow:hidden;">
                            <div id="jb3-bar-wp" style="background:#0073aa;height:22px;width:0%;transition:width .4s;"></div>
                        </div>
                        <p id="jb3-label-wp" style="margin:4px 0 16px;color:#555;"></p>

                        <p style="margin:0 0 4px;font-weight:600;"><?php esc_html_e( 'Fase 2: Mailchimp → Archivo', 'jb3-members' ); ?></p>
                        <div style="background:#e0e0e0;border-radius:4px;height:22px;width:100%;max-width:520px;overflow:hidden;">
                            <div id="jb3-bar-mc" style="background:#e65100;height:22px;width:0%;transition:width .4s;"></div>
                        </div>
                        <p id="jb3-label-mc" style="margin:4px 0 0;color:#555;"></p>

                    </div>

                    <!-- Results area (shown when done) -->
                    <div id="jb3-sync-results" style="display:none;"></div>

                    <!-- Errors area -->
                    <div id="jb3-sync-errors" style="display:none;margin-top:12px;"></div>

                    <!-- Action buttons -->
                    <p style="margin-top:16px;">
                        <button id="jb3-btn-start" class="button button-primary button-large">
                            <?php esc_html_e( 'Iniciar sincronización', 'jb3-members' ); ?>
                        </button>
                        <button id="jb3-btn-cancel" class="button button-large" style="display:none;margin-left:8px;">
                            <?php esc_html_e( 'Cancelar', 'jb3-members' ); ?>
                        </button>
                        <span id="jb3-sync-spinner" class="spinner" style="display:none;float:none;vertical-align:middle;margin-left:8px;"></span>
                    </p>

                </div>

                <script>
                (function () {
                    var ajaxurl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                    var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
                    var state    = <?php echo wp_json_encode( $state ?: null ); ?>;
                    var running  = false;

                    var btnStart   = document.getElementById('jb3-btn-start');
                    var btnCancel  = document.getElementById('jb3-btn-cancel');
                    var spinner    = document.getElementById('jb3-sync-spinner');
                    var divProg    = document.getElementById('jb3-sync-progress');
                    var divRes     = document.getElementById('jb3-sync-results');
                    var divErr     = document.getElementById('jb3-sync-errors');
                    var barWp      = document.getElementById('jb3-bar-wp');
                    var labelWp    = document.getElementById('jb3-label-wp');
                    var barMc      = document.getElementById('jb3-bar-mc');
                    var labelMc    = document.getElementById('jb3-label-mc');

                    function escHtml(s) {
                        return String(s)
                            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    }

                    function post(action, cb) {
                        var fd = new FormData();
                        fd.append('action', action);
                        fd.append('_ajax_nonce', nonce);
                        fetch(ajaxurl, { method: 'POST', body: fd })
                            .then(function(r){ return r.json(); })
                            .then(cb)
                            .catch(function(e){
                                running = false;
                                setSpinner(false);
                                alert('Error de red: ' + e.message + '. Recargá la página para ver el estado actual.');
                            });
                    }

                    function setSpinner(on) {
                        spinner.style.display = on ? 'inline-block' : 'none';
                        spinner.className = on ? 'spinner is-active' : 'spinner';
                    }

                    function pct(done, total) {
                        if (total <= 0) return 0;
                        return Math.min(100, Math.round(done * 100 / total));
                    }

                    function renderState() {
                        if (!state) {
                            divProg.style.display = 'none';
                            divRes.style.display  = 'none';
                            divErr.style.display  = 'none';
                            btnStart.disabled     = false;
                            btnStart.textContent  = <?php echo wp_json_encode( __( 'Iniciar sincronización', 'jb3-members' ) ); ?>;
                            btnCancel.style.display = 'none';
                            return;
                        }

                        var phase = state.phase;

                        if (phase === 'done') {
                            // --- Results ---
                            divProg.style.display = 'none';
                            var html = '<div class="notice notice-success inline" style="padding:12px 16px;">'
                                     + '<p style="margin:0;"><strong>' + <?php echo wp_json_encode( __( 'Sincronización completada.', 'jb3-members' ) ); ?> + '</strong></p>'
                                     + '<p style="margin:4px 0 0;">'
                                     + escHtml(state.upserted) + ' <?php echo esc_js( __( 'usuario(s) enviados a Mailchimp', 'jb3-members' ) ); ?>.<br>'
                                     + escHtml(state.archived) + ' <?php echo esc_js( __( 'contacto(s) archivados', 'jb3-members' ) ); ?>.<br>'
                                     + '<?php echo esc_js( __( 'Iniciada:', 'jb3-members' ) ); ?> ' + escHtml(state.started_at)
                                     + '</p></div>';
                            divRes.innerHTML = html;
                            divRes.style.display = 'block';

                            btnStart.disabled     = false;
                            btnStart.textContent  = <?php echo wp_json_encode( __( 'Sincronizar de nuevo', 'jb3-members' ) ); ?>;
                            btnCancel.style.display = 'none';

                        } else {
                            // --- In progress ---
                            divProg.style.display = 'block';
                            divRes.style.display  = 'none';

                            // Phase 1 bar
                            var p1done  = (phase === 'mc_to_wp' || phase === 'done');
                            var wp_done = p1done ? state.wp_total : state.wp_offset;
                            var wp_pct  = p1done ? 100 : pct(state.wp_offset, state.wp_total);
                            barWp.style.width = wp_pct + '%';
                            labelWp.textContent = escHtml(state.upserted) + ' <?php echo esc_js( __( 'usuario(s) enviados', 'jb3-members' ) ); ?>';

                            // Phase 2 bar
                            if (phase === 'mc_to_wp') {
                                var mc_pct = state.mc_total > 0 ? pct(state.mc_offset, state.mc_total) : 0;
                                barMc.style.width = mc_pct + '%';
                                labelMc.textContent = (state.mc_total > 0
                                    ? state.mc_offset + ' / ' + state.mc_total + ' — '
                                    : '<?php echo esc_js( __( 'Verificando...', 'jb3-members' ) ); ?> — ')
                                    + escHtml(state.archived) + ' <?php echo esc_js( __( 'archivados', 'jb3-members' ) ); ?>';
                            } else {
                                barMc.style.width = '0%';
                                labelMc.textContent = '<?php echo esc_js( __( 'Pendiente', 'jb3-members' ) ); ?>';
                            }

                            btnStart.disabled       = true;
                            btnCancel.style.display = 'inline-block';
                        }

                        // Errors
                        if (state.errors && state.errors.length > 0) {
                            var errHtml = '<details><summary style="cursor:pointer;color:#c0392b;">'
                                        + state.errors.length + ' <?php echo esc_js( __( 'error(s) — clic para ver', 'jb3-members' ) ); ?>'
                                        + '</summary><ul style="margin:8px 0 0 20px;list-style:disc;">';
                            state.errors.forEach(function(e){ errHtml += '<li>' + escHtml(e) + '</li>'; });
                            errHtml += '</ul></details>';
                            divErr.innerHTML     = errHtml;
                            divErr.style.display = 'block';
                        } else {
                            divErr.style.display = 'none';
                        }
                    }

                    function processChunk() {
                        if (!running) return;
                        setSpinner(true);
                        post('jb3_sync_chunk', function(resp) {
                            setSpinner(false);
                            if (!resp.success) {
                                running = false;
                                alert('Error: ' + resp.data);
                                return;
                            }
                            state = resp.data;
                            renderState();
                            if (state.phase !== 'done') {
                                setTimeout(processChunk, 150);
                            } else {
                                running = false;
                            }
                        });
                    }

                    // --- Start button ---
                    btnStart.addEventListener('click', function () {
                        if (running) return;
                        running = true;
                        divRes.style.display = 'none';
                        btnStart.disabled    = true;
                        btnStart.textContent = <?php echo wp_json_encode( __( 'Iniciando…', 'jb3-members' ) ); ?>;
                        setSpinner(true);

                        post('jb3_sync_start', function(resp) {
                            setSpinner(false);
                            if (!resp.success) {
                                running = false;
                                btnStart.disabled = false;
                                alert('Error al iniciar: ' + resp.data);
                                return;
                            }
                            state = resp.data;
                            renderState();
                            processChunk();
                        });
                    });

                    // --- Cancel button ---
                    btnCancel.addEventListener('click', function () {
                        running = false;
                        setSpinner(false);
                        post('jb3_sync_cancel', function() {
                            state = null;
                            renderState();
                        });
                    });

                    // --- Restore state on page load ---
                    if (state && state.phase !== 'done') {
                        // Sync was interrupted; show state and offer to resume
                        renderState();
                        btnStart.disabled    = false;
                        btnStart.textContent = <?php echo wp_json_encode( __( 'Reanudar sincronización', 'jb3-members' ) ); ?>;
                        btnCancel.style.display = 'inline-block';
                    } else if (state && state.phase === 'done') {
                        renderState();
                    }
                })();
                </script>

            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Import: admin page
    // -------------------------------------------------------------------------

    public static function page_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $uid     = get_current_user_id();
        $results = get_transient( 'jb3_import_results_' . $uid );
        delete_transient( 'jb3_import_results_' . $uid );

        $form_url = add_query_arg( [ 'page' => 'jb3-import' ], admin_url( 'admin.php' ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Importar Usuarios', 'jb3-members' ); ?></h1>

            <?php if ( $results !== false ) : ?>
                <div class="notice notice-<?php echo empty( $results['errors'] ) ? 'success' : 'warning'; ?> is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__( '%1$d usuario(s) creado(s), %2$d actualizado(s).', 'jb3-members' ),
                            (int) $results['created'],
                            (int) $results['updated']
                        );
                        ?>
                    </p>
                    <?php if ( ! empty( $results['errors'] ) ) : ?>
                        <p><strong><?php esc_html_e( 'Errores:', 'jb3-members' ); ?></strong></p>
                        <ul style="list-style:disc;margin-left:20px;">
                            <?php foreach ( $results['errors'] as $err ) : ?>
                                <li><?php echo esc_html( $err ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e( 'Subí un archivo CSV. La primera fila debe contener los nombres de columna. La columna "Email" es obligatoria.', 'jb3-members' ); ?></p>
            <p><?php esc_html_e( 'Si el email ya existe se actualizan sus datos; si no existe se crea un nuevo miembro (auto-aprobado).', 'jb3-members' ); ?></p>

            <details style="margin-bottom:16px;">
                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Columnas reconocidas', 'jb3-members' ); ?></summary>
                <table class="widefat" style="max-width:560px;margin-top:8px;">
                    <thead><tr><th><?php esc_html_e( 'Columna CSV', 'jb3-members' ); ?></th><th><?php esc_html_e( 'Campo', 'jb3-members' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( [
                            [ 'Email',              __( 'Email (obligatorio)', 'jb3-members' ) ],
                            [ 'Nombre',             __( 'Nombre', 'jb3-members' ) ],
                            [ 'Apellido',           __( 'Apellido', 'jb3-members' ) ],
                            [ 'Estado',             __( 'Estado (Aprobado / Pendiente)', 'jb3-members' ) ],
                            [ 'País',               __( 'País (nombre o código ISO)', 'jb3-members' ) ],
                            [ 'Empresa',            __( 'Empresa', 'jb3-members' ) ],
                            [ 'Cargo',              __( 'Cargo', 'jb3-members' ) ],
                            [ 'Teléfono',           __( 'Teléfono', 'jb3-members' ) ],
                            [ 'Sector',             __( 'Sector', 'jb3-members' ) ],
                            [ 'Newsletter',  __( 'Newsletter (Sí / No)', 'jb3-members' ) ],
                            [ 'Newsletters', __( 'Newsletters por País (nombres separados por coma)', 'jb3-members' ) ],
                            [ 'Intereses',   __( 'Intereses (separados por coma)', 'jb3-members' ) ],
                            [ 'Fecha Registro JB3', __( 'Fecha de registro JB3', 'jb3-members' ) ],
                            [ 'Fecha Aprobación',   __( 'Fecha de aprobación', 'jb3-members' ) ],
                        ] as [ $col, $desc ] ) : ?>
                            <tr><td><code><?php echo esc_html( $col ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:8px;">
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'jb3-export', 'action' => 'download' ], admin_url( 'admin.php' ) ), 'jb3_export_users' ) ); ?>">
                        <?php esc_html_e( '↓ Descargar CSV actual como plantilla', 'jb3-members' ); ?>
                    </a>
                </p>
            </details>

            <form method="post" action="<?php echo esc_url( $form_url ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'jb3_import_users', 'jb3_import_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="jb3_csv"><?php esc_html_e( 'Archivo CSV', 'jb3-members' ); ?></label></th>
                        <td><input type="file" id="jb3_csv" name="jb3_csv" accept=".csv,text/csv" required></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Importar', 'jb3-members' ), 'primary' ); ?>
            </form>
        </div>
        <?php
    }
}
