<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Packages;//class name from model
use App\ImageTable;//class name from model
use App\Inventory;
use App\Invoice;
use App\User;
Use App\Booking;
use App\TaxDetails;
use App\HotelInformation;
use App\CompanyDetails;
use App\MasterRoomType;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Http\Controllers\Extranetv4\CurrencyController;
use App\Http\Controllers\Extranetv4\IpAddressService;
use App\Http\Controllers\Extranetv4\BookingEngineController;
use App\OnlineTransactionDetail;
use DB;
use App\Http\Controllers\Controller;
//create a new class PackagesController
class PackageBookingController extends Controller
{
    protected $invService, $ipService;
    protected $updateInventoryService;
    protected $curency;
    protected $booking_engine;
    protected $gst_arr=array();
    public function __construct(InventoryService $invService,UpdateInventoryService $updateInventoryService,CurrencyController $curency,IpAddressService $ipService,
    BookingEngineController $booking_engine)
    {
       $this->invService = $invService;
       $this->updateInventoryService = $updateInventoryService;
       $this->curency=$curency;
       $this->ipService=$ipService;
       $this->booking_engine=$booking_engine;
    }
    private $pkg_rules=array(
        'hotel_id'=>'required',
        'from_date'=>'required',
        //'to_date'=>'required'
    );
    private $pkg_messages=[
        'hotel_id.required'=>'Hotel id should be required',
        'from_date.required'=>'From date should be required',
      //  'to_date.required'=>'To date should be required',
    ];
    private $rules=array(
        'hotel_id'=>'required'
    );
    private $messages=[
        'hotel_id.required'=>'Hotel id is be required',
    ];
    private $booking_rules = array(
        'hotel_id' => 'required | numeric',
        'from_date' => 'required ',
        'to_date' => 'required',
        'cart' => 'required'
    );
      //Custom Error Messages
      private $booking_messages = [
        'hotel_id.required' => 'The hotel id field is required.',
        'hotel_id.numeric' => 'The hotel id must be numeric.',
        'from_date.required' => 'Check in date is required.',
        'to_date.required' => 'Check out is required.',
        'cart.required' => 'cart is required.'
        ];

    public function getAccess(string $company_url,Request $request)
    {
        $conditions=array('company_url'=>$company_url);
        $info=CompanyDetails::select('api_key','hotels_table.hotel_id','company_table.company_id')
        ->join('hotels_table','company_table.company_id','hotels_table.company_id')
        ->where($conditions)->first();
        $info['comp_hash']= openssl_digest($info['company_id'], 'sha512');
        if($info['api_key'])
        {
            $res=array('status'=>1,'message'=>"Company auth successfull",'data'=>$info);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>0,'message'=>"Invalid company url");
            return response()->json($res);
        }
    }
    public function packageBookingDetails(int $hotel_id,string $currency_name,Request $request)
    {
        $bookingdetails=new Packages();
        $failure_message="date should be providated";
        $validator=Validator::make(array('hotel_id'=>$hotel_id),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        //$data=$request->all();
        $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;//Get base currency
        $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')->select('package_table.*','image_table.*','room_type_table.total_rooms','room_type_table.room_type_id')->where('date_to','>=',date('Y-m-d'))
        ->where('package_table.hotel_id',$hotel_id)
        ->where('package_table.is_trash',0)->get();
        $converted_package_inventory=array();//Initialize the empty array
        foreach($package_inventory as $key => $roomtype)
        {
            $roomtype['min_inv']=0;
            if($baseCurrency == $currency_name){
                $converted_package_inventory[$key]["amount"]=$roomtype["amount"];
            }else{
                $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($roomtype["amount"],$currency_name,$baseCurrency),2);
            }
            if($baseCurrency == $currency_name){
                $converted_package_inventory[$key]["discounted_amount"]=$roomtype["discounted_amount"];
            }else{
                $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($roomtype["discounted_amount"],$currency_name,$baseCurrency),2);
            }
            if($baseCurrency == $currency_name){
                $converted_package_inventory[$key]["extra_child_price"]=$roomtype["extra_child_price"];
            }else{
                $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($roomtype["extra_child_price"],$currency_name,$baseCurrency),2);
            }
            if($baseCurrency == $currency_name){
                $converted_package_inventory[$key]["extra_child_price"]=$roomtype["extra_child_price"];
            }else{
                $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($roomtype["extra_person_price"],$currency_name,$baseCurrency),2);
            }
            $converted_package_inventory[$key]["adults"]=$roomtype["adults"];
            $converted_package_inventory[$key]['max_child']=$roomtype["max_child"];
            $converted_package_inventory[$key]['extra_person']=$roomtype["extra_person"];
            $converted_package_inventory[$key]['extra_child']=$roomtype["extra_child"];
            $converted_package_inventory[$key]['package_id']=$roomtype["package_id"];
            $converted_package_inventory[$key]['package_name']=$roomtype["package_name"];
            $converted_package_inventory[$key]['room_type_id']=$roomtype["room_type_id"];
            $converted_package_inventory[$key]['total_rooms']=$roomtype["total_rooms"];
            $converted_package_inventory[$key]['nights']=$roomtype["nights"];
            $converted_package_inventory[$key]['image_name']=$roomtype["image_name"];
            $converted_package_inventory[$key]['image_id']=$roomtype["image_id"];
            $converted_package_inventory[$key]['package_description']=$roomtype["package_description"];
            $converted_package_inventory[$key]['package_image']=$roomtype["package_image"];
            $converted_package_inventory[$key]['date_from']=$roomtype["date_from"];
            $converted_package_inventory[$key]['date_to']=$roomtype["date_to"];
            $info=$this->invService->getInventeryByRoomTYpe($roomtype['room_type_id'],$roomtype['date_from'],$roomtype['date_to'], 0);
            $roomtype['min_inv']=$info[0]['no_of_rooms'];
            $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
            $package_inventory[$key]["inv"]=$info;
            $converted_package_inventory[$key]["inv"]=$info;
        }
        if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
        {
            $res=array("status"=>1,"message"=>"sucessfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
            return response()->json($res);
        }
        else{
            $res=array("status"=>0,"message"=>"fail to retrive details");
            return response()->json($res);
        }
    }
    public function getPackageDetails(int $package_id,Request $request)
    {
        // $packagedetails=Packages::join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')->join('image_table','room_type_table.image','=','image_table.image_id')->where('package_table.package_id',$package_id)->first();
        // if($packagedetails)
        // {
        //     $res=array("status"=>1,"message"=>"sucessfully retrive details","data"=>$packagedetails);
        //     return response()->json($res);
        // }
        // else{
        //     $res=array("status"=>0,"message"=>"fail to retrive details");
        //     return response()->json($res);
        // }
        $packageDetails=Packages::join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')->where('package_table.package_id',$package_id)->first();
        if($packageDetails)
        {
            $packageDetails['package_image']=explode(',',$packageDetails['package_image']);//Converting string to array
            $packageDetails['package_image']=$this->getImages($packageDetails['package_image']);
            $res=array("status"=>1,"message"=>"sucessfully retrive details","data"=>$packageDetails);
            return response()->json($res);
        }
        else{
            $res=array("status"=>0,"message"=>"fail to retrive details");
            return response()->json($res);
        }
    }
    public function packageBookings(string $api_key,Request $request)
    {
        $hotel_id=$request->input('hotel_id');
        $status="invalid";
        $status=$this->checkAccess($api_key,$hotel_id);
        if($status=="invalid")
        {
            $res=array('status'=>1,'message'=>"Invalid company or Hotel");
            return response()->json($res);
        }
        $percentage=0;//THis shoud be set from Mail invoice
        $invoice= new Invoice();
        $booking= new Booking();
        $failure_message='Booking failed due to unsuffcient booking details';
        $validator = Validator::make($request->all(),$this->booking_rules,$this->booking_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $cart=$request->input('cart');
        $from_date=date('Y-m-d',strtotime($request->input('from_date')));
        $to_date=date('Y-m-d',strtotime($request->input('to_date')));
        $visitors_ip=$this->ipService->getIPAddress();
        $data = $request->all();
        $discount_per = isset($data['discount_percent'])?$data['discount_percent']:'null';
       
        $chkPrice=$this->checkPackagePrice($cart,$from_date,$to_date,$hotel_id);
        $validCart= $chkPrice;
        if($validCart)
        {
             $inv_data=array();
             $hotel= $this->getHotelInfo($hotel_id);
             $booking_data=array();
             $inv_data['check_in_out']="[".$from_date.'-'.$to_date."]";
             $inv_data['room_type']  = json_encode($this->preparePackageType($cart));
             $inv_data['hotel_id']   = $hotel_id;
             $inv_data['hotel_name'] = $hotel->hotel_name;
             $inv_data['user_id']    = $request->auth->user_id;
             $inv_data['total_amount']  = $this->getTotal($cart);
             $inv_data['paid_amount']   = $inv_data['total_amount'];
             $inv_data['extra_details'] = json_encode($this->getExtraDetails($cart));
             $inv_data['booking_date'] = date('Y-m-d H:i:s');
             $inv_data['visitors_ip'] = $visitors_ip;
             $inv_data['package_id'] = $cart[0]["package_id"];
             $inv_data['ref_no']=rand().strtotime("now");
             $inv_data['booking_status']=2;//Initially Booking status set 2 ,For the pending status
             $inv_data['invoice']=$this->createInvoice($hotel_id,$cart,$from_date,$to_date,$inv_data['user_id'],$percentage);
             $booking_status='Commit';
             $inv_data['discount_code'] = $discount_per;
             $inv_data['ids_re_id']=$this->booking_engine->handleIds($cart,$from_date,$to_date,$inv_data['booking_date'],$hotel_id,$inv_data['user_id'],$booking_status);


             $failure_message="Invoice details saving failed";
             if($invoice->fill($inv_data)->save())
             {
                 $cur_invoice=Invoice::where('ref_no',$inv_data['ref_no'])->first();
                 $booking_data=$this->prepare_booking_data($cart,$cur_invoice->invoice_id,$from_date,$to_date,$inv_data['user_id'],$inv_data['booking_date'],$hotel_id);

                 if(DB::table('hotel_booking')->insert($booking_data))
                 {
                     if($this->preinvoiceMail($cur_invoice->invoice_id))
                     {
                        $user_data=$this->getUserDetails($inv_data['user_id']);
                        $b_invoice_id=base64_encode($cur_invoice->invoice_id);
                        $invoice_hashData=$cur_invoice->invoice_id.'|'.$cur_invoice->total_amount.'|'.$cur_invoice->paid_amount.'|'.$user_data->email_id.'|'.$user_data->mobile.'|'.$b_invoice_id;
                        $invoice_secureHash= hash('sha512', $invoice_hashData);
                        $res=array("status"=>1,"message"=>"Invoice details saved successfully","invoice_id"=>$cur_invoice->invoice_id,'invoice_secureHash'=>$invoice_secureHash);
                        return response()->json($res);
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
    public function checkAccess($api_key,$hotel_id)
    {
        //$hotel=new HotelInformation();
        $cond=array('api_key'=>$api_key);
        $comp_info=DB::connection('bookingjini_kernel')->table('company_table')->select('company_id')
        ->where($cond)->first();
        if(!$comp_info->company_id)
        {
        return "Invalid";
        }
        $conditions=array('hotel_id'=>$hotel_id,'company_id'=>$comp_info->company_id);
        $info=DB::connection('bookingjini_kernel')->table('hotels_table')->select('hotel_name')->where($conditions)->first();
        if($info)
        {
            return 'valid';
        }
        else
        {
            return "invalid";
        }
    }
    public function preparePackageType($cart)
    {
        $package_types=array();
        foreach($cart as $cartItem)
        {
            $package_type=$cartItem['package_type'];
            $packages=$cartItem['packages'];
            array_push($package_types,sizeof($packages).' '.$package_type);
        }
        return $package_types;
    }
    public function checkPackagePrice($cart,$from_date,$to_date,$hotel_id)
    {
        $qty=0;
        $chkQty=false;//Initially chkQty set to false
        $chkRmRate=array();
        foreach($cart as $cartItem)
        {
            $room_type_id=$cartItem['room_type_id'];
            $packages=$cartItem['packages'];
            $package_id=$cartItem['package_id'];
            $package_price=$cartItem['price'];
            $gst_price=$cartItem['tax'][0]['gst_price'];
            $other_tax_arr=$cartItem['tax'][0]['other_tax'];
            $discount_amount = isset($cartItem['discounted_price'])?$cartItem['discounted_price']:0;
            $package_price= $package_price+$discount_amount;
            $qty=sizeof($packages);//No of rooms is size of the rooms array
            $chkQty=$this->CheckQty($room_type_id,$qty,$from_date,$to_date);
            array_push($chkRmRate,$this->CheckPackageRate($room_type_id,$packages,$from_date,$to_date,$package_price,$package_id,$gst_price,$other_tax_arr,$hotel_id,$discount_amount));
        }
        $rmStatus=true;
        foreach($chkRmRate as $chkRm)
        {
        if($chkRm!=1)
        {
            $rmStatus=false;
        }
        }
        ///Check all the status
        if($chkQty && $rmStatus)
        {
            return true;
        }
        else{
            return false;
        }
    }
    public function CheckQty($room_type_id,$qty,$from_date,$to_date)
    {
        $min_inv=0;
        $data=$this->invService->getInventeryByRoomTYpe($room_type_id,$from_date, $to_date, 0);
        foreach($data as $inv_room)
        {
            if($min_inv==0)
            {
                $min_inv=$inv_room['no_of_rooms'];
            }
            if($inv_room['no_of_rooms']<=$min_inv)
            {
                $min_inv  = $inv_room['no_of_rooms'];
            }
        }

        //Check qty
        if($qty<=$min_inv)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    public function CheckPackageRate($room_type_id,$packages,$from_date,$to_date,$curr_room_price,$package_id,$curr_gst_price,$cur_other_tax,$hotel_id,$discount_amount)
    {
        $date1=date_create($from_date);
        $date2=date_create($to_date);
        $date3=date_create(date('Y-m-d'));
        $diff=date_diff($date1,$date2);
        $diff=$diff->format("%a");//Diffrence betwen checkin and checkout date
        $diff1=date_diff($date3,$date1);///Diffrence betwen booking date that is today and checkin date
        $diff1=$diff1->format("%a");
        $room_price=0;
        $extra_adult_price=0;
        $extra_child_price=0;
        $extra_adult_ok=false;
        $extra_child_ok=false;
        $multiple_occ=array();
        $conditions=array('room_type_id'=>$room_type_id,'package_id'=>$package_id);
        $package_types=Packages::where($conditions)->first();
        $tot_extra_adult_price=0;
        $tot_extra_child_price=0;
        $bar_price=0;
        foreach($packages as $package)
        {
            $curr_extra_adult_price=$package_types['extra_person_price'];
            $curr_extra_child_price=$package_types['extra_child_price'];
            $extra_adult_price=$package['extra_adult_price'];
            $extra_child_price=$package['extra_child_price'];
            $selected_adult=$package['selected_adult'];
            $selected_child=$package['selected_child'];
            $bar_price+=$package_types['discounted_amount'];
            //Check extra adult price
                $total_extra_child_price=0;
                $total_extra_adult_price=0;
                $max_adult= $package_types->adults;
                $max_child= $package_types->max_child;
                if($selected_adult>$max_adult)
                {
                    $no_of_extra_adult=$selected_adult-$max_adult;
                    $total_extra_adult_price=$no_of_extra_adult * $curr_extra_adult_price;
                    if($extra_adult_price==$total_extra_adult_price)
                        {
                            $extra_adult_ok=true;
                        }
                }
                else if($selected_adult==$max_adult)
                {
                    $extra_adult_ok=true;
                }
                else if($selected_adult < $max_adult && $selected_adult > 0)
                {
                    $extra_adult_ok=true;
                }

            //Check extra child price
                if($selected_child>$max_child)
                {
                    $no_of_extra_child=$selected_child-$max_child;
                    $total_extra_child_price=$no_of_extra_child * $curr_extra_child_price;
                    if($extra_child_price==$total_extra_child_price)
                    {
                        $extra_child_ok=true;
                    }

                }
                else if($selected_child==$max_child)
                {
                    $extra_child_ok=true;
                }
                else if($selected_child < $max_child && $selected_child > 0)
                {
                    $extra_child_ok=true;
                }
             $tot_extra_adult_price+= $total_extra_adult_price;
             $tot_extra_child_price+= $total_extra_child_price;

        }
        $chk_gst_ok=false;
        
        //TO check the GST And Other taxes
        $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
        $price=$curr_room_price+$tot_extra_child_price+$tot_extra_adult_price-$discount_amount;
        if($curr_gst_price!=0 && $baseCurrency=='INR'){
            $gst_price=$this->getGstPrice($diff,sizeof($packages),$room_type_id,$price,$package_id);//TO get the GSt price
            // dd($gst_price,$curr_gst_price);
            if(ceil($gst_price)==ceil($curr_gst_price))
            {
                $chk_gst_ok=true;
            }
        }else{
            $chk_gst_ok=true;//Other than INR tax calculated in Other Tax (Dynamic tax)
        }
        //Other tax module check
        if($curr_gst_price == 0 && $baseCurrency!='INR'){
            $orig_tax_details=$this->getorigTaxDetails($hotel_id);
            $chk_other_tax_ok=true;
            if(sizeof($orig_tax_details) == sizeof($cur_other_tax)){
                foreach($orig_tax_details as $key=>$orig_tax){
                if($orig_tax['tax_name']==$cur_other_tax[$key]['tax_name'] &&
                round(($orig_tax['tax_percent'] * $price /100),2)==round($cur_other_tax[$key]['tax_price'],2)){
                    $chk_other_tax_ok=$chk_other_tax_ok && true;
                }else{
                    $chk_other_tax_ok = $chk_other_tax_ok && false;
                }
                }
            }
        }else{
            $chk_other_tax_ok=true;
        }
        ///Check all the conditions
        if($bar_price == $curr_room_price &&  $extra_child_ok && $extra_adult_ok && $chk_gst_ok && $chk_other_tax_ok)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    //GET dynamic tax moudle
    public function getorigTaxDetails($hotel_id){
        $conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
        $finDetails=TaxDetails::where($conditions)->select('tax_name','tax_percent')->first();
        $tax_name_arr=explode(',', $finDetails->tax_name);
        $tax_percent_arr=explode(',',$finDetails->tax_percent);
        $finace_details=array();
        foreach($tax_name_arr as $key=>$tax){
            array_push($finace_details,array('tax_name' => $tax_name_arr[$key],'tax_percent'=> $tax_percent_arr[$key]));
        }
        return $finace_details;
    }
    public function getGstPrice($no_of_nights,$no_of_rooms,$room_type_id,$price,$package_id)
    {
        $chek_price=$price/$no_of_rooms/$no_of_nights;
        $gstPercent=$this->checkGSTPercent($room_type_id,$chek_price,$package_id);
        $gstPrice=(($price)*$gstPercent)/100;
        // dd($gstPrice);
        return $gstPrice;
    }
public function preinvoiceMail($id)
{
    $invoice        = $this->successInvoice($id);
    $invoice=$invoice[0];
    $booking_id     = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
    $u=$this->getUserDetails($invoice->user_id);

    $subject        = "Booking From ".$invoice->hotel_name;
    $body           = $invoice->invoice;
    $body           = str_replace("#####", $booking_id, $body);
    $body           = str_replace("BOOKING CONFIRMATION", "BOOKING CONFIRMATION(UNPAID)", $body);
    $to_email='reservations@bookingjini.com';
    //$to_email='satya.godti@bookingjini.com';
    if($this->sendMail($to_email,$body, $subject,"",$invoice->hotel_name))
    {
        return true;
    }

}
public function sendMail($email,$template, $subject,$hotel_email,$hotel_name)
{
    $mail_array=['trilochan.parida@5elements.co.in', 'gourab.nandy@5elements.co.in','reservations@bookingjini.com'];
    $data = array('email' =>$email,'subject'=>$subject,'mail_array'=>$mail_array);
    $data['template']=$template;
    $data['hotel_name']=$hotel_name;
    if($hotel_email)
    {
        $data['hotel_email']=$hotel_email;
    }
    else{
        $data['hotel_email']="";
    }
    Mail::send([],[],function ($message) use ($data)
    {
        if($data['hotel_email']!="")
        {
            $message->to($data['email'])
            ->cc($data['hotel_email'])
            ->bcc($data['mail_array'])
            ->from( env("MAIL_FROM"), $data['hotel_name'])
            ->subject( $data['subject'])
            ->setBody($data['template'], 'text/html');
        }
        else
        {
            $message->to($data['email'])
            ->from( env("MAIL_FROM"), $data['hotel_name'])
            ->cc('gourab.nandy@bookingjini.com')
            ->subject( $data['subject'])
            ->setBody($data['template'], 'text/html');
        }
    });
    if(Mail::failures())
    {
        return false;
    }
    return true;
}
//*************Update the GST % of the Individual room*****************/
public function checkGSTPercent($room_type_id,$price,$package_id)
    {
        if($price<1000)
        {
            return 0;
        }
        else if($price>=1000 && $price<7500)
        {
            return 12;
        }
        else if($price>=7500)
        {
            return 18;
        }

    }
    public function getHotelInfo($hotel_id)
    {
        $hotel=DB::connection('bookingjini_kernel')->table('hotels_table')->select('*')->where('hotel_id',$hotel_id)->first();
        return $hotel;
    }
    public function getUserDetails($user_id)
    {
        $user=DB::connection('bookingjini_kernel')->table('user_table')->select('*')->where('user_id',$user_id)->first();
        return $user;
    }
    public function getTotal($cart)
    {
        $total_price=0;
        foreach($cart as $cartItem)
        {
            $total_extra_price=0;
            $price=0;
            foreach($cartItem['packages'] as $cart_room)
            {
                $total_extra_price+=$cart_room['extra_child_price']+$cart_room['extra_adult_price'];
                $price+=$cart_room['bar_price'];
                //$discount+=$cart_room['discounted_amount'];
            }
        $total_price+=$price+$cartItem['tax'][0]['gst_price']+$total_extra_price;
        foreach($cartItem['tax'][0]['other_tax'] as $other_tax){
            $total_price+=$other_tax['tax_price'];
           }
        }
        return $total_price;
    }
    public function getExtraDetails($cart)
    {
        $extra_details=array();
        foreach($cart as $cartItem)
        {
            foreach($cartItem['packages'] as $room)
            {
                array_push($extra_details,array( $cartItem['room_type_id']=>array($room['selected_adult'],$room['selected_child'])));
            }
        }
        return $extra_details;
    }
    public function prepare_booking_data($cart,int $invoice_id,$from_date,$to_date,$user_id,$booking_date,$hotel_id)
    {
        $booking_data=array();
        $booking_data_arr=array();
        foreach($cart as $cartItem)
        {
            $booking_data['room_type_id']=$cartItem['room_type_id'];
            $booking_data['rooms']=sizeof($cartItem['packages']);
            $booking_data['check_in']=$from_date;
            $booking_data['check_out']=$to_date;
            $booking_data['booking_status']=2;//Intially Un Paid
            $booking_data['user_id']=$user_id;
            $booking_data['booking_date']=$booking_date;
            $booking_data['invoice_id']=$invoice_id;
            $booking_data['hotel_id']=$hotel_id;
            array_push($booking_data_arr,$booking_data);
        }

        return $booking_data_arr;
    }
    public function createInvoice($hotel_id,$cart,$check_in,$check_out,$user_id,$percentage)
    {
        $booking_id="#####";
        $booking_date=date('Y-m-d');
        $booking_date=date("jS M, Y", strtotime($booking_date));
        $hotel_details=$this->getHotelInfo($hotel_id);
        $u=$this->getUserDetails($user_id);
        $dsp_check_in=date("jS M, Y", strtotime($check_in));
        $dsp_check_out=date("jS M, Y", strtotime($check_out));
        $total_adult=0;
        $total_child=0;
        $total_cost=0;
        $all_room_type_name="";
        $paid_service_details="";
        $all_rows="";
        //$total_discount_price=0;
        $total_price_after_discount=0;
        $total_tax=0;
        $display_discount=0.00;
        $other_tax_arr=array();
        //Get base currency and currency hex code
        $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
        $currency_code=$this->getBaseCurrency($hotel_id)->hex_code;
        foreach($cart as $cartItem)
        {
            $i=1;
            $total_price=0;
            $all_room_type_name.=','.$cartItem['package_type'];
            $every_room_type="";
            $all_rows.= '<tr><td rowspan="'.sizeof($cartItem['packages']).'" colspan="2">'.$cartItem['package_type'].')</td>';
            foreach($cartItem['packages'] as $packages)
            {
                $ind_total_price=0;
                $total_adult+=$packages['selected_adult'];
                $total_child+=$packages['selected_child'];
                $ind_total_price=$packages['bar_price']+$packages['extra_adult_price']+$packages['extra_child_price'];

                if($i==1)
                {
                    $all_rows= $all_rows.'<td  align="center">'.$i.'</td>
                    <td  align="center">'.round($packages['extra_adult_price']).'</td>
                    <td  align="center">'.round($packages['extra_child_price']).'</td>
                    <td  align="center">'.$currency_code.$ind_total_price.'</td>
                    </tr>';
                }
                else{
                    $all_rows= $all_rows.'<tr><td  align="center">'.$i.'</td>
                    <td  align="center">'.round($packages['extra_adult_price']).'</td>
                    <td  align="center">'.round($packages['extra_child_price']).'</td>
                    <td  align="center">'.$currency_code.$ind_total_price.'</td>
                    </tr>';
                }

                $i++;
                $total_price+=$ind_total_price;
            }
            $total_cost+=$total_price;
            if($cartItem['tax'][0]['gst_price']!=0){
                $total_tax  += $cartItem['tax'][0]['gst_price'];
            }
            else
            {
                foreach($cartItem['tax'][0]['other_tax'] as $key => $other_tax){
                    $total_tax+=$other_tax['tax_price'];
                    $other_tax_arr[$key]['tax_name']=$other_tax['tax_name'];
                    if(!isset($other_tax_arr[$key]['tax_price'])){
                        $other_tax_arr[$key]['tax_price']=0;
                        $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                    }else{
                        $other_tax_arr[$key]['tax_price']+=$other_tax['tax_price'];
                    }
                }
            }
            $total_tax = number_format((float)$total_tax, 2, '.', '');
            $every_room_type=$every_room_type.'
            <tr>
                <td colspan="5" align="right" style="font-weight: bold;">Total &nbsp;</td>
                <td align="center" style="font-weight: bold;">'.$currency_code.$total_price.'</td>
            </tr>
            ';

            $all_rows=$all_rows.$every_room_type;
        }
        $gst_tax_details="";
        if($baseCurrency=='INR'){
           $gst_tax_details='<tr>
           <td colspan="5" align="right"> GST &nbsp;&nbsp;&nbsp;</td>
           <td align="center">'.$currency_code.$total_tax.'</td>
           </tr>';
        }
        $other_tax_details="";
        if(sizeof($other_tax_arr)>0)
        {

           foreach($other_tax_arr as $other_tax)
           {
              $other_tax_details=$other_tax_details.'<tr>
              <td colspan="6" style="text-align:right;">'.$other_tax['tax_name'].'&nbsp;&nbsp;</td>
              <td style="text-align:center;">'.$currency_code.$other_tax['tax_price'].'</td>
              <tr>';
           }
        }
        $total_amt = $total_cost;
        $total_amt = number_format((float)$total_amt, 2, '.', '');
        $total=$total_amt+$total_tax;
        $total = number_format((float)$total, 2, '.', '');

        if($percentage==0 || $percentage==100)
        {
            $paid_amount=$total;
            $paid_amount = number_format((float)$paid_amount, 2, '.', '');
        }
        else
        {
            $paid_amount=$total*$percentage/100;
            $paid_amount = number_format((float)$paid_amount, 2, '.', '');
        }


        $due_amount=$total-$paid_amount;
        $due_amount = number_format((float)$due_amount, 2, '.', '');
        $body='<html>
        <head>
        <style>
            html{
                font-family: Arial, Helvetica, sans-serif;
            }
            table, td {
                height: 26px;
            }
            table, th {
                height: 35px;
            }
            p{
                color: #000000;
            }
        </style>
        </head>
        <body style="color:#000;">
        <div style="margin: 0px auto;">
        <table width="100%" align="center">
        <tr>
        <td style="border: 1px #0; padding: 4%; border-style: double;">
            <table width="100%" border="0">
                <tr>
                    <th colspan="2" valign="middle" style="font-size: 23px;"><u>BOOKING CONFIRMATION</u></th>
                </tr>
                <tr>
                <td><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td>
                        <div>
                            <div style="font-weight: bold; font-size: 22px; color:#fff; background-color: #1d99b5; padding: 5px;">'.$hotel_details->hotel_name.'</div>
                        </div>
                    </td>
                    <td style="font-size: 16px;font-weight: bold;" align="right">BOOKING ID : '.$booking_id.'</td>
                </tr>
                <tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
                <tr>
                    <td colspan="2"><b>Dear '.$u->first_name.' '.$u->last_name.',</b></td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:17px;"><b>Thank  you for choosing to book with '.$hotel_details->hotel_name.'. Your reservation has been accepted, your booking details can be found below. We look forward to see you soon.</b></td>
                </tr>
                <tr>
                    <td colspan="2"><b style="color: #ffffff;">*</b></td>
                </tr>
        </table>

            <table width="100%" border="1" style="border-collapse: collapse;">
                <th colspan="2" bgcolor="#ec8849">BOOKING DETAILS</th>
                <tr>
                    <td >PROPERTY & PLACE</td>
                    <td>'.$hotel_details->hotel_name.'</td>
                </tr>
                <tr>
                    <td width="45%">NAME OF GUEST</td>
                    <td>'.$u->first_name.' '.$u->last_name.'</td>
                </tr>
                <tr>
                    <td>GUEST PHONE NUMBER</td>
                    <td>'.$u->mobile.'</td>
                </tr>
                <tr>
            <td>EMAIL ID</td>
            <td>'.$u->email_id.'</td>
        </tr>
                <tr>
            <td>BOOKING DATE</td>
            <td>'.$booking_date.'</td>
        </tr>
                <tr>
            <td>CHECK IN DATE</td>
            <td>'.$dsp_check_in.'</td>
        </tr>
                <tr>
            <td>CHECK OUT DATE</td>
            <td>'.$dsp_check_out.'</td>
        </tr>
                <tr>
            <td>CHECK IN TIME</td>
            <td>'.$hotel_details->check_in.'</td>
        </tr>
                <tr>
            <td>CHECK OUT TIME</td>
            <td>'.$hotel_details->check_out.'</td>
        </tr>
                    <tr>
            <td>TOTAL ADULT</td>
            <td>'.$total_adult.'</td>
        </tr>
                    <tr>
            <td>TOTAL CHILD</td>
            <td>'.$total_child.'</td>
        </tr>
                <tr>
            <td>PACKAGE NAME</td>
            <td>'.substr($all_room_type_name, 1).'</td>
        </tr>

        </table>

            <table width="100%" border="1" style="border-collapse: collapse;">
                <tr>
        <th colspan="10" valign="middle" height="" style="font-size: 20px;">TARIFF APPLICABLE</th>
        </tr>
                <tr>
            <th colspan="2" bgcolor="#ec8849" align="center">Package Type</th>
            <th bgcolor="#ec8849" align="center">Rooms</th>
            <th bgcolor="#ec8849" align="center">Extra Adult Price</th>
            <th bgcolor="#ec8849" align="center">Extra Child Price</th>
            <th bgcolor="#ec8849" align="center">Total Price</th>
        </tr>
                '.$all_rows.'
        <tr>
            <td colspan="6"><p style="color: #ffffff;">*</p></td>
        </tr>
                <tr>
            <td colspan="5" align="right">Total Room Rate&nbsp;&nbsp;</td>
            <td align="center">'.$currency_code.$total_cost.'</td>
        </tr>
        '.$gst_tax_details.'
        '.$other_tax_details.'
        <tr>
            <td colspan="5" align="right"><p>Total Amount&nbsp;&nbsp;</p></td>
            <td align="center">'.$currency_code.$total.'</td>
        </tr>
        <tr>
            <td colspan="5" align="right">Total Paid Amount&nbsp;&nbsp;</td>
            <td align="center">'.$currency_code.'<span id="pd_amt">'.$paid_amount.'</span></td>
        </tr>
        <tr>
         <td colspan="6"><p style="color: #ffffff;">* </p></td>
     </tr>
        </table>

            <table width="100%" border="0">
                <tr>
        <th colspan="2" style="font-size: 21px; color: #ffffff;"><u>*</u></th>
        </tr>
                <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;">Awaiting For Your Welcome  !!!</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>

                <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">Regards,<br />
                Reservation Team<br />
                '.$hotel_details->hotel_name.'<br />
                '.$hotel_details->hotel_address.'<br />
                Mob   : '.$hotel_details->mobile.'<br />
                Email : '.$hotel_details->email_id.'</span>
            </td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Note</u> :</span> Taxes applicable may change subject to govt policy at the time of check in.</td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Terms & Conditions</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color:#f00"><li>On Any 100% Cancellation Policy there will be a 3% mandatory deduction from the booking amount due to payment gateway charges.</li></span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">'.$hotel_details->terms_and_cond.'</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold; font-size: 20px;"><u>Cancellation Policy</u> :</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #fff; font-weight: bold; font-size: 20px;">*</span></td>
        </tr>
        <tr>
            <td colspan="2"><span style="color: #000; font-weight: bold;">'.$hotel_details->hotel_policy.'</span></td>
        </tr>
        </table>
        </td>
        </tr>
        </table>
        </div>
        </body>
        </html>';

        return $body;
    }
    public function invoiceDetails(int $invoice_id,Request $request)
    {
        $invoice = $this->successInvoice($invoice_id);
        $invoice=$invoice[0];
        if(!$invoice)
        {
        $res=array('status'=>0,"message"=>"Invoice details not found");
        return response()->json($res);
        }
        $booking_id = date("dmy", strtotime($invoice->booking_date)).str_pad($invoice->invoice_id, 4, '0', STR_PAD_LEFT);
        $body = $invoice->invoice;
        $body = str_replace("#####", $booking_id, $body);
        if($body)
        {
        $res=array('status'=>1,"message"=>"Invoice feteched sucesssfully!",'data'=>$body);
        return response()->json($res);
        }
        else
        {
        $res=array('status'=>0,"message"=>"Invoice details not found");
        return response()->json($res);
        }
    }
    public function successInvoice($id)
    {

        $query      = "Select DISTINCT(a.invoice_id), b.user_id, b.room_type_id, a.booking_date, a.invoice, a.hotel_name, a.hotel_id, a.room_type, a.check_in_out, a.total_amount, a.paid_amount, c.hotel_address, c.mobile, c.email_id, c.terms_and_cond from invoice_table a, hotel_booking b, kernel.hotels_table c where a.invoice_id=b.invoice_id AND a.hotel_id=c.hotel_id AND a.invoice_id=$id";
        $result    = DB::select($query);
        return $result;
    }
    /**
     * Get base currency of hotel/Company
     * @param hotel_id(Hotel Id)
     * @auther Godti Vinod
     */
    public function getBaseCurrency($hotel_id){
        $company=DB::connection('bookingjini_kernel')->table('company_table')->join('hotels_table','company_table.company_id','hotels_table.company_id')
                ->where('hotels_table.hotel_id',$hotel_id)
                ->select('currency','hex_code')
                ->first();
        if($company){
            return $company;
        }
    }

    // public function getSpecificPackageDetails(int $hotel_id,$from_date,$currency_name,Request $request){
    //     $failure_message="Please provide hotel id and dates";
    //     $validator=Validator::make(array('hotel_id'=>$hotel_id,'from_date'=>$from_date),$this->pkg_rules,$this->pkg_messages);
    //     if ($validator->fails())
    //     {
    //         return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
    //     }
    //     $from_date = date('Y-m-d',strtotime($from_date));
    //     // $to_date = date('Y-m-d',strtotime($to_date));
    //     if($hotel_id){
    //         $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;//Get base currency
    //         $package_inventory = Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table as db2','package_table.room_type_id','=','db2.room_type_id')->select('package_table.*','image_table.*','db2.total_rooms','db2.room_type_id')
    //         ->where('package_table.hotel_id',$hotel_id)
    //         ->where('package_table.date_from','<=',$from_date)
    //         ->where('package_table.date_to','>=',$from_date)
    //         ->where('package_table.is_trash',0)
    //         ->get();
    //         $converted_package_inventory=array();//Initialize the empty array
    //         foreach($package_inventory as $key => $package)
    //         {
    //             $package['min_inv']=0;
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["amount"]=$package["amount"];
    //             }else{
    //                 $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($package["amount"],$currency_name,$baseCurrency),2);
                   
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["discounted_amount"]=$package["discounted_amount"];
    //             }else{
    //                 $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($package["discounted_amount"],$currency_name,$baseCurrency),2);
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
    //             }else{
    //                 $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($package["extra_child_price"],$currency_name,$baseCurrency),2);
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
    //             }else{
    //                 $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($package["extra_person_price"],$currency_name,$baseCurrency),2);
    //             }
    //             $converted_package_inventory[$key]["adults"]=$package["adults"];
    //             $converted_package_inventory[$key]['max_child']=$package["max_child"];
    //             $converted_package_inventory[$key]['extra_person']=$package["extra_person"];
    //             $converted_package_inventory[$key]['extra_child']=$package["extra_child"];
    //             $converted_package_inventory[$key]['package_id']=$package["package_id"];
    //             $converted_package_inventory[$key]['package_name']=$package["package_name"];
    //             $converted_package_inventory[$key]['room_type_id']=$package["room_type_id"];
    //             $converted_package_inventory[$key]['total_rooms']=$package["total_rooms"];
    //             $converted_package_inventory[$key]['nights']=$package["nights"];
    //             $converted_package_inventory[$key]['image_name']=$package["image_name"];
    //             $converted_package_inventory[$key]['image_id']=$package["image_id"];
    //             $converted_package_inventory[$key]['package_description']=$package["package_description"];
    //             $converted_package_inventory[$key]['package_image']=$package["package_image"];
    //             $converted_package_inventory[$key]['date_from']=$package["date_from"];
    //             $converted_package_inventory[$key]['date_to']=$package["date_to"];
    //             $info=$this->invService->getInventeryByRoomTYpe($package['room_type_id'],$package['date_from'],$package['date_to'], 0);
    //             $roomtype['min_inv']=$info[0]['no_of_rooms'];
    //             $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
    //             $package_inventory[$key]["inv"]=$info;
    //             $converted_package_inventory[$key]["inv"]=$info;
    //         }

    //         if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
    //         {
    //             $res=array("status"=>1,"message"=>"successfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
    //             return response()->json($res);
    //         }
    //         else{
    //             $res=array("status"=>0,"message"=>"fail to retrive details");
    //             return response()->json($res);
    //         }
    //     }
    // }
    // public function getSpecificPackageDetails(int $hotel_id,$from_date,$currency_name,Request $request){
    //     $failure_message="Please provide hotel id and dates";
    //     $validator=Validator::make(array('hotel_id'=>$hotel_id,'from_date'=>$from_date),$this->pkg_rules,$this->pkg_messages);
    //     if ($validator->fails())
    //     {
    //         return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
    //     }
    //     $from_date = date('Y-m-d',strtotime($from_date));
    //     if($hotel_id){
    //         $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;//Get base currency

    //         $getPackage_details = packages::select('*')->where('package_table.hotel_id',$hotel_id)
    //         // ->where('package_table.date_from','<=',$from_date)
    //         ->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) //-----get packages from curenct date by samya ranjan------------//
    //         ->orWhere('package_table.date_from','>=',$from_date) //-----get packages from curenct date by samya ranjan------------//
    //         ->where('package_table.is_trash',0)
    //         ->get();
            
    //         if(sizeof($getPackage_details)>0){
    //             foreach($getPackage_details as $package_details){
    //                 if($package_details->room_type_id == 0){
    //                     $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->select('package_table.*','image_table.*')
    //                     ->where('package_table.hotel_id',$hotel_id)
    //                     // ->where('package_table.date_from','<=',$from_date)
    //                     ->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) //-----get packages from curenct date by samya ranjan------------//
    //                     ->orWhere('package_table.date_from','>=',$from_date) //-----get packages from curenct date by samya ranjan------------//
    //                     ->where('package_table.is_trash',0)
    //                     ->get();
    //                 }
    //                 else{
    //                     $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')
    //                     ->select('package_table.*','image_table.*','room_type_table.total_rooms','room_type_table.room_type_id')
    //                     ->where('package_table.hotel_id',$hotel_id)
    //                     // ->where('package_table.date_from','<=',$from_date)
    //                     ->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) //-----get packages from curenct date by samya ranjan------------//
    //                     ->orWhere('package_table.date_from','>=',$from_date) //-----get packages from curenct date by samya ranjan------------//
    //                     ->where('package_table.is_trash',0)
    //                     ->get();
    //                 }
    //             }
    //         $converted_package_inventory=array();//Initialize the empty array
    //         foreach($package_inventory as $key => $package)
    //         {
    //             $package['min_inv']=0;
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["amount"]=$package["amount"];
    //             }else{
    //                 $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($roomtype["amount"],$currency_name,$baseCurrency),2);
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["discounted_amount"]=$package["discounted_amount"];
    //             }else{
    //                 $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($package["discounted_amount"],$currency_name,$baseCurrency),2);
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
    //             }else{
    //                 $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($package["extra_child_price"],$currency_name,$baseCurrency),2);
    //             }
    //             if($baseCurrency == $currency_name){
    //                 $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
    //             }else{
    //                 $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($package["extra_person_price"],$currency_name,$baseCurrency),2);
    //             }
    //             $converted_package_inventory[$key]["adults"]=$package["adults"];
    //             $converted_package_inventory[$key]['max_child']=$package["max_child"];
    //             $converted_package_inventory[$key]['extra_person']=$package["extra_person"];
    //             $converted_package_inventory[$key]['extra_child']=$package["extra_child"];
    //             $converted_package_inventory[$key]['package_id']=$package["package_id"];
    //             $converted_package_inventory[$key]['package_name']=$package["package_name"];
    //             $converted_package_inventory[$key]['room_type_id']=$package["room_type_id"];
    //             $converted_package_inventory[$key]['total_rooms']=$package["total_rooms"];
    //             $converted_package_inventory[$key]['nights']=$package["nights"];
    //             $converted_package_inventory[$key]['image_name']=$package["image_name"];
    //             $converted_package_inventory[$key]['image_id']=$package["image_id"];
    //             $converted_package_inventory[$key]['package_description']=$package["package_description"];

    //             //-----------get multiple images of packages by Samya Ranjan Patel----------//
    //             $package['package_image']=explode(',',$package['package_image']);
    //             $converted_package_inventory[$key]['package_image']=$this->getImages($package->package_image);
    //             //---------end of by Samya Ranjan Patel---------------------//
    //             // $converted_package_inventory[$key]['package_image']=$package["package_image"];
                
    //             //----------- To display dates in human readable format by Samya Ranjan-----------------//
    //             $converted_package_inventory[$key]['package_start_date']= date('d M Y', strtotime($package["date_from"]));
    //             $converted_package_inventory[$key]['package_end_date']= date('d M Y', strtotime($package["date_to"]));
    //             //----------- end of To display dates in human readable format by Samya Ranjan-----------------//

    //             $converted_package_inventory[$key]['date_from']=$package["date_from"];
    //             $converted_package_inventory[$key]['date_to']=$package["date_to"];
    //             $info=$this->invService->getInventeryByRoomTYpe($package['room_type_id'],$package['date_from'],$package['date_to'], 0);
    //             $roomtype['min_inv']=$info[0]['no_of_rooms'];
    //             $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
    //             $package_inventory[$key]["inv"]=$info;
    //             $converted_package_inventory[$key]["inv"]=$info;
    //         }
    //         if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
    //         {
    //             $res=array("status"=>1,"message"=>"sucessfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
    //             return response()->json($res);
    //         }
    //         else{
    //             $res=array("status"=>0,"message"=>"fail to retrive details");
    //             return response()->json($res);
    //         }
    //         }else{
    //             $res=array("status"=>0,"message"=>"fail to retrive details");
    //             return response()->json($res);
    //         }
           
    //     }
    // }
     public function getImages($imgs)
      {
          $images=DB::table('kernel.image_table')
                  ->whereIn('image_id', $imgs)
                  ->select('image_name')
                  ->get();
         if($images)
         {
             return $images;
         }
         else
         {
             return array();
         }
         
      }
       public function getSpecificPackageDetails(int $hotel_id,$from_date,$currency_name){
        $failure_message="Please provide hotel id and dates";
        $validator=Validator::make(array('hotel_id'=>$hotel_id,'from_date'=>$from_date),$this->pkg_rules,$this->pkg_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $from_date = date('Y-m-d',strtotime($from_date));
        if($hotel_id){
            $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
            $getPackage_details = Packages::select('*')->where('package_table.hotel_id',$hotel_id)
            ->where('package_table.is_trash','=',0)
            ->where('package_table.date_from','<=',$from_date)  //added by manoj
            ->where('package_table.date_to','>',$from_date)  //added by manoj
            //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) commented by manoj
            //->orWhere([['package_table.date_from','>=',$from_date],['package_table.is_trash','=',0]])
            ->get();
            //->orWhere('package_table.date_from','>=',$from_date)
            
            

        if(sizeof($getPackage_details)>0){
            foreach($getPackage_details as $package_details){
                if($package_details->room_type_id == 0){
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->select('package_table.*','image_table.*')
                    ->where('package_table.hotel_id',$hotel_id)
                    ->where('package_table.date_from','<=',$from_date) 
                    ->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) 
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.is_trash',0)
                    ->get();
                }
                else{
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')
                    ->select('package_table.*','image_table.*','room_type_table.total_rooms','room_type_table.room_type_id','room_type_table.room_type')
                    ->where('package_table.hotel_id',$hotel_id)
                    ->where('package_table.date_from','<=',$from_date)
                    ->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]])
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.is_trash',0)
                    ->get();
                }
            }
            $converted_package_inventory=array();//Initialize the empty array

            foreach($package_inventory as $key => $package)
            {
                $package['min_inv']=0;
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["amount"]=$package["amount"];
                }else{
                    $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($package["amount"],$currency_name,$baseCurrency),2);
                   
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["discounted_amount"]=$package["discounted_amount"];
                }else{
                    $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($package["discounted_amount"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($package["extra_child_price"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($package["extra_person_price"],$currency_name,$baseCurrency),2);
                }
                $converted_package_inventory[$key]["blackout_dates"]=json_decode($package["blackout_dates"],true);
                $converted_package_inventory[$key]["adults"]=$package["adults"];
                $converted_package_inventory[$key]['max_child']=$package["max_child"];
                $converted_package_inventory[$key]['extra_person']=$package["extra_person"];
                $converted_package_inventory[$key]['extra_child']=$package["extra_child"];
                $converted_package_inventory[$key]['package_id']=$package["package_id"];
                $converted_package_inventory[$key]['package_name']=$package["package_name"];
                $converted_package_inventory[$key]['room_type_id']=$package["room_type_id"];
                $converted_package_inventory[$key]['room_type']=$package["room_type"];
                $converted_package_inventory[$key]['total_rooms']=$package["total_rooms"];
                $converted_package_inventory[$key]['nights']=$package["nights"];
                $converted_package_inventory[$key]['image_name']=$package["image_name"];
                $converted_package_inventory[$key]['image_id']=$package["image_id"];
                $converted_package_inventory[$key]['package_description']=$package["package_description"];
                $package['package_image']=explode(',',$package['package_image']);
                $converted_package_inventory[$key]['package_image']=$this->getImages($package->package_image);

                $converted_package_inventory[$key]['date_from']=$package["date_from"];
                $converted_package_inventory[$key]['date_to']=$package["date_to"];

                $converted_package_inventory[$key]['package_date_from']= date('d M Y', strtotime($package["date_from"]));
                $converted_package_inventory[$key]['package_date_to']= date('d M Y', strtotime($package["date_to"]));

                $info=$this->invService->getInventeryByRoomTYpe($package['room_type_id'],$from_date,$package['date_to'], 0);
                $package['min_inv']=$info[0]['no_of_rooms'];
                $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
                $package_inventory[$key]["inv"]=$info;
                $converted_package_inventory[$key]["inv"]=$info;
            }

            if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
            {
                $res=array("status"=>1,"message"=>"successfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
                return response()->json($res);
            }
        }
        else{
                $res=array("status"=>0,"message"=>"fail to retrive details");
                return response()->json($res);
            }
        }
    }






    //added for changes in packages
    public function getSpecificPackageDetailsTest(int $hotel_id,$from_date,$currency_name){
        $failure_message="Please provide hotel id and dates";
        $validator=Validator::make(array('hotel_id'=>$hotel_id,'from_date'=>$from_date),$this->pkg_rules,$this->pkg_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $from_date = date('Y-m-d',strtotime($from_date));
        if($hotel_id){
            $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
            $getPackage_details = Packages::select('*')->where('package_table.hotel_id',$hotel_id)
            ->where('package_table.is_trash','=',0)
            ->where('package_table.date_from','<=',$from_date)  //added by manoj
            ->where('package_table.date_to','>',$from_date)  //added by manoj
            //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) commented by manoj
            //->orWhere([['package_table.date_from','>=',$from_date],['package_table.is_trash','=',0]])
            ->get();
            //->orWhere('package_table.date_from','>=',$from_date)
            
            

        if(sizeof($getPackage_details)>0){
            foreach($getPackage_details as $package_details){
                if($package_details->room_type_id == 0){
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->select('package_table.*','image_table.*')
                    ->where('package_table.hotel_id',$hotel_id)
                    ->where('package_table.date_from','<=',$from_date) 
                    ->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) 
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.is_trash',0)
                    ->get();
                }
                else{
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')
                    ->select('package_table.*','image_table.*','room_type_table.total_rooms','room_type_table.room_type_id','room_type_table.max_people','room_type_table.max_child as room_max_child','room_type_table.max_room_capacity','room_type_table.extra_person as room_extra_person','room_type_table.extra_child as room_extra_child','room_type_table.max_occupancy')
                    ->where('package_table.hotel_id',$hotel_id)
                    ->where('package_table.date_from','<=',$from_date)
                    ->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]])
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.is_trash',0)
                    ->get();
                }
            }
            $converted_package_inventory=array();//Initialize the empty array

            foreach($package_inventory as $key => $package)
            {
                $package['min_inv']=0;
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["amount"]=$package["amount"];
                }else{
                    $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($package["amount"],$currency_name,$baseCurrency),2);
                   
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["discounted_amount"]=$package["discounted_amount"];
                }else{
                    $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($package["discounted_amount"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($package["extra_child_price"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($package["extra_person_price"],$currency_name,$baseCurrency),2);
                }


                //added for changes in packages
                $converted_package_inventory[$key]["max_people"]=$package["max_people"];
                $converted_package_inventory[$key]["room_max_child"]=$package["room_max_child"];
                $converted_package_inventory[$key]['max_room_capacity']=$package["max_room_capacity"];
                $converted_package_inventory[$key]['room_extra_person']=$package["room_extra_person"];
                $converted_package_inventory[$key]['room_extra_child']=$package["room_extra_child"];
                $converted_package_inventory[$key]['max_occupancy']=$package["max_occupancy"];
                // added for changes in packages



                $converted_package_inventory[$key]["blackout_dates"]=json_decode($package["blackout_dates"],true);
                $converted_package_inventory[$key]["adults"]=$package["adults"];
                $converted_package_inventory[$key]['max_child']=$package["max_child"];
                $converted_package_inventory[$key]['extra_person']=$package["extra_person"];
                $converted_package_inventory[$key]['extra_child']=$package["extra_child"];
                $converted_package_inventory[$key]['package_id']=$package["package_id"];
                $converted_package_inventory[$key]['package_name']=$package["package_name"];
                $converted_package_inventory[$key]['room_type_id']=$package["room_type_id"];
                $converted_package_inventory[$key]['total_rooms']=$package["total_rooms"];
                $converted_package_inventory[$key]['nights']=$package["nights"];
                $converted_package_inventory[$key]['image_name']=$package["image_name"];
                $converted_package_inventory[$key]['image_id']=$package["image_id"];
                $converted_package_inventory[$key]['package_description']=$package["package_description"];
                $package['package_image']=explode(',',$package['package_image']);
                $converted_package_inventory[$key]['package_image']=$this->getImages($package->package_image);

                $converted_package_inventory[$key]['date_from']=$package["date_from"];
                $converted_package_inventory[$key]['date_to']=$package["date_to"];

                $converted_package_inventory[$key]['package_date_from']= date('d M Y', strtotime($package["date_from"]));
                $converted_package_inventory[$key]['package_date_to']= date('d M Y', strtotime($package["date_to"]));

                $info=$this->invService->getInventeryByRoomTYpe($package['room_type_id'],$from_date,$package['date_to'], 0);
                $roomtype['min_inv']=$info[0]['no_of_rooms'];
                $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
                $package_inventory[$key]["inv"]=$info;
                $converted_package_inventory[$key]["inv"]=$info;
            }

            if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
            {
                $res=array("status"=>1,"message"=>"successfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
                return response()->json($res);
            }
        }
        else{
                $res=array("status"=>0,"message"=>"fail to retrive details");
                return response()->json($res);
            }
        }
    }

    //================================================================================================
    public function getPackageDetailsByPackageID(int $hotel_id, $package_id, $from_date, $currency_name){
        $failure_message="Please provide hotel id and dates";
        $validator=Validator::make(array('hotel_id'=>$hotel_id,'from_date'=>$from_date),$this->pkg_rules,$this->pkg_messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $from_date = date('Y-m-d',strtotime($from_date));
        if($hotel_id){
            $baseCurrency=$this->getBaseCurrency($hotel_id)->currency;
            $getPackage_details = Packages::select('*')->where('package_table.hotel_id',$hotel_id)
            //->where('package_table.is_trash','=',0)
            //->where('package_table.date_from','<=',$from_date)  //added by manoj
            //->where('package_table.date_to','>',$from_date)  //added by manoj
            ->where('package_table.package_id',$package_id)  //added by manoj
            //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) commented by manoj
            //->orWhere([['package_table.date_from','>=',$from_date],['package_table.is_trash','=',0]])
            ->get();
            //->orWhere('package_table.date_from','>=',$from_date)
            
            

        if(sizeof($getPackage_details)>0){
            foreach($getPackage_details as $package_details){
                if($package_details->room_type_id == 0){
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->select('package_table.*','image_table.*')
                    ->where('package_table.hotel_id',$hotel_id)
                    //->where('package_table.date_from','<=',$from_date) 
                    //->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]]) 
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.package_id',$package_id)  //added by manoj
                    //->where('package_table.is_trash',0)
                    ->get();
                }
                else{
                    $package_inventory=Packages::join('image_table','package_table.package_image','=','image_table.image_id')->join('room_type_table','package_table.room_type_id','=','room_type_table.room_type_id')
                    ->select('package_table.*','image_table.*','room_type_table.total_rooms','room_type_table.room_type_id')
                    ->where('package_table.hotel_id',$hotel_id)
                    //->where('package_table.date_from','<=',$from_date)
                    //->where('package_table.date_to','>',$from_date)  //added by manoj
                    //->where([['package_table.date_from','<=',$from_date],['package_table.date_to' ,'>=',$from_date]])
                    //->orWhere('package_table.date_from','>=',$from_date) 
                    ->where('package_table.package_id',$package_id)  //added by manoj
                    //->where('package_table.is_trash',0)
                    ->get();
                }
            }
            $converted_package_inventory=array();//Initialize the empty array

            foreach($package_inventory as $key => $package)
            {
                $package['min_inv']=0;
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["amount"]=$package["amount"];
                }else{
                    $converted_package_inventory[$key]["amount"]=round($this->curency->currencyDetails($package["amount"],$currency_name,$baseCurrency),2);
                   
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["discounted_amount"]=$package["discounted_amount"];
                }else{
                    $converted_package_inventory[$key]["discounted_amount"]=round($this->curency->currencyDetails($package["discounted_amount"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_child_price"]=round($this->curency->currencyDetails($package["extra_child_price"],$currency_name,$baseCurrency),2);
                }
                if($baseCurrency == $currency_name){
                    $converted_package_inventory[$key]["extra_child_price"]=$package["extra_child_price"];
                }else{
                    $converted_package_inventory[$key]["extra_person_price"]=round($this->curency->currencyDetails($package["extra_person_price"],$currency_name,$baseCurrency),2);
                }
                $converted_package_inventory[$key]["blackout_dates"]=json_decode($package["blackout_dates"],true);
                $converted_package_inventory[$key]["adults"]=$package["adults"];
                $converted_package_inventory[$key]['max_child']=$package["max_child"];
                $converted_package_inventory[$key]['extra_person']=$package["extra_person"];
                $converted_package_inventory[$key]['extra_child']=$package["extra_child"];
                $converted_package_inventory[$key]['package_id']=$package["package_id"];
                $converted_package_inventory[$key]['package_name']=$package["package_name"];
                $converted_package_inventory[$key]['room_type_id']=$package["room_type_id"];
                $converted_package_inventory[$key]['total_rooms']=$package["total_rooms"];
                $converted_package_inventory[$key]['nights']=$package["nights"];
                $converted_package_inventory[$key]['image_name']=$package["image_name"];
                $converted_package_inventory[$key]['image_id']=$package["image_id"];
                $converted_package_inventory[$key]['package_description']=$package["package_description"];
                $package['package_image']=explode(',',$package['package_image']);
                $converted_package_inventory[$key]['package_image']=$this->getImages($package->package_image);

                $converted_package_inventory[$key]['date_from']=$package["date_from"];
                $converted_package_inventory[$key]['date_to']=$package["date_to"];

                $converted_package_inventory[$key]['package_date_from']= date('d M Y', strtotime($package["date_from"]));
                $converted_package_inventory[$key]['package_date_to']= date('d M Y', strtotime($package["date_to"]));

                $info=$this->invService->getInventeryByRoomTYpe($package['room_type_id'],$from_date,$package['date_to'], 0);
                $roomtype['min_inv']=$info[0]['no_of_rooms'];
                $converted_package_inventory[$key]["min_inv"]=$info[0]['no_of_rooms'];
                $package_inventory[$key]["inv"]=$info;
                $converted_package_inventory[$key]["inv"]=$info;
            }

            if(sizeof($package_inventory)>0 && sizeof($converted_package_inventory)>0)
            {
                $res=array("status"=>1,"message"=>"successfully retrive details","package_inventory"=>$package_inventory,"converted_package_inventory"=>$converted_package_inventory);
                return response()->json($res);
            }
        }
        else{
                $res=array("status"=>0,"message"=>"fail to retrive details");
                return response()->json($res);
            }
        }
    }

}
