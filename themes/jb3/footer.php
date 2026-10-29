<?php
/**
 * Pie: parte de bloques "footer" + cierre del documento.
 *
 * @package JB3
 */

defined( 'ABSPATH' ) || exit;
?>
	<footer class="site-footer">
		<?php jb3_template_part( 'footer' ); ?>
	</footer>
</div><!-- .site -->
<?php wp_footer(); ?>
</body>
</html>
