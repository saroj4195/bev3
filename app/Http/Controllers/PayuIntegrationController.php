<?php

namespace App\Http\Controllers;

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


  //validation rules
  private $rules = array(
    'hotel_id' => 'required | numeric',
    'merchant[display_name]' => 'required ',
    'merchant[email]' => 'required',
    'merchant[mobile]' => 'required',
    'merchant[bank_details][account_no]' => 'required ',
    'merchant[bank_details][ifsc_code]' => 'required ',
    'merchant[bank_details][account_holder_name]' => 'required ',
    'merchant[business_address][addr_line1]' => 'required ',
    'merchant[business_address][pin]' => 'required ',
    'merchant[business_details][registered_name]' => 'required ',
    'merchant[business_details][pan]' => 'required ',
    'merchant[business_details][pancard_name]' => 'required ',
    'merchant[operating_address][addr_line1]' => 'required ',
    'merchant[signing_authority_details][name]' => 'required ',
    'merchant[signing_authority_details][email]' => 'required ',
    'merchant[signing_authority_details][pancard_number]' => 'required ',
    'gst_number' => 'required',
    );


  //Custom Error Messages
  private $messages = [
    'hotel_id.required' => 'The hotel id field is required.',
    'hotel_id.numeric' => 'The hotel id must be numeric.',
    'merchant[display_name].required' => 'The Name id must be required ',
    'merchant[email].required' => 'The Email id must be required',
    'merchant[mobile].required' => 'The Mobile id must be required',
    'merchant[bank_details][account_no].required' => 'The Bank account no id must be required ',
    'merchant[bank_details][ifsc_code].required' => 'The Bank IFSC id must be required ',
    'merchant[bank_details][account_holder_name].required' => 'The bank holder name id must be required ',
    'merchant[business_address][addr_line1].required' => 'Please enter business address details',
    'merchant[business_address][pin].required' => 'The business address pin must be ',
    'merchant[business_details][registered_name].required' => 'The registered name for the business must be required ',
    'merchant[business_details][pan].required' => 'The pan must be required ',
    'merchant[business_details][pancard_name].required' => 'The pan card must be required ',
    'merchant[operating_address][addr_line1].required' => 'The signing authority name must oprating address be required ',
    'merchant[signing_authority_details][name].required' => 'The signing authority name must be required',
    'merchant[signing_authority_details][email].required' => 'The signing authority email must be required ',
    'merchant[signing_authority_details][pancard_number].required' => 'The signing authority pancard number must be required',
    'gst_number.required' =>'The signing authority name must be required',

    ];

 
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  public function getToken()
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

    $response = json_decode($response);
    $expires_in = $response->expires_in + $response->created_at;
    $expiry_time = date('Y-m-d H:i:s', $expires_in);
    $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['access_token' => $response->access_token, 'expiry_time' => $expiry_time]);

    if ($update_details) {
      return 'Token generated';
    } else {
      return 'failed';
    }
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  public function getKycToken()
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
                "scope": "client_manage_kyc_details"
                }',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($response);
    $expires_in = $response->expires_in + $response->created_at;
    $expiry_time = date('Y-m-d H:i:s', $expires_in);
    $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['kyc_access_token' => $response->access_token, 'kyc_expiry_time' => $expiry_time]);

    if ($update_details) {
      return 'Kyc Token generated';
    } else {
      return 'failed';
    }
  }
  ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  public function eSignToken() 
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
                "scope": "client_manage_agreement"
                }',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Cookie: PHPSESSID=89cb51fc6441598ffde4898747164ad5'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);


    $response = json_decode($response);
    $expires_in = $response->expires_in + $response->created_at;
    $expiry_time = date('Y-m-d H:i:s', $expires_in);
    $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['esign_access_token' => $response->access_token, 'esign_expiry_time' => $expiry_time]);

    if ($update_details) {
      return 'Esign Token generated';
    } else {
      return 'failed';
    }
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function getMerchantCredentialsToken()
  {

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $client_id = $get_accessdata->client_id;
    $client_secret = $get_accessdata->client_secret_key;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => '	https://partner.payu.in/oauth/token',
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
                "scope": "read_merchant_reseller " 
                }',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Cookie: PHPSESSID=89cb51fc6441598ffde4898747164ad5'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);


    $response = json_decode($response);
    $expires_in = $response->expires_in + $response->created_at;
    $expiry_time = date('Y-m-d H:i:s', $expires_in);
    $update_details = PayuAccessCredential::where('id', $get_accessdata->id)->update(['access_token_merchanct_credential' => $response->access_token, 'credencial_expiry_time' => $expiry_time]);

    if ($update_details) {
      return 'Token generated';
    } else {
      return 'failed';
    }
  }


  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function createMerchant(request $request)
   {
    // $failure_message = "Field should not be blank";
    // $validator = Validator::make($request->all(), $this->rules, $this->messages);
    // if ($validator->fails()) {
    //     return response()->json(array('status' => 0, 'message' => $failure_message, 'error' => $validator->errors()));
    // }
    
    $data = $request->all();
    $payu_paymentgateway = [];
    $get_accessdata = PayuAccessCredential::select('*')->first();

    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);

    if (empty($get_accessdata->access_token)) {
      $access_token = $this->getToken();
      // } elseif ($current_time >= $get_accessdata->expiry_time) {
      //   $access_token = $this->getToken();
    } else {
      $access_token = $get_accessdata->access_token;
    }

    //$hotel_id = urlencode(isset($data['hotel_id']) ? $data['hotel_id'] : null);
    $merchant_display_name = urlencode($data['merchant']['display_name']);
    $merchant_moblie_no = urlencode($data['merchant']['mobile']);
    $merchant_email = urlencode($data['merchant']['email']);
    $business_entity_type = urlencode($data['merchant']['business_details']['business_entity_type']);
    $business_category = urlencode($data['merchant']['business_details']['business_category']);
    $business_sub_category = urlencode($data['merchant']['business_details']['business_sub_category']);
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
    $merchant_signing_authority_detail_cin_no = urlencode($data['merchant']['signing_authority_details']['cin_number']);
    $merchant_website = urlencode($data['merchant']['website_details']['website_url']);



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
      CURLOPT_POSTFIELDS => 'merchant[display_name]=' . $merchant_display_name . '&merchant[email]=' . $merchant_email . '&merchant[mobile]=' . $merchant_moblie_no . '&merchant[bank_details][account_no]=' . $account_no . '&merchant[bank_details][ifsc_code]=' . $ifsc_code . '&merchant[bank_details][account_holder_name]=' . $account_holder_name . '&merchant[business_address][addr_line1]=' . $business_addr_line . '&merchant[business_address][pin]=' . $business_addr_pin . '&merchant[business_details][business_category]=' . $business_category . '&merchant[business_details][registered_name]=' . $merchant_display_name . '&merchant[business_details][pan]=' . $business_pan . '&merchant[business_details][business_entity_type]=' . $business_entity_type . '&merchant[business_details][business_sub_category]=' . $business_sub_category . '&merchant[monthly_expected_volume]=' . $monthly_expected_volume . '&merchant[operating_address][addr_line1]=' . $operating_addr_line1 . '&merchant[operating_address][pin]=' . $operating_addr_pin . '&merchant[director1_details][name]=' . $merchant_display_name . '&merchant[business_details][pancard_name]=' . $business_pancard_name . '&merchant[director1_details][email]=' . $merchant_email . '&merchant[director2_details][name]=' . $merchant_display_name . '&merchant[director2_details][email]=' . $merchant_email . '&merchant[signing_authority_details][name]=' .$merchant_signing_authority_detail_name. '&merchant[signing_authority_details][email]=' .$merchant_signing_authority_detail_email. '&merchant[signing_authority_details][pancard_number]=' .$merchant_signing_authority_detail_pancard. '&merchant[signing_authority_details][cin_number]=' .$merchant_signing_authority_detail_cin_no. '&merchant[website_details][website_url]='.$merchant_website.'',

      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token . '',
        'Content-Type: application/x-www-form-urlencoded'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    if($response) {

      $response = json_decode($response);
      if(isset($response->errors->error)) {

        return response()->json(array('status' => 0, 'msg' => "Merchant already exists for given user", "merchant" => $response->merchant));
      } else {
        if(!isset($response->merchant->uuid)){
            $uuid = '11ed-61bb-d3246898-81cb-025dcc012560';
        }
        else{
          $uuid = $response->merchant->uuid;
        }
        if(!isset($response->merchant->mid)){
          $mid = '8747257';
        }
        else{
          $mid = $response->merchant->mid;
        }
        $payu_paymentgateway = [
          'hotel_id' => $data['hotel_id'],
          'mid'      => $mid,
          'uuid'     => $uuid,
          'credential' => Null,
          'url' => NULL

        ];
        $payu_details = PayuPaymentgateway::insert($payu_paymentgateway);
      }
      if ($payu_details) {
        $get_merchant_drtails =  $this->getMerchant($access_token, $mid,$data['hotel_id']);
        return response()->json(array('status' => 1, 'data' => $response));
      } else {
        return response()->json(array('status' => 0, 'msg' => ' Unable to get merchant credential'));
      }
    } else {
      return response()->json(array('status' => 0, 'msg' => ' Unable to get merchant id'));
    }
  }

  ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function getMerchant($access_token, $mid, $hotel_id)
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

    $response = json_decode($response);
    if($response){
    $credential = '[{"key":"'.$response->data->credentials->prod_key.'","salt":"'.$response->data->credentials->prod_salt.'"}]';

    $update_tokens = PayuPaymentgateway::where('mid', $mid)->update(['credential' => $credential]);
   if($update_tokens){

  $array = [
    'hotel_id' => $hotel_id,
    'provider_name' => 'hdfc_payu',
    'credentials' => $credential,
    'user_id' => $hotel_id

  ];
    $paymentgateway_details = DB::table('paymentgateway_details')->insert($array);

   }else{
    return response()->json(array('msg' => 'Insert failed '));
   }
  }else{
        return response()->json(array('msg' => 'No data found'));
  }
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

 public function getStatus(request $request){
  $data = $request->all();

  $merchant_display_name = urlencode($data['merchant']['display_name']);
    $merchant_moblie_no = urlencode($data['merchant']['mobile']);


 }

 /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  public function getMarchantByHotelId($hotel_id, $mid)
  {

    $get_access_token =  PayuAccessCredential::select('*')->first();
    $access_token = $get_access_token->access_token;
    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);

    if (empty($access_token)) {
      $access_token = $this->getToken();
      // } elseif ($current_time >= $get_access_token->expiry_time) {
      //   $access_token = $this->getToken();
    } else {
      $access_token = $get_access_token->access_token;
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://uat-partner.payu.in/api/v1/merchants/' . $mid . '/credential',
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
      $credential = '[{"key": ' . $response->data->credentials->prod_key . ',"salt":' . $response->data->credentials->prod_salt . '}]';

      $update_tokens = PayuPaymentgateway::where('hotel_id', $hotel_id)->where('mid', $mid)->update(['credential' => $credential]);
    } else {
      return response()->json(array('status' => 0, 'msg' => ' Unable to get merchant id'));
    }
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function uploadAadhaarXML(request $request) //pending
  {
    $data = $request->all();
    $get_accessdata = PayuAccessCredential::select('*')->first();

    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);

    if (empty($get_accessdata->kyc_access_token)) {
      $access_token = $this->getToken();
      // } elseif ($current_time >= $get_accessdata->expiry_time) {
      //   $access_token = $this->getKycToken();
    } else {
      $access_token = $get_accessdata->kyc_access_token;
    }

    // $aadhaar = $data['aadhaar_file'];
    $file = $request->file('aadhaar_file');
    $file_name = $file->getClientOriginalName();
    $cmp_file = file_get_contents($file);
    // dd($cmp_file);
    // $path = $file->getRealPath();
    
    $aadhaar_share_code = $data['aadhaar_share_code'];
    $merchant_id = $data['merchant_id'];
    $storage = $file->move(public_path('uploads/Payu/'.$merchant_id), $file_name);   

    // $curl = curl_init();

    // curl_setopt_array($curl, array(
    //   CURLOPT_URL => 'https://uat-partner.payu.in/api/v3/merchants/kyc_document/aadhaar_xml_offline',
    //   CURLOPT_RETURNTRANSFER => true,
    //   CURLOPT_ENCODING => '',
    //   CURLOPT_MAXREDIRS => 10,
    //   CURLOPT_TIMEOUT => 0,
    //   CURLOPT_FOLLOWLOCATION => true,
    //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //   CURLOPT_CUSTOMREQUEST => 'POST',
    //   CURLOPT_POSTFIELDS => array('aadhaar_file' => new \CURLFILE('https://dev.be.bookingjini.com/public/uploads/Payu/'.$merchant_id. '/' . $file_name), 'aadhaar_share_code' => $aadhaar_share_code, 'merchant_id' => $merchant_id),
    //   CURLOPT_HTTPHEADER => array(
    //     'Authorization: Bearer ' . $access_token . ''
    //   ),
    // ));

    // $response = curl_exec($curl);

    // curl_close($curl);

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://uat-partner.payu.in/api/v3/merchants/kyc_document/aadhaar_xml_offline',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array('aadhaar_file' => $cmp_file, 'aadhaar_share_code' => $aadhaar_share_code, 'merchant_id' => $merchant_id),
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token . ''
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    if($response){
      $result = json_decode($response);
      if($result){
         return response()->json(array('msg' =>'Signing Contact Details verification is complited'));
      }else{
        return response()->json(array('msg' =>' "error_message": "The signing authority name mismatched with the name on Aadhaar card. Kindly verify the details and retry or try a different mode for KYC'));
      }

    }
  }

  ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

 

  ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function generateMerchantAgreement($mid)
  {
    $get_kyc_details = PayuPaymentgateway::select('*')->where('mid', $mid)->first();

    $uuid = $get_kyc_details->uuid;

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $access_token = $get_accessdata->esign_access_token;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://onboarding.payu.in/api/v1/merchants/' . $uuid . '/generate_merged_document_for_esign',
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

      if (!empty($response)) {
        $insert_agreement_uuid = PayuPaymentgateway::where('mid', $mid)->update(['kyc_document_uuid', $response->kyc_document->kyc_document_uuid]);

        if ($insert_agreement_uuid) {
          $get_otp_details =  $this->oTPSignatoryEmail($mid);

          if ($get_otp_details) {

            return response()->json(array('status' => 1, 'msg' => 'OTP has been sent to your register email address '));
          } else {
            return response()->json(array('status' => 0, 'msg' => 'OTP sent failed'));
          }
        } else {
          return response()->json(array('status' => 0, 'msg' => 'failed'));
        }
      } else {
        return response()->json(array('status' => 0, 'msg' => 'Unable to get merchant id'));
      }
    } else {
      return response()->json(array('status' => 0, 'msg' => 'No response found'));
    }
  }
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function oTPSignatoryEmail($mid)
  {

    $get_kyc_details = PayuPaymentgateway::select('*')->where('mid', $mid)->first();


    $uuid = $get_kyc_details->uuid;
    $kyc_doc_uuid = $get_kyc_details->kyc_document_uuid;

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $access_token = $get_accessdata->esign_access_token;



    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://onboarding.payu.in/api/v1/merchants/' . $uuid . '/kyc_documents' . $kyc_doc_uuid . '/send_e_sign_otp',
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
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  public function eSignMerchantAgreement($mid, request $request)
  {

    $data = $request->all();

    $otp = $data['otp'];

    $get_accessdata = PayuAccessCredential::select('*')->first();

    $current_time = date('Y-m-d H:i:s');
    $current_time = strtotime($current_time);

    if (empty($get_accessdata->esign_access_token)) {
      $access_token = $this->eSignToken();
      // } elseif ($current_time >= $get_accessdata->esign_expiry_time) {
      //   $access_token = $this->eSignToken();
    } else {
      $access_token = $get_accessdata->esign_access_token;
    }

    $get_kyc_details = PayuPaymentgateway::select('*')->where('mid', $mid)->first();

    $uuid = $get_kyc_details->uuid;
    $kyc_doc_uuid = $get_kyc_details->kyc_document_uuid;


    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://onboarding.payu.in/api/v1/merchants/' . $uuid . '/kyc_documents' . $kyc_doc_uuid . '/esign_merged_document',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array('otp' => $otp),
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token . ''
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    if(!empty($response))
    {
      $response = json_decode($response);

      if($response){
        return response()->json(array('status' => 1 , 'data' => ' Agreement Signing Contact Details verification Complited'));
      }else{
        return response()->json(array('status' => 0 , 'data' => ' Agreement Signing Contact Details verification Failed'));
      }

    }else{
      return response()->json(array('status' => 0, 'msg' => 'No response found'));
    }
  }

}