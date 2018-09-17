<?php
require_once __DIR__ . '/bootstrap.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if ($argc < 3) {
    echo "Usage: " . basename($argv[0]) . " <source folder URL> <destination folder URL>\n";
    exit(1);
}

// Instantiate the SQLite Database
$dbPath = __DIR__ . '/' . $config['db_path'];
$isNewDatabase = (file_exists($dbPath) == FALSE);
$db = new \PDO("sqlite://$dbPath");
if ($isNewDatabase) {
    $db->exec('CREATE TABLE files (id char(28))');
}

// Instantiate the Google Drive client
$jsonFilePath = __DIR__ . '/' . $config['client_secret_path'];
$tmpDir = __DIR__ . '/tmp';
$drive = new \Taeram\Google\Drive($jsonFilePath, $tmpDir, $config['impersonate_user']);
$drive->setLogger($log);
$drive->getClient();

// Validate the source folder url
$sourceFolderId = preg_replace('/^.*\/folders\//', '', $argv[1]);
$sourceFolder = $drive->getFileById($sourceFolderId);
if (!$sourceFolder) {
    throw new \Exception("Invalid source folder URL");
}

// Validate the destination folder url
$destinationFolderId = preg_replace('/^.*\/folders\//', '', $argv[2]);
$destinationFolder = $drive->getFileById($destinationFolderId);
if (!$destinationFolder) {
    throw new \Exception("Invalid destination folder URL");
}

// What do all the colours mean
echo "Legend\n";
echo "=============\n";
echo colorize('light_green', " *"), " - New\n";
echo colorize('light_gray', " *") . " - Exists\n";
echo colorize('dark_gray', " *"), " - Ignored\n";
echo colorize('yellow', " R"), " - Rate Limited\n";

// Get the list of all new files
$files = null;
recursiveListFiles($sourceFolderId);

// Copy the new files over to the destination folder
foreach ($files as $fileId => $destPath) {
    $sourceFile = $drive->getFileById($fileId);
    $destPath = preg_replace('/^(.+?\/)/', '/', $destPath);
    copyFile($sourceFile, $destPath, $destinationFolderId);
}

function copyFile($sourceFile, $destPath, $destinationFolderId) {
    global $drive, $log;

    $folderPath = dirname($destPath);
    $folderPathArray = [];
    $previousPath = NULL;
    foreach (explode('/', $folderPath) as $path) {
        if (empty($path)) {
            continue;
        }
        if ($previousPath !== NULL) {
            $path = $previousPath . '/' . $path;
        }
        $folderPathArray[] = $path;
        $previousPath = $path;
    }

    // Create all of the folders and sub-folders
    $subFolderId = $destinationFolderId;
    foreach ($folderPathArray as $folderPath) {
        $singleFolder = basename($folderPath);
        $destinationSubFolder = $drive->getFileByName($singleFolder, $subFolderId);
        if (!$destinationSubFolder) {
            $log->addInfo("Creating $singleFolder");
            $destinationSubFolder = $drive->createFolder($singleFolder, $subFolderId);
            $subFolderId = $destinationSubFolder->id;
        }
    }

    // Copy the file
    $drive->copyFile($sourceFile, $subFolderId);
}

/**
 * Recursively list all new files to be copied
 *
 * @param string $sourceFolderId The source folder id
 * @param string $parentPath The parent path. Used only during recursion.
 *
 * @return null
 */
function recursiveListFiles($sourceFolderId, $parentPath = NULL) {
    global $drive, $config, $log, $files;

    $sourceFolder = $drive->getFileById($sourceFolderId);
    if (!$sourceFolder) {
        throw new \Exception("Cannot find source folder: $sourceFolderId");
    }
    $parentPath .= '/' . $sourceFolder->getName();
    $log->addInfo("Scanning $parentPath");

    // Iterate through all files in this folder
    $sourceFiles = $drive->findFilesInFolderById($sourceFolder->id);
    if (!$sourceFiles) {
        return NULL;
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
            // Ignore certain file regexes
            if (preg_match('/_UNPACK_/', $sourceFile->getName())) {
                $log->addInfo("Ignoring Dir: $parentPath/" . $sourceFile->getName());
                echo colorize('dark_gray', "*");
                continue;
            }

            recursiveListFiles($sourceFile->id, $parentPath);
        }
        else {
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

            // List the new file
            echo colorize('light_green', "*");
            $log->addInfo("New: $parentPath/" . $sourceFile->getName());
            $files[$sourceFile->id] = "$parentPath/" . $sourceFile->getName();
        }
    }
}

function colorize($color, $text) {
    $colors = [
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
    ];

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
        return TRUE;
    }

    return FALSE;
}

/**
 * Store the file id in the database
 *
 * @param string $fileId The file id
 */
function storeFileId($fileId) {
    global $db;
    $statement = $db->prepare("INSERT INTO files (id) VALUES (?)");
    if (!$statement->execute([$fileId])) {
        throw new \Exception("Cannot store file id: $fileId");
    }
}
