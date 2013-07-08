<?php
/*
Plugin Name: Dragon Video
Plugin URI: http://github.com/DexterTheDragon/dragon-video
Description: Extensible html5 video plugin. Dragon Video handles the interactions with WordPress (uploading videos, shortcodes, gallery) and leaves the actual transcoding and optional &lt;video&gt; player display to a filter. It intergrates with Wordpress Media Library so that when you upload a video, it automatically gets resized and transcoded to the configured formats. You can then insert the video into a post by browsing the library and clicking the "Insert into Post" button, which inserts a shortcode which will then be converted to the html needed to play the video. ffmpeg is needed to query video information, though the actual transcoding can be handled by a third party service. A default encoder is provided that uses zencoder.com to transcode videos. A VideoJS player is also included.
Author: Kevin Carter
Author URI: http://dexterthedragon.com/
Version: 0.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
# TODO: Link on page to re-convert video
require_once __DIR__.'/vendor/autoload.php';
use DragonVideo\DragonVideo;

$dragonvideo = new DragonVideo();
$dragonvideo->pluginInit(__FILE__);
