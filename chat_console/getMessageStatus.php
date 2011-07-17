<?php

    require('../../config.php');

    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
    header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
    header("Cache-Control: no-cache, must-revalidate" );
    header("Pragma: no-cache" );
    header("Content-Type: text/plain; charset=utf-8");

    $messages = $DB->get_records_select('message',
            "useridto = ?", array($_GET['myId']),
            'timecreated');

    $json = '{messages:';

    if($messages)
    {
        $json .= '[ ';
        foreach($messages as $message)
        {
            $myname = $DB->get_field('user','firstname',array('id'=>($message->useridto)))
                    ." ".$DB->get_field('user','lastname',array('id'=>($message->useridto)));
            $yourname = $DB->get_field('user','firstname',array('id'=>($message->useridfrom)))
                    ." ".$DB->get_field('user','lastname',array('id'=>($message->useridfrom)));
            $json .= '{';
            $json .= '"id":  "' . $message->id . '",';
            $json .= '"name": "' . htmlspecialchars($yourname) . '",';
            $json .= '"me": "' . htmlspecialchars($myname) . '",';
            $json .= '"myId": "' . $message->useridto . '",';
            $json .= '"yourId": "' . $message->useridfrom . '",';
            $json .= '"time": "' . $message->timecreated . '"';
            $json .= '},';
        }
        $json = substr($json, 0, strlen($json)-1);
        $json .= ']}';
        $json = str_replace("\n", "", $json);
//              $json = json_encode($messages);
    }
    else
    {
            //Send an empty message to avoid a Javascript error when we check for message lenght in the loop.
            $json .= '[]}';
    }

    echo $json;

?>
