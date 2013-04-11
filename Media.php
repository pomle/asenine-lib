<?
namespace Asenine;

use \Asenine\DB as DB;

class MediaException extends \Exception
{}

interface iMedia
{
	public static function canHandleFile($filePath);
	public function __construct($mediaHash = null, File $File = null);
	public function getInfo();
}

abstract class Media implements iMedia
{
	protected
		$File;

	public
		$mediaID,
		$mediaHash,
		$mimeType,
		$fileOriginalName;


	public static function createFromFile(File $File)
	{
		if( !$File->reads() )
		{
			throw new MediaException("File not readable: \"" . $File . "\"");
			return false;
		}

		if( !static::canHandleFile($File) )
		{
			throw new MediaException(get_called_class() . " can not handle file: \"" . $File . "\"");
			return false;
		}

		$mediaHash = $File->hash;

		$Media = new static($mediaHash, $File);
		$Media->mimeType = $File->mime;

		return $Media;
	}

	final public static function createFromFilename($filename, $mime = null)
	{
		return self::createFromFile( new File($filename, null, null, $mime) );
	}

	final public static function createFromHash($mediaHash)
	{
		$filePath = DIR_MEDIA_SOURCE . $mediaHash;
		return new static($mediaHash, new File($filePath) );
	}

	public static function createFromType($type, $mediaHash, File $File)
	{
		if( strlen($type) == 0 )
		{
			#trigger_error(__METHOD__ . ' requires argument #1 to be non-zero length string', E_USER_WARNING);
			return false;
		}

		$classPath = '\\Asenine\\Media\\Type\\' . ucfirst($type);

		if( class_exists($classPath) )
			return new $classPath($mediaHash, $File);

		return false;
	}

	public static function integrateIntoLibrary(self $Media)
	{
		$query = DB::prepareQuery("SELECT
				f.id AS file_id,
				m.id AS media_id
			FROM
				asenine_files f
				LEFT JOIN asenine_media m ON m.file_id = f.ID
			WHERE
				f.hash = %s",
			$Media->File->getHash());

		$Archiver = new Archiver(ASENINE_DIR_ARCHIVE_SOURCE);

		/* Reuse File and/or Media from Library if already existing */
		if($media = DB::queryAndFetchOne($query))
		{
			/* If media already exists, return existing media */
			if($media['media_id']) {
				return self::loadFromDB($media['media_id']);
			}

			/* If only file exists, replace file with library file */
			$Media->File = File::loadFromDB($Archiver, $media['file_id']);
		}
		else
		{
			$Archiver->putFile($Media->File);
			File::saveToDB($Media->File);
		}

		if(!$Media->File->fileID)
			throw new MediaException('Media Integration failed, file ID missing');

		self::saveToDB($Media);

		return $Media;
	}

	public static function loadByHash($mediaHash)
	{
		return static::loadFromDB(Media\Dataset::getIDFromHash($mediaHash));
	}

	public static function loadFromDB($mediaIDs)
	{
		if( !$returnArray = is_array($mediaIDs) )
			$mediaIDs = (array)$mediaIDs;

		$medias = array_fill_keys($mediaIDs, false);

		$query = DB::prepareQuery("SELECT
				m.id AS media_id,
				f.id AS file_id,
				m.time_created,
				m.time_modified,
				m.media_type,
				f.hash AS file_hash,
				f.size AS file_size,
				f.mime AS mime_type,
				f.ext AS extension
			FROM
				asenine_media m
				JOIN asenine_files f ON f.id = m.file_id
			WHERE
				m.id IN %a",
			$mediaIDs);

		$result = DB::queryAndFetchResult($query);

		$Archiver = new Archiver(ASENINE_DIR_ARCHIVE_SOURCE);

		while($media = DB::assoc($result))
		{
			try
			{
				$mediaID = (int)$media['media_id'];

				$File = new File(
					$Archiver->getFileLocation($media['file_hash']),
					(int)$media['file_size'] ?: null,
					$media['mime_type'],
					sprintf('Media_ID_%d.%s', $mediaID, $media['extension']),
					$media['file_hash']
				);

				$File->fileID = (int)$media['file_id'];

				if( !$Media = self::createFromType($media['media_type'], $media['file_hash'], $File) )
					$Media = new \Asenine\Media\Type\Defunct($media['file_hash'], $File); ### Fallback to Defunct type

				$Media->mimeType = $media['mime_type'];
				$Media->mediaID = $mediaID;

				$medias[$Media->mediaID] = $Media;
			}
			catch(\Exception $e)
			{
				if( DEBUG )
					trigger_error(sprintf("Could not instantiate Media with ID %d, %s", $mediaID, $e->getMessage()), E_USER_WARNING);
			}
		}

		$medias = array_filter($medias);

		return $returnArray ? $medias : reset($medias);
	}

	public static function removeFromDB(self $Media, $forceDBDelete = false)
	{
		$skipSourceDelete = false;
		$skipDBDelete = false;

		### Collect all autogenerated material and delete it
		$files = Media\Dataset::getSpreadByHash($Media->mediaHash);
		foreach($files as $file)
		{
			if( is_file($file) || !is_writable($file) || !unlink($file) )
			{
				throw new \Exception("File \"$file\" was found but could not be removed");
			}
		}

		### Only remove source file if all autogenerated files could be deleted
		if( $skipSourceDelete === false )
		{
			$sourceFile = $Media->getFilePath();
			if( file_exists($sourceFile) && ( !is_writable($sourceFile) || @!unlink($sourceFile) ) )
			{
				throw new \Exception("Source file \"$sourceFile\" was found but could not be removed");

				### Only delete DB row if source file could be deleted to avoid stray files
				$skipDBDelete = true;
			}
		}

		### Notice that DB skip can be overridden
		if( $skipDBDelete === false || $forceDBDelete === true )
		{
			$query = DB::prepareQuery("DELETE FROM asenine_media WHERE id = %u", $Media->mediaID);
			DB::queryAndCountAffected($query);

			return true;
		}

		return false;
	}

	public static function saveToDB(self $Media)
	{
		$timeCreated = $timeModified = time();
		$mediaType = isset($Media->type) ? $Media->type : $Media::TYPE;

		if(!isset($Media->mediaID))
		{
			$query = DB::prepareQuery("INSERT INTO
				asenine_media (
					file_id,
					time_created,
					time_modified,
					media_type
				) VALUES(
					%d,
					%d,
					%d,
					%s)",
				$Media->File->fileID,
				$timeCreated,
				$timeModified,
				$mediaType);

			$Media->mediaID = (int)DB::queryAndGetID($query, 'asenine_media_id_seq');
			$Media->timeCreated = $timeCreated;
		}
		else
		{
			$query = DB::prepareQuery("UPDATE
					asenine_media
				SET
					time_modified = %u,
					media_type = %s
				WHERE
					id = %d",
				$timeModified,
				$mediaType,
				$Media->mediaID);

			DB::query($query);
		}

		$Media->timeModified = $timeModified;
	}


	public function __construct($mediaHash = null, File $File = null)
	{
		#if( strlen($mediaHash) !== 32 ) trigger_error(__METHOD__ . ' expects argument 1 to be string of exact length 32', E_USER_ERROR);
		$this->mediaHash = $mediaHash;
		$this->File = $File;
	}

	final public function __get($key)
	{
		return $this->$key;
	}

	final public function __isset($key)
	{
		return isset($this->$key);
	}

	final public function __toString()
	{
		return $this->mediaHash;
	}


	final public function getFile()
	{
		return $this->File;
	}

	final public function getFilePath()
	{
		return (string)$this->File; ### $File::__toString() provides $File->location and if null will be ""
	}

	final public function getMediaHash()
	{
		return $this->mediaHash;
	}

	final public function isFileValid()
	{
		return static::canHandleFile($this->filePath);
	}
}