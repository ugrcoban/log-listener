<? 

	require_once 'ugr.class.php';
	$UGR = new UGR();
	

	/************
			AJAX SEARCH - POST
										********/
	if($_POST['submit'] && $_POST['checker']==2) {
		
		## POST PROTECTION ##
		$pst['start']=$UGR->validateText($_POST['start'],10,"nocase");
		$pst['end']=$UGR->validateText($_POST['end'],10,"nocase");
		$pst['log_level']=$UGR->validateText($_POST['log_level'],50,"nocase");
		$pst['server_name']=$UGR->validateText($_POST['server_name'],50,"nocase");
		$pst['log_detail']=NULL;
		
		$pst['start']=substr($pst['start'],-4)."-".substr($pst['start'],3,2)."-".substr($pst['start'],0,2);
		$pst['end']=substr($pst['end'],-4)."-".substr($pst['end'],3,2)."-".substr($pst['end'],0,2);
		
		## POST CHECKER ##
		if(!$UGR->validateDate($pst['start'],"Y-m-d")) {
			$return['error']="Start date format wrong! Please check!";	
		} else if(!$UGR->validateDate($pst['end'],"Y-m-d")) {
			$return['error']="End date format wrong! Please check!";	
		} else if(COUNT($UGR->logs['levels']) && $pst['log_level'] && !in_array($pst['log_level'],$UGR->logs['levels'])) {
			$return['error']="Log level undefined! Please check!";	
		} else if(COUNT($UGR->logs['servers']) && $pst['servers'] && !in_array($pst['servers'],$UGR->logs['servers'])) {
			$return['error']="Server name undefined! Please check!";	
		} else if($pst['start']>$pst['end']) {
			$return['error']="Start date must not be bigger than end date! Please check! ";
		} else {
			
			$start_time = microtime(true);
			$rows=$UGR->searchTable(array("start"=>$pst['start'],"end"=>$pst['end'],"log_level"=>$pst['log_level'],"server_name"=>$pst['server_name'],"log_detail"=>$pst['log_detail']));
			$execution_time=microtime(true) - $start_time;
			
			## Display now if records
			if($counter=COUNT($rows)) {
				$display_limit=0;#It will not take long. HTML Lines waiting to us.
				foreach($rows as $row) {
					if(!$display_limit || $display_limit>++$i)
						$return['content'].="<tr><td>{$row['timestamp']}</td><td>{$row['log_level']}</td><td>{$row['server_name']}</td><td>{$row['log_detail']}</td></tr>";
				}
			} else {
				$return['content']="<tr><td>NO RESULTS</td></tr>";
			}
		}
		
		##table html
$return['content']=<<<EndHTML
<table>
	<thead>
		<tr>
			<td>Timestamp</td>
			<td>Log Level</td>
			<td>Server Name</td>
			<td>Log Detail</td>
		</tr>
	</thead>
	<tbody>
		{$return['content']}
	</tbody>
	<tfoot>
		<tr><td><br><big>{$counter}</big> records & <big>{$execution_time}</big> seconds</td></tr>
	</tfoot>
</table>
EndHTML;

		
		## Hata varsa sadece hata dönsün.
		if($return['error'])
			UNSET($return['content']);
		
		## RETURN Ajax
		die(json_encode($return));
		
	}//end Die
	
	
	
	
	
	/************
			HTML DISPLAY - INPUT & SELECT
										********/
	# Display Log Levels Options
	if(COUNT($UGR->logs['levels'])){
		foreach($UGR->logs['levels'] as $level)
			$opt['log_levels'].="<option value='{$level}'>{$level}</option>";
	}	
	
	# Display Servers Options
	if(COUNT($UGR->logs['servers'])){
		foreach($UGR->logs['servers'] as $server)
			$opt['log_servers'].="<option value='{$server}'>{$server}</option>";
	}
		
	# Today for input last day
	$today=date("d.m.Y");
	
echo <<<EndHTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>TEST TEB</title>

    <!-- jQuery library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>

    <!-- GOOGLE CHARTS visualization -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

	<!------------ Including jQuery Date UI with CSS -------------->
	<script src="https://code.jquery.com/ui/1.11.0/jquery-ui.js"></script>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.11.0/themes/smoothness/jquery-ui.css">
	<!-- jQuery Code executes on Date Format option ----->

<style>
select {font-size:14px;height:27px}
input[type=text] {font-size:14px;width:100px;height:20px}
input[type=submit] {font-size:14px;height:27px}
p.error {color:red}
table {width:60%}
table thead{font-weight:bold}
table tbody{}
table tfoot{}
</style>
  </head>
  <body>
    <article>
      <h1>Task 1 - Monitor Real-time</h1>
      <div id="chart_div"></div>
    </article>
	<br><br>
	<article>
      <h1>Task 2 - Collect Log & Search</h1>
      <form id="searchForm" action="" method="post" autocomplete="off" onsubmit="return false;">
		<input type="hidden" name="checker" id="checker" value="1">
		<select name="log_level">
			<option value="0">All Levels</option>
			{$opt['log_levels']}
		</select>
		<select name="server_name">
			<option value="0">All servers</option>
			{$opt['log_servers']}
		</select>
		<input type="text" name="start" id="start" value="01.04.2019" maxlength="10">
		<input type="text" name="end" id="end" value="{$today}" maxlength="10">
		<input type="submit" name="submit" value="Search">
	  </form>
	  <br>
	  <div id="ajaxLoading" style="display:none">Loading...</div>
	  <div id="ajaxContent"></div>
    </article>
  </body>
</html>
<script>
var chart_data;
var stats='';
google.charts.load('current', {packages: ['corechart']});
google.charts.setOnLoadCallback(load_page_data);

function load_page_data(){
	

    $.ajax('listen.php?rnd='+ new Date().getTime(),{
        data: "&stats="+stats,
		type: 'post',
        dataType: "json",
        success: function(result){
			if(result['data']){
				stats = result['stats'];
				drawChart(result['data']);
            }
        },error: function(jqXHR, textStatus, errorThrown){
			 console.log('jqXHR:');
             console.log(jqXHR);
			 alert("ERROR!");
		}
    });
}

function drawChart(chart_data) {
    var data = google.visualization.arrayToDataTable(chart_data);
	  
    var options = {
        title: 'Real-time Monitor',
		chartArea : {
			  width:'80%',
			  height:'80%'
		}
    };

    var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
    chart.draw(data, options);
}
setInterval(function(){ load_page_data(); }, 5000);
document.getElementById("checker").value = 2;

$(document).ready(function() {
	$("#start,#end").datepicker({
		dateFormat: 'dd.mm.yy'
	});
	
	var notCompleted=0;
	$('input[name=submit]').click(function () {
		if(notCompleted) return false;
		
		var btn = $(this);
		var form = $('#searchForm');
		var ajaxContent = $('#ajaxContent');
		var ajaxLoading = $('#ajaxLoading');
		notCompleted=1;
		
		btn.attr("disabled","disabled").css("opacity","0.6");
		ajaxLoading.show();
		ajaxContent.hide().html('');
		
		$.ajax('', {
			data: form.serialize()+"&submit=1", 
			type: 'POST',
			dataType: "json",
			async: true,
			xhrFields: {withCredentials: true},
			success: function(data) 
			{
				if(data['error']) {
					ajaxContent.html('<p class=error>'+data['error']+'</p>').show();
				} else {
					ajaxContent.html(data['content']).show();
				}
				ajaxLoading.hide();
				btn.removeAttr("disabled").css("opacity","1");
				notCompleted=0;
				
			},
			error: function(xmlhttprequest, textstatus, message) {
				ajaxLoading.hide();
				notCompleted=0;
				btn.removeAttr("disabled").css("opacity","1");
				alert("Please check your internet connection.");
				//location.reload();
			}
		});
		
	});
	
	// Hala sorgu bekliyor, işlem bitmedi henüz
	$(window).on('beforeunload', function(){
		if(notCompleted)
			return 1;
	});	
	
});
</script>	
EndHTML;
	
	
?>