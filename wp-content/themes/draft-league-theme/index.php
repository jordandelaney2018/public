<?php
/**
 * Main template.
 *
 * @package Draft_League_Theme
 */

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>

<div class="dl-site-shell dl-content">
	<?php if (have_posts()) : ?>
		<?php while (have_posts()) : ?>
			<?php the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php if (!is_front_page()) : ?>
					<h1><?php the_title(); ?></h1>
				<?php endif; ?>

				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php echo esc_html__('Nothing found.', 'draft-league-theme'); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
