<?php
$token = "6968842126:AAHfYXGRtGw5IvN48QpGsXToc445ojsRcdk";
 $chat_id = '133776412';
 
$data = file_get_contents('php://input');
$data = json_decode($data,TRUE);
$log_file_name = __DIR__ . "/message.txt";
file_put_contents($log_file_name, print_r($data, true));

$chat_id = $data['message']['chat']['id'];
$message = "This is a reply from the bot";

$send_message_url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message);

file_get_contents($send_message_url);
echo file_get_contents(__DIR__ . '/message.txt');
