<?php

include('VPCPaymentConnection.php');
$conn = new VPCPaymentConnection();
$secureSecret = "3ED39E2E66A164E18AD09FD2653C84DA";

$conn->setSecureSecret($secureSecret);
ksort ($_POST);
$vpcURL = $_POST["virtualPaymentClientURL"];

$title  = $_POST["Title"];

unset($_POST["virtualPaymentClientURL"]); 
unset($_POST["SubButL"]);
unset($_POST["Title"]);

foreach($_POST as $key => $value) {
	if (strlen($value) > 0) {
		$conn->addDigitalOrderField($key, $value);
	}
}

$conn->addDigitalOrderField("AgainLink", $againLink);

$secureHash = $conn->hashAllFields();
$conn->addDigitalOrderField("Title", $title);
$conn->addDigitalOrderField("vpc_SecureHash", $secureHash);
$conn->addDigitalOrderField("vpc_SecureHashType", "SHA256");

$vpcURL = $conn->getDigitalOrder($vpcURL);

header("Location: ".$vpcURL);

?>