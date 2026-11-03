<?php
/**
 * Pantalla JB3 Members > Ayuda: guía completa para quien administra el sitio.
 * El README es la documentación técnica; esto se escribe para el administrador.
 * Si cambia un flujo (registro, mails, Mailchimp, importar, borrar), actualizar
 * también acá.
 */

defined( 'ABSPATH' ) || exit;

$jb3_pantalla = static function ( string $page, string $texto ): string {
	return '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html( $texto ) . '</a>';
};
$jb3_usuarios = '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'Usuarios', 'jb3-members' ) . '</a>';
?>
<style>
	.jb3-ayuda { max-width: 960px; font-size: 14px; line-height: 1.6; }
	.jb3-ayuda h2 { margin-top: 2.6em; padding-top: 1em; border-top: 1px solid #dcdcde; font-size: 1.5em; }
	.jb3-ayuda h3 { margin-top: 1.8em; font-size: 1.15em; }
	.jb3-ayuda p, .jb3-ayuda li { font-size: 14px; }
	.jb3-ayuda ol, .jb3-ayuda ul { margin-left: 1.6em; }
	.jb3-ayuda ul { list-style: disc; }
	.jb3-ayuda li { margin-bottom: .45em; }
	.jb3-ayuda table.widefat { margin: 1em 0 1.4em; }
	.jb3-ayuda table.widefat th { font-weight: 600; }
	.jb3-ayuda table.widefat td, .jb3-ayuda table.widefat th { font-size: 13px; line-height: 1.5; }
	.jb3-ayuda__indice { margin: 1.4em 0; padding: 14px 20px; background: #fff; border: 1px solid #dcdcde; columns: 2; }
	.jb3-ayuda__indice ol { margin: 0 0 0 1.4em; }
	.jb3-ayuda__nota { margin: 1em 0; padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; }
	.jb3-ayuda__aviso { margin: 1em 0; padding: 10px 14px; background: #fcf9e8; border-left: 4px solid #dba617; }
	.jb3-ayuda__peligro { margin: 1em 0; padding: 10px 14px; background: #fcf0f1; border-left: 4px solid #d63638; }
	.jb3-ayuda__volver { margin-top: 1em; font-size: 12px; }
	@media (max-width: 782px) { .jb3-ayuda__indice { columns: 1; } }
</style>

<div class="wrap jb3-ayuda" id="jb3-ayuda-inicio">
	<h1><?php esc_html_e( 'JB3 Members — Ayuda', 'jb3-members' ); ?></h1>
	<p>Guía completa del sistema de usuarios: qué pasa cuando alguien se registra, cómo se aprueba, qué mails salen, qué hace Mailchimp y cómo importar o borrar usuarios desde un Excel.</p>

	<nav class="jb3-ayuda__indice" aria-label="Índice de la ayuda">
		<ol>
			<li><a href="#conceptos">Cómo funciona, en pocas palabras</a></li>
			<li><a href="#registro">Registro de un usuario, paso a paso</a></li>
			<li><a href="#aprobar">Cómo aprobar a un usuario</a></li>
			<li><a href="#ingreso">Cómo ingresa un usuario al sitio</a></li>
			<li><a href="#mails">Qué mails envía el sistema</a></li>
			<li><a href="#mailchimp">Mailchimp: qué pasa en cada caso</a></li>
			<li><a href="#importar">Importar usuarios desde un Excel</a></li>
			<li><a href="#borrar">Borrar usuarios desde un Excel y archivarlos</a></li>
			<li><a href="#sincronizar">Sincronizar Mailchimp</a></li>
			<li><a href="#otras">Otras acciones sobre un usuario</a></li>
			<li><a href="#representantes">Representantes</a></li>
			<li><a href="#contenido">Contenido privado y descargas</a></li>
			<li><a href="#admins">Administradores: ingreso y verificación</a></li>
			<li><a href="#configuracion">Configuración: qué tiene que estar listo</a></li>
			<li><a href="#problemas">Problemas comunes</a></li>
		</ol>
	</nav>

	<h2 id="conceptos">1. Cómo funciona, en pocas palabras</h2>
	<ul>
		<li><strong>Nadie tiene contraseña.</strong> Se ingresa siempre con un enlace que llega por mail (magic link), tanto miembros como administradores.</li>
		<li><strong>Toda cuenta nueva necesita aprobación.</strong> Quien se registra queda <em>Pendiente</em> y no puede entrar hasta que un representante o un administrador lo apruebe. Las cuentas importadas son la excepción: entran ya aprobadas.</li>
		<li><strong>WordPress es la fuente de verdad.</strong> Mailchimp refleja lo que hay en WordPress, y solo reciben el newsletter los usuarios aprobados que lo aceptaron.</li>
	</ul>

	<table class="widefat striped">
		<thead><tr><th style="width:24%;">Quién</th><th>Qué es</th></tr></thead>
		<tbody>
			<tr><td><strong>Miembro</strong></td><td>Usuario del sitio (rol <code>jb3_member</code>). Ve el contenido y su perfil, pero no entra al panel de WordPress ni ve la barra de administración.</td></tr>
			<tr><td><strong>Administrador</strong></td><td>Gestiona el sitio desde el panel. No necesita aprobación, pero entra con enlace y código de verificación. Nunca lo afectan los borrados ni las importaciones masivas.</td></tr>
			<tr><td><strong>Representante</strong></td><td>Contacto comercial de uno o más países. No es un usuario del sitio: es una ficha con su mail, que recibe los avisos y aprueba registros desde el mail.</td></tr>
		</tbody>
	</table>

	<table class="widefat striped">
		<thead><tr><th style="width:24%;">Estado</th><th>Qué significa</th></tr></thead>
		<tbody>
			<tr><td><strong>Pendiente</strong></td><td>Registrado, sin aprobar. No puede pedir enlace de acceso y no está en Mailchimp. Si pasan <strong>30 días</strong> sin aprobación, la cuenta se borra sola.</td></tr>
			<tr><td><strong>Aprobado</strong></td><td>Puede ingresar. Si aceptó el newsletter, está suscripto en Mailchimp.</td></tr>
		</tbody>
	</table>

	<h2 id="registro">2. Registro de un usuario, paso a paso</h2>
	<ol>
		<li><strong>El usuario completa el formulario</strong> en la página de registro (<code>/registro/</code>): nombre, apellido, email, país, teléfono, empresa, sector, cargo, temas de interés y newsletter. Todos son obligatorios salvo los temas de interés. El newsletter viene tildado y puede destildarlo.</li>
		<li><strong>El sitio crea la cuenta como Pendiente.</strong> El usuario ve «¡Registro recibido!»: un representante de su región revisará el registro y recibirá un correo cuando sea aprobado.</li>
		<li><strong>Se le avisa a los representantes de su país</strong> con el mail «Nuevo registro pendiente». Si el país no tiene representante asignado, le llega al representante por defecto.</li>
		<li><strong>Un representante o un administrador lo aprueba</strong> (ver <a href="#aprobar">punto 3</a>).</li>
		<li><strong>El usuario recibe «Tu acceso ha sido aprobado»</strong> con el botón «Ingresar al sitio».</li>
		<li><strong>Se sincroniza con Mailchimp en ese mismo momento</strong>: si aceptó el newsletter queda suscripto, con sus datos y el grupo de su país.</li>
		<li><strong>El usuario hace clic y ya está adentro</strong>, con la sesión abierta y sin contraseña.</li>
	</ol>
	<div class="jb3-ayuda__nota"><strong>En Mailchimp no pasa nada hasta la aprobación.</strong> Mientras la cuenta esté Pendiente, el contacto no se crea, aunque haya tildado el newsletter.</div>
	<div class="jb3-ayuda__nota"><strong>Si el email ya existe,</strong> el usuario ve el mismo mensaje de «Registro recibido», pero no se crea nada y no sale ningún mail. Es a propósito: así nadie puede averiguar qué emails tienen cuenta. Si alguien dice «me registré y no me llegó nada», puede que ya tuviera cuenta: buscalo en <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>.</div>
	<p>Protecciones del formulario: reCAPTCHA y un máximo de 5 intentos de registro cada 10 minutos desde una misma conexión.</p>

	<h2 id="aprobar">3. Cómo aprobar a un usuario</h2>
	<h3>Opción A: desde el mail (representantes)</h3>
	<ol>
		<li>Abrir el mail «[JB3] Nuevo registro pendiente: <em>nombre</em>», que trae todos los datos del usuario.</li>
		<li>Hacer clic en <strong>Aprobar usuario</strong>. Se abre una página con el nombre, email, empresa y país.</li>
		<li>Hacer clic en <strong>✓ Aprobar usuario</strong> en esa página. Recién ahí queda aprobado: abrir el link solo no aprueba a nadie.</li>
	</ol>
	<p>Si dos representantes reciben el mismo aviso, el segundo ve «Usuario ya aprobado». El link del mail no vence, pero deja de servir si la cuenta se borra.</p>

	<h3>Opción B: desde el panel (administradores)</h3>
	<ol>
		<li>Ir a <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. La columna <strong>Estado JB3</strong> muestra quién está Pendiente, y se puede ordenar haciendo clic en el título.</li>
		<li>Pasar el mouse sobre el usuario y hacer clic en <strong>Aprobar</strong>.</li>
		<li>Listo: se le manda el mail de bienvenida y se sincroniza con Mailchimp.</li>
	</ol>
	<p>También se puede abrir la ficha del usuario, pasar el Estado a Aprobado y guardar: hace exactamente lo mismo.</p>

	<h3>¿Cómo se rechaza a alguien?</h3>
	<p>No hay botón de rechazo ni mail de rechazo. Hay dos caminos: no hacer nada, y la cuenta se borra sola a los 30 días; o borrarla desde <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. En ambos casos el usuario no recibe aviso.</p>

	<h2 id="ingreso">4. Cómo ingresa un usuario al sitio</h2>
	<ol>
		<li>Entra a <code>/login/</code> y escribe su email.</li>
		<li>Recibe el mail «[JB3] Tu enlace de acceso».</li>
		<li>Hace clic en el enlace y entra. Si venía de una página privada, vuelve a esa página.</li>
		<li><strong>Si le falta algún dato obligatorio del perfil</strong>, lo lleva a su perfil y no puede seguir navegando hasta completarlo. Pasa sobre todo con usuarios importados.</li>
	</ol>
	<table class="widefat striped">
		<thead><tr><th style="width:42%;">Regla</th><th>Valor</th></tr></thead>
		<tbody>
			<tr><td>Validez del enlace de acceso (miembro)</td><td>120 días sin usar. Es de un solo uso.</td></tr>
			<tr><td>Validez del enlace de acceso (administrador)</td><td>1 hora.</td></tr>
			<tr><td>Si pide un enlace nuevo</td><td>El anterior deja de funcionar: vale siempre el último mail.</td></tr>
			<tr><td>Límite de pedidos</td><td>3 enlaces por email cada 10 minutos.</td></tr>
			<tr><td>Duración de la sesión</td><td>Se renueva sola con el uso. Se cierra a los 6 meses sin entrar, y como máximo a los 12 meses aunque entre seguido.</td></tr>
			<tr><td>Si está Pendiente y pide enlace</td><td>Ve «Tu cuenta está pendiente de aprobación». No se le manda nada.</td></tr>
			<tr><td>Si el email no existe</td><td>Ve el mismo mensaje que si existiera, y no se manda nada.</td></tr>
		</tbody>
	</table>

	<h2 id="mails">5. Qué mails envía el sistema</h2>
	<table class="widefat striped">
		<thead><tr><th style="width:27%;">Cuándo</th><th style="width:27%;">A quién</th><th>Asunto y contenido</th></tr></thead>
		<tbody>
			<tr><td>Alguien se registra</td><td>Representantes de su país, o el representante por defecto</td><td><strong>[JB3] Nuevo registro pendiente: <em>nombre</em></strong>. Todos los datos y el botón Aprobar.</td></tr>
			<tr><td>Se aprueba al usuario (desde el mail o desde el panel)</td><td>El usuario</td><td><strong>[JB3] Tu acceso a <em>sitio</em> ha sido aprobado</strong>. Botón «Ingresar al sitio».</td></tr>
			<tr><td>El usuario pide entrar</td><td>El usuario</td><td><strong>[JB3] Tu enlace de acceso a <em>sitio</em></strong>. También sale si llega sin sesión a una página privada desde un link de campaña de Mailchimp.</td></tr>
			<tr><td>El usuario pide eliminar su cuenta</td><td>El usuario</td><td><strong>[JB3] Confirmá la eliminación de tu cuenta en <em>sitio</em></strong>. Enlace válido 24 horas.</td></tr>
			<tr><td>Un usuario descarga una Oportunidad Comercial</td><td>Representantes de su país con «Recibe notificaciones» activo</td><td><strong>[JB3] <em>nombre</em> descargó "<em>oportunidad</em>"</strong>.</td></tr>
		</tbody>
	</table>
	<div class="jb3-ayuda__nota"><strong>Qué no envía mail:</strong> importar usuarios, borrar usuarios (por el panel, por lista o por limpieza automática), y descargar una oportunidad (el usuario que descarga no recibe nada). Los administradores tampoco reciben estos avisos: van solo a los representantes.</div>

	<h2 id="mailchimp">6. Mailchimp: qué pasa en cada caso</h2>
	<p>Mailchimp se usa para el newsletter. Algunas acciones lo actualizan al instante y otras necesitan <a href="#sincronizar">sincronizar</a> después:</p>
	<table class="widefat striped">
		<thead><tr><th style="width:38%;">Acción</th><th style="width:17%;">¿Cuándo?</th><th>Qué pasa en Mailchimp</th></tr></thead>
		<tbody>
			<tr><td>Registro (Pendiente)</td><td>Nunca</td><td>Nada.</td></tr>
			<tr><td>Aprobación con newsletter</td><td>Al instante</td><td>Queda suscripto, con sus datos y el grupo de su país.</td></tr>
			<tr><td>Aprobación sin newsletter</td><td>Al instante</td><td>No se suscribe.</td></tr>
			<tr><td>El usuario guarda su perfil, o un admin guarda su ficha</td><td>Al instante</td><td>Se actualizan los datos. Si tildó o destildó el newsletter, se suscribe o se archiva.</td></tr>
			<tr><td>Un admin pasa a alguien de Aprobado a Pendiente</td><td>Al instante</td><td>Se archiva.</td></tr>
			<tr><td>El usuario se desuscribe desde un mail de Mailchimp</td><td>Al instante, si el webhook funciona</td><td>Se archiva y en WordPress queda sin suscripción al newsletter. Su cuenta sigue activa y puede entrar al sitio.</td></tr>
			<tr><td>El usuario confirma la baja de su cuenta</td><td>Al instante</td><td>Se archiva.</td></tr>
			<tr><td>Un admin borra a un miembro desde Usuarios</td><td>Al instante</td><td>Se archiva.</td></tr>
			<tr><td>Borrar Miembros por <strong>Lista CSV</strong></td><td>Al instante</td><td>Se archiva cada usuario borrado.</td></tr>
			<tr><td>Borrar Miembros por <strong>Todos / Pendientes / Aprobados</strong></td><td>Al sincronizar</td><td>Nada en el momento. Se archivan al sincronizar con la opción de archivar contactos sin cuenta.</td></tr>
			<tr><td><strong>Importar usuarios</strong></td><td>Al sincronizar</td><td>Nada en el momento. La sincronización los suscribe o archiva.</td></tr>
		</tbody>
	</table>

	<h3>¿Qué significa «archivado»?</h3>
	<p>Es un estado de Mailchimp: el contacto sale de la audiencia activa y no recibe más campañas, pero conserva sus datos, temas y grupos, y se puede recuperar. Si esa persona vuelve a suscribirse, el contacto se reactiva. <strong>El sitio nunca borra contactos de Mailchimp de forma definitiva: solo los archiva.</strong></p>

	<h3>Newsletter por país</h3>
	<ul>
		<li>El usuario solo elige newsletter sí o no. Al suscribirse se le asigna automáticamente el grupo de <strong>su país</strong>; al desuscribirse se le quitan todos.</li>
		<li>Un administrador puede agregar o quitar países desde la ficha del usuario, en <strong>Newsletters que recibe el usuario</strong>. Esa selección se respeta mientras el usuario no cambie su sí/no; si lo cambia, vuelve la regla automática.</li>
		<li>Los grupos viven en Mailchimp, en la categoría <strong>Newsletters</strong>, y cada grupo se llama con el código del país: AR, CL, MX, etc.</li>
	</ul>
	<p>Si el usuario cambia de país en su perfil, su grupo pasa al país nuevo y conserva los demás que tenga. Si el cambio lo hace un administrador en la ficha, los grupos se ajustan a mano en esa misma ficha.</p>

	<div class="jb3-ayuda__nota"><strong>Si alguien se dio de baja sin querer y pide volver:</strong> abrí su ficha en <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>, tildá <strong>Suscripto al newsletter</strong> y guardá. Vuelve a estar suscripto al instante.</div>

	<h3>Datos que recibe Mailchimp</h3>
	<p>Nombre, apellido, teléfono, empresa, sector, cargo, país, estado (Aprobado o Admin), temas de interés y grupos de newsletter.</p>

	<h3>Webhook: de Mailchimp hacia el sitio</h3>
	<p>El webhook es el aviso que Mailchimp le manda al sitio cuando pasa algo allá. Con él, si alguien se desuscribe desde un mail de Mailchimp, WordPress se entera. También puede traer cambios de datos que la persona haga en su perfil de Mailchimp.</p>
	<div class="jb3-ayuda__aviso"><strong>Si el webhook no llega,</strong> WordPress sigue creyendo que esa persona acepta el newsletter, y la próxima sincronización puede volver a suscribirla aunque se haya dado de baja. Mailchimp tiene que poder acceder al sitio: si el sitio está protegido con la contraseña del hosting, el webhook no llega.</div>

	<h2 id="importar">7. Importar usuarios desde un Excel</h2>
	<p>Sirve para dar de alta usuarios en cantidad o actualizar sus datos. <strong>No envía ningún mail y no toca Mailchimp.</strong></p>
	<div class="jb3-ayuda__nota"><strong>La regla es simple.</strong> Si el email ya tiene cuenta en el sitio, se actualizan solo sus datos personales: nombre, apellido, país, empresa, cargo, teléfono y sector. El newsletter y los temas de interés quedan como los definió el usuario. Si el email no tiene cuenta, es un usuario nuevo y se carga todo lo que venga en el Excel.</div>

	<h3>Paso a paso</h3>
	<ol>
		<li><strong>Hacé un respaldo:</strong> en <?php echo $jb3_pantalla( 'jb3-export', 'Exportar Usuarios' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> descargá el CSV actual. Sirve también como plantilla con las columnas correctas.</li>
		<li><strong>Prepará el Excel.</strong> La primera fila tiene los títulos de columna, y la única obligatoria es <code>Email</code>.</li>
		<li><strong>Guardalo como CSV:</strong> en Excel, Archivo → Guardar como → <strong>CSV UTF-8 (delimitado por comas)</strong>. El sitio no acepta <code>.xlsx</code>.</li>
		<li><strong>Importalo:</strong> en <?php echo $jb3_pantalla( 'jb3-import', 'Importar Usuarios' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> elegí el archivo y apretá <strong>Importar</strong>. No cierres la pestaña hasta que termine.</li>
		<li><strong>Revisá el resultado:</strong> indica cuántos creó, cuántos actualizó y qué filas dieron error.</li>
		<li><strong>Sincronizá:</strong> andá a <?php echo $jb3_pantalla( 'jb3-sync', 'Sincronizar Mailchimp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> y apretá <strong>Iniciar sincronización</strong>. Si vas a importar varios archivos, podés sincronizar una sola vez al final.</li>
		<li><strong>Avisales a los usuarios nuevos.</strong> El sistema no les escribe: tienen que entrar a <code>/login/</code>, poner su email y pedir el enlace. Los que tengan datos incompletos deberán completarlos al entrar.</li>
	</ol>

	<h3>Columnas que reconoce</h3>
	<table class="widefat striped">
		<thead><tr><th style="width:25%;">Columna</th><th>Qué poner</th></tr></thead>
		<tbody>
			<tr><td><code>Email</code></td><td><strong>Obligatoria.</strong> Identifica al usuario.</td></tr>
			<tr><td><code>ID</code></td><td>La trae el archivo exportado. Ver el aviso de abajo.</td></tr>
			<tr><td><code>Nombre</code>, <code>Apellido</code></td><td>Texto.</td></tr>
			<tr><td><code>Estado</code></td><td><code>Aprobado</code> o <code>Pendiente</code>, escrito exactamente así. Cualquier otro texto cuenta como Pendiente. Si la columna no está o la celda está vacía, queda Aprobado.</td></tr>
			<tr><td><code>País</code></td><td>Nombre (<code>Argentina</code>) o código (<code>AR</code>). Si no lo reconoce, lo ignora sin avisar.</td></tr>
			<tr><td><code>Empresa</code>, <code>Teléfono</code></td><td>Texto.</td></tr>
			<tr><td><code>Cargo</code>, <code>Sector</code></td><td>Usar las mismas opciones del formulario de perfil.</td></tr>
			<tr><td><code>Suscripto al newsletter</code></td><td><code>Sí</code> o <code>No</code>. Solo para usuarios nuevos. Si falta, queda en No.</td></tr>
			<tr><td><code>Newsletters que recibe</code></td><td>Solo para usuarios nuevos y opcional. Grupos de país separados por coma, por ejemplo <code>AR, CL</code>. Si falta y se suscribe, se le asigna el grupo de su país.</td></tr>
			<tr><td><code>Intereses</code></td><td>Solo para usuarios nuevos. Nombres separados por coma, iguales a los de Mailchimp: <code>Películas, Series</code>.</td></tr>
			<tr><td><code>Fecha Registro JB3</code>, <code>Fecha Aprobación</code></td><td>Opcionales. Si faltan en un usuario nuevo, se usa la fecha de hoy.</td></tr>
		</tbody>
	</table>
	<p>Cualquier otra columna del Excel se ignora.</p>

	<h3>Qué hace con cada fila</h3>
	<table class="widefat striped">
		<thead><tr><th style="width:38%;">Caso</th><th>Resultado</th></tr></thead>
		<tbody>
			<tr><td>El email no tiene cuenta en el sitio</td><td>Crea el usuario como miembro con todos los datos del Excel, aprobado salvo que la columna Estado diga otra cosa.</td></tr>
			<tr><td>El email ya tiene cuenta en el sitio</td><td>Actualiza solo sus datos personales y solo las columnas que tengan dato: <strong>una celda vacía no borra nada</strong>. Su newsletter y sus temas no se tocan. Si cambió de país, su grupo pasa al país nuevo.</td></tr>
			<tr><td>El email ya existe y no hay columna Estado</td><td>Queda Aprobado. Un Pendiente se aprueba así, sin aviso por mail.</td></tr>
			<tr><td>Es un administrador</td><td>No lo toca y lo informa como error de esa fila.</td></tr>
			<tr><td>Email inválido</td><td>Saltea la fila y la informa.</td></tr>
			<tr><td>Usuario que no está en el archivo</td><td>No lo toca. Importar nunca borra a nadie, así que podés cargar un país sin afectar a los demás.</td></tr>
		</tbody>
	</table>

	<div class="jb3-ayuda__aviso"><strong>Un usuario borrado del sitio vuelve como nuevo.</strong> Borrar elimina su cuenta y sus opciones. Si reaparece en un Excel, se carga lo que diga el archivo, aunque antes se hubiera dado de baja del newsletter o tuviera otros temas. Al sincronizar, eso reemplaza lo que había en su contacto archivado de Mailchimp. Antes de reimportar a alguien borrado, revisá su fila.</div>
	<div class="jb3-ayuda__peligro"><strong>Cuidado con la columna ID.</strong> Si el archivo trae un ID que existe, la fila actualiza a ese usuario aunque el email sea otro, y le cambia el email. Es útil para corregir un mail, pero peligroso con un archivo editado a mano o exportado de otro sitio: en esos casos, <strong>borrá la columna ID</strong> antes de importar.</div>
	<p>Si se corta la conexión o se cierra la pestaña, al volver a la pantalla el sitio ofrece reanudar. <strong>Cancelar</strong> detiene el proceso, pero no deshace lo ya importado.</p>

	<h2 id="borrar">8. Borrar usuarios desde un Excel y archivarlos</h2>
	<div class="jb3-ayuda__peligro"><strong>Borrar no se puede deshacer.</strong> Antes hacé un respaldo desde <?php echo $jb3_pantalla( 'jb3-export', 'Exportar Usuarios' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. Los administradores nunca se borran.</div>

	<h3>Paso a paso con Lista CSV</h3>
	<p>Es el método recomendado: borra solo los emails del archivo, de cualquier país, y <strong>los archiva en Mailchimp en el momento</strong>.</p>
	<ol>
		<li><strong>Prepará el Excel</strong> con una columna titulada exactamente <code>Email</code>. Si hay más columnas, se ignoran.</li>
		<li><strong>Guardalo como CSV:</strong> Archivo → Guardar como → <strong>CSV UTF-8 (delimitado por comas)</strong>.</li>
		<li><strong>Abrí la pantalla:</strong> en <?php echo $jb3_pantalla( 'jb3-delete', 'Borrar Miembros' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> elegí <strong>Lista CSV (subir archivo)</strong>.</li>
		<li><strong>Subí el archivo</strong> y apretá <strong>Cargar y previsualizar</strong>. Todavía no se borra nada.</li>
		<li><strong>Revisá la vista previa:</strong> cuántos usuarios se van a borrar, cuántos administradores se excluyen y qué emails no se encontraron.</li>
		<li><strong>Confirmá:</strong> apretá <strong>Iniciar borrado</strong> y aceptá el mensaje. No cierres la pestaña hasta que termine.</li>
		<li><strong>Verificá en Mailchimp</strong> que algunos de esos contactos figuren como archivados. El sitio envía el pedido, pero no recibe confirmación de cada uno.</li>
	</ol>
	<div class="jb3-ayuda__aviso"><strong>Emails que no se encontraron:</strong> no tienen cuenta en el sitio, así que el borrado por lista no los toca. Si alguno está en Mailchimp, se archiva al <a href="#sincronizar">sincronizar</a> con la opción de archivar contactos sin cuenta.</div>

	<h3>Los otros modos: Todos, Solo pendientes, Solo aprobados</h3>
	<div class="jb3-ayuda__peligro"><strong>No distinguen país: afectan a toda la base.</strong> Y no archivan en Mailchimp en el momento: hay que <a href="#sincronizar">sincronizar</a> después, tildando la opción de archivar contactos sin cuenta. Para borrar un país o un grupo puntual, usá siempre Lista CSV.</div>
	<p>Si se corta la conexión, al volver el sitio ofrece reanudar. <strong>Cancelar</strong> no recupera a los usuarios ya borrados.</p>

	<h3>Actualizar un país entero</h3>
	<ol>
		<li>Exportá un respaldo.</li>
		<li>Importá el archivo nuevo del país.</li>
		<li>Armá un archivo con los emails que ya no siguen y borralos por Lista CSV.</li>
		<li>Corré la sincronización para que Mailchimp quede igual a WordPress.</li>
	</ol>

	<h2 id="sincronizar">9. Sincronizar Mailchimp</h2>
	<p>En <?php echo $jb3_pantalla( 'jb3-sync', 'Sincronizar Mailchimp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>, el botón <strong>Iniciar sincronización</strong> hace que Mailchimp quede igual a WordPress. No se ejecuta sola: hay que correrla después de importar o de borrar por Todos, Pendientes o Aprobados.</p>
	<ol>
		<li><strong>Fase 1, de WordPress a Mailchimp.</strong> Corre siempre. Cada miembro aprobado con newsletter se suscribe y actualiza sus datos. Cada aprobado sin newsletter se archiva. Los Pendientes no se tocan. Los administradores se suscriben solo si tienen el newsletter activo.</li>
		<li><strong>Fase 2, de Mailchimp al archivo.</strong> Corre solo si tildás <strong>Archivar también los contactos de Mailchimp sin cuenta en el sitio</strong>. Todo contacto de Mailchimp cuyo email no exista en WordPress se archiva.</li>
	</ol>
	<div class="jb3-ayuda__peligro"><strong>La Fase 2 archiva cualquier contacto sin cuenta en el sitio,</strong> incluso los cargados a mano en Mailchimp o que lleguen por otro formulario. Tildala solo después de borrar por Todos, Pendientes o Aprobados, o si querés limpiar la audiencia, y revisá antes que no haya contactos que deban quedar allá sin tener cuenta.</div>
	<ul>
		<li>Mantené la pestaña abierta mientras corre. Si se corta, el progreso queda guardado y aparece <strong>Reanudar</strong>.</li>
		<li><strong>«Completada» significa que Mailchimp recibió el pedido</strong>, no que ya lo aplicó. Puede tardar unos minutos en verse en su web.</li>
	</ul>

	<h2 id="otras">10. Otras acciones sobre un usuario</h2>
	<table class="widefat striped">
		<thead><tr><th style="width:32%;">Quiero…</th><th>Cómo se hace</th></tr></thead>
		<tbody>
			<tr><td>Ver o editar sus datos</td><td><?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → Editar → sección <strong>Datos JB3 Members</strong> → Actualizar. Guardar también lo sincroniza con Mailchimp.</td></tr>
			<tr><td>Cerrarle la sesión</td><td><?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → pasar el mouse → <strong>Desloguear</strong>. Cierra todas sus sesiones e invalida su enlace pendiente.</td></tr>
			<tr><td>Quitarle el acceso sin borrarlo</td><td>En su ficha, pasar el Estado a Pendiente y guardar (se archiva en Mailchimp). Después hacer clic en <strong>Desloguear</strong>: sin eso, la sesión que tenga abierta sigue activa. <strong>Ojo:</strong> a los 30 días como Pendiente la cuenta se borra sola.</td></tr>
			<tr><td>Borrar un usuario puntual</td><td><?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → Eliminar. Se archiva en Mailchimp automáticamente.</td></tr>
			<tr><td>Cambiarle el email</td><td>El usuario no puede hacerlo desde su perfil. Un admin lo cambia en la ficha, y Mailchimp lo toma como un contacto nuevo: el email viejo se archiva al sincronizar con la opción de archivar contactos sin cuenta.</td></tr>
			<tr><td>Corregir sus newsletters por país</td><td>En su ficha, <strong>Newsletters que recibe el usuario</strong>, y guardar.</td></tr>
			<tr><td>Darse de baja (lo hace el usuario)</td><td>En su perfil, <strong>Eliminar mi cuenta</strong>. Le llega un mail, entra al enlace (24 horas) y confirma en <strong>Sí, eliminar mi cuenta definitivamente</strong>. Se borra del sitio y se archiva en Mailchimp.</td></tr>
			<tr><td>Dejar el newsletter (lo hace el usuario)</td><td>Destilda el newsletter en su perfil, o usa el link de baja de Mailchimp. Su cuenta sigue activa.</td></tr>
			<tr><td>Exportar la base</td><td><?php echo $jb3_pantalla( 'jb3-export', 'Exportar Usuarios' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → <strong>Descargar CSV</strong>. Incluye a todos los miembros, no a los administradores.</td></tr>
		</tbody>
	</table>
	<p><strong>Limpieza automática:</strong> una vez por día, el sitio borra las cuentas Pendientes con más de 30 días desde el registro. Las cuentas aprobadas nunca se borran solas.</p>

	<h2 id="representantes">11. Representantes</h2>
	<p>Se administran en <?php echo $jb3_pantalla( 'jb3-members', 'Representantes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. Cada ficha tiene:</p>
	<table class="widefat striped">
		<thead><tr><th style="width:30%;">Campo</th><th>Para qué sirve</th></tr></thead>
		<tbody>
			<tr><td><strong>Nombre, email, teléfono, WhatsApp, foto</strong></td><td>Datos de contacto. El email es adonde llegan los avisos: si está mal escrito, nadie se entera de los registros.</td></tr>
			<tr><td><strong>Países</strong></td><td>Países que atiende. Puede tener varios, y un país puede tener varios representantes: todos reciben el aviso.</td></tr>
			<tr><td><strong>Visible en el sitio</strong></td><td>Lo muestra en la página de contacto.</td></tr>
			<tr><td><strong>Recibe notificaciones</strong></td><td>Le avisa por mail cuando alguien de su país descarga una Oportunidad Comercial. No afecta los avisos de registro, que llegan siempre.</td></tr>
			<tr><td><strong>Por defecto</strong></td><td>Recibe los avisos de los países sin representante asignado. Hay uno solo.</td></tr>
		</tbody>
	</table>
	<div class="jb3-ayuda__aviso"><strong>El representante por defecto tiene que tener un email real y revisado.</strong> Si un país no tiene representante y ese mail no funciona, los registros quedan pendientes sin que nadie se entere y se borran a los 30 días.</div>

	<h2 id="contenido">12. Contenido privado y descargas</h2>
	<h3>Contenido privado</h3>
	<p>Al editar cualquier página o contenido, la caja <strong>Acceso</strong> tiene la opción <strong>Contenido privado</strong>. Quien entre sin sesión ve un mensaje con los botones Ingresar y Registrarte, y al ingresar vuelve a esa página.</p>
	<h3>Links en campañas de Mailchimp</h3>
	<p>Si un link a una página privada termina en <code>?e=*|UNIQID|*</code>, Mailchimp identifica a cada destinatario. Cuando un usuario aprobado llega sin sesión, el sitio le manda el enlace de acceso a su mail, sin pedirle que escriba nada, y lo devuelve a esa página.</p>
	<h3>Oportunidades Comerciales</h3>
	<ul>
		<li>Cada vez que un usuario con sesión abre una oportunidad, se registra en la planilla de Google, hoja <strong>oportunidades vistas</strong>.</li>
		<li>Cada descarga se registra en la hoja <strong>oportunidades descargadas</strong> y avisa a los representantes de su país.</li>
		<li>Los PDF solo se ven o se descargan con sesión iniciada: el link no sirve si se comparte.</li>
		<li>La columna <strong>Posible bot</strong> marca accesos que pueden ser de un antivirus de correo y no de una persona. Es una estimación.</li>
	</ul>

	<h2 id="admins">13. Administradores: ingreso y verificación</h2>
	<ol>
		<li>Entrá a <code>/login/</code> y pedí el enlace con tu email. Vale <strong>1 hora</strong>.</li>
		<li>Al abrirlo, el sitio pide el código de 6 dígitos de una app de autenticación (Google Authenticator, Microsoft Authenticator, Authy…). La primera vez muestra un QR para configurarla.</li>
		<li>Después de 5 códigos incorrectos hay que pedir un enlace nuevo.</li>
	</ol>
	<p><strong>Si perdés el celular:</strong> otro administrador entra a <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → tu usuario → <strong>Reiniciar verificación en dos pasos</strong>. Si no hay otro administrador disponible, se reinicia desde el hosting. Conviene activar la copia en la nube de la app para no depender de un solo celular.</p>

	<h2 id="configuracion">14. Configuración: qué tiene que estar listo</h2>
	<p>Todo esto se revisa en <?php echo $jb3_pantalla( 'jb3-settings', 'Configuración' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>, y conviene verificarlo en cada sitio nuevo:</p>
	<ul>
		<li><strong>Páginas del plugin:</strong> Login (<code>[jb3_login]</code>), Perfil (<code>[jb3_profile]</code>), Inicio y <strong>Confirmar Baja de Cuenta</strong> (<code>[jb3_confirmar_baja]</code>). Sin esta última, el link del mail de baja no permite confirmar. La página de registro lleva <code>[jb3_register]</code>.</li>
		<li><strong>Email remitente:</strong> una dirección del dominio del sitio, autenticada en el servicio de envío. Un Gmail u Outlook personal hace que los mails se rechacen o lleguen a spam.</li>
		<li><strong>Mailchimp:</strong> API Key y Audience ID. Si creás o cambiás grupos, apretá <strong>Actualizar grupos desde Mailchimp</strong>.</li>
		<li><strong>Webhook:</strong> copiá la URL que aparece en Configuración y pegala en Mailchimp, en Audience → Settings → Webhooks. Activá Unsubscribes y Subscribes, y también Profile updates y Cleaned si querés que esos cambios lleguen al sitio. <strong>En las fuentes, no tildes «via the API»:</strong> si no, los cambios que hace el propio sitio cuentan como baja del usuario.</li>
		<li><strong>Google Sheets:</strong> el ID de la planilla, compartida como Editor con la cuenta de servicio.</li>
		<li><strong>Representantes:</strong> por lo menos uno por defecto, con un email real.</li>
	</ul>

	<h2 id="problemas">15. Problemas comunes</h2>
	<table class="widefat striped">
		<thead><tr><th style="width:38%;">Qué pasa</th><th>Qué hacer</th></tr></thead>
		<tbody>
			<tr><td>Alguien se registró y no llegó ningún aviso</td><td>Revisá que el país tenga representante y que su email esté bien; si no tiene, revisá el representante por defecto. Mirá también el spam y el remitente en Configuración. Mientras tanto, se puede aprobar desde <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>.</td></tr>
			<tr><td>Un usuario aprobado no recibió el mail de bienvenida</td><td>Puede estar en spam. Que pida el enlace en <code>/login/</code>. Si fue importado, nunca recibe ese mail.</td></tr>
			<tr><td>Un usuario dice que el enlace no funciona</td><td>Es de un solo uso, y solo vale el último pedido. Que pida uno nuevo y use ese.</td></tr>
			<tr><td>Un usuario importado no puede entrar</td><td>Si importó con Estado Pendiente, aprobalo desde <?php echo $jb3_usuarios; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. Si está aprobado, tiene que pedir el enlace en <code>/login/</code>: nunca recibió ningún mail.</td></tr>
			<tr><td>Al entrar, el usuario queda siempre en el perfil</td><td>Le falta algún dato obligatorio: nombre, apellido, empresa, cargo, teléfono, país o sector. Tiene que completarlos y guardar.</td></tr>
			<tr><td>Importé y no veo cambios en Mailchimp</td><td>Falta sincronizar: importar nunca toca Mailchimp.</td></tr>
			<tr><td>Sincronicé y un contacto sigue archivado</td><td>Esperá unos minutos: Mailchimp procesa el pedido después. Si sigue igual, abrí la ficha del usuario y apretá Actualizar sin cambiar nada: eso lo sincroniza de forma directa.</td></tr>
			<tr><td>Un importado figura archivado o no aparece en Mailchimp</td><td>Revisá su ficha: si no tiene tildado <strong>Suscripto al newsletter</strong>, sincronizar no lo suscribe. Si ya tenía cuenta, el Excel no lo cambia a propósito. Si corresponde, tildalo en su ficha.</td></tr>
			<tr><td>Un suscripto no recibe la campaña de su país</td><td>Revisá en su ficha <strong>Newsletters que recibe el usuario</strong>. Puede faltarle el país en su perfil, o no existir un grupo con ese código en Mailchimp.</td></tr>
			<tr><td>El CSV dice que falta la columna Email</td><td>La primera fila debe tener el título <code>Email</code>. Revisá que no haya filas vacías o un título arriba.</td></tr>
			<tr><td>El archivo no se puede subir</td><td>Tiene que ser <code>.csv</code>: guardá el Excel como CSV UTF-8.</td></tr>
			<tr><td>Al abrir el CSV exportado todo queda en una sola columna</td><td>Excel usa punto y coma en algunos idiomas. Importalo desde Datos → Desde texto/CSV, eligiendo coma como separador. Para volver a importarlo en el sitio, sirven tanto la coma como el punto y coma.</td></tr>
			<tr><td>El usuario pide la baja y el enlace no deja confirmar</td><td>Falta elegir la página <strong>Confirmar Baja de Cuenta</strong> en Configuración.</td></tr>
			<tr><td>Nadie puede entrar porque no llegan los mails</td><td>Puede estar caído el envío de correo. Hay que resolverlo desde el hosting: avisale a quien lo administra.</td></tr>
		</tbody>
	</table>

	<p class="jb3-ayuda__volver"><a href="#jb3-ayuda-inicio">↑ Volver al índice</a></p>
</div>
