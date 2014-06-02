<?php
/**
 * File object used to simplify operations on Files in the file system.
 *
 * @author Pontus Persson <pom@spotify.com>
 */
namespace Asenine;

class FileException extends \Exception
{}

interface iFile
{
	public function __construct($location, $size = null, $mime = null, $name = null, $hash = null);
}

class File implements iFile
{
	public $location;
	public $size;
	public $hash;
	public $extension;
	public $mime;
	public $name;


	public static function fromURL($fromURL, $toFile = null)
	{
		$d = $s = null;

		try {
			if (empty($fromURL)) {
				throw new \InvalidArgumentException('URL empty.');
			}

			if (!$toFile) {
				$toFile = tempnam(ASENINE_DIR_TEMP, 'AsenineDownload');
			}

			if (!$d = @fopen($toFile, 'w')) {
				throw new \RuntimeException(sprintf('Could not open destination "%s" for writing', $toFile));
			}

			if (!$s = @fopen($fromURL, 'r')) {
				throw new \RuntimeException(sprintf('Could not open source "%s" for reading', $fromURL));
			}

			$bufferSize = 512 * 16;

			$t = microtime(true);

			$downloadBytes = 0;

			while (($buffer = fgets($s, $bufferSize)) !== false) {
				$downloadBytes += fputs($d, $buffer);
			}

			$downloadTime = microtime(true) - $t;

			fclose($s);
			fclose($d);


			$name = basename($fromURL);
			if (strpos($name, '%') !== false) {
				$name = urldecode($name); ### If URL contains % we assume it's URL encoded.
			}


			$File = new static($toFile, filesize($toFile), null, $name);

			$File->name = $name;

			$File->downloadBytes = $downloadBytes;
			$File->downloadTime = $downloadTime;

			return $File;
		}
		catch(\Exception $e) {
			if($d) {
				fclose($d);
			}

			if($s) {
				fclose($s);
			}

			throw $e;
		}
	}

	public static function fromPHPUpload($phpfile)
	{
		switch ($phpfile['error']) {
			case UPLOAD_ERR_INI_SIZE:
				throw new \RuntimeException('Uploaded file too large for the webserver');

			case UPLOAD_ERR_NO_TMP_DIR:
				throw new \RuntimeException('No temporary storage available');
		}

		$File = new static($phpfile['tmp_name'], $phpfile['size'], $phpfile['type'], $phpfile['name']);

		return $File;
	}


	public function __construct($location = null, $size = null, $mime = null, $name = null, $hash = null)
	{
		$location = (string)$location;

		$this->location = $location;

		### File size can only be integer and must not be negative
		if (!is_null($size) && (!is_int($size) && ($size < 0 ))) {
			throw New \InvalidArgumentException(sprintf("Size must be integer and 0 or more"));
		}

		$this->hash = $hash;
		$this->size = $size ?: filesize($this->location);
		$this->mime = $mime;
		$this->name = $name ?: basename($this->location);
	}

	public function __get($key)
	{
		### Auto calculate hash and size if not available already
		switch ($key) {
			case 'hash':
				return $this->getHash();
			break;

			case 'mime':
				return $this->getMime();
			break;

			case 'size':
				return $this->getSize();
			break;
		}

		return isset($this->$key) ? $this->key : null;
	}

	public function __isset($key)
	{
		return isset($this->$key);
	}

	public function __toString()
	{
		return $this->location;
	}


	public function copy($to)
	{
		if (!copy($this->location, $to)) {
			throw new \RuntimeException(sprintf('File copy from "%s" to "%s" failed', $this->location, $to));
		}

		$File_New = clone $this;
		$File_New->location = $to;

		return $File_New;
	}

	public function delete()
	{
		if (!unlink($this->location)) {
			throw new \RuntimeException(sprintf('File delete from "%s" failed', $this->location));
		}

		return true;
	}

	public function exists()
	{
		return file_exists($this->location);
	}

	public function link($at)
	{
		if (!symlink($this->location, $at)) {
			throw new \RuntimeException(sprintf('File symlinking from "%s" to "%s" failed', $this->location, $at));
		}

		$File_Link = clone $this;
		$File_Link->location = $at;

		return $File_Link;
	}

	public function move($to)
	{
		if (!rename($this->location, $to)) {
			throw new \RuntimeException(sprintf('File move from "%s" to "%s" failed', $this->location, $to));
		}

		$this->location = $to;

		return true;
	}

	public function getContents()
	{
		return file_get_contents($this->location);
	}

	public function getExtension()
	{
		if (!isset($this->extension)) {

			$parts = explode('.', $this->name);

			if (count($parts) > 1 && strlen($ext = array_pop($parts)) < 5) {
				$this->extension = $ext;
			}
			elseif (($m = explode('/', $this->getMime())) && ($ext = end($m))) {
				$this->extension = $ext;
			}
			else {
				$this->extension = null;
			}
		}

		return $this->extension;
	}

	public function getHash()
	{
		if (is_null($this->hash)) {
			$this->hash = hash_file('sha256', $this->location, false);
		}

		return $this->hash;
	}

	public function getLocation()
	{
		return $this->location;
	}

	public function getMime()
	{
		if (is_null($this->mime) && $this->exists()) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$this->mime = finfo_file($finfo, $this->location);
			finfo_close($finfo);
		}

		return $this->mime;
	}

	public function getName()
	{
		return $this->name ?: basename($this->getLocation());
	}

	public function getSize()
	{
		if (is_null($this->size)) {
			$this->size = filesize($this->location);
		}

		return $this->size;
	}

	public function point($location)
	{
		$File = clone $this;
		$File->hash = null;
		$File->location = $location;
		return $File;
	}

	public function reads()
	{
		return is_readable($this->location);
	}

	public function sendToClient($name = null, $contentType = null)
	{
		if (!$this->exists()) {
			throw new \RuntimeException('File does not exist on disk.');
		}

		if (!$this->reads()) {
			throw new \RuntimeException('File is not readable.');
		}

		if (strlen($name) > 0) {
			$fileName = $name;
		}
		else {
			$fileName = $this->getName();
		}

		if (strlen($contentType) == 0) {
			$contentType = $this->getMime();
		}

		ini_set('zlib.output_compression', 'off');
		header('Accept-Ranges: bytes');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header('Content-Length: ' . $this->getSize());
		header('Content-Transfer-Encoding: binary');
		header('Content-Type: ' . $contentType);

		ob_clean();
		flush();

		$res = @readfile($this->getLocation());

		if (false === $res) {
			throw new \RuntimeException('File send failed.');
		}
	}

	public function writes()
	{
		return is_writeable($this->location);
	}
}