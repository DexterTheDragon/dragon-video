<?php
/*
Plugin Name: Dragon Video
Plugin URI: http://github.com/DexterTheDragon/dragon-video
Description: Provides ability to transcode/resize (using FFMPEG), and play videos with native support, using the HTML5 video tag and canvas element, in Firefox (Ogg Vorbis/Theora) and Safari (H264/AAC), and plugin support for Shockwave Flash (FLV), with the same (Firefox-like) controls. It intergrates with Wordpress Media Library so that when you upload a video, it automatically gets resized and transcoded to the configured formats. You can then insert the video into a post by browsing the library and clicking the "Insert into Post" button, which inserts a shortcode which will then be converted to the html needed to play the video. Note: This plugin requires that your server have ffmpeg, ffmpeg2theora, and the nessasary software packages to encode to the codecs ogg, mp3, aac, x264, and flv.
Author: Kevin Carter
Version: 0.1
Author URI: http://dexterthedragon.com/
*/
# TODO: Video should save into uploads folder CHECK
# TODO: Link on page to re-convert video
# TODO: Custom gallery mixing video and images CHECK
# TODO: Settings page CHECK
# TODO: Fix zencoder activation settings

class DragonVideo {


    /**
     * Error messages to diplay
     *
     * @var array
     */
    protected $_messages = array();

    /**
     * Array with default options
     *
     * @var array
     */
    protected $options = array(
        'formats' => array(
            'webm' => true,
            'ogg' => true,
            'mp4' => true,
        ),
        'sizes'   => array(
            'large'  => array('', ''),
            'medium' => array(720, 480),
            'small'  => array(480, 320),
        ),
        'ffmpeg_path' => '/usr/bin/ffmpeg',
        'override_gallery' => true,
    );

    function DragonVideo() {
        register_activation_hook(__FILE__, array(&$this, 'activate'));
        $this->options = get_option('dragon-video', $this->options);

        add_filter('attachment_fields_to_edit', array(&$this, 'show_video_fields_to_edit'), 11, 2);
        add_filter('media_send_to_editor', array(&$this,'video_send_to_editor_shortcode'), 10, 3 );
        add_shortcode('dragonvideo', array(&$this, 'tag_replace'));
        add_filter('wp_generate_attachment_metadata', array(&$this, 'video_metadata'), 10, 2);
        add_action('delete_attachment', array(&$this, 'delete_attachment'));

        add_action('admin_menu', array(&$this, 'admin_menu'), 1); //high priority so addons can attach to menu

        if ( $this->options['override_gallery'] ) {
            add_filter('post_gallery', array(&$this, 'video_gallery'), 10, 2);
        }
        add_filter('wp_get_attachment_link', array(&$this, 'wp_get_attachment_link'), 10, 6);
    }

    /**
     * Plugin installation method
     */
    public function activate() {
        add_option('dragon-video', $this->options, null, 'no');
    }

    public function admin_menu() {
        add_menu_page('Dragon Video', 'DragonVideo', 'manage_options', 'dragonvideo', array(&$this, 'options_page'), null, null );
        add_submenu_page('dragonvideo', 'Dragon Video', 'Dragon Video', 'manage_options', 'dragonvideo', array(&$this, 'options_page'));
    }

    function video_metadata($metadata, $attachment_id) {
        if ( !$this->is_video($attachment_id) ) {
            return $metadata;
        }
        $file = get_attached_file($attachment_id);
        if ( !array_key_exists('height', $metadata) ) {
            $video_info = $this->get_video_info($file);
            $metadata['height'] = $video_info['height'];
            $metadata['width'] = $video_info['width'];
            $metadata['duration'] = $video_info['duration'];
        }

        $sizes = array();
        foreach ( $this->get_video_sizes() as $s ) {
            $sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => FALSE );
            $sizes[$s]['width'] = $this->options['sizes'][$s][0];
        }

        $size = 'original';
        $resized = $this->make_encodings($size, $attachment_id, $file, (int)$metadata['width'], (int)$metadata['height'], false);
        if ( $resized )
            $metadata['sizes'][$size] = $resized;


        $orig_w = $metadata['width'];
        $orig_h = $metadata['height'];
        foreach ($sizes as $size => $size_data ) {
            $max_w = $size_data['width'];
            $max_h = $size_data['height'];
            $crop  = $size_data['crop'];
            $dims = image_resize_dimensions($orig_w, $orig_h, $max_w, $max_h, $crop);
            if ( !$dims )
                continue;
            list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;

            $resized = $this->make_encodings($size, $attachment_id, $file, $dst_w, $dst_h, $crop);
            if ( $resized )
                $metadata['sizes'][$size] = $resized;
        }

        do_action('dragon_video_encode', $file, $attachment_id, $metadata['sizes']);
        return $metadata;
    }

    function make_encodings($size, $attachment_id, $file, $width, $height, $crop) {
        $info = pathinfo($file);
        $dir = $info['dirname'];
        $ext = $info['extension'];
        $name = basename($file, ".{$ext}");
        $suffix = "{$width}x{$height}";
        $files = array();
        foreach ( $this->options['formats'] as $format => $active ) {
            if ( $active ) {
                $files[$format] = "{$name}-{$suffix}.{$format}";
            }
        }
        $meta = array(
            'width' => $width,
            'height' => $height,
            'file' => $files,
            'poster' => '',
        );

        return $meta;
    }

    function delete_attachment($postid) {
        if ( !$this->is_video($attachment_id) ) {
            return;
        }
        $src = get_attached_file($postid);
        $metadata = wp_get_attachment_metadata($postid);
        foreach ( $metadata['sizes'] as $size ) {
            $dir = dirname($src);
            if ( !empty($size['poster']) ) {
                @unlink($dir.'/'.$size['poster']);
            }
            foreach ( $size['file'] as $file ) {
                @unlink($dir.'/'.$file);
            }
        }
    }

    function is_video($post_id) {
        $file = get_attached_file($post_id);

        $ext = preg_match('/\.([^.]+)$/', $file, $matches) ? strtolower($matches[1]) : false;

        $video_exts = array('ogg', 'mp4', 'flv', 'avi', 'wmv', 'm4v', 'mov', 'ogv');

        if ( in_array($ext, $video_exts)  ||
            0 === strpos(get_post_mime_type($post_id), 'video/') ||
            0 === strpos(get_post_mime_type($post_id), 'application/octet-stream'))
            return true;
    }

    function video_embed($post, $size = 'medium') {
        $meta = $this->get_video_for_size($post->ID, $size);
        $file_url = wp_get_attachment_url($post->ID);
        $url = str_replace(basename($file_url), '', $file_url);

        $filename = $meta['file'];

        $poster = $url . $meta['poster'];
        $height = $meta['height'];
        $width  = $meta['width'];

        // MP4 Source Supplied
        if ($meta['file']['mp4']) {
            $mp4 = $url . $meta['file']['mp4'];
            $mp4_source = '<source src="'.$mp4.'" type="video/mp4">';
        }

        // WebM Source Supplied
        if ($meta['file']['webm']) {
            $webm = $url . $meta['file']['webm'];
            $webm_source = '<source src="'.$webm.'" type="video/webm">';
        }

        // Ogg source supplied
        if ($meta['file']['ogg']) {
            $ogg = $url . $meta['file']['ogg'];
            $ogg_source = '<source src="'.$ogg.'" type="video/ogg">';
        }

        $html = <<< HTML
<!-- Begin Video -->
<!-- Using the Video for Everybody Embed Code http://camendesign.com/code/video_for_everybody -->
<video width="$width" height="$height" controls preload poster="$poster">
    $mp4_source
    $webm_source
    $ogg_source
    <!-- Flash Fallback. -->
    <object width="$width" height="$height" type="application/x-shockwave-flash" data="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf">
        <param name="movie" value="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf" />
        <param name="allowfullscreen" value="true" />
        <param name="flashvars" value='config={"playlist":["$poster", {"url": "$mp4","autoPlay":false,"autoBuffering":true}]}' />
        <!-- Image Fallback. Typically the same as the poster image. -->
        <img src="$poster" width="$width" height="$height" alt="Poster Image" title="No video playback capabilities." />
    </object>
</video>
<!-- End Video -->
HTML;
        $video = compact('width', 'height', 'poster', 'mp4', 'ogg', 'webm');
        return apply_filters('dragon_video_player', $html, $video);
    }

    function tag_replace($attr) {
        if ( !$post = get_post($attr[0]) )
            return "Video $attr[0] Not Found";
        $post = get_post($attr[0]);
        return $this->video_embed($post);
    }

    function video_send_to_editor_shortcode($html, $post_id, $attachment) {
        if ( !$this->is_video($post_id) ) {
            return $html;
        }
        return "[dragonvideo $post_id]";
    }

    function get_video_info($src) {
        $cmd = $this->options['ffmpeg_path'] . ' -i ' . $src  . ' 2>&1';
        $lines = array();
        exec($cmd, $lines);
        $width = $height = 0;
        foreach ($lines as $line) {
            if (preg_match('/Stream.*Video:.* (\d+)x(\d+).*/', $line, $matches)) {
                $width      = $matches[1];
                $height     = $matches[2];
            }
            if (preg_match('/Duration:\s*([\d:.]+),/', $line, $matches))
                $duration = $matches[1];
        }
        $n = preg_match('/(\d+):(\d+):(\d+)./', $duration, $match);
        $total_seconds = 3600 * $match[1] + 60 * $match[2] + $match[3];
        return array('width' => $width, 'height' => $height, 'duration' => $total_seconds);
    }

    function show_video_fields_to_edit($fields, $post) {
        if ( !$this->is_video( $post ) ) {
            return $fields;
        }
        unset($fields['url']);
        $video_html = $this->video_embed($post);
        $video_html = '<p>'.__('Shortcode for embedding: ' ).$this->video_send_to_editor_shortcode( '', $post->ID, '' ).'</p>'.$video_html;

        $fields['video-preview'] = array(
            'label' => __( 'Preview and Insert' ),
            'input' => 'html',
            'html'  => $video_html,
        );

        return $fields;
    }

    function get_video_sizes() {
        return array_keys($this->options['sizes']);
    }

    function get_video_for_size($post_id, $size='medium') {
        if ( !is_array( $imagedata = wp_get_attachment_metadata( $post_id ) ) )
            return false;

        if ( in_array($size, array_keys($imagedata['sizes'])) )
            return $imagedata['sizes'][$size];

        // if there's only one size no point in going further
        if ( count(array_keys($imagedata['sizes'])) == 1 ) {
            $key = array_keys($imagedata['sizes']);
            return $imagedata['sizes'][$key[0]];
        }

        $wanted_size = array($this->options['sizes'][$size][0], $this->options['sizes'][$size][1]);
        // get the best one for a specified set of dimensions
        foreach ( $imagedata['sizes'] as $_size => $data ) {
            if ( ( $data['width'] == $wanted_size[0] ) || ( $data['height'] == $wanted_size[1] ) ) {
                return $data;
            }
        }

        // if we get here just use one
        $preferred = array('medium', 'original');
        foreach ( $preferred as $try ) {
            if ( isset($imagedata['sizes'][$try]) ) {
                return $imagedata['sizes'][$try];
            }
        }
    }

    public function video_gallery($html, $attr) {
        global $post, $wp_locale;
        // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
        if ( isset( $attr['orderby'] ) ) {
            $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
            if ( !$attr['orderby'] )
                unset( $attr['orderby'] );
        }

        extract(shortcode_atts(array(
            'order'      => 'ASC',
            'orderby'    => 'menu_order ID',
            'id'         => $post->ID,
            'itemtag'    => 'dl',
            'icontag'    => 'dt',
            'captiontag' => 'dd',
            'columns'    => 3,
            'size'       => 'thumbnail',
            'include'    => '',
            'exclude'    => ''
        ), $attr));

        $id = intval($id);
        if ( 'RAND' == $order )
            $orderby = 'none';

        if ( !empty($include) ) {
            $include = preg_replace( '/[^0-9,]+/', '', $include );
            $_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

            $attachments = array();
            foreach ( $_attachments as $key => $val ) {
                $attachments[$val->ID] = $_attachments[$key];
            }
        } elseif ( !empty($exclude) ) {
            $exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
            $attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
        } else {
            $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video, image', 'order' => $order, 'orderby' => $orderby) );
        }

        if ( empty($attachments) )
            return '';

        if ( is_feed() ) {
            $output = "\n";
            foreach ( $attachments as $att_id => $attachment )
                $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
            return $output;
        }

        $itemtag = tag_escape($itemtag);
        $captiontag = tag_escape($captiontag);
        $columns = intval($columns);
        $itemwidth = $columns > 0 ? floor(100/$columns) : 100;
        $float = is_rtl() ? 'right' : 'left';

        $selector = "gallery-{$instance}";

        $output = apply_filters('gallery_style', "
            <style type='text/css'>
                #{$selector} {
                    margin: auto;
                }
                #{$selector} .gallery-item {
                    float: {$float};
                    margin-top: 10px;
                    text-align: center;
                    width: {$itemwidth}%;			}
                #{$selector} img {
                    border: 2px solid #cfcfcf;
                }
                #{$selector} .gallery-caption {
                    margin-left: 0;
                }
            </style>
            <!-- see gallery_shortcode() in wp-includes/media.php -->
            <div id='$selector' class='gallery galleryid-{$id}'>");

        $i = 0;
        foreach ( $attachments as $id => $attachment ) {
            $link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

            $output .= "<{$itemtag} class='gallery-item'>";
            $output .= "
                <{$icontag} class='gallery-icon'>
                    $link
                </{$icontag}>";
            if ( $captiontag && trim($attachment->post_excerpt) ) {
                $output .= "
                    <{$captiontag} class='gallery-caption'>
                    " . wptexturize($attachment->post_excerpt) . "
                    </{$captiontag}>";
            }
            $output .= "</{$itemtag}>";
            if ( $columns > 0 && ++$i % $columns == 0 )
                $output .= '<br style="clear: both" />';
        }

        $output .= "
                <br style='clear: both;' />
            </div>\n";

        return $output;
    }

    function wp_get_attachment_link($string, $id, $size, $permalink, $icon, $text) {
        if ( !$this->is_video($id) ) {
            return $string;
        }

        $_post = & get_post( $id );

        $post_title = esc_attr($_post->post_title);

        $link_text = '';
        if ( $text ) {
            $link_text = esc_attr($text);
        } elseif ( ( is_int($size) && $size != 0 ) or ( is_string($size) && $size != 'none' ) or $size != false ) {
            $src = get_attached_file($_post->ID);
            $srcp = pathinfo($src);
            $url = str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $srcp['dirname']);
            $meta = $this->get_video_for_size($_post->ID);
            $poster = $url .'/'. $meta['poster'];
            $link_text = "<img src='$poster' width='150' height='150' />";
        }

        $url = wp_get_attachment_url($_post->ID);

        if ( $permalink )
            $url = get_attachment_link($_post->ID);

        if( trim($link_text) == '' )
            $link_text = $_post->post_title;

        return "<a href='$url' title='$post_title' class='video_overlay'>$link_text</a>";
    }

    /**
     * Display options page
     */
    public function options_page() {
        // if user clicked "Save Changes" save them
        if ( isset($_POST['Submit']) ) {
            foreach ( $this->options as $option => $value ) {
                if ( array_key_exists($option, $_POST) ) {
                    $this->options[$option] = $_POST[$option];
                }
            }
            update_option('dragon-video', $this->options);

            $this->_messages['updated'][] = 'Options updated!';
        }

        foreach ( $this->_messages as $namespace => $messages ) {
            foreach ( $messages as $message ) { ?>
                <div class="<?php echo $namespace; ?>">
                    <p>
                        <strong><?php echo $message; ?></strong>
                    </p>
                </div>
                <?php
            }
        } ?>
<div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>Dragon Video Settings</h2>
    <form method="post" action="">
        <div id="watermark_text" class="watermark_type">
            <table class="form-table" style="clear:none; width:auto;">
                <tr>
                    <th>FFmpeg path</th>
                    <td>
                    <input type="text" size="40" name="ffmpeg_path" value="<?php echo $this->options['ffmpeg_path']; ?>" /><br />
                    <span class="description">FFmpeg needed for video info discovery.</span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Formats:</th>
                    <td>
                        <?php foreach ( $this->options['formats'] as $format => $checked ) { ?>
                            <label>
                                <input name="formats[<?php echo $format; ?>]" type="hidden" id="formats_<?php echo $format; ?>_0" value="0" />
                                <input name="formats[<?php echo $format; ?>]" type="checkbox" id="formats_<?php echo $format; ?>" value="1"<?php echo $checked ? ' checked="checked"' : null; ?> />
                                <?php echo $format; ?>
                            </label>
                            <br />
                        <?php } ?>
                        <span class="description">Which formats to encode to.</span>
                    </td>
                </tr>
                <tr>
                    <th>Override WordPress Gallery</th>
                    <td>
                    <input name="override_gallery" type="checkbox" id="override_gallery" value="1"<?php echo $this->options['override_gallery'] ? ' checked="checked"' : null; ?> />
                    <span class="description">Override the WordPress gallery to include videos and images.</span>
                    </td>
                </tr>
            </table>
            <h3>Video Sizes</h3>
            <p>Maximum sizes to encode videos to. Leave blank to skip size.</p>
            <table class="form-table" style="clear:none; width:auto;">
                <?php foreach ( $this->options['sizes'] as $name => $size ) { ?>
                    <tr valign="top">
                        <th scope="row"><?php echo ucfirst($name); ?>:</th>
                        <td>
                            <label>
                                Max Width <input name="sizes[<?php echo $name; ?>][0]" type="text" id="size_<?php echo $name; ?>" value="<?php echo $size[0]; ?>" class="small-text" />
                            </label>
                            <label>
                                Max Height <input name="sizes[<?php echo $name; ?>][1]" type="text" id="size_<?php echo $name; ?>" value="<?php echo $size[1]; ?>" class="small-text" />
                            </label>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
        </p>

    </form>
</div>
<?php
    }

    static function encode_formats() {
        global $dragonvideo;
        $options = $dragonvideo->options;
        return array_keys(array_filter($options['formats'], create_function('$o', 'return $o;')));
    }
}

$dragonvideo = new DragonVideo();

?>
