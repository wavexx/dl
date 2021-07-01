<?php
// dl ticket event hooks
require_once("msg.php");

final class Hooks {
    protected static $instance = null;
    protected $hooks;
    
    public static function getInstance() {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }
    
    protected function __construct() {
        $this->hooks =
        [
            'onTicketCreate'    => ['onTicketCreate'],
            'onTicketUpdate'    => [],
            'onTicketDownload'  => ['onTicketDownload'],
            'onTicketPurge'     => ['onTicketPurge'],
            'onGrantCreate'     => ['onGrantCreate'],
            'onGrantUpdate'     => [],
            'onGrantPurge'      => ['onGrantPurge'],
            'onGrantUse'        => ['onGrantUse']
        ];
    }
    
    /**
     * Me not like clones! Me smash clones!
     */
    protected function __clone() { }
    
    public function registerHook($hookName, $callable) {
        if (!in_array($hookName,array_keys($this->hooks))) {
            throw new \Exception("Hook name unkown");
        }
        $this->hooks[$hookName][] = $callable;
        return $this;
    }
    
    public function callHook($hookName,$arrData) {
        if (!in_array($hookName,array_keys($this->hooks))) {
            throw new \Exception("Hook name unkown");
        }
        foreach($this->hooks[$hookName] as $a) {
            if (is_callable($a)) {
                $a($arrData);
            }
            else {
                throw new \Exception("Hook: " . $a . " is not callable");
            }
        }
    }
}

function onTicketCreate($DATA)
{
  global $fromAddr;

  // log
  $type = (!$DATA["expire"]? "permanent": "temporary");
  logTicketEvent($DATA, "$type ticket created");

  // send emails to recipient
  foreach(getEMailAddrs($DATA['sent_email']) as $email)
  {
    logTicketEvent($DATA, "sending link to $email");

    // please note that address splitting is performed to avoid
    // disclosing the recipient list (not normally needed)
    withLocale($DATA['locale'], 'msgTicketCreate', array($DATA, &$subject, &$body));
    mailUTF8($email, $subject, $body, "From: $fromAddr");
  }
}


function onTicketUpdate($DATA)
{
  // stub
}


function onTicketDownload($DATA)
{
  global $fromAddr;

  // log
  logTicketEvent($DATA, "downloaded by " . $_SERVER["REMOTE_ADDR"]);

  // notify if request
  if(!empty($DATA["notify_email"]))
  {
    logTicketEvent($DATA, "sending notification to " . $DATA["notify_email"]);
    withLocale($DATA['locale'], 'msgTicketDownload', array($DATA, &$subject, &$body));
    mailUTF8($DATA["notify_email"], $subject, $body, "From: $fromAddr");
  }
}


function onTicketPurge($args)
{
  global $fromAddr;
  
  $DATA = $args['ticket'];
  $auto = $args['auto'];

  // log
  $reason = ($auto? "automatically": "manually");
  logTicketEvent($DATA, "purged $reason after "
      . $DATA["downloads"] . " downloads");

  // notify if requested
  if(!empty($DATA["notify_email"]))
  {
    logTicketEvent($DATA, "sending notification to " . $DATA["notify_email"]);
    withLocale($DATA['locale'],
	($auto? 'msgTicketExpire': 'msgTicketPurge'),
	array($DATA, &$subject, &$body));
    mailUTF8($DATA["notify_email"], $subject, $body, "From: $fromAddr");
  }
}


function onGrantCreate($args)
{
  global $fromAddr;
  
  $DATA = $args['grant'];

  // log
  $type = (!$DATA["expire"]? "permanent": "temporary");
  logGrantEvent($DATA, "$type grant created");

  // send emails to recipient
  foreach(getEMailAddrs($DATA['sent_email']) as $email)
  {
    logGrantEvent($DATA, "sending link to $email");

    // please note that address splitting is performed to avoid
    // disclosing the recipient list (not normally needed)
    withLocale($DATA['locale'], 'msgGrantCreate', array($DATA, &$subject, &$body));
    mailUTF8($email, $subject, $body, "From: $fromAddr");
  }
}


function onGrantUpdate($DATA)
{
  // stub
}


function onGrantPurge($args)
{
  global $fromAddr;
  
  $DATA = $args['grant'];
  $auto = $args['auto'];

  // log
  $reason = ($auto? "automatically": "manually");
  logGrantEvent($DATA, "purged $reason");

  // notify if requested
  if(!empty($DATA["notify_email"]))
  {
    logGrantEvent($DATA, "sending notification to " . $DATA["notify_email"]);
    withLocale($DATA['locale'],
	($auto? 'msgGrantExpire': 'msgGrantPurge'),
	array($DATA, &$subject, &$body));
    mailUTF8($DATA["notify_email"], $subject, $body, "From: $fromAddr");
  }
}


function onGrantUse($args)
{
  global $fromAddr;
  
  $GRANT = $args['grant'];
  $TICKET = $args['ticket'];

  // log
  logGrantEvent($GRANT, "genenerated ticket " . $TICKET['id']
      . " by " . $_SERVER["REMOTE_ADDR"]);

  // notify
  if(!empty($GRANT['notify_email']))
  {
    logGrantEvent($GRANT, "sending link to " . $GRANT["notify_email"]);
    withLocale($GRANT['locale'], 'msgGrantUse', array($GRANT, $TICKET, &$subject, &$body));
    mailUTF8($GRANT["notify_email"], $subject, $body, "From: $fromAddr");
  }
}
