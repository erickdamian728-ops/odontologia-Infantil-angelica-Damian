<?php
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash para '123456': " . $hash;
?>