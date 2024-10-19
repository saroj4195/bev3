<?php
	
if($buyerEmail=='' && $buyerPhone =='' && $buyerFirstName =='' && $buyerLastName == '' && $amount == '')
	{
createsendBack($sendBack='',$error='ALL', $id,$value,'airpay/error.php');
	}
if($buyerEmail=='')
	{
createsendBack($sendBack='',$error='E', $id,$value,'airpay/error.php');
	}
else
	{	
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) ||  (strlen($buyerEmail) > 50) ){

createsendBack($sendBack='',$error='VE', $id,$value,'airpay/error.php');	
		}
}
	if($buyerPhone=='')
{
		createsendBack($sendBack='',$error='BP', $id,$value,'airpay/error.php');	
}
	else
{
		$regex = '/^[0-9- ]{8,15}$/i'; 
if(!preg_match($regex,$buyerPhone)) {
			createsendBack($sendBack='',$error='VBP', $id,$value,'airpay/error.php');	
}
	}
if($buyerFirstName=='')
	{
createsendBack($sendBack='',$error='FN', $id,$value,'airpay/error.php');	
	}
else
	{
$regex = '/^[a-z \d]{1,50}$/i'; 
		if(!preg_match($regex,$buyerFirstName)) {
createsendBack($sendBack='',$error='VFN', $id,$value,'airpay/error.php');	
		}
}
	if($buyerLastName=='')
{
		createsendBack($sendBack='',$error='LN', $id,$value,'airpay/error.php');	
}
	else
{
		$regex = '/^[a-z \d]{1,50}$/i'; 
if(!preg_match($regex,$buyerLastName)) {
			createsendBack($sendBack='',$error='VLN', $id,$value,'airpay/error.php');	
}
	}
if($buyerAddress!='')
{
$regex =  '/^[a-z ,;.#$\/( )-_\d]{4,255}$/i';
if(!preg_match($regex,$buyerAddress)) {
createsendBack($sendBack='',$error='VADD', $id,$value,'airpay/error.php?a='.$buyerAddress);	
}
}
if($buyerCity!='')
{
$regex =  '/^[a-z \d]{2,50}$/i';
if(!preg_match($regex,$buyerCity)) {
createsendBack($sendBack='',$error='VCIT', $id,$value,'airpay/error.php');	
}
}
if($buyerState!='')
{
$regex =  '/^[a-z \d]{2,50}$/i';
if(!preg_match($regex,$buyerState)) {
createsendBack($sendBack='',$error='VSTA', $id,$value,'airpay/error.php');	
}
}
if($buyerCountry!='')
{
$regex =  '/^[a-z \d]{2,50}$/i';
if(!preg_match($regex,$buyerCountry)) {
createsendBack($sendBack='',$error='VCON', $id,$value,'airpay/error.php');	
}
}
if($buyerPinCode!='')
{
$regex = '/^[a-z\d]{4,8}$/i';
if(!preg_match($regex,$buyerPinCode)) {
createsendBack($sendBack='',$error='VPIN', $id,$value,'airpay/error.php');	
}
}
if($amount=='')
{
createsendBack($sendBack='',$error='A', $id,$value,'airpay/error.php');	
}
else
{
$regex = '/^[0-9]{1,6}\.[0-9]{2,2}$/';
if(!preg_match($regex,$amount)) {
createsendBack($sendBack='',$error='VA', $id,$value,'airpay/error.php');	
}
}
function createsendBack($sendBack,$err='', $id, $value, $action){
echo '<!DOCTYPE HTML>';
	echo '<html lang="en">';
echo '<head>';
	echo '<meta charset="utf-8" />';
echo '</head>';
	echo '<body onLoad="javascript:document.errorform.submit();">';
echo '<form name="errorform" id="errorform" method="post" action="'.$action.'">';
	echo '<input type="hidden" id="bac" name="bac" value="'.htmlspecialchars($sendBack).'">';
echo '<input type="hidden" id="status" name="status" value="'.$err.'">';
	echo '<input type="hidden" id="statusmsg" name="statusmsg" value="'.$statusmsg.'">';
echo '<input type="hidden" id="'.$id.'" name="'.$id.'" value="'.$value.'">';
	echo '</form>';
echo '</body>';
	echo '</html>';
exit();
}
?>
