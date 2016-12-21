<?php
function getMoveFactor($posx, $posy, $img){
	// If vehicle is off the page then don't move
	if ($posx < 0 || $posx >= imagesx($img)
		|| $posy < 0 || $posy >= imagesy($img)){
		$factor = 0;
	}
	else {
		// Black will stop vehicle, white will allow it to continue
		$rgb = imagecolorat($img, $posx, $posy);
		$r = ($rgb >> 16) & 0xFF;
		$g = ($rgb >> 8) & 0xFF;
		$b = $rgb & 0xFF;
		
		$factor = (double)(0.2126*$r + 0.7152*$g + 0.0722*$b) / 255.0;	
	}
	
	return $factor;
}

$conn = new mysqli("localhost", "root", "", "racetrack");
if (!$conn){
	die ($conn->connect_error());
}
// Check player in game
if ($stmt = $conn->prepare("SELECT EXISTS(SELECT * FROM players WHERE IP = ? AND GameID = ? AND Crashed = 0) as res")){
	$stmt->bind_param("si", $_SERVER["REMOTE_ADDR"], $_POST["id"]);
	$stmt->execute();
	
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	if (!$row["res"]){
		die ("Player not in game or crashed");
	}
}

// If there are POST parameters submit change
if (isset($_POST["move"]) && isset($_POST["id"])){	
	if ($stmt = $conn->prepare("SELECT TurnNo FROM games WHERE GameID = ?")){
		$stmt->bind_param("i", $_POST["id"]);
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res->fetch_assoc();
		if ($row["TurnNo"] < 1){
			die ("Game not started");
		}
	}
	
	$query = "INSERT INTO turns(PlayerID, GameID, TurnNo, X, Y)"
		. " VALUES ((SELECT PlayerID FROM players WHERE IP = ?),"
		. " ?, (SELECT TurnNo From games WHERE GameID = ?), ?, ?)";
		
	if ($stmt = $conn->prepare($query)){
		$stmt->bind_param("siidd", $_SERVER["REMOTE_ADDR"],
			$_POST["id"], $_POST["id"], $x, $y);
		if ($velstmt = $conn->prepare("SELECT X, Y FROM turns WHERE PlayerID = (SELECT PlayerID FROM players WHERE IP = ?) ORDER BY TurnNo DESC LIMIT 1")){
			$velstmt->bind_param("s", $_SERVER["REMOTE_ADDR"]);
			$velstmt->execute();
			
			$x = 0.0; $y = 0.0;
			$res = $velstmt->get_result();
			if ($res->num_rows > 0){
				$row = $res->fetch_assoc();
				$x = (double)$row["X"];
				$y = (double)$row["Y"];
			}
			
			switch($_POST["move"][0]){
				case "n":
					$y -= 1;
					break;
				case "s":
					$y += 1;
					break;
			}
			
			switch($_POST["move"][1]){
				case "e":
					$x += 1;
					break;
				case "w":
					$x -= 1;
					break;
			}
			
			if ($cstmt = $conn->prepare("SELECT File FROM games WHERE GameID = ?")){
				$cstmt->bind_param("i", $_POST["id"]);
				$cstmt->execute();
				$res = $cstmt->get_result();
				if ($res->num_rows > 0){
					$row = $res->fetch_assoc();
					$file = $row["File"];
					
					if (file_exists($file))
					{
						$img = imagecreatefrompng($file);
						
						// Get position and find speed multiplier
						$posquery = "SELECT"
							. " COALESCE(SUM(X), 0) + (SELECT StartX FROM games WHERE GameID = ?) as POSX,"
							. " COALESCE(SUM(Y), 0) + (SELECT StartY FROM games WHERE GameID = ?) as POSY"
							. " FROM turns WHERE GameID = ? AND PlayerID = (SELECT PlayerID FROM players WHERE IP = ?)";
						if ($posstmt = $conn->prepare($posquery)){
							$posstmt->bind_param("iiis", $_POST["id"], $_POST["id"], $_POST["id"], $_SERVER["REMOTE_ADDR"]);
							$posstmt->execute();
							
							$res = $posstmt->get_result();
							if ($res->num_rows > 0){
								$pos = $res->fetch_assoc();
								$posx = (int)$pos["POSX"];
								$posy = (int)$pos["POSY"];
								
								$factor = getMoveFactor($posx, $posy, $img);
								
								// Check if the player has crashed (i.e. cannot move since factor = 0 or x/y will round to 0)
								if ($factor == 0 || getMoveFactor($posx + $x, $posy + $y, $img) == 0
									|| ($x != 0 && abs($x * $factor) < 0.5) || ($y != 0 && abs($y * $factor) < 0.5)){
									$crashq = "UPDATE players SET Crashed = 1 WHERE IP = ?";
									if ($crash = $conn->prepare($crashq)){
										$crash->bind_param("s", $_SERVER["REMOTE_ADDR"]);
										$crash->execute();
									}
									echo "You crashed!";
									
									// If move goes into wall, add move ($stmt); else return
									if ($factor == 0) return;
								}
								
								$x *= $factor;
								$y *= $factor;
							}
						}
					}
				}
			}
			
			$stmt->execute();
		}
	}
}

// Shall we move to the next turn? Has everyone submitted?
$query = "SELECT ((SELECT COUNT(*) FROM turns NATURAL JOIN players WHERE GameID = ? AND Crashed = 0)"
	. " = (SELECT COUNT(*) FROM players WHERE GameID = ? AND Crashed = 0)"
	. " * (SELECT TurnNo FROM games WHERE GameID = ?)) as res";
if ($stmt = $conn->prepare($query)){
	$stmt->bind_param("iii", $_POST["id"], $_POST["id"], $_POST["id"]);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	if ($row["res"]){
		if ($stmt = $conn->prepare("UPDATE games SET TurnNo = TurnNo + 1 WHERE GameID = ?")){
			$stmt->bind_param("i", $_POST["id"]);
			$stmt->execute();
		}
	}
}

if($conn->error){
	echo $conn->error;
}

?>