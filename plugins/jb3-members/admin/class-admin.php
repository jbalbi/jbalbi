<?php
defined( 'ABSPATH' ) || exit;

class JB3_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Form processing must run at admin_init — before any output is sent.
        add_action( 'admin_init', [ __CLASS__, 'handle_rep_actions' ] );

        // CSV export — must run at admin_init before any output.
        add_action( 'admin_init', [ __CLASS__, 'handle_export' ] );

        // Backfill de alias faltantes — must run at admin_init before any output.
        add_action( 'admin_init', [ __CLASS__, 'handle_fix_nicknames' ] );

        // El sitio entero está protegido con Basic Auth (contraseña de
        // Hostinger) — WordPress detecta el conflicto con Contraseñas de
        // aplicación (comparten el header Authorization) y muestra un aviso
        // en CADA perfil de usuario. No se usa esta función en el proyecto,
        // así que se desactiva entera en vez de convivir con el aviso.
        add_filter( 'wp_is_application_passwords_available', '__return_false' );

        // Users list – custom columns.
        add_filter( 'manage_users_columns',          [ __CLASS__, 'user_columns' ] );
        add_filter( 'manage_users_custom_column',    [ __CLASS__, 'user_column_value' ], 10, 3 );
        add_filter( 'manage_users_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
        add_action( 'pre_get_users',                 [ __CLASS__, 'sort_by_jb3_meta' ] );
        add_filter( 'user_row_actions',              [ __CLASS__, 'user_row_actions' ], 10, 2 );
        add_filter( 'list_table_primary_column',     [ __CLASS__, 'users_primary_column' ], 10, 2 );

        // AJAX handlers for user actions (run via admin-ajax.php — headers not yet sent).
        add_action( 'wp_ajax_jb3_approve_user',       [ __CLASS__, 'ajax_approve_user' ] );
        add_action( 'wp_ajax_jb3_logout_user',        [ __CLASS__, 'ajax_logout_user' ] );
        add_action( 'wp_ajax_jb3_fetch_mc_interests', [ __CLASS__, 'ajax_fetch_mc_interests' ] );

        // CSV import — browser-driven chunk loop.
        add_action( 'wp_ajax_jb3_import_start',  [ __CLASS__, 'ajax_import_start' ] );
        add_action( 'wp_ajax_jb3_import_chunk',  [ __CLASS__, 'ajax_import_chunk' ] );
        add_action( 'wp_ajax_jb3_import_cancel', [ __CLASS__, 'ajax_import_cancel' ] );

        // Bulk delete — browser-driven chunk loop.
        add_action( 'wp_ajax_jb3_delete_start',       [ __CLASS__, 'ajax_delete_start' ] );
        add_action( 'wp_ajax_jb3_delete_chunk',       [ __CLASS__, 'ajax_delete_chunk' ] );
        add_action( 'wp_ajax_jb3_delete_cancel',      [ __CLASS__, 'ajax_delete_cancel' ] );
        add_action( 'wp_ajax_jb3_delete_list_upload', [ __CLASS__, 'ajax_delete_list_upload' ] );


        // Settings registration.
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

        // Admin notices.
        add_action( 'admin_notices', [ __CLASS__, 'admin_notices' ] );

        // User profile – JB3 fields section.
        add_action( 'show_user_profile',        [ __CLASS__, 'render_user_profile' ] );
        add_action( 'edit_user_profile',        [ __CLASS__, 'render_user_profile' ] );
        add_action( 'edit_user_profile_update', [ __CLASS__, 'save_user_profile' ] );
        add_action( 'personal_options_update',  [ __CLASS__, 'save_user_profile' ] );

        // Guard: if an admin's role is changed away from administrator via the
        // native WP Role dropdown, restore it so admins never lose their access.
        add_action( 'set_user_role', [ __CLASS__, 'guard_admin_role' ], 10, 3 );
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public static function register_menu() {
        // Posición 32: fija, a propósito DESPUÉS de los 6 CPT del tema
        // (peliculas..integraciones, ahora con menu_position 26-31 explícito
        // en _init_custom_post_types.php). Antes esto pedía 30 sin saber que
        // WordPress le asigna esa misma posición automáticamente a uno de
        // los CPT (el orden en que se registran) — el choque de posiciones
        // hacía que JB3 Members quedara intercalado en el medio de la lista
        // de CPT en vez de después de todos — reportado en vivo 2026-08-31.
        add_menu_page(
            __( 'JB3 Members', 'jb3-members' ),
            __( 'JB3 Members', 'jb3-members' ),
            'manage_options',
            'jb3-members',
            [ __CLASS__, 'page_representatives' ],
            'dashicons-groups',
            32
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
        add_submenu_page(
            'jb3-members',
            __( 'Borrar Miembros', 'jb3-members' ),
            __( 'Borrar Miembros', 'jb3-members' ),
            'manage_options',
            'jb3-delete',
            [ __CLASS__, 'page_delete' ]
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
        wp_enqueue_style( 'jb3-admin', JB3_URL . 'public/css/jb3-forms.css', [], jb3_asset_ver( 'public/css/jb3-forms.css' ) );

        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
        if ( 'toplevel_page_jb3-members' === $hook && in_array( $action, [ 'add', 'edit' ], true ) ) {
            wp_enqueue_media();
        }
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public static function register_settings() {
        register_setting( 'jb3_settings', 'jb3_login_page_id',        [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_profile_page_id',      [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_dashboard_page_id',    [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'jb3_settings', 'jb3_email_from',      [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ] );
        register_setting( 'jb3_settings', 'jb3_email_from_name', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'jb3_settings', 'jb3_mc_api_key',  [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'jb3_settings', 'jb3_mc_list_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'jb3_settings', 'jb3_gsheets_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ] );
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
                'country_codes'    => array_map(
                    static fn( $c ) => strtoupper( sanitize_text_field( $c ) ),
                    wp_unslash( (array) ( $_POST['country_codes'] ?? [] ) )
                ),
                'first_name'       => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
                'last_name'        => sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) ),
                'email'            => sanitize_email(      wp_unslash( $_POST['email']      ?? '' ) ),
                'phone'            => jb3_sanitize_phone( wp_unslash( $_POST['phone']       ?? '' ) ),
                'whatsapp'         => jb3_sanitize_phone( wp_unslash( $_POST['whatsapp']    ?? '' ) ),
                'photo_id'         => absint( $_POST['photo_id'] ?? 0 ),
                'is_default'       => ! empty( $_POST['is_default'] )       ? 1 : 0,
                'visible'          => ! empty( $_POST['visible'] )          ? 1 : 0,
                'notify_downloads' => ! empty( $_POST['notify_downloads'] ) ? 1 : 0,
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
                'id' => 0, 'country_codes' => [], 'first_name' => '', 'last_name' => '',
                'email' => '', 'phone' => '', 'whatsapp' => '', 'photo_id' => 0, 'is_default' => 0,
                'visible' => 1, 'notify_downloads' => 1,
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
        $valid_codes = array_intersect( $data['country_codes'] ?? [], array_keys( jb3_countries() ) );
        if ( empty( $data['is_default'] ) && empty( $valid_codes ) ) {
            $errors[] = __( 'Seleccioná al menos un país, o marcá este representante como predeterminado.', 'jb3-members' );
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
                <tr>
                    <th><label for="nb_whatsapp"><?php esc_html_e( 'WhatsApp', 'jb3-members' ); ?></label></th>
                    <td><input type="tel" id="nb_whatsapp" name="whatsapp" value="<?php echo esc_attr( $rep->whatsapp ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="nb_photo_select"><?php esc_html_e( 'Foto', 'jb3-members' ); ?></label></th>
                    <td>
                        <input type="hidden" id="nb_photo_id" name="photo_id" value="<?php echo (int) ( $rep->photo_id ?? 0 ); ?>">
                        <div id="nb_photo_preview" style="margin-bottom:8px;">
                            <?php if ( ! empty( $rep->photo_id ) ) : ?>
                                <?php echo wp_get_attachment_image( (int) $rep->photo_id, [ 100, 100 ], false, [ 'style' => 'border-radius:50%;object-fit:cover;' ] ); ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="nb_photo_select"><?php esc_html_e( 'Elegir foto', 'jb3-members' ); ?></button>
                        <button type="button" class="button" id="nb_photo_remove" <?php echo empty( $rep->photo_id ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Quitar foto', 'jb3-members' ); ?></button>
                        <script>
                        jQuery( function ( $ ) {
                            var frame;
                            $( '#nb_photo_select' ).on( 'click', function ( e ) {
                                e.preventDefault();
                                if ( frame ) {
                                    frame.open();
                                    return;
                                }
                                frame = wp.media( {
                                    title: <?php echo wp_json_encode( __( 'Elegir foto del representante', 'jb3-members' ) ); ?>,
                                    multiple: false,
                                    library: { type: 'image' }
                                } );
                                frame.on( 'select', function () {
                                    var att = frame.state().get( 'selection' ).first().toJSON();
                                    var url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
                                    $( '#nb_photo_id' ).val( att.id );
                                    $( '#nb_photo_preview' ).html( $( '<img>' ).attr( 'src', url ).css( { width: '100px', height: '100px', borderRadius: '50%', objectFit: 'cover' } ) );
                                    $( '#nb_photo_remove' ).show();
                                } );
                                frame.open();
                            } );
                            $( '#nb_photo_remove' ).on( 'click', function ( e ) {
                                e.preventDefault();
                                $( '#nb_photo_id' ).val( '' );
                                $( '#nb_photo_preview' ).empty();
                                $( this ).hide();
                            } );
                        } );
                        </script>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Países', 'jb3-members' ); ?></th>
                    <td>
                        <div class="jb3-checkboxes">
                            <?php $rep_countries = (array) ( $rep->country_codes ?? [] ); ?>
                            <?php foreach ( jb3_countries() as $code => $name ) : ?>
                                <label class="jb3-cb-label">
                                    <input type="checkbox" name="country_codes[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $rep_countries, true ) ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Países en los que este representante gestiona oportunidades. Podés marcar varios.', 'jb3-members' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="nb_visible"><?php esc_html_e( 'Visible en el sitio', 'jb3-members' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="nb_visible" name="visible" value="1" <?php checked( ! isset( $rep->visible ) || ! empty( $rep->visible ) ); ?>>
                            <?php esc_html_e( 'Mostrar en la página de Contacto', 'jb3-members' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="nb_notify_downloads"><?php esc_html_e( 'Recibe notificaciones', 'jb3-members' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="nb_notify_downloads" name="notify_downloads" value="1" <?php checked( ! isset( $rep->notify_downloads ) || ! empty( $rep->notify_downloads ) ); ?>>
                            <?php esc_html_e( 'Avisar por email cuando alguien descarga una Op. Comercial de su país', 'jb3-members' ); ?>
                        </label>
                    </td>
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
                    <th><?php esc_html_e( 'Foto', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Países', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Nombre', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Apellido', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Teléfono', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'WhatsApp', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Visible', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Notif.', 'jb3-members' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'jb3-members' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $reps ) ) : ?>
                    <tr><td colspan="10"><?php esc_html_e( 'No hay representantes cargados.', 'jb3-members' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $reps as $rep ) : ?>
                        <?php
                        $rep_country_names = array_map(
                            static fn( $code ) => $countries[ $code ] ?? $code,
                            $rep->country_codes ?? []
                        );
                        ?>
                        <tr>
                            <td>
                                <?php if ( ! empty( $rep->photo_id ) ) : ?>
                                    <?php echo wp_get_attachment_image( (int) $rep->photo_id, [ 40, 40 ], false, [ 'style' => 'border-radius:50%;object-fit:cover;' ] ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $rep_country_names ) : ?>
                                    <?php echo esc_html( implode( ', ', $rep_country_names ) ); ?>
                                    <?php if ( $rep->is_default ) : ?>
                                        <br><em>(<?php esc_html_e( 'también por defecto', 'jb3-members' ); ?>)</em>
                                    <?php endif; ?>
                                <?php elseif ( $rep->is_default ) : ?>
                                    <em><?php esc_html_e( 'Por defecto', 'jb3-members' ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $rep->first_name ); ?></td>
                            <td><?php echo esc_html( $rep->last_name ); ?></td>
                            <td><?php echo esc_html( $rep->email ); ?></td>
                            <td><?php echo esc_html( $rep->phone ); ?></td>
                            <td><?php echo esc_html( $rep->whatsapp ); ?></td>
                            <td><?php echo $rep->visible
                                ? '<span class="dashicons dashicons-yes" style="color:#2e7d32;"></span>'
                                : '<span class="dashicons dashicons-no" style="color:#a00;"></span>'; ?>
                            </td>
                            <td><?php echo $rep->notify_downloads
                                ? '<span class="dashicons dashicons-yes" style="color:#2e7d32;"></span>'
                                : '<span class="dashicons dashicons-no" style="color:#a00;"></span>'; ?>
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

                <!-- Email -->
                <hr>
                <h2><?php esc_html_e( 'Email', 'jb3-members' ); ?></h2>
                <p class="description" style="max-width:600px;margin-bottom:12px;">
                    <?php esc_html_e( 'Remitente de los mails de registro, login (magic link) y avisos de descarga. Tiene que ser una dirección de un dominio propio, autenticado en el servicio de envío (SPF/DKIM) — un Gmail/Outlook/Yahoo personal no funciona: esos proveedores rechazan mails que digan venir de ellos pero que no hayan salido de sus propios servidores.', 'jb3-members' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="jb3_email_from"><?php esc_html_e( 'Email remitente', 'jb3-members' ); ?></label></th>
                        <td>
                            <input type="email" id="jb3_email_from" name="jb3_email_from" value="<?php echo esc_attr( get_option( 'jb3_email_from' ) ); ?>" class="regular-text" placeholder="notificaciones@tudominio.com">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="jb3_email_from_name"><?php esc_html_e( 'Nombre remitente', 'jb3-members' ); ?></label></th>
                        <td>
                            <input type="text" id="jb3_email_from_name" name="jb3_email_from_name" value="<?php echo esc_attr( get_option( 'jb3_email_from_name' ) ); ?>" class="regular-text" placeholder="JB3 Members">
                        </td>
                    </tr>
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

                <!-- Google Sheets -->
                <hr>
                <h2><?php esc_html_e( 'Google Sheets', 'jb3-members' ); ?></h2>
                <div class="notice notice-info inline" style="margin:0 0 16px;">
                    <p>
                        <?php esc_html_e( 'Registra vistas y descargas de Op. Comerciales en una planilla — la cuenta de servicio necesita acceso de Editor sobre esa planilla (compartida a mano desde Google Sheets).', 'jb3-members' ); ?>
                    </p>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label for="jb3_gsheets_id"><?php esc_html_e( 'ID de la planilla', 'jb3-members' ); ?></label></th>
                        <td>
                            <input type="text" id="jb3_gsheets_id" name="jb3_gsheets_id" value="<?php echo esc_attr( get_option( 'jb3_gsheets_id' ) ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'El texto entre /d/ y /edit en la URL de la planilla de Google Sheets.', 'jb3-members' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Users list columns
    // -------------------------------------------------------------------------

    public static function users_primary_column( $column, $screen ) {
        return 'users' === $screen ? 'email' : $column;
    }

    public static function user_columns( $columns ) {
        unset( $columns['username'] );
        unset( $columns['posts'] );

        $cb = isset( $columns['cb'] ) ? [ 'cb' => $columns['cb'] ] : [];
        unset( $columns['cb'] );

        // email first, then remaining WP columns, then our custom ones.
        $email = isset( $columns['email'] ) ? [ 'email' => $columns['email'] ] : [];
        unset( $columns['email'] );

        return array_merge( $cb, $email, $columns, [
            'jb3_status'     => __( 'Estado JB3', 'jb3-members' ),
            'jb3_newsletter' => __( 'Newsletter', 'jb3-members' ),
            'jb3_country'    => __( 'País', 'jb3-members' ),
            'jb3_company'    => __( 'Empresa', 'jb3-members' ),
        ] );
    }

    public static function sortable_columns( $columns ) {
        foreach ( [ 'jb3_status', 'jb3_newsletter', 'jb3_country', 'jb3_company' ] as $col ) {
            $columns[ $col ] = $col;
        }
        return $columns;
    }

    public static function sort_by_jb3_meta( $query ) {
        if ( ! is_admin() ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( ! in_array( $orderby, [ 'jb3_status', 'jb3_newsletter', 'jb3_country', 'jb3_company' ], true ) ) {
            return;
        }
        $query->set( 'meta_query', [
            'jb3_sort' => [ 'key' => $orderby, 'compare' => 'EXISTS' ],
        ] );
        $query->set( 'orderby', 'jb3_sort' );
    }

    public static function user_column_value( $value, $column, $user_id ) {
        $is_admin  = user_can( $user_id, 'manage_options' );
        $is_member = jb3_is_member( $user_id );

        if ( ! $is_member && ! $is_admin ) {
            return '—';
        }

        switch ( $column ) {
            case 'jb3_status':
                if ( $is_admin ) {
                    return '<span style="color:#1565c0;font-weight:600;">' . esc_html__( 'Admin', 'jb3-members' ) . '</span>';
                }
                $status = jb3_get_status( $user_id );
                $labels = [
                    'pending'  => '<span style="color:#e65100;font-weight:600;">' . esc_html__( 'Pendiente', 'jb3-members' ) . '</span>',
                    'approved' => '<span style="color:#2e7d32;font-weight:600;">' . esc_html__( 'Aprobado',  'jb3-members' ) . '</span>',
                ];
                return $labels[ $status ] ?? esc_html( $status );

            case 'jb3_country':
                $code = get_user_meta( $user_id, 'jb3_country', true );
                if ( ! $code ) {
                    return '—';
                }
                $all = jb3_countries();
                return esc_html( $all[ $code ] ?? $code );

            case 'jb3_newsletter':
                $nl = (bool) get_user_meta( $user_id, 'jb3_newsletter', true );
                return $nl
                    ? '<span style="color:#2e7d32;font-weight:600;">' . esc_html__( 'Sí', 'jb3-members' ) . '</span>'
                    : '<span style="color:#999;">—</span>';

            case 'jb3_company':
                $company = get_user_meta( $user_id, 'jb3_company', true );
                return $company ? esc_html( $company ) : '—';
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
                <th><?php esc_html_e( 'Suscripto al newsletter', 'jb3-members' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="jb3_newsletter" value="1" <?php checked( $newsletter ); ?>>
                        <?php esc_html_e( 'Sí', 'jb3-members' ); ?>
                    </label>
                </td>
            </tr>
            <?php
            $nl_interests = JB3_Mailchimp::get_newsletter_interests();
            if ( ! empty( $nl_interests ) ) :
            ?>
            <tr>
                <th><?php esc_html_e( 'Newsletters que recibe el usuario', 'jb3-members' ); ?></th>
                <td>
                    <?php foreach ( $nl_interests as $id => $name ) : ?>
                        <label style="display:inline-block;margin-right:20px;margin-bottom:6px;">
                            <input type="checkbox" name="jb3_newsletter_countries[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $nl_countries, true ) ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Se completa solo con el país del usuario al suscribirse, y se vacía al desuscribirse — esta selección manual solo se respeta si el estado de "Suscripto al newsletter" no cambia en este guardado.', 'jb3-members' ); ?></p>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Intereses', 'jb3-members' ); ?></th>
                <td>
                    <?php $mc_interests = jb3_interests(); ?>
                    <?php if ( ! empty( $mc_interests ) ) : ?>
                        <?php foreach ( $mc_interests as $key => $label ) : ?>
                            <label style="display:inline-block;margin-right:20px;margin-bottom:6px;">
                                <input type="checkbox" name="jb3_interests[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $interests, true ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php elseif ( ! empty( $interests ) ) : ?>
                        <p style="color:#b32d2e;margin:0 0 6px;">
                            <?php esc_html_e( 'Mailchimp no disponible — intereses almacenados (guardar este perfil los eliminará):', 'jb3-members' ); ?>
                        </p>
                        <code><?php echo esc_html( implode( ', ', $interests ) ); ?></code>
                    <?php else : ?>
                        <span style="color:#999;"><?php esc_html_e( 'Sin intereses almacenados.', 'jb3-members' ); ?></span>
                    <?php endif; ?>
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
        if ( ! jb3_is_member( $user_id ) && ! user_can( $user_id, 'manage_options' ) ) {
            return;
        }

        $old_status     = get_user_meta( $user_id, 'jb3_status', true ) ?: 'pending';
        $old_newsletter = (bool) get_user_meta( $user_id, 'jb3_newsletter', true );
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
        update_user_meta( $user_id, 'jb3_newsletter', $newsletter );

        delete_user_meta( $user_id, 'jb3_interests' );
        if ( ! empty( $interests ) ) {
            update_user_meta( $user_id, 'jb3_interests', $interests );
        }

        // Solo en la transición real de suscripción se pisa "Newsletter por
        // País" con la regla automática (país propio al suscribir, vacío al
        // desuscribir — ver jb3_sync_newsletter_countries(), helpers.php).
        // Si el estado de suscripción no cambió en este guardado, se respeta
        // lo que el admin haya tildado a mano en el formulario.
        if ( (bool) $newsletter !== $old_newsletter ) {
            jb3_sync_newsletter_countries( $user_id, (bool) $newsletter );
        } else {
            delete_user_meta( $user_id, 'jb3_newsletter_countries' );
            if ( ! empty( $nl_countries ) ) {
                update_user_meta( $user_id, 'jb3_newsletter_countries', $nl_countries );
            }
        }

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
    // Backfill: alias (nickname) faltante
    // -------------------------------------------------------------------------

    /**
     * Los usuarios creados por importación CSV vía $wpdb->insert() directo
     * (más abajo, en ajax_import_chunk) se saltean wp_insert_user() por
     * velocidad — y con eso, también la lógica nativa de WordPress que le
     * pone "nickname" = user_login por defecto a todo usuario nuevo. Sin
     * ese meta, WordPress rechaza cualquier guardado de la ficha desde el
     * admin nativo con "tenés que ingresar un alias". Este backfill corre
     * una sola vez (o las veces que haga falta, es idempotente) y completa
     * SOLO a quien no lo tenga — no pisa un alias que alguien ya haya
     * puesto a mano.
     */
    public static function handle_fix_nicknames() {
        if ( empty( $_GET['page'] ) || 'jb3-import' !== $_GET['page'] ) {
            return;
        }
        if ( empty( $_GET['action'] ) || 'jb3_fix_nicknames' !== $_GET['action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        check_admin_referer( 'jb3_fix_nicknames' );

        $fixed = self::backfill_missing_nicknames();

        wp_safe_redirect( add_query_arg( [ 'page' => 'jb3-import', 'nicknames_fixed' => $fixed ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function backfill_missing_nicknames(): int {
        global $wpdb;
        // INSERT ... SELECT ... LEFT JOIN ... IS NULL: agrega nickname = user_login
        // solo para quien no tenga ya esa fila de usermeta — un solo query,
        // sin importar cuántos usuarios haya.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->query(
            "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
             SELECT u.ID, 'nickname', u.user_login
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = 'nickname'
             WHERE um.user_id IS NULL"
        );
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
    // Import: browser-driven chunk loop (AJAX)
    // -------------------------------------------------------------------------

    private static function import_state_key(): string {
        return 'jb3_import_state_' . get_current_user_id();
    }

    private static function import_col_map(): array {
        // All keys are lowercase — headers are normalized to lowercase on read.
        return [
            'nombre'                   => 'first_name',
            'apellido'                 => 'last_name',
            'estado'                   => 'jb3_status',
            'país'                     => 'jb3_country',
            'empresa'                  => 'jb3_company',
            'cargo'                    => 'jb3_job_title',
            'teléfono'                 => 'jb3_phone',
            'sector'                   => 'jb3_sector',
            'newsletter'               => 'jb3_newsletter',
            'newsletters'              => 'jb3_newsletter_countries',
            'intereses'                => 'jb3_interests',
            'fecha registro jb3'       => 'jb3_registered_at',
            'fecha aprobación'         => 'jb3_approved_at',
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
    }

    public static function ajax_import_start() {
        check_ajax_referer( 'jb3_import_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permiso.', 'jb3-members' ) );
        }

        $upload = $_FILES['jb3_csv'] ?? null;
        if ( ! $upload || $upload['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'Error al subir el archivo.', 'jb3-members' ) );
        }
        if ( strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) ) !== 'csv' ) {
            wp_send_json_error( __( 'El archivo debe tener extensión .csv', 'jb3-members' ) );
        }

        // Copy to a persistent temp file so chunks can resume across requests.
        $tmp_file = wp_tempnam( 'jb3_import_' );
        if ( ! move_uploaded_file( $upload['tmp_name'], $tmp_file ) ) {
            wp_send_json_error( __( 'No se pudo guardar el archivo temporal.', 'jb3-members' ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $tmp_file, 'r' );
        if ( ! $handle ) {
            @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            wp_send_json_error( __( 'No se pudo leer el archivo.', 'jb3-members' ) );
        }

        $first_line = fgets( $handle );
        if ( $first_line === false || trim( $first_line ) === '' ) {
            fclose( $handle );
            @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            wp_send_json_error( __( 'El archivo está vacío.', 'jb3-members' ) );
        }

        $first_line = ltrim( $first_line, "\xEF\xBB\xBF" ); // Strip UTF-8 BOM.
        $delimiter  = substr_count( $first_line, ';' ) >= substr_count( $first_line, ',' ) ? ';' : ',';
        $headers    = array_map( fn( $h ) => strtolower( trim( $h ) ), str_getcsv( $first_line, $delimiter ) );

        if ( ! in_array( 'email', $headers, true ) ) {
            fclose( $handle );
            @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            wp_send_json_error( __( 'El CSV debe tener una columna "Email".', 'jb3-members' ) );
        }

        // Record byte offset after header row, then count data rows.
        $data_start = ftell( $handle );
        $total      = 0;
        while ( fgetcsv( $handle, 0, $delimiter ) !== false ) {
            $total++;
        }
        fclose( $handle );

        if ( $total === 0 ) {
            @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            wp_send_json_error( __( 'El archivo no contiene filas de datos.', 'jb3-members' ) );
        }

        $state = [
            'file'      => $tmp_file,
            'delimiter' => $delimiter,
            'headers'   => $headers,
            'total'     => $total,
            'offset'    => $data_start,
            'row_num'   => 1,
            'processed' => 0,
            'created'   => 0,
            'updated'   => 0,
            'errors'    => [],
            'phase'     => 'running',
        ];

        update_option( self::import_state_key(), $state, false );
        wp_send_json_success( $state );
    }

    public static function ajax_import_chunk() {
        check_ajax_referer( 'jb3_import_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permiso.', 'jb3-members' ) );
        }

        $state = get_option( self::import_state_key() );
        if ( ! $state || $state['phase'] !== 'running' ) {
            wp_send_json_error( __( 'No hay importación en curso.', 'jb3-members' ) );
        }
        if ( ! file_exists( $state['file'] ) ) {
            delete_option( self::import_state_key() );
            wp_send_json_error( __( 'El archivo temporal no existe.', 'jb3-members' ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $state['file'], 'r' );
        if ( ! $handle ) {
            wp_send_json_error( __( 'No se pudo leer el archivo temporal.', 'jb3-members' ) );
        }
        fseek( $handle, $state['offset'] );

        $delimiter          = $state['delimiter'];
        $headers            = $state['headers'];
        $col_map            = self::import_col_map();
        $countries_by_name  = array_flip( jb3_countries() );
        $interests_by_label = array_flip( jb3_interests() );
        $nl_interests       = JB3_Mailchimp::get_newsletter_interests();
        $nl_by_name         = array_flip( $nl_interests );

        $created   = $state['created'];
        $updated   = $state['updated'];
        $errors    = $state['errors'];
        $row_num   = $state['row_num'];
        $processed = $state['processed'];

        // ── Read the full chunk into memory, then close the file ────────────────
        $chunk_rows = [];
        while ( count( $chunk_rows ) < 200 && ( $raw = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $chunk_rows[] = $raw;
        }
        $new_offset = ftell( $handle );
        fclose( $handle );
        $handle = null;

        // ── Bulk email + ID lookup: 1 SQL instead of N get_user_by() calls ───
        $id_col_idx    = array_search( 'id', $headers, true );
        $lookup_emails = [];
        $lookup_ids    = [];
        foreach ( $chunk_rows as $raw ) {
            $email = sanitize_email( trim( $raw[ array_search( 'email', $headers, true ) ] ?? '' ) );
            if ( is_email( $email ) ) {
                $lookup_emails[] = strtolower( $email );
            }
            if ( $id_col_idx !== false ) {
                $csv_id = absint( $raw[ $id_col_idx ] ?? 0 );
                if ( $csv_id ) {
                    $lookup_ids[] = $csv_id;
                }
            }
        }

        $email_to_id = [];
        if ( ! empty( $lookup_emails ) ) {
            global $wpdb;
            $placeholders = implode( ',', array_fill( 0, count( $lookup_emails ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
            foreach ( $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, LOWER(user_email) AS lc_email FROM {$wpdb->users} WHERE LOWER(user_email) IN ($placeholders)",
                    ...$lookup_emails
                )
            ) as $db_row ) {
                $email_to_id[ $db_row->lc_email ] = (int) $db_row->ID;
            }
        }

        // Prime WP user object + meta caches for all existing users in this chunk
        $preload_ids = array_unique( array_merge( array_values( $email_to_id ), $lookup_ids ) );
        if ( ! empty( $preload_ids ) ) {
            get_users( [ 'include' => $preload_ids, 'number' => -1 ] );
            update_meta_cache( 'user', $preload_ids );
        }

        // ── Process rows ─────────────────────────────────────────────────────
        // Collects meta rows for new users; flushed in a single INSERT after the loop.
        $new_user_metas = [];

        foreach ( $chunk_rows as $raw ) {
            $row_num++;
            $processed++;

            $data = [];
            foreach ( $headers as $i => $col ) {
                $data[ $col ] = isset( $raw[ $i ] ) ? trim( $raw[ $i ] ) : '';
            }

            $email = sanitize_email( $data['email'] ?? '' );
            if ( ! is_email( $email ) ) {
                if ( count( $errors ) < 200 ) {
                    $errors[] = sprintf(
                        __( 'Fila %1$d: email inválido «%2$s».', 'jb3-members' ),
                        $row_num, esc_html( $data['email'] ?? '' )
                    );
                }
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
                        $status_map            = [ 'Aprobado' => 'approved', 'Pendiente' => 'pending' ];
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
                        $meta_fields[ $field ] = in_array( strtolower( $val ), [ 'sí', 'si', '1', 'yes', 'true' ], true ) ? 1 : 0;
                        break;

                    case 'jb3_newsletter_countries':
                        $parts = array_filter( array_map( 'trim', explode( ',', $val ) ) );
                        $ids   = [];
                        foreach ( $parts as $part ) {
                            if ( isset( $nl_by_name[ $part ] ) ) {
                                $ids[] = $nl_by_name[ $part ];
                            } elseif ( isset( $nl_interests[ $part ] ) ) {
                                $ids[] = $part;
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

            // Look up existing user from pre-built cache (no extra DB calls).
            $existing = null;
            if ( $id_col_idx !== false ) {
                $csv_id = absint( $raw[ $id_col_idx ] ?? 0 );
                if ( $csv_id ) {
                    $existing = get_userdata( $csv_id ); // served from WP object cache
                }
            }
            if ( ! $existing ) {
                $existing_id = $email_to_id[ strtolower( $email ) ] ?? null;
                $existing    = $existing_id ? get_userdata( $existing_id ) : null; // served from WP object cache
            }

            if ( $existing ) {
                if ( user_can( $existing->ID, 'manage_options' ) ) {
                    if ( count( $errors ) < 200 ) {
                        $errors[] = sprintf(
                            __( 'Fila %1$d: "%2$s" es administrador/a y no puede modificarse desde la importación.', 'jb3-members' ),
                            $row_num, $email
                        );
                    }
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
                if ( is_wp_error( $result ) && count( $errors ) < 200 ) {
                    $errors[] = sprintf( __( 'Fila %1$d: %2$s', 'jb3-members' ), $row_num, $result->get_error_message() );
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

                $updated++;

            } else {
                // Insert directly into DB — avoids bcrypt per user and defers meta to batch INSERT.
                $user_login   = strlen( $email ) <= 60 ? $email : 'jb3_' . wp_generate_password( 8, false );
                $display_name = trim( ( $wp_fields['first_name'] ?? '' ) . ' ' . ( $wp_fields['last_name'] ?? '' ) ) ?: $email;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $inserted = $wpdb->insert(
                    $wpdb->users,
                    [
                        'user_login'      => $user_login,
                        'user_pass'       => '!' . wp_generate_password( 32, false ),
                        'user_email'      => $email,
                        'user_registered' => gmdate( 'Y-m-d H:i:s' ),
                        'display_name'    => $display_name,
                        'user_nicename'   => sanitize_title( $user_login ),
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%s' ]
                );

                if ( ! $inserted ) {
                    if ( count( $errors ) < 200 ) {
                        $errors[] = sprintf( __( 'Fila %1$d: no se pudo crear el usuario "%2$s".', 'jb3-members' ), $row_num, $email );
                    }
                    continue;
                }

                $user_id = (int) $wpdb->insert_id;

                // Long-email fallback.
                if ( strlen( $email ) > 60 ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->update( $wpdb->users, [ 'user_login' => (string) $user_id ], [ 'ID' => $user_id ] );
                }

                // Queue meta for batch INSERT (avoids 10+ individual queries per user).
                // nickname: wp_insert_user() lo completa solo con el user_login por
                // defecto — como acá se inserta directo a la tabla (se saltea esa
                // lógica a propósito, por velocidad), hay que ponerlo a mano. Sin
                // esto, WordPress rechaza cualquier guardado de la ficha desde el
                // admin nativo con "tenés que ingresar un alias".
                $new_user_metas[] = [ $user_id, 'nickname', $user_login ];
                if ( ! empty( $wp_fields['first_name'] ) ) {
                    $new_user_metas[] = [ $user_id, 'first_name', $wp_fields['first_name'] ];
                }
                if ( ! empty( $wp_fields['last_name'] ) ) {
                    $new_user_metas[] = [ $user_id, 'last_name', $wp_fields['last_name'] ];
                }
                // Role stored as usermeta capability.
                $new_user_metas[] = [ $user_id, $wpdb->prefix . 'capabilities', serialize( [ 'jb3_member' => true ] ) ];
                $new_user_metas[] = [ $user_id, $wpdb->prefix . 'user_level', '0' ];

                foreach ( $meta_fields as $key => $val ) {
                    $new_user_metas[] = [ $user_id, $key, $val ];
                }
                if ( ! isset( $meta_fields['jb3_status'] ) ) {
                    $new_user_metas[] = [ $user_id, 'jb3_status', 'approved' ];
                }
                if ( ! isset( $meta_fields['jb3_approved_at'] ) ) {
                    $new_user_metas[] = [ $user_id, 'jb3_approved_at', current_time( 'mysql' ) ];
                }
                if ( ! isset( $meta_fields['jb3_newsletter'] ) ) {
                    $new_user_metas[] = [ $user_id, 'jb3_newsletter', '0' ];
                }
                if ( ! isset( $meta_fields['jb3_registered_at'] ) ) {
                    $new_user_metas[] = [ $user_id, 'jb3_registered_at', current_time( 'mysql' ) ];
                }

                $created++;
            }
        }

        // Flush all queued meta for new users in a single INSERT query.
        if ( ! empty( $new_user_metas ) ) {
            $rows  = [];
            $binds = [];
            foreach ( $new_user_metas as [ $uid, $key, $raw_val ] ) {
                $rows[]  = '(%d, %s, %s)';
                $binds[] = $uid;
                $binds[] = $key;
                $binds[] = is_array( $raw_val ) || is_object( $raw_val ) ? serialize( $raw_val ) : (string) $raw_val;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES " . implode( ', ', $rows ),
                    ...$binds
                )
            );
        }

        $state['offset']    = $new_offset;
        $state['row_num']   = $row_num;
        $state['processed'] = $processed;
        $state['created']   = $created;
        $state['updated']   = $updated;
        $state['errors']    = $errors;

        if ( $processed >= $state['total'] ) {
            $state['phase'] = 'done';
            @unlink( $state['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            delete_option( self::import_state_key() );
        } else {
            update_option( self::import_state_key(), $state, false );
        }

        wp_send_json_success( $state );
    }

    public static function ajax_import_cancel() {
        check_ajax_referer( 'jb3_import_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permiso.', 'jb3-members' ) );
        }
        $state = get_option( self::import_state_key() );
        if ( $state && ! empty( $state['file'] ) && file_exists( $state['file'] ) ) {
            @unlink( $state['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        delete_option( self::import_state_key() );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Sync Mailchimp: page + browser-driven chunk loop
    // -------------------------------------------------------------------------

    public static function page_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $has_mc     = get_option( 'jb3_mc_api_key' ) && get_option( 'jb3_mc_list_id' );
        $state      = JB3_Sync::get_state();
        $nonce      = wp_create_nonce( 'jb3_sync_nonce' );
        $wp_members = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );
        $wp_admins  = count( get_users( [ 'role' => 'administrator', 'number' => -1, 'fields' => 'ID' ] ) );
        ?>
        <div class="wrap" id="jb3-sync-wrap">
            <h1><?php esc_html_e( 'Sincronizar con Mailchimp', 'jb3-members' ); ?></h1>

            <?php if ( ! $has_mc ) : ?>
                <div class="notice notice-error">
                    <p><?php echo wp_kses(
                        sprintf(
                            __( 'Mailchimp no está configurado. <a href="%s">Ir a Configuración</a>.', 'jb3-members' ),
                            esc_url( admin_url( 'admin.php?page=jb3-settings' ) )
                        ),
                        [ 'a' => [ 'href' => [] ] ]
                    ); ?></p>
                </div>
            <?php else : ?>

                <div class="notice notice-info inline" style="margin:16px 0;padding:12px 16px;">
                    <p style="margin:0 0 8px;"><strong><?php esc_html_e( '¿Qué hace esta sincronización?', 'jb3-members' ); ?></strong></p>
                    <ul style="list-style:disc;margin:0 0 0 20px;">
                        <li><?php esc_html_e( 'Fase 1 — WordPress → Mailchimp: sube todos los miembros aprobados. Con newsletter activo → suscribe. Con newsletter inactivo → archiva.', 'jb3-members' ); ?></li>
                        <li><?php esc_html_e( 'Los administradores siempre se sincronizan (para recibir emails de prueba).', 'jb3-members' ); ?></li>
                        <li><?php esc_html_e( 'Fase 2 — Mailchimp → Archivo: contactos en Mailchimp sin cuenta en WordPress quedan archivados (recuperables, no se borran definitivamente).', 'jb3-members' ); ?></li>
                    </ul>
                    <p style="margin:10px 0 0;background:#fff3cd;border:1px solid #ffc107;padding:8px 10px;">
                        <strong><?php esc_html_e( 'Mantené el browser abierto durante el proceso.', 'jb3-members' ); ?></strong>
                        <?php esc_html_e( 'Si se corta la conexión, el progreso queda guardado — recargá la página y hacé clic en "Reanudar".', 'jb3-members' ); ?>
                    </p>
                    <p style="margin:6px 0 0;color:#555;">
                        <?php printf(
                            esc_html__( 'Base actual: %1$d miembros · %2$d administradores.', 'jb3-members' ),
                            $wp_members, $wp_admins
                        ); ?>
                    </p>
                </div>

                <!-- Progress bars -->
                <div id="jb3-sync-progress" style="display:none;margin:20px 0;">
                    <p style="margin:0 0 4px;font-weight:600;"><?php esc_html_e( 'Fase 1: WordPress → Mailchimp', 'jb3-members' ); ?></p>
                    <div style="background:#e0e0e0;height:22px;width:100%;max-width:520px;overflow:hidden;">
                        <div id="jb3-bar-wp" style="background:#0073aa;height:22px;width:0%;transition:width .5s;"></div>
                    </div>
                    <p id="jb3-label-wp" style="margin:4px 0 16px;color:#555;font-size:13px;"></p>

                    <p style="margin:0 0 4px;font-weight:600;"><?php esc_html_e( 'Fase 2: Mailchimp → Archivo', 'jb3-members' ); ?></p>
                    <div style="background:#e0e0e0;height:22px;width:100%;max-width:520px;overflow:hidden;">
                        <div id="jb3-bar-mc" style="background:#e65100;height:22px;width:0%;transition:width .5s;"></div>
                    </div>
                    <p id="jb3-label-mc" style="margin:4px 0 0;color:#555;font-size:13px;"></p>
                    <p id="jb3-last-update" style="margin:10px 0 0;color:#888;font-size:12px;"></p>
                </div>

                <!-- Network error / reconnect -->
                <div id="jb3-sync-error" style="display:none;">
                    <div class="notice notice-warning inline" style="padding:10px 14px;">
                        <p style="margin:0;"><?php esc_html_e( 'Se interrumpió la conexión. El progreso está guardado — restablecé la conexión y hacé clic en Reanudar.', 'jb3-members' ); ?>
                        &nbsp;<button id="jb3-btn-resume" class="button button-primary" style="margin-left:6px;"><?php esc_html_e( 'Reanudar', 'jb3-members' ); ?></button></p>
                    </div>
                </div>

                <!-- Results -->
                <div id="jb3-sync-results" style="display:none;margin:20px 0;"></div>

                <!-- Errors -->
                <div id="jb3-sync-errors" style="display:none;margin-top:12px;"></div>

                <!-- Buttons -->
                <p style="margin-top:20px;">
                    <button id="jb3-btn-start" class="button button-primary button-large">
                        <?php esc_html_e( 'Iniciar sincronización', 'jb3-members' ); ?>
                    </button>
                    <button id="jb3-btn-cancel" class="button button-large" style="display:none;margin-left:8px;">
                        <?php esc_html_e( 'Cancelar', 'jb3-members' ); ?>
                    </button>
                    <span id="jb3-sync-spinner" class="spinner" style="display:none;float:none;vertical-align:middle;margin-left:8px;"></span>
                </p>

                <script>
                (function () {
                    var ajaxurl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                    var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
                    var state    = <?php echo wp_json_encode( $state ?: null ); ?>;
                    var running  = false;

                    var btnStart  = document.getElementById('jb3-btn-start');
                    var btnCancel = document.getElementById('jb3-btn-cancel');
                    var btnResume = document.getElementById('jb3-btn-resume');
                    var spinner   = document.getElementById('jb3-sync-spinner');
                    var divProg   = document.getElementById('jb3-sync-progress');
                    var divRes    = document.getElementById('jb3-sync-results');
                    var divErr    = document.getElementById('jb3-sync-errors');
                    var divNetErr = document.getElementById('jb3-sync-error');
                    var barWp     = document.getElementById('jb3-bar-wp');
                    var labelWp   = document.getElementById('jb3-label-wp');
                    var barMc     = document.getElementById('jb3-bar-mc');
                    var labelMc   = document.getElementById('jb3-label-mc');
                    var lastUpd   = document.getElementById('jb3-last-update');

                    function escHtml(s) {
                        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    }
                    function pct(done, total) {
                        return total > 0 ? Math.min(100, Math.round(done * 100 / total)) : 0;
                    }
                    function setSpinner(on) {
                        spinner.style.display = on ? 'inline-block' : 'none';
                        spinner.className     = on ? 'spinner is-active' : 'spinner';
                    }
                    function post(action, cb) {
                        var fd = new FormData();
                        fd.append('action', action);
                        fd.append('_ajax_nonce', nonce);
                        return fetch(ajaxurl, { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(cb);
                    }

                    // ----------------------------------------------------------------
                    // Render current state into the UI
                    // ----------------------------------------------------------------
                    function renderState() {
                        if (!state) {
                            divProg.style.display   = 'none';
                            divRes.style.display    = 'none';
                            divErr.style.display    = 'none';
                            divNetErr.style.display = 'none';
                            btnStart.disabled       = false;
                            btnStart.textContent    = <?php echo wp_json_encode( __( 'Iniciar sincronización', 'jb3-members' ) ); ?>;
                            btnCancel.style.display = 'none';
                            setSpinner(false);
                            return;
                        }

                        var phase = state.phase;

                        if (phase === 'done') {
                            divProg.style.display   = 'none';
                            divNetErr.style.display = 'none';
                            var html = '<div class="notice notice-success inline" style="padding:12px 16px;">'
                                + '<p style="margin:0;"><strong><?php echo esc_js( __( 'Sincronización completada.', 'jb3-members' ) ); ?></strong></p>'
                                + '<p style="margin:6px 0 0;">'
                                + escHtml(state.subscribed) + ' <?php echo esc_js( __( 'suscripto(s) en Mailchimp', 'jb3-members' ) ); ?>.<br>'
                                + escHtml(state.unsubscribed) + ' <?php echo esc_js( __( 'sin suscripción', 'jb3-members' ) ); ?>.<br>'
                                + (state.archived > 0 ? escHtml(state.archived) + ' <?php echo esc_js( __( 'contacto(s) sin cuenta WP archivados en Mailchimp', 'jb3-members' ) ); ?>.<br>' : '')
                                + '<span style="color:#888;font-size:12px;"><?php echo esc_js( __( 'Iniciada:', 'jb3-members' ) ); ?> '
                                + escHtml(state.started_at) + ' · <?php echo esc_js( __( 'Completada:', 'jb3-members' ) ); ?> ' + escHtml(state.updated_at) + '</span>'
                                + '</p></div>';
                            divRes.innerHTML        = html;
                            divRes.style.display    = 'block';
                            btnStart.disabled       = false;
                            btnStart.textContent    = <?php echo wp_json_encode( __( 'Sincronizar de nuevo', 'jb3-members' ) ); ?>;
                            btnCancel.style.display = 'none';
                            setSpinner(false);
                            running = false;

                        } else {
                            divProg.style.display   = 'block';
                            divRes.style.display    = 'none';
                            divNetErr.style.display = 'none';
                            btnStart.disabled       = true;
                            btnCancel.style.display = 'inline-block';
                            setSpinner(true);

                            // Phase 1 bar
                            var p1done = (phase === 'mc_to_wp');
                            barWp.style.width   = (p1done ? 100 : pct(state.wp_offset, state.wp_total)) + '%';
                            labelWp.textContent = (p1done
                                ? '<?php echo esc_js( __( 'Completa', 'jb3-members' ) ); ?> — '
                                : (state.wp_offset + ' / ' + state.wp_total + ' — '))
                                + escHtml(state.subscribed) + ' <?php echo esc_js( __( 'suscriptos', 'jb3-members' ) ); ?>'
                                + (state.unsubscribed > 0 ? ', ' + escHtml(state.unsubscribed) + ' <?php echo esc_js( __( 'sin suscripción', 'jb3-members' ) ); ?>' : '');

                            // Phase 2 bar
                            if (phase === 'mc_to_wp') {
                                barMc.style.width   = (state.mc_total > 0 ? pct(state.mc_offset, state.mc_total) : 0) + '%';
                                labelMc.textContent = (state.mc_total > 0
                                    ? state.mc_offset + ' / ' + state.mc_total
                                    : '<?php echo esc_js( __( 'Verificando…', 'jb3-members' ) ); ?>')
                                    + ' — ' + escHtml(state.archived) + ' <?php echo esc_js( __( 'archivados', 'jb3-members' ) ); ?>';
                            } else {
                                barMc.style.width   = '0%';
                                labelMc.textContent = '<?php echo esc_js( __( 'Pendiente (empieza al completar Fase 1)', 'jb3-members' ) ); ?>';
                            }

                            if (state.updated_at) {
                                lastUpd.textContent = '<?php echo esc_js( __( 'Última actualización:', 'jb3-members' ) ); ?> ' + state.updated_at;
                            }
                        }

                        // Errors
                        if (state.errors && state.errors.length > 0) {
                            var eHtml = '<details><summary style="cursor:pointer;color:#c0392b;">'
                                + state.errors.length + ' <?php echo esc_js( __( 'error(s) — clic para ver', 'jb3-members' ) ); ?>'
                                + '</summary><ul style="margin:8px 0 0 20px;list-style:disc;">';
                            state.errors.forEach(function(e){ eHtml += '<li>' + escHtml(e) + '</li>'; });
                            eHtml += '</ul></details>';
                            divErr.innerHTML     = eHtml;
                            divErr.style.display = 'block';
                        } else {
                            divErr.style.display = 'none';
                        }
                    }

                    // ----------------------------------------------------------------
                    // Chunk loop: browser calls jb3_sync_chunk until done
                    // ----------------------------------------------------------------
                    function processChunk() {
                        if (!running) return;
                        post('jb3_sync_chunk', function(resp) {
                            if (!running) return;
                            if (!resp || !resp.success) {
                                // Server returned an error — show reconnect UI
                                showNetworkError();
                                return;
                            }
                            state = resp.data;
                            renderState();
                            if (state.phase !== 'done') {
                                setTimeout(processChunk, 0);
                            }
                        }).catch(function() {
                            if (!running) return;
                            showNetworkError();
                        });
                    }

                    function showNetworkError() {
                        running = false;
                        setSpinner(false);
                        btnCancel.style.display = 'none';
                        btnStart.disabled       = true;
                        divNetErr.style.display = 'block';
                    }

                    // ----------------------------------------------------------------
                    // Button handlers
                    // ----------------------------------------------------------------
                    btnStart.addEventListener('click', function () {
                        btnStart.disabled    = true;
                        btnStart.textContent = <?php echo wp_json_encode( __( 'Iniciando…', 'jb3-members' ) ); ?>;
                        setSpinner(true);
                        divRes.style.display    = 'none';
                        divNetErr.style.display = 'none';

                        post('jb3_sync_start', function(resp) {
                            if (!resp || !resp.success) {
                                btnStart.disabled = false;
                                setSpinner(false);
                                alert('<?php echo esc_js( __( 'Error al iniciar:', 'jb3-members' ) ); ?> ' + (resp ? resp.data : ''));
                                return;
                            }
                            state   = resp.data;
                            running = true;
                            renderState();
                            processChunk();
                        }).catch(function() {
                            btnStart.disabled = false;
                            setSpinner(false);
                            alert('<?php echo esc_js( __( 'Error de red al iniciar. Verificá tu conexión.', 'jb3-members' ) ); ?>');
                        });
                    });

                    btnCancel.addEventListener('click', function () {
                        running = false;
                        post('jb3_sync_cancel', function() {
                            state = null;
                            renderState();
                        }).catch(function() {
                            state = null;
                            renderState();
                        });
                    });

                    btnResume.addEventListener('click', function () {
                        divNetErr.style.display = 'none';
                        btnStart.disabled       = true;
                        btnCancel.style.display = 'inline-block';
                        setSpinner(true);
                        running = true;
                        processChunk();
                    });

                    // ----------------------------------------------------------------
                    // On page load: if a sync was in progress, offer Resume
                    // ----------------------------------------------------------------
                    if (state) {
                        renderState();
                        if (state.phase !== 'done') {
                            // Don't auto-resume — show the reconnect button so user can
                            // confirm they're ready before the chunk loop kicks off
                            btnStart.disabled       = true;
                            btnCancel.style.display = 'inline-block';
                            divNetErr.style.display = 'block';
                            divProg.style.display   = 'block';
                        }
                    }
                })();
                </script>

            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Guard: preserve administrator role
    // -------------------------------------------------------------------------

    /**
     * Fires when WordPress's native Role dropdown saves a new role via set_role().
     * set_role() replaces ALL roles — if an admin's role is accidentally changed
     * to something else, this hook immediately adds 'administrator' back.
     *
     * @param int    $user_id   The user whose role changed.
     * @param string $new_role  The role that was just set.
     * @param array  $old_roles The roles the user had before.
     */
    public static function guard_admin_role( int $user_id, string $new_role, array $old_roles ) {
        if ( in_array( 'administrator', $old_roles, true ) && $new_role !== 'administrator' ) {
            $user = new WP_User( $user_id );
            $user->add_role( 'administrator' );
        }
    }

    // -------------------------------------------------------------------------
    // Import: admin page (browser-driven chunk loop)
    // -------------------------------------------------------------------------

    public static function page_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $nonce          = wp_create_nonce( 'jb3_import_ajax' );
        $ajax_url       = admin_url( 'admin-ajax.php' );
        $existing_state = get_option( self::import_state_key() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Importar Usuarios', 'jb3-members' ); ?></h1>

            <?php if ( isset( $_GET['nicknames_fixed'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            /* translators: %d: cantidad de usuarios arreglados */
                            esc_html__( 'Alias completado para %d usuario(s) que no lo tenían.', 'jb3-members' ),
                            (int) $_GET['nicknames_fixed']
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <p>
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'jb3-import', 'action' => 'jb3_fix_nicknames' ], admin_url( 'admin.php' ) ), 'jb3_fix_nicknames' ) ); ?>" class="button">
                    <?php esc_html_e( 'Completar alias faltantes', 'jb3-members' ); ?>
                </a>
                <span class="description"> <?php esc_html_e( '— arregla el error "tenés que ingresar un alias" en usuarios importados antes de este fix.', 'jb3-members' ); ?></span>
            </p>

            <!-- Upload form — hidden while import runs -->
            <div id="jb3im-upload"<?php echo $existing_state ? ' style="display:none"' : ''; ?>>
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
                                [ 'Newsletter',         __( 'Newsletter (Sí / No)', 'jb3-members' ) ],
                                [ 'Newsletters',        __( 'Newsletters por País (nombres separados por coma)', 'jb3-members' ) ],
                                [ 'Intereses',          __( 'Intereses (separados por coma)', 'jb3-members' ) ],
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

                <form id="jb3im-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="jb3_csv"><?php esc_html_e( 'Archivo CSV', 'jb3-members' ); ?></label></th>
                            <td><input type="file" id="jb3_csv" name="jb3_csv" accept=".csv,text/csv" required></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" id="jb3im-btn-start" class="button button-primary button-large">
                            <?php esc_html_e( 'Importar', 'jb3-members' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Progress panel — visible while import runs -->
            <div id="jb3im-progress"<?php echo ! $existing_state ? ' style="display:none"' : ''; ?>>
                <progress id="jb3im-bar" value="0" max="100" style="width:100%;max-width:600px;height:22px;display:block;margin-bottom:8px;"></progress>
                <p id="jb3im-txt" style="margin:0 0 12px;"></p>
                <button id="jb3im-btn-cancel" class="button button-secondary">
                    <?php esc_html_e( 'Cancelar', 'jb3-members' ); ?>
                </button>
            </div>

            <!-- Results panel — shown after completion or cancel -->
            <div id="jb3im-results"></div>
        </div>

        <script>
        (function() {
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
            var running = false;
            var state   = null;

            var divUpload   = document.getElementById('jb3im-upload');
            var divProgress = document.getElementById('jb3im-progress');
            var divResults  = document.getElementById('jb3im-results');
            var bar         = document.getElementById('jb3im-bar');
            var txt         = document.getElementById('jb3im-txt');
            var btnCancel   = document.getElementById('jb3im-btn-cancel');
            var btnStart    = document.getElementById('jb3im-btn-start');

            function ajax(body, onSuccess, onError) {
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (resp && resp.success) { onSuccess(resp.data); }
                        else { onError(resp && resp.data ? resp.data : '<?php echo esc_js( __( 'Error desconocido.', 'jb3-members' ) ); ?>'); }
                    })
                    .catch(function(e) { onError('<?php echo esc_js( __( 'Error de red:', 'jb3-members' ) ); ?> ' + e.message); });
            }

            function renderState(s) {
                var pct = s.total > 0 ? Math.round(s.processed / s.total * 100) : 0;
                bar.value = pct;
                var errStr = s.errors.length > 0 ? ', ' + s.errors.length + ' error(es)' : '';
                txt.textContent = s.processed + ' / ' + s.total + ' (' + pct + '%) — '
                    + s.created + ' creado(s), ' + s.updated + ' actualizado(s)' + errStr;
            }

            function showResults(s, cancelled) {
                divUpload.style.display   = '';
                divProgress.style.display = 'none';

                var cls  = (!cancelled && s.errors.length === 0) ? 'success' : 'warning';
                var html = '<div class="notice notice-' + cls + ' is-dismissible" style="margin-top:16px;"><p>';

                if (cancelled) {
                    html += '<strong><?php echo esc_js( __( 'Importación cancelada.', 'jb3-members' ) ); ?></strong> ';
                    html += s.processed + ' <?php echo esc_js( __( 'filas procesadas', 'jb3-members' ) ); ?>: ';
                } else {
                    html += '<strong>';
                }
                html += s.created + ' <?php echo esc_js( __( 'usuario(s) creado(s)', 'jb3-members' ) ); ?>, '
                      + s.updated + ' <?php echo esc_js( __( 'actualizado(s).', 'jb3-members' ) ); ?>';
                if (!cancelled) { html += '</strong>'; }
                html += '</p>';

                if (s.errors.length > 0) {
                    html += '<p><strong><?php echo esc_js( __( 'Errores:', 'jb3-members' ) ); ?></strong></p>'
                          + '<ul style="list-style:disc;margin-left:20px;">';
                    var limit = Math.min(s.errors.length, 200);
                    for (var i = 0; i < limit; i++) {
                        html += '<li>' + s.errors[i].replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</li>';
                    }
                    if (s.errors.length > 200) {
                        html += '<li>... y ' + (s.errors.length - 200) + ' más.</li>';
                    }
                    html += '</ul>';
                }
                html += '</div>';
                divResults.innerHTML = html;
            }

            function processChunk() {
                if (!running) return;
                var body = new FormData();
                body.append('action', 'jb3_import_chunk');
                body.append('nonce', nonce);
                ajax(body, function(s) {
                    if (!running) return;
                    state = s;
                    renderState(s);
                    if (s.phase === 'done') {
                        running = false;
                        showResults(s, false);
                    } else {
                        setTimeout(processChunk, 0);
                    }
                }, function(err) {
                    if (!running) return;
                    running = false;
                    txt.textContent = '<?php echo esc_js( __( 'Error:', 'jb3-members' ) ); ?> ' + err;
                    btnCancel.textContent = '<?php echo esc_js( __( 'Cerrar', 'jb3-members' ) ); ?>';
                });
            }

            // Form submit — upload CSV and start.
            document.getElementById('jb3im-form').addEventListener('submit', function(e) {
                e.preventDefault();
                var fileInput = document.getElementById('jb3_csv');
                if (!fileInput.files.length) return;

                btnStart.disabled = true;
                divUpload.style.display   = 'none';
                divProgress.style.display = '';
                divResults.innerHTML      = '';
                bar.value = 0;
                txt.textContent = '<?php echo esc_js( __( 'Subiendo archivo…', 'jb3-members' ) ); ?>';
                running = true;

                var body = new FormData();
                body.append('action', 'jb3_import_start');
                body.append('nonce', nonce);
                body.append('jb3_csv', fileInput.files[0], fileInput.files[0].name);

                ajax(body, function(s) {
                    state = s;
                    renderState(s);
                    processChunk();
                }, function(err) {
                    running = false;
                    btnStart.disabled = false;
                    divUpload.style.display   = '';
                    divProgress.style.display = 'none';
                    divResults.innerHTML = '<div class="notice notice-error"><p>' + err + '</p></div>';
                });
            });

            // Cancel button.
            btnCancel.addEventListener('click', function() {
                if (!running) {
                    divProgress.style.display = 'none';
                    return;
                }
                running = false;
                btnCancel.disabled = true;
                var body = new FormData();
                body.append('action', 'jb3_import_cancel');
                body.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .finally(function() {
                        btnCancel.disabled = false;
                        showResults(state || { processed: 0, created: 0, updated: 0, errors: [] }, true);
                    });
            });

            <?php if ( $existing_state ) : ?>
            // Resume in-progress import from a previous session.
            state = <?php echo wp_json_encode( $existing_state ); ?>;
            renderState(state);
            if (confirm(
                '<?php echo esc_js( __( 'Hay una importación en curso. ¿Querés reanudarla?', 'jb3-members' ) ); ?>'
                + ' (' + state.processed + '/' + state.total + ' <?php echo esc_js( __( 'filas procesadas', 'jb3-members' ) ); ?>)'
            )) {
                running = true;
                processChunk();
            }
            <?php endif; ?>
        })();
        </script>
        <?php
    }

    // =========================================================================
    // Bulk delete: page + browser-driven chunk loop
    // =========================================================================

    private static function delete_state_key() {
        return 'jb3_delete_state_' . get_current_user_id();
    }

    public static function ajax_delete_start() {
        check_ajax_referer( 'jb3_delete_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }

        $filter = sanitize_text_field( wp_unslash( $_POST['filter'] ?? 'all' ) );
        if ( ! in_array( $filter, [ 'all', 'pending', 'approved', 'list' ], true ) ) {
            $filter = 'all';
        }

        if ( 'list' === $filter ) {
            $state = get_option( self::delete_state_key() );
            if ( ! is_array( $state ) || 'ready' !== ( $state['phase'] ?? '' ) || 'list' !== ( $state['filter'] ?? '' ) ) {
                wp_send_json_error( __( 'Subí y previsualizá un CSV antes de iniciar el borrado.', 'jb3-members' ) );
            }
            $state['phase'] = 'running';
            update_option( self::delete_state_key(), $state, false );
            wp_send_json_success( $state );
        }

        $query = [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ];
        if ( 'pending' === $filter ) {
            $query['meta_key']   = 'jb3_status';
            $query['meta_value'] = 'pending';
        } elseif ( 'approved' === $filter ) {
            $query['meta_key']   = 'jb3_status';
            $query['meta_value'] = 'approved';
        }

        $total = count( get_users( $query ) );

        $state = [
            'filter'  => $filter,
            'total'   => $total,
            'deleted' => 0,
            'errors'  => [],
            'phase'   => 'running',
        ];

        update_option( self::delete_state_key(), $state, false );
        wp_send_json_success( $state );
    }

    public static function ajax_delete_chunk() {
        check_ajax_referer( 'jb3_delete_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }

        global $wpdb;

        $state = get_option( self::delete_state_key() );
        if ( ! is_array( $state ) || 'running' !== $state['phase'] ) {
            wp_send_json_error( 'No hay borrado en curso.' );
        }

        $filter = $state['filter'];

        if ( 'list' === $filter ) {
            $user_ids = array_splice( $state['list_ids'], 0, 50 );
        } else {
            $query = [ 'role' => 'jb3_member', 'number' => 500, 'fields' => 'ID', 'orderby' => 'ID', 'order' => 'ASC' ];
            if ( 'pending' === $filter ) {
                $query['meta_key']   = 'jb3_status';
                $query['meta_value'] = 'pending';
            } elseif ( 'approved' === $filter ) {
                $query['meta_key']   = 'jb3_status';
                $query['meta_value'] = 'approved';
            }
            $user_ids = get_users( $query );
        }

        if ( empty( $user_ids ) ) {
            $state['phase'] = 'done';
            delete_option( self::delete_state_key() );
            wp_send_json_success( $state );
        }

        // Never delete administrators (safety guard).
        $safe_ids = array_values( array_filter(
            array_map( 'intval', $user_ids ),
            fn( $id ) => ! user_can( $id, 'manage_options' )
        ) );

        if ( ! empty( $safe_ids ) ) {
            // Modo lista: a diferencia del borrado por rol/estado, acá se archiva en
            // Mailchimp de inmediato (no se difiere a la sincronización masiva) — es
            // el requisito principal de este flujo y el volumen esperado es acotado.
            $emails_to_archive = [];
            if ( 'list' === $filter ) {
                foreach ( get_users( [ 'include' => $safe_ids, 'fields' => [ 'ID', 'user_email' ] ] ) as $u ) {
                    $emails_to_archive[] = $u->user_email;
                }
            }

            $ph = implode( ',', array_fill( 0, count( $safe_ids ), '%d' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id IN ($ph)", ...$safe_ids ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->users} WHERE ID IN ($ph)", ...$safe_ids ) );
            foreach ( $safe_ids as $id ) {
                clean_user_cache( $id );
            }

            foreach ( $emails_to_archive as $email ) {
                JB3_Mailchimp::archive_contact( $email );
            }

            $state['deleted'] += count( $safe_ids );
        }

        update_option( self::delete_state_key(), $state, false );
        wp_send_json_success( $state );
    }

    public static function ajax_delete_cancel() {
        check_ajax_referer( 'jb3_delete_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado.' );
        }
        delete_option( self::delete_state_key() );
        wp_send_json_success();
    }

    public static function ajax_delete_list_upload() {
        check_ajax_referer( 'jb3_delete_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Sin permiso.', 'jb3-members' ) );
        }

        $upload = $_FILES['jb3_delete_csv'] ?? null;
        if ( ! $upload || $upload['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'Error al subir el archivo.', 'jb3-members' ) );
        }
        if ( strtolower( pathinfo( $upload['name'], PATHINFO_EXTENSION ) ) !== 'csv' ) {
            wp_send_json_error( __( 'El archivo debe tener extensión .csv', 'jb3-members' ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $upload['tmp_name'], 'r' );
        if ( ! $handle ) {
            wp_send_json_error( __( 'No se pudo leer el archivo.', 'jb3-members' ) );
        }

        $first_line = fgets( $handle );
        if ( $first_line === false || trim( $first_line ) === '' ) {
            fclose( $handle );
            wp_send_json_error( __( 'El archivo está vacío.', 'jb3-members' ) );
        }

        $first_line = ltrim( $first_line, "\xEF\xBB\xBF" ); // Strip UTF-8 BOM.
        $delimiter  = substr_count( $first_line, ';' ) >= substr_count( $first_line, ',' ) ? ';' : ',';
        $headers    = array_map( fn( $h ) => strtolower( trim( $h ) ), str_getcsv( $first_line, $delimiter ) );
        $email_idx  = array_search( 'email', $headers, true );

        if ( false === $email_idx ) {
            fclose( $handle );
            wp_send_json_error( __( 'El CSV debe tener una columna "Email".', 'jb3-members' ) );
        }

        // Sanitized + deduplicated emails found in the file.
        $emails = [];
        while ( ( $raw = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $email = sanitize_email( trim( $raw[ $email_idx ] ?? '' ) );
            if ( is_email( $email ) ) {
                $emails[ strtolower( $email ) ] = true;
            }
        }
        fclose( $handle );
        $emails = array_keys( $emails );

        if ( empty( $emails ) ) {
            wp_send_json_error( __( 'No se encontraron emails válidos en el archivo.', 'jb3-members' ) );
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
        $found_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, LOWER(user_email) AS lc_email FROM {$wpdb->users} WHERE LOWER(user_email) IN ($placeholders)",
                ...$emails
            )
        );

        $found_by_email = [];
        foreach ( $found_rows as $row ) {
            $found_by_email[ $row->lc_email ] = (int) $row->ID;
        }

        $to_delete_ids  = [];
        $skipped_admins = 0;
        $not_found      = [];

        foreach ( $emails as $email ) {
            if ( ! isset( $found_by_email[ $email ] ) ) {
                $not_found[] = $email;
                continue;
            }
            $uid = $found_by_email[ $email ];
            if ( user_can( $uid, 'manage_options' ) ) {
                $skipped_admins++;
                continue;
            }
            $to_delete_ids[] = $uid;
        }

        $state = [
            'filter'   => 'list',
            'total'    => count( $to_delete_ids ),
            'list_ids' => $to_delete_ids,
            'deleted'  => 0,
            'errors'   => [],
            'phase'    => 'ready',
        ];
        update_option( self::delete_state_key(), $state, false );

        wp_send_json_success( [
            'total'           => count( $to_delete_ids ),
            'skipped_admins'  => $skipped_admins,
            'not_found'       => array_slice( $not_found, 0, 200 ),
            'not_found_total' => count( $not_found ),
        ] );
    }

    public static function page_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso.', 'jb3-members' ) );
        }

        $nonce         = wp_create_nonce( 'jb3_delete_ajax' );
        $state         = get_option( self::delete_state_key() );
        $total_members = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID' ] ) );
        $total_pending = count( get_users( [ 'role' => 'jb3_member', 'number' => -1, 'fields' => 'ID', 'meta_key' => 'jb3_status', 'meta_value' => 'pending' ] ) );
        $total_approved  = $total_members - $total_pending;
        $current_filter  = $state['filter'] ?? 'all';
        $list_ready       = $state && 'ready' === ( $state['phase'] ?? '' ) && 'list' === $current_filter;
        $list_ready_total = $list_ready ? (int) $state['total'] : 0;
        ?>
        <div class="wrap" id="jb3-delete-wrap">
            <h1><?php esc_html_e( 'Borrar Miembros en Lote', 'jb3-members' ); ?></h1>

            <div class="notice notice-warning inline" style="margin:16px 0;padding:12px 16px;">
                <p style="margin:0;"><strong><?php esc_html_e( '⚠ Esta acción es irreversible.', 'jb3-members' ); ?></strong>
                <?php esc_html_e( 'Los administradores nunca se borran. Al borrar "Todos/Pendientes/Aprobados" el archivado en Mailchimp se hace luego, corriendo la Sincronización. Al borrar por "Lista CSV" el archivado en Mailchimp es inmediato.', 'jb3-members' ); ?></p>
            </div>

            <table class="form-table" style="max-width:480px;">
                <tr>
                    <th><?php esc_html_e( 'Qué borrar', 'jb3-members' ); ?></th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="jb3_delete_filter" value="all" <?php checked( $current_filter, 'all' ); ?>>
                            <?php printf( esc_html__( 'Todos los miembros (%d)', 'jb3-members' ), $total_members ); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="jb3_delete_filter" value="pending" <?php checked( $current_filter, 'pending' ); ?>>
                            <?php printf( esc_html__( 'Solo pendientes (%d)', 'jb3-members' ), $total_pending ); ?>
                        </label>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="jb3_delete_filter" value="approved" <?php checked( $current_filter, 'approved' ); ?>>
                            <?php printf( esc_html__( 'Solo aprobados (%d)', 'jb3-members' ), $total_approved ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="jb3_delete_filter" value="list" <?php checked( $current_filter, 'list' ); ?>>
                            <?php esc_html_e( 'Lista CSV (subir archivo)', 'jb3-members' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <div id="jb3-delete-list-block" style="display:none;max-width:520px;margin:0 0 20px;padding:12px 16px;background:#f6f7f7;border:1px solid #dcdcde;">
                <p style="margin-top:0;"><?php esc_html_e( 'CSV con una columna "Email". Los usuarios encontrados se borrarán y se archivarán en Mailchimp de inmediato (no se difiere a la sincronización).', 'jb3-members' ); ?></p>
                <p>
                    <input type="file" id="jb3-delete-list-file" accept=".csv,text/csv">
                    <button type="button" id="jb3-delete-list-btn-preview" class="button">
                        <?php esc_html_e( 'Cargar y previsualizar', 'jb3-members' ); ?>
                    </button>
                    <span id="jb3-delete-list-spinner" class="spinner" style="display:none;float:none;vertical-align:middle;margin-left:8px;"></span>
                </p>
                <div id="jb3-delete-list-preview"<?php echo $list_ready ? '' : ' style="display:none;"'; ?>>
                    <?php if ( $list_ready ) : ?>
                        <p style="margin:8px 0;"><strong><?php printf( esc_html( _n( '%d usuario listo para borrar.', '%d usuarios listos para borrar.', $list_ready_total, 'jb3-members' ) ), $list_ready_total ); ?></strong> <?php esc_html_e( 'Presioná "Iniciar borrado" para confirmar.', 'jb3-members' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="jb3-delete-progress" style="display:none;margin:20px 0;">
                <div style="background:#e0e0e0;height:22px;width:100%;max-width:520px;overflow:hidden;">
                    <div id="jb3-delete-bar" style="background:#b32d2e;height:22px;width:0%;transition:width .4s;"></div>
                </div>
                <p id="jb3-delete-label" style="margin:6px 0 0;color:#555;font-size:13px;"></p>
            </div>

            <div id="jb3-delete-result" style="display:none;margin:20px 0;"></div>
            <div id="jb3-delete-errors" style="display:none;margin-top:10px;"></div>

            <p style="margin-top:20px;">
                <button id="jb3-delete-btn-start" class="button button-primary button-large" style="background:#b32d2e;border-color:#8c1c1c;">
                    <?php esc_html_e( 'Iniciar borrado', 'jb3-members' ); ?>
                </button>
                <button id="jb3-delete-btn-cancel" class="button button-large" style="display:none;margin-left:8px;">
                    <?php esc_html_e( 'Cancelar', 'jb3-members' ); ?>
                </button>
                <span id="jb3-delete-spinner" class="spinner" style="display:none;float:none;vertical-align:middle;margin-left:8px;"></span>
            </p>
        </div>

        <script>
        (function () {
            var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
            var running = false;
            var state   = <?php echo wp_json_encode( $state ?: null ); ?>;

            var btnStart  = document.getElementById('jb3-delete-btn-start');
            var btnCancel = document.getElementById('jb3-delete-btn-cancel');
            var spinner   = document.getElementById('jb3-delete-spinner');
            var divProg   = document.getElementById('jb3-delete-progress');
            var bar       = document.getElementById('jb3-delete-bar');
            var label     = document.getElementById('jb3-delete-label');
            var divRes    = document.getElementById('jb3-delete-result');
            var divErr    = document.getElementById('jb3-delete-errors');

            var listBlock    = document.getElementById('jb3-delete-list-block');
            var listFile     = document.getElementById('jb3-delete-list-file');
            var btnPreview   = document.getElementById('jb3-delete-list-btn-preview');
            var listSpinner  = document.getElementById('jb3-delete-list-spinner');
            var divListPrev  = document.getElementById('jb3-delete-list-preview');
            var listTotal    = <?php echo (int) $list_ready_total; ?>;
            var listReady    = <?php echo $list_ready ? 'true' : 'false'; ?>;

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
            function setSpinner(on) {
                spinner.style.display = on ? 'inline-block' : 'none';
                spinner.className     = on ? 'spinner is-active' : 'spinner';
            }
            function updateBar(s) {
                var pct = s.total > 0 ? Math.min(100, Math.round(s.deleted * 100 / s.total)) : 100;
                bar.style.width  = pct + '%';
                label.textContent = s.deleted + ' / ' + s.total + ' <?php echo esc_js( __( 'borrados', 'jb3-members' ) ); ?>';
            }
            function showErrors(errs) {
                if (!errs || !errs.length) { divErr.style.display = 'none'; return; }
                divErr.style.display = 'block';
                divErr.innerHTML = '<details><summary style="cursor:pointer;color:#b32d2e;">'
                    + errs.length + ' <?php echo esc_js( __( 'errores', 'jb3-members' ) ); ?></summary><ul style="margin:.5rem 0 0 1.2rem;">'
                    + errs.map(function(e){ return '<li>' + escHtml(e) + '</li>'; }).join('')
                    + '</ul></details>';
            }
            function showDone(s) {
                divRes.style.display = 'block';
                divRes.innerHTML = '<div class="notice notice-success inline" style="padding:10px 14px;"><p style="margin:0;">'
                    + '<strong><?php echo esc_js( __( 'Completado.', 'jb3-members' ) ); ?></strong> '
                    + escHtml(s.deleted) + ' <?php echo esc_js( __( 'miembros borrados.', 'jb3-members' ) ); ?>'
                    + '</p></div>';
                showErrors(s.errors);
                btnStart.disabled       = false;
                btnStart.textContent    = <?php echo wp_json_encode( __( 'Borrar de nuevo', 'jb3-members' ) ); ?>;
                btnCancel.style.display = 'none';
                setSpinner(false);
                running = false;
                // Reload page to update member counts.
                setTimeout(function(){ location.reload(); }, 1500);
            }
            function post(action, extra, cb) {
                var fd = new FormData();
                fd.append('action', action);
                fd.append('nonce', nonce);
                for (var k in extra) { fd.append(k, extra[k]); }
                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(cb)
                    .catch(function(){ if (running) setTimeout(function(){ post(action, extra, cb); }, 3000); });
            }
            function processChunk() {
                if (!running) return;
                post('jb3_delete_chunk', {}, function(resp) {
                    if (!resp.success) { running = false; setSpinner(false); return; }
                    state = resp.data;
                    divProg.style.display = 'block';
                    updateBar(state);
                    if (state.phase === 'done') {
                        showDone(state);
                    } else {
                        processChunk();
                    }
                });
            }

            function updateFilterUI() {
                var filter = document.querySelector('input[name="jb3_delete_filter"]:checked').value;
                listBlock.style.display = (filter === 'list') ? 'block' : 'none';
            }
            Array.prototype.forEach.call(document.querySelectorAll('input[name="jb3_delete_filter"]'), function(r) {
                r.addEventListener('change', updateFilterUI);
            });
            updateFilterUI();

            listFile.addEventListener('change', function() {
                listReady = false;
                listTotal = 0;
                divListPrev.style.display = 'none';
            });

            btnPreview.addEventListener('click', function() {
                if (!listFile.files.length) {
                    alert(<?php echo wp_json_encode( __( 'Elegí un archivo CSV primero.', 'jb3-members' ) ); ?>);
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'jb3_delete_list_upload');
                fd.append('nonce', nonce);
                fd.append('jb3_delete_csv', listFile.files[0]);

                listReady = false;
                btnPreview.disabled = true;
                listSpinner.style.display = 'inline-block';
                listSpinner.className = 'spinner is-active';
                divListPrev.style.display = 'none';

                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        btnPreview.disabled = false;
                        listSpinner.style.display = 'none';
                        listSpinner.className = 'spinner';
                        if (!resp.success) {
                            divListPrev.style.display = 'block';
                            divListPrev.innerHTML = '<div class="notice notice-error inline" style="padding:8px 12px;"><p style="margin:0;">'
                                + escHtml(resp.data || <?php echo wp_json_encode( __( 'Error desconocido.', 'jb3-members' ) ); ?>) + '</p></div>';
                            return;
                        }
                        var d = resp.data;
                        listTotal = d.total;
                        listReady = d.total > 0;
                        var html = '<p style="margin:8px 0;"><strong>' + d.total
                            + ' <?php echo esc_js( __( 'usuario(s) serán borrados y archivados en Mailchimp.', 'jb3-members' ) ); ?></strong></p>';
                        if (d.skipped_admins > 0) {
                            html += '<p style="margin:4px 0;">' + d.skipped_admins
                                + ' <?php echo esc_js( __( 'administrador(es) excluido(s) (nunca se borran).', 'jb3-members' ) ); ?></p>';
                        }
                        if (d.not_found_total > 0) {
                            html += '<details><summary style="cursor:pointer;">' + d.not_found_total
                                + ' <?php echo esc_js( __( 'email(s) no encontrados en el sitio', 'jb3-members' ) ); ?></summary><ul style="margin:.5rem 0 0 1.2rem;">'
                                + d.not_found.map(function(e){ return '<li>' + escHtml(e) + '</li>'; }).join('')
                                + (d.not_found_total > d.not_found.length ? '<li>... y ' + (d.not_found_total - d.not_found.length) + ' <?php echo esc_js( __( 'más.', 'jb3-members' ) ); ?></li>' : '')
                                + '</ul></details>';
                        }
                        if (d.total === 0) {
                            html += '<p style="margin:4px 0;color:#b32d2e;"><?php echo esc_js( __( 'No hay usuarios para borrar con este archivo.', 'jb3-members' ) ); ?></p>';
                        }
                        divListPrev.style.display = 'block';
                        divListPrev.innerHTML = html;
                    })
                    .catch(function(e) {
                        btnPreview.disabled = false;
                        listSpinner.style.display = 'none';
                        listSpinner.className = 'spinner';
                        divListPrev.style.display = 'block';
                        divListPrev.innerHTML = '<div class="notice notice-error inline" style="padding:8px 12px;"><p style="margin:0;"><?php echo esc_js( __( 'Error de red:', 'jb3-members' ) ); ?> ' + escHtml(e.message) + '</p></div>';
                    });
            });

            btnStart.addEventListener('click', function() {
                if (running) return;
                var filter = document.querySelector('input[name="jb3_delete_filter"]:checked').value;
                if (filter === 'list' && !listReady) {
                    alert(<?php echo wp_json_encode( __( 'Subí y previsualizá un CSV antes de iniciar el borrado.', 'jb3-members' ) ); ?>);
                    return;
                }
                var label_text = {
                    all:      <?php echo wp_json_encode( __( 'todos los miembros', 'jb3-members' ) ); ?>,
                    pending:  <?php echo wp_json_encode( __( 'los miembros pendientes', 'jb3-members' ) ); ?>,
                    approved: <?php echo wp_json_encode( __( 'los miembros aprobados', 'jb3-members' ) ); ?>,
                    list:     listTotal + ' <?php echo esc_js( __( 'usuario(s) de la lista subida (se archivan en Mailchimp)', 'jb3-members' ) ); ?>'
                }[filter];
                if (!confirm(<?php echo wp_json_encode( __( '¿Estás seguro? Vas a borrar ', 'jb3-members' ) ); ?> + label_text + <?php echo wp_json_encode( __( '. Esta acción no se puede deshacer.', 'jb3-members' ) ); ?>)) return;

                running = true;
                btnStart.disabled       = true;
                btnCancel.style.display = 'inline-block';
                divRes.style.display    = 'none';
                divErr.style.display    = 'none';
                setSpinner(true);

                post('jb3_delete_start', { filter: filter }, function(resp) {
                    if (!resp.success) { running = false; setSpinner(false); btnStart.disabled = false; return; }
                    state = resp.data;
                    divProg.style.display = 'block';
                    updateBar(state);
                    processChunk();
                });
            });

            btnCancel.addEventListener('click', function() {
                running = false;
                setSpinner(false);
                btnStart.disabled       = false;
                btnCancel.style.display = 'none';
                post('jb3_delete_cancel', {}, function(){});
                divRes.style.display = 'block';
                divRes.innerHTML = '<div class="notice notice-warning inline" style="padding:10px 14px;"><p style="margin:0;">'
                    + <?php echo wp_json_encode( __( 'Borrado cancelado.', 'jb3-members' ) ); ?>
                    + (state ? ' ' + escHtml(state.deleted) + ' <?php echo esc_js( __( 'borrados hasta el momento.', 'jb3-members' ) ); ?>' : '')
                    + '</p></div>';
            });

            <?php if ( $state && 'running' === $state['phase'] ) : ?>
            if (confirm(<?php echo wp_json_encode( __( 'Hay un borrado en curso. ¿Querés reanudarlo?', 'jb3-members' ) ); ?>
                + ' (' + state.deleted + '/' + state.total + ')')) {
                running = true;
                btnStart.disabled       = true;
                btnCancel.style.display = 'inline-block';
                divProg.style.display   = 'block';
                setSpinner(true);
                updateBar(state);
                processChunk();
            }
            <?php endif; ?>
        })();
        </script>
        <?php
    }
}
