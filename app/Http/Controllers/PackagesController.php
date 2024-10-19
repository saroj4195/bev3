<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Packages;//class name from model
use App\ImageTable;//class name from model
use DB;
//create a new class PackagesController
class PackagesController extends Controller
{

        //validation rules
        private $rules = array(
        'company_id' => 'required',
                'hotel_id' => 'required',
        'room_type_id' => 'required',
                'package_name' => 'required',
        'date_from' => 'required',
                'date_to' => 'required',
        'adults' => 'required',
                'nights' => 'required',
        'amount' => 'required',
                'discounted_amount' => 'required',
        'package_description' => 'required',
                //'package_image' => 'required',
        'max_child' => 'required',
                'extra_person' => 'required',
        'extra_person_price' => 'required',
                'extra_child' => 'required',
        'extra_child_price' => 'required'
        );
        //Custom Error Messages
        private $messages = [
                'company_id.required' => 'The company id id field is required.',
                        'hotel_id.required' => 'The hotel id field is required.',
                'room_type_id.required' => 'The room type id field is required.',
                        'package_name.required' => 'The package name field is required.',
                'date_from.required' => 'The date from field is required.',
                        'date_to.required' => 'The date to field is required.',
                'adults.required' => 'The adults field is required.',
                        'nights.required' => 'The nights field is required.',
                'amount.required' => 'The amount field is required.',
                        'discounted_amount.required' => 'The  discounted amount field is required.',
                'package_description.required' => 'The package description field is required.',

                // 'package_image.required' => 'The package image field is required.',
                        'max_child.required' => 'The max child field is required.',
                'extra_person.required' => 'The extra person field is required.',

                'extra_person_price.required' => 'The extra person price field is required.',
                        'extra_child.required' => 'The extra child field is required.',
                'extra_child_price.required' => 'The extra child price field is required.'
        ];
        /**
        * Hotel packages
        * Create a new record of packages.
        * @author subhradip
        * @return Hotel packages saving status
        * function addnew for createing a new Rate Plan Names
        **/
        public function addNewPackages(Request $request)
        {
                $packages = new Packages();
                $failure_message='Packages Details Saving Failed';
                $validator = Validator::make($request->all(),$this->rules,$this->messages);
                if ($validator->fails())
                {
                        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
                }
                $data=$request->all();
                $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
                $data['date_to']=date('Y-m-d',strtotime($data['date_to']));
                if($packages->fill($data)->save())
                {
                        $res=array('status'=>1,'message'=>"packages saved successfully");
                        return response()->json($res);
                }
                else
                {
                        $res=array('status'=>-1,"message"=>$failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                }
        }
        /**
        * Delete packages
        * delete record of packages
        * @author subhradip
        * @return packages deleting status
        * function DeletePromo used for delete
        **/
        public function DeletePackages(int $package_id ,Request $request)
        {
                $failure_message='Deleted Failure';
                if(Packages::where('package_id',$package_id)->update(['is_trash' => 1]))
                {
                $res=array('status'=>1,"message"=>'packages Deleted successfully');
                        return response()->json($res);
                }
                else
                {
                        $res=array('status'=>-1,"message"=>$failure_message);
                        $res['errors'][] = "Internal server error";
                        return response()->json($res);
                }
        }
        /**
        * packages
        * Update record of packages
        * @author subhradip
        * @return packages  saving status
        * function UpdatePromo use for update
        **/
        public function UpdatePackages(int $package_id,Request $request)
        {
                $up_image=array();
                $packages1 = new Packages();
                $failure_message="packages  saving failed.";
                $validator = Validator::make($request->all(),$this->rules,$this->messages);
                if ($validator->fails())
                {
                        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
                }
                $data=$request->all();
                $data['date_from']=date('Y-m-d',strtotime($data['date_from']));
                $data['date_to']=date('Y-m-d',strtotime($data['date_to']));

                if($data['package_image']!="")
                {
                        $up_image=explode(',', $data['package_image']);
                }
                $packages = Packages::where('package_id',$package_id)->first();

                if($packages->package_id == $package_id )
                {
                        if(sizeof($packages->package_image)>0)
                        {
                                $images=explode(',',$packages->package_image);
                                foreach($images as $img)
                                {
                                        array_push($up_image,(int)$img);//PUsing the images get from user request
                                }
                        }
                        //dd($ext_img);
                        $up_image=implode(',',$up_image);
                        $data['package_image']=$up_image;

                        if($packages->fill($data)->save())
                        {
                                $res=array('status'=>1,"message"=>"packages updated successfully");
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
        * Get  packages
        * get one record of  room type
        * @author subhradip
        * function GetPackages for delecting data
        **/
        public function GetPackages(int $package_id ,Request $request)
        {
                $Packages=new Packages();
                if($package_id)
                {
                        $conditions=array('package_id'=>$package_id,'is_trash'=>0);
                        $res=Packages::where($conditions)->first();
                        if($res)
                        {
                                $res=array('status'=>1,'message'=>"packages details found",'data'=>$res);
                                return response()->json($res);
                        }
                        else
                        {
                                $res=array('status'=>0,"message"=>"No packages records found");
                                return response()->json($res);
                        }
                }
                else
                {
                        $res=array('status'=>-1,"message"=>"Master Hotel Rate Plan  fetching failed");
                        return response()->json($res);
                }
        }
        /**
        * Get all packages
        * get All record of packages
        * @author subhradip
        * function GetAllPromo for selecting all data
        **/
        public function GetAllPackages(int $hotel_id ,Request $request)
        {

                $res=DB::table('kernel.image_table')->join('booking_engine.package_table','image_table.image_id','=','package_table.package_image')
                ->select('image_table.image_name','package_table.package_name','package_table.nights','package_table.date_from',
                'package_table.date_to','package_table.package_id')
                ->where('package_table.hotel_id', '=', $hotel_id)
                ->where('package_table.is_trash', '=', 0)
                ->get();
                if(sizeof($res)>0)
                {
                        $res=array('status'=>1,'message'=>"records found",'data'=>$res);
                        return response()->json($res);
                }
                else
                {
                        $res=array('status'=>0,"message"=>"No packages  records found");
                        return response()->json($res);
                }
        }
        /**
        * Get images of packages
        * get All images o fpackages
        * @author subhradip
        * function getPckagesImages for selecting all data
        **/
        public function getPckagesImages(int $package_id, Request $request)
        {
                $HotelpoliciesDetails=new Packages();
                //dd("SELECT a.*, b.package_image as images FROM `package_table` a, image_table b  WHERE a.is_trash=0 AND  b.image_id=substring_index(a.image,',',1) ");
                $res=DB::select("SELECT a.*, b.image_name as images FROM `package_table` a, kernel.image_table b  WHERE a.is_trash=0 AND  b.image_id=substring_index(a.package_image,',',1)");
                $images = Packages::select('package_image')->where('package_id', $package_id)
                ->where('is_trash', 0)->first();
                $images=explode(',',$images->package_image);
                //dd($images);
                $roomTypeImages = DB::table('kernel.image_table')
                ->select('image_id','image_name')
                ->whereIn('image_id', $images)
                ->get();
                //$res=MasterRoomType::where($conditions)->get();
                if(sizeof($res)>0)
                {
                        $res1=array("status"=>1,"message"=>"Packages retrieved successfuly", "roomTypeImages"=>$roomTypeImages);
                        return response()->json($res1);
                }
                else
                {
                $res=array("status"=>0,"message"=>"No packages records found");
                        return response()->json($res);

                }
        }
        // DELETE RECORD FROM HOTELS IMAGES
        /**
        * Delete room type Images Details
        * @auther : Godti Vinod
        * @story : by this function we can delete a specific image having the id which is coming from URL
        *          It will also remove the image from its location .
        *          This also delete the record from the table 'imag_table'.
        *
        * @return Hotel_images deleting status
        **/
        public function deleteImage(Request $request)
        {
                // Validate UUID
                $failure_message='Image deletion failed';
                $data=$request->all();
                $im_name=$data['imageId'];
                $package_id=$data['package_id'];
                // print_r($im_name);
                //print_r($package_id);
                //Remove Image from folder
                $up_path =public_path('uploads').'/'.$im_name;//dd($up_path);
                $image_data=DB::connection('bookingjini_kernel')->table('image_table')->select('image_id')->where('image_name',$im_name)->first();
                if(file_exists($up_path))
                {
                        if(@unlink($up_path))
                        {
                                if(DB::connection('bookingjini_kernel')->table('image_table')->where('image_name',$im_name)->delete())
                                {
                                        $images = Packages::select('package_image')->where('package_id', $package_id)->first();
                                        if(sizeof($images->package_image)>0)
                                        {
                                                $images=explode(',',$images->package_image);
                                                foreach($images as $key=>$img)
                                                {
                                                        if( $img==$image_data->image_id)
                                                        {
                                                                unset($images[$key]);
                                                        }
                                                }
                                        }
                                        //dd($ext_img);
                                        $images=implode(',',$images);
                                        if(Packages::where('package_id',$package_id)->update(['package_image' => $images ]))
                                        {
                                                $res=array('status'=>1,"message"=>'Image deleted successfully');
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
                                $res['errors'][] = "Unable to remove the file ";
                                return response()->json($res);
                        }
                }
                else
                {
                        $res=array('status'=>-1,"message"=>$failure_message);
                        $res['errors'][] = "File not found in server.";
                        return response()->json($res);
          
                }       
                // Enable status
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
                ->join('room_type_table', 'hotel_cancellation_policy.room_type_id', '=', 'room_type_table.room_type_id')
                ->where('hotel_cancellation_policy.hotel_id' , '=' , $hotel_id)
                ->where('hotel_cancellation_policy.is_trash' , '=' , 0)
                ->select('hotel_cancellation_policy.from_date', 'hotel_cancellation_policy.to_date',
                'hotel_cancellation_policy.cancellation_before_days','hotel_cancellation_policy.percentage_refund',
                'room_type_table.room_type', 'room_type_table.room_type_id'
                )
                ->get();
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
}
