<?php
/**
 * SNN Social Sharing Buttons Element for Bricks Builder
 * 
 * Replaces Bricks native Social Sharing widget with improved URLs
 * that work correctly on mobile and desktop for all platforms.
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use Bricks\Element;

class SNN_Social_Sharing_Buttons extends Element {
    public $category     = 'snn';
    public $name         = 'snn-social-sharing';
    public $icon         = 'ti-share';
    public $css_selector = '.snn-social-sharing-wrapper';
    public $scripts      = [];
    public $nestable     = false;

    public function get_label() {
        return esc_html__('SNN Social Sharing', 'snn');
    }

    public function set_controls() {
        // Platforms to show
        $this->controls['platforms'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Social Platforms', 'snn'),
            'type'        => 'select',
            'multiple'    => true,
            'options'     => [
                'facebook'  => 'Facebook',
                'twitter'   => 'Twitter/X',
                'whatsapp'  => 'WhatsApp',
                'telegram'  => 'Telegram',
                'email'     => 'Email',
            ],
            'default'     => ['facebook', 'twitter', 'whatsapp', 'telegram', 'email'],
            'description' => esc_html__('Select which social platforms to show', 'snn'),
        ];

        // Layout direction
        $this->controls['direction'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Direction', 'snn'),
            'type'        => 'select',
            'options'     => [
                'horizontal' => 'Horizontal',
                'vertical'   => 'Vertical',
            ],
            'default'     => 'horizontal',
            'description' => esc_html__('Layout direction of sharing buttons', 'snn'),
        ];

        // Use brand colors
        $this->controls['use_brand_colors'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Use Brand Colors', 'snn'),
            'type'        => 'checkbox',
            'default'     => false,
            'description' => esc_html__('Use official brand colors for each platform', 'snn'),
        ];

        // Open in new tab
        $this->controls['open_new_tab'] = [
            'tab'         => 'content',
            'label'       => esc_html__('Open in New Tab', 'snn'),
            'type'        => 'checkbox',
            'default'     => true,
            'description' => esc_html__('Open sharing links in a new tab/window', 'snn'),
        ];

        // Icon size
        $this->controls['icon_size'] = [
            'tab'         => 'style',
            'label'       => esc_html__('Icon Size', 'snn'),
            'type'        => 'number',
            'default'     => 24,
            'unit'        => 'px',
            'description' => esc_html__('Size of social media icons', 'snn'),
        ];

        // Gap between buttons
        $this->controls['gap'] = [
            'tab'         => 'style',
            'label'       => esc_html__('Gap', 'snn'),
            'type'        => 'number',
            'default'     => 12,
            'unit'        => 'px',
            'description' => esc_html__('Space between buttons', 'snn'),
        ];
    }

    public function render() {
        $platforms = $this->settings['platforms'] ?? ['facebook', 'twitter', 'whatsapp', 'telegram', 'email'];
        $direction = $this->settings['direction'] ?? 'horizontal';
        $use_brand_colors = !empty($this->settings['use_brand_colors']);
        $open_new_tab = !empty($this->settings['open_new_tab'] ?? true);
        $icon_size = isset($this->settings['icon_size']) ? intval($this->settings['icon_size']) : 24;
        $gap = isset($this->settings['gap']) ? intval($this->settings['gap']) : 12;

        // Get current post/page data
        $post_id = get_the_ID();
        $post_title = get_the_title($post_id);
        $post_url = get_permalink($post_id);
        $post_excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(get_the_content(), 20);

        // Get featured image URL (convert WebP to JPG for WhatsApp compatibility)
        $image_url = '';
        if (has_post_thumbnail($post_id)) {
            $image_id = get_post_thumbnail_id($post_id);
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $image_url = set_url_scheme($image_url, 'https');
                // Convert WebP to JPG for WhatsApp
                if (strpos($image_url, '.webp') !== false) {
                    $original_file = get_attached_file($image_id);
                    if ($original_file && file_exists($original_file)) {
                        $upload_dir = wp_upload_dir();
                        $relative_path = str_replace($upload_dir['basedir'], '', $original_file);
                        $image_url = $upload_dir['baseurl'] . $relative_path;
                        $image_url = set_url_scheme($image_url, 'https');
                    } else {
                        $image_url = str_replace('.webp', '.jpg', $image_url);
                    }
                }
            }
        }

        // If no featured image, use default
        if (empty($image_url)) {
            $image_url = 'https://lanacionweb.com/fotoedicion/2025/08/favorit.jpg';
        }

        // Prepare sharing URLs
        $share_urls = $this->get_share_urls($post_title, $post_url, $post_excerpt, $image_url);

        // Build wrapper classes
        $wrapper_classes = ['snn-social-sharing-wrapper'];
        $wrapper_classes[] = 'snn-social-sharing-' . esc_attr($direction);
        if ($use_brand_colors) {
            $wrapper_classes[] = 'snn-social-sharing-brand-colors';
        }

        $this->set_attribute('_root', 'class', $wrapper_classes);

        // Build target attribute
        $target_attr = $open_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

        echo '<div ' . $this->render_attributes('_root') . '>';
        
        foreach ($platforms as $platform) {
            if (!isset($share_urls[$platform])) {
                continue;
            }

            $url = $share_urls[$platform]['url'];
            $icon = $share_urls[$platform]['icon'];
            $label = $share_urls[$platform]['label'];
            $brand_color = $share_urls[$platform]['color'] ?? '';

            $link_classes = ['snn-social-sharing-link', 'snn-social-sharing-' . esc_attr($platform)];
            if ($use_brand_colors && $brand_color) {
                $link_classes[] = 'snn-social-sharing-has-color';
            }

            $style_attr = '';
            if ($use_brand_colors && $brand_color) {
                $style_attr = ' style="color: ' . esc_attr($brand_color) . ';"';
            }

            echo '<a href="' . esc_url($url) . '" class="' . esc_attr(implode(' ', $link_classes)) . '"' . $target_attr . ' aria-label="' . esc_attr($label) . '"' . $style_attr . '>';
            
            // Render icon
            if (is_array($icon) && isset($icon['library']) && isset($icon['icon'])) {
                if (function_exists('\Bricks\Helpers::render_control_icon')) {
                    \Bricks\Helpers::render_control_icon($icon, []);
                } else {
                    // Fallback: build icon class manually
                    $icon_class = '';
                    if ($icon['library'] === 'fontawesomeBrands') {
                        $icon_class = 'fab ' . $icon['icon'];
                    } elseif ($icon['library'] === 'fontawesome') {
                        $icon_class = 'fas ' . $icon['icon'];
                    } else {
                        $icon_class = $icon['icon'];
                    }
                    echo '<i class="' . esc_attr($icon_class) . '"></i>';
                }
            } else {
                // Fallback if icon is not in expected format
                echo '<i class="fas fa-share-alt"></i>';
            }
            
            echo '</a>';
        }
        
        echo '</div>';
        ?>

        <style>
        .snn-social-sharing-wrapper {
            display: flex;
            gap: <?php echo esc_attr($gap); ?>px;
            align-items: center;
        }
        .snn-social-sharing-vertical {
            flex-direction: column;
        }
        .snn-social-sharing-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: <?php echo esc_attr($icon_size + 8); ?>px;
            height: <?php echo esc_attr($icon_size + 8); ?>px;
            text-decoration: none;
            transition: transform 0.2s, opacity 0.2s;
        }
        .snn-social-sharing-link:hover {
            transform: scale(1.1);
            opacity: 0.8;
        }
        .snn-social-sharing-link i,
        .snn-social-sharing-link svg {
            width: <?php echo esc_attr($icon_size); ?>px;
            height: <?php echo esc_attr($icon_size); ?>px;
        }
        .snn-social-sharing-brand-colors .snn-social-sharing-facebook {
            color: #1877F2 !important;
        }
        .snn-social-sharing-brand-colors .snn-social-sharing-twitter {
            color: #000000 !important;
        }
        .snn-social-sharing-brand-colors .snn-social-sharing-whatsapp {
            color: #25D366 !important;
        }
        .snn-social-sharing-brand-colors .snn-social-sharing-telegram {
            color: #0088cc !important;
        }
        .snn-social-sharing-brand-colors .snn-social-sharing-email {
            color: #EA4335 !important;
        }
        </style>
        <?php
    }

    /**
     * Get sharing URLs for all platforms
     * Uses correct URLs that work on mobile and desktop
     */
    private function get_share_urls($title, $url, $excerpt, $image_url) {
        // URL encode all values
        $encoded_title = urlencode($title);
        $encoded_url = urlencode($url);
        $encoded_text = urlencode($title . ' - ' . $excerpt);

        $urls = [];

        // Facebook - Use web sharer (works on mobile and desktop)
        $urls['facebook'] = [
            'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
            'icon'  => ['library' => 'fontawesomeBrands', 'icon' => 'fab fa-facebook-f'],
            'label' => 'Compartir en Facebook',
            'color' => '#1877F2',
        ];

        // Twitter/X - Use web intent (works on mobile and desktop, opens app if available)
        $urls['twitter'] = [
            'url'   => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
            'icon'  => ['library' => 'fontawesomeBrands', 'icon' => 'fab fa-x-twitter'],
            'label' => 'Compartir en Twitter/X',
            'color' => '#000000',
        ];

        // WhatsApp - Use web API (works on mobile and desktop)
        // On mobile, it will open the app if installed
        $whatsapp_text = $encoded_title . '%20' . $encoded_url;
        $urls['whatsapp'] = [
            'url'   => 'https://api.whatsapp.com/send?text=' . $whatsapp_text,
            'icon'  => ['library' => 'fontawesomeBrands', 'icon' => 'fab fa-whatsapp'],
            'label' => 'Compartir en WhatsApp',
            'color' => '#25D366',
        ];

        // Telegram - Use web share (works on mobile and desktop)
        $urls['telegram'] = [
            'url'   => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
            'icon'  => ['library' => 'fontawesomeBrands', 'icon' => 'fab fa-telegram'],
            'label' => 'Compartir en Telegram',
            'color' => '#0088cc',
        ];

        // Email - Use mailto (works everywhere)
        $email_subject = urlencode($title);
        $email_body = urlencode($excerpt . "\n\n" . $url);
        $urls['email'] = [
            'url'   => 'mailto:?subject=' . $email_subject . '&body=' . $email_body,
            'icon'  => ['library' => 'fontawesome', 'icon' => 'fas fa-envelope'],
            'label' => 'Compartir por Email',
            'color' => '#EA4335',
        ];

        return $urls;
    }
}

