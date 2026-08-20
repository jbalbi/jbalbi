<?php
/**
 * Plugin Name: JB3 ACF Admin Tools
 * Description: Agrega botones "Invertir selección" / "Seleccionar todo" / "Borrar selección" arriba de cualquier campo ACF de checkboxes (Checkbox, o Taxonomy con apariencia Checkbox) y del panel nativo de taxonomías jerárquicas del editor de bloques, y hace que esas listas de checkboxes fluyan en línea en vez de apilarse una debajo de la otra. No depende del theme ni de ningún CPT/taxonomía puntual — reusable en cualquier sitio con ACF.
 * Version:     1.0
 */
defined( 'ABSPATH' ) || exit;

// ─── BOTONES DE ACCIÓN RÁPIDA EN CAMPOS DE CHECKBOXES DEL ADMIN ─────────────
// Pedido puntual: cargar contenido para "todos los países menos uno" sin
// tener que tildar uno por uno — tildás el que sobra, apretás "Invertir
// selección", listo (todo lo tildado se destilda, todo lo destildado se
// tilda). "Seleccionar todo"/"Borrar selección" son el mismo mecanismo,
// sin depender de qué estaba tildado antes.
//
// Hacen falta DOS mecanismos distintos, no uno — el mismo campo "Canal"/
// "País" se renderiza de forma diferente según DÓNDE se edite el contenido
// (confirmado inspeccionando el DOM real con Playwright, no adivinando):
//   1. Campo ACF clásico (Checkbox, o Taxonomy con apariencia Checkbox),
//      HTML armado por PHP: <ul class="acf-checkbox-list"> — aparece en
//      metaboxes de posición Normal/Alta, pantallas de taxonomía, perfil de
//      usuario, customizer, repeaters.
//   2. Taxonomía jerárquica NATIVA del editor de bloques (NO es ACF): si el
//      grupo de campos ACF de un CPT queda en posición "Lateral", Gutenberg
//      no reubica ese HTML clásico — lo vuelve a pintar con sus propios
//      componentes React (panel "Documento" > nombre de la taxonomía), acá
//      el contenedor es <div class="editor-post-taxonomies__hierarchical-terms-list">
//      con checkboxes de @wordpress/components.
//
// A PROPÓSITO no soporta campos Taxonomy con apariencia "Select"/"Multi
// Select" (un <select> con Select2): ACF los carga siempre por AJAX con la
// lista de opciones arrancando vacía (hardcodeado en
// class-acf-field-taxonomy.php → render_field_select(), no es un ajuste que
// se pueda cambiar) — no hay forma de calcular "todas las opciones menos las
// tildadas" sin repetir esa consulta AJAX a mano, y el intento de hacerlo
// simple rompía el widget en vez de invertir la selección. Si un campo
// necesita este botón, la solución real es cambiarle la apariencia a
// Checkbox (mismo dato guardado, sin pérdida) en vez de Select.
add_action( 'acf/input/admin_footer', function () {
	?>
	<script>
	( function ( $ ) {
		// .click() nativo, NO poner ".checked = ..." a mano + un evento
		// "change" fabricado: el panel de taxonomías del editor de bloques
		// está armado con React, y React necesita procesar un evento DOM
		// REAL para actualizar su propio estado interno (lo que después se
		// manda a guardar) — con ".checked" + un evento fabricado, el
		// cuadrito cambia de color (responde a CSS) pero React nunca se
		// entera, así que al guardar la página el cambio se pierde en
		// silencio (detectado 2026-08-14: "Seleccionar todo" pintaba los 19
		// checkboxes pero no persistía nada). ".click()" dispara el mismo
		// click+change reales que un click de mouse de verdad, así que
		// sirve para los dos casos (React Y los checkboxes clásicos de ACF).
		// modo: 'invertir' | 'todo' | 'ninguno'.
		function aplicarModoCheckboxes( $lista, modo, excluirSelector ) {
			$lista.find( 'input[type="checkbox"]' ).each( function () {
				if ( excluirSelector && $( this ).is( excluirSelector ) ) {
					return;
				}
				var deberiaQuedar = modo === 'todo' ? true : ( modo === 'ninguno' ? false : ! this.checked );
				if ( this.checked !== deberiaQuedar ) {
					this.click();
				}
			} );
		}

		// Grupo de 3 botones reutilizado por los dos casos de abajo —
		// "aplicar(modo)" es lo único que cambia entre uno y otro.
		function crearGrupoBotones( aplicar ) {
			var $grupo = $( '<div class="jb3-acf-botones"></div>' );
			[
				{ texto: 'Invertir', modo: 'invertir' },
				{ texto: 'Todos', modo: 'todo' },
				{ texto: 'Limpiar', modo: 'ninguno' }
			].forEach( function ( accion ) {
				var $btn = $( '<button type="button" class="button button-small jb3-acf-invertir"></button>' ).text( accion.texto );
				$btn.on( 'click', function ( e ) {
					e.preventDefault();
					aplicar( accion.modo );
				} );
				$grupo.append( $btn );
			} );
			return $grupo;
		}

		// Caso 1: <ul class="acf-checkbox-list"> — grupo de botones como
		// primer <li>. ".acf-checkbox-toggle" es el "Seleccionar todos"
		// nativo de ACF, se excluye a propósito para no tocarlo.
		function procesarListaAcf( $lista ) {
			// Marca real en el DOM, no en la caché de jQuery vía .data(): "ACF >
			// Duplicar fila" clona el HTML tal cual, botones incluidos, pero ese
			// clonado no copia datos guardados con .data() — usar eso como
			// bandera hacía que cada fila duplicada se creyera "sin botones
			// todavía" y sumara otro grupo encima del ya clonado.
			var $existentes = $lista.children( 'li.jb3-acf-invertir-wrap' );
			if ( $existentes.length ) {
				$existentes.slice( 1 ).remove();
				return;
			}
			var $li = $( '<li class="jb3-acf-invertir-wrap"></li>' );
			$li.append( crearGrupoBotones( function ( modo ) {
				aplicarModoCheckboxes( $lista, modo, '.acf-checkbox-toggle' );
			} ) );
			$lista.prepend( $li );
		}

		// Caso 2: panel de taxonomía nativo del editor de bloques — grupo de
		// botones arriba de la lista (no es un <ul>, así que no suma un <li>).
		function procesarListaGutenberg( $lista ) {
			var $existentes = $lista.prev( '.jb3-acf-invertir-wrap' );
			if ( $existentes.length ) {
				$existentes.slice( 1 ).remove();
				return;
			}
			var $grupo = crearGrupoBotones( function ( modo ) {
				aplicarModoCheckboxes( $lista, modo );
			} ).addClass( 'jb3-acf-invertir-wrap' );
			$lista.before( $grupo );
		}

		var SELECTOR_ACF        = 'ul.acf-checkbox-list';
		var SELECTOR_GUTENBERG  = '.editor-post-taxonomies__hierarchical-terms-list';
		var SELECTOR_CUALQUIERA = SELECTOR_ACF + ', ' + SELECTOR_GUTENBERG;

		function jb3Escanear( $contexto ) {
			$contexto.find( SELECTOR_ACF ).each( function () {
				procesarListaAcf( $( this ) );
			} );
			$contexto.find( SELECTOR_GUTENBERG ).each( function () {
				procesarListaGutenberg( $( this ) );
			} );
		}

		$( document ).ready( function () {
			jb3Escanear( $( 'body' ) );
		} );

		// Repeaters/flexible content agregan filas nuevas después del load
		// inicial — ACF dispara este hook cada vez que inserta HTML nuevo al
		// DOM.
		if ( window.acf ) {
			acf.addAction( 'append', function ( $el ) {
				jb3Escanear( $el );
			} );
		}

		// El panel de taxonomías del editor de bloques se carga después del
		// HTML inicial (Gutenberg lo arma por su cuenta ya con la página
		// andando), y esa carga no dispara el "append" de ACF de arriba (ese
		// es solo para filas de repeater/flexible content). Un
		// MutationObserver no depende de saber CUÁNDO ni CÓMO se insertó el
		// HTML — agarra cualquiera de las dos listas en el momento en que
		// aparezcan, vengan de donde vengan.
		//
		// OJO acá: reescanear TODO document.body ante CUALQUIER mutación
		// (incluida la que dispara este mismo script al insertar su propio
		// botón) termina siendo un reescaneo constante en un editor que
		// cambia el DOM todo el tiempo (tipeo, autosave, re-renders) — por
		// eso el observer solo mira los nodos recién agregados, y solo actúa
		// si ese nodo puntual trae (o es) uno de los casos de arriba. Ni el
		// <li> ni el <button> que insertamos matchean nada de esto, así que
		// nunca se re-disparan solos.
		if ( window.MutationObserver ) {
			var observer = new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					mutation.addedNodes.forEach( function ( nodo ) {
						if ( nodo.nodeType !== 1 ) {
							return;
						}
						var $nodo = $( nodo );
						var esRelevante = $nodo.is( SELECTOR_CUALQUIERA ) || $nodo.find( SELECTOR_CUALQUIERA ).length;
						if ( esRelevante ) {
							jb3Escanear( $nodo.parent().length ? $nodo.parent() : $nodo );
						}
					} );
				} );
			} );
			observer.observe( document.body, { childList: true, subtree: true } );
		}
	} )( jQuery );
	</script>
	<style>
	/* Layout horizontal para cualquier lista de checkboxes de ACF (Checkbox,
	   Taxonomy en Checkbox) — por default ACF apila una opción por línea,
	   que con listas largas (ej. países de Latinoamérica) hace la página
	   larguísima. flex-wrap deja que cada opción ocupe solo el ancho de su
	   propio texto y fluya a la línea siguiente cuando no entra más, en vez
	   de forzar un renglón entero por opción. */
	ul.acf-checkbox-list {
		display: flex;
		flex-wrap: wrap;
		gap: 4px 20px;
	}
	ul.acf-checkbox-list > li {
		width: auto;
		margin: 0;
	}
	/* Excepción al flujo en columnas de arriba: el panel de ajustes de un
	   bloque (".block-editor-block-inspector") es angosto y de ancho fijo,
	   no variable como una pantalla de admin — ahí 2 columnas quedan
	   apretadas/salpicadas en vez de ahorrar espacio de verdad. Una sola
	   columna se ve prolija sin importar cuántos países haya. */
	.block-editor-block-inspector ul.acf-checkbox-list {
		display: block;
	}
	.block-editor-block-inspector ul.acf-checkbox-list > li {
		width: 100%;
	}
	.jb3-acf-invertir-wrap {
		list-style: none;
		margin: 0 0 8px !important;
		/* flex-basis:100% fuerza al grupo de botones a ocupar todo el ancho de
		   la fila y empuja las opciones a arrancar en la línea siguiente, en
		   vez de quedar flotando como si fuera una opción más al lado de la
		   primera. */
		flex-basis: 100%;
	}
	.jb3-acf-botones {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}
	button.jb3-acf-invertir {
		font-size: 12px !important;
	}
	/* El panel nativo de taxonomías del editor de bloques agrega un buscador
	   arriba de la lista de checkboxes — útil para Etiquetas (decenas de
	   términos), pero de más en Países/Canales: son pocos y ya conocidos de
	   memoria, el buscador solo ocupa espacio. Se apunta puntual a esas dos
	   (por aria-label, que Gutenberg pone con el nombre de la taxonomía) sin
	   tocar Etiquetas ni ninguna otra. :has() con selector de hermano
	   siguiente: "el buscador que tiene inmediatamente después la lista de
	   Países/Canales" — soportado en navegadores actuales (Chrome/Edge).
	   El buscador y la lista son hermanos, no hay una clase común más
	   específica para agarrar solo el buscador de memoria. */
	.components-search-control:has( + .editor-post-taxonomies__hierarchical-terms-list[aria-label="Países"] ),
	.components-search-control:has( + .editor-post-taxonomies__hierarchical-terms-list[aria-label="Canales"] ) {
		display: none;
	}
	</style>
	<?php
}, 20 );
