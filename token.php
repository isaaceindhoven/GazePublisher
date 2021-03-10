<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

require('Gaze.php');

$gaze = new Gaze(); // in service container

echo json_encode(['token' => $gaze->generateClientToken(['admin']) ]);