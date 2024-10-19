<?php
namespace App\Http\Controllers\bharatstay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\HotelInformation;
use App\CompanyDetails;
use App\ImageTable;
use App\Invoice;
use App\HotelBooking;
use App\HotelFAQ;
use App\HotelStatistics;
use App\SearchLog;
use App\SearchStatistics;
use App\Http\Controllers\Controller;

class SearchController extends Controller 
{
    //================================================================================================
    public function getTopDestinations(Request $request)
    {
        $popular_cities = array();
        $city_sql = "SELECT city_table.city_id, city_name, count(hotel_id) as hotels_present
            FROM 
            kernel.city_table 
            INNER JOIN kernel.hotels_table ON city_table.city_id = hotels_table.city_id
            WHERE hotels_table.status = 1
            GROUP BY city_table.city_id, city_name
            ORDER BY count(hotel_id) DESC
            LIMIT 10";
        $all_popular_city = DB::select("$city_sql");



        
        foreach($all_popular_city as $popular_city)
        {
            $city['city_id'] = $popular_city->city_id;
            $city['city_name'] = $popular_city->city_name;
            $city['city_image'] = env('S3_ROOT_PATH')."group-website/bookingjini/city_images/".$popular_city->city_id.'.png';
            $popular_cities[] = $city;
        }
        
        
        $msg = array('status' => 1,'message'=>'data found','destinations'=>$popular_cities);
        return response()->json($msg);
    }
    //================================================================================================
    public function getTopRatedHotels(Request $request)
    {
        $group_hotels = DB::table('kernel.hotels_table')
        ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->where('status',1)
        ->groupBy('kernel.hotels_table.hotel_id','hotel_name','exterior_image','image_name','city_table.city_name')
        ->select('kernel.hotels_table.hotel_id','hotel_name',
        DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name')
        ;
        $group_hotels = $group_hotels->take(10)->get();

        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            
            $hotel_record['city_name'] = $hotel_info->city_name;
            
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data);
        return response()->json($msg);
        
      
    }
    //================================================================================================================================
    public function getAvailableHotelListByCityAndDateOld(Request $request )
    {
        $city_id = (int)$request['city_id'];
        $checkin_date = date('d-m-Y',strtotime($request['checkin_date']));
        $checkout_date = date('d-m-Y',strtotime($request['checkout_date']));
        $no_of_rooms = (int)$request['no_of_rooms'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        $str_amenities = $request['amenities'];
        $page_no =  (int)$request['page_no'];
        $skip = ($page_no - 1) * 10;
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.city_table.city_id', $city_id)
            ->Where('kernel.hotels_table.status', 1)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',
            DB::raw('min(bar_price) as starting_price'), 
            DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),
            'kernel.image_table.image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->orderBy('hotel_name')
            ->skip($skip)
            ->take(10)
            ;
        
        
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        $city_name = $group_hotels[0]->city_name;
        foreach($group_hotels as $hotel_info)
        {
            $hotel_id = $hotel_info->hotel_id;
            $api_key = $hotel_info->api_key;
            //get inventory
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://be.bookingjini.com/bookingEngine/get-inventory/$api_key/$hotel_id/$checkin_date/$checkout_date/INR",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $res_json = json_decode($response, true);
            $hotel_has_rooms = FALSE;
            if($res_json['status'] == 1)
            {
                $room_types = $res_json['room_data'];
                foreach($room_types as $room_type)
                {
                    $room_type_room_available = TRUE;
                    $room_inventories = $room_type['inv'];
                    foreach($room_inventories as $room_inventory)
                    {
                        if($no_of_rooms  > $room_inventory['no_of_rooms'])
                        {
                            $room_type_room_available = FALSE;
                            break;
                        }
                    }
                    if($room_type_room_available)
                    {
                        $hotel_has_rooms = TRUE;
                        break;
                    }
                }
            }
            if($hotel_has_rooms)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $hotel_record['hotel_name'] = $hotel_info->hotel_name;
                $hotel_record['star'] = $hotel_info->star_of_property;
                $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
                $hotel_record['ending_price'] = $hotel_info->ending_price;
                $hotel_record['starting_price'] = $hotel_info->starting_price;
                $hotel_record['city_name'] = $hotel_info->city_name;
                $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
                $hotel_record['max_guest'] = 3;
                $hotel_record['hotel_address'] = $hotel_info->hotel_address;
                $hotel_record['api_key'] = $hotel_info->api_key;
                $hotels_data[] = $hotel_record;
            }  
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'hotels_found'=>count($hotels_data));
        return response()->json($msg);
    }
    //================================================================================================================================
    public function getAvailableHotelListByCityAndDate(Request $request )
    {
        $user_id = $request['user_id'];
        $city_id = (int)$request['city_id'];
        $city_name = $request['city_name'];
        $checkin_date = date('Y-m-d',strtotime($request['checkin_date']));
        $checkout_date = date('Y-m-d',strtotime($request['checkout_date']));
        $start_date = $checkin_date;
        $end_date = date('Y-m-d',strtotime($checkout_date .' -1 day'));

        $checkin = date_create($checkin_date);
        $checkout = date_create($checkout_date);
        $no_of_nights = date_diff($checkin,$checkout)->format('%a');

        $no_of_rooms = (int)$request['no_of_rooms'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        $str_amenities = $request['amenities'];
        $page_no =  (int)$request['page_no'];
        $skip = ($page_no - 1) * 10;
        $hotel_count = 0;
        
        
        $group_hotels = DB::select(DB::raw("SELECT T.hotel_id, T.hotel_name, T.hotel_address, T.star_of_property, T.image_name, T.latitude, T.longitude,
            min(T.starting_price) AS starting_price FROM (
            SELECT A.hotel_id, A.hotel_name, A.city_id, A.hotel_address, A.star_of_property, B.image_name, C.stay_day, A.latitude, A.longitude,
            sum(C.no_of_rooms) as available_rooms,  min(D.bar_price) as starting_price
            FROM kernel.hotels_table A
            LEFT JOIN kernel.image_table B ON SUBSTRING_INDEX(A.exterior_image,',',2) = B.image_id
            INNER JOIN booking_engine.dp_cur_inv_table C ON A.hotel_id = C.hotel_id
            LEFT JOIN booking_engine.current_rate_table D ON C.hotel_id = D.hotel_id AND C.stay_day = D.stay_date AND C.room_type_id = D.room_type_id 
            WHERE A.status = 1
            AND A.city_id = $city_id
            AND C.stay_day between '$start_date' and '$end_date'
            AND C.block_status = 0
            GROUP BY A.hotel_id, A.hotel_name, A.city_id, A.hotel_address, A.star_of_property, B.image_name,C.stay_day, A.latitude, A.longitude
            HAVING sum(C.no_of_rooms) >= $no_of_rooms) T
            GROUP BY T.hotel_id, T.hotel_name, T.hotel_address, T.star_of_property, T.image_name, T.latitude, T.longitude
            HAVING count(hotel_id) = $no_of_nights
            LIMIT 10
            OFFSET $skip"))
            
            ;
        
        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['lat'] = $hotel_info->latitude;
            $hotel_record['lng'] = $hotel_info->longitude;
            $hotels_data[] = $hotel_record;
        }
        $hotel_count = ($page_no - 1) * 10 + count($group_hotels);
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'hotels_found'=>$hotel_count);
        
        $search_log = new SearchLog();
        $search_log->user_id = $user_id;
        $search_log->search_date = date('Y-m-d H:i:s');
        $search_log->search_type = 'CITY';
        $search_log->search_id = $city_id;
        $search_log->search_value = $city_name;
        $search_log->save();

        return response()->json($msg);
    }
    //================================================================================================================
    public function getQueryResult($user_id, $query_text)
    {
        $query_text = strtolower(urldecode($query_text));
        $hotels_data = array();
        
        //Place List
        
        $group_hotels = DB::table('kernel.city_table')
            ->Where(DB::raw('lower(kernel.city_table.city_name)'), 'like',"$query_text%")
            ->select(DB::raw('DISTINCT kernel.city_table.city_id,city_table.city_name'))
            ->take(20)
            ->get();
        

        
        foreach($group_hotels as $hotel_info)
        {
            $city_record['city_id'] = $hotel_info->city_id;
            $city_record['city_name'] = $hotel_info->city_name;
            $city_record['type'] = 'CITY';
            $hotels_data[] = $city_record;
        }

        //Hotel List
       
        $group_hotels = DB::table('kernel.hotels_table')
            ->Where('kernel.hotels_table.status', 1)
            ->Where(DB::raw('lower(kernel.hotels_table.hotel_name)'), 'like',"%$query_text%")
            ->select('kernel.hotels_table.hotel_id','hotel_name')
            ->take(20)
            ->get();
        

        
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['type'] = 'HOTEL';
            $hotels_data[] = $hotel_record;
        }

        

        //area List
        
        // $group_hotels = DB::table('kernel.hotels_table')
        // ->Where('kernel.hotels_table.status', 1)
        // ->Where('kernel.hotels_table.hotel_address', 'like',"%$query_text%")
        // ->select(DB::raw('DISTINCT kernel.hotels_table.hotel_address'))
        // ->take(20)
        // ->get();
        $group_hotels = [];
        
 
        
        foreach($group_hotels as $hotel_info)
        {
            $area_record['city_id'] = 1;
            $area_record['city_name'] = $hotel_info->hotel_address;
            $area_record['type'] = 'AREA';
            $hotels_data[] = $area_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data);
        return response()->json($msg);

    }
    //================================================================================================================
    public function getHotelDetails(Request $request)
    {
        $hotel_id = (int)$request['hotel_id'];
        $user_id = $request['user_id'];

        $hotel_data = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.hotel_id', $hotel_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','city_table.city_name','hotels_table.hotel_description','hotels_table.company_id','hotels_table.hotel_address',
            'hotels_table.latitude','hotels_table.longitude','hotel_policy','child_policy','cancel_policy','terms_and_cond',
            'facility','subdomain_name','exterior_image')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'),
            'city_table.city_name','hotels_table.hotel_description','hotels_table.company_id','hotels_table.hotel_address',
            'hotels_table.latitude','hotels_table.longitude','hotel_policy','child_policy','cancel_policy','terms_and_cond',
            'facility','subdomain_name','exterior_image')
            ->first();

        $hotel_aminities = DB::table('kernel.hotel_amenities')
        ->join('kernel.amenity_categories', 'hotel_amenities.category_id', '=','amenity_categories.category_id')
        ->Where('kernel.amenity_categories.is_hotel', 1)
        ->select('kernel.hotel_amenities.hotel_amenities_id','hotel_amenities_name')
        ->get();
        
        $images = $hotel_data->exterior_image;
        $arr_images = explode(',',$images);
        $hotel_images = DB::table('kernel.image_table')
        ->WhereIn('image_id', $arr_images)
        ->select('image_name')
        ->get();
        
        $str_images = array();
        foreach($hotel_images as $hotel_image)
        {
            $str_images[] = env('S3_IMAGE_PATH').$hotel_image->image_name;
        }
        
        $facilities = array();

        $arr_facility = explode(',',$hotel_data->facility);
        foreach($arr_facility as $facility_id)
        {
            foreach($hotel_aminities as $hotel_aminity)
            {
                if($hotel_aminity->hotel_amenities_id == $facility_id)
                {
                    $facilities[] = $hotel_aminity->hotel_amenities_name;
                }
            }
        }

        
        $company_id = $hotel_data->company_id;
        $hotel_record['hotel_id'] = $hotel_data->hotel_id;
        $hotel_record['hotel_name'] = $hotel_data->hotel_name;
        $hotel_record['star'] = $hotel_data->star_of_property;
        if(isset($hotel_images[0]))
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_images[0]->image_name;
        else
            $hotel_record['image'] = '';
        $hotel_record['starting_price'] = $hotel_data->starting_price;
        $hotel_record['city_name'] = $hotel_data->city_name;
        $hotel_record['address'] = $hotel_data->hotel_address;
        $hotel_record['latitude'] = $hotel_data->latitude;
        $hotel_record['longitude'] = $hotel_data->longitude;
        $hotel_record['hotel_description'] = $hotel_data->hotel_description;
        $hotel_record['hotel_policy'] = $hotel_data->hotel_policy;
        $hotel_record['child_policy'] = $hotel_data->child_policy;
        $hotel_record['cancel_policy'] = $hotel_data->cancel_policy;
        $hotel_record['terms_and_cond'] = $hotel_data->terms_and_cond;
        $hotel_record['facility'] = $facilities;
        $hotel_record['be_url'] = $hotel_data->subdomain_name;
        $hotel_record['max_guest'] = 3;
        
        $conditions=array('company_id'=>$company_id);
        $info=CompanyDetails::select('banner','logo','home_url','api_key')
            ->where($conditions)->first();
        $info->logo =$this->getImages(array($info->logo));
        $info->logo = $info->logo[0]->image_name;
        $api_key = $info->api_key;

        $info->logo = $info->logo;
        $info->banner =$this->getBannerImages($info->banner);
        $info->banner = $info->banner;
        foreach($info->banner as $key => $value){
            $info->banner[$key]->image_name =  $value->image_name;
        }

        $hotel_record['banners'] = $info->banner;
        $hotel_record['api_key'] = $api_key;
        $hotel_record['images'] = $str_images;

        $today = date('Y-m-d');
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://be.bookingjini.com/extranetv4/min-hotel-price',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "hotel_id":['.$hotel_id.'],
            "from_date":"'.$today.'"
        }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);
        $data = $response['data'];

        $hotel_record['starting_price'] = $data[0]['minimum_rates'];

        $search_log = new SearchLog();
        $search_log->user_id = $user_id;
        $search_log->search_date = date('Y-m-d H:i:s');
        $search_log->search_type = 'HOTEL';
        $search_log->search_id = $hotel_id;
        $search_log->search_value = $hotel_record['hotel_name'];
        $search_log->save();

        
        $msg = array('status' => 1,'message'=>'Hotels List','hotel_data' => $hotel_record);
        return response()->json($msg);

    }
    //================================================================================================
    public function getImages($imgs)
    {
        $images=ImageTable::whereIn('image_id', $imgs)
            ->select('image_name')
            ->get();
        if(sizeof($images)>0)
        {
            return $images;
        }
        else
        {
            $images=ImageTable::where('image_id', 3)
                ->select('image_name')
                ->get();
            return $images;
        }
    }
    //============================================================================================================================
    public function getBannerImages($imgs)
    {
        $banner_ids = explode(",", $imgs);
        $images=ImageTable::whereIn('image_id',  $banner_ids)
                ->select('image_name')
                ->get();
        if(sizeof($images)!=0)
        {
            return $images;
        }
        else
        {
            $images=ImageTable::where('image_id',  2)
            ->select('image_name')
            ->get();
            return $images;
        }
    }
    //============================================================================================================================
    public function recentSearch($user_id)
    {
        $recent_searches = SearchLog::where('user_id', $user_id)
        ->orderBy('search_date','desc')
        ->distinct()
        ->get(['search_type','search_id','search_value'])
        ->take(10);
        $search_list = array();
        foreach($recent_searches as $recent_search)
        {
            $search_record = null;
            if($recent_search['search_type'] == 'CITY')
            {
                $search_record['city_id'] = $recent_search['search_id'];
                $search_record['city_name'] = $recent_search['search_value'];
                $search_record['type'] = $recent_search['search_type'];
            }
            else if($recent_search['search_type'] == 'HOTEL')
            {
                $search_record['hotel_id'] = $recent_search['search_id'];
                $search_record['hotel_name'] = $recent_search['search_value'];
                $search_record['type'] = $recent_search['search_type'];
            }
            
            $search_list[] = $search_record;
        }
        $res = array('status'=>1,'search_list'=>$search_list);
        return response()->json($res);
    }
    //============================================================================================================================
    
}
