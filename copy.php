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

// Instantiate the SQLite Database
$dbPath = __DIR__ . '/' . $config['db_path'];
$isNewDatabase = (file_exists($dbPath) == false);
$db = new \PDO("sqlite://$dbPath");
if (!$isNewDatabase) {
    $db->exec('CREATE TABLE files (id char(28))');
}

// Instantiate the Google Drive client
$drive = new \Taeram\Google\Drive($config['application_name'], __DIR__ . '/' . $config['client_secret_path'], TMP_PATH);
$drive->getClient();

// Start the copy!
recursiveCopy($sourceFolderId, $destinationFolderId);

/**
 * Recursively copy one Google Drive folder to another
 *
 * @param string $sourceFolderId The source folder id
 * @param string $destinationFolderId The destination folder id
 * @param string $parentPath The parent path. Optional. Used only during recursion.
 */
function recursiveCopy($sourceFolderId, $destinationFolderId, $parentPath = null) {
    global $drive, $config;

    $sourceFolder = $drive->getFileById($sourceFolderId);
    if (!$sourceFolder) {
        throw new \Exception("Cannot find source folder: $sourceFolderId");
    }
    $parentPath .= '/' . $sourceFolder->getName();

    // Iterate through all files in this folder
    $destinationSubFolder = null;
    $sourceFiles = $drive->findFilesInFolderById($sourceFolder->id);
    foreach ($sourceFiles as $sourceFile) {
        // Skip files we've already copied
        if (fileIdExists($sourceFile->id)) {
            echo "Exists: $parentPath/" . $sourceFile->getName() . "\n";
            continue;
        }

        $isFolder = ($sourceFile->getMimeType() == 'application/vnd.google-apps.folder');
        if ($isFolder) {
            // Iterate through all folders in this folder
            $destinationChildFolder = recursiveCopy($sourceFile->id, $destinationFolderId, $parentPath);
            if ($destinationChildFolder) {
                // Create the parent, and move the child into the parent
                $destinationSubFolder = $drive->createFolder($sourceFolder->getName(), $destinationFolderId);
                $drive->moveFolder($destinationChildFolder->id, $destinationSubFolder->id);
            }
        } else {
            // Ignore certain files by extension
            foreach ($config['ignored_file_extension_regexes'] as $regex) {
                if (preg_match("/$regex$/i", $sourceFile->getName())) {
                    echo "Ignoring: $parentPath/" . $sourceFile->getName() . "\n";
                    continue 2;
                }
            }

            // Create a destination folder
            if (!$destinationSubFolder) {
                $destinationSubFolder = $drive->createFolder($sourceFolder->getName(), $destinationFolderId);
            }

            // Make a copy of the file, and put it in the destination folder
            echo "Copying: $parentPath/" . $sourceFile->getName() . "\n";
            $drive->copyFile($sourceFile, $destinationSubFolder->id);

            // Store the file in the list
            storeFileId($sourceFile->id);
        }
    }

    return $destinationSubFolder;
}

/**
 * Does the file id exist in the database?
 *
 * @param string $fileId The file id
 *
 * @return boolean
 */
function fileIdExists($fileId) {
    global $db;

    foreach ($db->query("SELECT id FROM files WHERE id = '$fileId'") as $row) {
        return true;
    }

    return false;
}

/**
 * Store the file id in the database
 *
 * @param string $fileId The file id
 */
function storeFileId($fileId) {
    global $db;
    $statement = $db->prepare("INSERT INTO files (id) VALUES (?)");
    if (!$statement->execute(array($fileId))) {
        throw new \Exception("Cannot store file id: $fileId");
    }
}
