Dragon Video
============

A WordPress plugin for converting uploaded videos to HTML5 format (h264,
webm, ogv) and displaying them with the \<video> tag.

Dragon Video consists of 3 plugins:

* Dragon Video
* Dragon Video - Zencoder encoder
* Dragon Video - VideoJS Player

Dragon Video is the core plugin. It handles interfacing with videos
uploaded through WordPress, passing them off to an encoder, providing a
shortcode to display the video, and enabling videos to be shown in the
WordPress gallery. Dragon Video itself does not handle video encoding.
WordPress actions are called for video encoding and display of the HTML5
tag allowing any desired encoder or player to be used.

Dragon Video - Zencoder encoder is a bundled encoder. It uses
http://zencoder.com to encode uploaded videos to HTML5 media types. An
API key is required to use.

Dragon Video - VideoJS Player is a bundled player using the
http://videojs.com/ HTML5 video player.

Requirements
------------

FFmpeg is required on the server in order to read video metadata. Video
encoding does not really on FFmpeg.

How it works
------------

Videos can be uploaded in any format accepted by WordPress. This video
is saved in the standard WordPress location. e.g.
`wp-content/uploads/YYYY/MM/my-video.mov` Dragon Video then calls the
`dragon_video_encode` action passing the filename, attachment id, and an
array of video sizes. The configured encoder then encodes the video to
the requested sizes and formats saving the resulting video alongside the
original. e.g.:

* `wp-content/uploads/YYYY/MM/my-video-640x480.mp4`
* `wp-content/uploads/YYYY/MM/my-video-640x480.ogv`
* `wp-content/uploads/YYYY/MM/my-video-640x480.webm`

A thumbnail is also saved and its filename is stored in the original
videos WordPress attachment metadata.

A shortcode is created to place the video into a post. Dragon Video will
create a standard HTML5 \<video> tag with a flash player fallback
provided by flowplayer. It then calls the `dragon_video_player` action
to allow customizing of the displayed html.

Known Bugs
----------

### Dragon Video - Zencoder encoder

* Upon completion of encoding Zencoder sends us a notification. We then
  begin to retrieve the encoded video. With large files, downloading can
  take longer than Zencoder waits for a response to the notification.
  This causes Zencoder to mark the notification as a failure and requeue
  it. We still download the video successfully but Zencoder sends the
  notification several times until we can reply with a success.

### Dragon Video

* WordPress gallery integration is broken as of WordPress 3.5


Contributing
------------

* Fork the project.
* Make your feature addition or bug fix.
* Add tests for it. This is important so I don't break it in a future version unintentionally.
* Commit your changes
* Send me a pull request. Bonus points for topic branches.
