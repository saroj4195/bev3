<?php
namespace App\Http\Controllers\Extranetv4;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Http\Controllers\Extranetv4\CrsReservationController;
use App\Http\Controllers\Extranetv4\ManageCrsRatePlanController;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Booking;
use App\Invoice;
use App\CrsReservation;
use App\AgentCredit;
use DB;
//create a new class OfflineBookingController
class AgentReservationController extends Controller
{   
protected $roomRateService;
    protected $updateInventoryService;
protected $crsReservationService;
    public function __construct(
ManageCrsRatePlanController $roomRateService,
    UpdateInventoryService $updateInventoryService,
CrsReservationController $crsReservationService)
    {
$this->roomRateService = $roomRateService;
        $this->updateInventoryService = $updateInventoryService;
$this->crsReservationService= $crsReservationService;
    }
//validation rules
     private $rules = array(
'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
'to_date' => 'required',
        'cart' => 'required',
'agent_credit'=>'required',
        'user_data'=>'required'
);
      //Custom Error Messages
private $messages = [
        'hotel_id.required' => 'The hotel id field is required.',
'hotel_id.numeric' => 'The hotel id must be numeric.',
        'from_date.required' => 'Check in date is required.',
'to_date.required' => 'Check out is required.',
        'agent_credit.required' => 'Agent credit amount is required.',
'user_data.required' => 'user_data is required.'
        ];         
//Bookings save starts here
public function newReservation(Request $request)
{
  $hotel_id=$request->input('hotel_id');
$pay_type=$request->input('pay_type');
  $percentage=0;//THis shoud be set from Mail invoice
$invoice= new Invoice();
  $booking= new Booking();
$crs_reservation= new CrsReservation();
  $failure_message='Booking failed due to insuffcient booking details';
$validator = Validator::make($request->all(),$this->rules,$this->messages);
  if ($validator->fails())
{
      return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
  $cart=$request->input('cart');
$public_user_data=$request->input('user_data');
  $from_date=date('Y-m-d',strtotime($request->input('from_date')));
$to_date=date('Y-m-d',strtotime($request->input('to_date')));
  $client_ip=$request->input('client_ip');
//$comments=$request->input('comments');
  $for_user_id=$request->input('for_user_id');
$operator_user_id=$request->auth->admin_id;
  $agent_credit=$request->input('agent_credit');
$chkPrice=false;
  //Check room price && Check Qty && Check Extra adult prce && Check Extra child price && Check GSt && Discounted Price
$validCart=$this->crsReservationService->checkRoomPrice($for_user_id,$cart,$from_date,$to_date,$hotel_id);
  $validCart=$validCart && $this->checkAgentCredit($for_user_id,$agent_credit);
if($validCart)
   {
$inv_data=array();
        $hotel= $this->crsReservationService->getHotelInfo($hotel_id);
$booking_data=array();
        $inv_data['check_in_out']="[".$from_date.'-'.$to_date."]";
$inv_data['room_type']  = json_encode($this->crsReservationService->prepareRoomType($cart));
        $inv_data['hotel_id']   = $hotel_id;
$inv_data['hotel_name'] = $hotel->hotel_name;
        $inv_data['user_id']    = $this->crsReservationService->saveGuestDetails($public_user_data);
$inv_data['total_amount']  = number_format((float)$this->crsReservationService->getTotal($cart), 2, '.', '');
        if($pay_type == 'both')
{
            $inv_data['paid_amount']   =  $inv_data['total_amount'] - $agent_credit;
}
        else{
$inv_data['paid_amount']   =  $inv_data['total_amount'];
        }
$inv_data['paid_amount']   = number_format((float)$inv_data['paid_amount'], 2, '.', '');
        $inv_data['discount_code'] ="";
$inv_data['paid_service_id'] =0;
        $inv_data['extra_details'] = json_encode($this->crsReservationService->getExtraDetails($cart));
$inv_data['booking_date'] = date('Y-m-d H:i:s');
        $inv_data['visitors_ip'] = $client_ip;
$inv_data['ref_no']=rand().strtotime("now");
        $inv_data['booking_status']=2;//Initially Booking status set 2 ,For the pending status
// $adjusted_amount=0;//For agent adjusted amount is 0
        $inv_data['invoice']=$this->crsReservationService->createInvoice($hotel_id,$cart,$from_date,$to_date,$inv_data['user_id'],$percentage);
$failure_message="Invoice details saving failed";
        if($invoice->fill($inv_data)->save())
{
            $cur_invoice=Invoice::where('ref_no',$inv_data['ref_no'])->first();
$booking_data=$this->crsReservationService->prepare_booking_data($cart,$cur_invoice->invoice_id,$from_date,$to_date,$inv_data['user_id'],$inv_data['booking_date'],$hotel_id);
            $crs_data['for_user_id']=$for_user_id;
$crs_data['invoice_id']=$cur_invoice->invoice_id;
            $crs_data['operator_user_id']=$operator_user_id;
if(DB::table('hotel_booking')->insert($booking_data))
            {
if($crs_reservation->fill($crs_data)->save())
                {
if($pay_type=="now")
                    {
if($this->crsReservationService->preinvoiceMail($cur_invoice->invoice_id))
                        {
$res=array("status"=>1,"message"=>"Invoice details saved successfully","pay_type"=>$pay_type,"invoice_id"=> base64_encode($cur_invoice->invoice_id));
                            return response()->json($res);
}
                        else
{
                            $res=array('status'=>-1,"message"=>$failure_message);
$res['errors'][] = "Internal server error";
                            return response()->json($res); 
}
                    }
elseif($pay_type=='pay_later')
                    {   
//Success booking
                        $invoice_details=Invoice::where('invoice_id',$cur_invoice->invoice_id)->first();
$invoice_details->booking_status = 1;
                        if($invoice_details->save())
{
                            $hotel_booking_model=Booking::where('invoice_id',$cur_invoice->invoice_id)->get();
$crs_reservation_model=CrsReservation::where('invoice_id',$cur_invoice->invoice_id)->first();
                            $crs_reservation_model->pay_status=1;
$crs_reservation_model->credit_status='credit';
                            $crs_reservation_model->save();
foreach($hotel_booking_model as $hbm) {
                                $hbm->booking_status = 1;
$hbm->save();
                                $this->crsReservationService->updateInventory($hbm,'consume');
}
                            $failure_message="Mail Not sent";
if($this->crsReservationService->invoiceMail($cur_invoice->invoice_id))
                            {
$res=array("status"=>1,"message"=>"Booking confirmation done successfully","pay_type"=>$pay_type);
                                return response()->json($res);
}
                            else
{
                                $res=array("status"=>0,"message"=>"Booking failed","pay_type"=>$pay_type);
return response()->json($res);
                            }
}
                       
}
                    else if($pay_type=='both')
{
                        $invoice_details=Invoice::where('invoice_id',$cur_invoice->invoice_id)->first();
$invoice_details->booking_status = 1;
                        if($invoice_details->save())
{
                            $hotel_booking_model=Booking::where('invoice_id',$cur_invoice->invoice_id)->get();
$crs_reservation_model=CrsReservation::where('invoice_id',$cur_invoice->invoice_id)->first();
                            $crs_reservation_model->pay_status=1;
$crs_reservation_model->credit_status='credit';
                            $crs_reservation_model->save();
foreach($hotel_booking_model as $hbm) {
                                $hbm->booking_status = 1;
$hbm->save();
                                $this->crsReservationService->updateInventory($hbm,'consume');
}
                        }
$this->crsReservationService->invoiceMail($cur_invoice->invoice_id);
                        if($this->crsReservationService->preinvoiceMail($cur_invoice->invoice_id))
{
                            $res=array("status"=>1,"message"=>"Invoice details saved successfully","pay_type"=>$pay_type,"invoice_id"=> base64_encode($cur_invoice->invoice_id));
return response()->json($res);
                        }
else
                        {
$res=array('status'=>-1,"message"=>$failure_message);
                            $res['errors'][] = "Internal server error";
return response()->json($res); 
                        }
}
                }
else
                {
$res=array('status'=>-1,"message"=>$failure_message);
                    $res['errors'][] = "Internal server error";
return response()->json($res); 
                }
}
            else
{
                $res=array('status'=>-1,"message"=>$failure_message);
$res['errors'][] = "Internal server error";
                return response()->json($res);
}
        }
else
        {
$res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
return response()->json($res);
        }
}
   else
{
    $res=array('status'=>-1,"message"=>"Booking failed due to data tempering,Please try again later");
return response()->json($res);  
   }
}
/**
* Check agent credit remaining
 * 
*/
    public function checkAgentCredit($agent_id,$credit_amount)
{
        $agentCredit=0;
$agentCredit=$this->calculateAgentCredit($agent_id);
        if(round($agentCredit, 2)==round($credit_amount, 2))
{
            return true;
}
        else
{
            return false;
}
    }
/**
 * Calculate Agent Credit
* 
 */
public function calculateAgentCredit($agent_id)
    {
$agentCredit=0;
        $agentCreditModel=new AgentCredit();
$agentCredit=$agentCreditModel->where('agent_id',$agent_id)->first();
        $agentCredit=(int)$agentCredit->credit_limit;
$credit_amount=CrsReservation::
        join('invoice_table', 'crs_reservation.invoice_id', '=', 'invoice_table.invoice_id')
->where('credit_status','credit')
        ->where('pay_status', '=', 1)
->where('for_user_id', '=',$agent_id)
        ->sum('invoice_table.paid_amount');
if($credit_amount>0)
        { 
$agentCredit=$agentCredit-$credit_amount;
        }
return $agentCredit;
    }
/**
 * To calcalulate and get the agent credit limit 
*/
    public function getAgentCredit($agent_id,Request $request)
{
        $agentCredit=0;
$agentCredit=$this->calculateAgentCredit($agent_id);
        $res=array('status'=>1,"message"=>'Agent credit fetch successfull!','agent_credit'=>$agentCredit);
return response()->json($res);
    }
}
