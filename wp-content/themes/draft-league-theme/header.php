<?php
/**
 * Site header.
 *
 * @package Draft_League_Theme
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="dl-site-header">
	<div class="dl-site-shell dl-site-header__inner">
		<a class="dl-site-brand" href="<?php echo esc_url(home_url('/')); ?>">
			<?php echo esc_html(draft_league_theme_site_name()); ?>
		</a>

		<button class="dl-menu-toggle" type="button" aria-controls="dl-primary-menu" aria-expanded="false">
			<span class="dl-menu-toggle__bar"></span>
			<span class="dl-menu-toggle__bar"></span>
			<span class="dl-menu-toggle__bar"></span>
			<span class="screen-reader-text"><?php echo esc_html__('Menu', 'draft-league-theme'); ?></span>
		</button>

		<nav id="dl-primary-menu" class="dl-site-nav dl-site-nav--primary" aria-label="<?php echo esc_attr__('Primary menu', 'draft-league-theme'); ?>">
			<?php
			draft_league_theme_nav_menu('primary', 'Main Menu');
			?>
		</nav>
	</div>
</header>

<main id="content" class="dl-site-main">
