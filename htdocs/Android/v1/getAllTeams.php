<?php

require_once '../includes/DbOperations.php';
$response = array();


if($_SERVER['REQUEST_METHOD'] == 'GET'){
    $db = new DbOperations();

    $teams = $db -> getAllTeams();

    $response['error'] = false;
    $response['data'] = $teams;
}

echo json_encode($response);

?>
