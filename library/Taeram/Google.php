<?php

namespace Taeram;

class Google {

    /**
     * The application name
     *
     * @var string
     */
    protected $applicationName;

    /**
     * The path to the credentials file
     *
     * @var string
     */
    protected $credentialsPath;

    /**
     * The path to the client secret file
     *
     * @var string
     */
    protected $clientSecretPath;

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
     * @param string $clientSecretPath The path to the client_secret.json
     * @param string $tmpPath The path to the temp folder
     */
    public function __construct($applicationName, $clientSecretPath, $tmpPath) {
        $this->applicationName = $applicationName;
        $this->clientSecretPath = $clientSecretPath;
        $this->credentialsPath = $tmpPath . '/google_credentials.json';
    }

    /**
     * Get the client
     *
     * @param array $scopes The scopes
     *
     * @return \Google_Client
     */
    public function getClient($scopes) {
        if ($this->client) {
            return $this->client;
        }

        // Create the client
        $this->client = new \Google_Client();
        $this->client->setApplicationName($this->applicationName);
        $this->client->setScopes($scopes);
        $this->client->setAuthConfig($this->clientSecretPath);
        $this->client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        if (file_exists($this->credentialsPath)) {
            $accessToken = json_decode(file_get_contents($this->credentialsPath), true);
        } else {
            // Request authorization from the user.
            $authUrl = $this->client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if(!file_exists(dirname($this->credentialsPath))) {
                mkdir(dirname($this->credentialsPath), 0700, true);
            }
            file_put_contents($this->credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $this->credentialsPath);
        }
        $this->client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            file_put_contents($this->credentialsPath, json_encode($this->client->getAccessToken()));
        }
    }
}
