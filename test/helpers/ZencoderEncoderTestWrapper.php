<?php
use DragonVideo\ZencoderEncoder;

class ZencoderEncoderTestWrapper extends ZencoderEncoder {
    public function handle_incoming_video($token) {
        parent::handle_incoming_video($token);
    }
}
