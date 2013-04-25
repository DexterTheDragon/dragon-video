<?php
/*
Plugin Name: Dragon Video - VideoJS Player
Plugin URI: http://github.com/DexterTheDragon/dragon-video
Description: Bundled player for Dragon Video. Uses the http://videojs.com player.
Author: Kevin Carter
Author URI: http://dexterthedragon.com/
Version: 0.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
class VideoJsPlayer {

    function VideoJsPlayer() {
        add_action('wp_head', array(&$this, 'add_videojs_header'));
        add_filter('dragon_video_player', array(&$this, 'show_video'), 10, 2);
    }

    function add_videojs_header() {
        echo "";
        $url = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) . 'lib/video-js';
        echo <<<HTML
        <script src="{$url}/video.js" type="text/javascript" charset="utf-8"></script>
        <link rel="stylesheet" href="{$url}/video-js.css" type="text/css" media="screen" title="Video JS" charset="utf-8">
        <script type="text/javascript" charset="utf-8">
            VideoJS.setupAllWhenReady();
        </script>
HTML;
    }

    function show_video($html, $video) {
        extract($video);

        $mp4_source = $mp4_link = $flash_fallback = $webm_source = $webm_link = $ogv_source = $ogv_link = null;
        // MP4 Source Supplied
        if ( isset($mp4) ) {
            $mp4_source = '<source src="'.$mp4.'" type="video/mp4">';
            $mp4_link = '<a href="'.$mp4.'">MP4</a>';
            $flash_fallback = <<<HTML
<!-- Flash Fallback. Use any flash video player here. Make sure to keep the vjs-flash-fallback class. -->
<object class="vjs-flash-fallback" width="$width" height="$height" type="application/x-shockwave-flash"
    data="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf">
    <param name="movie" value="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf" />
    <param name="allowfullscreen" value="true" />
    <param name="flashvars" value='config={"playlist":["$poster", {"url": "$mp4","autoPlay":false,"autoBuffering":true}]}' />
    <!-- Image Fallback. Typically the same as the poster image. -->
    <img src="$poster" width="$width" height="$height" alt="Poster Image" title="No video playback capabilities." />
</object>
HTML;
        }

        // WebM Source Supplied
        if ( isset($webm) ) {
            $webm_source = '<source src="'.$webm.'" type="video/webm">';
            $webm_link = '<a href="'.$webm.'">WebM</a>';
        }

        // Ogg source supplied
        if ( isset($ogv) ) {
            $ogv_source = '<source src="'.$ogv.'" type="video/ogg">';
            $ogv_link = '<a href="'.$ogv.'">Ogg</a>';
        }

        $html = <<< HTML
<!-- Begin VideoJS -->
<div class="video-js-box">
    <!-- Using the Video for Everybody Embed Code http://camendesign.com/code/video_for_everybody -->
    <video class="video-js" width="$width" height="$height" controls preload poster="$poster">
        $mp4_source
        $webm_source
        $ogv_source
        $flash_fallback
    </video>
    <!-- Download links provided for devices that can't play video in the browser. -->
    <p class="vjs-no-video"><strong>Download Video:</strong>
        $mp4_link
        $webm_link
        $ogv_link
        <!-- Support VideoJS by keeping this link. -->
        <a href="http://videojs.com">HTML5 Video Player</a> by VideoJS
    </p>
</div>
<!-- End VideoJS -->
HTML;
        return $html;
    }

}

$videojsplayer = new VideoJsPlayer;
