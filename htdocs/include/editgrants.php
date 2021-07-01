<?php
$act = "gedit";
$ref = pageLinkAct(array('id' => $id, 'src' => $src));
$title = sprintf(T_("Editing grant %s"), "<span class=\"ticketid\">$id</span>");
pageHeader(array('title' => $title));

// form values
$notify = anyOf(@$_POST['notify'], join(", ", getEMailAddrs($DATA['notify_email'])));
$comment = trim(anyOf(@$_POST['comment'], $DATA['cmt']), "");
$hasPass = hasPassHash($DATA);
$pass = anyOf(@$_POST['pass'], "");
$pass_clear = anyOf(@$_POST['pass_clear'], false);
$pass_send = anyOf(@$_POST['pass_send'], $DATA['pass_send']);
$grant_permanent = anyOf(@$_POST['grant_permanent'], !($DATA['grant_expire'] || $DATA["grant_last_time"] || $DATA["grant_expire_uln"]));
$ticket_permanent = anyOf(@$_POST['ticket_permanent'], !($DATA['expire'] || $DATA["last_time"] || $DATA["expire_dln"]));

// current grant expiration values
if(isset($_POST['grant_totaldays']))
  $grantTotalDays = $_POST['grant_totaldays'];
elseif($DATA["grant_expire"])
  $grantTotalDays = ceil(($DATA["grant_expire"] + $DATA["time"] - time()) / (3600 * 24));
elseif($grant_permanent)
  $grantTotalDays = $defaults['grant']['total'] / (3600 * 24);
else
  $grantTotalDays = 0;

if(isset($_POST['grant_lastuldays']))
  $grantLastUlDays = $_POST['grant_lastuldays'];
elseif($DATA["grant_last_time"])
  $grantLastUlDays = ceil($DATA["grant_last_time"] / (3600 * 24));
elseif($grant_permanent)
  $grantLastUlDays = $defaults['grant']['lastul'] / (3600 * 24);
else
  $grantLastUlDays = 0;

if(isset($_POST['grant_maxul']))
  $grantMaxUl = $_POST['grant_maxul'];
elseif($DATA["grant_expire_uln"])
  $grantMaxUl = ($DATA["grant_expire_uln"] - $DATA["uploads"]);
elseif($grant_permanent)
  $grantMaxUl = $defaults['grant']['maxul'];
else
  $grantMaxUl = 0;

// current ticket expiration values
if(isset($_POST['ticket_totaldays']))
  $ticketTotalDays = $_POST['ticket_totaldays'];
elseif($DATA["expire"])
  $ticketTotalDays = ceil($DATA["expire"] / (3600 * 24));
elseif($ticket_permanent)
  $ticketTotalDays = $defaults['ticket']['total'] / (3600 * 24);
else
  $ticketTotalDays = 0;

if(isset($_POST['ticket_lastdldays']))
  $ticketLastDlDays = $_POST['ticket_lastdldays'];
elseif($DATA["last_time"])
  $ticketLastDlDays = ceil($DATA["last_time"] / (3600 * 24));
elseif($ticket_permanent)
  $ticketLastDlDays = $defaults['ticket']['lastdl'] / (3600 * 24);
else
  $ticketLastDlDays = 0;

if(isset($_POST['ticket_maxdl']))
  $ticketMaxDl = $_POST['ticket_maxdl'];
elseif($DATA["expire_dln"])
  $ticketMaxDl = $DATA["expire_dln"];
elseif($ticket_permanent)
  $ticketMaxDl = $defaults['ticket']['maxdl'];
else
  $ticketMaxDl = 0;

// current grant details
$grantUrl = grantUrl($DATA);
$details = array();
$details[T_('Current expiration')] = grantExpiration($DATA);
$details[T_('Created on')] = date($dateFmtFull, $DATA["time"]);

// owner
if($DATA["user_id"] != $auth["id"])
{
  $user = DBConnection::getInstance()->getUserById($DATA["user_id"]);
  
  
  $details[T_('Created by')] = htmlEntUTF8($user["name"]);
}

$details[T_('Upload link')] = "<a class=\"ticketid\" href=\"$grantUrl\">" . htmlEntUTF8($grantUrl) . "</a>";

// uploads
$details[T_("Upload count")] = $DATA["uploads"];
if($DATA["uploads"])
  $details[T_("Last upload")] = date($dateFmtFull, $DATA["last_stamp"]);

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
	$error = ((@$_POST["submit"] === $act) && empty($_POST["notify"]));
	$class = "description required" . ($error? " error": "");
      ?>
      <label class="<?php echo $class; ?>"><?php echo T_("Notification e-mail"); ?></label>
      <div>
	<input name="notify" class="element text" type="email" required multiple maxlength="255" value="<?php echo htmlEntUTF8($notify); ?>"/>
      </div>
      <p class="guidelines"><small>
	  <?php
	    echo T_("Type a <b>mandatory</b> e-mail address (or addresses) that"
		. " should be notified when the file is uploaded to the server."
		. " You can separate multiple addresses with commas.");
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
	  echo T_("Type an <em>optional</em> password that will be required to"
		  . " both upload and download the file, as an additional"
		  . " security measure.");
          if($hasPass)
	    echo " " . T_("Checking \"Clear password\" will remove the password.");
	  ?>
      </small></p>
    </li>

    <ul>
      <li>
	<label class="description"><?php echo T_("Grant expiry"); ?></label>
	<ul>

	  <li>
	    <label class="description"><?php echo T_("Expire in total # of days"); ?></label>
	    <div>
	      <input name="grant_totaldays" value="<?php echo $grantTotalDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
	    </div>
	    <p class="guidelines"><small>
	      <?php
	      echo T_("Type the <strong>maximal number of days</strong> the grant is"
		      . " allowed to be used. After this period is passed the grant will"
		      . " be deleted from the server.");
	      ?>
	    </small></p>
	  </li>

	  <li>
	    <label class="description"><?php echo T_("Expire in # of days after last upload"); ?></label>
	    <div>
	      <input name="grant_lastuldays" value="<?php echo $grantLastUlDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
	    </div>
	    <p class="guidelines"><small>
	      <?php
	      echo T_("Type the number of days the grant is allowed to be used"
		      . " <strong>after any upload</strong>. After this period is passed"
		      . " without activity, the grant will be deleted from the server.");
	      ?>
	    </small></p>
	  </li>

	  <li>
	    <label class="description"><?php echo T_("Expire after # of uploads"); ?></label>
	    <div>
	      <input name="grant_maxul" value="<?php echo $grantMaxUl; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
	    </div>
	    <p class="guidelines"><small>
	      <?php
	      echo T_("Type the number of times the grant file is <strong>allowed to"
		      . " be used in total</strong>. After this amount is reached the"
		      . " grant will be deleted from the server.");
	      ?>
	    </small></p>
	  </li>

	  <li>
	    <label class="description"><?php echo T_("Permanent grant"); ?></label>
	    <div>
	      <input name="grant_permanent" id="grant_permanent" class="element checkbox" type="checkbox"
		     <?php if($grant_permanent) echo 'checked="checked"'; ?> value="1"/>
	      <label for="grant_permanent" class="choice"><?php echo T_("Do not expire"); ?></label>
	    </div>
	    <p class="guidelines"><small>
	      <?php
	      echo T_("Set this checkmark if you do not want the grant to expire.");
	      ?>
	    </small></p>
	  </li>

	</ul>
      </li>
      <li><label class="description"><?php echo T_("Ticket expiry"); ?></label>

	<ul>
	  <li>
	    <label class="description"><?php echo T_("Expire in total # of days"); ?></label>
	    <div>
	      <input name="ticket_totaldays" value="<?php echo $ticketTotalDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
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
	      <input name="ticket_lastdldays" value="<?php echo $ticketLastDlDays; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
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
	      <input name="ticket_maxdl" value="<?php echo $ticketMaxDl; ?>" class="element text" type="number" min="0" maxlength="255" value=""/>
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
		     <?php if($ticket_permanent) echo 'checked="checked"'; ?> value="1"/>
	      <label for="ticket_permanent" class="choice"><?php echo T_("Do not expire"); ?></label>
	    </div>
	    <p class="guidelines"><small>
	      <?php
	      echo T_("Set this checkmark if you do not want the uploaded file to expire.");
	      ?>
	    </small></p>
	  </li>

	</ul>
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
