<?php
class user{
public $username; 
public $email; 
public $password;
public $error;
private $db;

function __construct(){
global $db;
$this->db = $db;
$this->error = array();
}

function register($username,$email,$password){
if(filter_var($email,FILTER_VALIDATE_EMAIL)){
$email = $this->db->real_escape_string(trim($email));
$email_check = $this->db->query("SELECT * FROM `users` WHERE `email` = '{$email}';");
if($email_check->num_rows > 0){
$this->error[] = "Email address already exists";
}
}
else{
$this->error[] = "Invalid email address";
}
if(!preg_match("/^[A-Za-z0-9-_]{4,16}$/i",$username)){
$this->error[] = "Username should contain only alphanumeric characters, _ or - ranging from 4 to 16 characters";
}else{
$username = trim($this->db->real_escape_string($username));
$username_check = $this->db->query("SELECT * FROM `users` WHERE `username` = '{$username}';");
if($username_check->num_rows > 0){
$this->error[] = "Username already exists";
}
}

if(strlen($password) < 8 || strlen($password) > 32){
$this->error[] = "Password should range from 8 to 32 characters";
}else{ 
$password = password_hash($password,PASSWORD_DEFAULT);
}

if(count($this->error) < 1){
$stmt = $this->db->prepare("INSERT INTO users (username,email,password,code) VALUES(?, ?, ?, ?)");
$stmt->bind_param("ssss",$username,$email,$password,bin2hex(random_bytes(32)));
$stmt->execute();
$stmt->close();
return true;
}else{return false;}
}

function auth($user,$password){
$user = $this->db->real_escape_string(filter_var($user,FILTER_SANITIZE_STRING));
$user_check = $this->db->query("SELECT * FROM `users` WHERE `username` = '{$user}' OR `email` = '{$user}';");
if($user_check->num_rows > 0){
$check = mysqli_fetch_assoc($user_check);
if(password_verify($password,$check['password'])){
return true;
}else{
$this->error[] = "Incorrect password";
return false;
}
}else{
$this->error[] = "Unknown user";
return false;
}
}

function details($user){
$user = filter_var($user,FILTER_SANITIZE_STRING);
$user = $this->db->real_escape_string($user);
$details = mysqli_fetch_assoc($this->db->query("SELECT * FROM `users` WHERE `username`='{$user}' OR `email`='{$user}';"));
if(!empty($details) > 0){
unset($details['password']);
return $details;
} else {
$this->error[] = "User not found";
return false;
}
}

function list($status=1){
$details = array();
$list = $this->db->query("SELECT * FROM `users` WHERE `status` = '".intval($status)."';");
while($detail = $list->fetch_assoc()){
unset($detail['password']);
$details[] = $detail;
}
return $details;
}

function status($user,$status){
$user = $this->db->real_escape_string(filter_var($user,FILTER_SANITIZE_STRING));
$status = $this->db->real_escape_string(intval($status));
$status_change = $this->db->query("UPDATE `users` SET `status`='{$status}' WHERE `username`='{$user}' OR `email`='{$user}';");
if($status_change){
return true;
}
}

function block($user){
return $this->status($user,2);
}

function unblock($user){
return $this->status($user,1);
}

function activate($user,$code){
$user = filter_var($user,FILTER_SANITIZE_STRING);
$user_code = $this->details($user)['code'];

if(hash_equals($user_code,$code)){
$this->status($user,1);
return true;
}else{
$this->error[] = "Invalid activation code";
return false;
}
}

}

include"page.php";
$user = new user;
var_dump($user->activate("test",$user->details("test")['code']));

print_r($user->error);