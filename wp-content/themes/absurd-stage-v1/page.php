<?php get_header(); ?>
<main id="main" class="inner-page"><div class="container">
 <p class="eyebrow"><span class="eyebrow-index">АБСУРД / </span> ИНФОРМАЦИЯ</p>
 <h1 class="page-display"><?php the_title(); ?><span class="heading-point">.</span></h1>
 <?php while(have_posts()): the_post(); ?>
  <div class="page-body"><?php the_content(); ?></div>
 <?php endwhile; ?>
 <?php if(is_page('kontakty')): ?>
 <div class="contact-callout"><p>Официальный канал театра</p><a href="<?php echo esc_url(absurd_stage_telegram()); ?>" target="_blank" rel="noopener noreferrer">Открыть Telegram ↗</a></div>
 <?php endif; ?>
 </div></main>
<?php get_footer(); ?>
