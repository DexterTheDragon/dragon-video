<?php
?>
<?php
class ZencoderEncoder {

    function ZencoderEncoder() {
        $this->API_KEY = '8165fce7ebee2c61bbe6cba04c41abb1';
        $this->WATERMARK_URL = '';
        $this->NOTIFICATION_URL = '';

        add_action('dragon_video_encode', array(&$this, 'make_encodings'), 10, 5);

        add_filter('rewrite_rules_array', array(&$this, 'insert_rewrite_rules'));
        add_filter('query_vars',          array(&$this, 'insert_query_vars'));
        add_filter('init', 'flush_rewrite_rules');
        add_action('parse_query', array(&$this, 'do_page_redirect'));
    }

    public function insert_rewrite_rules($rules) {
        $new_rules = array(
            'zencoder/([a-zA-Z0-9]{32})/?$' => 'index.php?za=zencoder&zk=$matches[1]',
        );

        return $new_rules + $rules;
    }
    public function insert_query_vars($vars) {
        array_unshift($vars, 'za', 'zk');
        return $vars;
    }
    public function do_page_redirect($wp_query) {
        if (isset($wp_query->query_vars['za'])) {
            switch ($wp_query->query_vars['za']) {
                case 'zencoder':
                    $this->_handle_incoming_video($wp_query->query_vars['zk']);
                    break;
            }

            exit;
        }
    }
    private function _handle_incoming_video($token) {
        if (!empty($_POST)) {
            // $savedtoken = get_option('zencoder_token');
            $savedtoken = '9a4e65ac114cbfda975e9e2b658ecd91';

            if ($token == $savedtoken) {
                require_once("zencoder-php/Zencoder.php");
                $notification = ZencoderOutputNotification::catch_and_parse();
                // var_dump($notification);
                // var_dump("zencoder_job_{$notification->output->id}");
                $attachment = get_option("zencoder_job_{$notification->output->id}");
                // var_dump(get_option("zencoder_job_{$notification->output->id}"));
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                $tmp = download_url($notification->output->url);
                // var_dump($tmp);
                // unlink($tmp);
                $url_info = parse_url($notification->output->url);
                $name = basename($url_info['path']);
                // var_dump($name);
                $info = pathinfo($name);
                $ext = $info['extension'];
                $attachment_id = $attachment['attachment_id'];
                // var_dump(get_post($attachment['attachment_id']));
                $metadata = wp_get_attachment_metadata($attachment['attachment_id']);
                $size = isset($metadata['sizes'][$attachment['size']]) ? $attachment['size'] : 'original';
                // var_dump($size);
                $video_info = DragonVideo::get_video_info($tmp);
                $metadata['sizes'][$size]['height'] = $video_info['height'];
                $suffix = "{$metadata['sizes'][$size]['width']}x{$metadata['sizes'][$size]['height']}";
                $file = get_attached_file($attachment_id);
                // var_dump($file);
                $info = pathinfo($file);
                $dir = $info['dirname'];
                $ext2 = $info['extension'];
                $name = basename($file, ".{$ext2}");
                // var_dump($name);
                $destfilename = "{$dir}/{$name}-{$suffix}.{$ext}";
                // var_dump($destfilename);
                rename($tmp, $destfilename);
                $metadata['sizes'][$size]['file'] = "{$name}-{$suffix}";
                $details = new ZencoderRequest("https://app.zencoder.com/api/jobs/{$notification->job->id}", $this->API_KEY);

                $thumb = download_url($details->results['job']['thumbnails'][0]['url']);
                $url_info = parse_url($details->results['job']['thumbnails'][0]['url']);
                $fname = basename($url_info['path']);
                // var_dump($name);
                $info = pathinfo($fname);
                $ext = $info['extension'];
                $destfilename = "{$dir}/{$name}-{$suffix}.{$ext}";
                rename($thumb, $destfilename);
                $metadata['sizes'][$size]['thumbnail'] = "{$name}-{$suffix}.{$ext}";

                wp_update_attachment_metadata($attachment_id, $metadata);
            }
        } else {
            echo '<strong>ERROR:</strong> no direct access';
        }
    }

    function make_encodings($attachment_id, $file, $width, $height, $crop) {
        $file = str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $file);
        require_once("zencoder-php/Zencoder.php");
        // New Encoding Job
        $job = array(
            'test' => 1,
            'input' => $file,
            'output' => array(
                array(
                    'video_codec' => 'h264',
                    'width' => $width,
                    'watermark' => array(
                        'url' => $this->WATERMARK_URL,
                        'width' => '50%',
                    ),
                    'notifications' => array(
                        $this->NOTIFICATION_URL,
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                    ),
                ),
                array(
                    'video_codec' => 'theora',
                    'width' => $width,
                    'watermark' => array(
                        'url' => $this->WATERMARK_URL,
                        'width' => '50%',
                    ),
                    'notifications' => array(
                        $this->NOTIFICATION_URL,
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                    ),
                ),
            ),
            'api_key' => $this->API_KEY
        );
        $encoding_job = new ZencoderJob($job);
        // Check if it worked
        if ($encoding_job->created) {
            foreach ( $encoding_job->outputs as $o ) {
                add_option("zencoder_job_{$o->id}", array(
                    'attachment_id' => $attachment_id,
                    'size' => $width,
                ));
            }
            return true;
        } else {
            return false;
        }
    }
}

$zencoderencoder = new ZencoderEncoder;
