<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Http\Controllers\Controller;
class IpAddressService extends Controller
{
public function getIPAddress()
   {
$ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
$ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
$ipaddress = $_SERVER['REMOTE_ADDR'];
    else
$ipaddress = '1.1.1.1';
    return $ipaddress;
}
}