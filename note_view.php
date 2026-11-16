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
	Portions created by the Initial Developer are Copyright (C) 2018 - 2026
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!(permission_exists('note_add') || permission_exists('note_edit'))) {
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
	$button_icon_back = $settings->get('theme', 'button_icon_back', '');
	$button_icon_edit = $settings->get('theme', 'button_icon_edit', '');
	$button_icon_copy = $settings->get('theme', 'button_icon_copy', '');
	$button_icon_delete = $settings->get('theme', 'button_icon_delete', '');
	$button_icon_save = $settings->get('theme', 'button_icon_save', '');
	$input_toggle_style = $settings->get('theme', 'input_toggle_style', 'switch round');

//include markdown class
	//require_once "resources/classes/parsedown.php";
	$parsedown = new parsedown();
	$parsedown->setSafeMode(true);
	$parsedown->setMarkupEscaped(true);

//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$note_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$user_uuid = $_POST["user_uuid"];
		$note_type = $_POST["note_type"];
		$note_category = $_POST["note_category"];
		$note_subcategory = $_POST["note_subcategory"];
		$note_tags = $_POST["note_tags"];
		$note_content = $_POST["note_content"];
	}

//process the data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: notes.php');
				exit;
			}

		//process the http post data by submitted action
			if ($_POST['action'] != '' && strlen($_POST['action']) > 0) {

				//prepare the array(s)
				//send the array to the database class
				switch ($_POST['action']) {
					case 'copy':
						if (permission_exists('note_add')) {
							$obj = new notes;
							$obj->copy($array);
						}
						break;
					case 'delete':
						if (permission_exists('note_delete')) {
							$obj = new notes;
							$obj->delete($array);
						}
						break;
					case 'toggle':
						if (permission_exists('note_update')) {
							$obj = new notes;
							$obj->toggle($array);
						}
						break;
				}

				//redirect the user
				if (in_array($_POST['action'], array('copy', 'delete', 'toggle'))) {
					header('Location: note_edit.php?id='.$id);
					exit;
				}
			}

		//check for all required data
			$msg = '';
			//if (strlen($user_uuid) == 0) { $msg .= $text['message-required']." ".$text['label-user_uuid']."<br>\n"; }
			//if (strlen($note_type) == 0) { $msg .= $text['message-required']." ".$text['label-note_type']."<br>\n"; }
			//if (strlen($note_category) == 0) { $msg .= $text['message-required']." ".$text['label-note_category']."<br>\n"; }
			//if (strlen($note_subcategory) == 0) { $msg .= $text['message-required']." ".$text['label-note_subcategory']."<br>\n"; }
			//if (strlen($note_tags) == 0) { $msg .= $text['message-required']." ".$text['label-note_tags']."<br>\n"; }
			if (strlen($note_content) == 0) { $msg .= $text['message-required']." ".$text['label-note_content']."<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//verify the record belongs to the current domain
			if ($action == "update") {
				$sql = "select note_uuid from v_notes ";
				$sql .= "where note_uuid = :note_uuid ";
				if (!permission_exists('note_all')) {
					$sql .= "and domain_uuid = :domain_uuid ";
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				}
				$parameters['note_uuid'] = $note_uuid;
				$row = $database->select($sql, $parameters, 'row');
				unset($sql, $parameters);
				if (empty($row)) {
					message::add('Invalid UUID', 'negative');
					header('Location: notes.php');
					exit;
				}
			}

		//add the note_uuid
			if (!is_uuid($_POST["note_uuid"])) {
				$note_uuid = uuid();
			}

		//prepare the array
			$array['notes'][0]['note_uuid'] = $note_uuid;
			$array['notes'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			if ($action == "add") {
				$array['notes'][0]['user_uuid'] = $_SESSION['user_uuid'];
			}
			$array['notes'][0]['note_type'] = $note_type;
			$array['notes'][0]['note_category'] = $note_category;
			$array['notes'][0]['note_subcategory'] = $note_subcategory;
			$array['notes'][0]['note_tags'] = $note_tags;
			$array['notes'][0]['note_content'] = $note_content;

		//save the data
			$database->app_name = 'notes';
			$database->app_uuid = '2782adaf-6e71-4192-9c1a-d7333d188c03';
			$database->save($array);

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					$_SESSION["message"] = $text['message-add'];
				}
				if ($action == "update") {
					$_SESSION["message"] = $text['message-update'];
				}
				//header('Location: notes.php');
				header('Location: note_edit.php?id='.urlencode($note_uuid));
				return;
			}
	}

//pre-populate the form
	if (is_array($_GET) && $_POST["persistformvar"] != "true") {
		$sql = "select ";
		$sql .= " note_uuid, ";
		$sql .= " user_uuid, ";
		$sql .= " note_type, ";
		$sql .= " note_category, ";
		$sql .= " note_subcategory, ";
		$sql .= " note_tags, ";
		$sql .= " note_content ";
		$sql .= "from v_notes ";
		$sql .= "where note_uuid = :note_uuid ";
		if (!permission_exists('note_all')) {
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		}
		$parameters['note_uuid'] = $note_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			//$user_uuid = $row["user_uuid"];
			$note_type = $row["note_type"];
			$note_category = $row["note_category"];
			$note_subcategory = $row["note_subcategory"];
			$note_tags = $row["note_tags"];
			$note_content = $row["note_content"];
		}
		unset($sql, $parameters, $row);
	}

//formatted markdown
	if (!empty($note_content)) {
		$note_content_formatted = $parsedown->text($note_content);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-note'];
	require_once "resources/header.php";

//script
	echo "<script>\n";
	/* copy element value/content to clipboard
	 *
	 * pass in the element by reference
	 *
	 * examples...
	 * 		jquery: $(this) or $('#id')
	 * 		javascript: document.getElementById('id')
	 */
	echo "function copy_to_clipboard(element, what = '', duration = 1000) {\n";
	echo "	var temp = $('<textarea>');\n";
	echo "	$('body').append(temp);\n";
	echo "	temp.val($(element).val()).select();\n";
	echo "	document.execCommand('copy');\n";
	echo "	temp.remove();\n";
	echo "	display_message(what + \" Copied to Clipboard<i class='fas fa-clipboard fa-fw ml-2'></i>\", 'positive', duration);\n";
	echo "}\n";
	echo "</script>\n";
	echo "\n";

?>
<script>

//define the variable
let selected_text = '';

//add the markdown commands array
var markdown_commands = {
	'header_1': { default_text: '# Header1\n', selected_text: '# ${selected_text}' },
	'header_2': { default_text: '## Header2\n', selected_text: '## ${selected_text}' },
	'header_3': { default_text: '### Header3\n', selected_text: '### ${selected_text}' },
	'bold': { default_text: '**Bold**', selected_text: '**${selected_text}**' },
	'italic': { default_text: '*Italic*', selected_text: '*${selected_text}*' },
	'code_inline': { default_text: '`inline`', selected_text: '`${selected_text}`' },
	'code_block': { default_text: '\n```\n```\n', selected_text: '\n```\n${selected_text}\n```\n' },
	'link': { default_text: '[Label](https://example.com)\n', selected_text: '[Link Label](${selected_text})' },
	'image': { default_text: '![Label](https://example.com/image.png)\n', selected_text: '![Alt Text](${selected_text})' },
	'list_unordered': { default_text: '- Item\n- Item\n- Item\n', selected_text: '- ${selected_text}\n- Item\n- Item\n' },
	'list_ordered': { default_text: '1. Item\n1. Item\n1. Item\n', selected_text: '1. ${selected_text}\n1. Item\n1. Item\n' },
	'horizontal_rule': { default_text: '\n---\n\n', selected_text: '${selected_text}\n---\n\n' }
};

// Initialize history stack and current position in the stack
let text_history = [];
let history_position = -1;

// Function to add markdown text to the textarea
function add_markdown_text(command, textarea_id) {
	const textarea = document.getElementById(textarea_id);
	const start = textarea.selectionStart;
	const end = textarea.selectionEnd;
	let value = textarea.value;

	// Save current state before making changes for undo functionality
	if (history_position < text_history.length - 1) {
		text_history.splice(history_position + 1, text_history.length - history_position - 1);
	}
	text_history.push(value);
	history_position++;

	let formatted_text = '';
	let new_text;

	if (start !== end) {
		const selected_text = value.substring(start, end);
		formatted_text = markdown_commands[command].selected_text;
		new_text = formatted_text.replace('${selected_text}', selected_text);
		textarea.value = value.substring(0, start) + new_text + value.substring(end);
		textarea.setSelectionRange(start + new_text.length - (end - start), start + new_text.length - (end - start));
	} else {
		const default_text = markdown_commands[command].default_text;
		textarea.value = value.substring(0, start) + default_text + value.substring(end);
		textarea.setSelectionRange(start + default_text.length, start + default_text.length);
	}
}

// Function to handle undo
function undo(textarea_id) {
	const textarea = document.getElementById(textarea_id);

	if (history_position > 0) {
		history_position--;
		textarea.value = text_history[history_position];
	} else {
		// Optionally alert or handle the case where there's nothing more to undo
		console.log('Nothing to undo');
	}
}

// Function to handle redo
function redo(textarea_id) {
	const textarea = document.getElementById(textarea_id);

	if (history_position < text_history.length - 1) {
		history_position++;
		textarea.value = text_history[history_position];
	} else {
		// Optionally alert or handle the case where there's nothing more to redo
		console.log('Nothing to redo');
	}
}

// Add event listeners for Ctrl+Z and Ctrl+Y
document.addEventListener('keydown', function(event) {
	const textarea_id = 'message'; // Replace with your actual textarea ID
	if (event.ctrlKey && event.key === 'z') {
		undo(textarea_id);
	} else if (event.ctrlKey && event.key === 'y') {
		redo(textarea_id);
	}
});

// Example usage of add_markdown_text function
//document.getElementById('bold_button').addEventListener('click', function() {
//	add_markdown_text('bold', 'message');
//});


/*
//add markdown to the textarea
function add_markdown_text(command, textarea_id) {
	const textarea = document.getElementById(textarea_id);
	const start = textarea.selectionStart;
	const end = textarea.selectionEnd;
	let value = textarea.value;

	// disable submit when using the toolbar - assume your button is <button id="submit">Submit</button>
	//const submit = document.getElementById('submit');
	//submit.addEventListener('click', function(event) {
	//	event.preventDefault(); // ⟵ cancels the browser’s default
	//	// …your custom logic here…
	//});

	// Extract the selected text and wrap it with Markdown syntax based on the command
	if (start !== end) {
		//define the variable
		let formatted_text = '';

		//get the selected text
		selected_text = value.substring(start, end);

		//get the formatted_text
		formatted_text = markdown_commands[command].selected_text;

		//replace the variable
		let new_text = formatted_text.replace('${selected_text}', selected_text);

		// Replace the selected text with the formatted text
		textarea.value = value.substring(0, start) + new_text + value.substring(end);

	} else {
		// Handle commands that don't require selection

		//define the variable
		let default_text;

		//get the selected text
		default_text = markdown_commands[command].default_text;

		// Insert default_text at cursor position
		textarea.value = value.substring(0, start) + default_text + value.substring(end);

		// Move cursor to after inserted text
		textarea.setSelectionRange(start + default_text.length, start + default_text.length);
	}
}
*/

//resize the toolbar to match the textarea
//const textarea = document.querySelector('textarea');
//const observer = new ResizeObserver(entries => {
//	for (let entry of entries) {
//		adjust_toolbar_width();
//	}
//});
//observer.observe(textarea);

//resize the toolbar
//function adjust_toolbar_width() {
//    const toolbar = document.getElementById('markdown-toolbar');
//    const textarea = document.getElementById('message');
//    // Set the width of the toolbar to match the textarea
//    toolbar.style.width = (textarea.offsetWidth - 20) + 'px';
//}

// Initialize the toolbar width on page load
//window.onload = function() {
//    adjust_toolbar_width();
//};

</script>

<style>
	.toolbar {
		padding: 3px 5px;
		margin-bottom: 0px;
		/*background-color: #eeeeee;*/
	}
	.btn {
		padding: 3px 5px;
		cursor: pointer;
		border: 1px solid #cccccc;
		background-color: #f8f9fa;
		margin-right: 5px;
	}

code {
  display: block;
  width: 100%;
  box-sizing: border-box;
  background-color: #B8D5FF; /* Pastel light blue */
  color: #1e3a5f;            /* Professional dark blue for readability */
  font-family: 'SF Mono', Monaco, Consolas, 'Courier New', monospace;
  font-size: 0.9rem;
  line-height: 1.6;
  padding: 1.25rem;
  border: 1px solid #90BDFE;
  border-radius: 8px;
  white-space: pre-wrap;
  overflow-x: auto;
  margin: 0 0 1rem 0;
}

/* Optional: Professional scrollbar styling for long code blocks */
code::-webkit-scrollbar {
  height: 8px;
}

code::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 4px;
}

code::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}

code::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.25);
}

</style>

<?php

//show the content
	echo "<form name='frm' id='frm' method='post' action=''>\n";
	echo "<input class='formfld' type='hidden' name='note_uuid' value='".escape($note_uuid)."'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-note']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$button_icon_back,'id'=>'btn_back','collapse'=>'hide-xs','style'=>'margin-right: 15px;','link'=>'notes.php']);
	if ($action == 'update') {
		if (permission_exists('_add')) {
			echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$button_icon_copy,'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
		}
		if (permission_exists('_delete')) {
			echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$button_icon_delete,'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none; margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
		}
	}
	echo button::create(['type'=>'button','label'=>$text['button-edit'],'icon'=>$button_icon_edit,'id'=>'btn_back','collapse'=>'hide-xs','style'=>'margin-right: 15px;','link'=>'note_edit.php?id='.$id]);

	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['title_description-notes']."\n";
	echo "<br /><br />\n";

	if ($action == 'update') {
		if (permission_exists('note_add')) {
			echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'copy','onclick'=>"modal_close();"])]);
		}
		if (permission_exists('note_delete')) {
			echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
		}
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "	".$text['label-user_uuid']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' style='position: relative;' align='left'>\n";
	// echo "  <input class='formfld' type='text' name='user_uuid' maxlength='255' value='".escape($user_uuid)."'>\n";
	// echo "<br />\n";
	// echo $text['description-user_uuid']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "	".$text['label-note_type']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' style='position: relative;' align='left'>\n";
	// echo escape($note_type);
	//echo "	<input class='formfld' type='text' name='note_type' maxlength='255' value='".escape($note_type)."'>\n";
	//echo "<br />\n";
	//echo $text['description-note_type']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "	".$text['label-note_category']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' style='position: relative;' align='left'>\n";
	// echo escape($note_category);
	//echo "	<input class='formfld' type='text' name='note_category' maxlength='255' value='".escape($note_category)."'>\n";
	//echo "<br />\n";
	//echo $text['description-note_category']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "	".$text['label-note_subcategory']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' style='position: relative;' align='left'>\n";
	// echo escape($note_subcategory);
	//echo "	<input class='formfld' type='text' name='note_subcategory' maxlength='255' value='".escape($note_subcategory)."'>\n";
	//echo "<br />\n";
	//echo $text['description-note_subcategory']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	// echo "<tr>\n";
	// echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	// echo "	".$text['label-note_tags']."\n";
	// echo "</td>\n";
	// echo "<td class='vtable' style='position: relative;' align='left'>\n";
	// echo escape($note_subcategory);
	//echo "	<input class='formfld' type='text' name='note_tags' maxlength='255' value='".escape($note_tags)."'>\n";
	//echo "<br />\n";
	//echo $text['description-note_tags']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	//echo "<tr>\n";
	//echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	//echo "	".$text['label-note_content']."\n";
	//echo "</td>\n";
	//echo "<td class='vtable' style='position: relative;' align='left'>\n";

	//markdown toolbar
	// echo "								<div class='toolbar' style='padding: 1px 1px;' id='markdown-toolbar'>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('header_1','message');\" title='Heading 1'><i class='fa-solid fa-heading mr-1'></i><i class='fa-solid fa-1'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 14px; line-height: 22px;' onclick=\"add_markdown_text('header_2','message');\" title='Heading 2'><i class='fa-solid fa-heading mr-1'></i><i class='fa-solid fa-2'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 12px; line-height: 22px;' onclick=\"add_markdown_text('header_3','message');\" title='Heading 3'><i class='fa-solid fa-heading mr-1'></i><i class='fa-solid fa-3'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('bold','message'); return false;\" title='Bold'><i class='fa-solid fa-bold fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('italic','message'); return false;\" title='Italic'><i class='fa-solid fa-italic fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('code_inline','message'); return false;\" title='Code Inline'><i class='fa-solid fa-terminal fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('code_block','message'); return false;\" title='Code Block'><i class='fa-solid fa-code fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('link','message'); return false;\" title='Link'><i class='fa-solid fa-link fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('image','message'); return false;\" title='Image'><i class='fa-solid fa-image fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('list_unordered','message'); return false;\" title='Unordered List'><i class='fa-solid fa-list-ul fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('list_ordered','message'); return false;\" title='Order List'><i class='fa-solid fa-list-ol fa-fw'></i></button>\n";
	// echo "									<button type='button' class='btn mx-0 mb-1' style='font-size: 16px; line-height: 22px;' onclick=\"add_markdown_text('horizontal_rule','message'); return false;\" title='Horizontal Rule'><i class='fa-solid fa-minus fa-fw'></i></button>\n";
	// echo "								</div>\n";

    echo $note_content_formatted;
    //echo "\n";

	// echo "								<textarea class='formfld' id='message' name='note_content' style='width: 100%; max-width: 100%; height: 400px; resize: vertical;'>".$note_content."</textarea>\n";
	// echo "							</td>\n";

	// echo "<br />\n";
	// echo $text['description-note_content']."\n";
	// echo "</td>\n";
	// echo "</tr>\n";

	// echo "</table>";
	echo "<br /><br />";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
