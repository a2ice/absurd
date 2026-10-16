<?php
/**
 * АБСУРД — isolated local portfolio theme (art-direction prototype).
 * @package AbsurdStage
 */
if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    register_nav_menus(['primary' => 'Основная навигация']);
});

add_action('wp_enqueue_scripts', static function (): void {
    $dir = get_template_directory();
    wp_enqueue_style('absurd-stage', get_stylesheet_uri(), [], (string) filemtime($dir . '/style.css'));
    wp_enqueue_script('absurd-stage-interactions', get_template_directory_uri() . '/assets/js/site.js', [], (string) filemtime($dir . '/assets/js/site.js'), true);
});

function absurd_stage_telegram(): string {
    return 'https://t.me/absurd_theater_now';
}

function absurd_stage_svg_arrow(): string {
    return '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>';
}

function absurd_stage_page_link(string $slug): string {
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . $slug . '/');
}
