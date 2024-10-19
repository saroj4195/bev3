<?php
namespace App\Http\Controllers\Extranetv4\crs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Invoice;
use App\User;
Use App\Booking;
use App\CrsReservation;//class name from model
use App\HotelInformation;
use DB;
use App\Http\Controllers\Extranetv4\UpdateInventoryService;
use App\Http\Controllers\Extranetv4\crs\ManageCrsRatePlanController;
use App\Http\Controllers\Extranetv4\InventoryService;
use App\Http\Controllers\Extranetv4\IpAddressService;
//use App\Http\Controllers\IdsController;
use App\OnlineTransactionDetail;
use App\MasterRoomType;//class name from model
use App\AgentCredit;//class name from model
use App\MasterHotelRatePlan;//class name from model\
use App\RoomRateSettings;
use App\AdminUser;
use App\ImageTable;
use App\CompanyDetails;
use App\CmOtaDetails;
use App\Http\Controllers\Controller;
use Rap2hpoutre\FastExcel\FastExcel;
use App\PartnerDetails;

//create a new class OfflineBookingController
class CrsPartnerImportController extends Controller
{
   
    public function partnerImport(Request $request)
    {
        // Validate the request for file
        $validator = Validator::make($request->all(), [
            'import_file' => 'required|file|mimes:xlsx,csv'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first()
            ]);
        }
    
        if ($request->hasFile('import_file')) {
            $file = $request->file('import_file');
            $partnersData = (new FastExcel)->import($file);
    
            foreach ($partnersData as $partner) {
                // Validate each partner's data
                $partnerValidator = Validator::make($partner, [
                    'hotel_id' => 'required',
                    'partner_name' => 'required',
                    'partner_type' => 'required',
                    // 'contact_no' => 'required',
                    'email_id' => 'required',
                ]);
    
                if ($partnerValidator->fails()) {
                    return response()->json([
                        'status' => 0,
                        'message' => $partnerValidator->errors()->first()
                    ]);
                }
                
                $email = filter_var($partner['email_id'], FILTER_VALIDATE_EMAIL) ? $partner['email_id'] : "test@gmail.com";

                $alternate_contact_no_array = json_decode($partner['alternate_contact_no'], true);
                $alternate_email_array = json_decode($partner['alternate_email_id'], true);
    
                // Additional validation for alternate contact numbers and emails
                if (is_array($alternate_contact_no_array)) {
                    foreach ($alternate_contact_no_array as $contact) {
                        if (!preg_match('/^[0-9]{10}$/', $contact)) {
                            return response()->json([
                                'status' => 0,
                                'message' => 'Invalid alternate contact number format'
                            ]);
                        }
                    }
                }
    
                if (is_array($alternate_email_array)) {
                    foreach ($alternate_email_array as $email) {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            return response()->json([
                                'status' => 0,
                                'message' => 'Invalid alternate email format'
                            ]);
                        }
                    }
                }
    
                $partner_data = [
                    'hotel_id' => $partner['hotel_id'],
                    'partner_name' => $partner['partner_name'],
                    'company_name' => $partner['partner_company'],
                    'partner_type' => $partner['partner_type'],
                    'contact_no' => $partner['contact_no'],
                    'email_id' => $email,
                    'country' => $partner['country'],
                    'state' => $partner['state'],
                    'city' => $partner['city'],
                    'address' => $partner['address'],
                    'pin' => $partner['pin'],
                    'contact_person' => $partner['contact_person'],
                    'desgination' => $partner['designation'],
                    'gstin' => $partner['gstin'],
                    'pan' => $partner['pan'],
                    'alternate_contact_no' => $alternate_contact_no_array,
                    'alternate_email_id' => $alternate_email_array,
                    'website' =>isset($partner['website']) ? $partner['website'] : '',
                    
                ];
    
                $insert_data = PartnerDetails::insert($partner_data);
            }
    
            if ($insert_data) {
                return response()->json(['status' => 1, 'message' => 'Partner details uploaded']);
            } else {
                return response()->json(['status' => 0, 'message' => 'Partner details upload failed']);
            }
        } else {
            return response()->json(['status' => 0, 'message' => 'Please provide a file']);
        }
    }
    
}