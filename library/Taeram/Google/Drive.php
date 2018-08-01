<?php

namespace Taeram\Google;

class Drive extends \Taeram\Google {

  /**
   * @var \Google_Service_Drive
   */
  protected $service;

  /**
   * Get the client
   *
   * @return \Google_Client
   */
  public function getClient($scopes = NULL) {
    $this->client->addScope(\Google_Service_Drive::DRIVE);
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
    return $this->call($this->service->files, 'get', [
      $fileId,
    ]);
  }

  /**
   * Get the specified file. Assumes only one file exists with the specified
   * name.
   *
   * @param string $fileId The file id
   * @param string $folderId The folder id. Optional. If not set, looks in the
   *   root folder
   *
   * @return \Google_Service_Drive_DriveFile or null if none found
   */
  public function getFileByName($fileName, $folderId = NULL) {
    $fileName = addslashes($fileName);
    $q = "name = '$fileName'";
    if ($folderId) {
      $q .= " and '$folderId' in parents";
    }

    $fileList = $this->call($this->service->files, 'listFiles', [
      [
        'orderBy' => 'name,folder,createdTime',
        'q' => $q,
      ],
    ]);

    $files = $fileList->getFiles();
    if ($files) {
      return $files[0];
    }

    return NULL;
  }

  /**
   * Get the specified file. Assumes only one file exists with the specified
   * name.
   *
   * @param string $fileId The file id
   * @param string $folderId The folder id. Optional. If not set, looks in the
   *   root folder
   *
   * @return boolean
   */
  public function fileExists($fileName, $folderId = NULL) {
    $fileName = addslashes($fileName);
    $q = "name = '$fileName'";
    if ($folderId) {
      $q .= " and '$folderId' in parents";
    }

    $fileList = $this->call($this->service->files, 'listFiles', [
      [
        'q' => $q,
      ],
    ]);

    if ($fileList->getFiles()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Create a folder
   *
   * @param string $folderName The folder name
   * @param string $parentFolderId The parent folder id. Optional.
   *
   * @return \Google_Service_Drive_DriveFile
   */
  public function createFolder($folderName, $parentFolderId = NULL) {
    // Return if the folder already exists
    $folder = $this->getFileByName($folderName, $parentFolderId);
    if ($folder) {
      return $folder;
    }

    // Create the folder
    $fileMetadata = new \Google_Service_Drive_DriveFile([
      'name' => $folderName,
      'mimeType' => 'application/vnd.google-apps.folder',
    ]);
    $folder = $this->call($this->service->files, 'create', [
      $fileMetadata,
      ['fields' => 'id'],
    ]);

    if ($parentFolderId) {
      // Retrieve the existing parents to remove
      $folderParents = $this->call($this->service->files, 'get', [
        $folder->id,
        ['fields' => 'parents'],
      ]);
      $previousParents = join(',', $folderParents->parents);

      // Move the file to the new folder
      $emptyFileMetadata = new \Google_Service_Drive_DriveFile();
      $this->call($this->service->files, 'update', [
        $folder->id,
        $emptyFileMetadata,
        [
          'addParents' => $parentFolderId,
          'removeParents' => $previousParents,
          'fields' => 'id, parents',
        ],
      ]);
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
    $childFolderParents = $this->call($this->service->files, 'get', [
      $childFolderId,
      ['fields' => 'parents'],
    ]);
    $previousParents = join(',', $childFolderParents->parents);

    // Move the file to the new folder
    $emptyFileMetadata = new \Google_Service_Drive_DriveFile();
    $this->call($this->service->files, 'update', [
      $childFolderId,
      $emptyFileMetadata,
      [
        'addParents' => $parentFolderId,
        'removeParents' => $previousParents,
        'fields' => 'id, parents',
      ],
    ]);

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
    $fileToCopy->setParents([$destinationFolderId]);

    // Make a copy of the file
    $fileCopy = $this->call($this->service->files, 'copy', [
      $file->id,
      $fileToCopy,
    ]);

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
    $files = NULL;

    $pageToken = NULL;
    do {
      $fileList = $this->call($this->service->files, 'listFiles', [
        [
          'orderBy' => 'name,folder,createdTime',
          'pageToken' => $pageToken,
          'q' => "'$folderId' in parents",
        ],
      ]);

      $list = $fileList->getFiles();
      foreach ($list as $item) {
        $files[] = $item;
      }

      $pageToken = $fileList->getNextPageToken();
    } while ($pageToken != NULL);

    if (empty($files)) {
      return NULL;
    }

    return $files;
  }

  /**
   * Download a file and write it to a path
   *
   * @param string $fileId The file id
   * @param string $filePath Where to write the file to
   *
   * @return mixed The file contents
   */
  public function downloadFile($fileId, $filePath) {
    $response = $this->call($this->service->files, 'get', [
      $fileId,
      ['alt' => 'media'],
    ]);

    $fileDir = dirname($filePath);
    if (!file_exists($fileDir)) {
      mkdir($fileDir, $mode = 0755, $recursive = TRUE);
    }

    file_put_contents($filePath, $response->getBody()->getContents());

    return TRUE;
  }
}
