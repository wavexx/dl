<?php
// download a ticket
require_once("ticketfuncs.php");

// fetch the ticket id
if(!isset($_SERVER["PATH_INFO"]))
{
  logError("missing PATH_INFO, cannot continue");
  httpBadRequest();
}

$id = false;
if(preg_match("/^\/([^\/]+)/", $_SERVER["PATH_INFO"], $tmp)) $id = $tmp[1];
if($id === false || !isTicketId($id))
{
  logError("invalid ticket requested");
  httpNotFound();
}

// try to fetch the id
$DATA = DBConnection::getInstance()->getTicketById($id);

if($DATA === false || isTicketExpired($DATA))
{
  $category = ($DATA === false? 'unknown': 'expired');
  logError("$category ticket requested");
  httpNotFound();
}

// check for password
if(hasPassHash($DATA) && !isset($_SESSION['t'][$id]))
{
  logTicketEvent($DATA, "missing credentials", LOG_ERR);
  httpBadRequest();
}

// open the file first
$fd = fopen($DATA["path"], "r");
if($fd === false)
{
  logTicketEvent($DATA, "data file " . $DATA["path"] . " is missing!", LOG_ERR);
  httpInternalError();
}

// update range parameters
if(!empty($_SERVER["HTTP_RANGE"]))
  preg_match("/^bytes=(\d*)-(\d*)/", $_SERVER["HTTP_RANGE"], $range);
if(empty($range[1]) || $range[1] < 0 || $range[1] >= $DATA["size"])
  $range[1] = 0;
if(empty($rage[2]) || $range[2] < $range[1] || $range[2] >= $DATA["size"])
  $range[2] = $DATA["size"] - 1;
$size = max(0, $range[2] - $range[1] + 1);
$complete = ($size == $DATA["size"]);
$last = ($range[2] == $DATA["size"] - 1);

// update the record for the next query
DBConnection::getInstance()->updateTicketUsage($id,time(),0);

// disable mod_deflate
if(function_exists('apache_setenv'))
  apache_setenv('no-gzip', '1');

// send the file
header("ETag: $id");
header("Pragma: private");
header("Cache-Control: cache");
header("Accept-Ranges: bytes");
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment");
if(!$complete)
{
  header("HTTP/1.1 206 Partial Content");
  header("Content-Range: bytes $range[1]-$range[2]/" . $DATA["size"]);
}
header("Content-Length: $size");
session_write_close();
ob_end_flush();

// contents
$left = $size;
fseek($fd, $range[1]);
while($left)
{
  $data = fread($fd, 16384);
  $left -= strlen($data);
  print($data);
  flush();
}
fclose($fd);

if($last && !connection_aborted())
{
  ++$DATA["downloads"];

  // set default locale for notifications
  switchLocale($defLocale);

  // trigger download hooks
  onTicketDownload($DATA);

  // check for validity after download
  if(isTicketExpired($DATA))
    ticketPurge($DATA);
  else
  {
    // update download count
    DBConnection::getInstance()->updateTicketUsage($id,time(),1);
  }

  // kill the session ASAP
  if($auth === false)
    session_destroy();
}
