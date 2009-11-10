<?php
// initialize the session and authorization

function authenticate()
{
  global $db, $authRealm;

  // external authentication (built-in methods)
  foreach(Array('PHP_AUTH_USER', 'REMOTE_USER', 'REDIRECT_REMOTE_USER') as $key)
  {
    if(isset($_SERVER[$key]))
    {
      $remoteUser = $_SERVER[$key];
      break;
    }
  }

  // authentication attempt
  if(!isset($remoteUser))
  {
    if(empty($_REQUEST['u']) || !isset($_REQUEST['p']))
    {
      // simple logout
      return false;
    }

    $user = $_REQUEST['u'];
    $pass = md5($_REQUEST['p']);
  }
  else
  {
    if(isset($_REQUEST['u']) && empty($_REQUEST['u']))
    {
      // remote logout
      Header('HTTP/1.0 401 Unauthorized');
      Header('WWW-Authenticate: Basic realm="' . $authRealm . '"');
      includeTemplate('style/include/rmtlogout.php');
      exit();
    }

    $user = $remoteUser;
  }

  // verify if we have administration rights
  $sql = "SELECT u.id, u.name, md5, admin FROM users u"
    . " LEFT JOIN roles r ON r.id = u.role_id"
    . " WHERE u.name = " . $db->quote($user);
  $DATA = $db->query($sql)->fetch();
  if($DATA !== false)
    $okpass = (isset($remoteUser) || ($pass === $DATA['md5']));
  else
  {
    $okpass = isset($remoteUser);
    if($okpass)
    {
      // create a stub user and get the id
      $sql = "INSERT INTO users (name, role_id) VALUES (";
      $sql .= $db->quote($user);
      $sql .= ", (SELECT id FROM roles WHERE name = 'user')";
      $sql .= ")";
      if($db->exec($sql) != 1) return false;

      // fetch defaults
      $sql = "SELECT u.id, u.name, admin FROM users";
      $sql .= " LEFT JOIN roles r ON r.id = u.role_id";
      $sql .= " WHERE ROWID = last_insert_rowid()";
      $DATA = $db->query($sql)->fetch();
    }
  }

  if(!$okpass) return false;
  return $DATA;
}

session_name($sessionName);
session_start();
if(!isset($_SESSION["auth"]) || isset($_REQUEST['u']))
  $_SESSION["auth"] = authenticate();
$auth = &$_SESSION["auth"];

?>