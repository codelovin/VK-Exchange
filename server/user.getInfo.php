<?php

require 'common.php';

header('content-type: application/json');
ob_start();

$key = "84497dfe8a3cf1d98671f23d9ac0dad5da08e9e5291962566afcb15aed393481";
//NSData

/*
 * Checking if all parameters are passed
 */
if (!check_params(array('user_id', 'pass'))) {
    show_error();
}

$user_id = $_GET['user_id'];
$pass = $_GET['pass'];

/*
 * Checking SHA
 */
if (!check_sha512(array($user_id), $key, $pass)) {
    show_error();
}

/*
 * Opening database
 */
$db = open_database();
begin_transaction($db);

/*
 * Getting user info
 */
if ($stmt = $db->prepare('SELECT * FROM USERS WHERE user_id = ?')) {
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if(!$res) {
        rollback_transaction($db);
    }
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        commit_transaction(json_encode(array('response'=>$row)), $db);
    } else {
        rollback_transaction($db);
    }
} else {
    rollback_transaction($db);
}

?>