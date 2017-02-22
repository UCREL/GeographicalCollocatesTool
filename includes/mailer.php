<?php

function sendMail($subject, $message, $fromEmail, $fromName, $toEmail) {

$injection = "[\r\n]";

if(ereg($injection, $subject) || ereg($injection, $fromEmail) || ereg($injection, $fromName) || ereg($injection, $toEmail))
	return false;

//strip slashes so don't get \'
$subject = stripslashes($subject);
$message = stripslashes($message);
$message = str_replace("\n.", "\n..", $message);
$message = wordwrap($message, 70);

$fromEmail = stripslashes($fromEmail);
$fromName = stripslashes($fromName);
$toEmail = stripslashes($toEmail);


// ALWAYS PUT THE MIME TYPE IN!
$headers = "MIME-Version: 1.0\n";
// tell the email client what kind of content it is
$headers .= "Content-type: text/plain; charset=iso-8859-1\n";
// put a name in, then an email address
// based where the domain is the same
// as the domain you're actually using -
// you could use the user's name in the
// first part of the "From: " bit
$headers .= "From: \"".$fromName."\" <".$fromEmail.">\n";
// Use the reply to as the user's details
$headers .= "Reply-To: \"".$fromName."\" <".$fromEmail.">\n";
// tell the email client what you were
// using to send the email
$headers .= "X-Mailer: PHP's mail() Function\n";

return mail($toEmail, $subject, $message, $headers);
}

?>