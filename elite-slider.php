<?php
/*
Plugin Name: Elite Slider
Description: Create beautiful responsive image sliders with an easy dashboard and shortcode.
Version: 1.1
Author: Coder Sachin
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: elite-slider
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Elite_Slider_Plugin {

    public function __construct(){
        add_action('init', [ $this, 'register_slider_cpt' ]);
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'admin_assets' ]);
        add_action('wp_enqueue_scripts', [ $this, 'frontend_assets' ]);
        add_action('save_post_elite_slider', [ $this, 'save_slider_meta' ], 10, 3 );
        add_shortcode('elite_slider', [ $this, 'shortcode_render' ]);
        add_action('wp_ajax_elite_slider_action', [ $this, 'ajax_actions' ]);
        add_action('admin_post_elite_feedback', [ $this, 'handle_feedback' ]);
    }

    public function register_slider_cpt(){
        $labels = [
            'name' => 'Elite Sliders',
            'singular_name' => 'Elite Slider',
        ];
        $args = [
            'public' => false,
            'show_ui' => false, // we use our custom dashboard
            'supports' => ['title'],
            'has_archive' => false,
            'capability_type' => 'post'
        ];
        register_post_type('elite_slider', $args);
    }

    public function add_admin_menu(){
        add_menu_page(
            'Elite Slider',
            'Elite Slider',
            'manage_options',
            'elite-slider',
            [ $this, 'admin_dashboard' ],
            'dashicons-images-alt2',
            58 // near Media/Plugins (tweak if needed)
        );
        add_submenu_page('elite-slider', 'Add Slider', 'Add Slider', 'manage_options', 'elite-slider-add', [ $this, 'admin_add_edit' ]);
        add_submenu_page('elite-slider', 'Docs', 'Docs', 'manage_options', 'elite-slider-docs', [ $this, 'admin_docs' ]);
        add_submenu_page('elite-slider', 'Feedback', 'Feedback', 'manage_options', 'elite-slider-feedback', [ $this, 'admin_feedback' ]);
    }

    public function admin_assets($hook){
        // load only on our plugin pages
        if (strpos($hook, 'elite-slider') === false && $hook !== 'toplevel_page_elite-slider') return;
        wp_enqueue_style('elite-admin-css', plugin_dir_url(__FILE__).'assets/admin.css');
        wp_enqueue_media();
        wp_enqueue_script('elite-admin-js', plugin_dir_url(__FILE__).'assets/admin.js', ['jquery'], false, true);
        // localize
        wp_localize_script('elite-admin-js', 'EliteAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elite-slider-nonce'),
            'plugin_url' => plugin_dir_url(__FILE__),
        ]);
    }

    public function frontend_assets(){
        // Swiper CDN (easy). For production bundle local copies.
        wp_enqueue_style('elite-swiper-css', 'https://unpkg.com/swiper@9/swiper-bundle.min.css', [], null);
        wp_enqueue_script('elite-swiper-js', 'https://unpkg.com/swiper@9/swiper-bundle.min.js', [], null, true);
        wp_enqueue_style('elite-frontend-css', plugin_dir_url(__FILE__).'assets/frontend.css');
        wp_enqueue_script('elite-frontend-js', plugin_dir_url(__FILE__).'assets/frontend.js', ['elite-swiper-js','jquery'], null, true);
    }

    public function admin_dashboard(){
        // List existing sliders
        $items = get_posts(['post_type'=>'elite_slider','numberposts'=>-1]);
        ?>
        <div class="wrap elite-wrap">
            <h1>Elite Slider <a href="<?php echo admin_url('admin.php?page=elite-slider-add');?>" class="page-title-action">Add Slider</a></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Title</th><th>Shortcode</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if($items): foreach($items as $it): 
                    $disabled = get_post_meta($it->ID,'_elite_disabled',true) ? true : false;
                    ?>
                    <tr>
                        <td><?php echo esc_html($it->post_title); ?></td>
                        <td><code>[elite_slider id="<?php echo $it->ID;?>"]</code></td>
                        <td><?php echo $disabled ? '<span style="color:#a00">Disabled</span>' : '<span style="color:green">Active</span>'; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=elite-slider-add&edit='.$it->ID); ?>">Edit</a> |
                            <a href="#" class="elite-copy-shortcode" data-shortcode="[elite_slider id=&quot;<?php echo $it->ID;?>&quot;]">Copy</a> |
                            <a href="#" class="elite-toggle" data-id="<?php echo $it->ID;?>"><?php echo $disabled ? 'Enable' : 'Disable';?></a> |
                            <a href="#" class="elite-preview" data-id="<?php echo $it->ID;?>">Preview</a> |
                            <a href="#" class="elite-delete" data-id="<?php echo $it->ID;?>">Delete</a> |
                            <a href="#" class="elite-duplicate" data-id="<?php echo $it->ID;?>">Duplicate</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">No sliders yet. Click <a href="<?php echo admin_url('admin.php?page=elite-slider-add');?>">Add Slider</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div id="elite-preview-modal" style="display:none;"></div>
        </div>
        <?php
    }

    public function admin_add_edit(){
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $title = $edit_id ? get_the_title($edit_id) : '';
        $meta = $edit_id ? get_post_meta($edit_id, '_elite_meta', true) : [];
        $images = isset($meta['images']) ? $meta['images'] : [];
        $settings = isset($meta['settings']) ? $meta['settings'] : [
            'images_per_slide'=>1,
            'width'=>'100%',
            'height'=>'400px',
            'object_fit'=>'cover',
            'autoplay'=>1,
            'autoplay_speed'=>3,
            'arrows'=>1,
            'pagination'=>1,
        ];
        ?>
        <div class="wrap elite-wrap">
            <h1><?php echo $edit_id ? 'Edit Slider' : 'Add Slider'; ?></h1>
            <form method="post">
                <?php if($edit_id): wp_nonce_field('elite_edit_'.$edit_id,'elite_nonce'); endif; ?>
                <table class="form-table">
                   <tr>
  <th colspan="2">
    <div class="elite-card">
      <h4>Title</h4>
      <input type="text" name="elite_title" value="<?php echo esc_attr($title);?>" style="width:60%">
    </div>
  </th>
</tr>

                    
<tr>
  <th colspan="2">
    <div class="elite-card" data-min-images="2">
      <h4>Images</h4>
      <button class="button elite-media-add" type="button">Select / Add Images</button>
      <div id="elite-images-list">
        <?php foreach($images as $id): 
            $src = wp_get_attachment_image_url($id, 'medium'); 
        ?>
          <div class="elite-image-item" data-id="<?php echo $id;?>">
              <img src="<?php echo esc_url($src);?>" width="110" />
              <a class="elite-remove-image" href="#">Remove</a>
              <input type="hidden" name="elite_images[]" value="<?php echo $id;?>">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </th>
</tr>


                   <tr>
  <th colspan="2">
    <div class="elite-row-cards">
      <div class="elite-card">
        <h4>Images per Slide</h4>
        <input type="number" name="images_per_slide" value="<?php echo esc_attr($settings['images_per_slide']);?>" min="1" max="5">
      </div>
      <div class="elite-card">
        <h4>Slider Height</h4>
        <input type="text" name="height" value="<?php echo esc_attr($settings['height']);?>" placeholder="e.g. 400px or 50vh">
      </div>
      <div class="elite-card">
        <h4>Object-fit</h4>
        <select name="object_fit">
          <?php foreach(['cover','contain','fill','none'] as $of): ?>
            <option value="<?php echo $of;?>" <?php selected($settings['object_fit'],$of);?>><?php echo ucfirst($of);?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </th>
</tr>

                   <tr><th colspan="2">
    <div class="elite-cards-wrapper" style="display:flex;flex-wrap:wrap;gap:15px;">

        <div class="elite-card">
            <h4>Autoplay</h4>
            <label>
                <input type="checkbox" name="autoplay" value="1" <?php checked($settings['autoplay'],1); ?>>
                Enable Autoplay
            </label>
        </div>

        <div class="elite-card">
            <h4>Autoplay Speed</h4>
            <input type="number" name="autoplay_speed" value="<?php echo esc_attr($settings['autoplay_speed']); ?>" min="1"> seconds
        </div>

        <div class="elite-card">
            <h4>Navigation Arrows</h4>
            <label>
                <input type="checkbox" name="arrows" value="1" <?php checked($settings['arrows'],1); ?>>
                Enable Arrows
            </label>
        </div>

        <div class="elite-card">
            <h4>Navigation Dots</h4>
            <label>
                <input type="checkbox" name="pagination" value="1" <?php checked($settings['pagination'],1); ?>>
                Enable Dots
            </label>
        </div>

    </div>
</tr>

                </table>

                <p class="submit">
                    <input type="hidden" name="elite_edit_id" value="<?php echo $edit_id;?>">
                    <?php submit_button($edit_id ? 'Update & Publish' : 'Publish', 'primary', 'elite_publish'); ?>
                    
                </p>
            </form>

            <?php if($edit_id): ?>
                <h2>Shortcode</h2>
                <p><code>[elite_slider id="<?php echo $edit_id;?>"]</code> — copy & paste into page/post</p>
            <?php endif; ?>

        </div>

        <?php
    }

    public function admin_docs(){
        ?>
        <div class="wrap elite-wrap">
            <h1>Elite Slider — Docs</h1>
           
            <ol>
<div style="max-width:700px;margin:20px auto;font-family:Arial,sans-serif;line-height:1.6;color:#333;">
  <h2 style="text-align:center;color:#2c3e50;">🚀 How to Use Elite Slider Plugin</h2>
  
  <ol style="padding-left:20px;">
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">1. Install The Plugin</strong><br>
      <span style="color:#555;">Download the Elite Slider plugin and upload it via WordPress &rarr; Plugins &rarr; Add New.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">2. Activate the Plugin</strong><br>
      <span style="color:#555;">Go to the Plugins menu and click <em>Activate</em> next to Elite Slider.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">3. Open Elite Slider Tab</strong><br>
      <span style="color:#555;">On the left-hand menu, click <strong>Elite Slider</strong> to access the dashboard.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">4. Add a New Slider</strong><br>
      <span style="color:#555;">Click the <strong>Add Slider</strong> button to start creating your slider.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">5. Configure Slider</strong><br>
      <span style="color:#555;">Enter a title, upload images, choose autoplay settings, and adjust other options for your slider.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">6. Publish & Copy Shortcode</strong><br>
      <span style="color:#555;">Click <strong>Publish</strong> and copy the generated shortcode for your slider.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">7. Add Shortcode to Page</strong><br>
      <span style="color:#555;">Insert a Shortcode Widget or block on your page and paste the copied shortcode.</span>
    </li>
    
    <li style="margin-bottom:15px;">
      <strong style="color:#e67e22;">8. Publish & Preview</strong><br>
      <span style="color:#555;">Publish the page and preview your beautiful new slider in action! 🌟</span>
    </li>
  </ol>
</div>

            </ol>
            <h3>Pro tips</h3>
            <ul>
                <li>Use high-resolution images for fullscreen sliders.</li>
                <li>Enable autoplay for dynamic hero sliders.</li>
            </ul>
        </div>
        <?php
    }

    public function admin_feedback(){
        $demo_email = 'demo@example.com';
        ?>
        <div class="wrap elite-wrap">
            <h1>Send Feedback</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php');?>">
                <input type="hidden" name="action" value="elite_feedback">
                <?php wp_nonce_field('elite_feedback','elite_feedback_nonce'); ?>
                <table class="form-table">
                    <tr><th>Your name</th><td><input type="text" name="fb_name" required></td></tr>
                    <tr><th>Your message</th><td><textarea name="fb_message" rows="6" required></textarea></td></tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="Send Feedback"></p>
            </form>
           
        </div>
        <?php
    }

    public function handle_feedback(){
        if(!isset($_POST['elite_feedback_nonce']) || !wp_verify_nonce($_POST['elite_feedback_nonce'],'elite_feedback')) {
            wp_die('Invalid request');
        }
        $name = sanitize_text_field($_POST['fb_name']);
        $msg = sanitize_textarea_field($_POST['fb_message']);
        $to = 'sachinraj18sj@gmail.com'; // demo; replace later
        $subject = "Elite Slider feedback from ".$name;
        $body = "Message:\n\n".$msg;
        wp_mail($to, $subject, $body);
        wp_redirect(admin_url('admin.php?page=elite-slider-feedback&sent=1'));
        exit;
    }

    public function save_slider_meta($post_id, $post, $update){
        // not used since admin Add/Update uses regular form submit; implement below
    }

    public function ajax_actions(){
        check_ajax_referer('elite-slider-nonce','nonce');
        $act = isset($_POST['act']) ? sanitize_text_field($_POST['act']) : '';
        if($act === 'save_slider'){
            // create or update CPT
            $title = sanitize_text_field($_POST['title'] ?? 'Untitled');
            $images = isset($_POST['images']) ? array_map('intval', $_POST['images']) : [];
            $settings = [
                'images_per_slide' => intval($_POST['images_per_slide'] ?? 1),
                'height' => sanitize_text_field($_POST['height'] ?? '400px'),
                'object_fit' => sanitize_text_field($_POST['object_fit'] ?? 'cover'),
                'autoplay' => intval($_POST['autoplay'] ?? 1),
                'autoplay_speed' => intval($_POST['autoplay_speed'] ?? 3),
                'arrows' => intval($_POST['arrows'] ?? 1),
                'pagination' => intval($_POST['pagination'] ?? 1),
            ];
            $edit = intval($_POST['edit_id'] ?? 0);
            if($edit){
                $postarr = ['ID'=>$edit,'post_title'=>$title];
                wp_update_post($postarr);
                update_post_meta($edit, '_elite_meta', ['images'=>$images,'settings'=>$settings]);
                wp_send_json_success(['id'=>$edit,'shortcode'=>"[elite_slider id=\"$edit\"]"]);
            } else {
                $pid = wp_insert_post(['post_title'=>$title,'post_type'=>'elite_slider','post_status'=>'publish']);
                update_post_meta($pid, '_elite_meta', ['images'=>$images,'settings'=>$settings]);
                wp_send_json_success(['id'=>$pid,'shortcode'=>"[elite_slider id=\"$pid\"]"]);
            }
        }

        if($act === 'toggle_disable'){
            $id = intval($_POST['id']);
            $cur = get_post_meta($id,'_elite_disabled',true);
            if($cur) delete_post_meta($id,'_elite_disabled'); else update_post_meta($id,'_elite_disabled',1);
            wp_send_json_success();
        }

        if($act === 'delete_slider'){
            $id = intval($_POST['id']);
            wp_delete_post($id,true);
            wp_send_json_success();
        }

        if($act === 'duplicate'){
            $id = intval($_POST['id']);
            $post = get_post($id);
            if(!$post) wp_send_json_error('Missing');
            $meta = get_post_meta($id,'_elite_meta',true);
            $new = wp_insert_post(['post_title'=>$post->post_title.' (copy)','post_type'=>'elite_slider','post_status'=>'publish']);
            update_post_meta($new, '_elite_meta', $meta);
            wp_send_json_success(['id'=>$new]);
        }

        if($act === 'preview'){
            $id = intval($_POST['id']);
            $html = $this->shortcode_render(['id'=>$id], '', null);
            wp_send_json_success(['html'=> $html ]);
        }

        wp_send_json_error('unknown action');
    }

    public function shortcode_render($atts){
        $atts = shortcode_atts(['id'=>0], $atts);
        $id = intval($atts['id']);
        if(!$id) return '';
        $meta = get_post_meta($id, '_elite_meta', true);
        if(empty($meta) || empty($meta['images'])) return '<!-- Elite Slider: no images -->';
        $settings = $meta['settings'];
        $images = $meta['images'];

        // generate markup expected by frontend JS (Swiper)
        $uid = 'elite-slider-'. $id .'-'. wp_rand(1000,9999);
        ob_start();
        ?>
        <div class="elite-slider-wrap" id="<?php echo esc_attr($uid);?>" data-settings="<?php echo esc_attr(json_encode($settings)); ?>">
            <div class="swiper <?php echo esc_attr($uid);?>">
                <div class="swiper-wrapper">
                    <?php foreach($images as $img_id): 
                        $src = wp_get_attachment_image_url($img_id,'large');
                        ?>
                        <div class="swiper-slide"><img src="<?php echo esc_url($src);?>" style="object-fit:<?php echo esc_attr($settings['object_fit']);?>;height:<?php echo esc_attr($settings['height']);?>;width:100%;"></div>
                    <?php endforeach; ?>
                </div>

                <?php if($settings['pagination']): ?>
                <div class="swiper-pagination"></div>
                <?php endif; ?>

                <?php if($settings['arrows']): ?>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}

new Elite_Slider_Plugin();

/* Create assets directory if not present and include minimal CSS/JS files
   Create folder plugin-dir/assets and add:
   - admin.css
   - admin.js
   - frontend.css
   - frontend.js
   (I will show minimal contents below — paste into those files)
*/
