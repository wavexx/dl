<?php
// process a ticket

// try to fetch the ticket
$DATA = dba_fetch($_REQUEST["t"], $tDb);
if($DATA === false)
{
  includeTemplate("style/include/noticket.php",
      array('title' => 'Unknown ticket', 'id' => $_REQUEST["t"]));
  exit();
}
$DATA = unserialize($DATA);

// fix IE total crap by moving to a new location containing the resulting file
// name in the URL (this could be improved for browsers known to work by
// starting to send the file immediately)
header("Location: " . $dPath . "/" . $_REQUEST["t"]
    . "/" . urlencode($DATA["name"]));
?>
