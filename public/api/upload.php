<?php

require __DIR__ . '/../../vendor/autoload.php';

use lufinkey\GYSO;

GYSO\Manager::setMimeTypeFileSizeLimit("application/x-bittorrent", 102400);
GYSO\Manager::setMimeTypeSubdirectory("application/x-bittorrent", "torrents");
GYSO\Manager::setMimeTypeFileCheckHandler("application/x-bittorrent", function ($file_path, &$new_filename, &$error) {
    $hash = torrent_hash($file_path);
    if ($hash == null) {
        $error = "file is not a valid torrent file";
        return false;
    }
    return true;
});
GYSO\Manager::setMimeTypeOrganizePrepareHandler("application/x-bittorrent",
    function ($file_path, $media_info, &$organized_data, &$error) {
        $hash = torrent_hash($file_path);
        //TODO organize based on media info
    });


$media_info = null;
if (!isset($_POST["media_info"])) {
    $media_info = json_decode($_POST["media_info"], true);
    if ($media_info == null) {
        echo json_encode(array("result" => false, "organized_data" => array(), "error" => "invalid media_info field"),
            JSON_FORCE_OBJECT);
        exit;
    }
}
$result = false;
$error = "";
$organized_data = array();
if (isset($_FILES["file"])) {
    if (isset($_FILES["file"]["error"]) && !empty($_FILES["file"]["error"])) {
        $result = false;
        $error = "upload error: " . $_FILES["file"]["error"];
    } else {
        $result = GYSO\Manager::prepareFileForOrganizing($_FILES["file"], true, $media_info, $organized_data, $error);
    }
} else {
    if (isset($_POST["url"]) && !empty($_POST["url"])) {
        $url = $_POST["url"];
        $result = GYSO\Manager::prepareURLForOrganizing($url, $media_info, $organized_data, $error);
    } else {
        $result = false;
        $error = "you must upload a file or send a URL";
    }
}

if ($result) {
    echo json_encode(array("result" => $result, "organized_data" => $organized_data, "error" => $error));
} else {
    echo json_encode(array("result" => $result, "organized_data" => array(), "error" => $error), JSON_FORCE_OBJECT);
}
exit;

//TODO get list of files in torrent
//Attempt to identify files from media type
//echo back a list of the file matchings for the torrent, along with some ID
// for the torrent
//user can fix matches client-side and send them back, along with a confirmation
// to begin torrenting
//user can also fix matches at any point in time before torrent finishes downloading
