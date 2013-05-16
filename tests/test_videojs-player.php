<?php
require dirname(__FILE__).'/../lib/VideoJsPlayer.php';

/**
 *
 */
class VideoJsPlayerTests extends WP_UnitTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->videojsplayer = new VideoJsPlayer();
    }

    /**
     * @covers VideoJsPlayer::VideoJsPlayer
     */
    public function test_filter_setup()
    {
        $this->assertEquals(10, has_action('wp_head', array(&$this->videojsplayer, 'add_videojs_header')));
        $this->assertEquals(10, has_filter('dragon_video_player', array($this->videojsplayer, 'show_video')));
    }

    /**
     * @covers VideoJsPlayer::add_videojs_header
     */
    public function test_add_videojs_header()
    {
        $expected = file_get_contents( dirname( __FILE__ ) . '/fixtures/videojs_header.html');
        $actual = get_echo(array(&$this->videojsplayer, 'add_videojs_header'));
        $this->assertEquals($expected, "$actual\n");
    }

    /**
     * @covers VideoJsPlayer::show_video
     */
    public function test_show_video()
    {
        $video = array(
            'width'  => 720,
            'height' => 480,
            'mp4'    => 'test.mp4',
            'webm'   => 'test.webm',
            'ogv'    => 'test.ogv',
            'poster' => 'poster.jpg',
        );
        $expected = file_get_contents( dirname( __FILE__ ) . '/fixtures/videojs.html');
        $actual = $this->videojsplayer->show_video('', $video);
        $this->assertEquals($expected, "$actual\n");
    }
}
