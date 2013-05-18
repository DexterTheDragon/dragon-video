<?php

class MockJob {
    function create($job) {
        throw new Services_Zencoder_Exception('ERROR');
    }
}
