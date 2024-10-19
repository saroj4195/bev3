<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use DB;

use App\Http\Controllers\Controller;

require('vendor/autoload.php');

// use Razorpay\Api\Api;
// use App\Invoice;
// use App\HotelInformation;
// use App\CompanyDetails;
// use App\OnlineTransactionDetail;
// use App\Http\Controllers\Axis\VPCPaymentConnection;
// use App\Http\Controllers\Paytm\PaytmEncdecController;
// use App\MetaSearchEngineSetting;
// use App\PaymentGetway;

class StripeController extends Controller
{
  public function Stripe()
  {

    \Stripe\Stripe::setApiKey('sk_test_51LAs7KD9rbSv7Bjn0wXmEjp0hgKtLDitjyPgHhrIfbcLw5gPjYnenIVR4lp0NKvlHLaJh6wKHQ9jDV4yPzxMJwP0008UYbGPqU');

    header('Content-Type: application/json');

    $booking_id = '12072256789';
    $booking_date = "2022-07-13";
    $amount = '2000';

    $checksum = $booking_id . '|' . $booking_date . '|' . $amount;

    $checksum = md5($checksum);
    $booking_id = base64_encode($booking_id);
    $booking_date = base64_encode($booking_date);
    $amount = base64_encode($amount);


    //dd();
    $checkout_session = \Stripe\Checkout\Session::create([
      'line_items' => [[
        'price_data' => [
          'currency' => 'usd',
          'product_data' => [
            'name' => 'room',
          ],
          'unit_amount' => 2000,
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
  }

  public function StripeSuccessResponse($booking_id, $booking_date, $amount, $checksum)
  {
    //dd($booking_id,$booking_date,$amount,$checksum);
    $booking_id = base64_decode($booking_id);

    $booking_date = base64_decode($booking_date);
    $amount = base64_decode($amount);
    $response_checksum = $booking_id . '|' . $booking_date . '|' . $amount;

    $response_checksum = md5($response_checksum);

    //dd($response_checksum,$checksum);
    if ($checksum == $response_checksum) {
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
    return $data;
  }
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
