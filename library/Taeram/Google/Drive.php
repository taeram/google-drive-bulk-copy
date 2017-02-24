<?php

namespace Taeram\Google;

class Drive extends \Taeram\Google {

    /**
     * @var \Google_Service_Drive
     */
    protected $service;

    /**
     * Construct the class
     *
     * @param string $serviceAccountKeyJson The path to the application credentials JSON file
     */
    public function __construct($serviceAccountKeyJson) {
        parent::__construct($serviceAccountKeyJson);

        // Setup Google Drive
        $this->client->addScope(\Google_Service_Drive::DRIVE);
        $this->service = new \Google_Service_Drive($this->client);
    }

}
