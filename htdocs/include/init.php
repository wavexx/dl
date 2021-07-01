<?php
// basic initialization
require_once("prelude.php");
require_once("confwrap.php");
require_once("dbfuncs.php");
require_once("lang.php");

// check data dirs
if(!is_readable($spoolDir) || !is_writable($spoolDir))
  die("cannot access spool directory\n");
if(!file_exists($dataDir))
{
  if(!mkdir($dataDir))
    die("cannot initialize data directory\n");
}

// initialize logging
if($useSysLog)
  $ret = openlog($logFile, 0, LOG_LOCAL0);
elseif(!empty($logFile))
  $ret = $logFd = fopen($logFile, "at");
if(@$ret === false)
  die("cannot initialize logging\n");
else
  ini_set('display_errors', 0);

// initial state
$UPLOAD_ERRNO = UPLOAD_ERR_OK;

// set the initial default locale/timezone
$locale = $defLocale;
switchLocale($locale);
date_default_timezone_set($defTimezone);
