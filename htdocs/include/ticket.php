<?php
// process a ticket
require_once("ticketfuncs.php");

// try to fetch the ticket
$id = $_REQUEST["t"];
if(!isTicketId($id))
{
  $id = false;
  $DATA = false;
}
else
{
  $DATA = DBConnection::getInstance()->getTicketById($id);
}

$ref = "$masterPath?t=$id";
if($DATA === false || isTicketExpired($DATA))
{
  $category = ($id === false? 'invalid': ($DATA === false? 'unknown': 'expired'));
  logError("$category ticket requested");
  includeTemplate("$style/include/noticket.php", array('id' => $id));
  exit();
}

// check for password
if(hasPassHash($DATA) && !isset($_SESSION['t'][$id]))
{
  $ret = false;
  if(!empty($_POST['p']))
  {
    $ret = checkPassHash('ticket', $DATA, $_POST['p']);
    logTicketEvent($DATA, "password attempt: " . ($ret? "success": "fail"),
		   ($ret? LOG_INFO: LOG_ERR));
  }
  if($ret)
  {
    // authorize the ticket for this session
    $_SESSION['t'][$id] = array('pass' => $_POST["p"]);
  }
  else
  {
    include("ticketp.php");
    exit();
  }
}

// fix IE total crap by moving to a new location containing the resulting file
// name in the URL (this could be improved for browsers known to work by
// starting to send the file immediately)
header("Location: $dPath/$id/" . rawurlencode($DATA["name"]));
