<?
include(SERVER_ROOT.'/classes/text.class.php'); // Text formatting class
$Text = new TEXT;

if (!empty($_GET['artistreleases'])) {
	$OnlyArtistReleases = true;
}

/**
 * Basic check on parameters to an AJAX call. First parameter should be ID,
 * others should be any other "filter" style arguments. Will make sure that
 * exactly ONE of ID or filter(s) is passed.
 *
 * @param $Id The ID that has been submitted
 * @param $Args Optional other arguments
 * @return true if the AJAX params are acceptable
 */
function check_parameters($Id) {
	$Args = func_get_args();
	array_shift(array); // First arg is Id
	$HasId = !empty($Id);

	foreach ($Args as $Arg) {
		if ($HasId && !empty($Arg)) {
			return false;
		} elseif (!empty($Arg)) {
			return true;
		}
	}
}
function build_filters_sql($Filters) {
	$WhereFilters = array();
	foreach ($Filters as $Column => $Match) {
		// Null values are skippable
		if ($Match === null) {
			continue;
		}
		if (is_array($Match)) {
			$ArrayFilters = array();
			foreach ($Match as $Value) {
				// Null values are skippable
				if ($Value === null) {
					continue;
				}
				if (is_string($Value)) {
					$Match = db_string(trim($Value));
					$ArrayFilters[] = "'$Match'";
				} else {
					$ArrayFilters[] = $Match;
				}
			}
			$WhereFilters[] = "$Column IN (" . implode(',', $ArrayFilters) . ')';
		} elseif (is_string($Value)) {
			$Match = db_string(trim($Match));
			$WhereFilters[] = "$Column LIKE '$Match'";
		} else {
			$WhereFilters[] = "$Column == $Match";
		}
	}
	return implode(' AND ', $WhereFilters);
}

if (!check_parameters($_  GET['id'], $_GET['UserID']), $_GET['CategoryID'], $_GET['TagList'], $_GET['Featured'])) {
	json_die("failure", "bad parameters");
}

$CollageID = $_GET['id'];
if ($CollageID && !is_number($CollageID)) {
	json_die("failure");
}

if (empty($CollageID)) {
	$DB->query("SELECT ArtistID FROM artists_alias WHERE Name LIKE '$Name'");
	if (!(list($ArtistID) = $DB->next_record(MYSQLI_NUM, false))) {
		json_die("failure");
	}
}

if (!empty($_GET['revisionid'])) { // if they're viewing an old revision
	$RevisionID=$_GET['revisionid'];
	if (!is_number($RevisionID)) {
		error(0);
	}
	$Data = $Cache->get_value("artist_$ArtistID"."_revision_$RevisionID");
} else { // viewing the live version
	$Data = $Cache->get_value('artist_'.$ArtistID);
	$RevisionID = false;
}
if ($Data) {
	list($K, list($Name, $Image, $Body, $NumSimilar, $SimilarArray, , , $VanityHouseArtist)) = each($Data);
} else {
	if ($RevisionID) {
		$sql = "
			SELECT
				a.Name,
				wiki.Image,
				wiki.body,
				a.VanityHouse
			FROM wiki_artists AS wiki
				LEFT JOIN artists_group AS a ON wiki.RevisionID=a.RevisionID
			WHERE wiki.RevisionID='$RevisionID' ";
	} else {
		$sql = "
			SELECT
				a.Name,
				wiki.Image,
				wiki.body,
				a.VanityHouse
			FROM artists_group AS a
				LEFT JOIN wiki_artists AS wiki ON wiki.RevisionID=a.RevisionID
			WHERE a.ArtistID='$ArtistID' ";
	}
	$sql .= " GROUP BY a.ArtistID";
	$DB->query($sql);

	if ($DB->record_count() == 0) {
		json_die("failure");
	}
;
	list($Name, $Image, $Body, $VanityHouseArtist) = $DB->next_record(MYSQLI_NUM, array(0));
}

if (($Importances = $Cache->get_value('artist_groups_'.$ArtistID)) === false) {
	$DB->query("
		SELECT
			DISTINCTROW ta.GroupID, ta.Importance, tg.VanityHouse, tg.Year
		FROM torrents_artists AS ta
			JOIN torrents_group AS tg ON tg.ID=ta.GroupID
		WHERE ta.ArtistID='$ArtistID'
		ORDER BY tg.Year DESC, tg.Name DESC");
	$GroupIDs = $DB->collect('GroupID');
	$Importances = $DB->to_array(false, MYSQLI_BOTH, false);
	$Cache->cache_value('artist_groups_'.$ArtistID, $Importances, 0);
} else {
	$GroupIDs = array();
	foreach ($Importances as $Group) {
		$GroupIDs[] = $Group['GroupID'];
	}
}


// Cache page for later use

if ($RevisionID) {
	$Key = "artist_$ArtistID"."_revision_$RevisionID";
} else {
	$Key = 'artist_'.$ArtistID;
}

$Data = array(array($Name, $Image, $Body, $NumSimilar, $SimilarArray, array(), array(), $VanityHouseArtist));

$Cache->cache_value($Key, $Data, 3600);

json_die("success", array(
	'id' => (int) $ArtistID,
	'name' => $Name,
	'notificationsEnabled' => $notificationsEnabled,
	'hasBookmarked' => Bookmarks::has_bookmarked('artist', $ArtistID),
	'image' => $Image,
	'body' => $Text->full_format($Body),
	'vanityHouse' => $VanityHouseArtist == 1,
	'tags' => array_values($Tags),
	'similarArtists' => $JsonSimilar,
	'statistics' => array(
		'numGroups' => $NumGroups,
		'numTorrents' => $NumTorrents,
		'numSeeders' => $NumSeeders,
		'numLeechers' => $NumLeechers,
		'numSnatches' => $NumSnatches
	),
	'torrentgroup' => $JsonTorrents,
	'requests' => $JsonRequests
));

?>
