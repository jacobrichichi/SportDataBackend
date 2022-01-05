<?php

require_once '../includes/DbOperations.php';
$response = array();


if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $db = new DbOperations();

    $players = $db -> getCertainPlayers($_POST);

    $response['error'] = false;
    $response['data'] = $players;
}

echo json_encode($response);

?>