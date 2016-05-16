<?php

require_once("GFOSManager.php");

if(isset($_FILES["file"]))
{
	if(isset($_FILES["file"]["error"]) && !empty($_FILES["file"]["error"]))
	{
		echo json_encode(array("result" => false, "error"=>"upload error: ".$_FILES["file"]["error"]));
		exit;
	}
	$result = GFOSManager::organizeFile($_FILES["file"], true, $error);
	echo json_encode(array("result" => $result, "error"=>$error));
	exit;
}
else if(isset($_POST["url"]) && !empty($_POST["url"]))
{
	$url = $_POST["url"];
	$result = GFOSManager::organizeFromURL($url, $error);
	echo json_encode(array("result" => $result, "error"=>$error));
	exit;
}

echo json_encode(array("result" => false, "error" => "you must upload a file or send a url"));
exit;

//TODO get list of files in torrent
//Attempt to identify files from media type
//echo back a list of the file matchings for the torrent, along with some ID
// for the torrent
//user can fix matches client-side and send them back, along with a confirmation
// to begin torrenting
//user can also fix matches at any point in time before torrent finishes downloading

?>
