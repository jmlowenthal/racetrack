<?php
if(!isset($_GET["id"])){
	header("Location: lobby.php");
	return;
}
$conn = new mysqli("localhost", "root", "", "racetrack");
if (!$conn){
	die ($conn->connect_error());
}

// Reset game
if(isset($_POST["reset"])){
	if ($stmt = $conn->prepare("DELETE FROM turns WHERE GameID = ?")){
		$stmt->bind_param("i", $_GET["id"]);
		$stmt->execute();
	}
	
	if ($stmt = $conn->prepare("UPDATE games SET TurnNo = 0 WHERE GameID = ?")){
		$stmt->bind_param("i", $_GET["id"]);
		$stmt->execute();
	}
	
	if ($stmt = $conn->prepare("UPDATE players SET Crashed = 0 WHERE GameID = ?")){
		$stmt->bind_param("i", $_GET["id"]);
		$stmt->execute();
	}
}

// Start game
if(isset($_POST["start"])){
	if ($stmt = $conn->prepare("UPDATE games SET TurnNo = 1 WHERE GameID = ?")){
		$stmt->bind_param("i", $_GET["id"]);
		$stmt->execute();
	}
}

$startx = 0; $starty = 0;
if ($stmt = $conn->prepare("SELECT * FROM games WHERE GameID = ?")){
	$stmt->bind_param("i", $_GET["id"]);
	$stmt->execute();
	$res = $stmt->get_result();
	
	// If there is no game return to lobby
	if ($res->num_rows < 1){
		header("Location: lobby.php");
		return;
	}
	
	$row = $res->fetch_assoc();
	
	echo "<form method='post'>";
	if ($row["TurnNo"] < 1){
		echo "<button name='start'>Start</button>";
	}
	else {
		echo "<button name='reset'>Reset</button>";
	}
	echo "</form>";
	
	if ($row["File"]){
		$file = $row["File"];
	}
	
	$startx = $row["StartX"];
	$starty = $row["StartY"];
}
else {
	die ($conn->error);
}

// Check if player is in game
$play = false;
if ($stmt = $conn->prepare("SELECT"
	. " ((SELECT TurnNo FROM games WHERE GameID = ?) > 0 OR "
	. "(SELECT COUNT(*) FROM players WHERE IP = ? AND GameID = ?) < 1) as res")){
	$stmt->bind_param("iii", $_GET["id"], $_SERVER["REMOTE_ADDR"], $_GET["id"]);
	$stmt->execute();
	$play = $stmt->get_result()->fetch_assoc()["res"];
}
?>

<head>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	
	<style>
		.joystick, .joystick-label {
			height: 200px;
			width: 200px;
			padding: 0;
		}
	
		.joystick input, .joystick select {
			display: none;
		}
		
		.joystick-label {
			display: block;
			overflow: hidden;
			cursor: pointer;
			
			border-radius: 50%;
			background-color: #EEEEEE;
			border: 3px solid grey;
			
			transform: rotate(0deg);
		}
		
		.joystick-label:before {
			content: "";
			display: block;
			background: #FFFFFF;
			
			width: 30%;
			height: 30%;
			margin: 0px;
			border-radius: 50%;
			border: 1px solid grey;
			
			position: absolute;
			top: 35%;
			left: 35%;
			
			-webkit-transform: inherit;
			-moz-transform: inherit;
			-ms-transform: inherit;
			-o-transform: inherit;
			transform: inherit;
		}
		
		.edge:before {
			right: 10%;
			left: auto;
		}
		
		.body {
			max-width: 1000px;
			width: 90%;
			margin: auto;
		}
		
		canvas {
			margin-left: auto;
			margin-right: auto;
		}
	</style>
</head>

<body>
	<?php if (isset($file)) { ?>
		<img id="map" src="bg_<?=$file?>" hidden />
	<?php
	}
	else { ?>
		<img id="map" hidden />
	<?php
	} ?>
	
	<div class="body">
		<div id="canvas-container" style="padding-bottom: 20px">
			<canvas id="canvas" width="1000" height="600"></canvas>
		</div>
		<div id="controls-container" width="100%">
			<?php if($play){ ?>
			<div class="joystick" style="margin:auto">
				<select name="move" id="joy">
					<option value="nw">nw</option>
					<option value="nc">nc</option>
					<option value="ne">ne</option>
					<option value="ce">ce</option>
					<option value="cc" selected>cc</option>
					<option value="cw">cw</option>
					<option value="sw">sw</option>
					<option value="sc">sc</option>
					<option value="se">se</option>
				</select>
				<label class="joystick-label" for="joy"></label>
			</div>
			<div id="res"></div>
			<?php } ?>
		</div>
		<a href="lobby.php">Back to lobby</a>
	</div>

	<script>	
		$(function(){
			var canvas = $("#canvas")[0];
			if (!canvas.getContext) return;
			var ctx = canvas.getContext("2d");
			
			var map = $("#map");
			$(".body").width(map.width());
			ctx.canvas.width = map.width();
			ctx.canvas.height = map.height();
			
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			ctx.drawImage(map[0], 0, 0, map.width(), map.height());
			
			function poll(force) {				
				var params = { id: <?=$_GET["id"]?> };
				if (force){
					params.force = "yesplz";
				}
				
				var request = $.ajax({
					method: "get",
					url: "board.php",
					data: params,
					dataType: "json",
				});
					
				request.done(function(data) {
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					ctx.drawImage(map[0], 0, 0, map.width(), map.height());
					
					for (var player in data){
						// Draw path lines
						ctx.beginPath();
						var x = <?=$startx?>, y = <?=$starty?>;
						ctx.lineTo(x * 10, y * 10);
						for (var move in data[player]){
							x += data[player][move].x;
							y += data[player][move].y;
							ctx.lineTo(x * 10, y * 10);
						}
						ctx.stroke();
						ctx.closePath();
						
						// Draw path points
						var x = <?=$startx?>, y = <?=$starty?>;
						ctx.beginPath();
						ctx.ellipse(x * 10, y * 10, 2, 2, 0, 0, 2 * Math.PI);
						ctx.fill();
						ctx.closePath();
						for (var move in data[player]){
							x += data[player][move].x;
							y += data[player][move].y;
							
							ctx.beginPath();
							ctx.ellipse(x * 10, y * 10, 2, 2, 0, 0, 2 * Math.PI);
							ctx.fill();
							ctx.closePath();
						}
						
						ctx.fillText(player, x * 10 + 10, y * 10);
					}
				});
				
				request.always(function(){
					poll(false);
				});
			}
			
			dir = {
				'45' : "ne", '90' : "nc", '135' : "nw",
				'0' : "ce", '180' : "cw", '-180' : "cw",
				'-45' : "se", '-90' : "sc", '-135' : "sw"
			};
			
			function getRotationDegrees(obj) {
				var matrix = obj.css("-webkit-transform") ||
					obj.css("-moz-transform")    ||
					obj.css("-ms-transform")     ||
					obj.css("-o-transform")      ||
					obj.css("transform");
				if(matrix !== 'none') {
					var values = matrix.split('(')[1].split(')')[0].split(',');
					var a = values[0];
					var b = values[1];
					var angle = Math.round(Math.atan2(b, a) * (180/Math.PI));
				} else { var angle = 0; }
				return (angle < 0) ? angle + 360 : angle;
			}
			
			$("label.joystick-label").click(function(e){
				var x = e.offsetX - $(this).width() / 2;
				var y = e.offsetY - $(this).height() / 2;
				
				var select = $("#" + $(this).attr("for"));
				
				var submit = false;
				
				var deadzone = $(this).width() / 6;
				if (x * x + y * y > deadzone * deadzone){
					var angle = Math.atan2(-y , x) * 180 / Math.PI - getRotationDegrees($(this));
					angle = Math.round(angle / 45) * 45;
					angle = (angle + 180 * 3) % 360 - 180;
						
					$(this).addClass("edge");
					
					if (dir[angle] == select.val()){
						submit = true;
					}
					else {
						select.val(dir[angle]);
					
						angle = -angle;
						$(this).css({
							"-webkit-transform": "rotate(" + angle + "deg)",
							"-moz-transform": "rotate(" + angle + "deg)",
							"-ms-transform": "rotate(" + angle + "deg)",
							"-o-transform": "rotate(" + angle + "deg)",
							"transform": "rotate(" + angle + "deg)"
						});
					}
				}
				else {
					$(this).removeClass("edge");
					if (select.val() == "cc"){
						submit = true;
					}
					else {
						select.val("cc");
					}
				}
				
				if (submit){
					$.post("submit.php", {
						move : select.val(),
						id: <?= $_GET["id"] ?>
					}, function(data){
						$("#res").html(data);
					});
				}
			});
			
			poll(true);
		});
	</script>
</body>