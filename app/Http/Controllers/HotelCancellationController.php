<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\HotelCancellationPolicy; //class from modelManageRoomType
use App\MasterRoomType;
use DB;
//a new class created HotelCancellationController
class HotelCancellationController extends Controller
{
    private $rules = array(
        'from_date' => 'required ',
        'to_date' => 'required ',
        'cancellation_before_days' => 'required | numeric',
        'percentage_refund' => 'required | numeric',
        'hotel_id' =>'required | numeric'
        );
         //Custom Error Messages
        private $messages = [

        'from_date.required' => 'The from date  field is required.',
        'to_date.required' => 'The to date  field is required.',

        'cancellation_before_days.required' => 'The cancellation before days  field is required.',
        'cancellation_before_days.numeric' => 'The cancellation before days  should be numeric.',

        'percentage_refund.required' => 'The percentage refund  field is required.',
        'percentage_refund.numeric' => 'The percentage refund should be numeric.',
        'hotel_id.required' =>'Hotel id is required'
    ];

    /**
     * Hotel cancellation policy
     * Create a new record of Hotel cancellation policy .
     * @author subhradip
     * @return Hotel cancellation policy Name saving status
     *function addNewCancellationPolicies is  for creating a new cancellation policy name
    **/
    public function addNewCancellationPolicies(Request $request)
    {
        $hotel_cancellationpolicy = new HotelCancellationPolicy();
        $failure_message='Hotel cancellation policies saving failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
        //TO get user id from AUTH token
        if(isset($request->auth->admin_id)){
            $data['user_id']=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
            $data['user_id']=$request->auth->super_admin_id;
        }
        else if(isset($request->auth->id)){
            $data['user_id']=$request->auth->id;
        }
        if($hotel_cancellationpolicy->checkCancellationPolicy($data['room_type_id'],$data['hotel_id'],$data['from_date'],$data['to_date'])=="new")
        {
            if($hotel_cancellationpolicy->fill($data)->save())
            {
                $res=array('status'=>1,'message'=>"Hotel cancellation polices saved successfully",'res'=>$data);
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
            $res=array('status'=>-1,"message"=>"Cancellation policy of hotel already exists");
            return response()->json($res);;
        }
    }
    /**
     * Hotel cancellation policy
     * Update record of Hotel cancellation policy
     * @author subhradip
     * @return Hotel cancellation policy saving status
     * function updateCancellationPolicy id for updating  cancellation policy
    **/
    public function updateCancellationPolicy(int $id ,Request $request)
    {
      $hotel_cancellationpolicy = new HotelCancellationPolicy();
      $failure_message="Hotel's cancellation policies  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
      $data=$request->all();
      $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
      $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
      $hotel_cancellation_policy = HotelCancellationPolicy::where('id',$id)->first();
      if($hotel_cancellation_policy->id == $id )
        {
            if($hotel_cancellation_policy->fill($data)->save())
            {
                $res=array('status'=>1,'message'=>"Hotel cancellation polices updated successfully",'res'=>$data);
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
    /**
     * Get Hotel cancellation policy
     * get one record of Hotel cancellation policy
     * @author subhradip
     * function getHotelCancellationPolicy is for getting a data.
    **/
    public function getHotelCancellationPolicy(int $id ,Request $request)
    {
        $hotel_cancellation_policy=new HotelCancellationPolicy();
        if($id)
        {
            $conditions=array('id'=>$id,'is_trash'=>0);
            $hotel_cancell_policy=HotelCancellationPolicy::where($conditions)->first();
            if($hotel_cancell_policy)
            {
                $data=$hotel_cancell_policy;
                $res=array('status'=>1,'message'=>"Hotel cancellation policy retrieved successfully",'data'=>$data);

                return response()->json($res);
            }
            else
            {
                $res=array('status'=>0,"message"=>"No hotel cancellation policy records found");
                return response()->json($res);
            }
        }
        else
        {
            $res=array('status'=>-1,"message"=>"Hotel id is invalid");
            return response()->json($res);
        }

    }
     /**
     * Get all  Hotel cancellation policy
     * get All record of  Hotel cancellation policy
     * @author subhradip
     * function GetAllCancellationPolicy for selecting all data
    **/
    public function GetAllCancellationPolicy(int $hotel_id ,Request $request)
    {
        $data= DB::table('hotel_cancellation_policy')
        ->leftJoin('kernel.room_type_table', 'hotel_cancellation_policy.room_type_id', '=', 'room_type_table.room_type_id')
        ->where('hotel_cancellation_policy.hotel_id' , '=' , $hotel_id)
        ->where('hotel_cancellation_policy.is_trash' , '=' , 0)
        ->select('hotel_cancellation_policy.id','hotel_cancellation_policy.from_date', 'hotel_cancellation_policy.to_date',
                 'hotel_cancellation_policy.cancellation_before_days','hotel_cancellation_policy.percentage_refund',
                 'room_type_table.room_type', 'hotel_cancellation_policy.room_type_id'
               )
               ->get();
        if($data)
        {
            foreach($data as $cancel_policy)
            {
                if($cancel_policy->room_type_id==0)
                {
                    $cancel_policy->room_type="All";
                }
            }
        }
        if(sizeof($data)>0)
        {
            $res=array('status'=>1,'message'=>"Found hotel cancellation",'data'=>$data);
            return response()->json($res);
        }
        else
        {
            $data=array('status'=>0,"message"=>"No  Hotel cancellation policy  records found");
            return response()->json($data);
        }
    }

     /**
     * Delete Cancellation policy
     * delete record of Cancellation policy
     * @author subhradip
     * @return Cancellation policy deleting status
     * function DeleteCancellationPolicy used for delete
    **/
    public function DeleteCancellationPolicy(int $cancel_policy_id ,Request $request)
    {
        $failure_message='Cancellation deletion failed';
        if(HotelCancellationPolicy::where('id',$cancel_policy_id)->update(['is_trash' => 1]))
        {
            $res=array('status'=>1,"message"=>'Cancellation policy Deleted successfully');
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
