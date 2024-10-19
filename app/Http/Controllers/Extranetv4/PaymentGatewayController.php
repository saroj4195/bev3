<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use DB;
use App\Http\Controllers\Extranetv4\Checksum;
use App\Http\Controllers\Extranetv4\BookingEngineController;
use App\Http\Controllers\Extranetv4\worldline\Awlmeapi;
use App\Http\Controllers\Extranetv4\worldline\ReqMsgDTO;
use App\Http\Controllers\Extranetv4\Controller;

require('razorpay/razorpay-php/Razorpay.php');
require('vendor/autoload.php');

use Razorpay\Api\Api;
use App\Invoice;
use App\HotelInformation;
use App\CompanyDetails;
use App\OnlineTransactionDetail;
use App\Http\Controllers\Extranetv4\Axis\VPCPaymentConnection;
use App\Http\Controllers\Extranetv4\Paytm\PaytmEncdecController;
use App\MetaSearchEngineSetting;
use App\PaymentGetway;



class PaymentGatewayController extends Controller
{
    protected $checksum;
    protected $booking_engine;
    protected $awlmeapi, $reqmsgdto;
    protected $paytm_controller;
    public function __construct(Checksum $checksum, BookingEngineController $booking_engine, Awlmeapi $awlmeapi, ReqMsgDTO $reqmsgdto, PaytmEncdecController $paytm_controller)
    {
        $this->checksum = $checksum;
        $this->booking_engine = $booking_engine;
        $this->awlmeapi = $awlmeapi;
        $this->reqmsgdto = $reqmsgdto;
        $this->paytm_controller = $paytm_controller;
    }
    public function actionIndex(String $invoice_id, string $hash, Request $request)
    {
        $b_invoice_id = $invoice_id;
        $invoice_id = base64_decode($invoice_id);
        $txnid = date('dmy') . $invoice_id;
        $row = $this->invoiceDetails($invoice_id);
        $hash_verify = false;

        $invoice_hashData = $invoice_id . '|' . $row->total_amount . '|' . $row->paid_amount . '|' . $row->email_id . '|' . $row->mobile . '|' . $b_invoice_id;
        $invoice_secureHash = hash('sha512', $invoice_hashData);
        if ($invoice_secureHash == $hash) // Hash Varification
        {
            $hash_verify = true;
        }

        $pg_details = $this->pgDetails($row->hotel_id);
       
        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }
        // var_dump($pg_details,$pg_details->provider_name,$hash_verify);
        if ($pg_details && $pg_details->provider_name == 'airpay' && $hash_verify) //Bookingjini Airpay Payment Gateway
        {
            $buyerEmail = trim($row->email_id);
            $buyerPhone = trim($row->mobile);
            $buyerPhone = str_replace("+91-", '', $buyerPhone);
            $buyerFirstName = trim($row->first_name);
            $buyerLastName = trim($row->last_name);
            $buyerAddress = $row->address;
            if (strpos($row->paid_amount, ".") === false) {
                $amount = trim($row->paid_amount . ".00");
            } else {
                $amount = trim($row->paid_amount);
            }

            $currency_details = $this->getCurrency($row->company_id);

            $buyerCity = $row->city;
            $buyerState = $row->state;
            $buyerPinCode = $row->zip_code;
            $buyerCountry = 'IND';
            $orderid = $txnid;
            $currency = $currency_details->numeric_code;
            $isocurrency = $currency_details->currency;
            $username =  trim($credentials[0]->username); //'3183548'; // Username
            $password =  trim($credentials[0]->password); //'k62q6wrT'; // Password
            $secret =    trim($credentials[0]->secret); //'H8rvmSMa55gtX2pZ'; // API key
            $mercid =    trim($credentials[0]->m_id); //'24149'; //Merchant ID
            //require_once 'airpay/validation.php';
            $values = array("buyerEmail" => "$buyerEmail", "buyerPhone" => "$buyerPhone", "buyerFirstName" => "$buyerFirstName", "buyerLastName" => "$buyerLastName", "buyerAddress" => "$buyerAddress", "amount" => "$amount", "buyerCity" => "$buyerCity", "buyerState" => "$buyerState", "buyerPinCode" => "$buyerPinCode", "buyerCountry" => "$buyerCountry", "orderid" => "$orderid");

            $alldata   = $buyerEmail . $buyerFirstName . $buyerLastName . $buyerAddress . $buyerCity . $buyerState . $buyerCountry . $amount . $orderid;
            $privatekey = $this->checksum->encrypt($username . ":|:" . $password, $secret);
            $checksum = $this->checksum->calculateChecksum($alldata . date('Y-m-d'), $privatekey);

            ?>
            <html xmlns="http://www.w3.org/1999/xhtml">

            <body onload="document.frmsend.submit();">
                <center>
                    <table width="500px;">
                        <tr>
                            <td align="center" valign="middle">
                                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" valign="middle">
                                <!-- https://payments.airpay.co.in/pay/index.php -->
                                <form action="https://payments.airpay.co.in/pay/index.php" method="post" name="frmsend" id="frmsend">
                                    <input type="hidden" name="privatekey" value="<?php echo $privatekey; ?>">
                                    <input type="hidden" name="mercid" value="<?php echo $mercid; ?>">
                                    <input type="hidden" name="orderid" value="<?php echo $txnid; ?>">
                                    <input type="hidden" name="currency" value="<?= $currency ?>">
                                    <input type="hidden" name="isocurrency" value="<?= $isocurrency ?>">
                                    <input type="hidden" name="kittype" value="inline">
                                    <input type="hidden" name="chmod" value="">
                                    <?php
                                    foreach ($values as $key => $value) {
                                        echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                                    }
                                    echo '<input type="hidden" name="checksum" value="' . $checksum . '" />' . "\n";
                                    ?>

                                </form>
                            </td>

                        </tr>

                    </table>

                </center>
            </body>

            </html>
        <?php
        } else if ($pg_details && $pg_details->provider_name == 'hdfc' && $hash_verify) //Thier own HDFC Payment
        {
            $account_id = trim($credentials[0]->account_id);
            $secretkey  = trim($credentials[0]->secretkey);
            $name = $row->first_name . ' ' . $row->last_name;

            if ($account_id == 16985) {
                $dsc = "EcoRetreat Payment";
            } else {
                $dsc = "Room Booking Payment";
            }

            $values = array("channel" => "10", "account_id" => "$account_id", "secretkey" => "$secretkey", "reference_no" => "$txnid", "amount" => "$row->paid_amount", "currency" => "INR", "description" => "$dsc", "return_url" => "https://be.bookingjini.com/hdfc-response", "mode" => "LIVE", "name" => "$name", "address" => "NA", "city" => "NA", "state" => "NA", "postal_code" => "NA", "country" => "IND", "email" => "$row->email_id", "phone" => "$row->mobile", "ship_name" => "$name", "ship_address" => "NA", "ship_city" => "NA", "ship_state" => "NA", "ship_postal_code" => "NA", "ship_country" => "IND", "ship_phone" => "$row->mobile");
            $HASHING_METHOD = 'sha512'; // md5,sha1
            $ACTION_URL = "https://secure.ebs.in/pg/ma/payment/request/";

            // This post.php used to calculate hash value using md5 and redirect to payment page.
            if (isset($values['secretkey']))
                $_SESSION['SECRET_KEY'] = $values['secretkey'];
            else
                $_SESSION['SECRET_KEY'] = $secretkey; //set your secretkey here

            $hashData = $_SESSION['SECRET_KEY'];

            unset($values['secretkey']);
            unset($values['submitted']);


            ksort($values);
            foreach ($values as $key => $value) {
                if (strlen($value) > 0) {
                    $hashData .= '|' . $value;
                }
            }

            if (strlen($hashData) > 0) {
                $secureHash = strtoupper(hash($HASHING_METHOD, $hashData));
            }
            ?>
            <html>

            <body onLoad="document.payment.submit();">
                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                </div>

                <form action="<?php echo $ACTION_URL; ?>" name="payment" method="POST">
                    <?php
                    foreach ($values as $key => $value) {
                    ?>
                        <input type="hidden" value="<?php echo $value; ?>" name="<?php echo $key; ?>" />
                    <?php
                    }
                    ?>
                    <input type="hidden" value="<?php echo $secureHash; ?>" name="secure_hash" />
                </form>
            </body>

            </html>
        <?php
        } else if ($pg_details && $pg_details->provider_name == 'hdfc_payu' && $hash_verify) //Thier own HDFC Payment
        {
            $key = trim($credentials[0]->key);
            $salt  = trim($credentials[0]->salt);
            $values = array("key" => "$key", "txnid" => "$txnid", "amount" => "$row->paid_amount", "productinfo" => "$row->hotel_name Booking", "firstname" => "$row->first_name", "email" => "$row->email_id", "phone" => "$row->mobile", "lastname" => "$row->last_name", "address1" => "$row->address", "city" => "$row->city", "state" => "$row->state", "country" => "IND", "zipcode" => "$row->zip_code", "surl" => "https://be.bookingjini.com/hdfc-payu-response", "furl" => "https://be.bookingjini.com/hdfc-payu-fail");
            $hashData = $key . '|' . $txnid . '|' . $row->paid_amount . '|' . $row->hotel_name . ' Booking|' . $row->first_name . '|' . $row->email_id . '|||||||||||' . $salt;
            if ($key == '7rnFly') {
                $ACTION_URL = "https://test.payu.in/_payment";
            } else {
                $ACTION_URL = "https://secure.payu.in/_payment";
            }
            if (strlen($hashData) > 0) {
                $secureHash = hash('sha512', $hashData);
            }
            ?>
            <html>

            <body onLoad="document.payment.submit();">
                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                </div>
                <form action="<?php echo $ACTION_URL; ?>" name="payment" method="POST">
                    <?php
                    foreach ($values as $key => $value) {
                    ?>
                        <input type="hidden" value="<?php echo $value; ?>" name="<?php echo $key; ?>" />
                    <?php
                    }
                    ?>
                    <input type="hidden" value="<?php echo $secureHash; ?>" name="hash" />
                </form>
            </body>

            </html>
        <?php
        } else if ($pg_details && $pg_details->provider_name == 'worldline' && $hash_verify) //Thier own WorldLine Payment Gateway
        {
            $name = $row->first_name . ' ' . $row->last_name;
            $mid = $credentials[0]->m_id;
            $sec_key = $credentials[0]->secret;
            $this->reqmsgdto->setMid($mid);
            $this->reqmsgdto->setOrderId($txnid);
            $row->paid_amount = $row->paid_amount * 100;
            $this->reqmsgdto->setTrnAmt($row->paid_amount);
            //$this->reqmsgdto->setTrnAmt('100');
            $this->reqmsgdto->setTrnRemarks($row->hotel_name . ' Booking');
            $this->reqmsgdto->setMeTransReqType('S');
            $this->reqmsgdto->setEnckey($sec_key);
            $this->reqmsgdto->setTrnCurrency('USD');
            $this->reqmsgdto->setResponseUrl('https://be.bookingjini.com/worldline-response');
            $this->reqmsgdto->setAddField1($name);
            $this->reqmsgdto->setAddField2($row->email_id);
            $this->reqmsgdto->setAddField3($row->mobile);

            $merchantRequest = "";

            $reqMsgDTO = $this->awlmeapi->generateTrnReqMsg($this->reqmsgdto);

            if ($reqMsgDTO->getStatusDesc() == "Success") {
                $merchantRequest = $reqMsgDTO->getReqMsg();
            }
          ?>
            <html>

            <body onLoad="document.txnSubmitFrm.submit();">
                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                </div>
                <form action="https://ipg.in.worldline.com/doMEPayRequest" method="post" name="txnSubmitFrm">
                    <input type="hidden" size="200" name="merchantRequest" id="merchantRequest" value="<?php echo $merchantRequest; ?>" />
                    <input type="hidden" name="MID" id="MID" value="<?php echo $reqMsgDTO->getMid(); ?>" />
                </form>
            </body>

            </html>
        <?php
        } else if ($pg_details && $pg_details->provider_name == 'sslcommerz' && $hash_verify) //Thier own sslcommerz Payment Gateway
        {
            $name = $row->first_name . ' ' . $row->last_name;
            $post_data = array();
            $post_data['store_id'] = "sayemanresort001live";
            $post_data['store_passwd'] = "sayemanresort001live58410";
            $post_data['total_amount'] = $row->paid_amount;
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $txnid;
            $post_data['success_url'] = "https://be.bookingjini.com/sslcommerz-response";
            $post_data['fail_url'] = "https://be.bookingjini.com/sslcommerz-response";
            $post_data['cancel_url'] = "https://be.bookingjini.com/sslcommerz-response";
            $post_data['emi_option'] = "0";
            $post_data['cus_name'] = $name;
            $post_data['cus_email'] = $row->email_id;
            $post_data['cus_phone'] = $row->mobile;
            $post_data['cus_add1'] = $row->address;
            $post_data['cus_add2'] = "";
            $post_data['cus_city'] = $row->city;
            $post_data['cus_state'] = $row->state;
            $post_data['cus_postcode'] = $row->zip_code;
            $post_data['cus_country'] = $row->country;

            $direct_api_url = "https://securepay.sslcommerz.com/gwprocess/";

            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, $direct_api_url);
            curl_setopt($handle, CURLOPT_TIMEOUT, 30);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


            $content = curl_exec($handle);

            $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($code == 200 && !(curl_errno($handle))) {
                curl_close($handle);
                $sslcommerzResponse = $content;
            } else {
                curl_close($handle);
                echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
                exit;
            }

            # PARSE THE JSON RESPONSE
            echo '<div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);"><h3>Please wait, redirecting to secure payment gateway...</h3></div>';
            echo ($sslcommerzResponse);
            exit;
        } else if ($pg_details && $pg_details->provider_name == 'atompay' && $hash_verify) //Thier own AtomPay Payment Gateway
        {
            $name = $row->first_name . ' ' . $row->last_name;
            $datenow = date("d/m/Y h:m:s");
            $transactionDate = str_replace(" ", "%20", $datenow);
            require_once 'Atompay/TransactionRequest.php';
            $transactionRequest = new TransactionRequest();
            if ($row->address == '') {
                $row->address = 'NA';
            }
            //Setting all values here
            $transactionRequest->setLogin(trim($credentials[0]->user_id));
            $transactionRequest->setPassword(trim($credentials[0]->password));
            $transactionRequest->setProductId(trim($credentials[0]->product_id));
            $transactionRequest->setAmount($row->paid_amount);
            $transactionRequest->setTransactionCurrency("INR");
            $transactionRequest->setTransactionAmount($row->paid_amount);
            $transactionRequest->setReturnUrl("https://be.bookingjini.com/atompay-response");
            $transactionRequest->setClientCode("007");
            $transactionRequest->setTransactionId($txnid);
            $transactionRequest->setTransactionDate($transactionDate);
            $transactionRequest->setCustomerName($name);
            $transactionRequest->setCustomerEmailId($row->email_id);
            $transactionRequest->setCustomerMobile($row->mobile);
            $transactionRequest->setCustomerBillingAddress($row->address);
            $transactionRequest->setCustomerAccount("0123456789");
            $transactionRequest->setReqHashKey(trim($credentials[0]->rq_key));
            $transactionRequest->seturl("https://payment.atomtech.in/paynetz/epi/fts");
            $transactionRequest->setRequestEncypritonKey(trim($credentials[0]->rq_enc_key));
            $transactionRequest->setSalt(trim($credentials[0]->rq_salt));
            $url = $transactionRequest->getPGUrl();
            return redirect($url);
        } else if ($pg_details && $pg_details->provider_name == 'icici' && $hash_verify) //Thier own ICICI Payment Gateway
        {
            ?>
            <html xmlns="http://www.w3.org/1999/xhtml">

            <body onload="document.frmsend.submit();">
                <center>
                    <table width="500px;">
                        <tr>
                            <td align="center" valign="middle">
                                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" valign="middle">
                                <form action="https://www4.ipg-online.com/connect/gateway/processing" method="post" name="frmsend" id="frmsend">
                                    <input type="hidden" name="timezone" value="IST" />
                                    <input type="hidden" name="authenticateTransaction" value="true" />
                                    <input size="50" type="hidden" name="txntype" value="sale" />
                                    <input size="50" type="hidden" name="txndatetime" value="<?php echo date('Y:m:d-H:i:s'); ?>" />
                                    <input size="50" type="hidden" name="hash" value="<?php echo $this->createHash($row->paid_amount, "356"); ?>" />
                                    <input size="50" type="hidden" name="currency" value="356" />
                                    <input size="50" type="hidden" name="mode" value="payonly" />
                                    <input size="50" type="hidden" name="storename" value="3387031062" />
                                    <input size="50" type="hidden" name="chargetotal" value="<?php echo $row->paid_amount; ?>" />
                                    <input size="50" type="hidden" name="paymentMethod" value="" />
                                    <input id="" name="full_bypass" size="20" value="false" type="hidden">
                                    <input size="50" type="hidden" name="sharedsecret" value="Lh78maArTx" />
                                    <input size="50" type="hidden" name="oid" value="<?php echo $txnid; ?>" />
                                    <input id="email" name="email" size="40" type="hidden" value="<?php echo $row->email_id; ?>">
                                    <input size="50" type="hidden" name="language" value="en_EN" />
                                    <input type="hidden" name="responseSuccessURL" value="https://be.bookingjini.com/icici-response" />
                                    <input type="hidden" name="responseFailURL" value="https://be.bookingjini.com/icici-response" />
                                    <input type="hidden" name="hash_algorithm" value="SHA1" />
                                </form>
                            </td>

                        </tr>

                    </table>

                </center>
            </body>

            </html>
            <?php
        } else if ($pg_details && $pg_details->provider_name == 'razorpay' && $hash_verify) //Thier own ICICI Payment Gateway
        {
            $name = $row->first_name . ' ' . $row->last_name;
            $keyId = trim($credentials[0]->key);
            $company_name = $row->company_full_name;
            $keySecret = trim($credentials[0]->secret);
            $displayCurrency = 'INR';
            $api = new Api($keyId, $keySecret);
            $orderData = [
                'receipt'         => $txnid,
                'amount'          => $row->paid_amount * 100, // 2000 rupees in paise
                'currency'        => 'INR',
                'payment_capture' => 1 // auto capture
            ];
            $razorpayOrder = $api->order->create($orderData);
            $razorpayOrderId = $razorpayOrder['id'];
            if ($razorpayOrderId) {
                $this->keepOrder($invoice_id, $razorpayOrderId);
                $encrpt_data = base64_encode($invoice_id);
            ?>
                <html>

                <body onLoad="document.payment.submit();">
                    <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                        <h3>Please wait, redirecting to secure payment gateway...</h3>
                    </div>
                    <form method="POST" name="payment" action="https://api.razorpay.com/v1/checkout/embedded">
                        <input type="hidden" name="key_id" value="<?php echo $keyId; ?>">
                        <input type="hidden" name="order_id" value="<?php echo $razorpayOrderId; ?>">
                        <input type="hidden" name="name" value="<?php echo $company_name; ?>">
                        <input type="hidden" name="description" value="ROOM BOOKING">
                        <!-- <input type="hidden" name="image" value="https://cdn.razorpay.com/logos/DtcRhTP170rj4I_medium.png"> -->
                        <input type="hidden" name="prefill[name]" value="<?php echo $name; ?>">
                        <input type="hidden" name="prefill[contact]" value="<?php echo $row->mobile; ?>">
                        <input type="hidden" name="prefill[email]" value="<?php echo $row->email_id; ?>">
                        <input type="hidden" name="notes[shipping address]" value="<?php echo $row->address; ?>">
                        <input type="hidden" name="callback_url" value="https://be.bookingjini.com/razorpay-response">
                        <input type="hidden" name="cancel_url" value="https://be.bookingjini.com/razorpay-cancel/<?php echo $encrpt_data; ?>">
                    </form>
                </body>

                </html>
            <?php
            }
        } else if ($pg_details && $pg_details->provider_name == 'ccavenue' && $hash_verify) //Thier own CCAVENUE Payment Gateway
        {
            $name = $row->first_name . ' ' . $row->last_name;
            $merchant_data = '';      // Merchant ID
            $working_key = $credentials[0]->working_key; //Shared by CCAVENUES
            $access_code = $credentials[0]->access_code; //Shared by CCAVENUES
            $merchant_id = $credentials[0]->merchant_id;
            $values = array("merchant_id" => "$merchant_id", "order_id" => "$txnid", "amount" => "$row->paid_amount", "currency" => "INR", "billing_name" => "$name", "billing_tel" => "$row->mobile", "billing_email" => "$row->email_id", "billing_address" => "$row->address", "billing_city" => "$row->city", "billing_state" => "$row->state", "billing_country" => "India", "billing_zip" => "$row->zip_code", "redirect_url" => "https://be.bookingjini.com/ccavenue-response", "cancel_url" => "https://be.bookingjini.com/ccavenue-response", "language" => "EN");
            foreach ($values as $key => $value) {
                $merchant_data .= $key . '=' . urlencode($value) . '&';
            }
            $key = $this->hextobin(md5($working_key));
            $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
            $openMode = openssl_encrypt($merchant_data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
            $encryptedText = bin2hex($openMode);
            if ($encryptedText != '') { ?>
                <html>

                <body onLoad="document.redirect.submit();">
                    <form method="POST" id="redirect" name="redirect" action="https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction">
                        <input type="hidden" name="encRequest" value="<?php echo $encryptedText; ?>">
                        <input type="hidden" name="access_code" value="<?php echo $access_code; ?>">
                        <form>
                </body>

                </html>
            <?php
            }
        } else if ($pg_details && $pg_details->provider_name == 'paytm' && $hash_verify) //Thier own CCAVENUE Payment Gateway
        {
            $paytmParams = array(
                "MID" => trim($credentials[0]->m_id),
                "WEBSITE" => "WEBSTAGING",
                "INDUSTRY_TYPE_ID" => "Retail",
                "CHANNEL_ID" => "WEB",
                "ORDER_ID" => $txnid,
                "CUST_ID" => trim($row->email_id),
                "MOBILE_NO" => trim($row->mobile),
                "EMAIL" => trim($row->email_id),
                "TXN_AMOUNT" => trim($row->paid_amount),
                "CALLBACK_URL" => "https://be.bookingjini.com/paytm-response",
            );
            $checksum = $this->paytm_controller->getChecksumFromArray($paytmParams, trim($credentials[0]->key));
            $url = "https://securegw.paytm.in/order/process";
            ?>
            <html>

            <body onLoad="document.redirect.submit();">
                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                </div>
                <form method="POST" id="redirect" name="redirect" action='<?php echo $url; ?>'>
                    <?php
                    foreach ($paytmParams as $name => $value) {
                        echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
                    }
                    ?>
                    <input type="hidden" name="CHECKSUMHASH" value="<?php echo $checksum ?>">
                </form>
            </body>

            </html>
            <?php
        } else if ($pg_details && $pg_details->provider_name == 'Axis' && $hash_verify) //Thier own CCAVENUE Payment Gateway
        {
            $access_code    = trim($credentials[0]->AccessCode);
            $merchant_id    = trim($credentials[0]->MerchantID);
            $secure_secret  = trim($credentials[0]->SecureSecret);
            $post_data = array(
                "Title" => "PHP VPC 3 Party Transacion",
                "virtualPaymentClientURL" => "https://migs.mastercard.co.in/vpcpay",
                "vpc_Version" => "1",
                "vpc_Command" => "pay",
                "vpc_AccessCode" => $access_code,
                "vpc_MerchTxnRef" => $txnid,
                "vpc_Merchant" => $merchant_id,
                "vpc_OrderInfo" => "Room type booking",
                "vpc_Amount"    => $row->paid_amount * 100,
                "vpc_ReturnURL" => "https://be.bookingjini.com/axis-response",
                "vpc_Locale"    => "en_UK",
                "vpc_Currency"  => "INR"
            );
            $exc_fun = $this->axisRequest($post_data);
        }else if ($pg_details && $pg_details->provider_name == 'stripe' && $hash_verify) {  //Stripe Payment Gateway
            \Stripe\Stripe::setApiKey($credentials[0]->key);

            header('Content-Type: application/json');

            $booking_id = $txnid;
            $booking_date = date('Y/m/d');
            $paid_amount = $row->paid_amount;
            // dd($paid_amount);
            $checksum = $booking_id . '|' . $booking_date . '|' . $paid_amount;
         
            $checksum = md5($checksum);
            $booking_id = base64_encode($booking_id);
            $booking_date = base64_encode($booking_date);
            $amount = base64_encode($paid_amount);
            $paid_amount = (int)$paid_amount;
            $checkout_session = \Stripe\Checkout\Session::create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'room',
                        ],
                        'unit_amount' => $paid_amount,
                    ],
                    'quantity' => 1,
                ]],
                
                'mode' => 'payment',
                'success_url' => 'https://dev.be.bookingjini.com/success/' . $checksum . '/' . $booking_id . '/' . $booking_date . '/' . $amount,
                'cancel_url' => 'https://dev.be.bookingjini.com/cancel',
            ]);

            $url = $checkout_session->url;
            header("Location: $url");
            exit;
        }else if ($hash_verify) //Bookingjini PAYU Payment Gateway
        {
            $values = array("key" => "HpCvAH", "txnid" => "$txnid", "amount" => "$row->paid_amount", "productinfo" => "$row->hotel_name Booking", "firstname" => "$row->first_name", "email" => "$row->email_id", "phone" => "$row->mobile", "lastname" => "$row->last_name", "address1" => "$row->address", "city" => "$row->city", "state" => "$row->state", "country" => "IND", "zipcode" => "$row->zip_code", "surl" => "https://be.bookingjini.com/payu-response", "furl" => "https://be.bookingjini.com/payu-response");
            $hashData = 'HpCvAH|' . $txnid . '|' . $row->paid_amount . '|' . $row->hotel_name . ' Booking|' . $row->first_name . '|' . $row->email_id . '|||||||||||sHFyCrYD';

            $ACTION_URL = "https://secure.payu.in/_payment";
            if (strlen($hashData) > 0) {
                $secureHash = hash('sha512', $hashData);
            }
            ?>
            <html>

            <body onLoad="document.payment.submit();">
                <div style="width:450px; margin: 200px auto; padding: 20px; border: 1px dotted #f16622;box-shadow: 0px 3px 5px 3px rgba(209,209,209,0.75);">
                    <h3>Please wait, redirecting to secure payment gateway...</h3>
                </div>
                <form action="<?php echo $ACTION_URL; ?>" name="payment" method="POST">
                    <?php
                    foreach ($values as $key => $value) {
                    ?>
                        <input type="hidden" value="<?php echo $value; ?>" name="<?php echo $key; ?>" />
                    <?php
                    }
                    ?>
                    <input type="hidden" value="<?php echo $secureHash; ?>" name="hash" />
                </form>
            </body>

            </html>
            <?php

        }  else {
        ?>
            <html>

            <body>
                <h1>WRONG INVOICE HASH</h1>
            </body>

            </html>
            <?php
        }
    }

    /*----------------------------------- RESPONSE FROM STRIP (START) ----------------------------------*/

    public function StripeSuccessResponse($checksum, $booking_id, $booking_date, $amount)
    {

        $logpath = storage_path("logs/stripe.log" . date("Y-m-d"));
        $logfile = fopen($logpath, "a+");
        fwrite($logfile, "checksum1: " . $checksum . "\n");
        fwrite($logfile, "booking_id: " . $booking_id . "\n");
        fwrite($logfile, "booking_date: " . $booking_date . "\n");
        fwrite($logfile, "amount: " . $amount . "\n");
        fclose($logfile);
       
        $booking_id = base64_decode($booking_id);

        $booking_date = base64_decode($booking_date);
        $amount = base64_decode($amount);
        $response_checksum = $booking_id . '|' . $booking_date . '|' . $amount;

        $response_checksum = md5($response_checksum);
        $hash       =     $checksum;
        $payment_mode   = 'LIVE';
        $mihpayid     = $booking_id;

        if ($checksum == $response_checksum) {
            $id = str_replace($booking_date, '', $booking_id);
            $ivoice_id = substr($booking_id,'6');
            $url = $this->booking_engine->successBooking($ivoice_id, $mihpayid, $payment_mode, $hash, $booking_id);

            if ($url != '') {
                $url = $this->findURL($url, $id, $booking_id);


                $data = '<!DOCTYPE html>
             <html>
             <head>
               <title>Thanks for your Booking!</title>
               <link rel="stylesheet" href="style.css">
             </head>
             <body>
               <section>
                 <p>
                     Booking successfull
                 </p>
               </section>
             </body>
             </html>';
            } else {
                $data = '<h1>Error!</h1>
                       <p>Sorry! Trasaction not completed</p>
                       <script>window.ReactNativeWebView.postMessage(failure)
                       </script>';
            }
        }
        return $data;
    }

    /*------------------------------------ RESPONSE FROM PAYU (END) -----------------------------------*/

    /*----------------------------------- RESPONSE FROM PAYU (START) ----------------------------------*/

    public function payuResponse(Request $request)
    {
        $data = $request->all();
        $status     = $data['status'];
        $email      = $data['email'];
        $firstname    = $data['firstname'];
        $productinfo  = $data['productinfo'];
        $amount     = $data['amount'];
        $txnid      = $data['txnid'];
        $hash       = $data['hash'];
        $payment_mode   = $data['mode'];
        $mihpayid     = $data['mihpayid'];
        $curdate    = date('dmy');
        $hashData     = 'sHFyCrYD|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|HpCvAH';
        if (strlen($hashData) > 0) {
            $secureHash = hash('sha512', $hashData);
            if (strcmp($secureHash, $hash) == 0) {
                if ($status == 'success') {
                    $id = str_replace($curdate, '', $txnid);
                    $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

                    if ($url != '') {
                        $url = $this->findURL($url, $id, $txnid);
            ?>
                        <html>

                        <body onLoad="document.response.submit();">
                            <div style="width:300px; margin: 200px auto;">
                                <img src="loading.png" />
                            </div>
                            <form action="<?= $url ?>" name="response" method="GET">
                            </form>
                        </body>

                        </html>
                    <?php
                    } else {
                        echo '<h1>Error!</h1>';
                        echo '<p>Database error</p>';
                        echo '<script>window.ReactNativeWebView.postMessage(failure)
                        </script>';
                    }
                } else {
                    echo '<h1>Error!</h1>';
                    echo '<p>Sorry! Trasaction not completed</p>';
                    echo '<script>window.ReactNativeWebView.postMessage(failure)
                    </script>';
                }
            } else {
                echo '<h1>Error!</h1>';
                echo '<p>Hash validation failed</p>';
                echo '<script>window.ReactNativeWebView.postMessage(failure)
                </script>';
            }
        } else {
            echo '<h1>Error!</h1>';
            echo '<p>Invalid response</p>';
            echo '<script>window.ReactNativeWebView.postMessage(failure)
            </script>';
        }
    }

    /*------------------------------------ RESPONSE FROM PAYU (END) -----------------------------------*/

    /*----------------------------------- REQUEST FROM Axis (START) ----------------------------------*/
    public function axisRequest($post_data)
    {
        require_once 'Axis/VPCPaymentConnection.php';
        $conn = new VPCPaymentConnection();
        $secureSecret = "89C48EC529D12A49FEDFE52FB9B67E5D";

        $conn->setSecureSecret($secureSecret);
        ksort($post_data);
        $vpcURL = $post_data["virtualPaymentClientURL"];

        $title  = $post_data["Title"];

        unset($post_data["virtualPaymentClientURL"]);
        unset($post_data["SubButL"]);
        unset($post_data["Title"]);

        foreach ($post_data as $key => $value) {
            if (strlen($value) > 0) {
                $conn->addDigitalOrderField($key, $value);
            }
        }

        // $conn->addDigitalOrderField("AgainLink", $againLink);

        $secureHash = $conn->hashAllFields();
        $conn->addDigitalOrderField("Title", $title);
        $conn->addDigitalOrderField("vpc_SecureHash", $secureHash);
        $conn->addDigitalOrderField("vpc_SecureHashType", "SHA256");

        $vpcURL = $conn->getDigitalOrder($vpcURL);

        header("Location: " . $vpcURL);
        die();
    }
    /*----------------------------------- REQUEST FROM Axis (END) ----------------------------------*/
    /*----------------------------------- RESPONSE FROM Axis (START) ----------------------------------*/
    public function axisResponse()
    {
        require_once 'Axis/VPCPaymentConnection.php';
        $conn = new VPCPaymentConnection();
        $secureSecret = "89C48EC529D12A49FEDFE52FB9B67E5D";
        $conn->setSecureSecret($secureSecret);

        $errorExists = false;
        $title  = $_GET["Title"];


        foreach ($_GET as $key => $value) {
            if (($key != "vpc_SecureHash") && ($key != "vpc_SecureHashType") && ((substr($key, 0, 4) == "vpc_") || (substr($key, 0, 5) == "user_"))) {
                $conn->addDigitalOrderField($key, $value);
            }
        }
        $serverSecureHash    = array_key_exists("vpc_SecureHash", $_GET)    ? $_GET["vpc_SecureHash"] : "";
        $secureHash = $conn->hashAllFields();
        if ($secureHash == $serverSecureHash) {
            $Title                 = array_key_exists("Title", $_GET)                         ? $_GET["Title"]                 : "";
            $againLink             = array_key_exists("AgainLink", $_GET)                     ? $_GET["AgainLink"]             : "";
            $amount             = array_key_exists("vpc_Amount", $_GET)                 ? $_GET["vpc_Amount"]             : "";
            $locale             = array_key_exists("vpc_Locale", $_GET)                 ? $_GET["vpc_Locale"]             : "";
            $batchNo             = array_key_exists("vpc_BatchNo", $_GET)                 ? $_GET["vpc_BatchNo"]             : "";
            $command             = array_key_exists("vpc_Command", $_GET)                 ? $_GET["vpc_Command"]             : "";
            $message             = array_key_exists("vpc_Message", $_GET)                 ? $_GET["vpc_Message"]            : "";
            $version              = array_key_exists("vpc_Version", $_GET)                 ? $_GET["vpc_Version"]             : "";
            $cardType           = array_key_exists("vpc_Card", $_GET)                     ? $_GET["vpc_Card"]             : "";
            $orderInfo             = array_key_exists("vpc_OrderInfo", $_GET)                 ? $_GET["vpc_OrderInfo"]         : "";
            $receiptNo             = array_key_exists("vpc_ReceiptNo", $_GET)                 ? $_GET["vpc_ReceiptNo"]         : "";
            $merchantID          = array_key_exists("vpc_Merchant", $_GET)                 ? $_GET["vpc_Merchant"]         : "";
            $merchTxnRef         = array_key_exists("vpc_MerchTxnRef", $_GET)             ? $_GET["vpc_MerchTxnRef"]        : "";
            $authorizeID         = array_key_exists("vpc_AuthorizeId", $_GET)             ? $_GET["vpc_AuthorizeId"]         : "";
            $transactionNo      = array_key_exists("vpc_TransactionNo", $_GET)             ? $_GET["vpc_TransactionNo"]     : "";
            $acqResponseCode     = array_key_exists("vpc_AcqResponseCode", $_GET)         ? $_GET["vpc_AcqResponseCode"]     : "";
            $txnResponseCode     = array_key_exists("vpc_TxnResponseCode", $_GET)         ? $_GET["vpc_TxnResponseCode"]     : "";
            $riskOverallResult    = array_key_exists("vpc_RiskOverallResult", $_GET)         ? $_GET["vpc_RiskOverallResult"] : "";

            // Obtain the 3DS response
            $vpc_3DSECI                = array_key_exists("vpc_3DSECI", $_GET)             ? $_GET["vpc_3DSECI"] : "";
            $vpc_3DSXID                = array_key_exists("vpc_3DSXID", $_GET)             ? $_GET["vpc_3DSXID"] : "";
            $vpc_3DSenrolled         = array_key_exists("vpc_3DSenrolled", $_GET)         ? $_GET["vpc_3DSenrolled"] : "";
            $vpc_3DSstatus             = array_key_exists("vpc_3DSstatus", $_GET)             ? $_GET["vpc_3DSstatus"] : "";
            $vpc_VerToken             = array_key_exists("vpc_VerToken", $_GET)             ? $_GET["vpc_VerToken"] : "";
            $vpc_VerType             = array_key_exists("vpc_VerType", $_GET)             ? $_GET["vpc_VerType"] : "";
            $vpc_VerStatus            = array_key_exists("vpc_VerStatus", $_GET)             ? $_GET["vpc_VerStatus"] : "";
            $vpc_VerSecurityLevel    = array_key_exists("vpc_VerSecurityLevel", $_GET)     ? $_GET["vpc_VerSecurityLevel"] : "";

            $cscResultCode     = array_key_exists("vpc_CSCResultCode", $_GET)              ? $_GET["vpc_CSCResultCode"] : "";
            $ACQCSCRespCode = array_key_exists("vpc_AcqCSCRespCode", $_GET)             ? $_GET["vpc_AcqCSCRespCode"] : "";

            $txnResponseCodeDesc = "";
            $cscResultCodeDesc = "";
            $avsResultCodeDesc = "";

            $error = "";
            if ($txnResponseCode == "7" || $txnResponseCode == "No Value Returned" || $errorExists) {
                $error = "Error ";
            }
            $logpath = storage_path("logs/axisbank.log" . date("Y-m-d"));
            $logfile = fopen($logpath, "a+");
            fwrite($logfile, "details: " . $txnResponseCode . $vpc_VerType . $vpc_VerStatus . "\n");
            fclose($logfile);
            if ($vpc_VerStatus == 'Y' && $txnResponseCode == 0) {
                $mihpayid = $transactionNo;
                $curdate    = date('dmy');
                $id = str_replace($curdate, '', $merchTxnRef);
                $url = $this->booking_engine->successBooking($id, $mihpayid, "success", $secureHash, $merchTxnRef);

                if ($url != '') {
                    $url = $this->findURL($url, $id, $merchTxnRef);
                    ?>
                    <html>

                    <body onLoad="document.response.submit();">
                        <div style="width:300px; margin: 200px auto;">
                            <img src="loading.png" />
                        </div>
                        <form action="<?= $url ?>" name="response" method="GET">
                        </form>
                    </body>

                    </html>
                    <?php
                } else {
                    echo '<h1>Error in Authorisation</h1>';
                    echo '<script>window.ReactNativeWebView.postMessage(failure)
                    </script>';
                }
            } else {
                echo '<h1>Error!</h1>';
                echo '<p></p>';
                echo '<script>window.ReactNativeWebView.postMessage(failure)
                    </script>';
            }
        } else {
            $hashValidated = "<font color='#FF0066'><strong>INVALID HASH</strong></font>";
            echo '<script>window.ReactNativeWebView.postMessage(failure)
            </script>';
            $errorsExist = true;
        }
    }
    /*------------------------------------ RESPONSE FROM Axis (END) -----------------------------------*/


    /*----------------------------------- RESPONSE FROM HDFC PAYU (START) ----------------------------------*/

    public function hdfcPayuResponse(Request $request)
    {
        $data = $request->all();
        $status     = $data['status'];
        $email      = $data['email'];
        $firstname    = $data['firstname'];
        $productinfo  = $data['productinfo'];
        $amount     = $data['amount'];
        $txnid      = $data['txnid'];
        $hash       = $data['hash'];
        $payment_mode   = $data['mode'];
        $mihpayid     = $data['mihpayid'];
        $curdate    = date('dmy');
        $id = str_replace($curdate, '', $txnid);
        $row = $this->invoiceDetails($id);
        $pg_details = $this->pgDetails($row->hotel_id);
        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }
        $key = trim($credentials[0]->key);
        $salt  = trim($credentials[0]->salt);
        $hashData     = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
        if (strlen($hashData) > 0) {
            $secureHash = hash('sha512', $hashData);

            if (strcmp($secureHash, $hash) == 0) {
                if ($status == 'success') {
                    $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

                    if ($url != '') {
                        $url = $this->findURL($url, $id, $txnid);
                    ?>
                        <html>

                        <body onLoad="document.response.submit();">
                            <div style="width:300px; margin: 200px auto;">
                                <img src="loading.png" />
                            </div>
                            <form action="<?= $url ?>" name="response" method="GET">
                            </form>
                        </body>

                        </html>
                    <?php
                    } else {
                        $check = OnlineTransactionDetail::where('transaction_id', $txnid)->first();
                        if (!$check) {
                            $online_payment_data = array(
                                "payment_mode" => $payment_mode,
                                "invoice_id" => $id,
                                "transaction_id" => $txnid,
                                "payment_id" => $mihpayid,
                                "secure_hash" => $hash,
                                "payment_status" => 'failure'
                            );
                            $insertPaymentDetails = OnlineTransactionDetail::insert($online_payment_data);
                        }
                    ?>
                        <script>
                            alert("Sorry! Duplicate Transaction id");
                            window.location.href = '<?= $pg_details->fail_url ?>';
                        </script>
                        <script>
                            window.ReactNativeWebView.postMessage(failure)
                        </script>
                    <?php
                    }
                } else {
                    $check = OnlineTransactionDetail::where('transaction_id', $txnid)->first();
                    if (!$check) {
                        $online_payment_data = array(
                            "payment_mode" => $payment_mode,
                            "invoice_id" => $id,
                            "transaction_id" => $txnid,
                            "payment_id" => $mihpayid,
                            "secure_hash" => $hash,
                            "payment_status" => 'failure'
                        );
                        $insertPaymentDetails = OnlineTransactionDetail::insert($online_payment_data);
                    }
                    echo "<h1 style='color:red;'>Sorry! Transaction Failed</h1>";
                    echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
                }
            } else {
                $check = OnlineTransactionDetail::where('transaction_id', $txnid)->first();
                if (!$check) {
                    $online_payment_data = array(
                        "payment_mode" => $payment_mode,
                        "invoice_id" => $id,
                        "transaction_id" => $txnid,
                        "payment_id" => $mihpayid,
                        "secure_hash" => $hash,
                        "payment_status" => 'failure'
                    );
                    $insertPaymentDetails = OnlineTransactionDetail::insert($online_payment_data);
                }
                echo '<h1>Error!</h1>';
                echo '<p>Hash validation failed</p>';
                echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
            }
        } else {
            $check = OnlineTransactionDetail::where('transaction_id', $txnid)->first();
            if (!$check) {
                $online_payment_data = array(
                    "payment_mode" => $payment_mode,
                    "invoice_id" => $id,
                    "transaction_id" => $txnid,
                    "payment_id" => $mihpayid,
                    "secure_hash" => $hash,
                    "payment_status" => 'failure'
                );
                $insertPaymentDetails = OnlineTransactionDetail::insert($online_payment_data);
            }
            echo '<h1>Error!</h1>';
            echo '<p>Invalid response</p>';
            echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
        }
    }

    /*------------------------------------ RESPONSE FROM HDFC PAYU (END) -----------------------------------*/


    /*----------------------------------- RESPONSE FROM AIRPAY (START) ----------------------------------*/

    public function airpayResponse(Request $request)
    {
        $data = $request->all();
        $TRANSACTIONID = trim($data['TRANSACTIONID']);
        $APTRANSACTIONID  = trim($data['APTRANSACTIONID']);
        $AMOUNT  = trim($data['AMOUNT']);
        $TRANSACTIONSTATUS  = trim($data['TRANSACTIONSTATUS']);
        $MESSAGE  = trim($data['MESSAGE']);
        $ap_SecureHash = trim($data['ap_SecureHash']);
        $CUSTOMVAR  = trim($data['CUSTOMVAR']);


        $curdate    = date('dmy');
        $id       = str_replace($curdate, '', $TRANSACTIONID); //Invoice _id
        $row = $this->invoiceDetails($id);
        $pg_details = $this->pgDetails($row->hotel_id);
        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }
        $username =  trim($credentials[0]->username); //'3183548'; // Username
        $password =  trim($credentials[0]->password); //'k62q6wrT'; // Password
        $secret =    trim($credentials[0]->secret); //'H8rvmSMa55gtX2pZ'; // API key
        $mercid =    trim($credentials[0]->m_id); //'24149'; //Merchant ID


        $merchant_secure_hash = sprintf("%u", crc32($TRANSACTIONID . ':' . $APTRANSACTIONID . ':' . $AMOUNT . ':' . $TRANSACTIONSTATUS . ':' . $MESSAGE . ':' . $mercid . ':' . $username));

        //comparing Secure Hash with Hash sent by Airpay
        if ($ap_SecureHash == $merchant_secure_hash) {
            if ($TRANSACTIONSTATUS == 200) {
                $payment_mode   = 'Online';
                $RefNo      = $TRANSACTIONID;
                $txnid      = $APTRANSACTIONID;
                $hash       = $ap_SecureHash;
                $mihpayid     = $APTRANSACTIONID;

                $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

                if ($url != '') {
                    $url = $this->findURL($url, $id, $txnid);

                    ?>
                    <html>

                    <body onLoad="document.response.submit();">
                        <form action="<?= $url ?>" name="response" method="POST">
                        </form>
                    </body>

                    </html>
                    <?php
                }
            } else {
                echo '<h1>Error!</h1>';
                echo '<p>Sorry! Trasaction not completed.</p>';
                echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
            }
        } else {

            echo '<h1>Error!</h1>';
            echo '<p>Hash validation failed</p>';
            echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
        }
    }

    /*------------------------------------ RESPONSE FROM AIRPAY (END) -----------------------------------*/

    /*----------------------------------- RESPONSE FROM HDFC (START) ----------------------------------*/

    public function hdfcResponse(Request $request)
    {
        $data = $request->all();
        $payment_mode   = $data['Mode'];
        $RefNo      = $data['MerchantRefNo'];
        $txnid      = $data['TransactionID'];
        $hash       = $data['SecureHash'];
        $mihpayid     = $data['PaymentID'];
        $curdate    = date('dmy');
        $HASHING_METHOD = 'sha512';  // md5,sha1


        $id       = str_replace($curdate, '', $RefNo); //Invoice _id
        $invoice_dtl  = $this->invoiceDetails($id); // call to Invoice Details
        $pg_details   = $this->pgDetails($invoice_dtl->hotel_id); // Call to payment Gateway details
        $credentials  = json_decode($pg_details->credentials);

        $account_id   = trim($credentials[0]->account_id);
        $secretkey    = trim($credentials[0]->secretkey);


        // This response.php used to receive and validate response.
        if (!isset($_SESSION['SECRET_KEY']) || empty($_SESSION['SECRET_KEY']))
            $_SESSION['SECRET_KEY'] = $secretkey; //set your secretkey here

        $hashData = $_SESSION['SECRET_KEY'];
        ksort($data);
        foreach ($data as $key => $value) {
            if (strlen($value) > 0 && $key != 'SecureHash') {
                $hashData .= '|' . $value;
            }
        }
        if (strlen($hashData) > 0) {
            $secureHash = strtoupper(hash($HASHING_METHOD, $hashData));
            if ($secureHash == $data['SecureHash']) {
                if ($data['ResponseCode'] == 0) {
                    $status = $this->ApiStaus($mihpayid, $txnid, $account_id, $secretkey);
                    $status_details = json_encode($status);

                    $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $status_details, $txnid);

                    if ($url != '') {
                        $url = $this->findURL($url, $id, $txnid);
                    ?>
                        <html>

                        <body onLoad="document.response.submit();">
                            <form action="<?= $url ?>" name="response" method="POST">
                            </form>
                        </body>

                        </html>
                    <?php
                    }
                } else {
                    $status = $this->statusByRef($account_id, $secretkey, $RefNo);
                    $status_details = json_encode($status);
                    ?>
                    <script>
                        alert("Sorry! Trasaction not completed");
                    </script>
                <?php

                    header('Location: ' . $pg_details->fail_url);
                }
            } else {
                echo '<h1>Error!</h1>';
                echo '<p>Hash validation failed</p>';
                echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
            }
        } else {
            echo '<h1>Error!</h1>';
            echo '<p>Invalid response</p>';
            echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
        }
    }

    /*------------------------------------ RESPONSE FROM HDFC (END) -----------------------------------*/

    /*----------------------------------- RESPONSE FROM WORLDLINE (START) ----------------------------------*/

    public function worldLineResponse(Request $request)
    {
        $data = $request->all();

        $enc_key = "24ca16fcba0400308458afa5033c1426";
        $responseMerchant = $data['merchantResponse'];
        $response = $this->awlmeapi->parseTrnResMsg($responseMerchant, $enc_key);
        $status     = $response->getStatusCode();
        $txnid      = $response->getOrderId();
        $payment_mode   = $response->getRrn();
        $mihpayid     = $response->getPgMeTrnRefNo();
        $curdate    = date('dmy');
        $id = str_replace($curdate, '', $txnid);
        $hash = $response->getAuthZCode();
        if ($status == 'S') {
            $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

            if ($url != '') {
                $url = $this->findURL($url, $id, $txnid);
                ?>
                <html>

                <body onLoad="document.response.submit();">
                    <div style="width:300px; margin: 200px auto;">
                        <img src="loading.png" />
                    </div>
                    <form action="<?= $url ?>" name="response" method="GET">
                    </form>
                </body>

                </html>
            <?php
            } else {
            ?>
                <script>
                    alert("Sorry! Duplicate Transaction id");
                    window.location.href = 'https://norkhil.bookingjini.com';
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
            <?php
            }
        } else {
            ?>
            <script>
                alert("Sorry! Trasaction not completed");
                window.location.href = 'https://norkhil.bookingjini.com';
            </script>
            <script>
                window.ReactNativeWebView.postMessage(failure)
            </script>
            <?php
        }
    }

    /*------------------------------------ RESPONSE FROM WORLDLINE (END) -----------------------------------*/


    /*----------------------------------- RESPONSE FROM SSLCOMMERZ (START) ----------------------------------*/

    public function sslcommerzResponse(Request $request)
    {
        $data = $request->all();
        $status     = $data['status'];

        if ($status == 'VALID') {
            $txnid      = $data['tran_id'];
            $payment_mode   = $data['card_type'];
            $mihpayid     = $data['val_id'];
            $curdate    = date('dmy');
            $id = str_replace($curdate, '', $txnid);
            $hash = $data['verify_sign_sha2'];
            $check = DB::table('online_transaction_details')
                ->select('*')->where('invoice_id', $id)->first();
            if ($check) {
            ?>
                <script>
                    alert("Transaction already processed!");
                    window.location.href = 'https://sayemanbeachresort.bookingjini.com';
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
            <?php
            } else {
                $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);
            }
            if ($url != '') {
                $url = $this->findURL($url, $id, $txnid);
            ?>
                <html>

                <body onLoad="document.response.submit();">
                    <div style="width:300px; margin: 200px auto;">
                        <img src="loading.png" />
                    </div>
                    <form action="<?= $url ?>" name="response" method="GET">
                    </form>
                </body>

                </html>
            <?php
            } else {
            ?>
                <script>
                    alert("Sorry! Duplicate Transaction id");
                    window.location.href = 'https://sayemanbeachresort.bookingjini.com';
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
            <?php
            }
        } else {
            ?>
            <script>
                alert("Sorry! Trasaction not completed");
                window.location.href = 'https://sayemanbeachresort.bookingjini.com';
            </script>
            <script>
                window.ReactNativeWebView.postMessage(failure)
            </script>
            <?php
        }
    }

    /*------------------------------------ RESPONSE FROM SSLCOMMERZ (END) -----------------------------------*/


    /*----------------------------------- RESPONSE FROM ATOMPAY (START) ----------------------------------*/

    public function atompayResponse(Request $request)
    {
        $data = $request->all();
        require_once 'Atompay/TransactionResponse.php';
        $transactionResponse = new TransactionResponse();
        $pg_details = $this->pgDetails(1807);
        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }
        $transactionResponse->setRespHashKey(trim($credentials[0]->rs_key));
        $transactionResponse->setResponseEncypritonKey(trim($credentials[0]->rs_enc_key));
        $transactionResponse->setSalt(trim($credentials[0]->rs_salt));
        $arrayofdata = $transactionResponse->decryptResponseIntoArray($_POST['encdata']);

        if ($arrayofdata['f_code'] == 'Ok') {
            $txnid      = $arrayofdata['mer_txn'];
            $payment_mode   = $arrayofdata['discriminator'];
            $mihpayid     = $arrayofdata['mmp_txn'];
            $curdate    = date('dmy');
            $id = str_replace($curdate, '', $txnid);
            $hash = $arrayofdata['signature'];

            $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

            if ($url != '') {
                $url = $this->findURL($url, $id, $txnid);
            ?>
                <html>

                <body onLoad="document.response.submit();">
                    <div style="width:300px; margin: 200px auto;">
                        <img src="loading.png" />
                    </div>
                    <form action="<?= $url ?>" name="response" method="GET">
                    </form>
                </body>

                </html>
            <?php
            } else {
            ?>
                <script>
                    alert("Sorry! Duplicate Transaction id");
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
            <?php
            }
        } else {
            ?>
            <script>
                alert("Sorry! Trasaction not completed");
            </script>
            <script>
                window.ReactNativeWebView.postMessage(failure)
            </script>
            <?php
        }
    }

    /*------------------------------------ RESPONSE FROM ATOMPAY (END) -----------------------------------*/



    /*----------------------------------- RESPONSE FROM ICICI (START) ----------------------------------*/

    public function iciciResponse(Request $request)
    {
        $data = $request->all();
        if ($data['status'] == 'APPROVED') {
            $txnid      = $data['endpointTransactionId'];
            $payment_mode   = $data['paymentMethod'];
            $mihpayid     = $data['ipgTransactionId'];
            $curdate    = date('dmy');
            $id = str_replace($curdate, '', $data['oid']);
            $hash = $data['response_hash'];

            $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);

            if ($url != '') {
                $url = $this->findURL($url, $id, $txnid);
            ?>
                <html>

                <body onLoad="document.response.submit();">
                    <div style="width:300px; margin: 200px auto;">
                        <img src="loading.png" />
                    </div>
                    <form action="<?= $url ?>" name="response" method="GET">
                    </form>
                </body>

                </html>
            <?php
            } else {
            ?>
                <script>
                    alert("Sorry! Duplicate Transaction id");
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
            <?php
            }
        } else {
            ?>
            <script>
                alert("Sorry! Trasaction not completed");
            </script>
            <script>
                window.ReactNativeWebView.postMessage(failure)
            </script>
            <?php
        }
    }

    /*------------------------------------ RESPONSE FROM ICICI (END) -----------------------------------*/



    /*----------------------------------- RESPONSE FROM RAZORPAY (START) ----------------------------------*/

    public function razorpayResponse(Request $request)
    {
        $data = $request->all();
        if (isset($data['razorpay_payment_id'])) {
            $txnid = $data['razorpay_payment_id'];
            $string = $data['razorpay_order_id'] . "|" . $txnid;
            $id = (int)$this->fetchOrder($data['razorpay_order_id']);
            $invoice_details = Invoice::where('invoice_id', $id)->first();
            $hotel_id       = $invoice_details['hotel_id'];
            $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
            $company_id     = $company_dtls['company_id'];
            $getPaymentgateway_key = PaymentGetway::select('credentials')->where('company_id', $company_id)->first();
            $credentials = json_decode($getPaymentgateway_key->credentials);
            $secureHash = hash_hmac('sha256', $string, $credentials[0]->secret);
            if ($secureHash == $data['razorpay_signature']) {
                // $id=$this->fetchOrder($data['razorpay_order_id']);
                $url = $this->booking_engine->successBooking($id, $txnid, 'NA', $data['razorpay_signature'], $data['razorpay_order_id']);
                if ($url != '') {
                    $url = $this->findURL($url, $id, $txnid);
            ?>
                    <html>

                    <body onLoad="document.response.submit();">
                        <div style="width:300px; margin: 200px auto;">
                            <img src="loading.png" />
                        </div>
                        <form action="<?= $url ?>" name="response" method="GET">
                        </form>
                    </body>

                    </html>
                <?php
                }
            } else {
                $CompanyDetaiils   = CompanyDetails::select('*')->where('company_id', $company_id)->first();
                $url = $CompanyDetaiils['company_url'];
                ?>
                <script>
                    alert("Sorry! Trasaction not completed");
                    window.location.href = "#";
                </script>
                <script>
                    window.ReactNativeWebView.postMessage(failure)
                </script>
        <?php
            }
        }
    }
    public function razorpayCancel($invoice_data)
    {
        $invoice_id = (int)base64_decode($invoice_data);
        $invoice_details = Invoice::select('hotel_id')->where('invoice_id', $invoice_id)->first();
        $hotel_id       = $invoice_details['hotel_id'];
        $company_dtls   = HotelInformation::select('company_id')->where('hotel_id', $hotel_id)->first();
        $company_id     = $company_dtls['company_id'];
        $CompanyDetaiils   = CompanyDetails::select('*')->where('company_id', $company_id)->first();
        $url = $CompanyDetaiils['company_url'];
        ?>
        <script>
            alert("Sorry! Trasaction not completed");
            window.location.href = "http://<?= $url ?>";
        </script>
        <script>
            window.ReactNativeWebView.postMessage(failure)
        </script>
        <?php
    }

    /*------------------------------------ RESPONSE FROM RAZORPAY (END) -----------------------------------*/

    /*----------------------------------- RESPONSE FROM CCAVENUE (START) ----------------------------------*/

    public function ccavenueResponse(Request $request)
    {
        $data = $request->all();
        $working_key = 'BC3EE2CB3F405167BC0AC4EE0EF43854'; //Shared by CCAVENUES
        $access_code = 'AVVS90HA11CI96SVIC'; //Shared by CCAVENUES
        $encResponse = $data["encResp"];
        if ($encResponse != '') {
            $key = $this->hextobin(md5($working_key));
            $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
            $encryptedText = $this->hextobin($encResponse);
            $rcvdString = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
            $order_status = "";
            $tracking_id = "";
            $bank_ref_no = "";
            $payment_mode = "";
            $decryptValues = explode('&', $rcvdString);
            $dataSize = sizeof($decryptValues);

            for ($i = 0; $i < $dataSize; $i++) {
                $information = explode('=', $decryptValues[$i]);
                if ($i == 3) $order_status = $information[1];
                if ($i == 1) $tracking_id = $information[1];
                if ($i == 2) $bank_ref_no = $information[1];
                if ($i == 5) $payment_mode = $information[1];
            }
            if ($order_status === "Success") {
                $txnid      = $tracking_id;
                $payment_mode   = $payment_mode;
                $mihpayid     = $bank_ref_no;
                $curdate    = date('dmy');
                $id = str_replace($curdate, '', $data['orderNo']);
                $hash = $encResponse;
                $url = $this->booking_engine->successBooking($id, $mihpayid, $payment_mode, $hash, $txnid);
                if ($url != '') {
                    $url = $this->findURL($url, $id, $txnid);
        ?>
                    <html>

                    <body onLoad="document.response.submit();">
                        <div style="width:300px; margin: 200px auto;">
                            <img src="loading.png" />
                        </div>
                        <form action="<?= $url ?>" name="response" method="GET">
                        </form>
                    </body>

                    </html>
                <?php
                }
            } else if ($order_status === "Aborted") {
                echo "<br>Thank you for shopping with us.We will keep you posted regarding the status of your order through e-mail";
                echo '<script>window.ReactNativeWebView.postMessage(failure)
                </script>';
            } else if ($order_status === "Failure") {
                echo "<br>Thank you for shopping with us.However,the transaction has been declined.";
                echo '<script>window.ReactNativeWebView.postMessage(failure)
                </script>';
            } else {
                echo "<br>Security Error. Illegal access detected";
                echo '<script>window.ReactNativeWebView.postMessage(failure)
                </script>';
            }
        }
    }
    /*----------------------------------- RESPONSE FROM CCAVENUE (END) ----------------------------------*/

    /*----------------------------------- RESPONSE FROM PAYTM (START) ----------------------------------*/
    public function paytmResponse(Request $request)
    {
        $data = $request->all();
        $curdate    = date('dmy');
        $id = str_replace($curdate, '', $data['ORDERID']);
        $row = $this->invoiceDetails($id);
        $pg_details = $this->pgDetails($row->hotel_id);
        if ($pg_details) {
            $credentials = json_decode($pg_details->credentials);
        }
        $mid = $credentials[0]->m_id;
        $pkey = $credentials[0]->key;
        $paytmChecksum = "";
        $paytmParams = array();
        foreach ($data as $key => $value) {
            if ($key == "CHECKSUMHASH") {
                $paytmChecksum = $value;
            } else {
                $paytmParams[$key] = $value;
            }
        }
        $paytmParams["MID"] = $mid;
        $paytmParams["ORDERID"] = trim($data["ORDERID"]);
        $checksum = $this->paytm_controller->getChecksumFromArray($paytmParams, $pkey);
        $paytmParams["CHECKSUMHASH"] = $checksum;
        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $purl = "https://securegw.paytm.in/order/status";
        $ch = curl_init($purl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $resp = json_decode($response);
        $isValidChecksum = $this->paytm_controller->verifychecksum_e($paytmParams, $pkey, $paytmChecksum);
        if ($isValidChecksum == "TRUE" && $resp->STATUS == 'TXN_SUCCESS' && $resp->RESPCODE == 01) {
            if ($resp->TXNID == $data['TXNID'] && $resp->TXNAMOUNT == $data['TXNAMOUNT']) {

                $url = $this->booking_engine->successBooking($id, $resp->TXNID, $resp->PAYMENTMODE, $checksum, $data['ORDERID']);
                if ($url != '') {
                    $url = $this->findURL($url, $id, $resp->TXNID);
                ?>
                    <html>

                    <body onLoad="document.response.submit();">
                        <div style="width:300px; margin: 200px auto;">
                            <img src="loading.png" />
                        </div>
                        <form action="<?= $url ?>" name="response" method="GET">
                        </form>
                    </body>

                    </html>
                <?php
                } else {
                    echo '<script>window.ReactNativeWebView.postMessage(failure)
                </script>';
                    echo '<h1>Error!</h1>';
                    echo '<p>Database error</p>';
                }
            } else {
                ?>
                <script>
                    alert("Sorry! Trasaction not completed");
                </script>
<?php
            }
        } else {
            echo '<script>window.ReactNativeWebView.postMessage(failure)
        </script>';
            echo '<h1>Error!</h1>';
            echo '<p>Invalid response</p>';
        }
    }

    /*----------------------------------- RESPONSE FROM PAYTM (END) ----------------------------------*/




    /*----------------------------------- Support functions for HDFC (START) ----------------------------------*/

    public function curlPost($URL, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function xmlObject2Array($xml)
    {
        $xmlObject = simplexml_load_string($xml);
        $xmlArray  = json_decode(json_encode($xmlObject), 1);
        return $xmlArray;
    }

    public function ApiStaus($mihpayid, $txnid, $account_id, $secretkey)
    {
        // Action - status - sample code
        $action = 'status';
        $paymentid = $mihpayid; //HDFC Payment id
        $accountid = $account_id; //HDFC Accout id
        $secretkey = $secretkey; //HDFC secret key
        $transactionid = $txnid; //HDFC Transaction id
        $fields = array(
            'Action' => 'status',
            'AccountID' => '',
            'SecretKey' => '',
            'TransactionID' => '',
            'PaymentID' => ''
        );
        $files = array(
            array(
                'name' => 'uimg',
                'type' => 'image/jpeg',
                'file' => './profile.jpg',
            )
        );
        $url = 'https://api.secure.ebs.in/api/1_0';
        $data = "Action=" . $action . "&TransactionID=" . $transactionid . "&AccountID=" . $accountid . "&SecretKey=" . $secretkey . "&PaymentID=" . $paymentid;
        $xmlResponse   =   $this->curlPost('https://api.secure.ebs.in/api/1_0', $data);
        //$xmlResponse = http_post_fields($url, $data, $files);
        $responseArr   =   $this->xmlObject2Array($xmlResponse);
        $response      =   $responseArr['@attributes'];
        //print_r($response);
        return $response;
    }
    public function statusByRef($accountid, $secretkey, $ref_no)
    {
        // Action - status - sample code
        $action = 'statusByRef';
        $url = 'https://api.secure.ebs.in/api/1_0';
        $data = "Action=" . $action . "&AccountID=" . $accountid . "&SecretKey=" . $secretkey . "&RefNo=" . $ref_no;
        $xmlResponse   =   $this->curlPost('https://api.secure.ebs.in/api/1_0', $data);
        //$xmlResponse = http_post_fields($url, $data, $files);
        $responseArr   =   $this->xmlObject2Array($xmlResponse);
        $response      =   $responseArr['@attributes'];
        //print_r($response);
        return $response;
    }

    /*----------------------------------- Support functions for HDFC (END) ----------------------------------*/

    public function invoiceDetails($invoice_id)
    {
        $row = DB::table('invoice_table')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
            ->select(
                'user_table.company_id',
                'user_table.first_name',
                'user_table.last_name',
                'user_table.email_id',
                'user_table.mobile',
                'user_table.address',
                'user_table.country',
                'user_table.zip_code',
                'user_table.state',
                'user_table.city',
                'invoice_table.paid_amount',
                'invoice_table.total_amount',
                'invoice_table.hotel_name',
                'invoice_table.hotel_id',
                'company_table.company_full_name'
            )
            ->where('invoice_table.invoice_id', '=', $invoice_id)
            ->first();
        $packagerow = DB::table('package_invoice')
            ->join('kernel.user_table', 'package_invoice.user_id', '=', 'user_table.user_id')
            ->select(
                'user_table.company_id',
                'user_table.first_name',
                'user_table.last_name',
                'user_table.email_id',
                'user_table.mobile',
                'user_table.address',
                'user_table.country',
                'user_table.zip_code',
                'user_table.state',
                'user_table.city',
                'package_invoice.paid_amount',
                'package_invoice.total_amount',
                'package_invoice.hotel_name',
                'package_invoice.hotel_id'
            )
            ->where('package_invoice.invoice_id', '=', $invoice_id)
            ->first();
        if ($row) {
            return  $row;
        }
        if ($packagerow) {
            return  $packagerow;
        }
    }
    public function keepOrder($invoice_id, $order_id)
    {
        $row = DB::table('online_transaction_details')
            ->insert(['payment_mode' => 'NA', 'invoice_id' => $invoice_id, 'transaction_id' => $order_id, 'payment_id' => 'NotPaid', 'secure_hash' => 'NA']);
    }
    public function fetchOrder($order_id)
    {
        $row = DB::table('online_transaction_details')
            ->select('invoice_id')
            ->where('transaction_id', '=', $order_id)
            ->first();
        return $row->invoice_id;
    }

    public function pgDetails($hotel_id)
    {
        $pg_details =DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials', 'fail_url')
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->first();
        return $pg_details;
    }
    /*public function getCurrency($company_id)
     {
     $currency= DB::table('company_table')
     ->join('currency_info_table', 'company_table.currency', '=', 'currency_info_table.currency')
     ->select('company_table.currency', 'currency_info_table.currency_code')
     ->where('company_id', '=', $company_id)
     ->first();
     return $currency;
     }*/

    public function getCurrency($company_id)
    {
        $currency = DB::table('kernel.company_table')
            ->join('kernel.currencies', 'company_table.currency', '=', 'currencies.name')
            ->select(
                'company_table.currency',
                'currencies.numeric_code'
            )
            ->where('company_id', '=', $company_id)
            ->first();
        return $currency;
    }

    /*
         Function that calculates the hash of the following parameters:
         - Store Id
         - Date/Time(see $dateTime above)
         - chargetotal
         - shared secret
         */
    public function createHash($chargetotal, $Currency)
    {
        // Please change the store Id to your individual Store ID
        $storeId = "3387031062";
        // NOTE: Please DO NOT hardcode the secret in that script. For example read it from a database.
        $sharedSecret = "Lh78maArTx";

        $stringToHash = $storeId . date('Y:m:d-H:i:s') . $chargetotal . $Currency . $sharedSecret;
        $ascii = bin2hex($stringToHash);

        return sha1($ascii);
    }
    public function hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }

            $count += 2;
        }
        return $binString;
    }

    public function findURL($url, $invoice_id, $txnid)
    {
        $row = $this->invoiceDetails($invoice_id);
        $hotel_id = $row->hotel_id;
        $google_hotel_list = MetaSearchEngineSetting::get();
        $str = $google_hotel_list[0]['hotels'];
        $hotel_array = explode(",", $str);
        if (in_array($hotel_id, $hotel_array)) {
            $q = base64_encode($invoice_id . "||" . $url . "||");
            $url = "https://bookingjini.com/thankyou-page?q=" . $q;
        } else {

            if (strpos($url, 'bookingjini.com')) {
                $url = "https://" . $url . "/invoice/" . $invoice_id . "/" . $txnid;
            } else {
                $url = "https://" . $url . "/invoice/" . $invoice_id . "/" . $txnid;
            }
        }

        return $url;
    }

    //Stripe cancle url
    public function StripeCancelResponse()
    {
        $data = '<!DOCTYPE html>
        <html>
        <head>
          <title>Checkout canceled</title>
          <link rel="stylesheet" href="style.css">
        </head>
        <body>
          <section>
            <p>Booking cancelled</p>
          </section>
        </body>
        </html>';

        return $data;
    }
}