<?php
namespace DragonVideo;

class VideoJsPlayer
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
        add_filter('dragon_video_player', array(&$this, 'show_video'), 10, 2);
        $this->script_url = plugin_dir_url(realpath(dirname(__FILE__).'/../')).'video-js';
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('videojs', $this->script_url.'/video.js');
        wp_enqueue_style('videojs', $this->script_url.'/video-js.min.css');
    }

    public function show_video($html, $video)
    {
        extract($video);

        $mp4_source = $mp4_link = $flash_fallback = $webm_source = $webm_link = $ogv_source = $ogv_link = null;
        // MP4 Source Supplied
        if ( isset($mp4) ) {
            $mp4_source = '<source src="'.$mp4.'" type="video/mp4">';
        }

        // WebM Source Supplied
        if ( isset($webm) ) {
            $webm_source = '<source src="'.$webm.'" type="video/webm">';
        }

        // Ogg source supplied
        if ( isset($ogv) ) {
            $ogv_source = '<source src="'.$ogv.'" type="video/ogg">';
        }

        $html = <<< HTML
<!-- Begin VideoJS -->
<video class="video-js vjs-default-skin" width="$width" height="$height" controls preload poster="$poster" data-setup>
    $mp4_source
    $webm_source
    $ogv_source
</video>
<!-- End VideoJS -->
HTML;
        return $html;
    }
}
