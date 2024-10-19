<?php
namespace App\Models;
use DB;
use Illuminate\Database\Eloquent\Model;

class Commonmodel extends Model
{
    public static function curlGet($url)
	{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
        curl_setopt($ch, CURLOPT_HTTPHEADER,  array(
            'Authorization: '. $_SERVER['HTTP_AUTHORIZATION']
        ));
        $result = curl_exec($ch);
        return json_decode($result);	
	}

    public static function curlPost($url,$array_post_fields)
	{
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $array_post_fields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: '. $_SERVER['HTTP_AUTHORIZATION']
        )));
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result);
    }

    public static function curlPostWhatsApp($url,$array_post_fields)
	{
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $array_post_fields,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        )));
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result);
    }

}





