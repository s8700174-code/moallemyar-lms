<?php defined('ABSPATH') || exit; get_header(); ?><main class="content-page"><div class="container"><article class="content-card"><?php while(have_posts()){the_post();?><h1><?php the_title(); ?></h1><?php the_content();} ?></article></div></main><?php get_footer(); ?>

