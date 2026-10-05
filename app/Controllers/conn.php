<?php

$servername = "localhost";
$username = "multipenel_dipak";
$password = "multipenel_dipak";
$dbname = "multipenel_dipak";

$conn = mysqli_connect($servername,$username,$password,$dbname);

if(!$conn) {

die(" PROBLEM WITH CONNECTION : " . mysqli_connect_error());

}
  
?>