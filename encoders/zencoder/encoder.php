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
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once("zencoder-php/Zencoder.php");
                $notification = ZencoderOutputNotification::catch_and_parse();
                $tmp = download_url($notification->output->url);

                $attachment = get_option("zencoder_job_{$notification->output->id}");

                $url_info = parse_url($notification->output->url);
                $name = basename($url_info['path']);

                $info = pathinfo($name);
                $ext = $info['extension'];
                $attachment_id = $attachment['attachment_id'];

                $metadata = wp_get_attachment_metadata($attachment['attachment_id']);
                $size = isset($metadata['sizes'][$attachment['size']]) ? $attachment['size'] : 'original';


                $file = get_attached_file($attachment_id);
                $info = pathinfo($file);
                $dir = $info['dirname'];
                $info = pathinfo($metadata['sizes'][$size]['file'][$ext]);
                $name = $info['filename'];

                $destfilename = "{$dir}/{$metadata['sizes'][$size]['file'][$ext]}";

                rename($tmp, $destfilename);

                $details = new ZencoderRequest("https://app.zencoder.com/api/jobs/{$notification->job->id}", $this->API_KEY);

                $thumb = download_url($details->results['job']['thumbnails'][0]['url']);
                $url_info = parse_url($details->results['job']['thumbnails'][0]['url']);
                $fname = basename($url_info['path']);
                $info = pathinfo($fname);
                $ext = $info['extension'];


                $destfilename = "{$dir}/{$name}.{$ext}";
                rename($thumb, $destfilename);
                $metadata['sizes'][$size]['poster'] = "{$name}.{$ext}";

                wp_update_attachment_metadata($attachment_id, $metadata);
            }
        } else {
            echo '<strong>ERROR:</strong> no direct access';
        }
    }

    function make_encodings($meta, $attachment_id, $file) {
        $file = str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $file);
        require_once("zencoder-php/Zencoder.php");
        // New Encoding Job
        $job = array(
            'test' => 1,
            'input' => $file,
            'output' => array(
                array(
                    'video_codec' => 'h264',
                    'width' => $meta['width'],
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
                    'width' => $meta['width'],
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
                    'size' => $meta['width'],
                ));
            }

            return true;
        } else {
            return false;
        }
    }
}

$zencoderencoder = new ZencoderEncoder;
