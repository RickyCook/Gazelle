<?
// Main image proxy page
// The image proxy does not use script_start.php, its code instead resides entirely in image.php in the document root
// Bear this in mind when you try to use script_start functions.

if (!check_perms('site_proxy_images')) {
	img_error('forbidden');
}
$URL = isset($_GET['i']) ? htmlspecialchars_decode($_GET['i']) : null;

if (!extension_loaded('openssl') && strtoupper($URL[4]) == 'S') {
	img_error('badprotocol');
}

if (!preg_match('/^'.IMAGE_REGEX.'/is',$URL,$Matches)) {
	img_error('invalid');
}

if (isset($_GET['c'])) {
	list($Data,$Type) = $Cache->get_value('image_cache_'.md5($URL));
	$Cached = true;
}
if (!isset($Data) || !$Data) {
	$Cached = false;
	$Data = @file_get_contents($URL,0,stream_context_create(array('http'=>array('timeout'=>15))));
	if (!$Data || empty($Data)) {
		img_error('timeout');
	}
	$Type = image_type($Data);
	if ($Type && function_exists('imagecreatefrom'.$Type)) {
		$Image = imagecreatefromstring($Data);
		if (invisible($Image)) {
			img_error('invisible');
		}
		if (verysmall($Image)) {
			img_error('small');
		}
	}

	if (isset($_GET['c']) && strlen($Data) < 262144) {
		$Cache->cache_value('image_cache_'.md5($URL), array($Data,$Type), 3600 * 24 * 7);
	}
}

// Enforce avatar rules
if (isset($_GET['avatar'])) {
	if (!is_number($_GET['avatar'])) {
		die();
	}
	$UserID = $_GET['avatar'];

	$Height = image_height($Type, $Data);
	if (strlen($Data) > 256 * 1024 || $Height > 400) {
		// Sometimes the cached image we have isn't the actual image
		if ($Cached) {
			$Data2 = @file_get_contents($URL,0,stream_context_create(array('http'=>array('timeout'=>15))));
		} else {
			$Data2 = $Data;
		}
		if (strlen($Data2) > 256 * 1024 || image_height($Type, $Data2) > 400) {
			require_once(SERVER_ROOT.'/classes/mysql.class.php');
			require_once(SERVER_ROOT.'/classes/time.class.php'); //Require the time class
			$DBURL = db_string($URL);

			// Reset avatar, add mod note
			$UserInfo = $Cache->get_value('user_info_'.$UserID);
			$UserInfo['Avatar'] = '';
			$Cache->cache_value('user_info_'.$UserID, $UserInfo, 2592000);

			$DB->query("
				UPDATE users_info
				SET Avatar='', AdminComment=CONCAT('".sqltime()." - Avatar reset automatically (Size: ".number_format((strlen($Data)) / 1024)." kB, Height: ".$Height."px). Used to be $DBURL\n\n', AdminComment)
				WHERE UserID='$UserID'");

			// Send PM

			Misc::send_pm($UserID,0,"Your avatar has been automatically reset","The following avatar rules have been in effect for months now:

[b]Avatars must not exceed 256 kB or be vertically longer than 400px. [/b]

Your avatar at $DBURL has been found to exceed these rules. As such, it has been automatically reset. You are welcome to reinstate your avatar once it has been resized down to an acceptable size.");


		}
	}
}

/*
TODO: solve this properly for photoshop output images which prepend shit to the image file. skip it or strip it
if (!isset($Type)) {
	img_error('timeout');
}
*/
if (isset($Type)) {
	header('Content-type: image/'.$Type);
}
echo $Data;
?>
