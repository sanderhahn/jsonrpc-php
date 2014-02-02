<?php

include('rb.php');
R::setup('mysql:host=localhost;dbname=redbean','root','');

session_start();

function handle_error($errno, $errstr, $errfile, $errline, array $errcontext) {
  if (0 === error_reporting()) {
    return false;
  }
  throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function dispatch() {
  $req = json_decode(file_get_contents("php://input"), true);

  $method = $req['method'];
  $params = $req['params'];

  $json = array();
  if(array_key_exists('id', $_REQUEST)) {
    $json['id'] = $req['id'];
  }
  $func = 'rpc_' . $method;
  try {
    set_error_handler('handle_error');
    $json['result'] = call_user_func_array($func, $params);
  } catch(Exception $e) {
    header("HTTP/1.1 400 Bad Request");
    $json['error'] = $e->getMessage();
  }
  header('Content-type: application/json');
  echo json_encode($json);
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
  dispatch();
} else {
  header("HTTP/1.1 404 Not found");
}

// Hello

function rpc_hello($name) {
  if($name === 'sander') {
    throw new Exception("sander not allowed");
  }
  return "hello $name!";
}

// Users

function rpc_login($username, $password) {
  $user = R::findOne('users','username = ? and password = ?', array($username, $password));
  if($user !== null) {
    $_SESSION['whoami'] = $user->id;
    $json = $user->export();
    $json['password'] = '***';
    return $json;
  }
  throw new Exception('username or password incorrect');
}

function rpc_whoami() {
  if(array_key_exists('whoami', $_SESSION)) {
    $user = R::load('users', $_SESSION['whoami']);
    $json = $user->export();
    $json['password'] = '***';
    return $json;
  }
  return null;
}

function rpc_logout() {
  session_destroy();
  return null;
}

function rpc_register($username, $password) {
  if(R::findOne('users','username = ?', array($username)) !== null) {
    throw new Exception('username already exists');
  }
  $user = R::dispense('users');
  $user->username = $username;
  $user->password = $password;
  $id = R::store($user);
  $_SESSION['whoami'] = $id;
  $json = R::load('users', $id)->export();
  $json['password'] = '***';
  return $json;
}

function rpc_destroy($username, $password) {
  $user = R::findOne('users','username = ? and password = ?', array($username, $password));
  if($user !== null) {
    $json = $user->export();
    $json['password'] = '***';
    R::trash($user);
    session_destroy();
    return $json;
  }
  return false;
}
