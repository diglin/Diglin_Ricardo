<?php

$ricardoUsername = '';
$ricardoPassword = '';

$params = array(
    'createTemporaryCredentialParameter' => array()
);

$curlOptions = array(
    CURLOPT_URL            => 'https://ws.betaqxl.com/ricardoapi/SecurityService.Json.svc/CreateTemporaryCredential',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST           => 1,
    CURLOPT_POSTFIELDS     => jsonEncode($params),
    CURLOPT_HTTPHEADER     => addHeaders($ricardoUsername, $ricardoPassword),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSLVERSION     => 0
);

$ch = curl_init();
curl_setopt_array($ch, $curlOptions);
$return = json_decode(curl_exec($ch), true);

if (curl_errno($ch)) {
    throw new Exception('Error while trying to connect with the API - Curl Error Number: ' . curl_errno($ch) . ' - Curl Error Message: ' . curl_error($ch), curl_errno($ch));
}

curl_close($ch);

var_dump($return);

function addHeaders($username, $password = null)
{
    $headers = array(
        'Content-Type: application/json',
        'Host: ws.betaqxl.com',
        'Ricardo-Username: ' . $username
    );

    if ($password) {
        $headers[] = 'Ricardo-Password: ' . $password;
    }

    return $headers;
}

function jsonEncode($val)
{
    if (is_string($val) && strpos($val, '[') !== false && strpos($val, ']') === strlen($val) - 1) return $val;
    if (is_string($val)) return json_encode($val);
    if (is_numeric($val)) return $val;
    if ($val === null) return 'null';
    if ($val === true) return 'true';
    if ($val === false) return 'false';

    $assoc = false;
    $i = 0;
    foreach ($val as $k => $v) {
        if ($k !== $i++) {
            $assoc = true;
            break;
        }
    }
    $res = array();
    foreach ($val as $k => $v) {
        $v = jsonEncode($v);
        if ($assoc) {
            $k = json_encode($k);
            $v = $k . ':' . $v;
        }
        $res[] = $v;
    }
    $res = implode(',', $res);
    return ($assoc) ? '{' . $res . '}' : '[' . $res . ']';
}