<?php
/*
Plugin Name: teamburgerberaad-plugin
Plugin URI: https://github.com/PH-F/teamburgerberaad-plugin
Description: 2 extra posttypes.
Version: 1.0.3
Author: @PH-F
Author URI: https://github.com/PH-F
License: MIT
License URI: http://opensource.org/licenses/MIT
*/

if (!function_exists('hello_elementor_customizer')) {
    // Customizer controls
    function hello_elementor_customizer()
    {
        if (!is_customize_preview()) {
            return;
        }

        if (!hello_elementor_display_header_footer()) {
            return;
        }

        require get_template_directory() . '/includes/customizer-functions.php';
    }
}
add_action('init', 'hello_elementor_customizer');

if (!function_exists('hello_elementor_check_hide_title')) {
    /**
     * Check whether to display the page title.
     *
     * @param bool $val default value.
     *
     * @return bool
     */
    function hello_elementor_check_hide_title($val)
    {
        if (defined('ELEMENTOR_VERSION')) {
            $current_doc = Elementor\Plugin::instance()->documents->get(get_the_ID());
            if ($current_doc && 'yes' === $current_doc->get_settings('hide_title')) {
                $val = false;
            }
        }
        return $val;
    }
}
add_filter('hello_elementor_page_title', 'hello_elementor_check_hide_title');

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if (!function_exists('hello_elementor_body_open')) {
    function hello_elementor_body_open()
    {
        wp_body_open();
    }
}

if (!function_exists('custom_post_type')) {
    // ------------------------------
    // Register Custom Post Type
    // ------------------------------

    function custom_post_type()
    {
        $args = array(
            'label' => 'Projecten',
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'projects'),
            'supports' => array('title', 'excerpt', 'editor', 'thumbnail'),
        );

        register_post_type('custom_post', $args);
    }

    // ------------------------------
    // Custom fields for custom post type
    // ------------------------------

    function add_custom_meta_boxes()
    {
        add_meta_box(
            'custom_fields_meta_box',        // Unique ID
            'Additional Fields',             // Box title
            'custom_fields_meta_box_html',   // Callback function
            'custom_post',                   // Custom post type slug
            'normal',                        // Context (normal, side, etc.)
            'high'                           // Priority
        );
    }

    add_action('add_meta_boxes', 'add_custom_meta_boxes');

    function custom_fields_meta_box_html($post)
    {
        // Retrieve existing values
        $teaser = get_post_meta($post->ID, '_teaser', true);
        $video_url = get_post_meta($post->ID, '_video_url', true);
        $city = get_post_meta($post->ID, '_city', true);
        $selected_users = get_post_meta($post->ID, '_users', true) ?: [];

        // Get all WordPress users
        $users = get_users();

        // Output HTML
        ?>
        <p>
            <label for="city">Plaats</label><br>
            <input type="text" id="city" name="city" value="<?php echo esc_attr($city); ?>" size="50">
        </p>
        <p>
            <label for="teaser">Teaser</label><br>
            <textarea id="teaser" name="teaser" rows="10" cols="50"><?php echo esc_textarea($teaser); ?></textarea>
        </p>
        <p>
            <label for="video_url">Video Embed</label><br>
            <textarea id="teaser" name="video_url" rows="4" cols="50"><?php echo esc_textarea($video_url); ?></textarea>
        </p>


        <?php
    }


    function add_teammembers_meta_box_to_projecten()
    {
        add_meta_box(
            'projecten_teammembers',
            'Assign Team Members',
            'render_teammembers_meta_box_for_projecten',
            'custom_post',
            'normal',
            'default'
        );
    }

    add_action('add_meta_boxes', 'add_teammembers_meta_box_to_projecten');

// Render the meta box
    function render_teammembers_meta_box_for_projecten($post)
    {
        // Retrieve the current value
        $selected_teammembers = get_post_meta($post->ID, '_projecten_teammembers', true);
        $selected_teammembers = is_array($selected_teammembers) ? $selected_teammembers : [];

        // Fetch all team members
        $teammembers = get_posts([
            'post_type' => 'teammembers',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        // Security nonce
        wp_nonce_field('save_projecten_teammembers', 'projecten_teammembers_nonce');

        // Multi-select dropdown
        ?>
        <p>Kies team members voor dit project:</p>
        <select name="projecten_teammembers[]" id="projecten_teammembers" multiple="multiple"
                style="width: 100%; max-width: 400px;">
            <?php foreach ($teammembers as $teammember): ?>
                <option value="<?php echo esc_attr($teammember->ID); ?>" <?php selected(in_array($teammember->ID, $selected_teammembers)); ?>>
                    <?php echo esc_html($teammember->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="font-size: 12px; color: #555;">Houd de Ctrl-toets (Windows) of Command-toets (Mac) ingedrukt om
            meerdere opties te selecteren</p>
        <?php
    }

// Save the selected team members
    function save_projecten_teammembers($post_id)
    {
        // Verify nonce
        if (!isset($_POST['projecten_teammembers_nonce']) || !wp_verify_nonce($_POST['projecten_teammembers_nonce'], 'save_projecten_teammembers')) {
            return;
        }

        // Check for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the selected team members
        if (isset($_POST['projecten_teammembers'])) {
            $teammembers = array_map('intval', $_POST['projecten_teammembers']);
            update_post_meta($post_id, '_projecten_teammembers', $teammembers);
        } else {
            delete_post_meta($post_id, '_projecten_teammembers');
        }
    }

    add_action('save_post', 'save_projecten_teammembers');


    function save_custom_meta_box_data($post_id)
    {
        // Verify nonce (optional)
        if (!isset($_POST['teaser']) || !isset($_POST['video_url']) || !isset($_POST['city'])) {
            return;
        }

        // Save data
        update_post_meta($post_id, '_teaser', sanitize_textarea_field($_POST['teaser']));
        update_post_meta($post_id, '_teaser', sanitize_textarea_field($_POST['teaser']));
        update_post_meta($post_id, '_video_url', ($_POST['video_url']));
        update_post_meta($post_id, '_city', sanitize_text_field($_POST['city']));
//        update_post_meta($post_id, '_users', array_map('sanitize_text_field', $_POST['users']));
    }

    add_action('save_post', 'save_custom_meta_box_data');


    // ------------------------------
    // User fields
    // ------------------------------
    function register_teammembers_cpt()
    {
        $labels = array(
            'name' => 'Team Members',
            'singular_name' => 'Team Member',
            'add_new' => 'Team Member toevoegen',
            'add_new_item' => 'Team Member toevoegen',
            'edit_item' => 'Edit Team Member',
            'new_item' => 'New Team Member',
            'view_item' => 'View Team Member',
            'search_items' => 'Search Team Members',
            'not_found' => 'No team members found',
            'not_found_in_trash' => 'No team members found in Trash',
            'menu_name' => 'Team Members',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'teammembers'),
            'supports' => array('title', 'excerpt', 'thumbnail'),
            'show_in_rest' => true,
        );

        register_post_type('teammembers', $args);
    }

    add_action('init', 'register_teammembers_cpt');

    function add_teammembers_meta_boxes()
    {
        add_meta_box(
            'teammembers_details',
            'Team Member Details',
            'render_teammembers_meta_box',
            'teammembers',
            'normal',
            'high'
        );
    }

    add_action('add_meta_boxes', 'add_teammembers_meta_boxes');

    function render_teammembers_meta_box($post)
    {
        // Retrieve existing values
        $email = get_post_meta($post->ID, '_teammember_email', true);
        $linkedin = get_post_meta($post->ID, '_teammember_linkedin', true);
        $city = get_post_meta($post->ID, '_teammember_city', true);
        $bio = get_post_meta($post->ID, '_teammember_bio', true);


        // Security nonce
        wp_nonce_field('save_teammembers_meta_box', 'teammembers_meta_box_nonce');
        ?>
        <table>
            <tr>
                <td><label for="teammember_email">Contact:</label></td>
                <td><input type="text" name="teammember_email" id="teammember_email"
                           placeholder="Neem contact op met...."
                           value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <td><label for="teammember_linkedin">LinkedIn:</label></td>
                <td><input type="url" name="teammember_linkedin" id="teammember_linkedin"
                           value="<?php echo esc_url($linkedin); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <td><label for="teammember_city">Plaats:</label></td>
                <td><input type="text" name="teammember_city" id="teammember_city"
                           value="<?php echo esc_attr($city); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <td><label for="teammember_bio">Bio:</label></td>
                <td><textarea type="text" name="teammember_bio" rows="20" id="teammember_bio"
                              class="regular-text"><?php echo esc_attr($bio); ?></textarea></td>
            </tr>
        </table>

        <?php
    }

// Save Meta Box Data
    function save_teammembers_meta_box($post_id)
    {
        if (!isset($_POST['teammembers_meta_box_nonce']) || !wp_verify_nonce($_POST['teammembers_meta_box_nonce'], 'save_teammembers_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }


        update_post_meta($post_id, '_teammember_email', sanitize_text_field($_POST['teammember_email']));
        update_post_meta($post_id, '_teammember_linkedin', esc_url_raw($_POST['teammember_linkedin']));
        update_post_meta($post_id, '_teammember_city', sanitize_text_field($_POST['teammember_city']));
        update_post_meta($post_id, '_teammember_bio', sanitize_text_field($_POST['teammember_bio']));

    }

    add_action('save_post', 'save_teammembers_meta_box');

}

if (!function_exists('team')) {
    function team($atts, $content = null)
    {
        $posts = get_posts([
            'post_type' => 'teammembers',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'rand'
            // 'order'    => 'ASC'
        ]);

        $members = '';
        foreach ($posts as $post) {
            $members .= '<article
                        class="elementor-portfolio-item elementor-post post-805 post type-post status-publish format-standard has-post-thumbnail hentry category-team elementor-active"
                        style="margin-top: -0.03125px;border-radius: 10px;margin: 10px;">
                    <a class="elementor-post__thumbnail__link" href="' . get_permalink($post->ID) . '">
                        <div class="elementor-portfolio-item__img elementor-post__thumbnail">
                            <img loading="lazy" decoding="async" width="2560" height="2560"
                                 src="' . get_the_post_thumbnail_url($post->ID, 'thumbnail') . '"
                                 class="attachment-full size-full wp-image-1641" alt=""></div>
                        <div class="elementor-portfolio-item__overlay">
                            <h3 class="elementor-portfolio-item__title" style="font-size:20px;line-height: 1.1em;color:#ED562F">' . $post->post_title . '</h3>
                        </div>
                    </a>
                </article>';
        }

        return '<div class="e-con-inner" rel="ph">
            <div class="elementor-element elementor-element-78cca1e elementor-grid-5 elementor-grid-tablet-2 elementor-grid-mobile-1 elementor-widget elementor-widget-portfolio"
                 data-id="78cca1e" data-element_type="widget" 
                 data-settings="{&quot;columns&quot;:&quot;5&quot;,&quot;masonry&quot;:&quot;yes&quot;,&quot;row_gap&quot;:{&quot;unit&quot;:&quot;px&quot;,&quot;size&quot;:20,&quot;sizes&quot;:[]},&quot;columns_tablet&quot;:&quot;2&quot;,&quot;columns_mobile&quot;:&quot;1&quot;,&quot;item_gap&quot;:{&quot;unit&quot;:&quot;px&quot;,&quot;size&quot;:&quot;&quot;,&quot;sizes&quot;:[]}}"
                 data-widget_type="portfolio.default">
                <div class="elementor-widget-container">
                    <div class="elementor-portfolio elementor-grid elementor-posts-container elementor-posts-masonry">
                        ' . $members . '
                        <div class="elementor-portfolio-item elementor-portfolio-ghost-item"></div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

if (!function_exists('projects')) {
    function projects($atts, $content = null)
    {
        $posts = get_posts([
            'post_type' => 'custom_post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'rand'
            // 'order'    => 'ASC'
        ]);

        $projects = '';
        foreach ($posts as $post) {

            $id = $post->ID;
            $title = $post->post_title;
            $url = get_permalink($post->ID);
            $thumbnail = get_the_post_thumbnail_url($post->ID, 'large');
            $city = get_post_meta($post->ID, '_city', true);
            $teaser = get_the_excerpt($post->ID);




            $projects.= '
                <style>
                .elementor-grid-3 .elementor-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                </style>
                <script>
                setTimeout(function(){
                    var max=0;
                    jQuery(".elementor-post__text").each(function(){
                        if(jQuery(this).height() > max){
                            max = jQuery(this).height();
                        }
                    });
                    jQuery(".elementor-post__text").each(function(){
                        jQuery(this).height(max);
                    });
                },1000);
                </script>
				<article class="elementor-post elementor-grid-item post-892 post type-post status-publish format-standard has-post-thumbnail hentry category-expertise tag-riejanne">
                <div class="elementor-post__card" style="border-color: #273998;border-width: 3px;border-radius: 5px;border-style: solid;">
                    <a class="elementor-post__thumbnail__link" href="' . $url . '" tabindex="-1">
                    <div class="elementor-post__thumbnail elementor-fit-height">
                        <img loading="lazy" decoding="async" width="1798" height="1108" src="' . $thumbnail . '"></div></a>
                        <div class="elementor-post__text" style="padding: 20px;">
                            <h3 class="elementor-post__title" style="font-size:initial;overflow:hidden;text-overflow:ellipsis">
                            <a href="' . $url . '" style="color: #273998;font-size: 30px;font-weight: 400;text-transform: uppercase;font-style: normal;text-decoration: none;line-height: 56px;letter-spacing: 0.3px;word-spacing: 0px;">' . $city . '</a></h3>
                            <div class="elementor-post__excerpt">
                                <p>' . $teaser . '</p>
                            </div>
                            <div class="elementor-post__read-more-wrapper">
                                <a class="elementor-post__read-more" href="' . $url . '" style="font-size: 20px;" "tabindex="-1">Lees meer</a>
                            </div>
                        </div>
                    </div>
                </article>
				';
        }

        return '<style>.all_projects .elementor-post__thumbnail {display: block;overflow: hidden;aspect-ratio: 3 / 2;}</style>
                <div class="e-con-inner all_projects" rel="ph">
                <div class="elementor-element elementor-element-4a132f80 elementor-widget-mobile__width-inherit elementor-grid-3 elementor-grid-tablet-2 elementor-grid-mobile-1 elementor-posts--thumbnail-top elementor-card-shadow-yes elementor-posts__hover-gradient elementor-widget elementor-widget-posts" data-id="4a132f80" data-element_type="widget" data-settings="{&quot;cards_columns&quot;:&quot;3&quot;,&quot;cards_columns_tablet&quot;:&quot;2&quot;,&quot;cards_columns_mobile&quot;:&quot;1&quot;,&quot;cards_row_gap&quot;:{&quot;unit&quot;:&quot;px&quot;,&quot;size&quot;:35,&quot;sizes&quot;:[]},&quot;cards_row_gap_tablet&quot;:{&quot;unit&quot;:&quot;px&quot;,&quot;size&quot;:&quot;&quot;,&quot;sizes&quot;:[]},&quot;cards_row_gap_mobile&quot;:{&quot;unit&quot;:&quot;px&quot;,&quot;size&quot;:&quot;&quot;,&quot;sizes&quot;:[]}}" data-widget_type="posts.cards">
				<div class="elementor-widget-container">
                <div class="elementor-posts-container elementor-posts elementor-posts--skin-cards elementor-grid elementor-has-item-ratio" style="gap: 20px;">
                        ' . $projects . '
                </div>
                </div>
			    </div>
				</div>';
    }
}

add_action('init', 'custom_post_type');
add_shortcode("team", "team");
add_shortcode("projects", "projects");