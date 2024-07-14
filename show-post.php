<?php

/** Plugin name: Show post
 * Author: El Stasio
 * Version: 1.0
 * Description: This plugin adds a shortcode that displays the title and excerpt of a specified post.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';


use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ShowPost
{
    private $logger;

    public function __construct (LoggerInterface $logger)
    {
        $this->logger = $logger;
        add_shortcode('display_post', [$this, 'displayPost']);
        add_action('admin_menu', [$this, 'add_show_post_admin_page']);
        add_action('admin_init', [$this, 'register_custom_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('admin_post_save_show_post_data', [$this, 'save_show_post_data_handler']);

    }

    public function displayPost ()
    {
        $this->logger->info('Executing displayPost method.');
        $postSettings = json_decode(get_option('show_post_data'), true);

        if (isset($postSettings['category']) && $postSettings['category'] == "allCategory") {
            $postSettings['category'] = '';
        }
        try {
            $posts = get_posts(array(
                'numberposts'   => $postSettings['qualityPost'] ?? '10',
                'category_name' => $postSettings['category'],
            ));
            ?>
            <div class="post-list">
                <?php
                foreach ($posts as $post):
                    ?>
                    <div class="post-list__item">
                        <div class="post-list__title"><?= $post->post_title ?></div>
                        <div class="post-list__descr"><?= $post->post_excerpt ?></div>
                        <div class="post-list__link">
                            <a href="<?= get_permalink($post->ID) ?>">Подробнее</a>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
            <?php
        } catch (Exception $e) {
            $this->logger->error('Error displaying last posts: ' . $e->getMessage());
            return '<p>An error occurred while fetching posts.</p>';
        }

    }

    public function register_custom_settings ()
    {
        register_setting('show_post_options_group', 'show_post_data', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => json_encode(['qualityPost' => 10, 'category' => ''])
        ]);
    }

    public function save_show_post_data_handler ()
    {
        $this->logger->info('Executing save_show_post_data_handler method.');
        if (!empty($_POST['action']) && $_POST['action'] == 'save_show_post_data') {
            update_option('show_post_data', json_encode($_POST['settings']));
            $this->logger->info('Post data updated.', $_POST['settings']);

        }else {
            $this->logger->warning('No post data found.');
        }
        wp_redirect(admin_url('admin.php?page=show-post&status=success'));
        exit;

    }

    public function add_settings_link ($links = [])
    {
        $settings_link = '<a href="admin.php?page=show-post">Настройки</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_show_post_admin_page ()
    {
        add_menu_page(
            'Show post',
            'Show post',
            'manage_options',
            'show-post',
            [$this, 'render_show_post_admin_page']
        );
    }

    public function render_show_post_admin_page ()
    {
        ?>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Show post</title>
            <link rel="stylesheet" href="/wp-content/plugins/show-post/admin/css/show-post.css">
        </head>

        <form class="post__settings" method="post" action="<?= esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="save_show_post_data">
            <?php
            $settings   = json_decode(get_option('show_post_data'), true);
            $categories = get_categories();

            ?>
            <div class="posts-settings">
                <h1>Настройка вывода постов</h1>
                <div class="posts-settings__quality">
                    <label for="settings[qualityPost]">Количество выводимых постов</label>
                    <input type="number" name="settings[qualityPost]"
                           value="<?= $settings['qualityPost'] ? $settings['qualityPost'] : '' ?>"
                           placeholder="Введите количество выводимых постов">
                </div>

                <div class="posts-settings__categories">
                    <label for="settings[qualityPost]">Нужная рубрика</label>
                    <select name="settings[category]" id="">
                        <option value="allCategory">Все записи</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category->slug ?>" <?= $settings['category'] == $category->slug ? 'selected="selected"' : '' ?>><?= $category->name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
        <div class="projects__btns">
            <?= submit_button("Сохранить", 'primary post__btn post__btn--save', 'button') ?>
        </div>
        <script src="/wp-content/plugins/show-post/admin/js/show-post.js"></script>
        <?php

    }
    public function my_ajax_handler (){
        wp_send_json_success('Кнопка нажата');
    }
}

$logger = new Logger('wp_show_post');
$logger->pushHandler(new StreamHandler(plugin_dir_path(__FILE__) . 'logs/plugin.log', Logger::DEBUG));
// Инициализируем плагин
$show_post_plugin = new ShowPost($logger);