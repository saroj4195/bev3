<?php
namespace App\Http\Controllers\Extranetv4\Paytm;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Http\Controllers\Controller;
use App\Invoice;
use App\MasterRoomType;
use App\Http\Controllers\Extranetv4\Paytm\PaytmEncdecController;
/**
* Paytm Payment getway
*@author : Ranjit @Date : 20-05-2020
*@userstory : This controller is used to handle paytm transection request and response.
*/
class PaytmPaymentgetwayController extends Controller
{
    protected $paytm_controller;
    public function __construct(PaytmEncdecController $paytm_controller)
    {
       $this->paytm_controller=$paytm_controller;
    }
    public function paytmTransectionRequest(string $invoice_id, Request $request){
      $row= $this->invoiceDetails($invoice_id);
      $paytmParams = array(
      	"MID"=>"PLAshk33832364930670",
          "WEBSITE" => "WEBSTAGING",
          "INDUSTRY_TYPE_ID" => "Retail",
      	"CHANNEL_ID" => "WEB",
      	"ORDER_ID" => $invoice_id,
      	"CUST_ID" => trim($row->user_id),
      	"MOBILE_NO" => trim($row->mobile),
      	"EMAIL" => trim($row->email_id),
      	"TXN_AMOUNT" => trim($row->paid_amount),
      	"CALLBACK_URL" => "http://api.bookingjini.com/api/paytm/paytm-payment-response",
      );
      $checksum = $this->paytm_controller->getChecksumFromArray($paytmParams, "rbMI8R7KexaE25LL");
      /* for Staging */
      //$url = "https://securegw-stage.paytm.in/order/process";

      /* for Production */
       $url = "https://securegw-stage.paytm.in/order/process";

      /* Prepare HTML Form and Submit to Paytm */
      ?>
      <html>
      	<head>
      		<title>Merchant Checkout Page</title>
      	</head>
      	<body>
      		<center><h1>Please do not refresh this page...</h1></center>
      		<form method='post' action='<?php echo $url; ?>' name='paytm_form'>
      				<?php
      					foreach($paytmParams as $name => $value) {
      						echo '<input type="hidden" name="' . $name .'" value="' . $value . '">';
      					}
      				?>
      				<input type="hidden" name="CHECKSUMHASH" value="<?php echo $checksum ?>">
      		</form>
      		<script type="text/javascript">
      			document.paytm_form.submit();
      		</script>
      	</body>
      </html>
      <?php
    }
    public function paytmTransectionResponse(Request $request){
      $data=$request->all();
      $paytmChecksum = "";
      /* Create a Dictionary from the parameters received in POST */
      $paytmParams = array();
      foreach($data as $key => $value){
          if($key == "CHECKSUMHASH"){
              $paytmChecksum = $value;
          } else {
              $paytmParams[$key] = $value;
          }
      }
      /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
      $paytmParams["MID"] = "PLAshk33832364930670";
      /* Enter your order id which needs to be check status for */
      $paytmParams["ORDERID"] =trim($data["ORDERID"]);
      /**
       * Generate checksum by parameters we have in body
       * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys
       */
      $checksum = $this->getChecksumFromArray($paytmParams, "rbMI8R7KexaE25LL");
      /* put generated checksum value here */
      $paytmParams["CHECKSUMHASH"] = $checksum;
      /* prepare JSON string for request */
      $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
      /* for Staging */
      //$url = "https://securegw-stage.paytm.in/order/status";
      /* for Production */
      $url = "https://securegw-stage.paytm.in/order/status";

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
      $response = curl_exec($ch);
      $resp = json_decode($response);

      /**
       * Verify checksum
       * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys
       */
      $isValidChecksum = $this->verifychecksum_e($paytmParams, "rbMI8R7KexaE25LL", $paytmChecksum);
      if($isValidChecksum == "TRUE" && $resp->STATUS == 'TXN_SUCCESS' && $resp->RESPCODE == 01) {
          if($resp->TXNID == $data['TXNID'] && $resp->TXNAMOUNT == $data['TXNAMOUNT']){
              $id=$data['ORDERID'];
              $url=$this->booking_engine->successBooking($id, $paytmParams["MID"], $resp->PAYMENTMODE, $checksum, $resp->TXNID);
              if($url!=''){
                ?>
                <html>
                  <body onLoad="document.response.submit();">
                    <div style="width:300px; margin: 200px auto;">
                      <img src="loading.png" />
                    </div>
                    <form action="http://<?=$url?>/v3/ibe/#/invoice-details/<?=$id?>/<?=$resp->TXNID?>" name="response" method="GET">
                    </form>
                  </body>
                  </html>
              <?php
              }
              else{
                    echo '<h1>Error!</h1>';
                    echo '<p>Database error</p>';
              }
            }
          else{
            ?>
                <script>alert("Sorry! Trasaction not completed");</script>
                <?php
            header('Location: index.php');
          }
      }
      else{
        echo '<h1>Error!</h1>';
        echo '<p>Invalid response</p>';
      }
    }
    public function invoiceDetails($invoice_id)
    {
      $row= Invoice::join('user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->select('user_table.email_id','user_table.mobile','user_table.user_id','invoice_table.paid_amount','invoice_table.total_amount')
            ->where('invoice_table.invoice_id', '=', $invoice_id)
            ->first();
            return $row;
    }
}
