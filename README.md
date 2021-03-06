Google Drive Bulk Copy
======================

Bulk copy an entire Google Drive folder to another location in your Google Drive.

#### Features

This script will recursively copy a source folder into a destination folder.

* Files are skipped if they match a certain pattern (see `config/config.yml` for the ignore list)
* Files are only copied to the destination *once*, meaning you can delete them from the destination and then re-run this script without re-copying the file.

#### Setup

Google Developer Console

1. Browse to the [Google Developers Console](https://console.developers.google.com)
1. Create a new project called "Google Drive Bulk Copy".
1. On the Dashboard, click "Library" in the sidebar
1. In the search box, type "Drive" and click "Google Drive API"
1. Click "Enable"
1. In the sidebar, click "Credentials".
1. Click "Create Credentials", and select "OAuth Client ID".
1. Under "Create Client ID", select "Other".
1. For "Name", type "CLI".
1. Close the dialog that pops up with the Client ID and secret. We'll download those in the next step.
1. Find your Client in the list of Client ID's, and click the Download button next to it.
1. Copy the `client_secret_1234-abcd.json` file that you download to `config/client_secret.json`.

PHP

1. You'll need at least PHP 5.4 with the php-sqlite package installed
1. Lastly, you'll need to install the Composer dependencies: `composer install`

#### Operation

1. Find the full URL to your Source and Destination folders in Google Drive:
    * e.g. https://drive.google.com/drive/u/0/folders/ABCDEFG1234567
1. Start the copy with:
    * `php copy.php https://source-folder-url http://destination-folder-url`
