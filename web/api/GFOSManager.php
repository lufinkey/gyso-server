<?php

class GFOSManager
{
	private static $mime_types = array();

	public static $max_file_size = 524288000; //500 mb
	public static $tmp_path = "/tmp/gfos-server";
	public static $downloads_path = "~/Downloads/gfos-server";
	public static $tools_path = ""; //defined at the bottom of the class

	private static &getMimeTypeInfo($mime_type)
	{
		if(isset(GFOSManager::$mime_types[$mime_type]))
		{
			return GFOSManager::$mime_types[$mime_type];
		}
		$mime_type_info = array("max_size" => null,
					"disabled" => false,
					"subdir" => str_replace('/', '_', $mime_type),
					"filecheck_handler" => null,
					"organize_handler" => null);
		GFOSManager::$mime_types[$mime_type] = $mime_type_info;
		return GFOSManager::$mime_types[$mime_type];
	}

	private static expand_tilde($path)
	{
		if(function_exists("posix_getuid") && strpos($path, '~')!==false)
		{
			$info = posix_getpwuid(posix_getuid());
			$path = str_replace('~', $info["dir"], $path);
		}
		return $path;
	}

	private static str_startsWith($haystack, $needle)
	{
		if(strlen($needle)==0)
		{
			return true;
		}
		else if(strlen($haystack)>=strlen($needle))
		{
			if(substr($haystack,0,strlen($needle))==$needle)
			{
				return true;
			}
		}
		return false;
	}

	private static fix_path($path_var)
	{
		$path_var = GFOSManager::expand_tilde($path_var);
		if(GFOSManager::str_startsWith($path_var,'/'))
		{
			return $path_var;
		}
		else if($path_var=='.')
		{
			return __DIR__;
		}
		else if($path_var=='..')
		{
			return __DIR__."/..";
		}
		else if(GFOSManager::str_startsWith($path_var,"./"))
		{
			return __DIR__.substr($path_var,1,strlen($path_var)-1);
		}
		else if(GFOSManager::str_startsWith($path_var,"../"))
		{
			return __DIR__."/..".substr($path_var,2,strlen($path_var)-2);
		}
		return $path_var;
	}

	public static setMimeTypeFileSizeLimit($mime_type, $max_bytes)
	{
		self::getMimeTypeInfo($mime_type)["max_size"] = $max_bytes;
	}

	public static getMimeTypeFileSizeLimit($mime_type)
	{
		if(!isset(GFOSManager::$mime_type))
		{
			return $max_file_size;
		}
		$max_size = GFOSManager::$mime_types[$mime_type]["max_size"];
		if($max_size==null)
		{
			return $max_file_size;
		}
		return $max_size;
	}

	public static setMimeTypeSubdirectory($mime_type, $subdir_name)
	{
		self::getMimeTypeInfo($mime_type)["subdir"] = $subdir_name;
	}

	public static getMimeTypeSubdirectory($mime_type)
	{
		if(!isset(GFOSManager::$mime_types[$mime_type]))
		{
			return str_replace('/', '_', $mime_type);
		}
		return GFOSManager::$mime_types[$mime_type];
	}

	public static setMimeTypeDisabled($mime_type, $disabled)
	{
		self::getMimeTypeInfo($mime_type)["disabled"] = $disabled;
	}

	//handler signature: bool($filepath, &$new_filename, &$error)
	public static setMimeTypeFileCheckHandler($mime_type, $handler)
	{
		self::getMimeTypeInfo($mime_type)["filecheck_handler"] = $handler;
	}

	//handler signature: void($file_path)
	public static setMimeTypeOrganizeHandler($mime_type, $handler)
	{
		self::getMimeTypeInfo($mime_type)["organize_handler"] = $handler;
	}

	//takes an array from $_FILES, or one formatted in the same way.
	//pass true to $uploaded_file if the file is from $_FILES
	public static organizeFile($_file, $uploaded_file, &$error)
	{
		$tmp_file_path = $_file["tmp_name"];
		$mime_type = exec("file -b --mime-type ".escapeshellarg($tmp_file_path));
		if(!isset(GFOSManager::$mime_types[$mime_type]))
		{
			$error = "unsupported mime-type";
			return false;
		}
		$mime_type_info = GFOSManager::$mime_types[$mime_type];
		if($mime_type_info["disabled"]==null)
		{
			$error = "mime-type has been explicitly disabled";
			return false;
		}
		else if($mime_type_info["organize_handler"]==null)
		{
			$error = "no handler set for mime-type";
			return false;
		}
		
		if($mime_type_info["max_size"]!=null)
		{
			if($_file["size"] > $mime_type_info["max_size"])
			{
				$error = "file exceeds max size for mime-type";
				return false;
			}
		}
		else if($_file["size"] > self::$max_file_size)
		{
			$error = "file exceeds max size";
			return false;
		}

		$new_file_name = "";
		if($mime_type_info["filecheck_handler"]!=null)
		{
			$filecheck_handler = $mime_type_info["filecheck_handler"];
			if(!$filecheck_handler($tmp_file_path, $new_file_name, $error))
			{
				return false;
			}
		}
		else
		{
			$new_file_name = uniqid("", true);
		}
		$downloads_dir = GFOSManager::fix_path(self::$downloads_path).'/'.$mime_type_info["subdir"];
		if(!mkdir($downloads_dir, 01777, true))
		{
			$error = "failed to create downloads directory for mime-type";
			return false;
		}
		$new_file_path = $downloads_dir.'/'.$new_file_name;
		if($uploaded_file)
		{
			if(!move_uploaded_file($tmp_file_path, $new_file_path))
			{
				$error = "failed to move uploaded file";
				return false;
			}
		}
		else
		{
			if(!rename($tmp_file_path, $new_file_path))
			{
				$error = "failed to move file";
				return false;
			}
		}
		
		$organize_handler = $mime_type_info["organize_handler"];
		$organize_handler($new_file_path);
		return true;
	}

	public static organizeFromURL($url, &$error)
	{
		if(preg_match("/^(\w+)\:\\/\\/.*$/", $url, $matches)==1)
		{
			$protocol = $matches[1];
			if($protocol=="http" || $protocol=="https")
			{
				$head = array_change_key_case(get_headers($url, 1));
				$mime_type = $head["content-type"];
				if(!isset(GFOSManager::$mime_types[$mime_type]))
				{
					$error = "unsupported mime-type";
					return false;
				}
				$mime_type_info = GFOSManager::$mime_types[$mime_type];
				if($mime_type_info["disabled"]==null)
				{
					$error = "mime-type has been explicitly disabled";
					return false;
				}
				else if($mime_type_info["organize_handler"]==null)
				{
					$error = "no handler set for mime-type";
					return false;
				}
				
				$max_bytes = 0;
				if($mime_type_info["max_size"]!=null)
				{
					$max_bytes = $mime_type_info["max_size"];
				}
				else
				{
					$max_bytes = self::$max_file_size;
				}
				if(isset($head["content-length"]) && $head["content-length"] > $max_bytes)
				{
					$error = "file exceeds max size";
					return false;
				}
				
				if($mime_type=="application/x-bittorrent")
				{
					$download_folder = GFOSManager::fix_path(self::$tmp_dir)."/downloads";
					mkdir($download_folder, 01777, true);
					$tmp_file_path = tempnam($download_folder, "torrent_");
					if(!$remote_file = fopen($url, 'rb'))
					{
						$error = "unable to open download to remote file";
						return false;
					}
					if(!$local_file = fopen($tmp_file_path, 'wb'))
					{
						$error = "unable to write remote file to filesystem";
						return false;
					}
					$downloaded_bytes = 0;
					while(!feof($remote_file))
					{
						$chunk = fread($remote_file, 6000);
						$downloaded_bytes += strlen($chunk);
						if($downloaded_bytes > $max_size)
						{
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
					$_file = array("name" => basename($tmp_file_path),
							"type" => $mime_type,
							"size" => $downloaded_bytes,
							"tmp_name" => $tmp_file_path);
					$result = self::organizeFile($_file, false, $error);
					if(!$result)
					{
						unlink($tmp_file_path);
					}
					return $result;
				}
				else
				{
					//TODO add other file types to some DownloadManager
					$error = "file uploading is not supported at this time";
					return false;
				}
			}
			else
			{
				$error = "unsupported URL protocol";
				return false;
			}
		}
		else if(preg_match("/^magnet:\?xt=urn:(btih|sha1):([a-zA-Z0-9])+.*$/", $url, $matches)==1)
		{
			$download_folder = GFOSManager::fix_path(self::$tmp_path)."/torrents";
			mkdir($download_folder, 01777, true);
			$hash = exec(self::$tools_path."/magnet-to-torrent ".escapeshellarg($url)." ".escapeshellarg($download_folder)." -t 60");
			if(strlen($hash)==0)
			{
				$error = "aria2c encountered an error while trying to download the torrent metadata";
				return false;
			}
			$tmp_file_path = $download_folder.'/'.$hash.".torrent";
			$_file = array("name" => basename($tmp_file_path),
					"type" => $mime_type,
					"size" => $downloaded_bytes,
					"tmp_name" => $tmp_file_path);
			$result = self::organizeFile($_file, false, $error);
			if(!$result)
			{
				unlink($tmp_file_path);
			}
			return $result;
		}
		else
		{
			$error = "invalid URL"));
			return false;
		}
	}
}
GFOSManager::$tools_path = __DIR__."/../../tools";

GFOSManager::setMimeTypeFileCheckHandler("application/x-bittorrent", function($filepath, &$new_filename, &$error){
	$hash = exec(escapeshellarg(GFOSManager::$tools_path."/torrent-hash").' '.escapeshellarg($tmp_file_path));
	if(strlen($hash)==0)
	{
		$error = "file is not a valid torrent file";
		return false;
	}
	return true;
});

?>
