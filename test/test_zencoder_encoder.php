<?php
require_once dirname(__FILE__).'/../lib/DragonVideo.php';
$GLOBALS['dragonvideo'] = new DragonVideo();
require dirname(__FILE__).'/../lib/ZencoderEncoder.php';
require dirname(__FILE__).'/../vendor/autoload.php';

class ZencoderEncoderTestWrapper extends ZencoderEncoder {
    public function _handle_incoming_video($token) {
        parent::_handle_incoming_video($token);
    }
}
/**
 *
 */
class ZencoderEncoderTests extends WP_UnitTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->zencoderencoder = new ZencoderEncoderTestWrapper();
    }

    /**
     * @covers ZencoderEncoder::ZencoderEncoder
     */
    public function test_filter_setup()
    {
        $token = get_option('zencoder_token');
        $this->assertEquals('http://example.org/zencoder/'.$token, $this->zencoderencoder->NOTIFICATION_URL);

        $this->assertEquals(10, has_action('admin_menu', array(&$this->zencoderencoder, 'admin_menu')));

        $this->assertEquals(10, has_action('dragon_video_encode', array(&$this->zencoderencoder, 'make_encodings')));

        $this->assertEquals(10, has_filter('rewrite_rules_array', array(&$this->zencoderencoder, 'insert_rewrite_rules')));
        $this->assertEquals(10, has_filter('query_vars', array(&$this->zencoderencoder, 'insert_query_vars')));
        $this->assertEquals(10, has_filter('init', 'flush_rewrite_rules'));
        $this->assertEquals(10, has_filter('parse_query', array(&$this->zencoderencoder, 'do_page_redirect')));

    }

    /**
     * @covers ZencoderEncoder::activate
     */
    public function test_option_defaults()
    {
        $this->zencoderencoder->activate();

        $expected = array(
            'api_key' => '',
            'watermark_url' => '',
        );

        $this->assertEquals(get_option('zencoder_options'), $expected);
    }

    /**
     * @covers ZencoderEncoder::admin_menu
     */
    public function test_admin_menu()
    {
        $current_user = get_current_user_id();
        wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

        # fake the parent page
        add_menu_page('Dragon Video', 'DragonVideo', 'manage_options', 'dragonvideo', null, null, null );

        $this->zencoderencoder->admin_menu();

        $expected['zencoder'] = 'http://example.org/wp-admin/admin.php?page=zencoder';

        foreach ($expected as $name => $value) {
            $this->assertEquals( $value, menu_page_url( $name, false ) );
        }

        wp_set_current_user( $current_user );
    }

    /**
     * @covers ZencoderEncoder::insert_rewrite_rules
     */
    public function test_insert_rewrite_rules()
    {
        $expected = array(
            'zencoder/([a-zA-Z0-9]{32})/?$' => 'index.php?za=zencoder&zk=$matches[1]',
            'existing' => 'rule',
        );
        $actual = $this->zencoderencoder->insert_rewrite_rules(array('existing' => 'rule'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ZencoderEncoder::insert_query_vars
     */
    public function test_insert_query_vars()
    {
        $expected = array('za', 'zk', 'foo');
        $actual = $this->zencoderencoder->insert_query_vars(array('foo'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ZencoderEncoder::do_page_redirect
     */
    public function test_do_page_redirect()
    {
        $this->markTestIncomplete();
        $wp_query = new stdClass;
        $wp_query->query_vars = array(
            'za' => 'zencoder',
            'zk' => 'foobarbaz',
        );

        $stub = $this->getMock('ZencoderEncoder', array('_handle_incoming_video'));
        $stub->expects($this->once())
            ->method('_handle_incoming_video')
            ->with($wp_query->query_vars['zk'])
            ->will($this->returnValue(true));

        $stub->do_page_redirect($wp_query);
    }

    /**
     * @covers ZencoderEncoder::_handle_incoming_video
     */
    public function test__handle_incoming_video()
    {
        $post_id       = $this->factory->post->create();
        $attachment_id = $this->factory->attachment->create_object( 'video.ogv', $post_id, array(
            'post_mime_type' => 'video/ogg',
            'post_type'      => 'attachment',
            'post_title'     => 'video.ogv',
        ) );
        $metadata = array (
            'height'   => 480,
            'width'    => 720,
            'duration' => 4,
            'sizes'    => array (
                'small' => array (
                    'width'  => 480,
                    'height' => 320,
                    'poster' => '',
                    'file'   => array (
                        'mp4'  => 'video-480x320.mp4',
                        'webm' => 'video-480x320.webm',
                        'ogv'  => 'video-480x320.ogv',
                    ),
                ),
            ),
        );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        $token = 1234567890;
        update_option('zencoder_token', $token);
        $_SERVER['REQUEST_METHOD'] = "POST";

        $notification = json_decode("{
            \"job\": {
                \"outputs\": [{
                    \"label\": \"$attachment_id-webm-small\",
                    \"url\": \"http://google.com/images/google_favicon_128.png\",
                    \"thumbnails\": [{
                        \"images\": [{
                            \"url\": \"http://google.com/images/google_favicon_128.png\",
                            \"format\": \"png\"
                        }]
                    }]
                }]
            }
        }");

        $stub = new stdClass;
        $stub->notifications = $this->getMock('stdClass', array('parseIncoming'));
        $stub->notifications->expects($this->once())
            ->method('parseIncoming')
            ->will($this->returnValue($notification));

        $this->zencoderencoder->zencoder = $stub;

        $z = $this->zencoderencoder;
        $actual = get_echo(array(&$z, '_handle_incoming_video'), array($token));
        $expected = "Saved $attachment_id-webm-small to /var/www/wordpress/wp-content/uploads/video-480x320.webm
Saved poster /var/www/wordpress/wp-content/uploads/video-480x320-0.png
";
        $this->assertEquals($expected, $actual);
        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @covers ZencoderEncoder::_handle_incoming_video
     */
    public function test__handle_incoming_video_error_message()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $z = $this->zencoderencoder;
        $actual = get_echo(array(&$z, '_handle_incoming_video'), array(''));
        $expected = "<strong>ERROR:</strong> no direct access";
        $this->assertEquals($expected, $actual);
        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @covers ZencoderEncoder::make_encodings
     */
    public function test_make_encodings_returns_true_and_sets_outputs()
    {
        $options = array('api_key' => '', 'watermark_url' => 'http://example.org/watermark.url');
        update_option('zencoder_options', $options);
        $this->zencoderencoder = new ZencoderEncoder();

        $post_id       = $this->factory->post->create();
        $attachment_id = $this->factory->attachment->create_object( 'video.ogv', $post_id, array(
            'post_mime_type' => 'video/ogg',
            'post_type'      => 'attachment',
            'post_title'     => 'video.ogv',
        ) );
        $sizes = array(
            'small' => array(
                'width'  => 480,
                'height' => 320,
                'poster' => 'poster-480x320.jpg',
                'file'   => array(
                    'mp4'  => 'video-480x320.mp4',
                    'webm' => 'video-480x320.webm',
                    'ogv'  => 'video-480x320.ogv',
                ),
            ),
        );
        $expected = array(
            'input' => 'video.ogv',
            'output' => array(
                array(
                    'label' => "{$attachment_id}-mp4-small",
                    'video_codec' => 'h264',
                    'width' => 480,
                    'notifications' => array(
                        'http://example.org/zencoder/',
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                        'label' => "{$attachment_id}-mp4-small",
                    ),
                    'watermark' => array(
                        'url' => 'http://example.org/watermark.url',
                        'width' => '50%',
                    ),
                ),
                array(
                    'label' => "{$attachment_id}-webm-small",
                    'video_codec' => 'vp8',
                    'width' => 480,
                    'notifications' => array(
                        'http://example.org/zencoder/',
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                        'label' => "{$attachment_id}-webm-small",
                    ),
                    'watermark' => array(
                        'url' => 'http://example.org/watermark.url',
                        'width' => '50%',
                    ),
                ),
                array(
                    'label' => "{$attachment_id}-ogv-small",
                    'video_codec' => 'theora',
                    'width' => 480,
                    'notifications' => array(
                        'http://example.org/zencoder/',
                    ),
                    'thumbnails' => array(
                        'number' => 2,
                        'label' => "{$attachment_id}-ogv-small",
                    ),
                    'watermark' => array(
                        'url' => 'http://example.org/watermark.url',
                        'width' => '50%',
                    ),
                ),
            ),
        );

        $output = new stdClass;
        $output->outputs = array();

        $stub = new stdClass;
        $stub->jobs = $this->getMock('stdClass', array('create'));
        $stub->jobs->expects($this->once())
            ->method('create')
            ->with($expected)
            ->will($this->returnValue($output));

        $this->zencoderencoder->zencoder = $stub;

        $actual = $this->zencoderencoder->make_encodings('video.ogv', $attachment_id, $sizes);
        $this->assertTrue($actual);
    }

    /**
     * @covers ZencoderEncoder::make_encodings
     */
    public function test_make_encodings_returns_false()
    {
        $stub = new stdClass;
        $stub->jobs = new MockJob;

        $this->zencoderencoder->zencoder = $stub;

        $actual = $this->zencoderencoder->make_encodings('video.ogv', 0, array());
        $this->assertFalse($actual);
    }

    /**
     * @covers ZencoderEncoder::options_page
     */
    public function test_options_page()
    {
        $_POST = array(
            'Submit' => 'true',
            'api_key' => 1234567890,
        );
        $expected = file_get_contents(TEST_FIXTURE_DIR.'/zencoder_options_page.html');
        $actual = get_echo(array(&$this->zencoderencoder, 'options_page'));
        $this->assertEquals($expected, $actual);
    }
}
