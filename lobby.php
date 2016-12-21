<?php
$conn = new mysqli("localhost", "root", "", "racetrack");
if (!$conn){
	die ($conn->connect_error());
}

// Create new player
if (isset($_POST["name"])){
	if ($stmt = $conn->prepare("INSERT INTO players(IP, PlayerName) VALUES (?, ?)")){
		$stmt->bind_param("ss", $_SERVER["REMOTE_ADDR"], $_POST["name"]);
		$stmt->execute();
	}
}

// Remove player
if (isset($_POST["rm"])){
	if ($stmt = $conn->prepare("DELETE FROM players WHERE IP = ?")){
		$stmt->bind_param("s", $_SERVER["REMOTE_ADDR"]);
		$stmt->execute();
	}
}

// Join game
if (isset($_POST["game"])){
	if ($_POST["game"] == "NULL"){
		if ($stmt = $conn->prepare("UPDATE players SET GameID = NULL WHERE IP = ? LIMIT 1")){
			$stmt->bind_param("s", $_SERVER["REMOTE_ADDR"]);
			$stmt->execute();
		}
	}
	else if (isset($_POST["code"])) {
		if ($stmt = $conn->prepare("UPDATE players SET GameID = ? WHERE IP = ? AND EXISTS(SELECT * FROM games WHERE GameID = ? AND GameCode = ?) LIMIT 1")){
			$stmt->bind_param("isis", $_POST["game"], $_SERVER["REMOTE_ADDR"], $_POST["game"], $_POST["code"]);
			$stmt->execute();
		}
	}
}

// Create new game
if (isset($_POST["new"]) && isset($_POST["code"])){
	if($stmt = $conn->prepare("INSERT INTO games(GameCode) VALUES (?)")){
		$stmt->bind_param("i", $_POST["code"]);
		$stmt->execute();
	}
}

// Output form
echo "<form method='post'>";
if ($stmt = $conn->prepare("SELECT * FROM players WHERE IP = ? LIMIT 1")){
	$stmt->bind_param("s", $_SERVER["REMOTE_ADDR"]);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($res->num_rows > 0){
		$player = $res->fetch_assoc(); ?>
		<p>
			Welcome <?=$player["PlayerName"]?> (<?=$player["IP"]?>)
			Delete?<input type="checkbox" name="rm">
		</p>
		<p>
			<?php if ($player["GameID"] != NULL) { ?>
				You are currently in game <?=$player["GameID"]?>
				<a href="play.php?id=<?=$player["GameID"]?>">Goto</a>
				<a href="joy.php?id=<?=$player["GameID"]?>">Remote</a>
			<?php } ?>
		</p>
		<p>
			Join game
			<select name="game">
				<option value="NULL">None</option>
				<?php
				$res = $conn->query("SELECT GameID FROM games");
				if ($res->num_rows > 0){
					while ($row = $res->fetch_assoc()){
						echo "<option value='{$row["GameID"]}'";
						if ($row["GameID"] == $player["GameID"]){
							echo " selected";
						}
						echo ">{$row["GameID"]}</option>";
					}
				}
				?>
				<option value="new">New...</option>
			</select>
			Game code? <input name="code">
		</p>
		<?php
	}
	else {
		?>
			<p>Create new player:</p>
			<p>Name: <input name="name"></p>
		<?php
	}
}

echo "<input type='submit'></form>";

?>