<footer class="site-footer">
 <div class="footer-grid">
  <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-brand">АБСУРД<span>.</span></a>
  <div class="footer-links"><a href="<?php echo esc_url(absurd_stage_page_link('afisha')); ?>">Афиша</a><a href="<?php echo esc_url(absurd_stage_page_link('o-teatre')); ?>">О театре</a><a href="<?php echo esc_url(absurd_stage_page_link('kontakty')); ?>">Контакты</a></div>
  <a class="footer-telegram" href="<?php echo esc_url(absurd_stage_telegram()); ?>" target="_blank" rel="noopener noreferrer">Telegram ↗</a>
 </div>
 <div class="footer-bottom"><span>© <?php echo esc_html(date_i18n('Y')); ?> Театр «АБСУРД»</span><span>Локальный дизайн-прототип · данные афиши ожидают подтверждения</span></div>
</footer>
<?php wp_footer(); ?>
</body></html>
