<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contenido privado — un checkbox ("Contenido privado", meta _jb3_privado)
 * disponible en CUALQUIER tipo de contenido público (Páginas, Entradas,
 * Películas, Series, futuros CPT como "juegos", etc.), sin lista fija: se
 * registra dinámicamente contra get_post_types(['public' => true]) así no
 * hace falta acordarse de sumar un post type nuevo acá cuando se cree.
 *
 * A propósito NO usa el estado nativo "Privada" de WordPress (visible solo
 * para admins/editores, sin mensaje ni botones para el resto) ni toca
 * post_status — el post sigue "Publicado" como siempre, así ninguna
 * consulta existente del sitio ([jb3_modulo], grillas, geo-targeting, etc.)
 * necesita cambiar. Mismo criterio que jb3-geo-targeting: la restricción
 * vive aparte, en un guard de template_redirect, no en el estado del post.
 */
class JB3_Content_Privacy {

	const META_KEY = '_jb3_privado';
	const NONCE     = 'jb3_privado_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_metabox' ] );
		add_action( 'save_post', [ __CLASS__, 'save' ] );
		add_action( 'template_redirect', [ __CLASS__, 'guard' ], 1 );
	}

	// ─── Metabox: un checkbox, en cualquier post type público ────────────────

	public static function register_metabox() {
		foreach ( get_post_types( [ 'public' => true ] ) as $post_type ) {
			add_meta_box(
				'jb3-privado',
				__( 'Acceso', 'jb3-members' ),
				[ __CLASS__, 'render_metabox' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public static function render_metabox( WP_Post $post ) {
		wp_nonce_field( 'jb3_privado_save', self::NONCE );
		$checked = (bool) get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<label>
			<input type="checkbox" name="jb3_privado" value="1" <?php checked( $checked ); ?>>
			<?php esc_html_e( 'Contenido privado (solo para usuarios logueados)', 'jb3-members' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Si un visitante sin sesión intenta ver esta página, se le muestra un mensaje con botones para iniciar sesión o registrarse, en vez del contenido.', 'jb3-members' ); ?>
		</p>
		<?php
	}

	public static function save( int $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'jb3_privado_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_KEY, ! empty( $_POST['jb3_privado'] ) ? 1 : 0 );
	}

	// ─── Guard ─────────────────────────────────────────────────────────────────

	public static function guard() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || ! get_post_meta( $post_id, self::META_KEY, true ) ) {
			return;
		}

		// El propio autor/editor (o un admin) siempre puede ver/previsualizar
		// su contenido, esté logueado como miembro o no.
		if ( current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Ya logueado y con permiso: pasa, sin importar de dónde vino el
		// link (?e=, si hay, ya no aporta nada a partir de acá — ver
		// comentario de clase en JB3_Mailchimp_Entry).
		$user_id = get_current_user_id();
		if ( $user_id && ( jb3_is_member( $user_id ) || user_can( $user_id, 'manage_options' ) ) ) {
			return;
		}

		// URL actual SIN el parámetro "e" — evita que el redirect_to de
		// vuelta acá siga arrastrando el mismo unique_email_id en loop; ya
		// cumplió su función en esta request.
		$current_url = remove_query_arg( 'e', esc_url_raw(
			( is_ssl() ? 'https' : 'http' ) . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] )
		) );

		// Sin permiso (o sin sesión): si viene con ?e=*|UNIQID|* válido de
		// Mailchimp, mandamos el magic link directo a la persona correcta
		// en vez del formulario genérico de Ingresar/Registrarte.
		if ( ! empty( $_GET['e'] ) && class_exists( 'JB3_Mailchimp_Entry' ) ) {
			$resultado = JB3_Mailchimp_Entry::verificar(
				sanitize_text_field( wp_unslash( $_GET['e'] ) ),
				$current_url
			);
			if ( JB3_Mailchimp_Entry::MAGIC_LINK_ENVIADO === $resultado ) {
				self::render_magic_link_enviado( $post_id );
				exit;
			}
		}

		self::render_bloqueado( $post_id, $current_url );
		exit;
	}

	private static function render_bloqueado( int $post_id, string $current_url ) {
		$login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), home_url( '/login/' ) );
		$registro_url = home_url( '/registro/' );

		self::enqueue_estilos();

		get_header();
		?>
		<main id="jb3-main-content">
			<div class="jb3-privado-bloqueado">
				<h1 class="jb3-privado-bloqueado__titulo"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<p class="jb3-privado-bloqueado__texto">
					<?php esc_html_e( 'Este contenido es exclusivo para usuarios registrados. Iniciá sesión o registrate para verlo.', 'jb3-members' ); ?>
				</p>
				<div class="jb3-privado-bloqueado__botones">
					<a class="jb3-btn" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Ingresar', 'jb3-members' ); ?></a>
					<a class="jb3-btn jb3-btn--outline" href="<?php echo esc_url( $registro_url ); ?>"><?php esc_html_e( 'Registrarte', 'jb3-members' ); ?></a>
				</div>
			</div>
		</main>
		<?php
		get_footer();
	}

	/** Entrada por Mailchimp resuelta: magic link ya en camino al email correcto. */
	private static function render_magic_link_enviado( int $post_id ) {
		self::enqueue_estilos();

		get_header();
		?>
		<main id="jb3-main-content">
			<div class="jb3-privado-bloqueado">
				<h1 class="jb3-privado-bloqueado__titulo"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<p class="jb3-privado-bloqueado__texto">
					<?php esc_html_e( '¡Revisá tu correo! Te mandamos un enlace de acceso — al hacer clic vas a volver directo a esta página.', 'jb3-members' ); ?>
				</p>
			</div>
		</main>
		<?php
		get_footer();
	}

	private static function enqueue_estilos() {
		wp_enqueue_style( 'jb3-forms', JB3_URL . 'public/css/jb3-forms.css', [], jb3_asset_ver( 'public/css/jb3-forms.css' ) );
	}
}
