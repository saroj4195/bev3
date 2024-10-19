<?php
namespace App\Http\Controllers\Extranetv4\crs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
use App\Http\Controllers\Extranetv4\crs\CrsReservationController;
use App\Role;
use App\AdminUser;
use App\Http\Controllers\Controller;

class CrsPaymentGatewayController extends Controller
{
    protected $crs_reservation;
    public function __construct( CrsReservationController $crs_reservation)
    {
       $this->crs_reservation=$crs_reservation;
    }
    public function actionIndex(string $invoice_id, string $pay_status, Request $request)
    {  
        $amount=0;
        $invoice_id=base64_decode($invoice_id);
        $txnid=date('Ymd').$invoice_id;
        $row= $this->invoiceDetails($invoice_id);
        $amount=$this->getFinalPayment($invoice_id,$pay_status);
      
        if(!isset($row))
        {
            $res="Chekin date already expired!";
            return response()->json($res);
        }
        if($amount==0)
        {
            $res="Payment already paid for this booking!";
            return response()->json($res);
        }
        $pg_details=$this->pgDetails($row->company_id);
        if(sizeof($pg_details)>0)
        {
          $credentials = json_decode($pg_details->credentials);
        }
        
      $values = array("key"=>"HpCvAH", "txnid"=>"$txnid", "amount"=>"$amount", "productinfo"=>"$row->hotel_name Booking", "firstname"=>"$row->first_name", "email"=>"$row->email_id", "phone"=>"$row->mobile", "lastname"=>"$row->last_name", "address1"=>"Address", "city"=>"Bhubaneswar", "state"=>"Odisha", "country"=>"IND", "zipcode"=>"751001", "surl"=>"https://www.bookingjini.in/v3/api/crs/payu-response", "furl"=>"https://www.bookingjini.in/v3/api/crs/payu-fail");
      $hashData='HpCvAH|'.$txnid.'|'.$amount.'|'.$row->hotel_name.' Booking|'.$row->first_name.'|'.$row->email_id.'|||||||||||sHFyCrYD';
      //$values = array("key"=>"HpCvAH", "txnid"=>"$txnid", "amount"=>2, "productinfo"=>"$row->hotel_name Booking", "firstname"=>"$row->first_name", "email"=>"$row->email_id", "phone"=>"$row->mobile", "lastname"=>"$row->last_name", "address1"=>"Address", "city"=>"Bhubaneswar", "state"=>"Odisha", "country"=>"IND", "zipcode"=>"751001", "surl"=>"https://www.bookingjini.in/v3/api/crs/payu-response", "furl"=>"https://www.bookingjini.in/v3/api/crs/payu-fail");
      // $hashData='HpCvAH|'.$txnid.'|2|'.$row->hotel_name.' Booking|'.$row->first_name.'|'.$row->email_id.'|||||||||||sHFyCrYD';
      
      $ACTION_URL = "https://secure.payu.in/_payment";
      if (strlen($hashData) > 0) {
        $secureHash = hash('sha512', $hashData);
      }
      ?>
      <html>
      <body onLoad="document.payment.submit();">
      <div style="width:300px; margin: 200px auto;">
      <img src="loading.png" />
      </div>
      <form action="<?php echo $ACTION_URL;?>" name="payment" method="POST">
      <?php
        foreach($values as $key => $value) {
      ?>
      <input type="hidden" value="<?php echo $value;?>" name="<?php echo $key;?>"/>
      <?php
        }
      ?>
      <input type="hidden" value="<?php echo $secureHash; ?>" name="hash"/>
      </form>
      </body>
      </html>
      <?php
    }
    /* payment getway for pending conformation of agent start */
    public function payuIndex(string $invoice_id, Request $request)
    {  
      $amount=0;
      $invoice_id=base64_decode($invoice_id);
      $txnid=date('Ymd').$invoice_id;
      $row= $this->invoiceDetails($invoice_id);
      $amount=$this->getFinalPayment($invoice_id);
      $pg_details=$this->pgDetails($row->company_id);
      if(sizeof($pg_details)>0)
      {
        $credentials = json_decode($pg_details->credentials);
      }
      
    $values = array("key"=>"HpCvAH", "txnid"=>"$txnid", "amount"=>"$amount", "productinfo"=>"$row->hotel_name Booking", "firstname"=>"$row->first_name", "email"=>"$row->email_id", "phone"=>"$row->mobile", "lastname"=>"$row->last_name", "address1"=>"Address", "city"=>"Bhubaneswar", "state"=>"Odisha", "country"=>"IND", "zipcode"=>"751001", "surl"=>"https://www.bookingjini.in/v3/api/crs/payu-response", "furl"=>"https://www.bookingjini.in/v3/api/crs/payu-fail");
    $hashData='HpCvAH|'.$txnid.'|'.$amount.'|'.$row->hotel_name.' Booking|'.$row->first_name.'|'.$row->email_id.'|||||||||||sHFyCrYD';
    $ACTION_URL = "https://secure.payu.in/_payment";
    if (strlen($hashData) > 0) {
      $secureHash = hash('sha512', $hashData);
    }
    ?>
    <html>
    <body onLoad="document.payment.submit();">
    <div style="width:300px; margin: 200px auto;">
    <img src="loading.png" />
    </div>
    <form action="<?php echo $ACTION_URL;?>" name="payment" method="POST">
    <?php
      foreach($values as $key => $value) {
    ?>
    <input type="hidden" value="<?php echo $value;?>" name="<?php echo $key;?>"/>
    <?php
      }
    ?>
    <input type="hidden" value="<?php echo $secureHash; ?>" name="hash"/>
    </form>
    </body>
    </html>
    <?php
    }
    /*payment getway for pending conformation of agent end*/

    
/*----------------------------------- RESPONSE FROM PAYU (START) ----------------------------------*/

public function payuResponse(Request $request)
{
$data=$request->all();
$status     = $data['status'];
$email      = $data['email'];
$firstname    = $data['firstname'];
$productinfo  = $data['productinfo'];
$amount     = $data['amount'];
$txnid      = $data['txnid'];
$hash       = $data['hash'];
$payment_mode   = $data['mode'];
$mihpayid     = $data['mihpayid'];
$curdate    = date('Ymd');
$hashData     = 'sHFyCrYD|'.$status.'|||||||||||'.$email.'|'.$firstname.'|'.$productinfo.'|'.$amount.'|'.$txnid.'|HpCvAH';
// $success=$this->crs_reservation->successBooking('12828', '8945124037', 'NB', 'e9857007bf72573cb9c4ef6601a9bd3cfb1b5ba95b5a1ded71e55ae5c308ed3265fb9cb3ce6cb5a3ecf807c3d426bf71a59bd4f90209dc9101e5a0d3c1adaeb2', '2019082212844');

// if($success)
// {                 
// $res="Payment received successfully,Please check your email account for details";
// return response()->json($res);   
// return response()->json($res);   
// }
// else
// {
// $res="Sorry! Transaction not completed, please try once again";
// return response()->json($res);  
// }
if (strlen($hashData) > 0) 
{
  $secureHash = hash('sha512' , $hashData);
  if(strcmp($secureHash,$hash)== 0 )
  {
    if($status == 'success')
    {
      $invoice_id=str_replace($curdate,'',$txnid);
      $success=$this->crs_reservation->successBooking($invoice_id, $mihpayid, $payment_mode, $hash, $txnid);
      if($success)
        {                 
        $res="Payment received successfully,please check your email account for details";
        return response()->json($res);   
        }
        else
        {
        $res="Sorry! Trasaction not completed, please try once again";
        return response()->json($res);  
        }
    } 
    else 
    {
        $res="Sorry! Transaction not completed, please try once again";
            return response()->json($res);  
    }
  } 
  else
  {
    $res="Hash validation failed, please try once again";
    return response()->json($res); 
  }
} 
else 
{
  $res="Invalid response, please try once again";
  return response()->json($res); 
}   
}

/*------------------------------------ RESPONSE FROM PAYU (END) -----------------------------------*/
public function invoiceDetails($invoice_id)
{
  $invoice_id=str_replace(date('dmy'),'',$invoice_id);
    $today=date('Y-m-d');  
    $row= DB::table('invoice_table')
        ->join('user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->select('user_table.company_id', 'user_table.first_name', 'user_table.last_name',
                    'user_table.email_id','user_table.mobile',
                    'invoice_table.paid_amount','invoice_table.hotel_name', 'invoice_table.hotel_id'
                )
        ->where('invoice_table.invoice_id', '=', $invoice_id)
        ->where('hotel_booking.check_in', '>=', $today)
        ->first();
        return $row;
}

public function invoiceData($invoice_id)
{
    $row= DB::table('invoice_table')
        ->join('user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->select('user_table.company_id', 'user_table.first_name', 'user_table.last_name',
        'user_table.email_id','user_table.mobile',
        'invoice_table.paid_amount','invoice_table.hotel_name', 'invoice_table.hotel_id'
            )
        ->where('invoice_table.invoice_id', '=', $invoice_id)
        ->first();
        return $row;
}

public function getFinalPayment($invoice_id,$pay_status)
{
  $invoice_id=str_replace(date('dmy'),'',$invoice_id);
  $agent_type=DB::table('agent_credit_balance_details')->where('invoice_id', '=', $invoice_id)->first();
    $row= DB::table('crs_reservation')
    ->join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
    ->join('admin_table', 'crs_reservation.for_user_id', '=', 'admin_table.admin_id')
    ->select('admin_table.agent_commission',
                'invoice_table.paid_amount','admin_table.role_id','crs_reservation.pay_status',
                'crs_reservation.adjusted_amount','crs_reservation.credit_status'
            )
    ->where('invoice_table.invoice_id', '=', $invoice_id)
    ->first();
   
    if($row->role_id==4)
    {
        if($row->agent_commission>0)
        {
        $row->paid_amount=$row->paid_amount - ($row->paid_amount* $row->agent_commission)/100;
        }
    }
   if($row->pay_status==0)
   {
    return $row->paid_amount;
   }
   else if($row->pay_status==1 && $row->credit_status == 'credit')
   {
     if(sizeof($agent_type)>0)
     {
        if($pay_status == 'c_pay')
        {
            return $agent_type->agent_credit;
        }
        else{
            return $row->paid_amount;
        }
     }
     else{
      return $row->paid_amount;
     }
     
   }
   else
   {
    return 0;
   }
}


public function pgDetails($company_id)
{
  $pg_details=DB::table('payment_gateway_details')
        ->select('provider_name', 'credentials', 'fail_url')
        ->where('company_id', '=', $company_id)
        ->first();
   return $pg_details;
}
public function updateAgentCreditStatus($invoice_id){
    $data= DB::table('crs_reservation')->where('crs_reservation.pay_status', '=', 1)
    ->where('crs_reservation.credit_status', '=', 'credit')
    ->where('crs_reservation.invoice_id',$invoice_id)->first();
    if($data){
      DB::table('crs_reservation')->where('invoice_id', $invoice_id)
      ->update(['credit_status'=>"paid"]);//Paid for credit paid by agent
    }
}
}
