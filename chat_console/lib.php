<?php
/// library functions for messaging

require_once($CFG->libdir.'/eventslib.php');

define ('MESSAGE_SHORTLENGTH', 300);

//$PAGE isnt set if we're being loaded by cron which doesnt display popups anyway
if (isset($PAGE)) {
    $PAGE->set_popup_notification_allowed(false); // We are in a message window (so don't pop up a new one)
}

define ('MESSAGE_DISCUSSION_WIDTH',600);
define ('MESSAGE_DISCUSSION_HEIGHT',500);

define ('MESSAGE_SHORTVIEW_LIMIT', 8);//the maximum number of messages to show on the short message history

define('MESSAGE_HISTORY_SHORT',0);
define('MESSAGE_HISTORY_ALL',1);

define('MESSAGE_VIEW_UNREAD_MESSAGES','unread');
define('MESSAGE_VIEW_RECENT_CONVERSATIONS','recentconversations');
define('MESSAGE_VIEW_RECENT_NOTIFICATIONS','recentnotifications');
define('MESSAGE_VIEW_CONTACTS','contacts');
define('MESSAGE_VIEW_BLOCKED','blockedusers');
define('MESSAGE_VIEW_COURSE','course_');
define('MESSAGE_VIEW_SEARCH','search');

define('MESSAGE_SEARCH_MAX_RESULTS', 200);

define('MESSAGE_CONTACTS_PER_PAGE',10);
define('MESSAGE_MAX_COURSE_NAME_LENGTH', 30);

if (!isset($CFG->message_contacts_refresh)) {  // Refresh the contacts list every 60 seconds
    $CFG->message_contacts_refresh = 60;
}
if (!isset($CFG->message_chat_refresh)) {      // Look for new comments every 5 seconds
    $CFG->message_chat_refresh = 5;
}
if (!isset($CFG->message_offline_time)) {
    $CFG->message_offline_time = 300;
}

/**
 * Send a message from one user to another. Will be delivered according to the message recipients messaging preferences
 * @param object $userfrom the message sender
 * @param object $userto the message recipient
 * @param string $message the message
 * @param int $format message format such as FORMAT_PLAIN or FORMAT_HTML
 * @return int|false the ID of the new message or false
 */
function message_post_message($userfrom, $userto, $message, $format) {
    global $SITE, $CFG, $USER;

    $eventdata = new stdClass();
    $eventdata->component        = 'moodle';
    $eventdata->name             = 'instantmessage';
    $eventdata->userfrom         = $userfrom;
    $eventdata->userto           = $userto;

    //using string manager directly so that strings in the message will be in the message recipients language rather than the senders
    $eventdata->subject          = get_string_manager()->get_string('unreadnewmessage', 'message', fullname($userfrom), $userto->lang);

    if ($format == FORMAT_HTML) {
        $eventdata->fullmessage      = '';
        $eventdata->fullmessagehtml  = $message;
    } else {
        $eventdata->fullmessage      = $message;
        $eventdata->fullmessagehtml  = '';
    }

    $eventdata->fullmessageformat = $format;
    $eventdata->smallmessage     = strip_tags($message);//strip just in case there are is any html that would break the popup notification

    $s = new stdClass();
    $s->sitename = $SITE->shortname;
    $s->url = $CFG->wwwroot.'/message/index.php?user='.$userto->id.'&id='.$userfrom->id;

    $emailtagline = get_string_manager()->get_string('emailtagline', 'message', $s, $userto->lang);
    if (!empty($eventdata->fullmessage)) {
        $eventdata->fullmessage .= "\n\n---------------------------------------------------------------------\n".$emailtagline;
    }
    if (!empty($eventdata->fullmessagehtml)) {
        $eventdata->fullmessagehtml .= "<br /><br />---------------------------------------------------------------------<br />".$emailtagline;
    }

    $eventdata->timecreated     = time();
    return message_send($eventdata);
}

?>