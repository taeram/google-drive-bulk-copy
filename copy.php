<?php
require_once __DIR__ . '/bootstrap.php';

if ($argc < 3) {
    echo "Usage: " . basename($argv[0]) . " <source folder URL> <destination folder URL>\n";
    exit(1);
}

// Validate the source folder url
$sourceFolderId = preg_replace('/^.*\/folders\//', '', $argv[1]);
if (strlen($sourceFolderId) != 28) {
    throw new \Exception("Invalid source folder URL");
}

// Validate the destination folder url
$destinationFolderId = preg_replace('/^.*\/folders\//', '', $argv[2]);
if (strlen($destinationFolderId) != 28) {
    throw new \Exception("Invalid destination folder URL");
}

// Instantiate the Google Drive client
$drive = new \Taeram\Google\Drive($config['application_name'], __DIR__ . '/' . $config['client_secret_path'], TMP_PATH);
$drive->getClient();

recursiveCopy($sourceFolderId, $destinationFolderId);

function recursiveCopy($sourceFolderId, $destinationFolderId) {
    global $drive, $config;

    $sourceFolder = $drive->getFileById($sourceFolderId);
    if (!$sourceFolder) {
        throw new \Exception("Cannot find source folder: $sourceFolderId");
    }

    // Create a destination folder if it doesn't exist
    $destinationSubFolder = $drive->getFileByName($sourceFolder->getName(), $destinationFolderId);
    if (!$destinationSubFolder) {
        echo "Creating Destination Sub Folder: " . $sourceFolder->getName() . "\n";
        $destinationSubFolder = $drive->createFolder($sourceFolder->getName(), $destinationFolderId);
    }

    // Iterate through all files in this folder
    $sourceFiles = $drive->findFilesInFolderById($sourceFolder->id);
    foreach ($sourceFiles as $sourceFile) {
        $isFolder = ($sourceFile->getMimeType() == 'application/vnd.google-apps.folder');
        if (!$isFolder) {
            // Ignore certain files by extension
            foreach ($config['ignored_file_extension_regexes'] as $regex) {
                if (preg_match("/\.$regex$/i", $sourceFile->getName())) {
                    echo "Ignoring: " . $sourceFile->getName() . "\n";
                    continue 2;
                }
            }

            // Skip the file if it already exists in the destination
            $destinationFile = $drive->getFileByName($sourceFile->getName(), $destinationSubFolder->id);
            if ($destinationFile && !$destinationFile->getTrashed()) {
                if ($destinationFile->getSize() == $sourceFile->getSize()) {
                    echo "Skipping duplicate: " . $destinationFile->getName() . "\n";
                    continue;
                }

                echo "Trashing partial file: " . $destinationFile->getName() . "\n";
                $destinationFile->setTrashed(true);
            }

            // Make a copy of the file, and put it in the destination folder
            echo "Copying: " . $sourceFile->getName() . "\n";
            $drive->copyFile($sourceFile, $destinationSubFolder->id);
        }
    }
}
