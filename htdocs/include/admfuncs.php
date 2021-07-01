<?php
// administrative functions
require_once("funcs.php");
require_once("fatal.php");


function restart_session()
{
  session_regenerate_id();
  $_SESSION['token'] = randomToken();
}


function ticketPurge($DATA, $auto = true)
{
   error_log(print_r($DATA,true));
   if (DBConnection::getInstance()->purgeTicketById($DATA['id'])) {
      unlink($DATA["path"]);
      Hooks::getInstance()->callHook('onTicketPurge',['ticket' => $DATA,'auto' => $auto]);
  }
}


function grantPurge($DATA, $auto = true)
{
  if (DBConnection::getInstance()->purgeGrantById($DATA['id'])) {
      Hooks::getInstance()->callHook('onGrantPurge',['grant' => $DATA,'auto' => $auto]);
  }
}


function init()
{
  global $gcInternal, $gcProbability;

  if($gcInternal === true
  && ($gcProbability === 1.
   || (mt_rand() / mt_getrandmax() < $gcProbability)))
    runGc();
}


function runGc()
{
  global $gcLimit;
  foreach(DBConnection::getInstance()->getTicketsToPurge(time(),$gcLimit) as $DATA) {
    print_r($DATA);
    ticketPurge($DATA);
  }
  
  foreach(DBConnection::getInstance()->getGrantsToPurge(time(),$gcLimit) as $DATA) {
    grantPurge($DATA);
  }
}


function genTicketId()
{
  global $dataDir, $maxUUTries;

  $tries = $maxUUTries;
  do
  {
    $id = randomToken();
    $tmpFile = "$dataDir/$id";
  }
  while(@fopen($tmpFile, "x") === FALSE && --$tries);
  if(!$tries)
  {
    logError("cannot generate unique ticket ID");
    httpInternalError();
  }

  return array($id, $tmpFile);
}


function genGrantId()
{
  global $maxUUTries;

  $tries = $maxUUTries;
  do {
    $id = randomToken();
  }
  while((DBConnection::getInstance()->getGrantById($id)!==false) && --$tries);
  if(!$tries)
  {
    logError("cannot generate unique grant ID");
    httpInternalError();
  }

  return $id;
}


function userAdd($user, $pass, $admin, $email = false)
{
  global $maxUserLen, $maxPassLen;

  // validate user/password sizes
  if(strlen($user) > $maxUserLen || strlen($pass) > $maxPassLen)
    return false;
  
  if ($admin) {
      $role = DBConnection::getInstance()->getRoleByName('admin');
  }
  else {
      $role = DBConnection::getInstance()->getRoleByName('user');
  }
  if ($role===FALSE) {
      throw new \Exception("Could not find role");
  }
  
  if (empty($pass)) {
      $pass = null;
  }
  else {
      $pass = hashPassword($pass);
  }
  
  $result = DBConnection::getInstance()->createUser($user,
                                                      (empty($pass)? NULL : hashPassword($pass)),
                                                      $role['id'],
                                                      (empty($email)? NULL : $email));
  logEvent("adding user $user: " . ($result? "success": "fail"),
            ($result? LOG_INFO: LOG_ERR));
  return $result;
}


function userDel($user)
{
  $result = DBConnection::getInstance()->deleteUser($user);
  logEvent("deleting user $user: " . ($result? "success": "fail"),
      ($result? LOG_INFO: LOG_ERR));
  return $ret;
}


function userUpd($user, $pass = null, $admin = null, $email = null)
{
  global $maxUserLen, $maxPassLen;

  // validate user/password sizes
  if(strlen($user) > $maxUserLen || strlen($pass) > $maxPassLen)
    return false;

  // prepare the SQL
  $values = array();
  if(!is_null($pass))
  {
      $values['pass_ph'] = hashPassword($pass);
  }
  if(!is_null($admin))
  {
      $role = DBConnection::getInstance()->getRoleByName(($admin? 'admin': 'user'));
      $values['role_id'] = $role['id'];
  }
  if(!is_null($email))
  {
      $values['email'] = (empty($email)? NULL: $email);
  }
  
  if (count(array_keys($values))==0) {
      return false;
  }
  $ret = DBConnection::getInstance()->updateUser($user, $values);
  
  $msg = array();
  if(!is_null($pass)) $msg[] = "password";
  if(!is_null($admin)) $msg[] = "role";
  if(!is_null($email)) $msg[] = "email";
  logEvent("updating user $user (" . join(", ", $msg)
      . "): " . ($ret? "success": "fail"), ($ret? LOG_INFO: LOG_ERR));
  return $ret;
}


function userAdm($user)
{
  return DBConnection::getInstance()->userIsAdmin($user);
}


function userCheck($user, $pass)
{
  return userLogin($user, $pass, false);
}


function hasPassHash($DATA)
{
  return isset($DATA['pass_ph']);
}

function checkPassHash($table, $DATA, $pass)
{
  global $maxPassLen;

  // validate password size
  if(strlen($pass) > $maxPassLen)
    return false;

  if(!$DATA || empty($pass) || isset($DATA['pass_ph']))
  {
    $hash = ($DATA !== false? $DATA['pass_ph']: '*');
    return password_verify($pass, $hash);
  }
  return false;
}


function userLogin($user, $pass, $rmt, $email = false)
{
  global $maxUserLen, $maxPassLen;

  // validate user/password sizes
  if(strlen($user) > $maxUserLen || strlen($pass) > $maxPassLen)
    return false;

  // fetch the user
  $DATA = DBConnection::getInstance()->getUserByName($user);
  // remote auth doesn't check pass, but still needs an id stub
  if($rmt)
  {
    if(!$DATA)
    {
      $role = DBConnection::getInstance()->getRoleByName('user');
      if (!DBConnection::getInstance()->createUser($user,null,$role['id'],$email)) {
          return false;
      }
      $DATA = DBConnection::getInstance()->getUserByName($user);
    }
    return $DATA;
  }

  // validate the user
  $ret = checkPassHash('user', $DATA, $pass);
  logEvent("login attempt for user $user: " . ($ret? "success": "fail"),
      ($ret? LOG_INFO: LOG_ERR));
  return ($ret? $DATA: false);
}
