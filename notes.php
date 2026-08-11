<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2025
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('note_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//connect to the database
	$database = database::new();

//add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', 'false');

//get the http post data
	if (!empty($_POST['notes']) && is_array($_POST['notes'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$notes = $_POST['notes'];
	}

//process the http post data by action
	if (!empty($action) && !empty($notes) && is_array($notes) && @sizeof($notes) != 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: notes.php');
			exit;
		}

		//prepare the array
		if (!empty($notes)) {
			foreach ($notes as $row) {
				$array['notes'][$x]['checked'] = $row['checked'];
				$array['notes'][$x]['note_uuid'] = $row['note_uuid'];
				$x++;
			}
		}

		//prepare the database object
		$database->app_name = 'notes';
		$database->app_uuid = '2782adaf-6e71-4192-9c1a-d7333d188c03';

		//send the array to the database class
		switch ($action) {
			case 'copy':
				if (permission_exists('note_add')) {
					$database->copy($array);
					//$obj = new notes;
					//$obj->copy($notes);
				}
				break;
			case 'toggle':
				if (permission_exists('note_edit')) {
					$database->toggle($array);
					//$obj = new notes;
					//$obj->toggle($notes);
				}
				break;
			case 'delete':
				if (permission_exists('note_delete')) {
					$database->delete($array);
					//$obj = new notes;
					//$obj->delete($notes);
				}
				break;
		}

		//redirect the user
		header('Location: notes.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? null;
	$order = $_GET["order"] ?? null;

//define the variables
	$search = '';
	$show = '';
	$list_row_url = '';

//add the search variable
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//add the show variable
	if (!empty($_GET["show"])) {
		$show = $_GET["show"];
	}

//get the count
	$sql = "select count(note_uuid) ";
	$sql .= "from v_notes ";
	if (permission_exists('note_all') && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(note_type) like :search ";
		$sql .= "	or lower(note_category) like :search ";
		$sql .= "	or lower(note_subcategory) like :search ";
		$sql .= "	or lower(note_tags) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = !empty($search) ? "&search=".$search : null;
	$param .= (!empty($_GET['page']) && $show == 'all' && permission_exists('note_all')) ? "&show=all" : null;
	$page = !empty($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "note_uuid, ";
	$sql .= "user_uuid, ";
	$sql .= "note_type, ";
	$sql .= "note_category, ";
	$sql .= "note_subcategory, ";
	$sql .= "note_tags, ";
	$sql .= "note_content ";
	$sql .= "from v_notes as u, v_domains as d ";
	if (permission_exists('note_all') && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where u.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(note_type) like :search ";
		$sql .= "	or lower(note_category) like :search ";
		$sql .= "	or lower(note_subcategory) like :search ";
		$sql .= "	or lower(note_tags) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "and u.domain_uuid = d.domain_uuid ";
	$sql .= order_by($order_by, $order, '', '');
	$sql .= limit_offset($rows_per_page, $offset);
	$notes = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//additional includes
	$document['title'] = $text['title-notes'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-notes']."</b><div class='count'>".$num_rows."</div></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('note_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','name'=>'btn_add','link'=>'note_edit.php']);
	}
	if (permission_exists('note_add') && $notes) {
		echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$_SESSION['theme']['button_icon_copy'],'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if (permission_exists('note_edit') && $notes) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$_SESSION['theme']['button_icon_toggle'],'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('note_delete') && $notes) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if (permission_exists('note_all')) {
		if ($show == 'all') {
			echo "		<input type='hidden' name='show' value='all'>\n";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$_SESSION['theme']['button_icon_all'],'link'=>'?show=all&search='.$search]);
		}
	}
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('note_add') && $notes) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('note_edit') && $notes) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('note_delete') && $notes) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['title_description-notes']."\n";
	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search ?? '')."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('note_add') || permission_exists('note_edit') || permission_exists('note_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".empty($notes ? "style='visibility: hidden;'" : null).">\n";
		echo "	</th>\n";
	}
	if ($show == 'all' && permission_exists('note_all')) {
		echo th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	echo th_order_by('note_type', $text['label-note_type'], $order_by, $order);
	echo th_order_by('note_category', $text['label-note_category'], $order_by, $order);
	echo th_order_by('note_subcategory', $text['label-note_subcategory'], $order_by, $order);
	echo th_order_by('note_tags', $text['label-note_tags'], $order_by, $order);
	if (permission_exists('note_edit') && $list_row_edit_button == 'true') {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (!empty($notes) && is_array($notes) && @sizeof($notes) != 0) {
		$x = 0;
		foreach ($notes as $row) {
			if (permission_exists('note_edit')) {
				$list_row_url = "note_view.php?id=".urlencode($row['note_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('note_add') || permission_exists('note_edit') || permission_exists('note_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='notes[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='notes[$x][note_uuid]' value='".escape($row['note_uuid'])."' />\n";
				echo "	</td>\n";
			}
			if ($show == 'all' && permission_exists('note_all')) {
				echo "	<td>".escape($row['domain_name'])."</td>\n";
			}
			echo "	<td>".escape($row['note_type'])."</td>\n";
			echo "	<td>".escape($row['note_category'])."</td>\n";
			echo "	<td>".escape($row['note_subcategory'])."</td>\n";
			echo "	<td>".escape($row['note_tags'])."</td>\n";
			if (permission_exists('note_edit') && $list_row_edit_button == 'true') {
				echo "	<td class='action-button'>\n";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>$list_row_url]);
				echo "	</td>\n";
			}
			echo "</tr>\n";
			$x++;
		}
		unset($notes);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
