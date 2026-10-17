<?php get_header(); ?><main id="main" class="inner-page"><div class="container">
<a class="text-link" href="<?php echo esc_url(absurd_stage_page_link('afisha')); ?>">← Вернуться к афише</a>
<?php while(have_posts()):the_post(); ?>
<p class="eyebrow"><span class="eyebrow-index">СПЕКТАКЛЬ / </span> МАТЕРИАЛ ТЕАТРА</p><h1 class="page-display"><?php the_title(); ?></h1>
<div class="page-body"><?php the_content(); ?></div>
<p class="detail-note">Даты, время, площадка и информация о билетах публикуются только после подтверждения.</p>
<?php endwhile; ?></div></main><?php get_footer(); ?>
