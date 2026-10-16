<?php get_header(); ?>
<main id="main">
 <section class="hero" aria-labelledby="hero-heading">
  <div class="hero-media" aria-hidden="true">
   <picture>
     <source media="(max-width: 700px)" srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/images/curtain-mobile.webp'); ?>">
     <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/curtain-desktop.webp'); ?>" width="696" height="905" fetchpriority="high" alt="">
   </picture>
   <div class="curtain-panels" aria-hidden="true"><span></span><span></span></div>
  </div>
  <div class="hero-grid container">
   <div class="hero-content">
    <p class="eyebrow"><span class="eyebrow-dot"></span> ТЕАТР «АБСУРД»</p>
    <h1 id="hero-heading" class="hero-wordmark">АБСУРД</h1>
    <div class="hero-actions">
      <a class="button button-lime" href="<?php echo esc_url(absurd_stage_page_link('afisha')); ?>">Афиша <?php echo absurd_stage_svg_arrow(); ?></a>
      <a class="button button-outline" href="<?php echo esc_url(absurd_stage_telegram()); ?>" target="_blank" rel="noopener noreferrer">Telegram <span aria-hidden="true">↗</span></a>
    </div>
    <div class="hero-notice"><span class="notice-icon" aria-hidden="true">▦</span><span><strong>Даты показов пока не объявлены</strong><small>Следите за анонсами в Telegram</small></span></div>
   </div>
  </div>
  <a class="scroll-indicator" href="#afisha-preview" aria-label="Прокрутить к афише"><span>Листайте вниз</span><span aria-hidden="true">↓</span></a>
 </section>

 <section class="section shows-section" id="afisha-preview" aria-labelledby="shows-heading">
  <div class="container">
   <div class="section-top"><p class="eyebrow"><span class="eyebrow-index">01 / </span> ВСТРЕЧА СО ЗРИТЕЛЕМ</p><span class="section-edge">НАЧАЛО — ЗДЕСЬ</span></div>
   <div class="section-heading-line"><h2 id="shows-heading">Афиша<span class="heading-point">.</span></h2><p>Информация о будущих показах появится после подтверждения расписания театром.</p></div>
   <div class="empty-program">
    <div class="empty-program-art" aria-hidden="true"><span class="empty-orbit"></span><span class="empty-center">?</span></div>
    <div class="empty-program-copy"><span class="overline">В ОЖИДАНИИ ПЕРВОГО АКТА</span><h3>Сцена пока ждёт<br>своего часа.</h3><p>Даты показов ещё не опубликованы. Анонсы появятся в официальном канале театра.</p><a href="<?php echo esc_url(absurd_stage_telegram()); ?>" class="text-link" target="_blank" rel="noopener noreferrer">Перейти в Telegram <span aria-hidden="true">↗</span></a></div>
   </div>
  </div>
 </section>

 <section class="section about-section" id="about-preview" aria-labelledby="about-heading">
   <div class="container about-grid">
    <div class="about-label"><p class="eyebrow"><span class="eyebrow-index">02 / </span> О ТЕАТРЕ</p><div class="about-mark" aria-hidden="true"><span></span><span></span></div></div>
    <div class="about-content"><h2 id="about-heading">За кулисами<br><em>обычного.</em></h2><p>«АБСУРД» — название театра. Здесь появятся его история, команда и описание постановок после согласования материалов.</p><a class="text-link" href="<?php echo esc_url(absurd_stage_page_link('o-teatre')); ?>">Подробнее о театре <span aria-hidden="true">↗</span></a></div>
   </div>
 </section>

 <section class="contact-section section" id="contact-preview">
  <div class="container contact-grid">
   <p class="eyebrow"><span class="eyebrow-index">03 / </span> ОСТАВАЙТЕСЬ НА СВЯЗИ</p>
   <div><h2>Продолжение<br><em>следует.</em></h2><p>Новости и анонсы театра — в его официальном Telegram-канале.</p><a href="<?php echo esc_url(absurd_stage_telegram()); ?>" target="_blank" rel="noopener noreferrer" class="button button-lime">Открыть Telegram <?php echo absurd_stage_svg_arrow(); ?></a></div>
   <span class="contact-accent" aria-hidden="true">↗</span>
  </div>
 </section>
</main>
<?php get_footer(); ?>
