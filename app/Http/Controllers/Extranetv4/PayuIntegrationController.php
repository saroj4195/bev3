<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\PayuAccessCredential;
use App\PayuPaymentgateway;
use App\PaymentGetway;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use DB;
use File;
use Webpatser\Uuid\Uuid;


/** 
 * @author         : Swatishree Padhy
 * @Created Date   : 24/09/22
 * **/

class PayuIntegrationController extends Controller
{
  public function getToken() //generate token for merchant
  {

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $client_id = $get_accessdata->client_id;
    $client_secret = $get_accessdata->client_secret_key;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://accounts.payu.in/oauth/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
                "client_id": "' . $client_id . '",
                "client_secret":"' . $client_secret . '",
                "grant_type": "client_credentials",
                "scope": "refer_merchant" 
                }',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Cookie: PHPSESSID=89cb51fc6441598ffde4898747164ad5'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if ($response) {
      $response = json_decode($response);
      $expires_in = $response->expires_in + $response->created_at;
      $expiry_time = date('Y-m-d H:i:s', $expires_in);
      $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['access_token' => $response->access_token, 'expiry_time' => $expiry_time]);

      if ($update_details) {
        return $response->access_token;
      } else {
        return 'failed';
      }
    } else {
      return response()->json(array('msg' => 'failed'));
    }
  }


  public function createMerchant(request $request) // for create merchant
  {
    $data = $request->all();
    $payu_paymentgateway = [];
    $access_token = [];
    $get_accessdata = PayuAccessCredential::select('*')->first();

    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);
    $expiry_time = strtotime($get_accessdata->expiry_time);

    if (empty($get_accessdata->access_token)) {
      $access_token = $this->getToken();
    } elseif ($current_time >= $expiry_time) {
      $access_token = $this->getToken();
    } else {
      $access_token = $get_accessdata->access_token;
    }
    
    $merchant_display_name = urlencode($data['merchant']['display_name']);
    $merchant_moblie_no = urlencode($data['merchant']['mobile']);
    $merchant_email = urlencode($data['merchant']['email']);
    $business_entity_type = urlencode($data['merchant']['business_details']['business_entity_type']);
    $business_category = urlencode('Travel & Tourism');
    $business_sub_category = urlencode('Travel and Tour (all services)');
    $monthly_expected_volume = urlencode($data['merchant']['monthly_expected_volume']);
    $business_pancard_name = urlencode($data['merchant']['business_details']['pancard_name']);
    $business_pan = urlencode($data['merchant']['business_details']['pan']);
    $business_addr_line = urlencode($data['merchant']['business_address']['addr_line1']);
    $business_addr_pin = urlencode($data['merchant']['business_address']['pin']);
    $operating_addr_line1 = urlencode($data['merchant']['operating_address']['addr_line1']);
    $operating_addr_pin = urlencode($data['merchant']['operating_address']['pin']);
    $account_holder_name = urlencode($data['merchant']['bank_details']['account_holder_name']);
    $account_no = urlencode($data['merchant']['bank_details']['account_no']);
    $ifsc_code = urlencode($data['merchant']['bank_details']['ifsc_code']);
    $merchant_signing_authority_detail_name = urlencode($data['merchant']['signing_authority_details']['name']);
    $merchant_signing_authority_detail_email = urlencode($data['merchant']['signing_authority_details']['email']);
    $merchant_signing_authority_detail_pancard = urlencode($data['merchant']['signing_authority_details']['pancard_number']);
    $merchant_website = urlencode($data['merchant']['website_details']['website_url']);
    

    if(empty($data['merchant']['signing_authority_details']['cin_number'])){

      $merchant_signing_authority_detail_cin_no = urlencode('U72400MH2006PTC293037');

    }else{
      $merchant_signing_authority_detail_cin_no = urlencode($data['merchant']['signing_authority_details']['cin_number']);
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://partner.payu.in//api/v3/merchants',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => 'merchant[display_name]=' . $merchant_display_name . '&merchant[email]=' . $merchant_email . '&merchant[mobile]=' . $merchant_moblie_no . '&merchant[bank_details][account_no]=' . $account_no . '&merchant[bank_details][ifsc_code]=' . $ifsc_code . '&merchant[bank_details][account_holder_name]=' . $account_holder_name . '&merchant[business_address][addr_line1]=' . $business_addr_line . '&merchant[business_address][pin]=' . $business_addr_pin . '&merchant[business_details][business_category]=' . $business_category . '&merchant[business_details][registered_name]=' . $merchant_display_name . '&merchant[business_details][pan]=' . $business_pan . '&merchant[business_details][business_entity_type]=' . $business_entity_type . '&merchant[business_details][business_sub_category]=' . $business_sub_category . '&merchant[monthly_expected_volume]=' . $monthly_expected_volume . '&merchant[operating_address][addr_line1]=' . $operating_addr_line1 . '&merchant[operating_address][pin]=' . $operating_addr_pin . '&merchant[director1_details][name]=' . $merchant_display_name . '&merchant[business_details][pancard_name]=' . $business_pancard_name . '&merchant[director1_details][email]=' . $merchant_email . '&merchant[director2_details][name]=' . $merchant_display_name . '&merchant[director2_details][email]=' . $merchant_email . '&merchant[signing_authority_details][name]=' . $merchant_signing_authority_detail_name . '&merchant[signing_authority_details][email]=' . $merchant_signing_authority_detail_email . '&merchant[signing_authority_details][pancard_number]=' . $merchant_signing_authority_detail_pancard . '&merchant[signing_authority_details][cin_number]=' . $merchant_signing_authority_detail_cin_no . '&merchant[website_details][website_url]=' . $merchant_website . '',

      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' .$access_token . '',
        'Content-Type: application/x-www-form-urlencoded'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if ($response) {
      $response = json_decode($response);
      if(isset($response->merchant->uuid)){
        $uuid = $response->merchant->uuid;
        $mid = $response->merchant->mid;
        $payu_paymentgateway = [
          'hotel_id' => $data['hotel_id'],
          'mid'      => $mid,
          'uuid'     => $uuid,
          'credential' => Null,
          'url' => NULL
        ];
        $payu_details = PayuPaymentgateway::insert($payu_paymentgateway);
      }
      else if(isset($response->errors->error)) {
        return response()->json(array('status' => 0, 'msg' => "Merchant already exists for given user", "merchant" => $response->merchant));
      } 
      else if(isset($response->errors)){
          return response()->json(array('status' => 0, 'msg' => json_encode($response->errors)));
      }
      else if(isset($response->error)){
        return response()->json(array('status' => 0, 'msg' => json_encode($response->error)));
      }

      if ($payu_details) {
        $get_merchant_drtails =  $this->getMerchant($access_token, $mid, $data['hotel_id']);

        return response()->json(array('status' => 1,'msg' =>'Merchant created successfully','data' => $response));
      } else {
        return response()->json(array('status' => 0, 'msg' => ' Unable to get merchant credential'));
      }
    } else {
      return response()->json(array('status' => 0, 'msg' => ' Unable to get merchant id'));
    }
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function getMerchant($access_token, $mid, $hotel_id) // get merchant
  {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://partner.payu.in/api/v1/merchants/' . $mid . '/credential',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token . ''
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if ($response) {
      $response = json_decode($response);
      if ($response) {
        $credential = '[{"key": ' .'"'.$response->data->credentials->prod_key .'"' . ',"salt":' . '"'.$response->data->credentials->prod_salt .'"' . '}]';

        $update_tokens = PayuPaymentgateway::where('mid', $mid)->update(['credential' => $credential]);
        if ($update_tokens) {

          $array = [
            'hotel_id' => $hotel_id,
            'provider_name' => 'hdfc_payu',
            'credentials' => $credential,
            'user_id' => $hotel_id

          ];
          $paymentgateway_details = DB::table('paymentgateway_details')->insert($array);
        } else {
          return response()->json(array('msg' => 'Insert failed '));
        }
      } else {
        return response()->json(array('msg' => 'No data found'));
      }
    } else {
      return response()->json(array('msg' => 'Failed'));
    }
  }
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function getMerchsntStatus($mid){

    $get_data = PayuPaymentgateway::where('mid', $mid)->first();

    $uuid = $get_data->uuid;

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);
    $expiry_time = strtotime($get_accessdata->expiry_time);

    if (empty($get_accessdata->access_token)) {
      $access_token = $this->getToken();
    } elseif ($current_time >= $expiry_time) {
      $access_token = $this->getToken();
    } else {
      $access_token = $get_accessdata->access_token;
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://partner.payu.in/api/v1/merchants/' . $uuid ,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token . ''
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if ($response) {
        $response = json_decode($response);
      }
  }

  public function getCredentcial() //generate token for merchant
  {

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $client_id = $get_accessdata->client_id;
    $client_secret = $get_accessdata->client_secret_key;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://accounts.payu.in/oauth/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{
                "client_id": "' . $client_id . '",
                "client_secret":"' . $client_secret . '",
                "grant_type": "client_credentials",
                "scope": "refer_merchant" 
                }',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Cookie: PHPSESSID=89cb51fc6441598ffde4898747164ad5'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if ($response) {
      $response = json_decode($response);
      $expires_in = $response->expires_in + $response->created_at;
      $expiry_time = date('Y-m-d H:i:s', $expires_in);
      $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['access_token' => $response->access_token, 'expiry_time' => $expiry_time]);

      if ($update_details) {
        return $response->access_token;
      } else {
        return 'failed';
      }
    } else {
      return response()->json(array('msg' => 'failed'));
    }
  }

}
