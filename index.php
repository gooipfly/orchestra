<?php

// Contributor:
//      Phus Lu        <phus.lu@gmail.com>

$__version__  = '2.1.3';
$__password__ = 'yQ4tg3Bt';
$__timeout__  = 20;

function decode_request($data) {
    list($headers_length) = array_values(unpack('n', substr($data, 0, 2)));
    $headers_data = gzinflate(substr($data, 2, $headers_length));
    $body = substr($data, 2+intval($headers_length));

    $method  = '';
    $url     = '';
    $headers = array();
    $kwargs  = array();

    foreach (explode("\n", $headers_data) as $kv) {
        $pair = explode(':', $kv, 2);
        $key  = $pair[0];
        $value = trim($pair[1]);
        if ($key == 'G-Method') {
            $method = $value;
        } else if ($key == 'G-Url') {
            $url = $value;
        } else if (substr($key, 0, 2) == 'G-') {
            $kwargs[strtolower(substr($key, 2))] = $value;
        } else if ($key) {
            $headers[$key] = $value;
        }
    }
    if (isset($headers['Content-Encoding'])) {
        if ($headers['Content-Encoding'] == 'deflate') {
            $body = gzinflate($body);
            $headers['Content-Length'] = strval(strlen($body));
            unset($headers['Content-Encoding']);
        }
    }
    return array($method, $url, $headers, $kwargs, $body);
}

function header_function($ch, $header){
    header($header);
    $GLOBALS['header_length'] += 1;
    return strlen($header);
}

function write_function($ch, $body){
    echo $body;
    $GLOBALS['body_length'] += 1;
    return strlen($body);
}

function post()
{
    list($method, $url, $headers, $kwargs, $body) = @decode_request(@file_get_contents('php://input'));

    $password = $GLOBALS['__password__'];
    if ($password) {
        if (!isset($kwargs['password']) || $password != $kwargs['password']) {
            header("HTTP/1.0 403 Forbidden");
            echo '403 Forbidden';
            exit(-1);
        }
    }

    if ($body) {
        $headers['Content-Length'] = strval(strlen($body));
    }
    $headers['Connection'] = 'close';

    $timeout = $GLOBALS['__timeout__'];

    $curl_opt = array();

    $curl_opt[CURLOPT_RETURNTRANSFER] = true;
    $curl_opt[CURLOPT_BINARYTRANSFER] = true;

    $curl_opt[CURLOPT_HEADER]         = false;
    $curl_opt[CURLOPT_HEADERFUNCTION] = 'header_function';
    $curl_opt[CURLOPT_WRITEFUNCTION]  = 'write_function';


    $curl_opt[CURLOPT_FAILONERROR]    = true;
    $curl_opt[CURLOPT_FOLLOWLOCATION] = false;

    $curl_opt[CURLOPT_CONNECTTIMEOUT] = $timeout;
    $curl_opt[CURLOPT_TIMEOUT]        = $timeout;

    $curl_opt[CURLOPT_SSL_VERIFYPEER] = false;
    $curl_opt[CURLOPT_SSL_VERIFYHOST] = false;

    switch (strtoupper($method)) {
        case 'HEAD':
            $curl_opt[CURLOPT_NOBODY] = true;
            break;
        case 'GET':
            break;
        case 'POST':
            $curl_opt[CURLOPT_POST] = true;
            $curl_opt[CURLOPT_POSTFIELDS] = $body;
            break;
        case 'PUT':
        case 'DELETE':
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $method;
            $curl_opt[CURLOPT_POSTFIELDS] = $body;
            break;
        default:
            echo 'Invalid Method: ' . var_export($method, true);
            exit(-1);
    }

    $header_array = array();
    foreach ($headers as $key => $value) {
        if ($key) {
            $header_array[] = join('-', array_map('ucfirst', explode('-', $key))).': '.$value;
        }
    }
    $curl_opt[CURLOPT_HTTPHEADER] = $header_array;

    $ch = curl_init($url);
    curl_setopt_array($ch, $curl_opt);
    $ret = curl_exec($ch);
    //$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    if ($errno && !isset($GLOBALS['header_length'])) {
        echo 'cURL(' . $errno . '): ' .curl_error($ch);
    }
    curl_close($ch);
}

function get() {
    header('Location: https://www.google.com/');
}

function main() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        post();
    } else {
        get();
    }
}

main();
