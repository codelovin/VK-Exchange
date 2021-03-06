<?php

require 'common.php';

header('content-type: application/json');
ob_start();

$key = "eff09eab7ff62d2e71d0169816b8ee680cbd51e964df581cabce57bed52cd7f7";

/*
 * Checking if all parameters are passed
 */
if (!check_params(array('token', 'user_id', 'id', 'date_added', 'quantity', 'should_pay', 'priority', 'pass'))) {
    show_error();
}

$token = $_GET['token'];
$user_id = $_GET['user_id'];
$item_id = $_GET['id'];
$date_added = $_GET['date_added'];
$quantity = $_GET['quantity'];
$should_pay = $_GET['should_pay'];
$priority = $_GET['priority'];
$pass = $_GET['pass'];

/*
 * Checking SHA
 */
if (!check_sha512(array($token, $user_id, $item_id, $date_added, $quantity, $should_pay, $priority), $key, $pass)) {
    show_error();
}

/*
 * Opening database
 */
$db = open_database();
begin_transaction($db);

add_id($db, $token, 'token');

check_ban($user_id, $db);

/*
 * Checking if specified priority is allowed
 */
$priority = 0;

change_last_active_date($db, $user_id);

/*
 * Checking if user has enough money, adding the task
 */
$required = $quantity*9;
if ($stmt = $db->prepare('INSERT OR FAIL INTO ACTIVE_TASKS (order_id, type, id, owner_id, date_added, quantity, priority, date_last_viewed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')) {
    $stmt->bindValue(1, generate_order_id(), SQLITE3_TEXT);
    $stmt->bindValue(2, 'repost', SQLITE3_TEXT);
    $stmt->bindValue(3, $item_id, SQLITE3_INTEGER);
    $stmt->bindValue(4, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(5, $date_added, SQLITE3_INTEGER);
    $stmt->bindValue(6, $quantity, SQLITE3_INTEGER);
    $stmt->bindValue(7, $priority, SQLITE3_INTEGER);
    $stmt->bindValue(8, time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    if (!$res) {
        rollback_transaction($db);
    }
} else {
    rollback_transaction($db);
}

/*
 * Modifying user money
 */
if ($should_pay) {
    if ($stmt = $db->prepare('UPDATE OR FAIL USERS SET balance=balance-? WHERE user_id=? AND balance>=?')) {
        $stmt->bindValue(1, $required, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $required, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if (!$res) {
            rollback_transaction($db);
        }
    } else {
        rollback_transaction($db);
    }
}

/*
 * Retreiving from completed_inactive
 */
if ($stmt = $db->prepare('INSERT INTO COMPLETED_ACTIVE (user_id, order_id, automatic, type, owner_id, id, date_completed) SELECT user_id, order_id, automatic, type, owner_id, id, date_completed FROM COMPLETED_INACTIVE WHERE owner_id=? AND type=? AND id=?')) {
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'repost', SQLITE3_TEXT);
    $stmt->bindValue(3, $item_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if (!$res) {
        rollback_transaction($db);
    }
} else {
    rollback_transaction($db);
}

/*
 * Deleting from completed_inactive
 */
if ($stmt = $db->prepare('DELETE FROM COMPLETED_INACTIVE WHERE owner_id=? AND type=? AND id=?')) {
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, 'repost', SQLITE3_TEXT);
    $stmt->bindValue(3, $item_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if (!$res) {
        rollback_transaction($db);
    }
} else {
    rollback_transaction($db);
}

/*
 * Echo-ing user info
 */
if ($stmt = $db->prepare('SELECT * FROM USERS WHERE user_id=?')) {
    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if(!$res){
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