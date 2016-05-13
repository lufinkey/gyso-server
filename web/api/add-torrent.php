<?php

$webroot = $_SERVER["DOCUMENT_ROOT"];
require_once($webroot."/../config.php");
mkdir("/tmp/".Config::$APP_NAME, 01777);
if(!empty($_FILES["file"]))
{
	$tmp_file_path = $_FILES["file"]["tmp_name"];
	$mime_type = exec("file -b --mime-type ".escapeshellarg($tmp_file_path));
	if($mime_type!="application/x-bittorrent")
	{
		echo '{"success":false,"error":"File is not a valid torrent file"}';
		exit;
	}
	$hash = strtoupper(exec(escapeshellarg($webroot."/../tools/torrent-hash")." ".escapeshellarg($tmp_file_path)));
	if(strlen($hash)==0)
	{
		echo '{"success":false,"error":"File is not a valid torrent file"}';
		exit;
	}
	//TODO check if file is already downloading
	mkdir("/tmp/".Config::$APP_NAME, 01777, true);
	if(!move_uploaded_file($tmp_file_name, "/tmp/torrent-server/".$hash.".torrent"))
	{
		http_response_code(500);
		echo '{"success":false,"error":"The server could not move the uploaded file"}';
		exit;
	}
}
else if(isset($_POST["url"]))
{
	$url = $_POST["url"];
	if(preg_match("/^(\w+)\:\\/\\/.*$/", $url, $matches)==1)
	{
		$protocol = $matches[0];
		if($protocol=="http" || $protocol=="https")
		{
			//TODO attempt to download the torrent and add it to
			//the /tmp/torrents-server directory
		}
		else
		{
			//TODO invalid url
		}
	}
	//else if magnet link
	else
	{
		//TODO invalid URL
	}
}

//TODO get list of files in torrent
//Attempt to identify files from media type
//echo back a list of the file matchings for the torrent, along with some ID
// for the torrent
//user can fix matches client-side and send them back, along with a confirmation
// to begin torrenting
//user can also fix matches at any point in time before torrent finishes downloading

?>
