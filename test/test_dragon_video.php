<?php
require dirname(__FILE__).'/../lib/DragonVideo.php';

/**
 *
 */
class DragonVideoTests extends WP_UnitTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->dragonvideo = new DragonVideo();
    }

    private function create_attachment()
    {
        $this->post_id       = $this->factory->post->create();
        $this->attachment_id = $this->factory->attachment->create_object( 'video.ogv', $this->post_id, array(
            'post_mime_type' => 'video/ogg',
            'post_type'      => 'attachment',
            'post_title'     => 'video.ogv',
        ) );
        $this->metadata = array (
            'height'   => 480,
            'width'    => 720,
            'duration' => 4,
            'sizes'    => array (
                'original' => array (
                    'width'  => 720,
                    'height' => 480,
                    'poster' => 'poster-720x480.jpg',
                    'file'   => array (
                        'mp4'  => 'video-720x480.mp4',
                        'webm' => 'video-720x480.webm',
                        'ogv'  => 'video-720x480.ogv',
                    ),
                ),
                'small' => array (
                    'width'  => 480,
                    'height' => 320,
                    'poster' => 'poster-480x320.jpg',
                    'file'   => array (
                        'mp4'  => 'video-480x320.mp4',
                        'webm' => 'video-480x320.webm',
                        'ogv'  => 'video-480x320.ogv',
                    ),
                ),
            ),
        );
        wp_update_attachment_metadata( $this->attachment_id, $this->metadata );
    }

    /**
     * @covers DragonVideo::DragonVideo
     * @covers DragonVideo::pluginInit
     */
    public function test_pluginInit()
    {
        $this->dragonvideo->pluginInit('some.php');

        $this->assertEquals(
            10,
            has_action('activate_some.php', array(&$this->dragonvideo, 'activate')),
            'Plugin activation not registered'
        );

        $this->assertEquals(11, has_filter('attachment_fields_to_edit', array(&$this->dragonvideo, 'show_video_fields_to_edit')));
        $this->assertEquals(10, has_filter('media_send_to_editor', array(&$this->dragonvideo,'video_send_to_editor_shortcode')));
        $this->assertEquals(10, has_filter('wp_generate_attachment_metadata', array(&$this->dragonvideo, 'video_metadata')));
        $this->assertEquals(10, has_action('delete_attachment', array(&$this->dragonvideo, 'delete_attachment')));

        $this->assertEquals(1, has_action('admin_menu', array(&$this->dragonvideo, 'admin_menu')));

        $this->assertEquals(10, has_filter('post_gallery', array(&$this->dragonvideo, 'video_gallery')));
        $this->assertEquals(10, has_filter('wp_get_attachment_link', array(&$this->dragonvideo, 'wp_get_attachment_link')));

        global $shortcode_tags;
        $this->assertTrue(array_key_exists('dragonvideo', $shortcode_tags));
    }

    /**
     * @covers DragonVideo::activate
     */
    public function test_option_defaults()
    {
        $this->dragonvideo->activate();

        $expected = array(
            'formats' => array(
                'mp4' => true,
                'webm' => true,
                'ogv' => true,
            ),
            'sizes'   => array(
                'large'  => array('', ''),
                'medium' => array(720, 480),
                'small'  => array(480, 320),
            ),
            'ffmpeg_path' => '/usr/bin/ffmpeg',
            'override_gallery' => true,
        );

        $this->assertEquals($expected, get_option('dragon-video'));
    }

    /**
     * @covers DragonVideo::admin_menu
     */
    public function test_admin_menu()
    {
        $this->dragonvideo->admin_menu();

        $expected['dragonvideo'] = 'http://example.org/wp-admin/admin.php?page=dragonvideo';

        foreach ($expected as $name => $value) {
            $this->assertEquals( $value, menu_page_url( $name, false ) );
        }
    }

    /**
     * @covers DragonVideo::video_metadata
     */
    public function test_video_metadata_returns_if_not_a_video_()
    {
        $stub = $this->getMock('DragonVideo', array('is_video'));
        $stub->expects($this->once())
            ->method('is_video')
            ->will($this->returnValue(false));

        $post_id = $this->factory->post->create();
        $attachment_id = $this->factory->attachment->create_object( 'some.txt', $post_id, array(
            'post_mime_type' => 'text/text',
            'post_type' => 'attachment',
            'post_title' => 'some.txt'
        ) );
        $metadata = array(
            'Nothing to see here',
        );

        $result = $stub->video_metadata($metadata, $attachment_id);
        $this->assertEquals($metadata, $result);
    }

    /**
     * @covers DragonVideo::video_metadata
     */
    public function test_video_metadata_returns_the_metadata_for_resized_videos()
    {
        $expected = array (
            'height'   => 480,
            'width'    => 720,
            'duration' => 4,
            'sizes'    => array (
                'original' => array (
                    'width'  => 720,
                    'height' => 480,
                    'file'   => array (
                        'mp4'  => 'video-720x480.mp4',
                        'webm' => 'video-720x480.webm',
                        'ogv'  => 'video-720x480.ogv',
                    ),
                    'poster' => '',
                ),
                'small' => array (
                    'width'  => 480,
                    'height' => 320,
                    'file'   => array (
                        'mp4'  => 'video-480x320.mp4',
                        'webm' => 'video-480x320.webm',
                        'ogv'  => 'video-480x320.ogv',
                    ),
                    'poster' => '',
                ),
            ),
        );
        $stub = $this->getMock('DragonVideo', array('get_video_info'));
        $stub->expects($this->once())
            ->method('get_video_info')
            ->will($this->returnValue(array('width' => 720, 'height' => 480, 'duration' => 4)));

        $this->create_attachment();

        $a = new MockAction();
        $tag = 'dragon_video_encode';
        add_action($tag, array(&$a, 'action'));

        $metadata = $stub->video_metadata(array(), $this->attachment_id);
        $this->assertEquals($expected, $metadata);

        $this->assertEquals(1, $a->get_call_count());
        $this->assertEquals(array($tag), $a->get_tags());
    }

    /**
     * @covers DragonVideo::get_video_info
     */
    public function test_get_video_info()
    {
        $expected = array('width' => 720, 'height' => 480, 'duration' => 4);
        $file = TEST_FIXTURE_DIR.'/test_video.ogv';

        $info = $this->dragonvideo->get_video_info($file);

        $this->assertEquals($expected, $info);
    }

    /**
     * @covers DragonVideo::is_video
     */
    public function test_is_video_returns_true_for_known_video_types()
    {
        $video_exts = array('ogg', 'mp4', 'flv', 'avi', 'wmv', 'm4v', 'mov', 'ogv');
        foreach ($video_exts as $ext) {
            $post_id = $this->factory->post->create();
            $attachment_id = $this->factory->attachment->create_object( "video.${ext}", $post_id, array(
                'post_type' => 'attachment',
                'post_title' => 'video.ogv'
            ) );

            $this->assertTrue($this->dragonvideo->is_video($attachment_id));
        }
    }

    /**
     * @covers DragonVideo::is_video
     */
    public function test_is_video_returns_false_for_unknown_types()
    {
        $video_exts = array('jpg', 'png', 'gif', 'txt');
        foreach ($video_exts as $ext) {
            $post_id = $this->factory->post->create();
            $attachment_id = $this->factory->attachment->create_object( "video.${ext}", $post_id, array(
                'post_type' => 'attachment',
                'post_title' => 'video.ogv'
            ) );

            $this->assertFalse($this->dragonvideo->is_video($attachment_id));
        }
    }

    /**
     * @covers DragonVideo::is_video
     */
    public function test_is_video_returns_true_for_video_mime_types()
    {
        $video_exts = array('jpg', 'png', 'gif', 'txt');
        foreach ($video_exts as $ext) {
            $post_id = $this->factory->post->create();
            $attachment_id = $this->factory->attachment->create_object( "video.${ext}", $post_id, array(
                'post_mime_type' => "video/${ext}",
                'post_type' => 'attachment',
                'post_title' => 'video.ogv'
            ) );

            $this->assertTrue($this->dragonvideo->is_video($attachment_id));
        }
    }

    /**
     * @covers DragonVideo::get_video_sizes
     */
    public function test_get_video_sizes()
    {
        $expected = array(
            'large',
            'medium',
            'small',
        );
        $this->assertEquals($expected, $this->dragonvideo->get_video_sizes());
    }

    /**
     * @covers DragonVideo::make_encodings
     */
    public function test_make_encodings()
    {
        $expected = array(
            'width'  => 720,
            'height' => 480,
            'file'   => array(
                'mp4'  => 'video-720x480.mp4',
                'webm' => 'video-720x480.webm',
                'ogv'  => 'video-720x480.ogv',
            ),
            'poster' => '',
        );

        $actual = $this->dragonvideo->make_encodings(null, null, 'video.flv', 720, 480, null);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::delete_attachment
     */
    public function test_delete_attachment_returns_if_not_video()
    {
        $stub = $this->getMock('DragonVideo', array('is_video'));
        $stub->expects($this->once())
            ->method('is_video')
            ->will($this->returnValue(false));

        $result = $stub->delete_attachment(0);
        $this->assertNull($result);
    }

    /**
     * @covers DragonVideo::delete_attachment
     */
    public function test_delete_attachment()
    {
        $this->create_attachment();

        $src = dirname(get_attached_file($this->attachment_id));

        $metadata = array (
            'sizes'    => array (
                'original' => array (
                    'file'   => array (
                        'mp4'  => "${src}/video-720x480.mp4",
                        'webm' => "${src}/video-720x480.webm",
                        'ogv'  => "${src}/video-720x480.ogv",
                    ),
                    'poster' => "${src}/poster.jpg",
                    'posters' => array(
                        "${src}/poster1.jpg"
                    ),
                ),
            ),
        );
        foreach ( $metadata['sizes']['original']['file'] as $file ) {
            // touch($file);
        }

        wp_update_attachment_metadata( $this->attachment_id, $metadata );

        $this->dragonvideo->delete_attachment($this->attachment_id);
    }

    /**
     * @covers DragonVideo::video_embed
     */
    public function test_video_embed()
    {
        $expected = file_get_contents(TEST_FIXTURE_DIR.'/video.html');

        $this->create_attachment();
        $post = get_post($this->attachment_id);

        $a = new MockAction();
        $tag = 'dragon_video_player';
        add_action($tag, array(&$a, 'action'));


        $html = $this->dragonvideo->video_embed($post);
        $this->assertEquals($expected, "$html\n");

        $this->assertEquals(1, $a->get_call_count());
        $this->assertEquals(array($tag), $a->get_tags());
    }

    /**
     * @covers DragonVideo::tag_replace
     */
    public function test_tag_replace_returns_video_not_found()
    {
        $actual = $this->dragonvideo->tag_replace(array(0));
        $this->assertEquals('Video 0 Not Found', $actual);
    }

    /**
     * @covers DragonVideo::tag_replace
     */
    public function test_tag_replace_uses_default_size()
    {
        $this->create_attachment();
        $post = get_post($this->attachment_id);
        $stub = $this->getMock('DragonVideo', array('video_embed'));
        $stub->expects($this->once())
            ->method('video_embed')
            ->with($post, 'medium')
            ->will($this->returnValue(true));

        $stub->tag_replace(array($this->attachment_id));
    }

    /**
     * @covers DragonVideo::tag_replace
     */
    public function test_tag_replace_uses_requested_size()
    {
        $this->create_attachment();
        $post = get_post($this->attachment_id);
        $stub = $this->getMock('DragonVideo', array('video_embed'));
        $stub->expects($this->once())
            ->method('video_embed')
            ->with($post, 'small')
            ->will($this->returnValue(true));

        $stub->tag_replace(array($this->attachment_id, 'size' => 'small'));
    }

    /**
     * @covers DragonVideo::video_send_to_editor_shortcode
     */
    public function test_video_send_to_editor_shortcode_returns_html_when_not_a_video()
    {
        $expected = 'Nothing to see here';
        $actual = $this->dragonvideo->video_send_to_editor_shortcode($expected, 0, null);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::video_send_to_editor_shortcode
     */
    public function test_video_send_to_editor_shortcode_returns_shortcode()
    {
        $this->create_attachment();
        $expected = "[dragonvideo {$this->attachment_id}]";
        $actual = $this->dragonvideo->video_send_to_editor_shortcode($expected, $this->attachment_id, null);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::show_video_fields_to_edit
     */
    public function test_show_video_fields_to_edit_returns_fields_when_not_a_video()
    {
        $post = new stdClass;
        $post->ID = 0;
        $expected = array('Nothing to see here');
        $actual = $this->dragonvideo->show_video_fields_to_edit($expected, $post);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::show_video_fields_to_edit
     */
    public function test_show_video_fields_to_edit_returns_fields()
    {
        $this->create_attachment();
        $post = get_post($this->attachment_id);
        $fields = array(
            'url' => 'Remove Me',
            'keep' => 'me',
        );
        $expected = array(
            'keep' => 'me',
            'video-preview' => array(
                'label' => 'Preview and Insert',
                'input' => 'html',
                'html'  => "<p>Shortcode for embedding: [dragonvideo {$this->attachment_id}]</p>video_html",
            ),
        );

        $stub = $this->getMock('DragonVideo', array('video_embed'));
        $stub->expects($this->once())
            ->method('video_embed')
            ->with($post, 'medium')
            ->will($this->returnValue('video_html'));

        $actual = $stub->show_video_fields_to_edit($fields, $post);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_false_when_metadata_blank()
    {
        $result = $this->dragonvideo->get_video_for_size(0);
        $this->assertFalse($result);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_the_size_when_available()
    {
        $this->create_attachment();
        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'small');
        $this->assertEquals($this->metadata['sizes']['small'], $result);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_size_if_only_one_available()
    {
        $this->create_attachment();
        $metadata = array(
            'sizes' => array(
                'onesizefitsall' => array(
                    'width' => 1920,
                    'height' => 1080,
                ),
            ),
        );
        wp_update_attachment_metadata( $this->attachment_id, $metadata );

        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'small');
        $this->assertEquals($metadata['sizes']['onesizefitsall'], $result);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_the_closest_match()
    {
        $this->create_attachment();
        $metadata = array(
            'sizes' => array(
                'large' => array(
                    'width' => 4096,
                    'height' => 2160,
                ),
                'smallwidthbutlargeheight' => array(
                    'width' => 480,
                    'height' => 1080,
                ),
            ),
        );
        wp_update_attachment_metadata( $this->attachment_id, $metadata );

        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'small');
        $this->assertEquals($metadata['sizes']['smallwidthbutlargeheight'], $result);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_original_size_as_fallback()
    {
        $this->create_attachment();

        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'large');
        $this->assertEquals($this->metadata['sizes']['original'], $result);
    }

    /**
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_fails_on_unknown_sizes()
    {
        $this->markTestSkipped('Known Bug');
        $this->create_attachment();

        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'unsetsize');
        $this->assertEquals($this->metadata['sizes']['original'], $result);
    }

    /**
     * TODO: This is to make phpunit code coverage happy, should probably refactor the method
     * @covers DragonVideo::get_video_for_size
     */
    public function test_get_video_for_size_returns_null()
    {
        $this->create_attachment();
        $metadata = array(
            'sizes' => array(
            ),
        );
        wp_update_attachment_metadata( $this->attachment_id, $metadata );

        $result = $this->dragonvideo->get_video_for_size($this->attachment_id, 'small');
        $this->assertNull($result);
    }

    /**
     * @covers DragonVideo::video_gallery
     */
    public function test_video_gallery()
    {
        $this->markTestIncomplete();
    }

    /**
     * @covers DragonVideo::wp_get_attachment_link
     */
    public function test_wp_get_attachment_link_returns_passed_string_when_not_video()
    {
        $expected = 'Returns this string';
        $actual = $this->dragonvideo->wp_get_attachment_link($expected, 0, null, null, null, null);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::wp_get_attachment_link
     */
    public function test_wp_get_attachment_link_uses_passed_link_text()
    {
        $this->create_attachment();
        $link_text = 'Hello World';
        $expected = "<a href='http://example.org/wp-content/uploads/video.ogv' title='video.ogv' class='video_overlay'>$link_text</a>";
        $actual = $this->dragonvideo->wp_get_attachment_link('', $this->attachment_id, null, null, null, $link_text);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::wp_get_attachment_link
     */
    public function test_wp_get_attachment_link_returns_permalink()
    {
        $this->create_attachment();
        $link_text = 'Hello World';
        $url = get_attachment_link($this->attachment_id);
        $expected = "<a href='$url' title='video.ogv' class='video_overlay'>$link_text</a>";
        $actual = $this->dragonvideo->wp_get_attachment_link('', $this->attachment_id, null, true, null, $link_text);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::wp_get_attachment_link
     */
    public function test_wp_get_attachment_link_defaults_to_post_title()
    {
        $this->create_attachment();
        $link_text = '    ';
        $expected = "<a href='http://example.org/wp-content/uploads/video.ogv' title='video.ogv' class='video_overlay'>video.ogv</a>";
        $actual = $this->dragonvideo->wp_get_attachment_link('', $this->attachment_id, null, null, null, $link_text);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::wp_get_attachment_link
     */
    public function test_wp_get_attachment_link_returns_html5_video()
    {
        $this->create_attachment();

        $expected = "<a href='http://example.org/wp-content/uploads/video.ogv' title='video.ogv' class='video_overlay'><img src='http://example.org/wp-content/uploads/poster-720x480.jpg' width='150' height='150' /></a>";
        $actual = $this->dragonvideo->wp_get_attachment_link('', $this->attachment_id, 'medium', null, null, null);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::options_page
     */
    public function test_options_page()
    {
        $_POST = array(
            'Submit' => 'true',
            'override_gallery' => 0,
        );
        $expected = file_get_contents(TEST_FIXTURE_DIR.'/options_page.html');
        $actual = get_echo(array(&$this->dragonvideo, 'options_page'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers DragonVideo::encode_formats
     */
    public function test_encode_formats()
    {
        update_option('dragon-video', array(
            'formats' => array(
                'mp4' => true, 'webm' => true, 'ogv' => false
            )
        ));
        $expected = array('mp4', 'webm');
        $actual = DragonVideo::encode_formats();
        $this->assertEquals($expected, $actual);
    }

}
