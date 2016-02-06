<?php

	/*
	 * banchoWeb
	 * Prints the bancho web meme
	 */
	function banchoWeb()
	{
		echo('<pre>
           _                 __
          (_)              /  /
   ______ __ ____   ____  /  /____
  /  ___/  /  _  \/  _  \/  /  _  \
 /  /  /  /  /_) /  /_) /  /  ____/
/__/  /__/  .___/  .___/__/ \_____/
        /  /   /  /
       /__/   /__/
ripple 1.5 <u>bancho edition</u>
ripple 1.5 <u>on ripwot server</u>
ripple 1.5 <u>with less memes</u>
ripple 1.5 <u>with more features</u>
ripple 1.5 <u>free and open source</u>
ripple 1.5 <u>duck a fonkey</u>
ripple 1.5 <u><strike>(c)</strike> kwisk && phwr</u>
<marquee style="white-space:pre;">
                          .. o  .
                         o.o o . o
                        oo...
                    __[]__
    phwr-->  _\:D/_/o_o_o_|__     <span style="font-family: \'Comic Sans MS\'; font-size: 8pt;">u wot m8</span>
             \""""""""""""""/
              \ . ..  .. . /
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
</marquee>
<strike>reverse engineering a protocol impossible to reverse engineer since always</strike>
we are actually reverse engineering bancho successfully. kinda of.
</pre>');
	}

	/*
	 * outGz
	 * Outputs a gzip encoded string
	 *
	 * @param (string) ($str) Text to output
	 */
	function outGz($str) {
		echo(gzencode($str));
	}

	/*
	 * binStr
	 * Converts a string in a binary string
	 *
	 * @param (string) ($str) String
	 * @return (string) (0B+length+ASCII_STRING)
	 */
	function binStr($str) {
		$r = "";

		// Add 0B and length bytes
		$r .= "\x0B".pack("c", strlen($str));

		// Add Hex ASCII codes
		$r .= $str;

		// Return result
		return $r;
	}

	/*
	 * outputMessage
	 * Send a message to chat
	 *
	 * @param (string) ($from) From username
	 * @param (string) ($to) To username or channel
	 * @param (string) ($msg) Actual message
	 * @return (string)
	 */
	function outputMessage($from, $to, $msg)
	{
		$r = "";
		$r .= "\x07\x00\x00";
		$r .= pack("L", strlen($msg)+strlen($from)+strlen($to)+4+6);
		$r .= binStr($from);
		$r .= binStr($msg);
		$r .= binStr($to);
		$r .= pack("L", getUserOsuID($from));	// User ID
		return $r;
	}

	function outputChannel($name, $desc, $users)
	{
		$r = "";
		$r .= "\x41\x00\x00";
		$r .= pack("L", strlen($name)+strlen($desc)+2+4);
		$r .= binStr($name);
		$r .= binStr($desc);
		$r .= pack("S", 1337);
		return $r;
	}

	/*
	 * sendNotification
	 * Send a notification to client
	 * Is bugged as fuck with loooooong messages
	 * Use \\n for new line
	 *
	 * @param (string) ($msg) Notification message
	 * @return (string)
	 */
	function sendNotification($msg)
	{
		$r = "";
		$r .= "\x18\x00\x00";
		$r .= pack("L", strlen($msg)+2);
		$r .= binStr($msg);
		return $r;
	}

	// Generate a random Ripple Tatoe Token
	function generateToken()
	{
		return uniqid("rtt");
	}

	// Save a token in bancho_tokens
	function saveToken($t, $uid)
	{
		// Get latest message id, so we don't send messages sent before this user logged in
		$lm = $GLOBALS["db"]->fetch("SELECT id FROM bancho_messages ORDER BY id DESC");
		if (!$lm)
			$lm = 0;
		else
			$lm = current($lm);

		// Save token, latest action time and latest message id
		$GLOBALS["db"]->execute("INSERT INTO bancho_tokens (token, osu_id, latest_message_id, latest_packet_time, action, kicked) VALUES (?, ?, ?, ?, 0, 0)", array($t, $uid, $lm, time()));
	}

	// Delete all tokens for $uid user, except the current one ($ct)
	function deleteOldTokens($uid, $ct)
	{
		$GLOBALS["db"]->execute("DELETE FROM bancho_tokens WHERE osu_id = ? AND token != ?", array($uid, $ct));
	}

	// Get user id from token
	// Return user id if success
	// Return -1 if token not found
	function getUserIDFromToken($t)
	{
		$query = $GLOBALS["db"]->fetch("SELECT osu_id FROM bancho_tokens WHERE token = ?", array($t));
		if ($query)
			return current($query);
		else
			return -1;
	}

	// Returns an user panel packet from user id
	function userPanel($uid, $gm)
	{
		// Get mode for DB
		switch($gm)
		{
			case 0: $modeForDB = "std"; break;
			case 1: $modeForDB = "taiko"; break;
			case 2: $modeForDB = "ctb"; break;
			case 3: $modeForDB = "mania"; break;
		}

		// Get user data and stats
		$username = getUserUsername($uid);
		$userStats = $GLOBALS["db"]->fetch("SELECT * FROM users_stats WHERE username = ?", array($username));
		$userID = getUserOsuID($username);
		$userCountry = 108;

		// Unexpected copypasterino from Print.php
		// Get leaderboard with right total scores (to calculate rank)
		$leaderboard = $GLOBALS["db"]->fetchAll("SELECT osu_id FROM users_stats ORDER BY ranked_score_".$modeForDB." DESC");

		// Get all allowed users on ripple
		$allowedUsers = getAllowedUsers("osu_id");

		// Calculate rank
		$userRank = 1;
		foreach ($leaderboard as $person) {
			if ($person["osu_id"] == $userID) // We found our user. We know our rank.
				break;
			if ($person["osu_id"] != 2 && $allowedUsers[$person["osu_id"]]) // Only add 1 to the users if they are not banned and are confirmed.
				$userRank += 1;
		}

		// Total score. Should be longlong,
		// but requires 64bit PHP. Memes incoming.
		$userScore = $userStats["ranked_score_".$modeForDB];

		// Other stats
		$userPlaycount = $userStats["playcount_".$modeForDB];
		$userAccuracy = $userStats["avg_accuracy_".$modeForDB];
		$userPP = 0;	// Tillerino is sad

		// Packet start
		$output = "";
		$output .= "\x53\x00\x00";

		// 127 uint length meme thing
		$output .= pack("L", 21+strlen($username));

		// User panel data
		// User ID
		$output .= pack("L", $userID);
		// Username
		$output .= binStr($username);
		// Timezone
		$output .= "\x19";
		// Country
		$output .= pack("L", $userCountry);
		$output .= "\x00\x00\x00\x00";
		$output .= "\x00\x00";
		// Rank
		$output .= pack("L", $userRank);
		$output .= "\x0B\x00\x00\x2E\x00\x00\x00";
		$output .= pack("L", $userID);
		// Other flags
		// User status (idle, afk, playing etc)
		/*
		x00: Idle,
		x01: Afk,
		x02: Playing,
		x03: Editing,
		x04: Modding,
		x05: Multiplayer,
		x06: Watching,
		x07: Unknown,
		x08: Testing,
		x09: Submitting,
		x0A: (10) Paused,
		x0B: (11) Lobby,
		x0C: (12) Multiplaying,
		x0D: (13) OsuDirect
		*/
		$output .= pack("L", getAction($userID));
		$output .= "\x00\x00\x00";

		// Game mode
		// x00: Std
		// x01: Taiko
		// x02: Ctb
		// x03: Mania
		$output .= pack("c", $gm);
		$output .= "\x00\x00\x00\x01";
		// Score
		$output .= pack("L", $userScore);
		$output .= "\x00\x00\x00\x00";
		// Accuracy (0.1337 = 13,37%)
		$output .= pack("f", $userAccuracy/100);
		// Playcount
		$output .= pack("L", $userPlaycount);
		// Level progress (will add this later)
		$output .= "\x00\x00\x00\x00";
		$output .= "\x00\x00\x00\x00";
		// Rank
		$output .= pack("L", $userRank);
		// PP
		$output .= pack("S", $userPP);

		// Return the packet
		return $output;
	}

	function getAction($uid)
	{
		return current($GLOBALS["db"]->fetch("SELECT action FROM bancho_tokens WHERE osu_id = ?", array($uid)));
	}

	function setAction($uid, $a)
	{
		return current($GLOBALS["db"]->execute("UPDATE bancho_tokens SET action = ? WHERE osu_id = ?", array($a, $uid)));
	}

	// Not used
	/*function updateLatestActionTime($uid)
	{
		// Add token check there
		$GLOBALS["db"]->execute("UPDATE bancho_tokens SET latest_action_time = ? WHERE osu_id = ?", array(time(), $uid));
	}

	function getLatestActionTime($uid)
	{
		$q = $GLOBALS["db"]->fetch("SELECT latest_action_time FROM bancho_tokens WHERE osu_id = ?", array($uid));
		if($q)
			return current($q);
		else
			return 0;
	}*/

	// Set $uid's message id to $mid
	function updateLatestMessageID($uid, $mid)
	{
		$GLOBALS["db"]->execute("UPDATE bancho_tokens SET latest_message_id = ? WHERE osu_id = ?", array($mid, $uid));
	}

	// Get user latest message id
	function getLatestMessageID($uid)
	{
		return current($GLOBALS["db"]->fetch("SELECT latest_message_id FROM bancho_tokens WHERE osu_id = ?", array($uid)));
	}

	// Return all the unreceived messages for a user
	// Get everything sent after the latest message
	// Ignore his own messages
	function getUnreceivedMessages($uid)
	{
		return $GLOBALS["db"]->fetchAll("SELECT * FROM bancho_messages WHERE id > ? AND msg_from_userid != ?", array(getLatestMessageID($uid), $uid));
	}

	// Adds a message to DB
	function addMessageToDB($fuid, $to, $msg)
	{
		$GLOBALS["db"]->execute("INSERT INTO bancho_messages (`msg_from_userid`, `msg_from_username`, `msg_to`, `msg`, `time`) VALUES (?, ?, ?, ?, ?)", array($fuid, getUserUsername($fuid), $to, $msg, time()));
	}

	// Reads a binary string.
	// Works with messages, might not work with other packets
	// $s is the input packet, $start is the position of \x0B
	function readBinStr($s, $start)
	{
		// Make sure this is a string
		if($s[0][$start] != "\x0B")
			return false;

		/* Check if length is 10 (\x0A, new line char)
		if($s[0][$start+1] == "\x0A")
		{
			$start = -2;	// fuck php
			$source = $s[1];
		}
		else
		{
			$source = $s[0];
		}*/

		$source = $s[0];
		$str = "";
		$i = $start+2;
		while(isset($source[$i]) && $source[$i] != "\x0B")
		{
			// Read characters until a new \x0B (new string) or packet end
			$str .= $source[$i];
			$i++;
		}

		// Return the string
		return $str;
	}

	function fokaBotCommands($f, $c, $m)
	{
		switch($m)
		{
			// Faq commands
			case checkSubStr($m, "!faq rules"): addMessageToDB(999, $c, "Please make sure to check (Ripple's rules)[http://ripple.moe/?p=23]."); break;
			case checkSubStr($m, "!faq swearing"): addMessageToDB(999, $c, "Please don't abuse swearing."); break;
			case checkSubStr($m, "!faq spam"): addMessageToDB(999, $c, "Please don't spam."); break;
			case checkSubStr($m, "!faq offend"): addMessageToDB(999, $c, "Please don't offend other players."); break;
			case checkSubStr($m, "!report"): addMessageToDB(999, $c, "Report command is not here yet."); break;
			case checkSubStr($m, "!roll"):
			{
				// !roll command
				// Explode message
				$m = explode(" ", $m);

				// Get command parameters
				if (isset($m[1]) && intval($m[1]))
					$max = $m[1];
				else
					$max = 100;

				// Generate number
				if ($max > PHP_INT_MAX)
					$num = "youareanidiot";
				else
					$num = rand(0, $max);

				// Output
				addMessageToDB(999, $c, $f." rolls ".$num." points!");
			}
			break;

			case checkSubStr($m, "!silence"):
			{
				try
				{
					// Make sure we are an admin
					if (!checkAdmin($f))
						throw new Exception("Plz no akerino.");

					// Explode message
					$m = explode(" ", $m);

					// Check command parameters count
					if (count($m) < 4)
						throw new Exception("Invalid syntax. Syntax: !silence <username> <count> <unit (s/m/h/d)> <reason>");

					// Get command parameters
					$who = $m[1];
					$num = $m[2];
					$unit = $m[3];
					$reason = implode(" ", array_slice($m, 4));

					// Make sure the user exists
					if (!checkUserExists($who))
						throw New Exception("Invalid user");

					// Get unit (s/m/h/d)
					switch($unit)
					{
						case 's': $base = 1; break;
						case 'm': $base = 60; break;
						case 'h': $base = 3600; break;
						case 'd': $base = 86400; break;
						default: $base = 1; break;
					}

					// Calculate silence end time
					$end = $num*$base;

					// Make sure the user has lower rank than us
					if (getUserRank($who) >= getUserRank($f))
						throw new Exception("You can't silence that user.");

					// Silence and kick user
					silenceUser(getUserOsuID($who), time()+$end, $reason);
					kickUser(getUserOsuID($who));

					// Send FokaBot message
					throw New Exception($who." has been silenced for the following reason: ".$reason);
				}
				catch (Exception $e)
				{
					addMessageToDB(999, $c, $e->getMessage());
				}
			}
			break;

			case checkSubStr($m, "!kick"):
			{
				try
				{
					// Make sure we are an admin
					if (!checkAdmin($f))
						throw new Exception("Pls no akerino");

					// Explode message
					$m = explode(" ", $m);

					// Check parameter count
					if (count($m) < 2)
						throw new Exception("Invalid syntax. Syntax: !kick <username>");

					// Get command parameters
					$who = $m[1];

					// Make sure the user exists
					if (!checkUserExists($who))
						throw new Exception("Invalid user.");

					// Make sure the user has lower rank than us
					if (getUserRank($who) >= getUserRank($f))
						throw new Exception("You can't kick that user.");

					// Kick client
					kickUser(getUserOsuID($who));

					// User kicked!
					throw new Exception($who." has been kicked from the server.");
				}
				catch (Exception $e)
				{
					addMessageToDB(999, $c, $e->getMessage());
				}
			}
			break;

			case checkSubStr($m, "!moderated on"):
			{
				// Admin only command
				if (checkAdmin($f))
				{
					// Enable moderated mode
					setChannelStatus($c, 2);
					addMessageToDB(999, $c, "This channel is now in moderated mode!");
				}
			}
			break;

			case checkSubStr($m, "!moderated off"):
			{
				// Admin only command
				if (checkAdmin($f))
				{
					// Disable moderated mode
					setChannelStatus($c, 1);
					addMessageToDB(999, $c, "This channel is no longer in moderated mode!");
				}
			}
			break;
		}
	}

	// Channel mode:
	// 0: doesn't exists
	// 1: normal
	// 2: moderated
	function getChannelStatus($c)
	{
		// Make sure the channel exists
		$q = $GLOBALS["db"]->fetch("SELECT status FROM bancho_channels WHERE name = ?", array($c));

		// Return channel status
		if ($q)
			return current($q);
		else
			return 0;
	}

	function setChannelStatus($c, $s)
	{
		$GLOBALS["db"]->execute("UPDATE bancho_channels SET status = ? WHERE name = ?", array($s, $c));
	}

	function checkKicked($t)
	{
		$q = $GLOBALS["db"]->fetch("SELECT kicked FROM bancho_tokens WHERE token = ?", array($t));
		if (!$q)
			return false;
		else
			return (bool)current($q);
	}

	function getSilenceEnd($uid)
	{
		return current($GLOBALS["db"]->fetch("SELECT silence_end FROM users WHERE osu_id = ?", array($uid)));
	}

	function silenceUser($uid, $se, $sr)
	{
		$GLOBALS["db"]->execute("UPDATE users SET silence_end = ?, silence_reason = ? WHERE osu_id = ?", array($se, $sr, $uid));
	}

	function isSlienced($uid)
	{
		if (getSilenceEnd($uid) <= time())
			return false;
		else
			return true;
	}

	function kickUser($uid)
	{
		// Make sure the token exists
		$q = $GLOBALS["db"]->fetch("SELECT id FROM bancho_tokens WHERE osu_id = ?", array($uid));

		// Kick if token found
		if ($q)
			$GLOBALS["db"]->execute("UPDATE bancho_tokens SET kicked = 1 WHERE osu_id = ?", array($uid));
	}

	function checkUserExists($u)
	{
		return $GLOBALS["db"]->fetch("SELECT id FROM users WHERE username = ?", $u);
	}

	function checkSpam($uid)
	{
		$q = $GLOBALS["db"]->fetch("SELECT COUNT(*) FROM bancho_messages WHERE msg_from_userid = ? AND time >= ? AND time <= ?", array($uid, time()-10, time()) );
		if ($q)
		{
			if (current($q) >= 7)
				return true;
			else
				return false;
		}
		else
		{
			return false;
		}
	}

	function updateLatestPacketTime($uid, $t)
	{
		// Make sure the token exists
		$q = $GLOBALS["db"]->fetch("SELECT id FROM bancho_tokens WHERE osu_id = ?", array($uid));

		// If the token exists, update latest packet time
		if ($q)
			$GLOBALS["db"]->execute("UPDATE bancho_tokens SET latest_packet_time = ? WHERE osu_id = ?", array($t, $uid));
	}

	function joinChannel($u, $chan)
	{
		try
		{
			// Make sure the channel exists
			if (!channelExists($chan))
				throw new Exception($chan." channel doesn't exists");

			// Make sure the channel is public or we are admin
			if (isChannelPublicRead($chan) == 0 && getUserRank($u) < 3)
				throw new Exception("You are not allowed to join ".$chan);

			// Channel exists and is public read, join it
			$output = "";
			$output .= "\x40\x00\x00";
			$output .= pack("L", strlen($chan)+2);
			$output .= binStr($chan);
			return $output;
		}
		catch (Exception $e)
		{
			return outputMessage("FokaBot", $u, $e->getMessage());
		}
	}

	function isChannelPublicWrite($c)
	{
		// Check if channel exists
		$q = $GLOBALS["db"]->fetch("SELECT public_write FROM bancho_channels WHERE name = ?", array($c));
		if ($q)
			return current($q);	// Return public write value
		else
			return 0;			// Doesn't exist, no write thing
	}

	function isChannelPublicRead($c)
	{
		// Check if channel exists
		$q = $GLOBALS["db"]->fetch("SELECT public_read FROM bancho_channels WHERE name = ?", array($c));
		if ($q)
			return current($q);	// Return public read value
		else
			return 0;			// Doesn't exist, no read thing
	}

	function channelExists($c)
	{
		return $GLOBALS["db"]->fetch("SELECT id FROM bancho_channels WHERE name = ?", array($c));
	}

	/*
	 * banchoServer
	 * Main bancho """server""" function
	 */
	function banchoServer()
	{
		// Can't output before headers
		// We don't care about cho-token right now
		// because we handle only the login packets

		// Global variables
		$token = "";

		// Generate token if first packet
		if(!isset($_SERVER["HTTP_OSU_TOKEN"]))
		{
			// We don't have a token, generate it
			$token = generateToken();
			header("cho-token: ".$token);
		}
		else
		{
			// We have a token, use it
			$token = $_SERVER["HTTP_OSU_TOKEN"];
			header("cho-token: ".$_SERVER["HTTP_OSU_TOKEN"]);
		}

		header("cho-protocol: 19");
		header("Keep-Alive: timeout=5, max=100");
		header("Connection: Keep-Alive");
		header("Content-Type: text/html; charset=UTF-8");
		header("Vary: Accept-Encoding");
		header("Content-Encoding: gzip");

		// Check maintenance
		if (checkBanchoMaintenance())
		{
			$output = "";
			$output .= sendNotification("Ripple's Bancho server is in manitenance mode.\\nCheck http://ripple.moe/ for more information.");
			$output .= "\x05\x00\x00\x04\x00\x00\x00\xFF\xFF\xFF\xFF";
			outGz($output);
			die();
		}

		// Check kick
		if(isset($_SERVER["HTTP_OSU_TOKEN"]) && checkKicked($_SERVER["HTTP_OSU_TOKEN"]))
		{
			$output = "";
			$output .= sendNotification("You have been kicked from the server. Please login again.");
			$output .= "\x05\x00\x00\x04\x00\x00\x00\xFF\xFF\xFF\xFF";
			outGz($output);
			die();
		}

		// Get data
		// and fuck php, seriously.
		if(!isset($_SERVER["HTTP_OSU_TOKEN"]))
			$data = file('php://input');
		else
			$data = str_split(str_replace("\x0A", "\x00", file_get_contents('php://input')), 512);

		// Check if this is the first packet
		if(!isset($_SERVER["HTTP_OSU_TOKEN"]))
		{
			try
			{
				// Get provided username and password.
				// We need to remove last character because it's new line
				// Fuck php
				$username = substr($data[0], 0, -1);
				$password = substr($data[1], 0, -1);

				// Check user/password
				if (!checkOsuUser($username, $password)) {
					throw new Exception("\xFF");
				}

				// Ban check
				if (current($GLOBALS["db"]->fetch("SELECT allowed FROM users WHERE username = ?", array($username))) == '0') {
					throw new Exception("\xFC");
				}
			}
			catch (Exception $e)
			{
				// Login failed
				// xFF: Login failed
				// xFE: Need update
				// xFC: Banned (CLIENT WILL BE LOCKED)
				// xFB: Error (use for maintenance and stuff)
				// xFA: Need supporter (wtf)
				outGz("\x05\x00\x00\x04\x00\x00\x00".$e->getMessage()."\xFF\xFF\xFF");
				die();
			}

			// Username, password and allowed are ok
			// Update latest activity
			updateLatestActivity($username);

			// Get user data and stats
			$userData = $GLOBALS["db"]->fetch("SELECT * FROM users WHERE username = ?", array($username));
			$userStats = $GLOBALS["db"]->fetch("SELECT * FROM users_stats WHERE username = ?", array($username));

			// Get user id
			$userID = $userData["osu_id"];

			// Delete old token (if exist) and save the new one
			saveToken($token, $userID);
			deleteOldTokens($userID, $token);

			// Big meme here. Username is case-insensitive
			// but if we type it with wrong uppercase thing
			// there are memes in the userpanel. We don't
			// want it. Get the right username.
			$username = getUserUsername($userID);

			// Get silence time
			$silenceTime = getSilenceEnd($userID)-time();

			// Reset silence time if silence ended
			if ($silenceTime < 0)
				$silenceTime = 0;

			// Set variables
			// Supporter/GMT
			// x01: Normal (no supporter)
			// x02: GMT
			// x04: Supporter
			// x06: GMT + Supporter
			if (current($GLOBALS["db"]->fetch("SELECT value_int FROM bancho_settings WHERE name = 'free_direct'")) == 1)
				$defaultDirect = "\x04";
			else
				$defaultDirect = "\x01";
			$userSupporter = getUserRank($username) >= 3 ? "\x06" : $defaultDirect;

			// Output variable because multiple outGz are bugged.
			$output = "";

			// Standard stuff (login OK, lock client, memes etc)
			$output .= "\x5C\x00\x00\x04";
			$output .= "\x00\x00\x00";
			$output .= pack("L", $silenceTime);
			$output .= "\x05\x00\x00\x04\x00\x00\x00";
			// User ID
			$output .= pack("L", $userID);
			// More standard stuff
			$output .= "\x4B\x00\x00\x04\x00\x00\x00\x13\x00\x00\x00\x47\x00\x00";

			// Supporter/QAT/Friends stuff
			$output .= "\x04\x00\x00\x00".$userSupporter."\x00\x00\x00";

			// Online Friends
			/*$output .= "\x48\x00\x00\x0A\x00\x00\x00\x02\x00";
			$output .= pack("L", 100);
			$output .= pack("L", 100);
			$output .= "";*/

			// Output user panel stuff
			$output .= userPanel($userID, 0);

			// Old Online users info
			// Packet start
			/*$output .= "\x53\x00\x00";
			// Something related to name length,
			// if not correct user won't be shown
			$output .= pack("L", 21+strlen("FokaBot"));
			// User ID
			$output .= pack("L", 999);
			// Username
			$output .= binstr("FokaBot");
			// Other flags
			$output .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";*/


			// Required memes
			$output .= "\x60\x00\x00\x0A\x00\x00\x00\x02\x00\x00\x00\x00\x00";
			$output .= pack("L", $userID);
			$output .= "\x59\x00\x00\x04\x00\x00\x00\x00\x00\x00\x00";

			// Channel join
			$output .= joinChannel($username, "#osu");

			// Channels info packets
			$channels = $GLOBALS["db"]->fetchAll("SELECT * FROM bancho_channels");
			foreach ($channels as $channel) {
				$output .= outputChannel($channel["name"], $channel["description"], 1337);
			}

			// Default login messages
			$messages = current($GLOBALS["db"]->fetch("SELECT value_string FROM bancho_settings WHERE name = 'login_messages'"));
			if ($messages != "")
			{
				$messages = explode("\r\n", $messages);
				foreach ($messages as $message) {
					$messageData = explode('|', $message);
					$output .= outputMessage($messageData[0], "#osu", $messageData[1]);
				}
			}

			// Restricted meme message
			if (current($GLOBALS["db"]->fetch("SELECT value_int FROM bancho_settings WHERE name = 'restricted_joke'")) == 1)
				$output .= outputMessage("FokaBot", $username, "Your account is currently in restricted mode. Just kidding xd WOOOOOOOOOOOOOOOOOOOOOOO");

			// Login notification
			$msg = current($GLOBALS["db"]->fetch("SELECT value_string FROM bancho_settings WHERE name = 'login_notification'"));
			if ($msg != "")
				$output .= sendNotification($msg);

			/* Add some memes
			$output .= outputMessage("BanchoBot", $username, "Wtf? Who is FokaBot? Someone is trying to take my place? I'll restrict his account, give me a minute...", true);
			$output .= outputMessage("peppy", $username, "Fuck a donkey.", true);
			$output .= outputMessage("Loctav", $username, "So you are playing on ripple? I'll restrict your osu! account. Fuck you.", true);
			$output .= outputMessage("Cookiezi", $username, "ㅋㅋㅋㅋㅋ", true);
			$output .= outputMessage("Tillerino", $username, "Hello, I'm Tillerino, the PP wizard. Unfortunately this bot and PPs don't exist on Ripple yet :(", true);

			// #osu memes
			$output .= outputMessage("peppy", "#osu", "Who the fuck is FokaaBot?", false);
			$output .= outputMessage("BanchoBot", "#osu", "Peppy-sama!! He's trying replace me!", false);
			$output .= outputMessage("FokaBot", "#osu", "Che schifo peppy xd", false);
			$output .= outputMessage("peppy", "#osu", "!moderated on", false);
			$output .= outputMessage("BanchoBot", "#osu", "Moderated mode activated!", false);
			$output .= outputMessage("peppy", "#osu", "Fucktards.", false);*/

			// Output everything
			outGz($output);
		}
		else
		{
			// Other packets
			$output = "";

			// Get memes
			$userID = getUserIDFromToken($token);
			$username = getUserUsername($userID);

			// Check if user has sent a message (packet starts with \x01\x00\x00)
			// if so, add it to DB
			if ($data[0][0] == "\x01" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
				// Get message and channel
				$msg = readBinStr($data, 9);
				$channel = substr(readBinStr($data, 9+2+strlen($msg)), 0, -4);

				// Check channel statusand silence
				$isAdmin = checkAdmin($username);
				if ((getChannelStatus($channel) == 1 && !isSlienced($userID)) || $isAdmin)
				{
					// Check public meme
					if (isChannelPublicWrite($channel) == 1 || $isAdmin)
					{
						// Channel is not in moderated mode and we are not silenced, or we are admin
						if (strlen($msg) > 0)
						{
							addMessageToDB($userID, $channel, $msg);

							// Check if this message has triggered a fokabot command
							fokaBotCommands($username, $channel, $msg);

							// Anti spam
							if (checkSpam($userID))
							{
								addMessageToDB(999, $channel, $username." has been silenced (FokaBot spam protection)");
								silenceUser($userID, time()+300, "Spamming (FokaBot spam protection)");
								kickUser($userID);
							}
						}
					}
					else
					{
						$output .= outputMessage("FokaBot", $channel, "You can't talk in this channel.");
					}
				}
			}

			// Send updated userpanel if we've submitted a score
			// or we have changed our gamemode
			// and set our action to idle
			// (packet starts with \x00\x00\x00\x0E\x00\x00\x00)
			if ($data[0][0] == "\x00" && $data[0][1] == "\x00" && $data[0][2] == "\x00" && $data[0][3] == "\x0E" && $data[0][4] == "\x00" && $data[0][5] == "\x00" && $data[0][6] == "\x00")
			{
				$gameMode = intval(unpack("C",$data[0][16])[1]);
				$output .= userPanel($userID, $gameMode);
				setAction($userID, 0);
			}

			// Output unreceived messages if needed
			$messages = getUnreceivedMessages($userID);
			$last = 0;
			if ($messages)
			{
				foreach ($messages as $message) {
					$output .= outputMessage($message["msg_from_username"], $message["msg_to"], $message["msg"]);
					$last = $message["id"];
				}
			}

			// If we have received some messages, update our latest message ID
			if ($last != 0)
				updateLatestMessageID($userID, $last);

			// Output online users if needed
			if ($data[0][0] == "\x55" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
				$onlineUsers = $GLOBALS["db"]->fetchAll("SELECT osu_id FROM bancho_tokens WHERE kicked = 0 AND latest_packet_time >= ? AND latest_packet_time <= ? OR osu_id = 999", array(time()-120, time()));
				//$onlineUsers = $GLOBALS["db"]->fetchAll("SELECT osu_id FROM users WHERE allowed = 1");
				foreach ($onlineUsers as $user)
					$output .= userPanel($user["osu_id"], 0);
			}

			// Update our action if needed
			if ($data[0][0] == "\x00" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
				// Get new action
				$action = intval(unpack("C",$data[0][7])[1]);
				//$action = intval($data[0][7]);
				setAction($userID, $action);
			}

			// Channel list
			if ($data[0][0] == "\x55" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
				// Channels info packets
				$channels = $GLOBALS["db"]->fetchAll("SELECT * FROM bancho_channels");
				foreach ($channels as $channel) {
					$output .= outputChannel($channel["name"], $channel["description"], 1337);
				}
			}

			// Channel join
			if ($data[0][0] == "\x3F" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
				$channel = readBinStr($data, 7);
				$output .= joinChannel($username, $channel);
			}

			/* Channel part
			if ($data[0][0] == "\x4E" && $data[0][1] == "\x00" && $data[0][2] == "\x00")
			{
			}*/

			// Update latest packet time
			updateLatestPacketTime($userID, time());


			// Main menu icon
			/*$icon = current($GLOBALS["db"]->fetch("SELECT value_string FROM bancho_settings WHERE name = 'menu_icon'"));
			if ($icon != "")
			{
				$output .= "\x4C\x00\x00\x3D\x00\x00\x00";
				$output .= binStr($icon);
			}*/

			// Output everything
			outGz($output);
		}
	}
?>
