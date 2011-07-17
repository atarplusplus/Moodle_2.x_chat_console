<?php

    require('../../config.php');

    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
    header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
    header("Cache-Control: no-cache, must-revalidate" );
    header("Pragma: no-cache" );
    header("Content-Type: text/plain; charset=utf-8");

    $timetoshowusers = 300; //Seconds default
    if (isset($CFG->block_chat_console_timetosee)) {
        $timetoshowusers = $CFG->block_chat_console_timetosee * 60;
    }
    $timefrom = 100 * floor((time()-$timetoshowusers) / 100); // Round to nearest 100 seconds for better query cache

    // Get context so we can check capabilities.
    $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);

    //Calculate if we are in separate groups
    $isseparategroups = ($COURSE->groupmode == SEPARATEGROUPS
                         && $COURSE->groupmodeforce
                         && !has_capability('moodle/site:accessallgroups', $context));

    //Get the user current group
    $currentgroup = $isseparategroups ? groups_get_course_group($COURSE) : NULL;

    $groupmembers = "";
        $groupselect  = "";
        $params = array();

    //Add this to the SQL to show only group users
    if ($currentgroup !== NULL) {
            $groupmembers = ", {groups_members} gm";
            $groupselect = "AND u.id = gm.userid AND gm.groupid = :currentgroup";
            $params['currentgroup'] = $currentgroup;
    }

    $userfields = user_picture::fields('u', array('username'));

    if ($COURSE->id == SITEID) {  // Site-level
        $sql = "SELECT $userfields, MAX(u.lastaccess) AS lastaccess
                  FROM {user} u $groupmembers
                 WHERE u.lastaccess > $timefrom
                       $groupselect
              GROUP BY $userfields
              ORDER BY lastaccess DESC ";

       $csql = "SELECT COUNT(u.id), u.id
                  FROM {user} u $groupmembers
                 WHERE u.lastaccess > $timefrom
                       $groupselect
              GROUP BY u.id";

    } else {
        // Course level - show only enrolled users for now
        // TODO: add a new capability for viewing of all users (guests+enrolled+viewing)

        list($esqljoin, $eparams) = get_enrolled_sql($context);
        $params = array_merge($params, $eparams);

        $sql = "SELECT $userfields, MAX(ul.timeaccess) AS lastaccess
                  FROM {user_lastaccess} ul $groupmembers, {user} u
                  JOIN ($esqljoin) euj ON euj.id = u.id
                 WHERE ul.timeaccess > $timefrom
               AND u.id = ul.userid
                       AND ul.courseid = :courseid
                       $groupselect
              GROUP BY $userfields
              ORDER BY lastaccess DESC";

       $csql = "SELECT u.id
                  FROM {user_lastaccess} ul $groupmembers, {user} u
                  JOIN ($esqljoin) euj ON euj.id = u.id
                 WHERE ul.timeaccess > $timefrom
                       AND u.id = ul.userid
                       AND ul.courseid = :courseid
                       $groupselect
              GROUP BY u.id";

        $params['courseid'] = $COURSE->id;
    }

    //Calculate minutes
    $minutes  = floor($timetoshowusers/60);

    if ($users = $DB->get_records_sql($sql, $params, 0, 50)) {   // We'll just take the most recent 50 maximum
        foreach ($users as $user) {
            $users[$user->id]->fullname = fullname($user);
        }
    } else {
        $users = array();
    }

    if (count($users) < 50) {
        $usercount = "";
    } else {
            $usercount = $DB->count_records_sql($csql, $params);
        $usercount = ": $usercount";
    }

    $blockdata->content->text = "<div class=\"info\">(".get_string("periodnminutes","block_chat_console",$minutes)."$usercount)</div>";

    //Now, we have in users, the list of users to show
    //Because they are online
    if (!empty($users)) {
        //Accessibility: Don't want 'Alt' text for the user picture; DO want it for the envelope/message link (existing lang string).
        //Accessibility: Converted <div> to <ul>, inherit existing classes & styles.
        $blockdata->content->text .= "<ul class='list'>\n";
        if (isloggedin() && has_capability('moodle/site:sendmessage', $context)
                       && !empty($CFG->messaging) && !isguestuser()) {
            $canshowicon = true;
        } else {
            $canshowicon = false;
        }
        foreach ($users as $user)
        {
            $blockdata->content->text .= '<li class="listentry">';
            $timeago = format_time(time() - $user->lastaccess); //bruno to calculate correctly on frontpage

            $curr_user_pic = $OUTPUT->user_picture($user, array('size'=>16,'link'=>null));

            $curr_user_html = '';

            if ($user->username == 'guest')
            {
                $curr_user_html .= '<div class="user">';
                $curr_user_html .= $curr_user_pic;
                $curr_user_html .= get_string('guestuser');
                $curr_user_html .= '</div>';
            }
            else
            {
                if($USER->id == $user->id)
                    $curr_user_html .= '<div class="user" id="user'.$user->id.'" onmouseover="Highlight(1,'.$user->id.')" onmouseout="Highlight(0,'.$user->id.')">';
                else
                    $curr_user_html .= '<div class="user" id="user'.$user->id.'" onclick="Open_Div_Chat(\''.$user->fullname.'\',\''.$USER->firstname.' '.$USER->lastname.'\','.$USER->id.','.$user->id.')" onmouseover="Highlight(1,'.$user->id.')" onmouseout="Highlight(0,'.$user->id.')">';
                $curr_user_html .= $curr_user_pic;
                $curr_user_html .= $user->fullname;
                $curr_user_html .= '</div>';
            }

            $blockdata->content->text .= $curr_user_html;
            $blockdata->content->text .= "</li>\n";
        }
        $blockdata->content->text .= '</ul><div class="clearer"><!-- --></div>';
    } else {
        $blockdata->content->text .= "<div class=\"info\">".get_string("none")."</div>";
    }

    echo $blockdata->content->text;

?>
