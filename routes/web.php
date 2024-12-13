<?php
use App\Jobs\TestJob;
use App\Jobs\BucketJob;
use Illuminate\Foundation\Application;
// use Carbon\Carbon;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    // return $router->app->version();

    return "dibya";
});
//Hotel User Registration Route
$router->post('/hotel_users/register', ['uses' => 'CompanyRegistrationController@registerHotelAdmin']);
$router->put('/hotel_users/register',['uses'=>'HotelUserController@activateUser']);
$router->get('/hotel_users/register/resend/{email}', ['uses' => 'HotelUserController@resendEmail']);

//Hotel User Login Route
$router->post('/admin/auth', ['uses' => 'AdminAuthController@adminLogin']);
$router->post('/forgot-password', ['uses' => 'AdminAuthController@forgotPasswordAdmin']);
$router->get('/verify_user', ['uses' => 'AdminAuthController@verifyUser']);

$router->post('/user/auth', ['uses' => 'PublicUserController@login']);
$router->post('/user/register', ['uses' => 'PublicUserController@register']);


//Hotel user authenticated routes
$router->group(['prefix' => 'admin', 'middleware' => 'jwt.auth'], function($router) {
    $router->get('/getInfo',['uses'=>'AdminAuthController@getUsers']);
    $router->post('/change_password', ['uses' => 'AdminAuthController@changePassword']);
    $router->post('/check_password_admin', ['uses' => 'AdminAuthController@checkCurrentPassword']);
});
$router->post('/admin/change_password_admin', ['uses' => 'AdminAuthController@changePasswordAdmin']);
//Hotel user Add/Update/Delete Hotel Property
$router->group(['prefix' => 'hotel_admin', 'middleware' => 'jwt.auth'], function($router) {
    $router->post('/add_new_property',['uses'=>'AddHotelPropertyController@addNewHotelBrand']);
    $router->post('/update_property/{uuid}',['uses'=>'AddHotelPropertyController@updateHotelBrand']);
    $router->delete('delete_property/{uuid}',['uses'=>'AddHotelPropertyController@deleteHotelInfo']);
    $router->delete('disable_property/{uuid}',['uses'=>'AddHotelPropertyController@disableHotelInfo']);
    $router->get('/get_all_hotels',['uses'=>'AddHotelPropertyController@getAllHotelData']);
    $router->get('/get_all_running_hotels',['uses'=>'AddHotelPropertyController@getAllRunningHotelData']);
    $router->get('/get_all_deleted_hotels',['uses'=>'AddHotelPropertyController@getAllDeletedHotelData']);
    $router->get('/get_all_disabled_hotels',['uses'=>'AddHotelPropertyController@getAllDisabledHotelData']);
    $router->get('/get_all_hotels_by_country/{country_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryId']);
    $router->get('/get_all_hotels_by_country_state/{country_id}/{state_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateId']);
    $router->get('/get_all_hotels_by_country_state_city/{country_id}/{state_id}/{city_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateAndCityId']);
    $router->get('/get_all_hotels_by_id/{hotel_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByid']);
    $router->get('/get_all_hotels_by_name/{name}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByName']);
    $router->get('/get_all_hotels_by_group/{group_uuid}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByGroup']);
    $router->get('/get_all_hotels_by_company/{comp_hash}/{company_id}',['uses'=>'AddHotelPropertyController@getAllHotelsDataByCompany']);
    $router->get('/get_all_hotels_by_company_details/{comp_hash}/{commpany_id}/{auth_from}',['uses'=>'AddHotelPropertyController@getAllHotelsDataByCompanyDetails']);

    $router->post('/exterior',['uses'=>'AddHotelPropertyController@updateExterior']);
    $router->post('/interior',['uses'=>'AddHotelPropertyController@updateInterior']);

    $router->get('/get_interior_images/{hotel_id}',['uses'=>'AddHotelPropertyController@getInteriorImages']);
    $router->get('/get_hotel_list/{company_id}',['uses'=>'AddHotelPropertyController@getHotelList']);

});

// $router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}',['uses'=>'AddHotelPropertyController@getAllHotelsByCompany']);
// $router->get('/hotel_admin/get_all_hotel_by_id/{hotel_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByid']);

//HOtel Bank Details
$router->group(['prefix' => 'hotel_bank_account_details','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'HotelBankAccountDetailsController@addNew']);
    $router->post('/update',['uses'=>'HotelBankAccountDetailsController@updateBankAccount']);
    $router->delete('/{id}',['uses'=>'HotelBankAccountDetailsController@deleteBankAccount']);
    $router->get('/get/{hotel_id}',['uses'=>'HotelBankAccountDetailsController@getBankAccountDetails']);
});

//Hotel Currencies Routes
    $router->group(['prefix' => 'currencies','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CurrenciesController@addNewCurrencies']);
    $router->post('/update/{id}',['uses'=>'CurrenciesController@updateCurrencies']);
    $router->delete('/{id}',['uses'=>'CurrenciesController@deleteCurrencies']);
    $router->get('/all',['uses'=>'CurrenciesController@getAllgetCurrencies']);
    $router->get('/get/{id}',['uses'=>'CurrenciesController@getCurrencies']);
});

//Hotel Finance Related Details Routes
$router->group(['prefix' => 'finance_related','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'FinanceRelatedDetailsController@addNewFinanceDetails']);
    $router->post('/update/{id}',['uses'=>'FinanceRelatedDetailsController@updateFinanceRelatedDetails']);
    $router->get('/all',['uses'=>'FinanceRelatedDetailsController@getAllFinanceRelatedDetails']);
    $router->get('/{id}',['uses'=>'FinanceRelatedDetailsController@getFinanceRelatedSetails']);
    $router->get('/get/{country_id}',['uses'=>'FinanceRelatedDetailsController@getTitlesByCountry']);
    $router->get('/getCountry/{hotel_id}',['uses'=>'AddHotelPropertyController@gethotelCountry']);
});

//Hotel Tax Details Routes
    $router->group(['prefix' => 'tax_details','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'TaxDetailsController@addNewTaxDetails']);
    $router->post('/update',['uses'=>'TaxDetailsController@updateTaxDetails']);
    $router->get('/{hotel_id}',['uses'=>'TaxDetailsController@getTaxDetails']);

});
//Hotel paid service Routes
$router->group(['prefix' => 'paid_services'], function($router) {
    $router->post('/add',['uses'=>'PaidServicesController@addNewPaidService']);
    $router->post('/update/{id}',['uses'=>'PaidServicesController@updatePaidService']);
    $router->get('/{paid_service_id}',['uses'=>'PaidServicesController@getHotelPaidService']);
    $router->get('all/{hotel_id}',['uses'=>'PaidServicesController@getHotelPaidServices']);
    $router->delete('delete/{paid_service_id}',['uses'=>'PaidServicesController@DeletePaidServices']);
});
//For Booking Engine
$router->get('/paidServices/{hotel_id}',['uses'=>'PaidServicesController@getHotelPaidServices']);

//Routes By godti Vinod
//Hotel amenities
$router->group(['prefix' => 'hotel_amenities','middleware' => 'jwt.auth'], function($router) {
    $router->post('/update',['uses'=>'HotelAmenitiesController@updateHotelAmenities']);
    $router->get('/all',['uses'=>'HotelAmenitiesController@getAmenities']);
    $router->get('/hotelAmenity/{hotel_id}',['uses'=>'HotelAmenitiesController@getAmenitiesByHotel']);
});
//Hotel Policies   Routes
$router->group(['prefix' => 'hotel_policies','middleware' => 'jwt.auth'], function($router) {
    $router->post('/update',['uses'=>'HotelPoliceDescriptionController@updateHotelPolicies']);
    $router->get('/{hotel_id}',['uses'=>'HotelPoliceDescriptionController@getHotelpolicies']);
   });
//Hotel cancellation Policy
$router->group(['prefix' => 'cancellation_policy','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'HotelCancellationController@addNewCancellationPolicies']);
    $router->post('/update/{id}',['uses'=>'HotelCancellationController@updateCancellationPolicy']);
    $router->get('/{id}',['uses'=>'HotelCancellationController@getHotelCancellationPolicy']);
    $router->get('/all/{hotel_id}',['uses'=>'HotelCancellationController@GetAllCancellationPolicy']);
    $router->delete('/delete/{cancel_policy_id}',['uses'=>'HotelCancellationController@DeleteCancellationPolicy']);
    //$router->get('/cancellation_policies/{room_type_id}',['uses'=>'HotelCancellationController@GetRoomTypeName']);
});
//Hotel other Information
$router->group(['prefix' => 'hotel_other_information','middleware' => 'jwt.auth'], function($router) {
    $router->post('/update',['uses'=>'HotelOtherInformationController@updateHotelOtherInformation']);
    $router->get('/{hotel_id}',['uses'=>'HotelOtherInformationController@getHotelOtherInformation']);
});
//Hotel child Policy
$router->group(['prefix' => 'child_policy','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'HotelChildPlicyController@addNewChildPolicy']);
    $router->post('/update',['uses'=>'HotelChildPlicyController@updateChildPolicy']);
    $router->get('/{hotel_id}',['uses'=>'HotelChildPlicyController@getChildPolicy']);

});
//Hotel Rate Plan Details Routes
$router->group(['prefix' => 'master_rate_plan','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'MasterRatePlancontroller@addNew']);
    $router->post('/update/{rate_plan_id}',['uses'=>'MasterRatePlancontroller@UpdateMasterRatePlan']);
    $router->delete('/{rate_plan_id}',['uses'=>'MasterRatePlancontroller@DeleteMasteReatePlan']);
    $router->get('/all/{hotel_id}',['uses'=>'MasterRatePlancontroller@GetAllHotelRatePlan']);
    $router->get('/{rate_plan_id}',['uses'=>'MasterRatePlancontroller@GetHotelRatePlan']);
    $router->get('/rate_plans/{hotel_id}',['uses'=>'MasterRatePlancontroller@GetRatePlans']);
    $router->get('/rate_plan/{rate_plan_id}',['uses'=>'MasterRatePlancontroller@GetRateplan']);
});

//master Hotel Rate Plan Details Routes
$router->group(['prefix' => 'master_hotel_rate_plan','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'MasterHotelRatePlanController@addNew']);
    $router->post('/update/{room_rate_plan_id}',['uses'=>'MasterHotelRatePlanController@UpdateMasterHotelRatePlan']);
    $router->delete('/{room_rate_plan_id}',['uses'=>'MasterHotelRatePlanController@DeleteMasterHotelRatePlan']);
    $router->get('/all/{hotel_id}',['uses'=>'MasterHotelRatePlanController@GetAllMasterHotelRateplan']);
    $router->get('/{room_rate_plan_id}',['uses'=>'MasterHotelRatePlanController@GetMasterHotelRatePlan']);
    $router->get('/rate_plan_by_room_type/{room_type_id}',['uses'=>'MasterHotelRatePlanController@GetRatePlanByRoomType']);
    $router->get('/room_rate_plan/{hotel_id}',['uses'=>'MasterHotelRatePlanController@GetRoomRatePlan']);
    $router->post('/update-status-for-be',['uses'=>'MasterHotelRatePlanController@modifystatus']);
    $router->get('/room_rate_plan_by_room_type/{hotel_id}/{room_type_id}',['uses'=>'MasterHotelRatePlanController@GetRoomRatePlanByRoomType']);
});
//booking Routes
$router->group(['prefix' => 'booking'], function($router) {
    $router->post('/all/{hotel_id}',['uses'=>'ManageBookingController@GetAllBooking']);
});
//cancellation booking Routes
$router->group(['prefix' => 'cancellation_booking'], function($router) {
    $router->post('/all/{hotel_id}',['uses'=>'ManageCancellationController@GetAllCancellationBooking']);
});

//packages Routes
$router->group(['prefix' => 'packages','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'PackagesController@addNewPackages']);
    $router->post('/update/{package_id}',['uses'=>'PackagesController@UpdatePackages']);
    $router->delete('/{package_id}',['uses'=>'PackagesController@DeletePackages']);
    $router->get('/all/{hotel_id}',['uses'=>'PackagesController@GetAllPackages']);
    $router->get('/{package_id}',['uses'=>'PackagesController@GetPackages']);
    $router->get('/get_packages_images/{package_id}',['uses'=>'PackagesController@getPckagesImages']);
    $router->post('delete',['uses'=>'PackagesController@deleteImage']);
});
$router->get('/packages/get_packages_images/{package_id}',['uses'=>'PackagesController@getPckagesImages']);

// quick payment Routes
    $router->group(['prefix' => 'quick_payment','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'QuickPaymentLinkController@addQuickPayment']);
    $router->get('/all/{hotel_id}',['uses'=>'QuickPaymentLinkController@GetAllQuickPayment']);
    $router->get('/check/{payment_link_id}',['uses'=>'QuickPaymentLinkController@CheckQuickPayment']);
    $router->get('/resend-email/{payment_link_id}/{txn_id}',['uses'=>'QuickPaymentLinkController@resendEmail']);
    $router->get('/get_quickpayment_bookings/{id}/{hotel_id}',['uses'=>'QuickPaymentLinkController@getQuickPaymentBookingDetails']);
    $router->post('/get-room-rate-details',['uses'=>'QuickPaymentLinkController@getRoomRateDetails']);
});
$router->post('/booking-engine-payment-check',['uses'=>'QuickPaymentLinkController@beBookingPaymentStatus']);
// Image Upload  Routes
$router->group(['prefix' => 'upload'], function($router) {
    $router->post('/{hotel_id}',['uses'=>'ImageUploadController@imgageToUpload']);
});
$router->get('/hotel_admin/get_exterior_images/{hotel_id}',['uses'=>'AddHotelPropertyController@getExteriorImages']);
$router->post('/deleteImage',['uses'=>'ImageUploadController@deleteImage']);
$router->get('/getImages/{hotel_id}',['uses'=>'ImageUploadController@getImages']);

//Room type Routes
$router->group(['prefix' => 'hotel_master_room_type','middleware' => 'jwt.auth'], function($router) {
    $router->get('/all/{hotel_id}',['uses'=>'MasterRoomTypeController@getAllRoomTypes']);
    $router->get('/{room_type_id}',['uses'=>'MasterRoomTypeController@getHotelroomtype']);
    $router->post('add',['uses'=>'MasterRoomTypeController@addNewRoomType']);
    $router->post('update/{room_type_id}',['uses'=>'MasterRoomTypeController@updatemasterroomtype']);
    $router->delete('delete/{room_type_id}',['uses'=>'MasterRoomTypeController@deletemasterroomtype']);
    $router->post('delete',['uses'=>'MasterRoomTypeController@deleteImage']);
    $router->get('/room_types/{hotel_id}',['uses'=>'MasterRoomTypeController@GetRoomTypes']);
    $router->get('/room_type/{room_type_id}',['uses'=>'MasterRoomTypeController@GetRoomType']);
    $router->get('/get_rack_price/{room_type_id}',['uses'=>'MasterRoomTypeController@getHotelRackPrice']);
    $router->get('/get_max_people/{room_type_id}',['uses'=>'MasterRoomTypeController@getMaxPeople']);
    $router->post('update_amen/{room_type_id}',['uses'=>'MasterRoomTypeController@updateAmenities']);
    $router->post('/airbnb-details-add',['uses'=>'AirbnbController@addAirBnbDetails']);
    $router->post('/airbnb-details-update/{airbnb_details_id}',['uses'=>'AirbnbController@updateAirBnbDetails']);
    $router->get('/airbnb-data/{hotel_id}/{room_type_id}',['uses'=>'AirbnbController@getAirbnbData']);
    $router->get('/airbnb-ready-review/{hotel_id}/{room_type_id}',['uses'=>'AirbnbController@updateReviewStatus']);
    $router->get('/getairbnb-instant-booking/{room_type_id}/{hotel_id}',['uses'=>'AirbnbController@getAirbnbMaxdaystatus']);
    $router->get('/getairbnb-instant-booking/{airbnb_status}/{room_type_id}/{hotel_id}',['uses'=>'AirbnbController@airbnbInstantBooking']);
    $router->post('/listing_notification/{hotel_id}/{room_type_id}',['uses'=>'MasterRoomTypeController@updateNotification']);
});

$router->get('hotel_master_room_type/get_room_images/{room_type_id}',['uses'=>'MasterRoomTypeController@getroomtypeImages']);
$router->get('room_type_forbe/room_types/{hotel_id}',['uses'=>'MasterRoomTypeController@GetRoomTypes']);


//coupons Routes
$router->group(['prefix' => 'coupons','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CouponsController@addNewCoupons']);
    $router->post('/update/{coupon_id}',['uses'=>'CouponsController@Updatecoupons']);
    $router->delete('/{coupon_id}',['uses'=>'CouponsController@DeleteCoupons']);
    $router->get('/all',['uses'=>'CouponsController@GetAllCoupons']);
    $router->get('/{coupon_id}',['uses'=>'CouponsController@GetCoupons']);
    $router->get('/get/{hotel_id}',['uses'=>'CouponsController@GetCouponsByHotel']);


    $router->post('/add-new',['uses'=>'CouponsController@addNewCouponsNew']);
});
//promotional popup Routes
$router->group(['prefix' => 'promotional_popup','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'PromotionalPopupController@addNewPromo']);
    $router->post('/update/{coupon_id}',['uses'=>'PromotionalPopupController@UpdatePromo']);
    $router->delete('/{promo_id}',['uses'=>'PromotionalPopupController@DeletePromo']);
    $router->get('/all',['uses'=>'PromotionalPopupController@GetAllPromo']);
    $router->get('/{coupon_id}',['uses'=>'PromotionalPopupController@GetPromo']);
    $router->get('/get/{hotel_id}',['uses'=>'PromotionalPopupController@GetPromoByHotel']);
});
// offline booking Routes
$router->group(['prefix' => 'offline_booking','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'OfflineBookingController@addNewOfflineBooking']);

    $router->get('/{user_id}',['uses'=>'OfflineBookingController@GetOfflineBooking']);
    $router->get('/all/{hotel_id}/{type}/{from_date}/{to_date}',['uses'=>'OfflineBookingController@GetAllOfflineBooking']);
});
$router->get('/booking/all/{hotel_id}/{type}/{from_date}/{to_date}',['uses'=>'ManageBookingController@GetAllBooking']);
$router->get('/booking/one/{hotel_id}/{type}/{from_date}/{to_date}/{invoice_id}',['uses'=>'ManageBookingController@GetOneBooking']);
$router->get('/booking/sp-invoice/{hotel_id}/{invoice_id}',['uses'=>'ManageBookingController@GetSpInvoiceBooking']);
//CRM Routes
$router->group(['prefix' => 'crm_leads','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CrmLeadsController@addNewcrmleads']);
    $router->post('/update/{contact_details_id}',['uses'=>'CrmLeadsController@UpdateCrmLeads']);
    $router->get('/{contact_details_id}',['uses'=>'CrmLeadsController@GetCrmLeads']);
    $router->get('/all/{hotel_id}',['uses'=>'CrmLeadsController@GetAllCrmLeads']);
});
// follow up Routes
$router->group(['prefix' => 'follow_up','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'followUpController@addNewFollowUp']);
    $router->get('/all/{client_id}',['uses'=>'followUpController@GetAllFollowUp']);
});
//manage user routes
$router->group(['prefix' => 'manage_user','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'ManageUserController@addNewUsers']);
    $router->post('/update/{admin_id}',['uses'=>'ManageUserController@UpdateUsers']);
    $router->delete('/{admin_id}',['uses'=>'ManageUserController@DeleteUsers']);
    $router->get('/{admin_id}',['uses'=>'ManageUserController@GetUsers']);
    $router->get('/all/{company_id}',['uses'=>'ManageUserController@GetAllUsers']);
    $router->get('/external_users/{company_id}/{hotel_id}',['uses'=>'ManageUserController@GetExternalUsers']);
    $router->get('/agent/{company_id}/{hotel_id}',['uses'=>'ManageUserController@GetAgentUsers']);
});

//CM ota Details  Routes
$router->group(['prefix' => 'cm_ota_details','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CmOtaDetailsController@addNewCmHotel']);
    $router->post('/update/{ota_id}',['uses'=>'CmOtaDetailsController@updateCmHotel']);
    $router->delete('/{hotel_id}/{ota_id}',['uses'=>'CmOtaDetailsController@deleteCmHotel']);
    //$router->get('/all',['uses'=>'CmOtaDetailsController@getAllCmHotel']);
    $router->get('/{ota_id}',['uses'=>'CmOtaDetailsController@getCmHotel']);
    $router->get('/sync/{ota_id}',['uses'=>'CmOtaDetailsController@multipleFunction']);
    $router->get('/get/{hotel_id}',['uses'=>'CmOtaDetailsController@getAllCmHotel']);
    $router->get('/toggle/{hotel_id}/{ota_id}/{is_active}',['uses'=>'CmOtaDetailsController@toggle']);

 });
 //CM ota room type sync  Routes
     $router->group(['prefix' => 'cm_ota_roomtype_sync','middleware' => 'jwt.auth'], function($router) {
     $router->post('/add',['uses'=>'CmOtaSyncController@addNewCmOtaSync']);
     $router->post('/update/{id}',['uses'=>'CmOtaSyncController@updateCmOtaSync']);
     $router->delete('/{id}',['uses'=>'CmOtaSyncController@deleteCmOtaSync']);
     $router->get('/ota_room_types/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@otaRoomTypes']);
     $router->get('/ota_sync_room_types/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncRoomTypes']);
     $router->get('/ota_sync_data/{sync_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncById']);
     $router->get('/ota_rate_plan/{hotel_id}/{ota_id}/{ota_room_type_id}',['uses'=>'CmOtaSyncController@fetchOtaRoomRatePlan']);
     $router->get('/ota_sync_rate_plan/{hotel_id}/{ota_id}',['uses'=>'CmOtaSyncController@fetchOtaSyncRoomRatePlan']);
     $router->get('/ota_sync_rate/{sync_id}',['uses'=>'CmOtaSyncController@fetchOtaRatePlanSyncById']);
     $router->get('/ota_room_type/{hotel_id}/{ota_id}/{room_type_id}',['uses'=>'CmOtaSyncController@fetchOtaRoomType']);
     $router->get('/all_ota/{hotel_id}',['uses'=>'CmOtaSyncController@getAllSyncRoomsData']);
     $router->get('/all_ota_rates/{hotel_id}',['uses'=>'CmOtaSyncController@getAllSyncRoomRateData']);
    });

 //CM ota  rate plan sync  Routes
    $router->group(['prefix' => 'cm_ota_rateplan_sync','middleware' => 'jwt.auth'], function($router) {
    $router->post('/add',['uses'=>'CmOtaSyncController@addNewCmOtaRatePlanSync']);
    $router->post('/update/{id}',['uses'=>'CmOtaSyncController@updateCmOtaRatePlanSync']);
    $router->delete('/{id}',['uses'=>'CmOtaSyncController@deleteCmOtaRatePlanSync']);
    });
    //Ota and BE Inventory update
    $router->post('/hotel_inventory_update',['uses'=>'InventoryController@inventoryUpdate']);
    //Ota and BE Rates Update
    $router->post('/hotel_room_rate_update',['uses'=>'RoomRateController@roomRateUpdate']);
    /*================OTA BBookings==========================*/
    $router->group(['prefix' => 'ota_bookings'], function($router) {
        $router->get('/get/{from_date}/{to_date}/{date_type}/{ota}/{booking_status}/{hotel_id}/{booking_id}',['uses'=>'OtaBookingController@getOtaBookingsDateWise']);
    });

    //Company Profile updation routes
    $router->group(['prefix' => 'company_profile','middleware' => 'jwt.auth'], function($router) {
        $router->post('/booking_page/{company_id}',['uses'=>'BeregistrationController@updateBookingPageDetails']);
        // $router->post('/add',['uses'=>'CompanyRegistrationController@addNew']);
        // $router->post('/update',['uses'=>'CompanyRegistrationController@updateProfile']);
        // $router->get('/{company_id}',['uses'=>'CompanyRegistrationController@getCompanyProfile']);
        //  $router->post('/booking_page/{company_id}',['uses'=>'CompanyRegistrationController@updateBookingPageDetails']);
        // $router->get('/get/{company_id}',['uses'=>'CompanyRegistrationController@getCompanyDetails']);
    });
    $router->get('/company_profile/get-logo/{company_id}',['uses'=>'CompanyRegistrationController@getCompanyLogo']);
    $router->post('company_profile/delete',['uses'=>'CompanyRegistrationController@deleteImage']);

    ///Bookingjini PMS Routes
    $router->group(['prefix' => 'pms','middleware' => 'jwt.auth'], function($router) {
        $router->get('hotel-details/{key}/{hotel_id}',['uses'=>'PmsController@hotelDetails']);
        $router->post('booking-details',['uses'=>'PmsController@bookingDetails']);
        $router->post('update-inventory',['uses'=>'PmsController@updateInventory']);
    });
    //IDS PMS Routes
    $router->group(['prefix' => 'ids'], function($router) {
            $router->post('update-inventory',['uses'=>'IdsController@updateInventory']);
            $router->get('get-response',['uses'=>'IdsController@getResponse']);
            $router->get('bookings',['uses'=>'IdsController@execute']);
    });
     //rms routes
     $router->group(['prefix' => 'rms'], function($router) {
        $router->post('/update-inventory',['uses'=>'RmsController@updateInventory']);
        $router->post('/getroomtype',['uses'=>'RmsController@getRoomType']);
        $router->post('/getrateplan',['uses'=>'RmsController@getRatePlan']);
        $router->post('/update-rates',['uses'=>'RmsController@updateRates']);
        $router->post('/bookings_rules',['uses'=>'RmsController@rmsBookingRules']);
        $router->get('/ota_bookings',['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);
    });
    //pms details
    $router->group(['prefix' => 'pms_details','middleware' => 'jwt.auth'], function($router) {
        $router->post('/add',['uses'=>'PmsDetailsController@addNewCmHotel']);
        $router->post('/update/{ota_id}',['uses'=>'PmsDetailsController@updateCmHotel']);
        $router->get('/sync/{ota_id}',['uses'=>'PmsDetailsController@multipleFunction']);
    });
    //Dashboard Routes
    $router->get('/invoiceAmount/getById/{hotel_id}',['uses'=>'DashBoardController@selectInvoice']);
    $router->group(['prefix'=>'dashboard','middleware' => 'jwt.auth'],function($router){
        $router->get('/getById/{hotel_id}/{from_date}/{to_date}',['uses'=>'DashBoardController@selectInvoice']);
        $router->get('/getAll/{hotel_id}',['uses'=>'DashBoardController@getOtaDetails']);
        $router->get('/gethotelbooking/{hotel_id}',['uses'=>'DashBoardController@getHotelBookings']);
        $router->get('/getAllcheckout/{hotel_id}',['uses'=>'DashBoardController@getOtaDetailsCheckOut']);
        $router->get('/gethotelbookingcheckout/{hotel_id}',['uses'=>'DashBoardController@getHotelBookingsCheckOut']);
        $router->get('/hotelbooking/{invoice_id}',['uses'=>'DashBoardController@hotelBookingCheckInOutInvoice']);
        $router->get('/otabooking/{id}',['uses'=>'DashBoardController@otaBookingCheckInOutid']);
        $router->get('/bookingEngeenHelth/{hotel_id}',['uses'=>'DashBoardController@percentageCount']);
        $router->get('/yearlyhotelbooking/{hotel_id}',['uses'=>'DashBoardController@yearlyHotelBooking']);
        $router->get('/yearlyotabooking/{hotel_id}',['uses'=>'DashBoardController@yearlyOtaBooking']);
 });
    //Unique visitor Route
    $router->group(['prefix'=>'dashboard'],function($router){
        $router->post('/uniqueVisitors/{hotel_id}',['uses'=>'BeDashboardController@uniqueVisitors']);
        $router->get('/uniqueVisitorsDashboard/{company_id}/{from_date}/{to_date}',['uses'=>'DashBoardController@uniqueVisitorsDashboard']);
        $router->get('/uniqueVisitorsWB/{company_id}/{from_date}/{to_date}',['uses'=>'DashBoardController@uniqueVisitorsWB']);
    });
    //Mail-Invoice
    $router->group(['prefix'=>'mailInvoice','middleware' => 'jwt.auth'],function($router){
        $router->get('/details/{hotel_id}',['uses'=>'MailInvoiceController@getInvoiceDetails']);
        $router->post('/mail/{hotel_id}',['uses'=>'MailInvoiceController@sendInvoiceMail']);
    });
    //Logs Routes
    $router->group(['prefix'=>'log-details','middleware' => 'jwt.auth'],function($router){
        $router->get('/inventory/{hotel_id}/{from_date}/{to_date}/{room_type_id}/{selected_be_ota_id}',['uses'=>'LogsController@inventoryDetails']);
        $router->get('/rateplan/{hotel_id}/{from_date}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}',['uses'=>'LogsController@rateplanDetails']);
        $router->get('/booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'LogsController@bookingDetails']);
        $router->get('/session/{hotel_id}/{from_date}/{to_date}',['uses'=>'LogsController@userSession']);
    });
    $router->group(['prefix'=>'log-details','middleware' => 'jwt.auth'],function($router){
        $router->get('/inventory-test/{hotel_id}/{to_date}/{room_type_id}/{selected_be_ota_id}',['uses'=>'LogsControllerTest@inventoryDetails']);
        $router->get('/rateplan-test/{hotel_id}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}',['uses'=>'LogsControllerTest@rateplanDetails']);
        $router->get('/session-test/{hotel_id}/{to_date}',['uses'=>'LogsControllerTest@userSession']);
    });
    //blocking ip
    $router->post('/BlockedClientIp/insert',['uses'=>'BlockController@blockClientIp']);
    $router->get('/BlockedClientIp/get',['uses'=>'BlockController@BlockIpDetails']);
    $router->delete('/BlockedClientIp/delete/{wrong_attempt_id}',['uses'=>'BlockController@unBlockIp']);

    //reporting
    $router->group(['prefix'=>'reporting','middleware' => 'jwt.auth'],function($router){
    $router->get('/details/{hotel_id}/{type}',['uses'=>'ReportingController@bookingDetails']);
    $router->get('/details_dashboard/{hotel_id}/{from_date}/{to_date}',['uses'=>'ReportingController@dashboardBookingDetails']);
    $router->get('/total-earning/{hotel_id}/{type}',['uses'=>'ReportingController@bookingTotalEarning']);
    $router->get('/occupancy/{hotel_id}/{type}',['uses'=>'ReportingController@occupancy']);
    $router->get('/roomtypeSelect/{hotel_id}',['uses'=>'ReportingController@roomType']);
    $router->get('/average/{hotel_id}/{room_type_id}',['uses'=>'ReportingController@average']);
    $router->get('/tvcBooking/{hotel_id}',['uses'=>'ReportingController@tvcBooking']);
    $router->get('/otaSelect/{hotel_id}',['uses'=>'ReportingController@getOtaDetails']);
    $router->get('/cvcBooking/{hotel_id}/{ota_id}',['uses'=>'ReportingController@cvcBooking']);
    $router->get('/comission/{hotel_id}',['uses'=>'ReportingController@comission']);
    $router->post('/inventory/{hotel_id}',['uses'=>'ReportingController@inventory']);
    $router->get('/get-otawise-booking/{ota_id}/{hotel_id}',['uses'=>'ReportingController@getOTAtotalBookings']);
});

//Device Notfication APi
$router->post('/device_info/device_details',['uses'=>'DeviceNotificationController@deviceInformation']);

//Test
$router->get('/test-pushIds',['uses'=>'BookingEngineController@testPushIds']);


//Test airbnb
$router->get('/get_airbnb_token',['uses'=>'MasterRoomTypeController@getAirbnbToken']);

//no show routes
$router->group(['prefix'=>'bookingDotCom','middleware' => 'jwt.auth'],function($router){
    $router->post('/noshow',['uses'=>'BookingdotcomController@noShowPush']);
});

//test the booking voucher
$router->post('/test/test-voucher/',['uses'=>'otacontrollers\GoibiboController@actionIndex']);
$router->get('/test/voucher_mail/{ota_booking_id}/{bucket_booking_status}',['uses'=>'OtaAutoPushUpdateController@mailHandler1']);
$router->post('/test/test-booking-voucher/',['uses'=>'otacontrollers\BookingdotcomController@actionIndex']);
$router->post('/test/test-agoda-voucher/',['uses'=>'otacontrollers\AgodaController@actionIndex']);
$router->post('/test/test-expedia-voucher/',['uses'=>'otacontrollers\ExpediaController@actionIndex']);
$router->post('/test/test-via-voucher/',['uses'=>'otacontrollers\ViadotcomController@actionIndex']);
$router->post('/test/test-cleartrip-voucher/',['uses'=>'otacontrollers\CleartripController@actionIndex']);
$router->post('/test/test-travelguru-voucher/',['uses'=>'otacontrollers\TravelguruController@actionIndex']);
$router->post('/test/test-paytm-voucher/',['uses'=>'otacontrollers\PaytmController@actionIndex']);


//guest details of checkin date
$router->get('/guest/guest-details/',['uses'=>'GuestCheckinNotification@guestInformation']);
$router->get('/booking-data/download/{booking_data}',['uses'=>'BookingDetailsDownloadController@getSearchData']);
//for testing ota bucket controller
$router->get('/get-inv-from-ota',['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);
//for testing inv update from otabookingdatainsert
$router->get('/update-inv-in-be',['uses'=>'otacontrollers\BookingDataInsertationController@updateInvForBe']);

//new reports
$router->group(['prefix'=>'newreports'],function($router){
    $router->get('/number-of-night/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@getRoomNightsByDateRange']);
    $router->get('/total-amount/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@totalRevenueOtaWise']);
    $router->get('/total-bookings/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@numberOfBookings']);
    $router->get('/average-stay/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@averageStay']);
    $router->get('/rate-plan-performance/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@ratePlanPerformance']);
    $router->get('/rate-performance/{hotel_id}/{checkin}/{checkout}',['uses'=>'NewReportController@ratePerformance']);
});

//display api for inv,rate and bookings

$router->group(['prefix'=>'get-data'],function($router){
    $router->post('/number-of-inventory',['uses'=>'InvRateBookingDisplayController@invData']);
    $router->post('/rate_amount',['uses'=>'InvRateBookingDisplayController@rateData']);
});
//derive plan api

$router->group(['prefix'=>'derive-plan'],function($router){
    $router->get('/check-plan/{hotel_id}',['uses'=>'DerivePlanController@checkRoomRatePlan']);
    $router->post('/add-plan',['uses'=>'DerivePlanController@addDetailsOfDerivedPlan']);
    $router->get('/update-derived-plan/{room_rate_plan_id}/{master_status}',['uses'=>'DerivePlanController@updateMasterPlanStatus']);
    $router->get('/get-plan-details/{hotel_id}/{room_type_id}',['uses'=>'DerivePlanController@getRoomTypeRatePlanName']);
    $router->get('/make-normal/{hotel_id}/{room_rate_plan}/{room_type}/{rate_plan}',['uses'=>'DerivePlanController@normalPlan']);
    $router->post('/check_room_occupancy',['uses'=>'DerivePlanController@getRoomOccupancy']);
});

//get api data for website-builder
$router->get('/check-website-status/{company_id}',['uses'=>'AdminAuthController@fetchwebsitestatus']);
$router->get('/get-room-details/{company_id}',['uses'=>'AdminAuthController@retriveRoomDetails']);
$router->post('/fetch-subdomain-name/{company_id}',['uses'=>'AdminAuthController@fetchSubdomain']);
$router->get('/fetch-map-details/{company_id}',['uses'=>'AdminAuthController@getMapDetails']);
$router->get('/get-hotel-menu/{company_id}',['uses'=>'AdminAuthController@getHotelMenuDetails']);
$router->get('/get-hotel-details/{company_id}',['uses'=>'AdminAuthController@getHotelDetails']);
$router->get('/get-hotel-banner/{company_id}',['uses'=>'AdminAuthController@getHotelBanner']);
$router->get('/fetch-hotel-details/{company_id}',['uses'=>'AdminAuthController@fetchDetails']);
$router->post('/fetch-hotel-mailid/{hotel_id}',['uses'=>'AdminAuthController@getHotelMailId']);
$router->post('/fetch-hotel-packages/{hotel_id}/{checkin_date}/{checkout_date}',['uses'=>'AdminAuthController@getHotelPackages']);


$router->get('/test-mail-handler',['uses'=>'OtaAutoPushUpdateController@testmailhandler']);


//sync inventory and rate
$router->group(['prefix'=>'sync-inv-rate'],function($router){
    $router->get('/push-ota',['uses'=>'invrateupdatecontrollers\MasterInvRateUpdateController@syncInvRateDataPushToOTA']);
        $router->get('/notifications/{hotel_id}',['uses'=>'NotificationController@getNotificationDetails']);
    $router->post('/read-status',['uses'=>'NotificationController@updateReadStatus']);
    });
//get all hotel specific details

$router->get('/get-specific-details',['uses'=>'AllHotelDetailsControllers@getHotelSpecificDetails']);

$router->get('/test-hotelogix',['uses'=>'CmOtaBookingPushBucketController@actionBookingbucketengine']);


$router->get('sqs', function () use ($router) {
    // \App\Jobs\TestJob::dispatch();
    // return $router->app->version();
    $job=new TestJob();
    dispatch($job);

});

$router->get('run-bucket', function () use ($router) {
    $job2=new BucketJob();
    dispatch($job2);
    // echo "Processing...";
});


//get BDT currency test
$router->get('/get-BTD',['uses'=>'CurrencyController@getBDT']);

//get hotel name from hotel id for jini-chat-panel
$router->get('/retrive-hotel-name/{hotel_id}',['uses'=>'AdminAuthController@getHotelName']);



$router->group(['prefix'=>'dashboard','middleware' => 'jwt.auth'],function($router){
    $router->get('/crs-booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'CrsDashboardController@dashboardBookingDetails']);

$router->get('/checkin-crs-booking/{hotel_id}',['uses'=>'CrsDashboardController@getHotelBookings']);

$router->get('/checkout-crs-booking/{hotel_id}',['uses'=>'CrsDashboardController@getHotelBookingsCheckOut']);
    $router->get('/select-be-invoice/{hotel_id}/{from_date}/{to_date}',['uses'=>'BeDashboardController@selectInvoice']);

    $router->get('/select-be-visitor/{company_id}/{from_date}/{to_date}',['uses'=>'BeDashboardController@beUniqueVisitors']);

    $router->get('/select-be-room-nights/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeDashboardController@getRoomNightsByDateRange']);

    $router->get('/select-be-revenue/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeDashboardController@totalRevenueOtaWise']);

    $router->get('/select-be-avgstay/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeDashboardController@averageStay']);

    $router->get('/select-be-rateplan/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeDashboardController@ratePlanPerformance']);

    $router->get('/be-booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'BeDashboardController@dashboardBookingDetails']);

    $router->get('/checkin-be-booking/{hotel_id}',['uses'=>'BeDashboardController@getHotelBookings']);

    $router->get('/checkout-be-booking/{hotel_id}',['uses'=>'BeDashboardController@getHotelBookingsCheckOut']);
    
    $router->get('/be-checkinout-invoice/{invoice_id}',['uses'=>'BeDashboardController@hotelBookingCheckInOutInvoice']);

    $router->get('/crs-booking/{hotel_id}/{from_date}/{to_date}',['uses'=>'CrsDashboardController@dashboardBookingDetails']);

    $router->get('/checkin-crs-booking/{hotel_id}',['uses'=>'CrsDashboardController@getCrsBookings']);
    
    $router->get('/checkout-crs-booking/{hotel_id}',['uses'=>'CrsDashboardController@getCrsBookingsCheckOut']);

});

//test Bookingengine
$router->get('/test/{invoice_id}',['uses'=>'BookingEngineController@testbooking']);


//routes for providing the room details to google hotel ads
$router->post('/google-hotel-ads-room-details',['uses'=>'GoogleHotelAdsController@googleHotelAdsHotelData']);
$router->get('/google-hotel-ads-creation/{hotel_id}',['uses'=>'GoogleHotelAdsController@addHotelToGoogleHotelAds']);
$router->get('/google-hotel-ads-deletion/{hotel_id}',['uses'=>'GoogleHotelAdsController@removeHotelFromGoogleHotelAds']);
$router->get('/google-hotel-ads-retrieve',['uses'=>'GoogleHotelAdsController@retrieveGoogleHotelAds']);
$router->get('/google-hotel-ads-ari-update/{hotel_id}',['uses'=>'GoogleHotelAdsController@syncInventoryRateToGoogleHotelAds']);
$router->get('/google-hotel-ads-xml-download',['uses'=>'GoogleHotelAdsController@downloadXML']);


$router->get('/google-hotel-ads-room-type-sync',['uses'=>'GoogleHotelAdsController@roomTypeSync']);


//This route is used by google to update the hotel list
$router->get('/ghc/hotel-list',['uses'=>'GoogleHotelAdsController@ghcHotelList']);

// $router->get('/google-inventory-update/{hotel_id}/{room_type_id}/{no_of_rooms}/{from_date}/{to_date}',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@inventoryUpdateToGoogleAds']);
// $router->get('/google-rate-update/{hotel_id}/{room_type_id}/{rate_plan_id}/{from_date}/{to_date}/{bar_price}',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@rateUpdateToGoogleAds']);
// $router->get('/google-los-update/{hotel_id}/{room_type_id}/{rate_plan_id}/{from_date}/{to_date}/{los}',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@losUpdateToGoogleAds']);


$router->get('/test-multiple-join',['uses'=>'TestController@multiJoin']);


//CRM routes listed below.
$router->get('/customer-details',['uses'=>'TestControllerCRM@getCustomerDetails']);
$router->post('/customer-details',['uses'=>'CRMBookingControllers@getBooking']);

//Booking engine to cm update
$router->get('/be-to-cm-push',['uses'=>'TestController@testCmProcess']);

//bookingjini child age setup
$router->get('/push-data-to-child-setup',['uses'=>'TestController@insertDataIntoAddonCharges']);

$router->get('/get-child-setup/{hotel_id}',['uses'=>'BookingEngineController@getChildages']);


//Added by Jigyans dt : - 20-09-2022
$router->get('/customer-details-from-be',['uses'=>'TestControllerCRM@getCustomerDetailsforBE']);

$router->get('/customer-details-from-cm',['uses'=>'TestControllerCRM@getCustomerDetailsforCM']);

$router->get('/customer-details-from-ja',['uses'=>'TestControllerCRM@getCustomerDetailsforJA']);

$router->get('/customer-details-from-wb',['uses'=>'TestControllerCRM@getCustomerDetailsforWB']);


$router->post('/customer-details-booking-processing-be',['uses'=>'CRMBookingControllers@getBookingBE']);

$router->post('/customer-details-booking-processing-cm',['uses'=>'CRMBookingControllers@getBookingCM']);

$router->get('/customer-details-from-trial',['uses'=>'TestControllerCRM@getCustomerDetailstrial']);

//Added by Jigyans dt : - 24-09-2022
$router->post('/updateStatusForBEfromCRM',['uses'=>'TestControllerCRM@updateStatusForBEfromCRM']);

$router->post('/updateStatusForCMfromCRM',['uses'=>'TestControllerCRM@updateStatusForCMfromCRM']);

$router->post('/updateStatusForJAfromCRM',['uses'=>'TestControllerCRM@updateStatusForJAfromCRM']);

$router->post('/updateStatusForWBfromCRM',['uses'=>'TestControllerCRM@updateStatusForWBfromCRM']);

$router->post('/updateStatusForCMBookingfromCRM',['uses'=>'TestControllerCRM@updateStatusForCMBookingfromCRM']);

$router->post('/updateStatusForBEBookingfromCRM',['uses'=>'TestControllerCRM@updateStatusForBEBookingfromCRM']);


// $router->post('/send-msg',['uses'=>'BookingEngineController@sendWhatsAppBookingNotificationToHoteler']);




