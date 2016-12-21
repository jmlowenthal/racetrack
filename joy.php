<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	
	<style>			
		.joystick, .joystick-label {
			width: 90vh;
			max-width: 90vw;
			height: 90vh;
			max-height: 90vw;
		}
		
		.joystick {
			position: absolute;
			top: 50%;
			left: 50%;
			
			-webkit-transform: translate(-50%, -50%);
			-moz-transform: translate(-50%, -50%);
			-ms-transform: translate(-50%, -50%);
			-o-transform: translate(-50%, -50%);
			transform: translate(-50%, -50%);
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
	</style>
</head>

<div class="joystick">
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

<div id="res" style="position: absolute; bottom: 5%; left: 5%">
</div>

<script>
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
</script>