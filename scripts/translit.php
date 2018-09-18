<?php
/**
 * Summary about file.
 * 
 * @author 15@mail.ru
 * @version v1.0
 */


/**
 * Unzip files from zip file into temp folder.
 * 
 * @param string $zip_file_path	Path to input zip file.
 * 
 * @return string	Name of temp directory with unzipped files.
 */
function unzip($zip_file_path)
{
	$sys_temp_dir = sys_get_temp_dir();
	$unique_name = uniqid("ttl", true);
	$separator = DIRECTORY_SEPARATOR;
	
	$temp_dir = $sys_temp_dir . DIRECTORY_SEPARATOR . $unique_name;
	
	if(mkdir($temp_dir))
	{
		$zip = new ZipArchive;
		$res = $zip->open($zip_file_path);
		
		if($res == true)
		{
			if(!$zip->extractTo($temp_dir . DIRECTORY_SEPARATOR))
			{
				error_log("There was an error to extract files from the zip file: '" . $zip_file_path);
				$zip->close();
				cleanUp($temp_dir);
				return "";
			}
			$zip->close();
			return $temp_dir;
		}
		else
		{
			error_log("There was an error to open the zip file: " . $res);
		}
	}
	else
	{
		error_log("There was an error to create a temp directory.");
	}
	return "";
}


/**
 * Archive files from temp folder into zip file.
 * 
 * @param string $zip_file_path	Path to zip file.
 * @param string $temp_dir	Path to input files.
 * 
 * @return bool	Returns true on success.
 */
function zip($zip_file_path, $temp_dir)
{
	$zip = new ZipArchive;
	$res = $zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	if($res == true)
	{
		$it = new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

		foreach($files as $file)
		{
			if($file->isDir()) continue;
			
			$real_path = $file->getRealPath();
			$relative_path = substr($real_path, strlen($temp_dir) + 1);
			$zip->addFile($real_path, $relative_path);
		}
		
		if($zip->status != ZIPARCHIVE::ER_OK) error_log("There was an error to archive files.");
		
		$zip->close();
		return true;
	}
	else
	{
		error_log("There was an error to create the zip file: " . $res);
	}
	return false;
}


/**
 * Clean up temp folder.
 * 
 * @param string $path	Path to clean up.
 */
function cleanUp($path)
{
	if($path == "") return;
	
	$it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	
	foreach($files as $file)
	{
		if($file->isDir())
		{
			rmdir($file->getRealPath());
		}
		else
		{
			unlink($file->getRealPath());
		}
	}
	if(!rmdir($path)) error_log("There was an error to delete temp directory.");
}


/**
 * Find image files.
 * 
 * @param string $path	Path for look up images.
 * 
 * @return array	Array of found images.
 */
function findImages($path)
{
	$images = [];
	if($path == "") return $images;
	
	$exts = ["jpg", "jpeg", "png", "bmp", "gif"];
	
	$it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	
	foreach($files as $file)
	{
		if($file->isDir()) continue;

		$ext = mb_strtolower(pathinfo($file->getRealPath(), PATHINFO_EXTENSION));
		
		for($i = 0; $i < count($exts); ++$i)
		{
			if($exts[$i] == $ext)
			{
				$images[] = $file->getRealPath();
				break;
			}
		}
	}
	return $images;
}


/**
 * Find html files.
 * 
 * @param string $path	Path for look up html files.
 * 
 * @return array	Array of found html files.
 */
function findHtmls($path)
{
	$htmls = [];
	if($path == "") return $htmls;
	
	$it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	
	foreach($files as $file)
	{
		if($file->isDir()) continue;

		$ext = mb_strtolower(pathinfo($file->getRealPath(), PATHINFO_EXTENSION));
		
		if($ext == "html")
		{
			$content = file_get_contents($file->getRealPath());
			if($content !== false)
			{
				$start_tag = mb_stripos($content, "<img");
				if($start_tag != false)
				{
					$end_tag = mb_stripos($content, ">", $start_tag);
					if($end_tag != false)
					{
						$img_tag = mb_substr($content, $start_tag, $end_tag - $start_tag + 1);
						if(mb_stripos($img_tag, "src=") != false) $htmls[] = $file->getRealPath();
					}
				}
			}
		}
	}
	return $htmls;
}

/**
 * Transliterate name of image files and change content of html files.
 * 
 * @param array $images	Array of images to translit.
 * @param array $htmls	Array of htmls to change.
 * 
 * @return bool	Return true if function changed something.
 */
function translitFiles($images, $htmls)
{
	$res = false;
	
	$cyr  = [
		'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
		'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
	];
	
	$lat = [
		'a','b','v','g','d','e','jo','zh','z','i','j','k','l','m','n','o','p','r','s','t','u','f','kh','c','ch','sh','shh','','y','','eh','ju','ja',
		'A','B','V','G','D','E','Jo','Zh','Z','I','J','K','L','M','N','O','P','R','S','T','U','F','Kh','c','Ch','Sh','Shh','','Y','','Eh','Ju','Ja'
	];
	
	for($i = 0; $i < count($images); ++$i)
	{
		$sep_pos = mb_strripos($images[$i], DIRECTORY_SEPARATOR);
		if($sep_pos != false)
		{
			$name = mb_substr($images[$i], $sep_pos + 1);
			$found_cyr = false;
			
			for($n = 0; $n < mb_strlen($name); ++$n)
			{
				$ch = mb_substr($name, $n, 1);
				for($c = 0; $c < count($cyr); ++$c)
				{
					if($cyr[$c] == $ch)
					{
						$found_cyr = true;
						break;
					}
				}
				
				if($found_cyr) break;
			}
			
			if($found_cyr)
			{
				if(!$res) $res = true;

				$name_lat = str_replace($cyr, $lat, $name);
				$path = mb_substr($images[$i], 0, $sep_pos);

				if(file_exists($path . DIRECTORY_SEPARATOR . $name_lat))
				{
					$file_name = pathinfo($name_lat, PATHINFO_FILENAME);
					$ext = pathinfo($name_lat, PATHINFO_EXTENSION);
					$name_lat = $file_name . uniqid("ttl", false). "." . $ext;
				}
				
				for($h = 0; $h < count($htmls); ++$h)
				{
					$was_changed = false;
					$content = file_get_contents($htmls[$h]);
					if($content === false) continue;

					$start_tag = mb_stripos($content, "<img");
					while($start_tag != false)
					{
						$end_tag = mb_stripos($content, ">", $start_tag);

						if($end_tag == false) break;
						
						$img_tag = mb_substr($content, $start_tag, $end_tag - $start_tag + 1);
						$start_src = mb_stripos($img_tag, 'src="');

						if($start_src == false) break;
						
						$end_src = mb_stripos($img_tag, '"', $start_src + 5);

						if($end_src == false) break;
						
						$src = mb_substr($img_tag, $start_src + 5, $end_src - $start_src - 5);
						$name_start = mb_strpos($src, $name);

						if($name_start != false)
						{
							$content = mb_substr($content, 0, $start_tag + $start_src + 5 + $name_start) . $name_lat . mb_substr($content, $start_tag + $start_src + 5 + $name_start + mb_strlen($name));

							if(!$was_changed) $was_changed = true;
						}
						
						$start_tag = mb_stripos($content, "<img", $end_tag);
					}
					
					if($was_changed)
					{
						$res = file_put_contents($htmls[$h], $content);
						if($res === false) error_log("There was an error to write content to file: " . $htmls[$h]);
					}
				}
				$res = rename($images[$i], $path . DIRECTORY_SEPARATOR . $name_lat);
				if($res === false) error_log("There was an error to rename file: " . $htmls[$h]);
			}
		}
	}
	return $res;
}

$zip_file_name = $_FILES['arcfile']['name'];
$zip_file_path = $_FILES['arcfile']['tmp_name'];
$temp_dir = unzip($zip_file_path);
$images = findImages($temp_dir);
$htmls = findHtmls($temp_dir);

if(translitFiles($images, $htmls))
{
	if(zip($zip_file_path, $temp_dir))
	{
		header('Content-Type: application/zip');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $zip_file_name . "\""); 
		readfile($zip_file_path);
	}
	else
	{
		error_log("Zip file created.\n"); 
	}
}
cleanUp($temp_dir);
