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
if ($isNewDatabase) {
    $db->exec('CREATE TABLE files (id char(28))');
}

// Instantiate the Google Drive client
$drive = new \Taeram\Google\Drive($config['application_name'], __DIR__ . '/' . $config['client_secret_path'], TMP_PATH);
$drive->getClient();

// What do all the colours mean
echo "Legend\n";
echo "=============\n";
echo colorize('light_green', " *") , " - New\n";
echo colorize('light_gray', " *") . " - Exists\n";
echo colorize('dark_gray', " *") , " - Ignored\n";
echo colorize('yellow', " R") , " - Rate Limited\n";

// Start the copy!
recursiveCopy($sourceFolderId, $destinationFolderId);

echo "\n";

/**
 * Recursively copy one Google Drive folder to another
 *
 * @param string $sourceFolderId The source folder id
 * @param string $destinationFolderId The destination folder id
 * @param string $parentPath The parent path. Optional. Used only during recursion.
 */
function recursiveCopy($sourceFolderId, $destinationFolderId, $parentPath = null) {
    global $drive, $config, $log;

    $sourceFolder = $drive->getFileById($sourceFolderId);
    if (!$sourceFolder) {
        throw new \Exception("Cannot find source folder: $sourceFolderId");
    }
    $parentPath .= '/' . $sourceFolder->getName();
    $log->addInfo("Scanning $parentPath");

    // Iterate through all files in this folder
    $sourceFiles = $drive->findFilesInFolderById($sourceFolder->id);
    if (!$sourceFiles) {
        return null;
    }

    foreach ($sourceFiles as $sourceFile) {
        // Skip files we've already copied
        if (fileIdExists($sourceFile->id)) {
            $log->addInfo("Exists: $parentPath/" . $sourceFile->getName());
            echo colorize('light_gray', "*");
            continue;
        }

        $isFolder = ($sourceFile->getMimeType() == 'application/vnd.google-apps.folder');
        if ($isFolder) {
            // Create a destination folder if it doesn't exist
            $destinationSubFolder = $drive->getFileByName($sourceFile->getName(), $destinationFolderId);
            if (!$destinationSubFolder) {
                $log->addInfo("Creating $parentPath/" . $sourceFile->getName());
                $destinationSubFolder = $drive->createFolder($sourceFile->getName(), $destinationFolderId);
            }

            recursiveCopy($sourceFile->id, $destinationSubFolder->id, $parentPath);
        } else {
            // Ignore certain files by extension
            if ($config['ignored_file_extension_regexes']) {
                foreach ($config['ignored_file_extension_regexes'] as $regex) {
                    if (preg_match("/$regex$/i", $sourceFile->getName())) {
                        $log->addInfo("Ignoring: $parentPath/" . $sourceFile->getName());
                        echo colorize('dark_gray', "*");
                        continue 2;
                    }
                }
            }

            // Make a copy of the file, and put it in the destination folder
            echo colorize('light_green', "*");
            $log->addInfo("Copying: $parentPath/" . $sourceFile->getName());
            $drive->copyFile($sourceFile, $destinationFolderId);

            // Store the file in the list
            storeFileId($sourceFile->id);
        }
    }
}

function colorize($color, $text) {
    $colors = array(
        'black' => "\033[0;30m",
        'blue' => "\033[0;34m",
        'brown' => "\033[0;33m",
        'cyan' => "\033[0;36m",
        'dark_gray' => "\033[1;30m",
        'green' => "\033[0;32m",
        'light_blue' => "\033[1;34m",
        'light_cyan' => "\033[1;36m",
        'light_gray' => "\033[0;37m",
        'light_green' => "\033[1;32m",
        'light_purple' => "\033[1;35m",
        'light_red' => "\033[1;31m",
        'purple' => "\033[0;35m",
        'red' => "\033[0;31m",
        'white' => "\033[1;37m",
        'yellow' => "\033[1;33m",
        'reset' => "\033[0m",
    );

    if (!isset($colors[$color])) {
        throw new \Exception("Invalid color: $color");
    }

    echo $colors[$color] . $text . $colors['reset'];
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
