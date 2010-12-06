<?php
class VideoJsPlayer {

    function VideoJsPlayer() {
        add_action('wp_head', array(&$this, 'add_videojs_header'));
        add_filter('dragon_video_player', array(&$this, 'show_video'), 10, 2);
    }

    function add_videojs_header() {
        echo "";
        $url = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) . 'video-js';
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
        $html = <<< HTML
<!-- Begin VideoJS -->
<div class="video-js-box">
    <!-- Using the Video for Everybody Embed Code http://camendesign.com/code/video_for_everybody -->
    <video class="video-js" width="$width" height="$height" controls preload poster="$poster">
        <source src="$mp4" type='video/mp4' />
        <!-- <source src="http://video-js.zencoder.com/oceans-clip.webm" type='video/webm; codecs="vp8, vorbis"' /> -->
        <source src="$ogg" type='video/ogg' />
        <!-- Flash Fallback. Use any flash video player here. Make sure to keep the vjs-flash-fallback class. -->
        <object class="vjs-flash-fallback" width="$width" height="$height" type="application/x-shockwave-flash"
            data="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf">
            <param name="movie" value="http://releases.flowplayer.org/swf/flowplayer-3.2.1.swf" />
            <param name="allowfullscreen" value="true" />
            <param name="flashvars" value='config={"playlist":["$poster", {"url": "$mp4","autoPlay":false,"autoBuffering":true}]}' />
            <!-- Image Fallback. Typically the same as the poster image. -->
            <img src="$poster" width="$width" height="$height" alt="Poster Image" title="No video playback capabilities." />
        </object>
    </video>
    <!-- Download links provided for devices that can't play video in the browser. -->
    <p class="vjs-no-video"><strong>Download Video:</strong>
        <a href="$mp4">MP4</a>,
        <!-- <a href="http://video-js.zencoder.com/oceans-clip.webm">WebM</a>, -->
        <a href="$ogg">Ogg</a><br>
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
