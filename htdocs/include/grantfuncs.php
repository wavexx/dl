<?php
// new grant shared functions
require_once("funcs.php");
require_once("ticketfuncs.php");


function isGrantExpired($DATA, $now = NULL)
{
  if(!isset($now)) $now = time();
  $expire1 = (($DATA["grant_expire"]<>0) && ($DATA["grant_expire"] + $DATA["time"]) < $now);
  $expire2 = (($DATA["last_stamp"]<>0) && $DATA["grant_last_time"] && ($DATA["last_stamp"] + $DATA["grant_last_time"]) < $now);
  $expire3 = (($DATA["grant_expire_uln"]<>0) && $DATA["grant_expire_uln"] <= $DATA["uploads"]);
  return $expire1 || $expire2 || $expire3;
}


function grantExpiration($DATA, &$expVal = NULL)
{
  if($DATA["grant_expire_uln"] || $DATA["grant_last_time"])
  {
    if($DATA["last_stamp"] && $DATA["grant_last_time"])
    {
      $expVal = $DATA["last_stamp"] + $DATA["grant_last_time"] - time();
      return sprintf(T_("About %s"), humanTime($expVal));
    }
    elseif($DATA["grant_expire_uln"] && $DATA["uploads"])
    {
      $expVal = ($DATA["grant_expire_uln"] - $DATA["uploads"]);
      return sprintf(T_("About %d uploads"), $expVal);
    }
    elseif($DATA["grant_expire"])
    {
      $expVal = $DATA["grant_expire"] + $DATA["time"] - time();
      return sprintf(T_("About %s"), humanTime($expVal));
    }
    elseif($DATA["grant_expire_uln"])
    {
      $expVal = $DATA["grant_expire_uln"];
      return sprintf(T_("After %d uploads"), $expVal);
    }
    else
    {
      $expVal = $DATA["grant_last_time"];
      return sprintf(T_("%s after next upload"), humanTime($expVal));
    }
  }
  elseif($DATA["grant_expire"])
  {
    $expVal = $DATA["grant_expire"] + $DATA["time"] - time();
    return sprintf(T_("In %s"), humanTime($expVal));
  }

  $expVal = 4294967295;
  return ("<strong>" . T_("Never") . "</strong>");
}


function grantExpirationParams($params)
{
  global $defaults;

  if(!isset($params["grant_expiry"]))
  {
    if(!isset($params["grant_total"]) && !isset($params["grant_lastul"]) && !isset($params["grant_maxul"]))
      $params["grant_expiry"] = "auto";
    else
      $params["grant_expiry"] = "custom";
  }
  if($params["grant_expiry"] === "never")
  {
    $total = "NULL";
    $lastul = "NULL";
    $maxul = "NULL";
  }
  elseif($params["grant_expiry"] === "once")
  {
    $total = ($defaults['grant']['total'] == 0)? "NULL": $defaults['grant']['total'];
    $lastul = "NULL";
    $maxul = 1;
  }
  elseif($params["grant_expiry"] === "auto")
  {
    $total = ($defaults['grant']['total'] == 0)? "NULL": $defaults['grant']['total'];
    $lastul = ($defaults['grant']['lastul'] == 0)? "NULL": $defaults['grant']['lastul'];
    $maxul = ($defaults['grant']['maxul'] == 0)? "NULL": $defaults['grant']['maxul'];
  }
  else
  {
    $total = (empty($params["grant_total"])? 'NULL': $params["grant_total"]);
    $lastul = (empty($params["grant_lastul"])? 'NULL': (int)$params["grant_lastul"]);
    $maxul = (empty($params["grant_maxul"])? 'NULL': (int)$params["grant_maxul"]);
  }

  return array($total, $lastul, $maxul);
}


function genGrant($params)
{
  global $auth, $locale;

  // generate new unique id
  $id = genGrantId();

  // parameters
  if(!empty($params["comment"]))
    $params["comment"] = trim($params["comment"]);

  // expiration values
  list($grant_total, $grant_lastul, $grant_maxul) = grantExpirationParams($params);
  list($ticket_total, $ticket_lastdl, $ticket_maxdl) = ticketExpirationParams($params);
  
  $ret = DBConnection::getInstance()->createGrant($id,
                                           $auth['id'],
                                           $grant_total,
                                           $grant_lastul,
                                           $grant_maxul,
                                           (empty($params["comment"])? NULL:$params["comment"]),
                                           (empty($params["pass"])? NULL: hashPassword($params["pass"])),
                                           (!isset($params["pass_send"])? true: to_boolean($params["pass_send"])),
                                           time(),
                                           $ticket_total,
                                           $ticket_lastdl, /* 10 */
                                           $ticket_maxdl,
                                           (empty($params["notify"])? NULL: fixEMailAddrs($params["notify"])),
                                           (empty($params["send_to"])? NULL: fixEMailAddrs($params["send_to"])),
                                           $locale);
   if (!$ret) {       
    logDBError(null, "cannot commit new grant to database");
    return false;
  }
  
  $DATA = DBConnection::getInstance()->getGrantById($id);
  $DATA['pass'] = (empty($params["pass"])? NULL: $params["pass"]);

  // trigger creation hooks
  Hooks::getInstance()->callHook('onGrantCreate',['grant' => $DATA]);
  
  return $DATA;
}


// parameters validation
$grantRestParams = array
(
  'notify'           => array
  (
    'required'	     => true,
    'funcs'          => array('is_email_list1'),
  ),
  'comment'          => 'is_string',
  'pass'             => 'is_string',
  'pass_send'        => 'is_boolean',
  'grant_total'      => 'is_numeric_int',
  'grant_lastul'     => 'is_numeric_int',
  'grant_maxul'      => 'is_numeric_int',
  'grant_expiry'     => 'is_expiry_choice',
  'ticket_total'     => 'is_numeric_int',
  'ticket_lastdl'    => 'is_numeric_int',
  'ticket_maxdl'     => 'is_numeric_int',
  'ticket_expiry'    => 'is_expiry_choice',
  'ticket_permanent' => 'is_boolean',
  'send_to'          => 'is_email_list',
  'permanent'        => false,
);

$grantNewParams = array
(
  'notify'            => array
  (
    'required'	      => true,
    'funcs'           => array('is_email_list1'),
  ),
  'comment'           => 'is_string',
  'pass'              => 'is_string',
  'pass_send'         => 'is_boolean',
  'grant_totaldays'   => 'is_numeric',
  'grant_lastuldays'  => 'is_numeric',
  'grant_maxul'       => 'is_numeric_int',
  'grant_expiry'      => 'is_expiry_choice',
  'ticket_totaldays'  => 'is_numeric',
  'ticket_lastdldays' => 'is_numeric',
  'ticket_maxdl'      => 'is_numeric_int',
  'ticket_expiry'     => 'is_expiry_choice',
  'ticket_permanent'  => false,
  'send_to'           => 'is_email_list',
  'permanent'         => false,
);

$grantEditParams = $grantNewParams;
$grantEditParams['pass_clear'] = 'is_boolean';
$grantEditParams['grant_permanent'] = 'is_boolean';
$grantEditParams['ticket_permanent'] = 'is_boolean';

$grantUseParams = array
(
  'comment'           => 'is_string',
);
