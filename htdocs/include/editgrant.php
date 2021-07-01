<?php
// process a grant update
require_once("grantfuncs.php");
require_once("pages.php");

function handleUpdate($DATA, $params)
{
  // handle parameters
  $values = array();
  $values['notify_email'] = fixEMailAddrs($params["notify"]);

  if(isset($params['comment']))
  {
    $comment = trim($params['comment']);
    $values['cmt'] = (empty($comment)? NULL: $comment);
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
    $values['pass_send'] = false;

  if(isset($params['grant_permanent']) && $params['grant_permanent'])
  {
    $values['grant_last_time'] = NULL;
    $values['grant_expire'] = NULL;
    $values['grant_expire_uln'] = NULL;
  }
  else
  {
    if(empty($params['grant_totaldays']))
      $values['grant_expire'] = NULL;
    elseif(isset($params['grant_totaldays']))
      $values['grant_expire'] = (time() - $DATA["time"]) + $params["grant_totaldays"] * 3600 * 24;
    if(isset($params['grant_lastuldays']))
      $values['grant_last_time'] = (empty($params['grant_lastuldays'])? 'NULL': $params["grant_lastuldays"] * 3600 * 24);
    if(isset($params['grant_maxul']))
      $values['grant_expire_uln'] = (empty($params['grant_maxul'])? 'NULL': $DATA["uploads"] + (int)$params['grant_maxul']);
  }

  if(isset($params['ticket_permanent']) && $params['ticket_permanent'])
  {
    $values['last_time'] = NULL;
    $values['expire'] = NULL;
    $values['expire_dln'] = NULL;
  }
  else
  {
    if(empty($params['ticket_totaldays']))
      $values['expire'] = 'NULL';
    elseif(isset($params['ticket_totaldays']))
      $values['expire'] = $params["ticket_totaldays"] * 3600 * 24;
    if(isset($params['ticket_lastdldays']))
      $values['last_time'] = (empty($params['ticket_lastdldays'])? 'NULL': $params["ticket_lastdldays"] * 3600 * 24);
    if(isset($params['ticket_maxdl']))
      $values['expire_dln'] = (empty($params['ticket_maxdl'])? 'NULL': (int)$params['ticket_maxdl']);
  }

  // prepare the query
  if (!DBConnection::getInstance()->updateGrant($id,$values)) {
      return false;
  }
  
  $DATA = DBConnection::getInstance()->getGrantById($id);
  $DATA['pass'] = (empty($params["pass"])? NULL: $_POST["pass"]);

  // trigger update hooks
  Hooks::getInstance()->callHook('onGrantUpdate',['grant' => $DATA]);
  return $DATA;
}


// fetch the grant id and check for permissions
$DATA = false;
$id = &$_REQUEST['id'];
if(empty($id) || !isGrantId($id))
  $id = false;
else
{
  $DATA = DBConnection::getInstance()->getGrantById($id);
  if($DATA === false || isGrantExpired($DATA)
  || (!$auth["admin"] && $DATA["user_id"] != $auth["id"]))
    $DATA = false;
}

// handle update
if($DATA)
{
  if(validateParams($grantEditParams, $_POST))
  {
    // if update succeeds, return to listings
    if(handleUpdate($DATA, $_POST))
      $DATA = false;
  }
}

// resulting page
$src = (array_key_exists(@$_REQUEST['src'], $pages)? $_REQUEST['src']: 'glist');
if($DATA === false)
  header("Location: " . tokenUrl($adminPath, array('a' => $src)));
else
  include("editgrants.php");
