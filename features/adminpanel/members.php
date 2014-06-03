<?php
/***************************************************************************
* members.php - New Google style members search page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2010
* Revision: 0.0.2
***************************************************************************/

?>
<div class="members">
	<table style="text-align:center;border:none;width:100%;">
		<tr>
			<td>
					<table style="margin-right:auto;margin-left:auto;text-align:center;vertical-align:top;width:100%;white-space:nowrap;">
						<tr>
  							<td style="text-align:left;vertical-align:top;">
  								<div style="width:400px;margin-right:auto;margin-left:auto;">
     							<h2>
								 	Search the Member Directory
								</h2>
								<a style="font-size:.75em;" onclick="if(document.getElementById('instructions').style.display == 'none'){ document.getElementById('instructions').style.display = 'block'; }else{ document.getElementById('instructions').style.display = 'none';} return false;" >Show/Hide Instructions</a>
								<div id="instructions" style="display:none;font-size:.8em;">
								<span style="font-size:.85em;"><b>Name:</b></span><code style="color:blue;"> /n Joe </code> <span style="font-size:.85em;">Default search /n is not needed unless following another tag.</span><br />
								<span style="font-size:.85em;"><b>Field Search:</b></span><code style="color:blue;"> /f department like parent </code> <span style="font-size:.85em;">'fieldname followed by '=, !=, &gt;, &lt;, &gt;=, &lt;=, or like' then search criteria'</span><br />
								<span style="font-size:.85em;"><b>Sort:</b></span><code style="color:blue;"> ex /s -joined </code> <span style="font-size:.85em;">Sorts by joined descending</span><br />
								<span style="font-size:.85em;"><b>Example:</b></span><code style="color:blue;"> Joe /f email like hotmail.com</code> <span style="font-size:.85em;">6th grade math teacher named Joe Smith</span>
								<br /><br />
								</div>
								<a style="font-size:.75em;" onclick="if(document.getElementById('allfields').style.display == 'none'){ document.getElementById('allfields').style.display = 'inline'; }else{ document.getElementById('allfields').style.display = 'none';} return false;" >Show/Hide Available fields</a>
								<span id="allfields" style="display:none;font-size:.8em;">
									<?php
                                    //Get list of all fields that can be searched on
									if($result = get_db_result("SHOW COLUMNS FROM users")){
			 				 			echo "<select style='font-size:0.75em;height:15px;'>";
									  	while($field = fetch_row($result)){                                          
			 				 				echo '<option>'.$field["Field"].'</option>';
									  	}
									  	echo "</select>";
		 				 			}
									?>
								</span>
								<div style="text-align:left;" id="mem_searchbardiv">
									<span style="font-size:.75em;">
										Common Searches: 
										<a href="javascript: document.getElementById('waiting_span').style.display='inline'; ajaxapi('/features/adminpanel/members_script.php','members_search','&search=/s -joined',function() { if (xmlHttp.readyState == 4) { simple_display('mem_resultsdiv'); document.getElementById('waiting_span').style.display='none'; }},true);" >New Members</a>
		 								<a href="javascript: document.getElementById('waiting_span').style.display='inline'; ajaxapi('/features/adminpanel/members_script.php','members_search','&search=/s -last_activity',function() { if (xmlHttp.readyState == 4) { simple_display('mem_resultsdiv');document.getElementById('waiting_span').style.display='none'; }},true);" >Last Accessed</a>
									</span><br />
									<input type="text" id="searchbox" size="50" onkeypress="if(event.keyCode == 13 || event.which == 13){ document.getElementById('waiting_span').style.display='inline'; ajaxapi('/features/adminpanel/members_script.php','members_search','&search='+escape(document.getElementById('searchbox').value),function() { if (xmlHttp.readyState == 4) { simple_display('mem_resultsdiv'); document.getElementById('waiting_span').style.display='none';}},true); }" /> <input type="button" value="Search" onclick="document.getElementById('waiting_span').style.display='inline'; ajaxapi('/features/adminpanel/members_script.php','members_search','&search='+escape(document.getElementById('searchbox').value),function() { if (xmlHttp.readyState == 4) { simple_display('mem_resultsdiv'); document.getElementById('waiting_span').style.display='none';}},true);" />
									<span style="display:none;" id="waiting_span"><img src="<?php echo $CFG->wwwroot; ?>/images/indicator.gif" /></span>
								</div>
								</div>
							</td>
						</tr>
						<tr>
  							<td rowspan="2" style="text-align:left;vertical-align:top;" >
  							<br />
								<div style="text-align:left;" id="mem_resultsdiv"></div>
  							</td>
						</tr>
					</table>
				<div id="mem_debug"></div>
			</td>
		</tr>
	</table>
</div>
<br />
<br />
<br />