#!/bin/bash -ex
PLUGIN=video.php phpunit tests/test_dragon_video.php
PLUGIN=videojs-player.php phpunit tests/test_videojs-player.php
PLUGIN=zencoder_encoder.php phpunit tests/test_zencoder_encoder.php
