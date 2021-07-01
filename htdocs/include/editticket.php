<?php
// process a ticket update
require_once("ticketfuncs.php");
require_once("pages.php");

function handleUpdate($DATA, $params)
{
  // handle parameters
  $values = array();

  if(!empty($params['name']))
    $values['name'] = mb_sanitize($params['name']);

  if(isset($params['comment']))
  {
    $comment = trim($params['comment']);
    $values['cmt'] = (empty($comment)? 'NULL': $comment);
  }

  if(isset($params['pass_clear']) && $params['pass_clear'])
  {
    $values['pass_ph'] = NULL;
  }
  elseif(!empty($params['pass']))
  {
    $values['pass_ph'] = hashPassword($params['pass']);
  }

  if(isset($params['pass_send']) && $params['pass_send'])
    $values['pass_send'] = true;
  else
    $values['pass_send'] = 0false;

  if(isset($params['ticket_permanent']) && $params['ticket_permanent'])
  {
    $values['last_time'] = NULL;
    $values['expire'] = NULL;
    $values['expire_dln'] = NULL;
  }
  else
  {
    if(empty($params['ticket_totaldays']))
      $values['expire'] = NULL;
    elseif(isset($params['ticket_totaldays']))
      $values['expire'] = (time() - $DATA["time"]) + $params["ticket_totaldays"] * 3600 * 24;
    if(isset($params['ticket_lastdldays']))
      $values['last_time'] = (empty($params['ticket_lastdldays'])? NULL: $params["ticket_lastdldays"] * 3600 * 24);
    if(isset($params['ticket_maxdl']))
      $values['expire_dln'] = (empty($params['ticket_maxdl'])? NULL: $DATA["downloads"] + (int)$params['ticket_maxdl']);
  }

  if(isset($params['notify']))
    $values['notify_email'] = (empty($params['notify'])? NULL: fixEMailAddrs($params["notify"]));
  
  if (!DBConnection::getInstance()->updateTicket($DATA["id"],$values)) {
    return false;
  }
  
  $DATA = DBConnection::getInstance()->getTicketById($DATA["id"]);
  $DATA['pass'] = (empty($params["pass"])? NULL: $_POST["pass"]);

  // trigger update hooks
  Hooks::getInstance()->callHook('onTicketUpdate',['ticket' => $DATA]);
  return $DATA;
}


// fetch the ticket id and check for permissions
$DATA = false;
$id = &$_REQUEST['id'];
if(empty($id) || !isTicketId($id))
  $id = false;
else
{
  $DATA = DBConnection::getInstance()->getTicketById($DATA["id"]);
  if($DATA === false || isTicketExpired($DATA)
  || (!$auth["admin"] && $DATA["user_id"] != $auth["id"]))
    $DATA = false;
}

// handle update
if($DATA)
{
  if(validateParams($ticketEditParams, $_POST))
  {
    // if update succeeds, return to listings
    if(handleUpdate($DATA, $_POST))
      $DATA = false;
  }
}

// resulting page
$src = (array_key_exists(@$_REQUEST['src'], $pages)? $_REQUEST['src']: 'tlist');
if($DATA === false)
  header("Location: " . tokenUrl($adminPath, array('a' => $src)));
else
  include("edittickets.php");
