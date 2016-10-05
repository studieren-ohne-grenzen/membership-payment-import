<?php
require_once '../../../default/civicrm.settings.php';
require_once './CRM/Core/Config.php';
require_once './api/api.php';
$config =& CRM_Core_Config::singleton( );

//civicrm_initialize( );

// read a csv file containing the payments
// first row must contain column names
// mandatory fields are: [financial_type_id, total_amount, contact_id] (for contribution) and [membership_id] (to link contribution and membership_payment)
// financial_type_id: text is possible, e.g. "Mitgliedsbeitrag"
// SOG contribution_status_id: needs to be id, text is not possible
//      1=Completed; 2=Pending; 3=Cancelled; 4=Failed; 5=In Progress; 6=Overdue; ... TODO: write parser function for this (or fix in Civi-API itself)
// date format must be YYYYMMDD or YYYYMMDDhhmmss
$filename = "./import.csv";
$contribution_optional_cols = array("receive_date");

$row = 0;
$imported = 0;
$colCount = -1;
$schema = array();

if (($handle = fopen($filename, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 0, ",", '"')) !== FALSE) {
        if($row == 0) {
            detectColumns($data);
        }
        else {
            if(parseRow($data)) $imported++;
        }
        $row++;
    }
    fclose($handle);
    echo "<br><br>Added contributions: ".$imported;
}





// detect index of relevant columns
function detectColumns($data) {
    global $colCount, $schema;
    $colCount = count($data);    
    $schema = $data;
}


// translate the names of financialtype, etc. to the appropriate IDs automatically
    // SOG contribution_status_id: needs to be id, text is not possible
    //      1=Completed; 2=Pending; 3=Cancelled; 4=Failed; 5=In Progress; 6=Overdue; ... TODO: write parser function for this (or fix in Civi-API itself)
    // date format must be YYYYMMDD or YYYYMMDDhhmmss
function translate2id($value) {
    switch($value) {
        //payment instruments
        case "Überweisung":
            return 4;
        case "Bankeinzug":
            return 2;
        case "Bar":
            return 3;
            
        //financial types
        case "Erstattung Rücklastschriftgebühr":
            return 12;
        case "Mitgliedsbeitrag":
            return 2;            
        case "Patenschaft":
            return 7;
        case "Spende":
            return 10;
    }
    
    return $value;
}


// import one row into the database
function parseRow($data) {
    global $colCount, $schema;
    $num = count($data);
    if($num != $colCount) {
        echo "ERROR: Column count does not match schema". "<br />\n" . $data . "<br />\n<br />\n";
    }
    
    $params_contr = array(
        'version' => 3,
        'sequential' => 1,
    );
    // convert indices to names from first row and translate values to id values
    for ($c=0; $c < $num; $c++) {
        $params_contr[($schema[$c])] = translate2id($data[$c]);
    }
    $result_contr = civicrm_api('Contribution', 'create', $params_contr);
    if($result_contr['is_error']) {
        print_r($result_contr);
        print "<br>\n<br>\n";
        return false;
    }

    $params_mem = array(
        'version' => 3,
        'sequential' => 1,
        'membership_id' => $params_contr['membership_id'], 
        'contribution_id' => $result_contr['id'],
    );
    $result_mem = civicrm_api('MembershipPayment', 'create', $params_mem);
    if($result_mem['is_error']) {
        print_r($result_mem);
        print "<br>\n<br>\n";
        return false;
    }

    return true;
}

//TODO: give output = input + contribution_id(of added contribution)
