<?php
/**
 * Plugin Name: Test
 * Plugin URI: https://example.com
 * Description: Простий тестовий плагін з сторінкою налаштувань.
 * Version: 1.0.1
 * Requires at least: 6.6.2
 * Tested up to:      6.7.2
 * Requires PHP:      7.4
 * Author: Розробник WordPress
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: test-settings-plugin
 * Domain Path: /languages
 */

// Захист від прямого доступу
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Клас плагіну
class Test_Settings_Plugin {
    
    // Версія плагіну
    private $version = '1.0.1';
    
    // Назва плагіну
    private $plugin_name = 'Test';
    
    // Конструктор
    public function __construct() {
        // Додаємо пункт меню в адмінці
        add_action('admin_menu', array($this, 'add_plugin_page'));
    }
    
    // Додаємо пункт меню
    public function add_plugin_page() {
        add_menu_page(
            __('Тестовий плагін', 'test-settings-plugin'), // Заголовок сторінки
            __('Тестовий плагін', 'test-settings-plugin'), // Текст в меню
            'manage_options', // Права доступу
            'test-settings-plugin', // Slug сторінки
            array($this, 'create_admin_page'), // Функція відображення сторінки
            'dashicons-admin-generic', // Іконка
            100 // Позиція в меню
        );
    }
    
    // Відображення сторінки налаштувань
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->plugin_name); ?></h1>
            <div class="card">
                <h2><?php _e('Інформація про плагін', 'test-settings-plugin'); ?></h2>
                <p><strong><?php _e('Назва плагіну:', 'test-settings-plugin'); ?></strong> <?php echo esc_html($this->plugin_name); ?></p>
                <p><strong><?php _e('Версія плагіну:', 'test-settings-plugin'); ?></strong> <?php echo esc_html($this->version); ?></p>
            </div>
        </div>
        <?php
    }
    
    // Ініціалізація плагіну
    public static function init() {
        $instance = new self();
        return $instance;
    }
}

// Запуск плагіну
Test_Settings_Plugin::init(); 

// Додаємо перевірку оновлень через GitHub
require_once plugin_dir_path( __FILE__ ) . 'updates/github-updater.php';

// Ініціалізуємо систему оновлень
if ( is_admin() ) {
    new Test_GitHub_Updater(
        __FILE__,
        'pekarskyi',  // Ваш GitHub логін
        'test',       // Назва репозиторію
        ''            // GitHub access token (опціонально)
    );
} 