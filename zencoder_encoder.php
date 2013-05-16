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
require 'lib/ZencoderEncoder.php';

$zencoderencoder = new ZencoderEncoder;
