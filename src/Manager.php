<?php

namespace lufinkey\GYSO;

class Manager
{
    private static $mime_types = array();

    public static $max_file_size = 524288000; //500 mb
    public static $tmp_path = "/tmp/gfos-server";
    public static $downloads_path = "/var/lib/gfos-server";

    private static function &getMimeTypeInfo($mime_type)
    {
        if (isset(self::$mime_types[$mime_type])) {
            return self::$mime_types[$mime_type];
        }
        $mime_type_info = array(
            "max_size" => null,
            "disabled" => false,
            "subdir" => str_replace('/', '_', $mime_type),
            "filecheck_handler" => null,
            "organize_prepare_handler" => null
        );
        self::$mime_types[$mime_type] = $mime_type_info;
        return self::$mime_types[$mime_type];
    }

    public static function setMimeTypeFileSizeLimit($mime_type, $max_bytes)
    {
        self::getMimeTypeInfo($mime_type)["max_size"] = $max_bytes;
    }

    public static function getMimeTypeFileSizeLimit($mime_type)
    {
        if (!isset($mime_type)) {
            return self::$max_file_size;
        }
        $max_size = self::$mime_types[$mime_type]["max_size"];
        if ($max_size == null) {
            return self::$max_file_size;
        }
        return $max_size;
    }

    public static function setMimeTypeSubdirectory($mime_type, $sub_directory)
    {
        self::getMimeTypeInfo($mime_type)["subdir"] = $sub_directory;
    }

    public static function getMimeTypeSubdirectory($mime_type)
    {
        if (!isset(Manager::$mime_types[$mime_type])) {
            return str_replace('/', '_', $mime_type);
        }
        return Manager::$mime_types[$mime_type];
    }

    public static function setMimeTypeDisabled($mime_type, $disabled)
    {
        self::getMimeTypeInfo($mime_type)["disabled"] = $disabled;
    }

    //handler signature: bool($filepath, &$new_filename, &$error)
    public static function setMimeTypeFileCheckHandler($mime_type, $handler)
    {
        self::getMimeTypeInfo($mime_type)["filecheck_handler"] = $handler;
    }

    //handler signature: void($file_path, $media_info, &$organized_data, &$error)
    public static function setMimeTypeOrganizePrepareHandler($mime_type, $handler)
    {
        self::getMimeTypeInfo($mime_type)["organize_prepare_handler"] = $handler;
    }

    //takes an array from $_FILES, or one formatted in the same way.
    //pass true to $uploaded_file if the file is from $_FILES
    public static function prepareFileForOrganizing($_file, $uploaded_file, $media_info, &$organized_data, &$error)
    {
        $tmp_file_path = $_file["tmp_name"];
        $mime_type = exec("file -b --mime-type " . escapeshellarg($tmp_file_path));
        if (!isset(Manager::$mime_types[$mime_type])) {
            $error = "unsupported mime-type";
            return false;
        }
        $mime_type_info = Manager::$mime_types[$mime_type];
        if ($mime_type_info["disabled"] == null) {
            $error = "mime-type has been explicitly disabled";
            return false;
        } else {
            if ($mime_type_info["organize_prepare_handler"] == null) {
                $error = "no handler set for mime-type";
                return false;
            }
        }

        if ($mime_type_info["max_size"] != null) {
            if ($_file["size"] > $mime_type_info["max_size"]) {
                $error = "file exceeds max size for mime-type";
                return false;
            }
        } else {
            if ($_file["size"] > self::$max_file_size) {
                $error = "file exceeds max size";
                return false;
            }
        }

        $new_file_name = "";
        if ($mime_type_info["filecheck_handler"] != null) {
            $filecheck_handler = $mime_type_info["filecheck_handler"];
            if (!$filecheck_handler($tmp_file_path, $new_file_name, $error)) {
                return false;
            }
        } else {
            $new_file_name = uniqid("", true);
        }
        $downloads_dir = self::$downloads_path . '/' . $mime_type_info["subdir"];
        if (!mkdir($downloads_dir, 0777, true)) {
            $error = "failed to create downloads directory for mime-type";
            return false;
        }
        $new_file_path = $downloads_dir . '/' . $new_file_name;
        if ($uploaded_file) {
            if (!move_uploaded_file($tmp_file_path, $new_file_path)) {
                $error = "failed to move uploaded file";
                return false;
            }
        } else {
            if (!rename($tmp_file_path, $new_file_path)) {
                $error = "failed to move file";
                return false;
            }
        }

        $organize_prepare_handler = $mime_type_info["organize_prepare_handler"];
        return $organize_prepare_handler($new_file_path, $media_info, $organized_data, $error);
    }

    public static function prepareURLForOrganizing($url, $media_info, &$organized_data, &$error)
    {
        if (preg_match("/^(\w+)\:\\/\\/.*$/", $url, $matches) == 1) {
            $protocol = $matches[1];
            if ($protocol == "http" || $protocol == "https") {
                $head = array_change_key_case(get_headers($url, 1));
                $mime_type = $head["content-type"];
                if (!isset(Manager::$mime_types[$mime_type])) {
                    $error = "unsupported mime-type";
                    return false;
                }
                $mime_type_info = Manager::$mime_types[$mime_type];
                if ($mime_type_info["disabled"] == null) {
                    $error = "mime-type has been explicitly disabled";
                    return false;
                } else {
                    if ($mime_type_info["organize_prepare_handler"] == null) {
                        $error = "no handler set for mime-type";
                        return false;
                    }
                }

                if ($mime_type_info["max_size"] != null) {
                    $max_bytes = $mime_type_info["max_size"];
                } else {
                    $max_bytes = self::$max_file_size;
                }
                if (isset($head["content-length"]) && $head["content-length"] > $max_bytes) {
                    $error = "file exceeds max size";
                    return false;
                }

                if ($mime_type == "application/x-bittorrent") {
                    $download_folder = self::$tmp_path . "/downloads";
                    mkdir($download_folder, 0777, true);
                    $tmp_file_path = tempnam($download_folder, "torrent_");
                    if (!$remote_file = fopen($url, 'rb')) {
                        $error = "unable to open download to remote file";
                        return false;
                    }
                    if (!$local_file = fopen($tmp_file_path, 'wb')) {
                        $error = "unable to write remote file to filesystem";
                        return false;
                    }
                    $downloaded_bytes = 0;
                    while (!feof($remote_file)) {
                        $chunk = fread($remote_file, 6000);
                        $downloaded_bytes += strlen($chunk);
                        if ($downloaded_bytes > $max_bytes) {
                            fclose($remote_file);
                            fclose($local_file);
                            unlink($tmp_file_path);
                            $error = "remote file exceeds max file size";
                            return false;
                        }
                        fwrite($local_file, $chunk);
                    }
                    fclose($remote_file);
                    fclose($local_file);
                    $_file = array(
                        "name" => basename($tmp_file_path),
                        "type" => $mime_type,
                        "size" => $downloaded_bytes,
                        "tmp_name" => $tmp_file_path
                    );
                    $result = self::prepareFileForOrganizing($_file, false, $media_info, $organized_data, $error);
                    if (!$result) {
                        unlink($tmp_file_path);
                    }
                    return $result;
                } else {
                    $error = "URL must be a torrent or magnet link";
                    return false;
                }
            } else {
                $error = "unsupported URL protocol";
                return false;
            }
        } else {
            if (preg_match("/^magnet:\?xt=urn:(btih|sha1):([a-zA-Z0-9])+.*$/", $url, $matches) == 1) {
                $download_folder = self::$tmp_path . "/magnet-to-torrent";
                mkdir($download_folder, 01777, true);
                if (!magnet_to_torrent($url, $download_folder, $hash)) {
                    $error = "aria2c encountered an error while trying to download the torrent metadata";
                    return false;
                }
                $tmp_file_path = $download_folder . '/' . $hash . ".torrent";
                $_file = array(
                    "name" => basename($tmp_file_path),
                    "type" => $mime_type,
                    "size" => $downloaded_bytes,
                    "tmp_name" => $tmp_file_path
                );
                $result = self::prepareFileForOrganizing($_file, false, $media_info, $organized_data, $error);
                if (!$result) {
                    unlink($tmp_file_path);
                }
                return $result;
            } else {
                $error = "invalid URL";
                return false;
            }
        }
    }
}
