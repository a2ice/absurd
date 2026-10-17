<?php if (!defined('ABSPATH')) { exit; } ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0A0A">
<?php wp_head(); ?>
</head>
<body <?php body_class('absurd-stage'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">К основному содержимому</a>
<header class="site-header" id="page-top">
 <div class="header-inner">
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="АБСУРД — на главную">АБСУРД<span class="brand-period">.</span></a>
  <nav id="primary-navigation" class="primary-nav" aria-label="Основная навигация">
    <a href="<?php echo esc_url(absurd_stage_page_link('afisha')); ?>">Афиша</a>
    <a href="<?php echo esc_url(absurd_stage_page_link('o-teatre')); ?>">О театре</a>
    <a href="<?php echo esc_url(absurd_stage_page_link('kontakty')); ?>">Контакты</a>
    <a class="nav-telegram" href="<?php echo esc_url(absurd_stage_telegram()); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> Telegram</a>
  </nav>
  <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" aria-label="Открыть меню"><span></span><span></span><span></span></button>
 </div>
</header>
