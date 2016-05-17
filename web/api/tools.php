<?php

function getGFOSToolsPath()
{
	return __DIR__."/../../tools";
}

function magnet_to_torrent($magnet_url, $output_dir, &$hash)
{
	$tools_path = getGFOSToolsPath();
	$hash_str = exec(escapeshellarg($tools_path."/magnet-to-torrent").' '.escapeshellarg($magnet_url).' '.escapeshellarg($output_dir)." -t 60");
	if(strlen($hash_str)==0)
	{
		return false;
	}
	if($hash!=null)
	{
		$hash = $hash_str;
	}
	return true;
}

function torrent_hash($torrent_path)
{
	$tools_path = getGFOSToolsPath();
	$hash = exec(escapeshellarg($tools_path."/torrent-hash").' '.escapeshellarg($torrent_path));
	if(strlen($hash)==0)
	{
		return null;
	}
	return $hash;
}

function torrent_contents($torrent_path)
{
	$tools_path = getGFOSToolsPath();
	$output = array();
	exec(escapeshellarg($tools_path."/torrent-contents").' '.escapeshellarg($torrent_path), $output);
	return $output;
}

?>
