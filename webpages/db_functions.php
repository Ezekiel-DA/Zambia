<?php
//	Copyright (c) 2011-2017 The Zambia Group. All rights reserved. See copyright document for more details.

function mysql_query_XML($query_array) {
	global $linki, $message_error;
	$xml = new DomDocument("1.0", "UTF-8");
	$doc = $xml -> createElement("doc");
	$doc = $xml -> appendChild($doc);
	$multiQueryStr = "";
    foreach ($query_array as $query) {
        $query = trim($query);
        $multiQueryStr .= $query;
        if (substr($query, -1, 1) !== ";") {
            $multiQueryStr .= ";";
        }
    }
    $status = mysqli_multi_query($linki, $multiQueryStr);
    //error_log("1: $status");
    $queryNo = 0;
    $queryNameArr = array_keys($query_array);
    do {
        if ($queryNo !== 0) {
            $status = mysqli_next_result($linki);
        }
        if (!$status) {
            $message_error = $multiQueryStr . "<br />";
            $message_error .= "Error with query number " . ($queryNo + 1) . " <br />";
            $message_error .= mysqli_error($linki) . "<br />";
            //error_log("2: errored out of mysql_query_XML");
            return(FALSE);
        }
        $result = mysqli_store_result($linki);
        $queryNode = $xml -> createElement("query");
        $queryNode = $doc -> appendChild($queryNode);
        $queryNode->setAttribute("queryName", $queryNameArr[$queryNo]);
        while($row = mysqli_fetch_assoc($result)) {
            $rowNode = $xml -> createElement("row");
            $rowNode = $queryNode -> appendChild($rowNode);
            //error_log("3: \$row: ".print_r($row, true));
            foreach ($row as $fieldname => $fieldvalue) {
                if ($fieldvalue !== "" && $fieldvalue !== null) {
                    $rowNode -> setAttribute($fieldname, $fieldvalue);
                }
            }
        }
        mysqli_free_result($result);
        $queryNo++;
        //error_log("4: increment \$queryNo to $queryNo");
    } while (mysqli_more_results($linki));
	return ($xml);
}

function log_query_error($query, $error_message, $ajax) {
    error_log("mysql query error");
    error_log($query);
    error_log($error_message);
    if ($ajax) {
        echo "<span class=\"alert\">";
        echo "Error updating or querying database. ";
        echo $query . " ";
        echo $error_message;
        echo "</span>";
    } else {
        echo "<p class=\"alert alert - error\">";
        echo "Error updating or querying database.<br>\n";
        echo $query . "<br>\n";
        echo $error_message;
        echo "</p>\n";
    }
}

function mysql_query_exit_on_error($query) {
	return mysql_query_with_error_handling($query, true);
}

function mysqli_query_exit_on_error($query) {
    return mysqli_query_with_error_handling($query, true);
}

function mysql_query_with_error_handling($query, $exit_on_error = false, $ajax = false) {
    global $link, $message_error;
    $result = mysql_query($query, $link);
    if (!$result) {
        log_query_error($query, mysql_error($link), $ajax);
        if ($exit_on_error) {
            exitWithWrapup($ajax); // will exit script
        }
    }
    return $result;
}

function mysqli_query_with_error_handling($query, $exit_on_error = false, $ajax = false) {
    global $linki, $message_error;
    $result = mysqli_query($linki, $query);
    if (!$result) {
        log_query_error($query, mysqli_error($linki), $ajax);
        if ($exit_on_error) {
            exitWithWrapup($ajax); // will exit script
        }
    }
    return $result;
}

function exitWithWrapup($ajax) {
	global $header_used;
    if (!empty($header_used) && !$ajax) {
        switch ($header_used) {
            case HEADER_BRAINSTORM:
                brainstorm_footer();
                break;
            case HEADER_PARTICIPANT:
                participant_footer();
                break;
            case HEADER_STAFF:
                staff_footer();
                break;
        }
    }
    exit(-1);
};

function rollback() {
    mysql_query_with_error_handling("ROLLBACK;");
}

function rollback_mysqli($exit_on_error = false, $ajax = false) {
    global $linki;
    if (mysqli_rollback($linki)) {
        return true;
    }
    log_query_error("<ROLLBACK>", mysqli_error($linki), $ajax);
    if ($exit_on_error) {
        exitWithWrapup($ajax); // will exit script
    }
    return false;
}

function populateCustomTextArray() {
    global $customTextArray, $title;
    $customTextArray = array();
    $query = "SELECT tag, textcontents FROM CustomText WHERE page = \"$title\";";
    if (!$result = mysqli_query_with_error_handling($query))
        return (FALSE);
    while ($row = mysqli_fetch_assoc($result)) {
        $customTextArray[$row["tag"]] = $row["textcontents"];
    }
    mysqli_free_result($result);
    return true;
}

// Function prepare_db()
// Opens database channel
if (!include ('../db_name.php'))
	include ('./db_name.php'); // scripts which rely on this file (db_functions.php) may run from a different directory
//date_define_timezone_set(TIMEZONE);
function prepare_db() {
    global $link, $linki;
    $link = mysql_connect(DBHOSTNAME,DBUSERID,DBPASSWORD);
    if ($link === false)
		return (false);
	if (!mysql_select_db(DBDB,$link))
		return (false);
    if (!mysql_set_charset("utf8",$link))
        return (false);
    $linki = mysqli_connect(DBHOSTNAME, DBUSERID, DBPASSWORD, DBDB);
    if ($linki === false)
        return (false);
    return (mysqli_set_charset($linki, "utf8"));
}


// The table SessionEditHistory has a timestamp column which is automatically set to the
// current timestamp by MySQL. 
function record_session_history($sessionid, $badgeid, $name, $email, $editcode, $statusid) {
	global $linki;
	$name = mysqli_real_escape_string($linki, $name);
	$email = mysqli_real_escape_string($linki, $email);
	$query = <<<EOD
INSERT INTO SessionEditHistory
    SET
        sessionid = $sessionid,
        badgeid = "$badgeid",
        name = "$name",
        email_address = "$email",
        sessioneditcode = $editcode,
        statusid = $statusid;
EOD;
	return (mysqli_query_with_error_handling($query));
}

// Function get_name_and_email(&$name, &$email)
// Gets name and email from db if they are available and not already set
// returns FALSE if error condition encountered.  Error message in global $message_error
function get_name_and_email(&$name, &$email) {
    global $badgeid;
    if (!empty($name)) {
        return (TRUE);
    }
    if (isset($_SESSION['name'])) {
        $name = $_SESSION['name'];
        $email = $_SESSION['email'];
        //error_log("get_name_and_email found a name in the session variables.");
        return(TRUE);
    }
    if (may_I('Staff') || may_I('Participant')) { //name and email should be found in db if either set
        $query = "SELECT pubsname FROM Participants WHERE badgeid = '$badgeid';";
		if (!$result = mysqli_query_with_error_handling($query)) {
            return(FALSE);
        }
        $name = mysqli_fetch_row($result)[0];
		mysqli_free_result($result);
        if ($name === '') {
            $name = ' '; //if name is null or '' in db, set to ' ' so it won't appear unpopulated in query above
        }
        $query = "SELECT badgename, email FROM CongoDump WHERE badgeid = \"$badgeid\";";
		if (!$result = mysqli_query_with_error_handling($query)) {
            return(FALSE);
        }
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        if ($name === ' ') {
            $name = $row[0];
        }
        $email = $row[1];
    }
    return(TRUE); //return TRUE even if didn't retrieve from db because there's nothing to be done
}

// Function populate_select_from_table(...)
// Reads parameters (see below) and a specified table from the db.
// Outputs HTML of the "<OPTION>" values for a Select control.
//
function populate_select_from_table($table_name, $default_value, $option_0_text, $default_flag) {
    // set $default_value=-1 for no default value (note not really supported by HTML)
    // set $default_value=0 for initial value to be set as $option_0_text
    // otherwise the initial value will be equal to the row whose id == $default_value
    // assumes id's in the table start at 1
    // if $default_flag is true, the option 0 will always appear.
    // if $default_flag is false, the option 0 will only appear when $default_value is 0.
    if ($default_value == 0) {
        echo "<option value=\"0\" selected>$option_0_text</option>\n";
    } elseif ($default_flag) {
        echo "<option value=\"0\">$option_0_text</option>\n";
    }
    $query = "Select * FROM $table_name ORDER BY display_order;";
    if (!$result = mysqli_query_with_error_handling($query)) {
        return (FALSE);
    }
    while (list($option_value, $option_name) = mysqli_fetch_array($result, MYSQLI_NUM)) {
        echo "<option value=\"$option_value\"";
        if ($option_value == $default_value) {
            echo " selected=\"selected\"";
        }
        echo ">$option_name</option>\n";
    }
    mysqli_free_result($result);
    return (TRUE);
}

// Function populate_select_from_query(...)
// Reads parameters (see below) and a specified query for the db.
// Outputs HTML of the "<OPTION>" values for a Select control.
//
function populate_select_from_query($query, $default_value, $option_0_text, $default_flag) {
    // set $default_value=-1 for no default value (note not really supported by HTML)
    // set $default_value=0 for initial value to be set as $option_0_text
    // otherwise the initial value will be equal to the row whose id == $default_value
    // assumes id's in the table start at 1
    // if $default_flag is true, the option 0 will always appear.
    // if $default_flag is false, the option 0 will only appear when $default_value is 0.
    if ($default_value == 0) {
        echo "<option value=\"0\" selected>$option_0_text</option>\n";
    } elseif ($default_flag) {
        echo "<option value=\"0\">$option_0_text</option>\n";
    }
    $result = mysqli_query_with_error_handling($query);
    while (list($option_value, $option_name) = mysqli_fetch_array($result, MYSQLI_NUM)) {
        echo "<option value=\"$option_value\"";
        if ($option_value == $default_value)
            echo " selected";
        echo ">$option_name</option>\n";
    }
    mysqli_free_result($result);
}

// Function populate_multiselect_from_table(...)
// Reads parameters (see below) and a specified table from the db.
// Outputs HTML of the "<OPTION>" values for a Select control with
// multiple enabled.
//
function populate_multiselect_from_table($table_name, $skipset) {
    // assumes id's in the table start at 1 '
    // skipset is array of integers of values of id from table to preselect
    // error_log("Zambia->populate_multiselect_from_table->\$skipset: ".print_r($skipset,TRUE)."\n"); // only for debugging
    if ($skipset == "") {
        $skipset = array(-1);
    }
    $query = "SELECT * FROM $table_name ORDER BY display_order;";
    $result = mysqli_query_with_error_handling($query);
    while (list($option_value, $option_name) = mysqli_fetch_array($result, MYSQLI_NUM)) {
        echo "<option value=\"$option_value\"";
        if (array_search($option_value, $skipset) !== FALSE) {
            echo " selected=\"selected\"";
        }
        echo ">$option_name</option>\n";
    }
    mysqli_free_result($result);
}

// Function populate_multisource_from_table(...)
// Reads parameters (see below) and a specified table from the db.
// Outputs HTML of the "<OPTION>" values for a Select control associated
// with the *source* of an active update box.
//
function populate_multisource_from_table($table_name, $skipset) {
    // assumes id's in the table start at 1 '
    // skipset is array of integers of values of id from table not to include
    if ($skipset == "") {
        $skipset = array(-1);
    }
    $query = "SELECT * FROM $table_name ORDER BY display_order;";
    $result = mysqli_query_with_error_handling($query);
    while (list($option_value, $option_name) = mysqli_fetch_array($result, MYSQLI_NUM)) {
        if (array_search($option_value, $skipset) === false) {
            echo "<option value=\"$option_value\" >$option_name</option>\n";
        }
    }
    mysqli_free_result($result);
}

// Function populate_multidest_from_table(...)
// Reads parameters (see below) and a specified table from the db.
// Outputs HTML of the "<OPTION>" values for a Select control associated
// with the *destination* of an active update box.
//
function populate_multidest_from_table($table_name, $skipset) {
    // assumes id's in the table start at 1                        '
    // skipset is array of integers of values of id from table to include
    // in "dest" because they were skipped from "source"
    if ($skipset == "") {
        $skipset = array(-1);
    }
    $query = "SELECT * FROM $table_name ORDER BY display_order;";
    $result = mysqli_query_with_error_handling($query);
    while (list($option_value, $option_name) = mysqli_fetch_array($result, MYSQLI_NUM)) {
        if (array_search($option_value, $skipset) !== false) {
            echo "<option value=\"$option_value\" >$option_name</option>\n";
        }
    }
    mysqli_free_result($result);
}

// Function update_session()
// Takes data from global $session array and updates
// the tables Sessions, SessionHasFeature, and SessionHasService.
//
function update_session() {
    global $linki, $session, $message2;
    $session2 = array();
    $session2["track"] = filter_var($session["track"], FILTER_SANITIZE_NUMBER_INT);
    $session2["type"] = filter_var($session["type"], FILTER_SANITIZE_NUMBER_INT);
    $session2["divisionid"] = filter_var($session["divisionid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pubstatusid"] = filter_var($session["pubstatusid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["languagestatusid"] = filter_var($session["languagestatusid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pubno"] = mysqli_real_escape_string($linki, $session["pubno"]);
    $session2["title"] = mysqli_real_escape_string($linki, $session["title"]);
    $session2["secondtitle"] = mysqli_real_escape_string($linki, $session["secondtitle"]);
    $session2["pocketprogtext"] = mysqli_real_escape_string($linki, $session["pocketprogtext"]);
    $session2["progguiddesc"] = mysqli_real_escape_string($linki, $session["progguiddesc"]);
    $session2["persppartinfo"] = mysqli_real_escape_string($linki, $session["persppartinfo"]);
    if (DURATION_IN_MINUTES == "TRUE") {
        $session2["duration"] = conv_min2hrsmin($session["duration"]);
    } else {
        $session2["duration"] = mysqli_real_escape_string($linki, $session["duration"]);
    }
    $session2["estatten"] = empty($session["atten"]) ? "NULL" : "\"{$session["atten"]}\"";
    $session2["kidscatid"] = filter_var($session["kids"], FILTER_SANITIZE_NUMBER_INT);
    $session2["signupreq"] = empty($session["signup"]) ? "0" : "1";
    $session2["invitedguest"] = empty($session["invguest"]) ? "0" : "1";
    $session2["roomsetid"] = filter_var($session["roomset"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pocketprogtext"] = mysqli_real_escape_string($linki, $session["pocketprogtext"]);
    $session2["notesforpart"] = mysqli_real_escape_string($linki, $session["notesforpart"]);
    $session2["servnotes"] = mysqli_real_escape_string($linki, $session["servnotes"]);
    $session2["status"] = filter_var($session["status"], FILTER_SANITIZE_NUMBER_INT);
    $session2["notesforprog"] = mysqli_real_escape_string($linki, $session["notesforprog"]);
    $id = filter_var($session["sessionid"], FILTER_SANITIZE_NUMBER_INT);

    $query=<<<EOD
UPDATE Sessions SET
        trackid="{$session2["track"]}",
        typeid="{$session2["type"]}",
        divisionid="{$session2["divisionid"]}",
        pubstatusid="{$session2["pubstatusid"]}",
        languagestatusid="{$session2["languagestatusid"]}",
        pubsno="{$session2["pubno"]}",
        title="{$session2["title"]}",
        secondtitle="{$session2["secondtitle"]}",
        pocketprogtext="{$session2["pocketprogtext"]}",
        progguiddesc="{$session2["progguiddesc"]}",
        persppartinfo="{$session2["persppartinfo"]}",
        duration="{$session2["duration"]}",
        estatten={$session2["estatten"]},
        kidscatid="{$session["kidscatid"]}",
        signupreq={$session2["signupreq"]},
        invitedguest={$session2["invitedguest"]},
        roomsetid="{$session2["roomsetid"]}",
        notesforpart="{$session2["notesforpart"]}",
        servicenotes="{$session2["servnotes"]}",
        statusid="{$session2["status"]}",
        notesforprog="{$session2["notesforprog"]}"
    WHERE
        sessionid = $id;
EOD;
    if (!mysqli_query_with_error_handling($query)) {
        return false;
    }
    $query = "DELETE FROM SessionHasFeature WHERE sessionid = $id;";
    if (!mysqli_query_with_error_handling($query)) {
        return false;
    }
    if (!empty($session["featdest"])) {
        $query = "INSERT INTO SessionHasFeature (sessionid, featureid) VALUES ";
        for ($i = 0 ; !empty($session["featdest"][$i]) ; $i++ ) {
            $thisFeat = filter_var($session["featdest"][$i], FILTER_SANITIZE_NUMBER_INT);
            $query .= "($id, $thisFeat),";
        }
        $query = substr($query, 0, -1) . ";"; // drop trailing comma
        if (!mysqli_query_with_error_handling($query)) {
            return false;
        }
    }
    $query = "DELETE FROM SessionHasService WHERE sessionid = $id;";
    if (!mysqli_query_with_error_handling($query)) {
        return false;
    }
    if (!empty($session["servdest"])) {
        $query = "INSERT INTO SessionHasService (sessionid, serviceid) VALUES ";
        for ($i = 0 ; !empty($session["servdest"][$i]) ; $i++ ) {
            $thisServ = filter_var($session["servdest"][$i], FILTER_SANITIZE_NUMBER_INT);
            $query .= "($id, $thisServ),";
        }
        $query = substr($query, 0, -1) . ";"; // drop trailing comma
        if (!mysqli_query_with_error_handling($query)) {
            return false;
        }
    }
    $query = "DELETE FROM SessionHasPubChar WHERE sessionid = $id;";
    if (!mysqli_query_with_error_handling($query)) {
        return false;
    }
    if (!empty($session["pubchardest"])) {
        $query = "INSERT INTO SessionHasPubChar (sessionid, pubcharid) VALUES ";
        for ($i = 0 ; !empty($session["pubchardest"][$i]) ; $i++ ) {
            $thisPubChar = filter_var($session["pubchardest"][$i], FILTER_SANITIZE_NUMBER_INT);
            $query .= "($id, $thisPubChar),";
        }
        $query = substr($query, 0, -1) . ";"; // drop trailing comma
        if (!mysqli_query_with_error_handling($query)) {
            return false;
        }
    }
    return true;
}

// Function get_next_session_id()
// Reads Session table from db to determine next unused value
// of sessionid.
//
function get_next_session_id() {
    global $linki;
    $result = mysqli_query_with_error_handling("SELECT MAX(sessionid) FROM Sessions;");
    if (!$result) {
        return "";
    }
    list($maxid) = mysqli_fetch_array($result, MYSQLI_NUM);
    mysqli_free_result($result);
    if (!$maxid) {
        return "1";
    }
    return $maxid + 1;
}

// Function insert_session()
// Takes data from global $session array and creates new rows in
// the tables Sessions, SessionHasFeature, and SessionHasService.
//
function insert_session() {
    global $session, $linki;
    $query = "INSERT INTO Sessions SET ";
    $query .= "trackid=" . $session["track"] . ',';
    $temp = $session["type"];
    $query .= "typeid=" . (($temp == 0) ? "null" : $temp) . ", ";
    $temp = $session["divisionid"];
    $query .= "divisionid=" . (($temp == 0) ? "null" : $temp) . ", ";
    $query .= "pubstatusid=" . $session["pubstatusid"] . ',';
    $query .= "languagestatusid=" . $session["languagestatusid"] . ',';
    $query .= "pubsno=\"" . mysql_real_escape_string($session["pubno"], $link) . '",';
    $query .= "title=\"" . mysql_real_escape_string($session["title"], $link) . '",';
    $query .= "secondtitle=\"" . mysql_real_escape_string($session["secondtitle"], $link) . '",';
    $query .= "pocketprogtext=\"" . mysql_real_escape_string($session["pocketprogtext"], $link) . '",';
    $query .= "progguiddesc=\"" . mysql_real_escape_string($session["progguiddesc"], $link) . '",';
    $query .= "persppartinfo=\"" . mysql_real_escape_string($session["persppartinfo"], $link) . '",';
    if (DURATION_IN_MINUTES == "TRUE") {
        $query .= "duration=\"" . conv_min2hrsmin($session["duration"]) . "\", ";
    } else {
        $query .= "duration=\"" . mysql_real_escape_string($session["duration"], $link) . "\", ";
    }
    $query .= "estatten=" . ($session["atten"] != "" ? $session["atten"] : "null") . ',';
    $query .= "kidscatid=" . $session["kids"] . ',';
    $query .= "signupreq=";
    if ($session["signup"]) {
        $query .= "1,";
    } else {
        $query .= "0,";
    }
    $temp = $session["roomset"];
    $query .= "roomsetid=" . (($temp == 0) ? "null" : $temp) . ", ";
    $query .= "notesforpart=\"" . mysql_real_escape_string($session["notesforpart"], $link) . '",';
    $query .= "servicenotes=\"" . mysql_real_escape_string($session["servnotes"], $link) . '",';
    $query .= "statusid=" . $session["status"] . ',';
    $query .= "notesforprog=\"" . mysql_real_escape_string($session["notesforprog"], $link) . '",';
    $query .= "warnings=0,invitedguest="; // warnings db field not editable by form
    if ($session["invguest"]) {
        $query .= "1";
    } else {
        $query .= "0";
    }
    $result = mysql_query($query, $link);
    if (!$result) {
        $message_error = mysql_error($link);
        return $result;
    }
    $id = mysql_insert_id($link);
    if ($session["featdest"] != "") {
        for ($i = 0; $session["featdest"][$i] != ""; $i++) {
            $query = "INSERT INTO SessionHasFeature SET sessionid=" . $id . ", featureid=";
            $query .= $session["featdest"][$i];
            $result = mysql_query($query, $link);
        }
    }
    if ($session["servdest"] != "") {
        for ($i = 0; $session["servdest"][$i] != ""; $i++) {
            $query = "INSERT INTO SessionHasService SET sessionid=" . $id . ", serviceid=";
            $query .= $session["servdest"][$i];
            $result = mysql_query($query, $link);
        }
    }
    if ($session["pubchardest"] != "") {
        for ($i = 0; $session["pubchardest"][$i] != ""; $i++) {
            $query = "INSERT INTO SessionHasPubChar SET sessionid=" . $id . ", pubcharid=";
            $query .= $session["pubchardest"][$i];
            $result = mysql_query($query, $link);
        }
    }

    return $id;
}

// Function filter_session()
// Takes data from global $session array returns array with filtered data
//
function filter_session() {
    global $linki, $session;
    $session2 = array();
    $session2["track"] = filter_var($session["track"], FILTER_SANITIZE_NUMBER_INT);
    $session2["type"] = filter_var($session["type"], FILTER_SANITIZE_NUMBER_INT);
    $session2["divisionid"] = filter_var($session["divisionid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pubstatusid"] = filter_var($session["pubstatusid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["languagestatusid"] = filter_var($session["languagestatusid"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pubno"] = mysqli_real_escape_string($linki, $session["pubno"]);
    $session2["title"] = mysqli_real_escape_string($linki, $session["title"]);
    $session2["secondtitle"] = mysqli_real_escape_string($linki, $session["secondtitle"]);
    $session2["pocketprogtext"] = mysqli_real_escape_string($linki, $session["pocketprogtext"]);
    $session2["progguiddesc"] = mysqli_real_escape_string($linki, $session["progguiddesc"]);
    $session2["persppartinfo"] = mysqli_real_escape_string($linki, $session["persppartinfo"]);
    if (DURATION_IN_MINUTES == "TRUE") {
        $session2["duration"] = conv_min2hrsmin($session["duration"]);
    } else {
        $session2["duration"] = mysqli_real_escape_string($linki, $session["duration"]);
    }
    $session2["estatten"] = empty($session["atten"]) ? "NULL" : "\"{$session["atten"]}\"";
    $session2["kidscatid"] = filter_var($session["kids"], FILTER_SANITIZE_NUMBER_INT);
    $session2["signupreq"] = empty($session["signup"]) ? "0" : "1";
    $session2["invitedguest"] = empty($session["invguest"]) ? "0" : "1";
    $session2["roomsetid"] = filter_var($session["roomset"], FILTER_SANITIZE_NUMBER_INT);
    $session2["pocketprogtext"] = mysqli_real_escape_string($linki, $session["pocketprogtext"]);
    $session2["notesforpart"] = mysqli_real_escape_string($linki, $session["notesforpart"]);
    $session2["servnotes"] = mysqli_real_escape_string($linki, $session["servnotes"]);
    $session2["status"] = filter_var($session["status"], FILTER_SANITIZE_NUMBER_INT);
    $session2["notesforprog"] = mysqli_real_escape_string($linki, $session["notesforprog"]);
    $session2["id"] = filter_var($session["sessionid"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($session["featdest"])) {
        $session2["features"] = array();
        foreach ($session["featdest"] as $feature) {
            $session2["features"][] = filter_var($feature, FILTER_SANITIZE_NUMBER_INT);
        }
    }
    if (!empty($session["servdest"])) {
        $session2["services"] = array();
        foreach ($session["servdest"] as $service) {
            $session2["services"][] = filter_var($service, FILTER_SANITIZE_NUMBER_INT);
        }
    }
    if (!empty($session["pubchardest"])) {
        $session2["pubchars"] = array();
        foreach ($session["pubchardest"] as $pubchar) {
            $session2["pubchars"][] = filter_var($pubchar, FILTER_SANITIZE_NUMBER_INT);
        }
    }

    return $session2;
}

// Function retrieve_session_from_db()
// Reads Sessions, SessionHasFeature, and SessionHasService tables
// from db to populate global array $session.
//
function retrieve_session_from_db($sessionid) {
    global $session;
    global $link,$message2;
    $query= <<<EOD
select
        sessionid, trackid, typeid, divisionid, pubstatusid, languagestatusid, pubsno,
        title, secondtitle, pocketprogtext, progguiddesc, persppartinfo, duration,
        estatten, kidscatid, signupreq, roomsetid, notesforpart, servicenotes,
        statusid, notesforprog, warnings, invitedguest, ts
    from
        Sessions
    where
        sessionid=
EOD;
    $query.=$sessionid;
    $result=mysql_query($query,$link);
    if (!$result) {
        $message2=$query."<BR>\n".mysql_error($link);
        return (-3);
        }
    $rows=mysql_num_rows($result);
    if ($rows!=1) {
        $message2=$rows;
        return (-2);
        }
    $sessionarray=mysql_fetch_array($result, MYSQL_ASSOC);
    $session["sessionid"]=$sessionarray["sessionid"];
    $session["track"]=$sessionarray["trackid"];
    $session["type"]=$sessionarray["typeid"];
    $session["divisionid"]=$sessionarray["divisionid"];
    $session["pubstatusid"]=$sessionarray["pubstatusid"];
    $session["languagestatusid"]=$sessionarray["languagestatusid"];
    $session["pubno"]=$sessionarray["pubsno"];
    $session["title"]=$sessionarray["title"];
    $session["secondtitle"]=$sessionarray["secondtitle"];
    $session["pocketprogtext"]=$sessionarray["pocketprogtext"];
    $session["progguiddesc"]=$sessionarray["progguiddesc"];
    $session["persppartinfo"]=$sessionarray["persppartinfo"];
    $timearray=parse_mysql_time_hours($sessionarray["duration"]);
    if (DURATION_IN_MINUTES=="TRUE") {
            $session["duration"]=" ".strval(60*$timearray["hours"]+$timearray["minutes"]);
            }
        else {
            $session["duration"]=" ".$timearray["hours"].":".sprintf("%02d",$timearray["minutes"]);
            }
    $session["atten"]=$sessionarray["estatten"];
    $session["kids"]=$sessionarray["kidscatid"];
    $session["signup"]=$sessionarray["signupreq"];
    $session["roomset"]=$sessionarray["roomsetid"];
    $session["notesforpart"]=$sessionarray["notesforpart"];
    $session["servnotes"]=$sessionarray["servicenotes"];
    $session["status"]=$sessionarray["statusid"];
    $session["notesforprog"]=$sessionarray["notesforprog"];
    $session["invguest"]=$sessionarray["invitedguest"];
    $result=mysql_query("SELECT featureid FROM SessionHasFeature where sessionid=".$sessionid,$link);
    if (!$result) {
        $message2=mysql_error($link);
        return (-3);
        }
    unset($session["featdest"]);
    while ($row=mysql_fetch_array($result, MYSQL_NUM)) {
        $session["featdest"][]=$row[0];
        }
    $result=mysql_query("SELECT serviceid FROM SessionHasService where sessionid=".$sessionid,$link);
    if (!$result) {
        $message2=mysql_error($link);
        return (-3);
        }
    unset($session["servdest"]);
    while ($row=mysql_fetch_array($result, MYSQL_NUM)) {
        $session["servdest"][]=$row[0];
        }
    $result=mysql_query("SELECT pubcharid FROM SessionHasPubChar where sessionid=".$sessionid,$link);
    if (!$result) {
        $message2=mysql_error($link);
        return (-3);
        }
    unset($session["pubchardest"]);
    while ($row=mysql_fetch_array($result, MYSQL_NUM)) {
        $session["pubchardest"][]=$row[0];
        }
    return (37);
    }

// Function isLoggedIn()
// Reads the session variables and checks password in db to see if user is
// logged in.  Returns true if logged in or false if not.  Assumes db already
// connected on $link.

/* The script will check login status.  If user is logged in
   it will pass control to script (???) to implement edit my contact info.
   If user not logged in, it will pass control to script (???) to
   log user in. */
/* check login script, included in db_connect.php. */

function isLoggedIn() {
    global $link,$message2;

    if (!isset($_SESSION['badgeid']) || !isset($_SESSION['password'])) {
        return false;
        }

// remember, $_SESSION['password'] will be encrypted. $_SESSION['badgeid'] should already be escaped

    $result=mysql_query("SELECT password FROM Participants where badgeid='".$_SESSION['badgeid']."'",$link);
    if (!$result) {
        $message2=mysql_error($link);
        unset($_SESSION['badgeid']);
        unset($_SESSION['password']);
// kill incorrect session variables.
        return (-3);
        }

    if (mysql_num_rows($result)!=1) {
        unset($_SESSION['badgeid']);
        unset($_SESSION['password']);
// kill incorrect session variables.
        $message2="Incorrect number of rows returned when fetching password from db.";
        return (-1);
        }

    $row=mysql_fetch_array($result, MYSQL_NUM);
    $db_pass = $row[0];

// now we have encrypted pass from DB in
//$db_pass['password'], stripslashes() just incase:

    $db_pass = stripslashes($db_pass);
    $_SESSION['password'] = stripslashes($_SESSION['password']);

    //echo $db_pass."<BR>";
    //echo $_SESSION['password']."<BR>";

//compare:

    if($_SESSION['password'] != $db_pass) {
// kill incorrect session variables.
            unset($_SESSION['badgeid']);
            unset($_SESSION['password']);
            $message2="Incorrect userid or password.";
            return (false);
            }
// valid password for username
        else {
//          $i=set_permission_set($_SESSION['badgeid']);
//          should now be part of session variables
//            if ($i!=0) {
//                error_log("Zambia: permission_set error $i\n");
//                }
            return(true); // they have correct info
            }           // in session variables.
    }


// Function retrieve_participant_from_db()
// Reads Particpants tables
// from db to populate global array $participant.
//
function retrieve_participant_from_db($badgeid) {
    global $participant;
    global $link,$message2;
    $result=mysql_query("SELECT pubsname, password, bestway, interested, bio, share_email FROM Participants where badgeid='$badgeid'",$link);
    if (!$result) {
        $message2=mysql_error($link);
        return (-3);
        }
    $rows=mysql_num_rows($result);
    if ($rows!=1) {
        $message2="Participant rows retrieved: $rows ";
        return (-2);
        }
    $participant=mysql_fetch_array($result, MYSQL_ASSOC);
    return (0);
    }
// Function getCongoData()
// Reads CongoDump table
// from db to populate global array $congoinfo.
// also calls retrieve_participant_from_db() to populate
// global array $participant
//
function getCongoData($badgeid) {
    global $message_error,$message2,$congoinfo,$link,$participant;
    $query= <<<EOD
SELECT
        badgeid,
		firstname,
		lastname,
		badgename,
		phone,
		email,
		postaddress1,
		postaddress2,
		postcity,
		poststate,
		postzip,
		postcountry
    FROM
        CongoDump
    WHERE
        badgeid = "$badgeid";
EOD;
    $result=mysql_query($query,$link);
    if (!$result) {
        $message_error=mysql_error($link)."\n<br>Database Error.<br>No further execution possible.";
        return(-1);
        };
    $rows=mysql_num_rows($result);
    if ($rows!=1) {
        $message_error=$rows." rows returned for badgeid when 1 expected.<br>Database Error.<br>No further execution possible.";
        return(-1);
        };
    if (retrieve_participant_from_db($badgeid)!=0) {
        $message_error=$message2."<br>No further execution possible.";
        return(-1);
        };
	$participant["chpw"] = ($participant["password"] == "4cb9c8a8048fd02294477fcb1a41191a");
    $participant["password"]="";
    $congoinfo=mysql_fetch_array($result, MYSQL_ASSOC);
    return(0);
    }
// Function retrieve_participantAvailability_from_db()
// Reads ParticipantAvailability and ParticipantAvailabilityTimes tables
// from db to populate global array $partAvail.
// Returns 0: success; -1: badgeid not found; -2: badgeid matches >1 row;
//         -3: other error ($message_error populated)
//
function retrieve_participantAvailability_from_db($badgeid) {
    global $partAvail;
    global $link,$message2,$message_error;
    $query= <<<EOD
Select badgeid, maxprog, preventconflict, otherconstraints, numkidsfasttrack FROM ParticipantAvailability
EOD;
    $query.=" where badgeid=\"$badgeid\"";
    $result=mysql_query($query,$link);
    if (!$result) {
        $message_error=$query."<BR>\n".mysql_error($link);
        return (-3);
        }
    $rows=mysql_num_rows($result);
    if ($rows==0) {
        return (-1);
        }
    if ($rows!=1) {
        $message_error=$query."<BR>\n returned $rows rows.";
        return (-2);
        }
    $partAvailarray=mysql_fetch_array($result, MYSQL_ASSOC);
    $partAvail["badgeid"]=$partAvailarray["badgeid"];
    $partAvail["maxprog"]=$partAvailarray["maxprog"];
    $partAvail["preventconflict"]=$partAvailarray["preventconflict"];
    $partAvail["otherconstraints"]=$partAvailarray["otherconstraints"];
    $partAvail["numkidsfasttrack"]=$partAvailarray["numkidsfasttrack"];

    if (CON_NUM_DAYS>1) {
        $query="SELECT badgeid, day, maxprog FROM ParticipantAvailabilityDays where badgeid=\"$badgeid\"";
        $result=mysql_query($query,$link);
        if (!$result) {
            $message_error=$query."<BR>\n".mysql_error($link);
            return (-3);
            }
        for ($i=1; $i<=CON_NUM_DAYS; $i++) {
            unset($partAvail["maxprogday$i"]);
            }
        if (mysql_num_rows($result)>0) {
            while ($row=mysql_fetch_array($result, MYSQL_ASSOC)) {
                $i=$row["day"];
                $partAvail["maxprogday$i"]=$row["maxprog"];
                }
            }
        }
    $query= <<<EOD
SELECT badgeid, availabilitynum, DATE_FORMAT(starttime,'%T') AS starttime, 
	DATE_FORMAT(endtime,'%T') AS endtime FROM ParticipantAvailabilityTimes
	WHERE badgeid="$badgeid" ORDER BY starttime;
EOD;
    $result=mysql_query($query,$link);
    if (!$result) {
        $message_error=$query."<BR>\n".mysql_error($link);
        return (-3);
        }
    for ($i=1; $i<=AVAILABILITY_ROWS; $i++) {
        unset($partAvail["starttimestamp_$i"]);
        unset($partAvail["endtimestamp_$i"]);
        }
    $i=1;
    while ($row=mysql_fetch_array($result, MYSQL_ASSOC)) {
        $partAvail["starttimestamp_$i"]=$row["starttime"];
        $partAvail["endtimestamp_$i"]=$row["endtime"];
        $i++;
        }
    return (0);
    }
//
// Function set_permission_set($badgeid)
// Performs complicated join to get the set of permission atoms available to the user
// Stores them in global variable $permission_set
//
function set_permission_set($badgeid) {
    global $link;
    
// First do simple permissions
    $_SESSION['permission_set']="";
    $query= <<<EOD
    Select distinct permatomtag from PermissionAtoms as PA, Phases as PH,
    PermissionRoles as PR, UserHasPermissionRole as UHPR, Permissions P where
    ((UHPR.badgeid='$badgeid' and UHPR.permroleid = P.permroleid)
        or P.badgeid='$badgeid' ) and
    (P.phaseid is null or (P.phaseid = PH.phaseid and PH.current = TRUE)) and
    P.permatomid = PA.permatomid
EOD;
    $result=mysql_query($query,$link);
//    error_log("set_permission_set query:  ".$query);
    if (!$result) {
        $message_error=$query." \n ".mysql_error($link)." \n <BR>Database Error.<BR>No further execution possible.";
        error_log("Zambia: ".$message_error);
        return(-1);
        };
    $rows=mysql_num_rows($result);
    if ($rows==0) {
        return(0);
        };
    for ($i=0; $i<$rows; $i++) {
        $onerow=mysql_fetch_array($result, MYSQL_BOTH);
        $_SESSION['permission_set'][]=$onerow[0];
        };
// Second, do <<specific>> permissions
    $_SESSION['permission_set_specific']="";
    $query= <<<EOD
    Select distinct permatomtag, elementid from PermissionAtoms as PA, Phases as PH,
    PermissionRoles as PR, UserHasPermissionRole as UHPR, Permissions P where
    ((UHPR.badgeid='$badgeid' and UHPR.permroleid = P.permroleid)
        or P.badgeid='$badgeid' ) and
    (P.phaseid is null or (P.phaseid = PH.phaseid and PH.current = TRUE)) and
    P.permatomid = PA.permatomid and
    PA.elementid is not null
EOD;
    $result=mysql_query($query,$link);
    if (!$result) {
        $message_error=$query." \n ".mysql_error($link)." \n <BR>Database Error.<BR>No further execution possible.";
        error_log("Zambia: ".$message_error);
        return(-1);
        };
    $rows=mysql_num_rows($result);
    if ($rows==0) {
        return(0);
        };
    for ($i=0; $i<$rows; $i++) {
        $_SESSION['permission_set_specific'][]=mysql_fetch_array($result, MYSQL_ASSOC);
        };

    return(0);
    }

//function db_error($title,$query,$staff)
//Populates a bunch of messages to help diagnose a db error

function db_error($title,$query,$staff) {
    global $link;
    $message="Database error.<BR>\n";
    $message.=mysql_error($link)."<BR>\n";
    $message.=$query."<BR>\n";
    RenderError($title,$message);
    }

//function get_idlist_from_db($table_name,$id_col_name,$desc_col_name,$desc_col_match);
// Returns a string with a list of id's from a configuration table

function get_idlist_from_db($table_name,$id_col_name,$desc_col_name,$desc_col_match) {
    global $link;
//    error_log("zambia - get_idlist_from_db: desc_col_match: $desc_col_match");
    $query = "SELECT GROUP_CONCAT($id_col_name) from $table_name where ";
    $query.= "$desc_col_name in ($desc_col_match)";
//    error_log("zambia - get_idlist_from_db: query: $query");
    $result=mysql_query($query,$link);
    return(mysql_result($result,0));
    }

//function unlock_participant($badgeid);
//Removes all locks from participant table for participant in parameter
//and all locks held by the user known from the session
//call with $badgeid='' to unlock based on user only

// PBO 1/23/2017 Currently no biolockedby field in Participants table, so don't use this function.
function unlock_participant($badgeid) {
    global $query,$link;
    $query='UPDATE Participants SET biolockedby=NULL WHERE ';
    if (isset($_SESSION['badgeid'])) {
            $query.="biolockedby='".$_SESSION['badgeid']."'";
            if ($badgeid!='') {
                $query.=" or badgeid='$badgeid'";
                }
            }
        else {
            if ($badgeid!='') {
                    $query.="badgeid='$badgeid'";
                    }
                else {
                    return(0); //can't find anything to unlock
                    }
            }
    //error_log("Zambia: unlock_participants: ".$query);
    $result=mysql_query($query,$link);
    if (!$result) {
            return (-1);
            }
        else {
            return (0);
            }
    }

// Function get_sstatus()
// Populates the global sstatus array from the database

function get_sstatus() {
    global $link, $sstatus;
    $query = "SELECT statusid, may_be_scheduled, validate from SessionStatuses";
    $result=mysqli_query($query,$link);
    while ($arow = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $statusid=$arow['statusid'];
        $may_be_scheduled=($arow['may_be_scheduled']==1?1:0);
        $validate=($arow['validate']==1?1:0);
        $sstatus[$statusid]=array('may_be_scheduled'=>$may_be_scheduled, 'validate'=>$validate);
        }
    }
?>
