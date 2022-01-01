<?php

require_once '../includes/DbOperations.php';
$response = array();


if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $db = new DbOperations();

    $teams = $db -> customQuery($_POST);

    $response['error'] = false;
    $response['data'] = $teams;
}

echo json_encode($response);

?>
