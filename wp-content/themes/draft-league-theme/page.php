<?php
/**
 * Page template.
 *
 * @package Draft_League_Theme
 */

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>

<div class="dl-site-shell dl-content">
	<?php while (have_posts()) : ?>
		<?php the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if (!is_front_page()) : ?>
				<h1><?php the_title(); ?></h1>
			<?php endif; ?>

			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</div>

<?php
get_footer();
