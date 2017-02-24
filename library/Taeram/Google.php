<?php

namespace Taeram;

class Google {

    /**
     * The Google Client
     *
     * @var \Google_Client
     */
    protected $client;

    /**
     * Set the credentials
     *
     * @param string $serviceAccountKeyJson The path to the application credentials JSON file
     */
    public function __construct($serviceAccountKeyJson) {
        if (!file_exists($serviceAccountKeyJson)) {
            throw new \Exception("Cannot find service account key JSON file");
        }

        $this->client = new \Google_Client();
        $this->client->setAuthConfig($serviceAccountKeyJson);
    }

    /**
     * Get the Google Client
     *
     * @return \Google_Client
     */
    public function getClient() {
        return $this->client;
    }
}
