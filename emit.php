<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$data = json_decode(file_get_contents('php://input'), true);

require('Gaze.php');

$gaze = new Gaze();
$gaze->emit($data['topic'], $data['payload'], $data['roles']);

return 'Done';