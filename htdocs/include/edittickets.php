<?php
$act = "tedit";
$ref = pageLinkAct(array('id' => $id, 'src' => $src));
$title = sprintf(T_("Editing ticket %s"), "<span class=\"ticketid\">$id</span>");
pageHeader(array('title' => $title));

// form values
$name = anyOf(@$_POST['name'], $DATA['name']);
$comment = trim(anyOf(@$_POST['comment'], $DATA['cmt']), "");
$hasPass = hasPassHash($DATA);
$pass = anyOf(@$_POST['pass'], "");
$pass_clear = anyOf(@$_POST['pass_clear'], false);
$pass_send = anyOf(@$_POST['pass_send'], $DATA['pass_send']);
$permanent = anyOf(@$_POST['ticket_permanent'], !($DATA['expire'] || $DATA["last_time"] || $DATA["expire_dln"]));
$notify = anyOf(@$_POST['notify'], join(", ", getEMailAddrs($DATA['notify_email'])));

// current expiration values
if(isset($_POST['ticket_totaldays']))
  $totalDays = $_POST['ticket_totaldays'];
elseif($DATA["expire"])
  $totalDays = ceil(($DATA["expire"] + $DATA["time"] - time()) / (3600 * 24));
elseif($permanent)
  $totalDays = $defaults['ticket']['total'] / (3600 * 24);
else
  $totalDays = 0;

if(isset($_POST['ticket_lastdldays']))
  $lastDlDays = $_POST['ticket_lastdldays'];
elseif($DATA["last_time"])
  $lastDlDays = ceil($DATA["last_time"] / (3600 * 24));
elseif($permanent)
  $lastDlDays = $defaults['ticket']['lastdl'] / (3600 * 24);
else
  $lastDlDays = 0;

if(isset($_POST['ticket_maxdl']))
  $maxDl = $_POST['ticket_maxdl'];
elseif($DATA["expire_dln"])
  $maxDl = ($DATA["expire_dln"] - $DATA["downloads"]);
elseif($permanent)
  $maxDl = $defaults['ticket']['maxdl'];
else
  $maxDl = 0;

// current ticket details
$ticketUrl = ticketUrl($DATA);
$details = array();
$details[T_('Current expiration')] = ticketExpiration($DATA);
$details[T_('Created on')] = date($dateFmtFull, $DATA["time"]);
if(!empty($DATA['from_grant']))
{
  $details[T_('From grant')] =
    '<a href="' . pageLink('gedit', array('id' => $DATA['from_grant'], 'src' => $act))
  . '" class="ticketid">' . htmlEntUTF8($DATA['from_grant']) . '</a>';
}

// owner
if($DATA["user_id"] != $auth["id"])
{
  $user = DBConnection::getInstance()->getUserById($DATA["user_id"]);
  $details[T_('Created by')] = htmlEntUTF8($user["name"]);
}

$details[T_('File size')] = humanSize($DATA["size"]);
$details[T_('Download link')] = "<a class=\"ticketid\" href=\"$ticketUrl\">" . htmlEntUTF8($ticketUrl) . "</a>";

// downloads
$details[T_("Download count")] = $DATA["downloads"];
if($DATA["downloads"])
  $details[T_("Last download")] = date($dateFmtFull, $DATA["last_stamp"]);

// sent-to
if($DATA["sent_email"])
{
  $addrs = array();
  foreach(getEMailAddrs($DATA['sent_email']) as $email)
    $addrs[] = '<a href="mailto:' . rawurlencode($email) . '">' . htmlEntUTF8($email) . '</a>';
  $details[T_("Initially sent to")] = implode(", ", $addrs);
}

infoTable($details);
?>

<form enctype="multipart/form-data" method="post"
      onsubmit="validate(event);" action="<?php echo $ref; ?>" >
  <ul>
    <li>
      <?php
	$error = ((@$_POST["submit"] === $act) && empty($_POST["name"]));
	$class = "description required" . ($error? " error": "");
      ?>
      <label class="<?php echo $class; ?>"><?php echo T_("File name"); ?></label>
      <div>
	<input name="name" class="element text" type="text" required
	  pattern="[^/\\:?%*|&amp;&lt;&gt;\x00-\x1f\x7f]+" value="<?php echo htmlEntUTF8($name); ?>"/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type the file name of the ticket (<em>including the extension</em>).");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Comment"); ?></label>
      <div>
	<textarea name="comment" class="element textarea"><?php echo htmlEntUTF8($comment); ?></textarea>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type an <em>optional</em> comment for your uploaded file."
		. " The comment will be shown along with the file name.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo ($hasPass? T_("Change password"): T_("Password")); ?></label>
      <div>
	<input name="pass" class="element text password" type="text" maxlength="255"
	  placeholder="<?php if($hasPass) echo str_repeat('&bull;', 16); ?>"
	  value="<?php echo htmlEntUTF8($pass); ?>" autocomplete="off"/>
	<input class="element button password" type="button" value="<?php echo T_("Generate"); ?>" onclick="passGen();"/>
      <?php if($hasPass) { ?>
	<br/><input name="pass_clear" id="pass_clear" class="element checkbox" type="checkbox"
	   <?php if($pass_clear) echo 'checked="checked"'; ?> value="1"/>
	<label for="pass_clear" class="choice"><?php echo T_("Clear password"); ?></label>
      <?php } ?>
        <br/><input id="pass_send" name="pass_send" class="element checkbox" type="checkbox"
	   <?php if($pass_send) echo 'checked="checked"'; ?> value="1"/>
        <label for="pass_send"><?php echo T_("Send <i>in clear</i> with notifications"); ?></label>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type an <em>optional</em> password that will be required"
		. " to download the file, as an additional security measure.");
            if($hasPass)
	      echo " " . T_("Checking \"Clear password\" will remove the password.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Expire in total # of days"); ?></label>
      <div>
	<input name="ticket_totaldays" value="<?php echo $totalDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type the <strong>maximal number of days</strong> the"
		. " uploaded file is allowed to be kept on the server. After"
		. " this period is passed the file will be deleted from the"
		. " server.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Expire in # of days after last download"); ?></label>
      <div>
	<input name="ticket_lastdldays" value="<?php echo $lastDlDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type the number of days the uploaded file is allowed to be"
		. " kept on the server <strong>after being downloaded</strong>."
		. " After this period is passed without activity, the file will"
		. " be deleted from the server.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Expire after # of downloads"); ?></label>
      <div>
	<input name="ticket_maxdl" value="<?php echo $maxDl; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type the number of times the uploaded file is"
		. " <strong>allowed to be downloaded in total</strong>. After"
		. " this amount is reached the file will be deleted from the"
		. " server.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Permanent ticket / upload"); ?></label>
      <div>
	<input name="ticket_permanent" id="ticket_permanent" class="element checkbox" type="checkbox"
	   <?php if($permanent) echo 'checked="checked"'; ?> value="1"/>
	<label for="ticket_permanent" class="choice"><?php echo T_("Do not expire"); ?></label>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Set this checkmark if you do not want the uploaded file to expire.");
	  ?>
      </small></p>
    </li>

    <li>
      <label class="description"><?php echo T_("Get notified by e-mail"); ?></label>
      <div>
	<input name="notify" class="element text" type="email" multiple maxlength="255" value="<?php echo htmlEntUTF8($notify); ?>"/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type an <em>optional</em> e-mail address (or addresses) that"
		. " should be notified when the file is downloaded from the"
		. " server. You can separate multiple addresses with commas.");
	  ?>
      </small></p>
    </li>

    <li class="buttons">
      <input type="hidden" name="src" value="<?php echo $src; ?>"/>
      <input type="hidden" name="submit" value="<?php echo $act; ?>"/>
      <input id="submit" type="submit" value="<?php echo T_("Update"); ?>"/>
      <input type="reset" value="<?php echo T_("Reset"); ?>"/>
    </li>
  </ul>
</form>

<?php
pageFooter();
