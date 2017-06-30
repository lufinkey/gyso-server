<?php

namespace lufinkey\GYSO;

class Tools
{
    public static function getGFOSToolsPath()
    {
        return __DIR__ . "/../../tools";
    }

    public static function magnet_to_torrent($magnet_url, $output_dir, &$hash)
    {
        $tools_path = self::getGFOSToolsPath();
        $hash_str = exec(escapeshellarg($tools_path . "/magnet-to-torrent") . ' ' . escapeshellarg($magnet_url) . ' ' . escapeshellarg($output_dir) . " -t 60");
        if (strlen($hash_str) == 0) {
            return false;
        }
        if ($hash != null) {
            $hash = $hash_str;
        }
        return true;
    }

    public static function torrent_hash($torrent_path)
    {
        $tools_path = self::getGFOSToolsPath();
        $hash = exec(escapeshellarg($tools_path . "/torrent-hash") . ' ' . escapeshellarg($torrent_path));
        if (strlen($hash) == 0) {
            return null;
        }
        return $hash;
    }

    public static function torrent_contents($torrent_path)
    {
        $tools_path = self::getGFOSToolsPath();
        $output = array();
        exec(escapeshellarg($tools_path . "/torrent-contents") . ' ' . escapeshellarg($torrent_path), $output);
        $contents = array();
        for ($i = 0; $i < count($output); $i++) {
            $matches = null;
            if (preg_match("/^([0-9\\.]+) \\- (.*)$/", $output[$i], $matches) == 1) {
                array_push($contents, array("name" => $matches[2], "size" => $matches[1]));
            }
        }
        return $contents;
    }
}