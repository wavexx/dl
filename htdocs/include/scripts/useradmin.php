#!/usr/bin/env php
<?php
if(!isset($argc)) die("not running from the command line\n");

// initialize
require_once("../init.php");
require_once("admfuncs.php");

// parse the command line
if($argc < 2 || $argv[1] == 'help')
{
  echo(<<<EOD
Usage: $argv[0] command [args]

  help               : this help
  list               : list all users and their administrator status
  add user adm [pass]: add "user", setting its administrator status with
                       "adm" ("true" or "false") and the optional
                       password "pass".
  rst user adm [pass]: reset the admin status or password for "user".
  passwd user [pass] : just reset the password for "user".
  rm user            : remove "user"

EOD
);
  exit(2);
}

if($argv[1] == 'list')
{
  echo "#user\tadm\n";
  foreach(DBConnection::getInstance()->getAllUsers() as $DATA)
    echo $DATA["name"] . "\t" . ($DATA["admin"]? "true": "false") . "\n";

  exit(0);
}

if($argv[1] == 'add' && $argc > 3 && $argc < 6)
{
  $user = $argv[2];
  $admin = !strcasecmp($argv[3], "true");
  $pass = ($argc > 4? $argv[4]: false);
  if(!userAdd($user, $pass, $admin))
    die("cannot add user '$user'\n");

  exit(0);
}

if($argv[1] == 'rst' && $argc > 3 && $argc < 6)
{
  $user = $argv[2];
  $admin = !strcasecmp($argv[3], "true");
  $pass = ($argc > 4? $argv[4]: false);
  if(!userUpd($user, $pass, $admin))
    die("cannot reset user '$user'\n");

  exit(0);
}

if($argv[1] == 'passwd' && $argc > 2 && $argc < 5)
{
  $user = $argv[2];
  $pass = ($argc > 3? $argv[3]: false);
  if(!userUpd($user, $pass))
    die("cannot reset password for '$user'\n");

  exit(0);
}

if($argv[1] == 'rm' && $argc > 2)
{
  $user = $argv[2];
  if(!userDel($user))
    die("cannot remove user '$user'\n");

  exit(0);
}

echo "$argv[0]: bad arguments, see $argv[0] help\n";
exit(2);
