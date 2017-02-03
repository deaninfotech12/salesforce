<?php
session_start(); 
//Open session connection

define('MODE', '1'); //App mode 1:Live, 2:Sandbox
define('CLIENT_ID', 'ADD YOUR CLIENT ID HERE'); //App Client ID
define('CLIENT_SECRET', 'ADD YOUR CLIENT SECRET HERE'); //App Client Secret
define('SALESFORCE_USERNAME', 'ADD YOUR SALESFORCE USERNAME HERE'); //Salesforce Account Username
define('SALESFORCE_PASSWORD', 'ADD YOUR SALESFORCE PASSWORD HERE'); //Salesforce Account Password

header("Access-Control-Allow-Origin: https://virtualpbx.freshdesk.com");
header("Access-Control-Allow-Methods:  GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Content-Type: application/json; charset=utf-8");
//Allow cross domain ajax

if(MODE=='1'){
    $loginurl = "https://login.salesforce.com/services/oauth2/token";
    // Salesforce login url
}else{
    $loginurl = "https://test.salesforce.com/services/oauth2/token";
    // Salesforce login url
}
if(empty($_SESSION['access_token'])){ 
    // Check access token is set or not

    $params = "grant_type=password"
    . "&client_id=" . CLIENT_ID
    . "&client_secret=" . CLIENT_SECRET
    . "&username=" . SALESFORCE_USERNAME
    . "&password=" . SALESFORCE_PASSWORD;
    //salesforce app credentials

    //Curl code start here 
    $curl = curl_init($loginurl);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    $json_response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ( $status != 200 ) {
        die("Error: call to URL failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }
    curl_close($curl);
    //Curl code end here

    $response = json_decode($json_response, true);
    //Get the curl response

    $access_token = (isset($response['access_token']))?$response['access_token']:'';
    $instance_url = (isset($response['instance_url']))?$response['instance_url']:'';
    if (!isset($access_token) || $access_token == "") {
        die("Error - access token missing from response!");
    }

    if (!isset($instance_url) || $instance_url == "") {
        die("Error - instance URL missing from response!");
    }
    //Set access token and instance url into session
    $_SESSION['access_token'] = $access_token;
    $_SESSION['instance_url'] = $instance_url;
}
$access_token = (isset($_SESSION['access_token']))?$_SESSION['access_token']:'';
$instance_url = (isset($_SESSION['instance_url']))?$_SESSION['instance_url']:'';

if (!isset($access_token) || $access_token == "") {
    die("Error - access token missing from session!");
}
if (!isset($instance_url) || $instance_url == "") {
    die("Error - instance URL missing from session!");
}

//function for fetching the account and contact data from email
function accounts($instance_url, $access_token, $name) {
    $query = "SELECT Account.Name,Account.BillingAccount_ID__c,Account.Platform_ID__c, AccountId, Email, name, Phone, MobilePhone, MailingStreet, MailingCity, MailingState,MailingPostalCode, MailingCountry from Contact Where Email = '$name' LIMIT 1";
    $url = "$instance_url/services/data/v20.0/query?q=" . urlencode($query);
    //salesforce query to fetch the accont and contact data

    //Curl code start here
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER,
            array("Authorization: OAuth $access_token"));

    $json_response = curl_exec($curl);
    curl_close($curl);
    //Curl code end here

    $array_res = json_decode($json_response);
    //get the curl responce

    $response_array=array();
    if(isset($array_res->records[0]) && !empty($array_res->records[0])){
        $account_data           = $array_res->records[0];
        $acc_id                 = $account_data->AccountId;        
        $response_array['contact_Email']          = $account_data->Email;
        $response_array['contact_Name']           = $account_data->Name;
        $response_array['contact_Phone']          = $account_data->Phone;
        $response_array['contact_Street']         = $account_data->MailingStreet;
        $response_array['contact_City']           = $account_data->MailingCity;
        $response_array['contact_State']          = $account_data->MailingState;
        $response_array['contact_PostalCode']     = $account_data->MailingPostalCode;
        $response_array['contact_Country']        = $account_data->MailingCountry;
        $response_array['account_Name']           = $account_data->Account->Name;
        $response_array['account_Account_ID']     = $account_data->Account->BillingAccount_ID__c;
        $response_array['account_Platform_ID']    = $account_data->Account->Platform_ID__c;

        //Select VPBX ID and Account Type
        $query2 = "SELECT VPBX_ID__c,Type from Account Where id = '$acc_id'";
        $url = "$instance_url/services/data/v20.0/query?q=" . urlencode($query2);
        
        //Curl code start here
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER,
                array("Authorization: OAuth $access_token"));

        $json_response = curl_exec($curl);
        curl_close($curl);
        //Curl code end here
        $acc_data = json_decode($json_response);
        if(isset($acc_data->records[0]) && !empty($acc_data->records[0])){
            $accou_data           = $acc_data->records[0];
            $response_array['account_VPBX_ID'] = $accou_data->VPBX_ID__c;
            $response_array['account_Type'] = $accou_data->Type;
        }
    }
    if(!empty($response_array)){
        echo json_encode($response_array);
    }else{
        echo '';
    }
    //return the acccount and contact data
    die; 
}

if(isset($_REQUEST['method'])){
    //call the account function 
    accounts($instance_url, $access_token, trim($_REQUEST['name']));
}
?>