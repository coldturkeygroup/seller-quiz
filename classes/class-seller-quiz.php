<?php namespace ColdTurkey\SellerQuiz;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

// Composer autoloader
require_once SELLER_QUIZ_PLUGIN_PATH . 'assets/vendor/autoload.php';

use ColdTurkey\SellerQuiz\FrontDesk;

class SellerQuiz
{
    private $dir;
    private $file;
    private $assets_dir;
    private $assets_url;
    private $template_path;
    private $token;
    private $home_url;
    private $frontdesk;

    /**
     * Basic constructor for the Seller Quiz class
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->dir = dirname($file);
        $this->file = $file;
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $file)));
        $this->template_path = trailingslashit($this->dir) . 'templates/';
        $this->home_url = trailingslashit(home_url());
        $this->token = 'pf_seller_quiz';
        $this->frontdesk = new FrontDesk();

        global $wpdb;
        $this->table_name = $wpdb->base_prefix . $this->token;

        // Register 'pf_seller_quiz' post type
        add_action('init', [$this, 'register_post_type']);

        // Use built-in templates for landing pages
        add_action('template_redirect', [$this, 'page_templates'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 900);

        // Handle form submissions
        add_action('wp_ajax_pf_seller_quiz_submit_quiz', [$this, 'process_quiz']);
        add_action('wp_ajax_nopriv_pf_seller_quiz_submit_quiz', [$this, 'process_quiz']);
        add_action('wp_ajax_pf_seller_quiz_submit_offer', [$this, 'process_offer']);
        add_action('wp_ajax_nopriv_pf_seller_quiz_submit_offer', [$this, 'process_offer']);
        add_action('admin_post_pf_seller_quiz_remove_leads', [$this, 'remove_leads']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'meta_box_setup'], 20);
            add_action('save_post', [$this, 'meta_box_save']);
            add_filter('post_updated_messages', [$this, 'updated_messages']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles'], 10);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 10);
            add_filter('manage_edit-' . $this->token . '_columns', [
                $this,
                'register_custom_column_headings'
            ], 10, 1);
            add_filter('enter_title_here', [$this, 'change_default_title']);
            // Create FrontDesk Campaigns for pages
            add_action('publish_pf_seller_quiz', [$this, 'create_frontdesk_campaign']);
        }

        // Flush rewrite rules on plugin activation
        register_activation_hook($file, [$this, 'rewrite_flush']);
    }

    /**
     * Functions to be called when the plugin is
     * deactivated and then reactivated.
     *
     */
    public function rewrite_flush()
    {
        $this->register_post_type();
        $this->build_database_table();
        flush_rewrite_rules();
    }

    /**
     * Registers the Seller Quiz custom post type
     * with WordPress, used for our pages.
     *
     */
    public function register_post_type()
    {
        $labels = [
            'name' => _x('Seller Quizzes', 'post type general name', $this->token),
            'singular_name' => _x('Seller Quiz', 'post type singular name', $this->token),
            'add_new' => _x('Add New', $this->token, $this->token),
            'add_new_item' => sprintf(__('Add New %s', $this->token), __('Seller Quiz', $this->token)),
            'edit_item' => sprintf(__('Edit %s', $this->token), __('Seller Quiz', $this->token)),
            'new_item' => sprintf(__('New %s', $this->token), __('Seller Quiz', $this->token)),
            'all_items' => sprintf(__('All %s', $this->token), __('Seller Quizzes', $this->token)),
            'view_item' => sprintf(__('View %s', $this->token), __('Seller Quiz', $this->token)),
            'search_items' => sprintf(__('Search %a', $this->token), __('Seller Quizzes', $this->token)),
            'not_found' => sprintf(__('No %s Found', $this->token), __('Seller Quizzes', $this->token)),
            'not_found_in_trash' => sprintf(__('No %s Found In Trash', $this->token), __('Seller Quizzes', $this->token)),
            'parent_item_colon' => '',
            'menu_name' => __('Seller Quizzes', $this->token)
        ];

        $slug = __('seller-quiz', 'pf_seller_quiz');
        $custom_slug = get_option('pf_seller_quiz_slug');
        if ($custom_slug && strlen($custom_slug) > 0 && $custom_slug != '') {
            $slug = $custom_slug;
        }

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => $slug],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title'],
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-quiz'
        ];

        register_post_type($this->token, $args);
    }

    /**
     * Construct the actual database table that
     * will be used with all of the pages for
     * this plugin. The table stores data
     * from visitors and form submissions.
     *
     */
    public function build_database_table()
    {
        global $wpdb;
        $table_name = $wpdb->base_prefix . $this->token;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = '';

            if (!empty($wpdb->charset)) {
                $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
            }

            if (!empty($wpdb->collate)) {
                $charset_collate .= " COLLATE {$wpdb->collate}";
            }

            $sql = "CREATE TABLE `$table_name` (
								`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
								`frontdesk_id` int(10) unsigned DEFAULT NULL,
								`blog_id` int(10) unsigned DEFAULT 0,
								`first_name` varchar(255) DEFAULT NULL,
								`last_name` varchar(255) DEFAULT NULL,
								`email` varchar(255) DEFAULT NULL,
								`address` varchar(255) NOT NULL,
								`address2` varchar(255) DEFAULT NULL,
								`phone` varchar(20) DEFAULT NULL,
								`score` varchar(20) DEFAULT NULL,
								`responses` varchar(50) DEFAULT NULL,
								`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
								`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
								PRIMARY KEY (`id`)
							) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Register the headings for our defined custom columns
     *
     * @param array $defaults
     *
     * @return array
     */
    public function register_custom_column_headings($defaults)
    {
        $new_columns = ['permalink' => __('Link', $this->token)];

        $last_item = '';

        if (count($defaults) > 2) {
            $last_item = array_slice($defaults, -1);

            array_pop($defaults);
        }
        $defaults = array_merge($defaults, $new_columns);

        if ($last_item != '') {
            foreach ($last_item as $k => $v) {
                $defaults[$k] = $v;
                break;
            }
        }

        return $defaults;
    }

    /**
     * Define the strings that will be displayed
     * for users based on different actions they
     * perform with the plugin in the dashboard.
     *
     * @param array $messages
     *
     * @return array
     */
    public function updated_messages($messages)
    {
        global $post, $post_ID;

        $messages[$this->token] = [
            0 => '', // Unused. Messages start at index 1.
            1 => sprintf(__('Quiz updated. %sView page%s.', $this->token), '<a href="' . esc_url(get_permalink($post_ID)) . '">', '</a>'),
            4 => __('Quiz updated.', $this->token),
            /* translators: %s: date and time of the revision */
            5 => isset($_GET['revision']) ? sprintf(__('Quiz restored to revision from %s.', $this->token), wp_post_revision_title((int)$_GET['revision'], false)) : false,
            6 => sprintf(__('Quiz published. %sView quiz%s.', $this->token), '<a href="' . esc_url(get_permalink($post_ID)) . '">', '</a>'),
            7 => __('Quiz saved.', $this->token),
            8 => sprintf(__('Quiz submitted. %sPreview quiz%s.', $this->token), '<a target="_blank" href="' . esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))) . '">', '</a>'),
            9 => sprintf(__('Quiz scheduled for: %1$s. %2$sPreview quiz%3$s.', $this->token), '<strong>' . date_i18n(__('M j, Y @ G:i', $this->token), strtotime($post->post_date)) . '</strong>', '<a target="_blank" href="' . esc_url(get_permalink($post_ID)) . '">', '</a>'),
            10 => sprintf(__('Quiz draft updated. %sPreview quiz%s.', $this->token), '<a target="_blank" href="' . esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))) . '">', '</a>'),
        ];

        return $messages;
    }

    /**
     * Build the meta box containing our custom fields
     * for our Seller Quiz post type creator & editor.
     *
     */
    public function meta_box_setup()
    {
        add_meta_box($this->token . '-data', __('Seller Quiz Details', $this->token), [
            $this,
            'meta_box_content'
        ], $this->token, 'normal', 'high');

        do_action($this->token . '_meta_boxes');
    }

    /**
     * Build the custom fields that will be displayed
     * in the meta box for our Seller Quiz post type.
     *
     */
    public function meta_box_content()
    {
        global $post_id;
        $fields = get_post_custom($post_id);
        $field_data = $this->get_custom_fields_settings();

        $html = '';

        $html .= '<input type="hidden" name="' . $this->token . '_nonce" id="' . $this->token . '_nonce" value="' . wp_create_nonce(plugin_basename($this->dir)) . '" />';

        if (0 < count($field_data)) {
            $html .= '<table class="form-table">' . "\n";
            $html .= '<tbody>' . "\n";

            $html .= '<input id="' . $this->token . '_post_id" type="hidden" value="' . $post_id . '" />';

            foreach ($field_data as $k => $v) {
                $data = $v['default'];
                $placeholder = $v['placeholder'];
                $type = $v['type'];
                if (isset($fields[$k]) && isset($fields[$k][0])) {
                    $data = $fields[$k][0];
                }

                if ($type == 'text') {
                    $html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr($k) . '">' . $v['name'] . '</label></th><td>';
                    $html .= '<input style="width:100%" name="' . esc_attr($k) . '" id="' . esc_attr($k) . '" placeholder="' . esc_attr($placeholder) . '" type="text" value="' . esc_attr($data) . '" />';
                    $html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
                    $html .= '</td><tr/>' . "\n";
                } elseif ($type == 'posts') {
                    $html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr($k) . '">' . $v['name'] . '</label></th><td>';
                    $html .= '<select style="width:100%" name="' . esc_attr($k) . '" id="' . esc_attr($k) . '">';
                    $html .= '<option value="">Select a Page to Use</option>';

                    // Query posts
                    global $post;
                    $args = [
                        'posts_per_page' => 20,
                        'post_type' => $v['default'],
                        'post_status' => 'publish'
                    ];
                    $custom_posts = get_posts($args);
                    foreach ($custom_posts as $post) : setup_postdata($post);
                        $link = str_replace(home_url(), '', get_permalink());
                        $selected = '';
                        if ($link == $data)
                            $selected = 'selected';

                        $html .= '<option value="' . $link . '" ' . $selected . '>' . get_the_title() . '</option>';
                    endforeach;
                    wp_reset_postdata();

                    $html .= '</select><p class="description">' . $v['description'] . '</p>' . "\n";
                    $html .= '</td><tr/>' . "\n";
                } elseif ($type == 'select') {
                    $html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr($k) . '">' . $v['name'] . '</label></th><td>';
                    $html .= '<select style="width:100%" name="' . esc_attr($k) . '" id="' . esc_attr($k) . '">';
                    foreach ($v['options'] as $option) {
                        $selected = '';
                        if ($option == $data)
                            $selected = 'selected';

                        $html .= '<option value="' . $option . '" ' . $selected . '>' . ucfirst($option) . '</option>';
                    }
                    $html .= '</select><p class="description">' . $v['description'] . '</p>' . "\n";
                    $html .= '</td><tr/>' . "\n";
                } elseif ($type == 'upload') {
                    $html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr($k) . '">' . $v['name'] . '</label></th><td><input type="button" class="button" id="upload_media_file_button" value="' . __('Upload Image', $this->token) . '" data-uploader_title="Choose an image" data-uploader_button_text="Insert image file" /><input name="' . esc_attr($k) . '" type="text" id="upload_media_file" class="regular-text" value="' . esc_attr($data) . '" />' . "\n";
                    $html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
                    $html .= '</td><tr/>' . "\n";
                } else {
                    $default_color = '';
                    $html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr($k) . '">' . $v['name'] . '</label></th><td>';
                    $html .= '<input name="' . esc_attr($k) . '" id="primary_color" class="quiz-color"  type="text" value="' . esc_attr($data) . '"' . $default_color . ' />';
                    $html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
                    $html .= '</td><tr/>' . "\n";
                }

                $html .= '</td><tr/>' . "\n";
            }

            $html .= '</tbody>' . "\n";
            $html .= '</table>' . "\n";
        }

        echo $html;
    }

    /**
     * Save the data entered by the user using
     * the custom fields for our Seller Quiz post type.
     *
     * @param integer $post_id
     *
     * @return int
     */
    public function meta_box_save($post_id)
    {
        // Verify
        if ((get_post_type() != $this->token) || !wp_verify_nonce($_POST[$this->token . '_nonce'], plugin_basename($this->dir))) {
            return $post_id;
        }

        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }
        }

        $field_data = $this->get_custom_fields_settings();
        $fields = array_keys($field_data);

        foreach ($fields as $f) {

            if (isset($_POST[$f])) {
                ${$f} = strip_tags(trim($_POST[$f]));
            }

            // Escape the URLs.
            if ('url' == $field_data[$f]['type']) {
                ${$f} = esc_url(${$f});
            }

            if (${$f} == '') {
                delete_post_meta($post_id, $f, get_post_meta($post_id, $f, true));
            } else {
                update_post_meta($post_id, $f, ${$f});
            }
        }

    }

    /**
     * Register the stylesheets that will be
     * used for our scripts in the dashboard.
     *
     */
    public function enqueue_admin_styles()
    {
        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Register the Javascript files that will be
     * used for our scripts in the dashboard.
     */
    public function enqueue_admin_scripts()
    {
        // Admin JS
        wp_register_script($this->token . '-admin', esc_url($this->assets_url . 'js/admin.js'), [
            'jquery',
            'wp-color-picker'
        ], '1.0.0');
        wp_enqueue_script($this->token . '-admin');
    }

    /**
     * Register the Javascript files that will be
     * used for our templates.
     */
    public function enqueue_scripts()
    {
        if (is_singular($this->token)) {
            wp_register_style($this->token, esc_url($this->assets_url . 'css/sellerquiz.css'), [], SELLER_QUIZ_PLUGIN_VERSION);
            wp_register_style('animate', esc_url($this->assets_url . 'css/animate.css'), [], SELLER_QUIZ_PLUGIN_VERSION);
            wp_register_style('roboto', 'http://fonts.googleapis.com/css?family=Roboto:400,400italic,500,500italic,700,700italic,900,900italic,300italic,300');
            wp_register_style('robo-slab', 'http://fonts.googleapis.com/css?family=Roboto+Slab:400,700,300,100');
            wp_enqueue_style($this->token);
            wp_enqueue_style('animate');
            wp_enqueue_style('roboto');
            wp_enqueue_style('roboto-slab');

            wp_register_script('icheck', esc_url($this->assets_url . 'js/icheck.js'), ['jquery']);
            wp_register_script($this->token . '-js', esc_url($this->assets_url . 'js/scripts.js'), [
                'jquery',
                'icheck'
            ]);
            wp_enqueue_script('icheck');
            wp_enqueue_script($this->token . '-js');

            $localize = [
                'ajaxurl' => admin_url('admin-ajax.php')
            ];
            wp_localize_script($this->token . '-js', 'SellerQuiz', $localize);
        }
    }

    /**
     * Define the custom fields that will
     * be displayed and used for our
     * Seller Quiz post type.
     *
     * @return mixed
     */
    public function get_custom_fields_settings()
    {
        $fields = [];

        $fields['home_valuator'] = [
            'name' => __('Link To Home Valuator', $this->token),
            'description' => __('The last step of the funnel allows you to link the user to your Home Valuator. Enter the link for the funnel here.', $this->token),
            'placeholder' => '',
            'type' => 'posts',
            'default' => 'pf_valuator',
            'section' => 'info'
        ];

        $fields['area'] = [
            'name' => __('Quiz Area', $this->token),
            'description' => __('Question 4 requires you to define your area. Select to display your county or city.', $this->token),
            'placeholder' => '',
            'type' => 'select',
            'default' => '',
            'options' => ['county', 'city'],
            'section' => 'info'
        ];

        $fields['legal_broker'] = [
            'name' => __('Your Legal Broker', $this->token),
            'description' => __('This will be displayed on the bottom of each page.', $this->token),
            'placeholder' => '',
            'type' => 'text',
            'default' => '',
            'section' => 'info'
        ];

        $fields['retargeting'] = [
            'name' => __('Retargeting (optional)', $this->token),
            'description' => __('Facebook retargeting pixel to allow retargeting of people that view this page. (optional).', $this->token),
            'placeholder' => __('Ex: 4123423454', $this->token),
            'type' => 'text',
            'default' => '',
            'section' => 'info'
        ];

        $fields['conversion'] = [
            'name' => __('Conversion Tracking (optional)', $this->token),
            'description' => __('Facebook conversion tracking pixel to help track performance of your ad (optional).', $this->token),
            'placeholder' => __('Ex: 170432123454', $this->token),
            'type' => 'text',
            'default' => '',
            'section' => 'info'
        ];

        $fields['primary_color'] = [
            'name' => __('Primary Color', $this->token),
            'description' => __('Change the primary color of the quiz.', $this->token),
            'placeholder' => '',
            'type' => 'color',
            'default' => '',
            'section' => 'info'
        ];

        $fields['hover_color'] = [
            'name' => __('Hover Color', $this->token),
            'description' => __('Change the button hover color of the quiz.', $this->token),
            'placeholder' => '',
            'type' => 'color',
            'default' => '',
            'section' => 'info'
        ];

        return apply_filters($this->token . '_valuation_fields', $fields);
    }

    /**
     * Define the custom templates that
     * are used for our plugin.
     *
     */
    public function page_templates()
    {
        // Single seller quiz page template
        if (is_single() && get_post_type() == $this->token) {
            include($this->template_path . 'single-quiz.php');
            exit;
        }
    }

    /**
     * Get the optional media file selected for
     * a defined Seller Quiz.
     *
     * @param integer $pageID
     *
     * @return bool|string
     */
    public function get_media_file($pageID)
    {
        if ($pageID) {
            $file = get_post_meta($pageID, 'media_file', true);

            if (preg_match('/(\.jpg|\.png|\.bmp|\.gif)$/', $file))
                return '<img src="' . $file . '" style="margin-left:auto;margin-right:auto;margin-bottom:0px;display:block;" class="img-responsive img-thumbnail">';
        }

        return false;
    }

    /**
     * Create a campaign on tryfrontdesk.com
     * for a defined Seller Quiz created page.
     *
     * @param integer $post_ID
     *
     * @return bool
     */
    public function create_frontdesk_campaign($post_ID)
    {
        if (get_post_type($post_ID) != $this->token)
            return false;

        global $wpdb;
        $title = get_the_title($post_ID);
        $permalink = get_permalink($post_ID);

        // See if we're using domain mapping
        $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") == $wpdb->dmtable) {
            $blog_id = get_current_blog_id();
            $options_table = $wpdb->base_prefix . $blog_id . '_' . 'options';

            $mapped = $wpdb->get_var("SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$blog_id}' ORDER BY CHAR_LENGTH(domain) DESC LIMIT 1");
            $domain = $wpdb->get_var("SELECT option_value FROM {$options_table} WHERE option_name = 'siteurl' LIMIT 1");

            if ($mapped)
                $permalink = str_replace($domain, 'http://' . $mapped, $permalink);
        }

        $this->frontdesk->createCampaign($title, $permalink);
    }

    /**
     * Score the quiz answers provided by the user.
     *
     * @param $input
     *
     * @return array
     */
    protected function score_quiz($input)
    {
        $responses = [];
        $score = 0;
        $response = '';
        $feedback = [
            [
                '<em>Since you scored over 50 out of a possible 88</em>, you should be able to sell your house quickly and for top dollar. For the best results, talk to a licensed real estate agent who can give you advice on how to get top dollar for your home.',
                0,
                64
            ],
            [
                '<em>Congrats! Since you scored over 65 out of a possible 88</em>, you should be able to sell your house quickly and for top dollar.',
                65,
                74
            ],
            [
                '<em>Congrats! Since you scored over 75 out of a possible 88</em>, you will be able to sell your house quickly and for top dollar.',
                75,
                88
            ]
        ];

        foreach ($input as $key => $value) {
            if (strpos($key, 'question_') === false)
                continue;

            $answer_score = explode('-', $value);
            array_push($responses, $answer_score[0]);
            $score += $answer_score[1];
        }

        foreach ($feedback as $key => $value) {
            if ($score > $value[1] && $score < $value[2]) {
                $response = $value[0];
                break;
            }
        }

        return ['score' => $score, 'responses' => $responses, 'feedback' => $response];
    }

    /**
     * Format the responses to the quiz to be shown
     * in the email sent to the site admin.
     *
     * @param $responses
     *
     * @return array
     */
    protected function formatResponsesForEmail($responses)
    {
        $question_bank = [
            'How long have you owned your home?',
            'Do you need your home to sell in less than 90 days, or are you willing to wait for a potential buyer that might be willing to pay more money?',
            'Is your home newly renovated/updated, or does it currently need minor upgrades?',
            'Here in ' . get_option('platform_user_county', 'Kandiyohi') . ' County, certain price ranges sell a lot faster than other price ranges. What do you think your home is worth right now?',
            'What is the approximate age of your home?',
            'What is the condition of your roof/shingles?',
            'Does home equity play a major role in your retirement savings/strategy?',
            'Sometimes a home sale can affect your tax liability. Have you already spoken with an accountant?',
            'Are you prepared to "stage" your house during the time it is for sale? (Removing personal items like family photos, artwork, and rearranging furniture/layout so potential buyers can visualize it being their home—not yours).',
            'Are you willing to share closing costs with the potential buyer?',
            'Have you made any significant upgrades or renovations to your home since you purchased it?'
        ];
        $answer_bank = [
            [
                'a' => 'Less than 2 years',
                'b' => '2-5 years',
                'c' => '5-10 years',
                'd' => '10+ years'
            ],
            [
                'a' => 'My home needs to sell as soon as possible',
                'b' => 'I\'m willing to wait for the right price, even if it takes longer'
            ],
            [
                'a' => 'It definitely needs some work. I would need to make repairs and upgrades before selling.',
                'b' => 'It needs some cosmetic repairs, but nothing major (new paint, etc.)',
                'c' => 'It\'s pretty updated (newer appliances, newer interior)',
                'd' => 'My house is completely updated with brand new appliances, brand new interior (flooring, walls, etc.)'
            ],
            [
                'a' => 'Less than $150,000',
                'b' => '$150,000-$300,000',
                'c' => '$300,000-$600,000',
                'd' => '$600,000+'
            ],
            [
                'a' => 'It\'s less than 5 years old',
                'b' => 'It\'s 5-20 years old',
                'c' => '20-50 years old',
                'd' => '50 years+'
            ],
            [
                'a' => 'It\'s brand new',
                'b' => 'Less than 5 years old',
                'c' => 'Less than 15 years old',
                'd' => 'It probably needs to be replaced'
            ],
            ['a' => 'Yes', 'b' => 'No, not really'],
            ['a' => 'Yes', 'b' => 'No'],
            [
                'a' => 'Yes, I\'m willing to do whatever it takes to sell my home quickly and for top dollar—even if it means I have to temporarily take down family photos, get the house professionally cleaned, and maybe even paint rooms neutral colors to appeal to buyers.',
                'b' => 'Yes, I\'m willing to stage my home to appeal to buyers, however, I don\'t want to go overboard and spend a lot of time on it.',
                'c' => 'No, I believe my home will sell anyways, so I don\'t want to spend too much time on staging.',
                'd' => 'No, I do not have the time or money to stage my home.'
            ],
            ['a' => 'Yes', 'b' => 'No'],
            [
                'a' => 'Yes, I\'ve renovated/upgraded the bathroom',
                'b' => 'Yes, I\'ve renovated/upgraded the kitchen',
                'c' => 'Yes, I\'ve renovated/upgraded most of the house (kitchen, bathroom, bedrooms, living space, etc.)',
                'd' => 'No, I have not made any significant improvements'
            ]
        ];

        $formatted = [];

        foreach ($responses as $key => $value) {
            $question['question'] = $question_bank[$key];
            $question['answer'] = $answer_bank[$key][$value];

            array_push($formatted, $question);
        }

        return $formatted;
    }

    /**
     * Email the quiz results to the website admin
     *
     * @param $user_id
     */
    protected function emailResultsToAdmin($user_id)
    {
        // Get the prospect data saved previously
        global $wpdb;
        $subscriber = $wpdb->get_row('SELECT * FROM ' . $this->table_name . ' WHERE id = \'' . $user_id . '\' ORDER BY id DESC LIMIT 0,1');
        $responses = $this->formatResponsesForEmail(explode(',', $subscriber->responses));

        // Format the email and send it
        $admin_email = get_bloginfo('admin_email');
        $headers[] = 'From: Platform <info@platform.marketing>';
        $headers[] = 'Reply-To: ' . $subscriber->email;
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $subject = 'New Seller Quiz Submission';
        // Load template into message
        ob_start();
        include $this->template_path . 'single-email.php';
        $message = ob_get_contents();
        ob_end_clean();

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Process the quiz submission from the user.
     * Create a DB record for the user, and return the ID.
     * Create a prospect on tryfrontdesk.com with the given data.
     *
     * @return json
     */
    public function process_quiz()
    {
        if (isset($_POST[$this->token . '_nonce']) && wp_verify_nonce($_POST[$this->token . '_nonce'], $this->token . '_submit_quiz')) {
            global $wpdb;
            $blog_id = get_current_blog_id();
            $first_name = sanitize_text_field($_POST['first_name']);
            $email = sanitize_text_field($_POST['email']);
            $source = sanitize_text_field($_POST['permalink']);
            $score = $this->score_quiz($_POST);

            $wpdb->query($wpdb->prepare(
                'INSERT INTO ' . $this->table_name . '
				 ( blog_id, first_name, email, score, responses, created_at, updated_at )
				 VALUES ( %d, %s, %s, %s, %s, NOW(), NOW() )',
                [
                    $blog_id,
                    $first_name,
                    $email,
                    $score['score'],
                    implode(',', $score['responses'])
                ]
            ));

            $user_id = $wpdb->insert_id;

            // Create the prospect on FrontDesk
            $frontdesk_id = $this->frontdesk->createProspect([
                'source' => $source,
                'first_name' => $first_name,
                'email' => $email
            ]);

            if ($frontdesk_id != null) {
                $wpdb->query($wpdb->prepare(
                    'UPDATE ' . $this->table_name . '
					 SET frontdesk_id = %s
					 WHERE id = \'' . $user_id . '\'',
                    [
                        $frontdesk_id
                    ]
                ));
            }

            // TODO: Create a note on Frontdesk with the prospect's responses

            // Email the blog owner the details for the new prospect
            $this->emailResultsToAdmin($user_id);

            echo json_encode(['user_id' => $user_id, 'score' => $score['score'], 'feedback' => $score['feedback']]);
            die();
        }
    }

    /**
     * Perform the required actions after the user submits
     * their data to take advantage of the given offer.
     * Update the user record with the newly given data.
     * Update the prospect on tryfrontdesk.com with the given data.
     *
     * @return json
     */
    public function process_offer()
    {
        if (isset($_POST[$this->token . '_nonce']) && wp_verify_nonce($_POST[$this->token . '_nonce'], $this->token . '_submit_offer')) {
            global $wpdb;
            $user_id = sanitize_text_field($_POST['user_id']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $address = sanitize_text_field($_POST['address']);
            $address_2 = sanitize_text_field($_POST['address_2']);
            $city = sanitize_text_field($_POST['city']);
            $state = sanitize_text_field($_POST['state']);
            $zip_code = sanitize_text_field($_POST['zip_code']);
            $phone = sanitize_text_field($_POST['phone']);

            // Get the prospect data saved previously
            $subscriber = $wpdb->get_row('SELECT frontdesk_id, email FROM ' . $this->table_name . ' WHERE id = \'' . $user_id . '\' ORDER BY id DESC LIMIT 0,1');

            // Update the FrontDesk prospect if exists
            if ($subscriber->frontdesk_id != null) {
                $this->frontdesk->updateProspect($subscriber->frontdesk_id, [
                    'email' => $subscriber->email,
                    'last_name' => $last_name,
                    'address' => $address,
                    'address_2' => $address_2,
                    'city' => $city,
                    'state' => $state,
                    'zip_code' => $zip_code,
                    'phone' => $phone
                ]);
            }

            // Update the prospect data
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . $this->table_name . '
			 SET last_name = %s, address = %s, address2 = %s, phone = %s
			 WHERE id = \'' . $user_id . '\'',
                [
                    $last_name,
                    $address . ', ' . $city . ', ' . $state . ' ' . $zip_code,
                    $address_2,
                    $phone
                ]
            ));

            echo json_encode('success');
            die();
        }
    }

    /**
     * Change the post title placeholder text
     * for the custom post editor.
     *
     * @param $title
     *
     * @return string
     */
    public function change_default_title($title)
    {
        $screen = get_current_screen();

        if ($this->token == $screen->post_type) {
            $title = 'Enter a title for your seller quiz';
        }

        return $title;
    }

    /**
     * Remove the specified leads from the
     * leads table and the database.
     */
    public function remove_leads()
    {
        global $wpdb;
        $leads_to_delete = implode(',', $_POST['delete_lead']);

        // Update the prospect data
        $wpdb->query($wpdb->prepare(
            'DELETE FROM `' . $this->table_name . '`
			 WHERE `id` IN (' . $leads_to_delete . ')'
        ));

        wp_redirect(admin_url('edit.php?post_type=' . $this->token . '&page=' . $this->token . '_leads&deleted=true'));
        die();
    }

}