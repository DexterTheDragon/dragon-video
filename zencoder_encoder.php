<?php
/*
Plugin Name: Dragon Video - Zencoder encoder
Plugin URI: http://github.com/DexterTheDragon/dragon-video
Description: Bundled encoder for Dragon Video. Uses http://zencoder.com to perform video encoding.
Author: Kevin Carter
Author URI: http://dexterthedragon.com/
Version: 0.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
require_once __DIR__.'/vendor/autoload.php';
use DragonVideo\ZencoderEncoder;

$zencoderencoder = new ZencoderEncoder;
$zencoderencoder->pluginInit(__FILE__);
