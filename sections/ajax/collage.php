<?
include(SERVER_ROOT.'/classes/text.class.php'); // Text formatting class
$Text = new TEXT;

if (empty($_GET['id'])) {
	json_die("failure", "bad parameters");
}

$CollageID = $_GET['id'];
if ($CollageID && !is_number($CollageID)) {
	json_die("failure");
}

$CacheKey = "collage_$CollageID";
$Data = $Cache->get_value($CacheKey);
if ($Data) {
	list($K, list($Name, $Description, $TorrentGroups, $Subscribers, $CommentList, $Deleted, $CollageCategoryID, $CreatorID, $Locked, $MaxGroups, $MaxGroupsPerUser, $Updated)) = each($Data);
} else {
	$sql = "
		SELECT
			Name,
			Description,
			UserID,
			Deleted,
			CategoryID,
			Locked,
			MaxGroups,
			MaxGroupsPerUser,
			Subscribers
		FROM collages
		WHERE ID = '$CollageID'";
	$DB->query($sql);

	if (!$DB->has_results()) {
		json_die("failure");
	}

	list($Name, $Description, $CreatorID, $Deleted, $CollageCategoryID, $Locked, $MaxGroups, $MaxGroupsPerUser, $Subscribers) = $DB->next_record();
}

// Populate data that wasn't included in the cache
if (is_null($TorrentGroups) || is_number($TorrentGroups)) {
	$sql = "
		SELECT
			GroupID
		FROM collages_torrents
		WHERE CollageID = $CollageID";
	$DB->query($sql);
	$TorrentGroups = $DB->collect('GroupID');
}
if (is_null($Subscribers)) {
	$sql = "
		SELECT
			Subscribers
		FROM collages
		WHERE ID = $CollageID";
	$DB->query($sql);
	list($Subscribers) = $DB->next_record();
}

$Cache->cache_value($CacheKey, array(array($Name, $Description, $TorrentGroups, $Subscribers, $CommentList, $Deleted, $CollageCategoryID, $CreatorID, $Locked, $MaxGroups, $MaxGroupsPerUser)), 3600);

json_die("success", array(
	'id' => (int) $CollageID,
	'name' => $Name,
	'description' => $Text->full_format($Description),
	'creatorID' => (int) $CreatorID,
	'deleted' => (bool) $Deleted,
	'collageCategoryID' => (int) $CollageCategoryID,
	'locked' => (bool) $Locked,
	'categoryID' => (int) $CategoryID,
	'maxGroups' => (int) $MaxGroups,
	'maxGroupsPerUser' => (int) $MaxGroupsPerUser,
	'hasBookmarked' => Bookmarks::has_bookmarked('collage', $CollageID),
	'subscriberCount' => (int) $Subscribers,
	'torrentGroupIDList' => $TorrentGroups,
));

?>
