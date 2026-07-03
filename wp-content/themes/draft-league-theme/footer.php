<?php
/**
 * Site footer.
 *
 * @package Draft_League_Theme
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
</main>

<footer class="dl-site-footer">
	<div class="dl-site-shell dl-site-footer__inner">
		<p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(draft_league_theme_site_name()); ?></p>

		<nav class="dl-site-nav dl-site-nav--footer" aria-label="<?php echo esc_attr__('Footer menu', 'draft-league-theme'); ?>">
			<?php
			draft_league_theme_nav_menu('footer', 'Footer Menu');
			?>
		</nav>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
