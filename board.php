<?php
if (!isset($_GET["id"])){
	die ("No identifier");
}

set_time_limit(0);
session_write_close();

$conn = new mysqli("localhost", "root", "", "racetrack");
if (!$conn){
	die ($conn->connect_error());
}

function printBoard(){
	global $conn;
	/* JSON RETURN FORMAT:
	{
		"<player>" : [
			{
				"x" : <x>,
				"y" : <y>
			},
			...
		],
		...
	}*/
	
	// Get turns for this game ordered by player and turn
	$query = "SELECT * FROM turns NATURAL JOIN players"
		. " WHERE GameID = ? ORDER BY PlayerID, TurnNo";
	if ($stmt = $conn->prepare($query)){
		$stmt->bind_param("i", $_GET["id"]);
		$stmt->execute();
		$res = $stmt->get_result();
		
		if ($res->num_rows > 0){
			$arr = array();
			
			// Add turns to array structure
			while ($row = $res->fetch_assoc()) {
				$arr[$row["PlayerName"]][] = ["x" => $row["X"], "y" => $row["Y"]];
			}
			
			// Encode array into JSON
			echo json_encode($arr);
		}
	}
}

if (isset($_GET["force"])){
	printBoard();
	return;
}

$query = "SELECT TurnNo FROM games WHERE GameID = ?";
if ($stmt = $conn->prepare($query)){
	$stmt->bind_param("i", $_GET["id"]);
	$stmt->bind_result($turn);
	$stmt->execute();
	
	// Prevent mysqli complaining about 'commands out of sync'
	while($stmt->fetch()){}
	
	$turnno = $turn;
	while($turn == $turnno){
		usleep(100000);
		$stmt->execute();
		while($stmt->fetch()){}
	}
	
	printBoard();
}
?>