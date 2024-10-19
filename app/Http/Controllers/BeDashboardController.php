<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Invoice;
use App\HotelBooking;
use App\Visitors;
use App\VisitorsLog;
use App\Http\Controllers\Controller;
/**
* This Controller is made for giving details about be dashboard
*@author Ranjit Date- 03-03-2020
*/
class BeDashboardController extends Controller
{
  protected $ipService;
  public function __construct(IpAddressService $ipService){
     $this->ipService=$ipService;
  }
  private $rules=array(
      'company_id'=>'required | numeric',
      'visitor_browser'=>'required',
      'visitor_page'=>'required'
  );
  private $messages=[
      'company_id.required'=>'company id should be required',
      'company_id.numeric'=>'company id should be numeric',
      'visitor_browser.required'=>'visitor browser should be required',
      'visitor_page.required'=>'visitor page should be required'
  ];
    // Fetch be earning date wise.
    public function selectInvoice(int $hotel_id,$from_date,$to_date,Request $request)
    {
        $invoiceselect=new Invoice();
        $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        $bookingAmountBe = 0;
        $bookingAmountCrs = 0;
        if($hotel_id)
        {
            $condition=array('invoice_table.hotel_id'=>$hotel_id,'invoice_table.booking_status'=>1);
            try{
                $bookingAmount=Invoice::where($condition)
                ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                ->select(DB::raw('SUM(total_amount) as total_amount,booking_source'))
                ->whereDate('hotel_booking.check_in','>=',$from_date)
                ->whereDate('hotel_booking.check_out','<=',$to_date)
                ->groupBy('booking_source')
                ->get();
            }
            catch(Exception $e){
                $res = array('status'=>0,'message'=>$e->getMessage());
                return response()->json($res);
            }
            
            foreach($bookingAmount as $data){
                if($data->booking_source == 'website'){
                    $bookingAmountBe = round($data->total_amount);
                }if($data->booking_source == 'CRS'){
                    $bookingAmountCrs = round($data->total_amount);
                }
            }
            
            if($bookingAmountBe>0 || $bookingAmountCrs>0)
            {
                $res=array('status'=>1,'message'=>'bookingAmount retrieve successfully','bookingAmountBe'=>$bookingAmountBe,'bookingAmountCrs'=>$bookingAmountCrs);
                return response()->json($res);
            }
            else{
                $res=array('status'=>0,'message'=>'bookingAmount retrieve fails');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>1,'message'=>'bookingAmount retrieve fails');
                return response()->json($res);
        }
    }
    public function beUniqueVisitors(int $company_id,$from_date,$to_date,Request $request)
    {
        $to_date    =   date('Y-m-d',strtotime($to_date));
        $from_date  =   date('Y-m-d',strtotime($from_date));
        try{
            // $uniqueIp=Visitors::select('visitor_ip')->where('company_id',$company_id)->whereDate('visitor_date','>=',$from_date)->whereDate('visitor_date','<=',$to_date)->distinct()->count('visitor_ip');
        }
        catch(Exception $e){
            $res = array('status'=>0,'message'=>$e->getMessage());
            return response()->json($res);
        }
        // if($uniqueIp)
        // {
        //     $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>0);
        //     return response()->json($res);
        // }
        // else{
        //     $res = array('status'=>0,'message'=>'record not retrieve');
        //     return response()->json($res);
        // }
    }
    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getBeDate = HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
        ->select(DB::raw('SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as nights,booking_source'))
        ->where('invoice_table.hotel_id',$hotel_id)
        ->where('hotel_booking.check_in','>=',$checkin)
        ->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)
        ->groupBy('booking_source')
        ->get();
        $numberOfNightsBe=0;
        $numberOfNightsCrs=0;
        foreach($getBeDate as $key => $be_details){
            if($be_details->booking_source == 'website'){
                $numberOfNightsBe=$numberOfNightsBe+(int)$be_details->nights;
            }else if($be_details->booking_source == 'CRS'){
                $numberOfNightsCrs=$numberOfNightsCrs+(int)$be_details->nights;
            }
        }
        if($numberOfNightsBe > 0|| $numberOfNightsCrs > 0 ){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','dataBe'=>$numberOfNightsBe,'dataCrs'=>$numberOfNightsCrs);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails');
            return response()->json($resp);
        }
    }
    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getBeAverageStay=HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_be_bookings'),DB::raw('booking_source, SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as be_nights'))->where('invoice_table.hotel_id',$hotel_id)->where('hotel_booking.check_in','>=',$checkin)->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)->groupBy('booking_source')->get();
        $no_of_booking_be = 0;
        $no_of_booking_crs = 0;
        $no_of_nights_be = 0;
        $no_of_nights_crs = 0;
        foreach($getBeAverageStay as $be_details){
            if($be_details->booking_source == 'website'){
                if($be_details->no_of_be_bookings != 0){
                    $no_of_nights_be = $no_of_nights_be + $be_details->be_nights;
                    $no_of_booking_be = $no_of_booking_be+$be_details->no_of_be_bookings;
                }
            }
            else if($be_details->booking_source == 'CRS'){
                if($be_details->no_of_be_bookings != 0){
                    $no_of_nights_crs = $no_of_nights_crs + $be_details->be_nights;
                    $no_of_booking_crs = $no_of_booking_crs+$be_details->no_of_be_bookings;
                }
            } 
        }
        if(($no_of_nights_be > 0 && $no_of_booking_be > 0) || ($no_of_nights_crs > 0 && $no_of_booking_crs)){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','no_of_nights_be'=>$no_of_nights_be,'no_of_booking_be'=>$no_of_booking_be,'no_of_nights_crs'=>$no_of_nights_crs,'no_of_booking_crs'=>$no_of_booking_crs);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Average stay fetching fails');
            return response()->json($resp);
        }
    }
    public function ratePlanPerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getRoomType = Invoice::select(DB::raw('count(invoice_id) as room_no'),'room_type','booking_source')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('booking_status',1)->groupBy('room_type','booking_source')->orderBy('room_no','DESC')->limit(2)->get();
        $roomtype = '';
        $roomtype_be = 'NA';
        $roomtype_crs = 'NA';
        foreach($getRoomType as $room){
            if($room->booking_source == 'website'){
                $roomtype = substr($room->room_type,4);
                $roomtype_be = str_replace('"]','',$roomtype);
            }else if($room->booking_source == 'CRS'){
                $roomtype = substr($room->room_type,4);
                $roomtype_crs = str_replace('"]','',$roomtype);
            }
        }
        if($roomtype_be != '' || $roomtype_crs != ''){
            $resp = array('status'=>1,'message'=>'room rate plan for be','data_be'=>$roomtype_be,'data_crs'=>$roomtype_crs);
        }
        else{
            $resp = array('status'=>0,'message'=>'Fails');
        }
        return response()->json($resp);
    }
    public function dashboardBookingDetails(int $hotel_id,$from_date,$to_date, Request $request)
    {
        $from_date  = date('Y-m-d',strtotime($from_date));
        $to_date    = date('Y-m-d',strtotime($to_date));
        $start_date = $from_date;
        $end_date   = $to_date;
        $beBooking  = DB::select('CALL beBookingGraph(?,?,?)',["$hotel_id","$from_date","$to_date"]);
        $bookings = array();
        $bookings_data = array();
        if(count($beBooking)>0)
        {
            $i = 0;
            $check_date = array();
            while (strtotime($from_date) <= strtotime($to_date)) {
                foreach ($beBooking as $key=> $value) {
                    if($from_date == $value->index_date){
                        $bookings[$i]["bookings"] = $value->bookings;
                        $bookings[$i]["index_date"] = date("d-M-Y",strtotime($value->index_date));
                        $check_date[]=$value->index_date;
                        $i++;
                    }
                }
                $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
            }
              while (strtotime($start_date) <= strtotime($end_date)) {
                  foreach ($bookings as $value) {
                      if(isset($value["index_date"])){
                          if(!in_array($start_date,$check_date)){
                            $bookings_data["bookings"] =0;
                            $bookings_data["index_date"] = date("d-M-Y",strtotime($start_date));
                            $check_date[]=$start_date;
                            $bookings[] = $bookings_data;
                          }
                      }
                  }
                  $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
              }
            usort($bookings, function($a, $b) {
                return $a['index_date'] <=> $b['index_date'];
            });
            $res=array("status"=>1,"message"=>"BE booking details retrive sucessfully","beBooking"=>$bookings);
            return response()->json($res);
        }
        else
        {
          $i = 0;
          while (strtotime($from_date) <= strtotime($to_date)) {
                $bookings[$i]["bookings"] = 0;
                $bookings[$i]["index_date"] = date("d-M-Y",strtotime($from_date));
                $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
                $i++;
            }
            $res=array("status"=>0,"message"=>"BE booking details retrive fails",'beBooking'=>$bookings);
            return response()->json($res);
        }
    }
    public function getHotelBookings(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $HotelBookingdetails=HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
                    ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                    ->select('invoice_table.invoice_id','invoice_table.booking_date','hotel_booking.check_in','invoice_table.total_amount','invoice_table.paid_amount','user_table.first_name','user_table.last_name','hotel_booking.check_out')
                    ->where('invoice_table.booking_status',1)
                    ->where('hotel_booking.check_in',$today)
                    ->where('invoice_table.hotel_id',$hotel_id)
                    ->get();

            if(count($HotelBookingdetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$HotelBookingdetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"hotel booking details retrieve fails");
                return response()->json($res);
            }
    }
    public function getHotelBookingsCheckOut(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $HotelBookingdetails=HotelBooking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
                    ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                    ->select('invoice_table.invoice_id','invoice_table.booking_date','check_in','total_amount','paid_amount','first_name','last_name','check_out')
                    ->where('invoice_table.booking_status',1)
                    ->where('check_out',$today)
                    ->where('invoice_table.hotel_id',$hotel_id)
                    ->get();
            if(count($HotelBookingdetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$HotelBookingdetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"hotel booking details retrieve fails");
                return response()->json($res);
            }
    }
    public function hotelBookingCheckInOutInvoice(int $invoice_id,Request $request)
    {
        $HotelBookingdetails=Invoice::select('invoice')
                    ->where('invoice_id',$invoice_id)
                    ->first();
            if($HotelBookingdetails)
            {
                $res=array("status"=>1,"message"=>"hotel booking details retrieve successfully","HotelBookingdetails"=>$HotelBookingdetails);
                return response()->json($res);
            }
            else
            {
                $res=array("status"=>0,"message"=>"hotel booking details retrieve fails");
                return response()->json($res);
            }
    }
    public function uniqueVisitors(int $hotel_id,Request $request)
    {
        $data=$request->all();
        $uniqueVisitor=new Visitors();
        $failure_message="unique visitor failure";
        $validator=Validator::make($request->all(),$this->rules,$this->messages);
        if($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
        }
        
        $data['visitor_ip']=$this->ipService->getIPAddress();

        
        
        if($uniqueVisitor->fill($data)->save())
        {
            $uniqueIp=Visitors::select('visitor_ip')->where('company_id',$data['company_id'])->distinct()->count('visitor_ip');
            $res=array('status'=>1,'message'=>'record added successfully','uniqueVisitors'=> $uniqueIp);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'record not added');
            $res['error'][]="internal server error";
            return response()->json($res);
        }
    }
}
