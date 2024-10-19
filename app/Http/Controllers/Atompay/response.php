<?php

require_once 'TransactionResponse.php';
$transactionResponse = new TransactionResponse();

$transactionResponse->setRespHashKey("KEYRESP123657234");
$transactionResponse->setResponseEncypritonKey("8E41C78439831010F81F61C344B7BFC7");
$transactionResponse->setSalt("8E41C78439831010F81F61C344B7BFC7");

$arrayofdata = $transactionResponse->decryptResponseIntoArray($_POST['encdata']);

print_r($arrayofdata);


