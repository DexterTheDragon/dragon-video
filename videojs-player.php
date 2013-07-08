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
require_once __DIR__.'/vendor/autoload.php';
use DragonVideo\VideoJsPlayer;

$videojsplayer = new VideoJsPlayer;
