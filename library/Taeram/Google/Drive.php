<?php

namespace Taeram\Google;

class Drive extends \Taeram\Google {

    /**
     * @var \Google_Service_Drive
     */
    protected $service;

    /**
     * The max number of allowed requests per second
     *
     * From Google, the Drive API has a limit of 10,000	queries per 100 seconds
     *
     * @var integer
     */
    protected $maxRequestsPerSecond = 10000 / 100;

    /**
     * When did we start making requests?
     *
     * @var integer
     */
    protected $requestTimestamps = array();

    /**
     * How many requests have we made so far?
     *
     * @var integer
     */
    protected $numRequests = 0;

    /**
     * Get the client
     *
     * @return \Google_Client
     */
    public function getClient($scopes = null) {
        parent::getClient(array(
            \Google_Service_Drive::DRIVE
        ));
        $this->service = new \Google_Service_Drive($this->client);
    }

    /**
     * Get the specified file
     *
     * @param string $fileId The file id
     *
     * @return \Google_Service_Drive_DriveFile or null if none found
     */
    public function getFileById($fileId) {
        return $this->call($this->service->files, 'get', array(
            $fileId
        ));
    }

    /**
     * Get the specified file. Assumes only one file exists with the specified name.
     *
     * @param string $fileId The file id
     * @param string $folderId The folder id. Optional. If not set, looks in the root folder
     *
     * @return \Google_Service_Drive_DriveFile or null if none found
     */
    public function getFileByName($fileName, $folderId = null) {
        $fileName = addslashes($fileName);
        $q = "name = '$fileName'";
        if ($folderId) {
            $q .= " and '$folderId' in parents";
        }

        $fileList = $this->call($this->service->files, 'listFiles', array(
            array(
                'orderBy' => 'name,folder,createdTime',
                'q' => $q
            )
        ));

        $files = $fileList->getFiles();
        if ($files) {
            return $files[0];
        }

        return null;
    }

    /**
     * Create a folder
     *
     * @param string $folderName The folder name
     * @param string $parentFolderId The parent folder id. Optional.
     *
     * @return \Google_Service_Drive_DriveFile
     */
    public function createFolder($folderName, $parentFolderId = null) {
        // Return if the folder already exists
        $folder = $this->getFileByName($folderName, $parentFolderId);
        if ($folder) {
            return $folder;
        }

        // Create the folder
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ));
        $folder = $this->call($this->service->files, 'create', array(
            $fileMetadata,
            array('fields' => 'id')
        ));

        if ($parentFolderId) {
            // Retrieve the existing parents to remove
            $folderParents = $this->call($this->service->files, 'get', array(
                $folder->id,
                array('fields' => 'parents')
            ));
            $previousParents = join(',', $folderParents->parents);

            // Move the file to the new folder
            $emptyFileMetadata = new \Google_Service_Drive_DriveFile();
            $this->call($this->service->files, 'update', array(
                $folder->id,
                $emptyFileMetadata,
                array(
                    'addParents' => $parentFolderId,
                    'removeParents' => $previousParents,
                    'fields' => 'id, parents'
                )
            ));
        }

        return $this->getFileById($folder->id);
    }

    /**
     * Move a folder
     *
     * @param string $childFolderId The child folder id
     * @param string $parentFolderId The parent folder id
     *
     * @return \Google_Service_Drive_DriveFile
     */
    public function moveFolder($childFolderId, $parentFolderId) {
        // Retrieve the existing parents to remove
        $childFolderParents = $this->call($this->service->files, 'get', array(
            $childFolderId,
            array('fields' => 'parents')
        ));
        $previousParents = join(',', $childFolderParents->parents);

        // Move the file to the new folder
        $emptyFileMetadata = new \Google_Service_Drive_DriveFile();
        $this->call($this->service->files, 'update', array(
            $childFolderId,
            $emptyFileMetadata,
            array(
                'addParents' => $parentFolderId,
                'removeParents' => $previousParents,
                'fields' => 'id, parents'
            )
        ));

        return $this->getFileById($childFolderId);
    }

    /**
     * Copy a file to the specified destination
     *
     * @param \Google_Service_Drive_DriveFile $file The file to copy
     * @param string $destinationFolderId The destination folder id
     *
     * @return \Google_Service_Drive_DriveFile
     */
    public function copyFile(\Google_Service_Drive_DriveFile $file, $destinationFolderId) {
        // Create the dummy file
        $fileToCopy = new \Google_Service_Drive_DriveFile();
        $fileToCopy->setName($file->getName());
        $fileToCopy->setMimeType($file->getMimeType());
        $fileToCopy->setParents(array($destinationFolderId));

        // Make a copy of the file
        $fileCopy = $this->call($this->service->files, 'copy', array(
            $file->id,
            $fileToCopy
        ));

        return $this->getFileById($fileCopy->id);
    }

    /**
     * Get all of the files for the speicfied folder
     *
     * @param string $folderId The folder id
     *
     * @return array of \Google_Service_Drive_DriveFile or null if none found
     */
    public function findFilesInFolderById($folderId) {
        $files = null;

        $pageToken = null;
        do {
            $fileList = $this->call($this->service->files, 'listFiles', array(
                array(
                    'orderBy' => 'name,folder,createdTime',
                    'pageToken' => $pageToken,
                    'q' => "'$folderId' in parents"
                )
            ));

            $list = $fileList->getFiles();
            foreach ($list as $item) {
                $files[] = $item;
            }

            $pageToken = $fileList->getNextPageToken();
        } while ($pageToken !=  null);

        if (empty($files)) {
            return null;
        }

        return $files;
    }
}
