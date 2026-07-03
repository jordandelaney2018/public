<?php
/**
 * Single post template.
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
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</div>

<?php
get_footer();
