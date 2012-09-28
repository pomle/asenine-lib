<?
namespace Asenine\UserGroup;

use \Asenine\DB;

class Dataset
{
	public static function getAvailable()
	{
		$query = "SELECT ID FROM Asenine_UserGroups ORDER BY name ASC";
		return DB::queryAndFetchArray($query);
	}

	public static function getIDsFromLabel($userGroupLabel)
	{
		$query = DB::prepareQuery("SELECT ID FROM Asenine_UserGroups WHERE label = %s", $userGroupLabel);
		return DB::queryAndFetchOne($query);
	}

	public static function getProperties($userGroupID)
	{
		$query = DB::prepareQuery("SELECT * FROM Asenine_UserGroups WHERE ID = %u", $userGroupID);
		return DB::queryAndFetchOne($query);
	}

	public static function getUserIDs($userGroupIDs)
	{
		$query = DB::prepareQuery("SELECT userID FROM Asenine_UserGroupUsers WHERE userGroupID IN %a", $userGroupIDs);
		return DB::queryAndFetchArray($query);
	}

	public static function getUserIDsFromLabel($userGroupLabel)
	{
		$userGroupIDs = self::getIDsFromLabel($userGroupLabel);
		$userIDs = self::getUserIDs($userGroupIDs);
		return $userIDs;
	}
}