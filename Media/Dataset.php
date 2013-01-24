<?
namespace Asenine\Media;

use \Asenine\DB as DB;

class Dataset
{
	public static function getDescriptionByType($type)
	{
		$types = self::getTypes();
		return isset($types[$type]) ? $types[$type] : $type;
	}

	public static function getData($mediaID)
	{
		$query = DB::prepareQuery("SELECT * FROM asenine_media WHERE ID = %u", $mediaID);
		return DB::queryAndFetchOne($query);
	}

	public static function getFileOriginalName($mediaID)
	{
		$query = DB::prepareQuery("SELECT file_original_name FROM asenine_media WHERE id = %u", $mediaID);
		return DB::queryAndFetchOne($query);
	}

	public static function getHashFromID($mediaID)
	{
		$query = DB::prepareQuery("SELECT f.hash FROM asenine_media m JOIN asenine_files f ON f.id = m.file_id WHERE m.id = %u", $mediaID);
		return DB::queryAndFetchOne($query);
	}

	public static function getIDFromHash($mediaHash)
	{
		$query = DB::prepareQuery("SELECT m.id FROM asenine_media m JOIN asenine_files f ON f.id = m.file_id WHERE f.hash = %s", $mediaHash);
		return DB::queryAndFetchOne($query);
	}

	public static function getPlugins()
	{
		$plugins = array();

		$pluginPath = realpath(__DIR__ . '/Type');

		if (false === $pluginPath) {
			return $plugins;
		}

		$pluginPath .= '/';

		$pluginFiles = glob($pluginPath . '*.php');

		foreach ($pluginFiles as $pluginFile) {

			if (!preg_match('/\/([A-Za-z0-9]+)\.php/u', $pluginFile, $className)) {
				continue;
			}

			$className = '\\Asenine\\Media\\Type\\' . $className[1];

			if (class_exists($className)) {
				$plugins[] = $className;
			}
		}

		return $plugins;
	}

	public static function getSpreadByHash($mediaHash)
	{
		### This should not be enforced at this level, so has been commented out at 2012-02-24 12:26
		#if( strlen($mediaHash) !== 32 ) throw New \Exception("Media Hash Length not 32 chars (\"$mediaHash\")");

		if( strlen($mediaHash) == 0 ) throw New \Exception(__METHOD__ . ' arg# 1 must have length > 0');

		if( !defined('ASENINE_DIR_MEDIA') || !is_dir(ASENINE_DIR_MEDIA) ) throw New \Exception("DIR_MEDIA not defined or not valid dir");

		$cmd = sprintf('$(which find) %s -name %s | sort', \escapeshellarg(ASENINE_DIR_MEDIA), \escapeshellarg($mediaHash));
		$res = shell_exec($cmd);
		$arr = explode("\n", $res);
		$arr = array_filter($arr);
		$arr = preg_grep('%' . ASENINE_DIR_MEDIA . '%', $arr, PREG_GREP_INVERT); // Remove source media file from list

		foreach ($arr as $index => $filename) {
			if (!is_file($filename)) {
				unset($arr[$index]);
			}
		}

		return $arr;
	}

	public static function getSpreadByID($mediaID)
	{
		return self::getSpreadByHash(self::getHashFromID($mediaID));
	}

	public static function getTypes()
	{
		static $pluginNames;

		if (!isset($pluginNames)) {

			$pluginNames = array();

			foreach (self::getPlugins() as $className) {
				$pluginNames[$className::TYPE] = $className::DESCRIPTION;
			}

			asort($pluginNames);
		}

		return $pluginNames;
	}
}