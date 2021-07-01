<?php
// process a grant
require_once("grantfuncs.php");

// try to fetch the grant
$id = $_REQUEST["g"];
if(!isGrantId($id))
{
  $id = false;
  $GRANT = false;
}
else
{
  $GRANT = DBConnection::getInstance()->getGrantById($id)   ; 
}

$ref = "$masterPath?g=$id";
if($GRANT === false || isGrantExpired($GRANT))
{
  $category = ($id === false? 'invalid': ($GRANT === false? 'unknown': 'expired'));
  logError("$category grant requested");
  includeTemplate("$style/include/nogrant.php", array('id' => $id));
  exit();
}

if(hasPassHash($GRANT) && !isset($_SESSION['g'][$id]))
{
  $ret = false;
  if(!empty($_POST['p']))
  {
    $ret = checkPassHash('"grant"', $GRANT, $_POST['p']);
    logGrantEvent($GRANT, "password attempt: " . ($ret? "success": "fail"),
		  ($ret? LOG_INFO: LOG_ERR));
  }
  if($ret)
  {
    // authorize the grant for this session
    $_SESSION['g'][$id] = array('pass' => $_POST["p"]);
  }
  else
  {
    include("grantp.php");
    exit();
  }
}


// upload handler
function useGrant($upload, $GRANT, $DATA)
{
  // populate comment with file list when empty
  if(!empty($DATA["cmt"]))
    $DATA["cmt"] = trim($DATA["cmt"]);
  if(empty($DATA["cmt"]) && count($upload['files']) > 1)
    $DATA["cmt"] = T_("Archive contents:") . "\n  " . implode("\n  ", $upload['files']);

  // start Transaction
  try {
      DBConnection::getInstance()->beginTansaction();
      $success = DBConnection::getInstance()->generateTicket($upload['id'], 
                                                              $GRANT['user_id'], 
                                                              $upload["name"],
                                                              $upload["path"],
                                                              $upload["size"],
                                                              (empty($DATA["cmt"])? NULL: $DATA["cmt"]),
                                                              (empty($GRANT["pass_ph"])? NULL: $GRANT["pass_ph"]), 
                                                              $GRANT["pass_send"],
                                                              time(),
                                                              (empty($GRANT["expire"])? NULL: $GRANT['expire']),
                                                              (empty($GRANT["last_time"])? NULL: $GRANT['last_time']),
                                                              (empty($GRANT["expire_dln"])? NULL: $GRANT['expire_dln']),
                                                              NULL,
                                                              NULL,
                                                              (empty($GRANT["locale"])? NULL: $GRANT['locale']),
                                                              $GRANT['id']);
      if (!$success) {
          logDBError(null, "cannot commit new ticket to database");
          return false;
      }
      
      // update grant
      ++$GRANT["uploads"];
      if(isGrantExpired($GRANT))
      {
          DBConnection::getInstance()->purgeGrantById($GRANT['id']);
      }
      else
      {
          DBConnection::getInstance()->updateGrantUsage(time(),1);
      }
      DBConnection::getInstance()->commit();
  }
  catch (\Exception $e) {
      DBConnection::getInstance()->rollBack();
      return false;
  }
  
  $TICKET = DBConnection::getInstance()->getTicketById($$upload['id']);
  if(!empty($GRANT['pass'])) {
      $TICKET['pass'] = $GRANT['pass'];
  }
  
  Hooks::getInstance()->callHook('onGrantUse',['grant' => $GRANT, 'ticket' => $TICKET]);
  return array($GRANT, $TICKET);
}


// handle the request
$TICKET = false;
$FILES = uploadedFiles($_FILES["file"]);
if($FILES !== false && validateParams($grantUseParams, $_POST))
{
  if(!empty($_SESSION['g'][$id]['pass']))
    $GRANT['pass'] = $_SESSION['g'][$id]['pass'];

  $DATA = array();
  if(!empty($_POST['comment']))
    $DATA['cmt'] = $_POST['comment'];

  $ret = withUpload($FILES, 'useGrant', array($GRANT, $DATA));
  if($ret !== false)
    list($GRANT, $TICKET) = $ret;
}

// resulting page
if($TICKET === false)
  include("grants.php");
else
{
  if(isGrantExpired($GRANT))
    unset($ref);
  includeTemplate("$style/include/grantr.php");

  // kill the session ASAP
  if($auth === false)
    session_destroy();
}
