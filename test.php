<?php
/**
 * Test.php
 * This is a test of the Email Parser class developed by Danny Grove
 * This will take in a raw email and parse it for the necessary information.
 */

// Includes Email Parser Class
include('class.emailParser.php');


$emailStr = file_get_contents('email.txt');
$email = new Email($emailStr);
$boundry = $email->getBoundry();
$bodyContent = $email->getBody();

var_dump($email->getHTMLBody($boundry, $bodyContent));
?>
