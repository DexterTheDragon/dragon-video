#!/bin/bash -ex
PLUGIN=video.php phpunit tests/test_dragon_video.php
PLUGIN=videojs-player.php phpunit tests/test_videojs-player.php
