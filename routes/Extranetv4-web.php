<?php
$router->group(['prefix' => 'extranetv4'], function ($router) {
    /* web.php rout start */
    //Hotel User Registration Route
    $router->post('/hotel_users/register', ['uses' => 'Extranetv4\CompanyRegistrationController@registerHotelAdmin']);
    $router->put('/hotel_users/register', ['uses' => 'Extranetv4\HotelUserController@activateUser']);
    $router->get('/hotel_users/register/resend/{email}', ['uses' => 'Extranetv4\HotelUserController@resendEmail']);

    //Hotel User Login Route
    $router->post('/admin/auth', ['uses' => 'Extranetv4\AdminAuthController@adminLogin']);
    $router->post('/forgot-password', ['uses' => 'Extranetv4\AdminAuthController@forgotPasswordAdmin']);
    $router->get('/verify_user', ['uses' => 'Extranetv4\AdminAuthController@verifyUser']);

    $router->post('/user/auth', ['uses' => 'Extranetv4\PublicUserController@login']);
    $router->post('/user/register', ['uses' => 'Extranetv4\PublicUserController@register']);


    //Hotel user authenticated routes
    $router->group(['prefix' => 'admin', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/getInfo', ['uses' => 'Extranetv4\AdminAuthController@getUsers']);
        $router->post('/change_password', ['uses' => 'Extranetv4\AdminAuthController@changePassword']);
        $router->post('/check_password_admin', ['uses' => 'Extranetv4\AdminAuthController@checkCurrentPassword']);
    });
    $router->post('/admin/change_password_admin', ['uses' => 'Extranetv4\AdminAuthController@changePasswordAdmin']);
    //Hotel user Add/Update/Delete Hotel Property
    $router->group(['prefix' => 'hotel_admin', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add_new_property', ['uses' => 'Extranetv4\AddHotelPropertyController@addNewHotelBrand']);
        $router->post('/update_property/{uuid}', ['uses' => 'Extranetv4\AddHotelPropertyController@updateHotelBrand']);
        $router->delete('delete_property/{uuid}', ['uses' => 'Extranetv4\AddHotelPropertyController@deleteHotelInfo']);
        $router->delete('disable_property/{uuid}', ['uses' => 'Extranetv4\AddHotelPropertyController@disableHotelInfo']);
        $router->get('/get_all_hotels', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllHotelData']);
        $router->get('/get_all_running_hotels', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelData']);
        $router->get('/get_all_deleted_hotels', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllDeletedHotelData']);
        $router->get('/get_all_disabled_hotels', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllDisabledHotelData']);
        $router->get('/get_all_hotels_by_country/{country_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByCountryId']);
        $router->get('/get_all_hotels_by_country_state/{country_id}/{state_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateId']);
        $router->get('/get_all_hotels_by_country_state_city/{country_id}/{state_id}/{city_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByCountryAndStateAndCityId']);
        $router->get('/get_all_hotels_by_id/{hotel_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByid']);
        $router->get('/get_all_hotels_by_name/{name}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByName']);
        $router->get('/get_all_hotels_by_group/{group_uuid}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllRunningHotelDataByGroup']);
        $router->get('/get_all_hotels_by_company/{comp_hash}/{company_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllHotelsDataByCompany']);
        $router->get('/get_all_hotels_by_company_details/{comp_hash}/{commpany_id}/{auth_from}', ['uses' => 'Extranetv4\AddHotelPropertyController@getAllHotelsDataByCompanyDetails']);

        $router->post('/exterior', ['uses' => 'Extranetv4\AddHotelPropertyController@updateExterior']);
        $router->post('/interior', ['uses' => 'Extranetv4\AddHotelPropertyController@updateInterior']);

        $router->get('/get_interior_images/{hotel_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getInteriorImages']);
        $router->get('/get_hotel_list/{company_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getHotelList']);
    });

    // $router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}',['uses'=>'AddHotelPropertyController@getAllHotelsByCompany']);
    // $router->get('/hotel_admin/get_all_hotel_by_id/{hotel_id}',['uses'=>'AddHotelPropertyController@getAllRunningHotelDataByid']);

    //HOtel Bank Details
    $router->group(['prefix' => 'hotel_bank_account_details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\HotelBankAccountDetailsController@addNew']);
        $router->post('/update', ['uses' => 'Extranetv4\HotelBankAccountDetailsController@updateBankAccount']);
        $router->delete('/{id}', ['uses' => 'Extranetv4\HotelBankAccountDetailsController@deleteBankAccount']);
        $router->get('/get/{hotel_id}', ['uses' => 'Extranetv4\HotelBankAccountDetailsController@getBankAccountDetails']);
    });

    //Hotel Currencies Routes
    $router->group(['prefix' => 'currencies', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CurrenciesController@addNewCurrencies']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\CurrenciesController@updateCurrencies']);
        $router->delete('/{id}', ['uses' => 'Extranetv4\CurrenciesController@deleteCurrencies']);
        $router->get('/all', ['uses' => 'Extranetv4\CurrenciesController@getAllgetCurrencies']);
        $router->get('/get/{id}', ['uses' => 'Extranetv4\CurrenciesController@getCurrencies']);
    });

    //Hotel Finance Related Details Routes
    $router->group(['prefix' => 'finance_related', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\FinanceRelatedDetailsController@addNewFinanceDetails']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\FinanceRelatedDetailsController@updateFinanceRelatedDetails']);
        $router->get('/all', ['uses' => 'Extranetv4\FinanceRelatedDetailsController@getAllFinanceRelatedDetails']);
        $router->get('/{id}', ['uses' => 'Extranetv4\FinanceRelatedDetailsController@getFinanceRelatedSetails']);
        $router->get('/get/{country_id}', ['uses' => 'Extranetv4\FinanceRelatedDetailsController@getTitlesByCountry']);
        $router->get('/getCountry/{hotel_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@gethotelCountry']);
    });

    //Hotel Tax Details Routes
    $router->group(['prefix' => 'tax_details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\TaxDetailsController@addNewTaxDetails']);
        $router->post('/update', ['uses' => 'Extranetv4\TaxDetailsController@updateTaxDetails']);
        $router->get('/{hotel_id}', ['uses' => 'Extranetv4\TaxDetailsController@getTaxDetails']);
    });
    //Hotel paid service Routes
    $router->group(['prefix' => 'paid_services'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\PaidServicesController@addNewPaidService']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\PaidServicesController@updatePaidService']);
        $router->get('/{paid_service_id}', ['uses' => 'Extranetv4\PaidServicesController@getHotelPaidService']);
        $router->get('all/{hotel_id}', ['uses' => 'Extranetv4\PaidServicesController@getHotelPaidServices']);
        $router->delete('delete/{paid_service_id}', ['uses' => 'Extranetv4\PaidServicesController@DeletePaidServices']);
    });
    //For Booking Engine
    $router->get('/paidServices/{hotel_id}', ['uses' => 'Extranetv4\PaidServicesController@getHotelPaidServices']);

    //Routes By godti Vinod
    //Hotel amenities
    $router->group(['prefix' => 'hotel_amenities', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/update', ['uses' => 'Extranetv4\HotelAmenitiesController@updateHotelAmenities']);
        $router->get('/all', ['uses' => 'Extranetv4\HotelAmenitiesController@getAmenities']);
        $router->get('/hotelAmenity/{hotel_id}', ['uses' => 'Extranetv4\HotelAmenitiesController@getAmenitiesByHotel']);
    });
    //Hotel Policies   Routes
    $router->group(['prefix' => 'hotel_policies', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/update', ['uses' => 'Extranetv4\HotelPoliceDescriptionController@updateHotelPolicies']);
        $router->get('/{hotel_id}', ['uses' => 'Extranetv4\HotelPoliceDescriptionController@getHotelpolicies']);
    });
    //Hotel cancellation Policy
    $router->group(['prefix' => 'cancellation_policy', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\HotelCancellationController@addNewCancellationPolicies']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\HotelCancellationController@updateCancellationPolicy']);
        $router->get('/{id}', ['uses' => 'Extranetv4\HotelCancellationController@getHotelCancellationPolicy']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\HotelCancellationController@GetAllCancellationPolicy']);
        $router->delete('/delete/{cancel_policy_id}', ['uses' => 'Extranetv4\HotelCancellationController@DeleteCancellationPolicy']);
        //$router->get('/cancellation_policies/{room_type_id}',['uses'=>'HotelCancellationController@GetRoomTypeName']);
    });
    //Hotel other Information
    $router->group(['prefix' => 'hotel_other_information', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/update', ['uses' => 'Extranetv4\HotelOtherInformationController@updateHotelOtherInformation']);
        $router->get('/{hotel_id}', ['uses' => 'Extranetv4\HotelOtherInformationController@getHotelOtherInformation']);
    });
    //Hotel child Policy
    $router->group(['prefix' => 'child_policy', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\HotelChildPlicyController@addNewChildPolicy']);
        $router->post('/update', ['uses' => 'Extranetv4\HotelChildPlicyController@updateChildPolicy']);
        $router->get('/{hotel_id}', ['uses' => 'Extranetv4\HotelChildPlicyController@getChildPolicy']);
    });
    //Hotel Rate Plan Details Routes
    $router->group(['prefix' => 'master_rate_plan', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\MasterRatePlancontroller@addNew']);
        $router->post('/update/{rate_plan_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@UpdateMasterRatePlan']);
        $router->delete('/{rate_plan_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@DeleteMasteReatePlan']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@GetAllHotelRatePlan']);
        $router->get('/{rate_plan_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@GetHotelRatePlan']);
        $router->get('/rate_plans/{hotel_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@GetRatePlans']);
        $router->get('/rate_plan/{rate_plan_id}', ['uses' => 'Extranetv4\MasterRatePlancontroller@GetRateplan']);
    });

    //master Hotel Rate Plan Details Routes
    $router->group(['prefix' => 'master_hotel_rate_plan', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\MasterHotelRatePlanController@addNew']);
        $router->post('/update/{room_rate_plan_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@UpdateMasterHotelRatePlan']);
        $router->delete('/{room_rate_plan_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@DeleteMasterHotelRatePlan']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@GetAllMasterHotelRateplan']);
        $router->get('/{room_rate_plan_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@GetMasterHotelRatePlan']);
        $router->get('/rate_plan_by_room_type/{room_type_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@GetRatePlanByRoomType']);
        $router->get('/room_rate_plan/{hotel_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@GetRoomRatePlan']);
        $router->post('/update-status-for-be', ['uses' => 'Extranetv4\MasterHotelRatePlanController@modifystatus']);
        $router->get('/room_rate_plan_by_room_type/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\MasterHotelRatePlanController@GetRoomRatePlanByRoomType']);
    });
    //booking Routes
    $router->group(['prefix' => 'booking'], function ($router) {
        $router->post('/all/{hotel_id}', ['uses' => 'Extranetv4\ManageBookingController@GetAllBooking']);
    });
    //cancellation booking Routes
    $router->group(['prefix' => 'cancellation_booking'], function ($router) {
        $router->post('/all/{hotel_id}', ['uses' => 'Extranetv4\ManageCancellationController@GetAllCancellationBooking']);
    });

    //packages Routes
    $router->group(['prefix' => 'packages', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\PackagesController@addNewPackages']);
        $router->post('/update/{package_id}', ['uses' => 'Extranetv4\PackagesController@UpdatePackages']);
        $router->delete('/{package_id}', ['uses' => 'Extranetv4\PackagesController@DeletePackages']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\PackagesController@GetAllPackages']);
        $router->get('/{package_id}', ['uses' => 'Extranetv4\PackagesController@GetPackages']);
        $router->get('/get_packages_images/{package_id}', ['uses' => 'Extranetv4\PackagesController@getPckagesImages']);
        $router->post('delete', ['uses' => 'Extranetv4\PackagesController@deleteImage']);
    });
    $router->get('/packages/get_packages_images/{package_id}', ['uses' => 'PackagesController@getPckagesImages']);

    // quick payment Routes
    $router->group(['prefix' => 'quick_payment', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\QuickPaymentLinkController@addQuickPayment']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@GetAllQuickPayment']);
        $router->get('/check/{payment_link_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@CheckQuickPayment']);
        $router->get('/resend-email/{payment_link_id}/{txn_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@resendEmail']);
        $router->get('/get_quickpayment_bookings/{id}/{hotel_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@getQuickPaymentBookingDetails']);
        $router->post('/get-room-rate-details', ['uses' => 'Extranetv4\QuickPaymentLinkController@getRoomRateDetails']);
        $router->get('/{type}/{hotel_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@CheckQuickPaymentLinkStatus']);
        $router->get('/{type}/{hotel_id}/{date}', ['uses' => 'Extranetv4\QuickPaymentLinkController@CheckQuickPaymentLinkStatusNew']);
    });


    $router->post('/booking-engine-payment-check', ['uses' => 'Extranetv4\QuickPaymentLinkController@beBookingPaymentStatus']);
    // Image Upload  Routes
    $router->group(['prefix' => 'upload'], function ($router) {
        $router->post('/{hotel_id}', ['uses' => 'Extranetv4\ImageUploadController@imgageToUpload']);
    });
    $router->get('/hotel_admin/get_exterior_images/{hotel_id}', ['uses' => 'Extranetv4\AddHotelPropertyController@getExteriorImages']);
    $router->post('/deleteImage', ['uses' => 'Extranetv4\ImageUploadController@deleteImage']);
    $router->get('/getImages/{hotel_id}', ['uses' => 'Extranetv4\ImageUploadController@getImages']);

    //Room type Routes
    $router->group(['prefix' => 'hotel_master_room_type', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@getAllRoomTypes']);
        $router->get('/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@getHotelroomtype']);
        $router->post('add', ['uses' => 'Extranetv4\MasterRoomTypeController@addNewRoomType']);
        $router->post('update/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@updatemasterroomtype']);
        $router->delete('delete/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@deletemasterroomtype']);
        $router->post('delete', ['uses' => 'Extranetv4\MasterRoomTypeController@deleteImage']);
        $router->get('/room_types/{hotel_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@GetRoomTypes']);
        $router->get('/room_type/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@GetRoomType']);
        $router->get('/get_rack_price/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@getHotelRackPrice']);
        $router->get('/get_max_people/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@getMaxPeople']);
        $router->post('update_amen/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@updateAmenities']);
        $router->post('/airbnb-details-add', ['uses' => 'Extranetv4\AirbnbController@addAirBnbDetails']);
        $router->post('/airbnb-details-update/{airbnb_details_id}', ['uses' => 'Extranetv4\AirbnbController@updateAirBnbDetails']);
        $router->get('/airbnb-data/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\AirbnbController@getAirbnbData']);
        $router->get('/airbnb-ready-review/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\AirbnbController@updateReviewStatus']);
        $router->get('/getairbnb-instant-booking/{room_type_id}/{hotel_id}', ['uses' => 'Extranetv4\AirbnbController@getAirbnbMaxdaystatus']);
        $router->get('/getairbnb-instant-booking/{airbnb_status}/{room_type_id}/{hotel_id}', ['uses' => 'Extranetv4\AirbnbController@airbnbInstantBooking']);
        $router->post('/listing_notification/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@updateNotification']);
    });

    $router->get('hotel_master_room_type/get_room_images/{room_type_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@getroomtypeImages']);
    $router->get('room_type_forbe/room_types/{hotel_id}', ['uses' => 'Extranetv4\MasterRoomTypeController@GetRoomTypes']);


    //coupons Routes
    $router->group(['prefix' => 'coupons', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CouponsController@addNewCoupons']);
        $router->post('/update/{coupon_id}', ['uses' => 'Extranetv4\CouponsController@Updatecoupons']);
        $router->delete('/{coupon_id}', ['uses' => 'Extranetv4\CouponsController@DeleteCoupons']);
        $router->get('/all', ['uses' => 'Extranetv4\CouponsController@GetAllCoupons']);
        $router->get('/{coupon_id}', ['uses' => 'Extranetv4\CouponsController@GetCoupons']);
        $router->get('/get/{hotel_id}', ['uses' => 'Extranetv4\CouponsController@GetCouponsByHotel']);
        $router->get('/list/type', ['uses' => 'Extranetv4\CouponsController@couponsType']);
        $router->get('/fetch/{coupon_id}', ['uses' => 'Extranetv4\CouponsController@fetchCoupons']);
        $router->post('/get-rateplan/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\CouponsController@GetRatePlanByRoomType']);
    });
    $router->get('/manual-coupon-remove/{coupon_id}', ['uses' => 'Extranetv4\CouponsController@DeleteCoupons']);
    //promotional popup Routes
    $router->group(['prefix' => 'promotional_popup', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\PromotionalPopupController@addNewPromo']);
        $router->post('/update/{coupon_id}', ['uses' => 'Extranetv4\PromotionalPopupController@UpdatePromo']);
        $router->delete('/{promo_id}', ['uses' => 'Extranetv4\PromotionalPopupController@DeletePromo']);
        $router->get('/all', ['uses' => 'Extranetv4\PromotionalPopupController@GetAllPromo']);
        $router->get('/{coupon_id}', ['uses' => 'Extranetv4\PromotionalPopupController@GetPromo']);
        $router->get('/get/{hotel_id}', ['uses' => 'Extranetv4\PromotionalPopupController@GetPromoByHotel']);
    });
    // offline booking Routes
    $router->group(['prefix' => 'offline_booking', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\OfflineBookingController@addNewOfflineBooking']);

        $router->get('/{user_id}', ['uses' => 'Extranetv4\OfflineBookingController@GetOfflineBooking']);
        $router->get('/all/{hotel_id}/{type}/{from_date}/{to_date}', ['uses' => 'Extranetv4\OfflineBookingController@GetAllOfflineBooking']);
    });
    $router->get('/booking/all/{hotel_id}/{type}/{from_date}/{to_date}', ['uses' => 'Extranetv4\ManageBookingController@GetAllBooking']);
    $router->get('/booking/one/{hotel_id}/{type}/{from_date}/{to_date}/{invoice_id}', ['uses' => 'Extranetv4\ManageBookingController@GetOneBooking']);
    $router->get('/booking/sp-invoice/{hotel_id}/{invoice_id}', ['uses' => 'Extranetv4\ManageBookingController@GetSpInvoiceBooking']);
    //CRM Routes
    $router->group(['prefix' => 'crm_leads', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CrmLeadsController@addNewcrmleads']);
        $router->post('/update/{contact_details_id}', ['uses' => 'Extranetv4\CrmLeadsController@UpdateCrmLeads']);
        $router->get('/{contact_details_id}', ['uses' => 'Extranetv4\CrmLeadsController@GetCrmLeads']);
        $router->get('/all/{hotel_id}', ['uses' => 'Extranetv4\CrmLeadsController@GetAllCrmLeads']);
    });
    // follow up Routes
    $router->group(['prefix' => 'follow_up', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\followUpController@addNewFollowUp']);
        $router->get('/all/{client_id}', ['uses' => 'Extranetv4\followUpController@GetAllFollowUp']);
    });
    //manage user routes
    $router->group(['prefix' => 'manage_user', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\ManageUserController@addNewUsers']);
        $router->post('/update/{admin_id}', ['uses' => 'Extranetv4\ManageUserController@UpdateUsers']);
        $router->delete('/{admin_id}', ['uses' => 'Extranetv4\ManageUserController@DeleteUsers']);
        $router->get('/{admin_id}', ['uses' => 'Extranetv4\ManageUserController@GetUsers']);
        $router->get('/all/{company_id}', ['uses' => 'Extranetv4\ManageUserController@GetAllUsers']);
        $router->get('/external_users/{company_id}/{hotel_id}', ['uses' => 'Extranetv4\ManageUserController@GetExternalUsers']);
        $router->get('/agent/{company_id}/{hotel_id}', ['uses' => 'Extranetv4\ManageUserController@GetAgentUsers']);
    });

    //CM ota Details  Routes
    $router->group(['prefix' => 'cm_ota_details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CmOtaDetailsController@addNewCmHotel']);
        $router->post('/update/{ota_id}', ['uses' => 'Extranetv4\CmOtaDetailsController@updateCmHotel']);
        $router->delete('/{hotel_id}/{ota_id}', ['uses' => 'Extranetv4\CmOtaDetailsController@deleteCmHotel']);
        //$router->get('/all',['uses'=>'Extranetv4\CmOtaDetailsController@getAllCmHotel']);
        $router->get('/{ota_id}', ['uses' => 'Extranetv4\CmOtaDetailsController@getCmHotel']);
        $router->get('/sync/{ota_id}', ['uses' => 'Extranetv4\CmOtaDetailsController@multipleFunction']);
        $router->get('/get/{hotel_id}', ['uses' => 'Extranetv4\CmOtaDetailsController@getAllCmHotel']);
        $router->get('/toggle/{hotel_id}/{ota_id}/{is_active}', ['uses' => 'Extranetv4\CmOtaDetailsController@toggle']);
    });
    //CM ota room type sync  Routes
    $router->group(['prefix' => 'cm_ota_roomtype_sync', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CmOtaSyncController@addNewCmOtaSync']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\CmOtaSyncController@updateCmOtaSync']);
        $router->delete('/{id}', ['uses' => 'Extranetv4\CmOtaSyncController@deleteCmOtaSync']);
        $router->get('/ota_room_types/{hotel_id}/{ota_id}', ['uses' => 'Extranetv4\CmOtaSyncController@otaRoomTypes']);
        $router->get('/ota_sync_room_types/{hotel_id}/{ota_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaSyncRoomTypes']);
        $router->get('/ota_sync_data/{sync_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaSyncById']);
        $router->get('/ota_rate_plan/{hotel_id}/{ota_id}/{ota_room_type_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaRoomRatePlan']);
        $router->get('/ota_sync_rate_plan/{hotel_id}/{ota_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaSyncRoomRatePlan']);
        $router->get('/ota_sync_rate/{sync_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaRatePlanSyncById']);
        $router->get('/ota_room_type/{hotel_id}/{ota_id}/{room_type_id}', ['uses' => 'Extranetv4\CmOtaSyncController@fetchOtaRoomType']);
        $router->get('/all_ota/{hotel_id}', ['uses' => 'Extranetv4\CmOtaSyncController@getAllSyncRoomsData']);
        $router->get('/all_ota_rates/{hotel_id}', ['uses' => 'Extranetv4\CmOtaSyncController@getAllSyncRoomRateData']);
    });

    //CM ota  rate plan sync  Routes
    $router->group(['prefix' => 'cm_ota_rateplan_sync', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\CmOtaSyncController@addNewCmOtaRatePlanSync']);
        $router->post('/update/{id}', ['uses' => 'Extranetv4\CmOtaSyncController@updateCmOtaRatePlanSync']);
        $router->delete('/{id}', ['uses' => 'Extranetv4\CmOtaSyncController@deleteCmOtaRatePlanSync']);
    });
    //Ota and BE Inventory update
    $router->post('/hotel_inventory_update', ['uses' => 'Extranetv4\InventoryController@inventoryUpdate']);
    //Ota and BE Rates Update
    $router->post('/hotel_room_rate_update', ['uses' => 'Extranetv4\RoomRateController@roomRateUpdate']);

    $router->group(['prefix' => 'rates', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/room_rate_update_new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkRateUpdate']);
        $router->post('/room_rate_update_without_derived_rate_plan_new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkRateUpdateWithoutDerivedRatePlan']);
        $router->post('/room_rate_update_without_block_dates', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkRateUpdateWithoutBlockedRates']);

        $router->post('/block_room_rate_update_new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@blockRateUpdate']);
        $router->post('/individual-rate-update', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@individualRateUpdate']);

        //Added on date : - 10-06-2023
        $router->post('/room-wise-calendar-update', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@multiRoomtypeMultiRatePlanMultiDatesUpdate']);

        //For Test
        $router->post('/individual-rate-update-test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@individualRateUpdate']);
        $router->post('/room_rate_update_new_test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@bulkRateUpdate']);
        $router->post('/block_room_rate_update_new_test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@blockRateUpdate']);

        //date : - 09-06-2023
        $router->post('/room-wise-calendar-update-test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@multiRoomtypeMultiRatePlanMultiDatesUpdate']);
    });


    //Added by Jigyans dt : - 26-07-2023
    $router->post('/bulk-inventory-update-for-pms-ids', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);
    $router->post('/bulk-inventory-update-for-pms-ids-test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);
    
    $router->post('/bulk-inventory-block-for-pms-ids', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@blockInvForDateRange']);
    $router->post('/bulk-inventory-block-for-pms-ids-test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@blockInvForDateRange']);

    //Added by Jigyans dt : - 06-04-2023
    $router->group(['prefix' => 'inventory', 'middleware' => 'jwt.auth'], function ($router) {
        // $router->post('/room_rate_update_new',['uses'=>'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkRateUpdate']);
        $router->post('/sync-inventory-new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@sycInventoryUpdateNew']);
        $router->post('/block-inventory-new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@blockInvForDateRangeNew']);
        $router->post('/unblock-inventory-new', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@unblockInvForDateRangeNew']);

        // $router->post('/bulk-inventory-update-new',['uses'=>'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkInvUpdateNew']);
        $router->post('/bulk-inventory-update-new', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);

        //date : - 18-05-2023
        $router->post('/room-wise-calendar-update', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@roomTypeWiseCalendarUpdate']);

        $router->post('/bulk-inventory-update-new-test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateController@bulkInvUpdateNew']);

        //For Test
        // $router->post('/bulk-inventory-update-new-test',['uses'=>'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@bulkInvUpdateNew']);

        $router->post('/sync-inventory-new-test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@sycInventoryUpdateNew']);
        $router->post('/room-wise-calendar-update-test', ['uses' => 'Extranetv4\invrateupdatecontrollersnew\BookingEngineInvRateControllerJigyansTest@roomTypeWiseCalendarUpdate']);
    });


    /*================OTA BBookings==========================*/
    $router->group(['prefix' => 'ota_bookings'], function ($router) {
        $router->get('/get/{from_date}/{to_date}/{date_type}/{ota}/{booking_status}/{hotel_id}/{booking_id}', ['uses' => 'Extranetv4\OtaBookingController@getOtaBookingsDateWise']);
    });

    //Company Profile updation routes
    $router->group(['prefix' => 'company_profile', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/booking_page/{company_id}', ['uses' => 'Extranetv4\BeregistrationController@updateBookingPageDetails']);
        // $router->post('/add',['uses'=>'Extranetv4\CompanyRegistrationController@addNew']);
        // $router->post('/update',['uses'=>'Extranetv4\CompanyRegistrationController@updateProfile']);
        // $router->get('/{company_id}',['uses'=>'Extranetv4\CompanyRegistrationController@getCompanyProfile']);
        //  $router->post('/booking_page/{company_id}',['uses'=>'Extranetv4\CompanyRegistrationController@updateBookingPageDetails']);
        // $router->get('/get/{company_id}',['uses'=>'Extranetv4\CompanyRegistrationController@getCompanyDetails']);
    });
    $router->get('/company_profile/get-logo/{company_id}', ['uses' => 'Extranetv4\CompanyRegistrationController@getCompanyLogo']);
    $router->post('company_profile/delete', ['uses' => 'Extranetv4\CompanyRegistrationController@deleteImage']);

    ///Bookingjini PMS Routes
    $router->group(['prefix' => 'pms', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('hotel-details/{key}/{hotel_id}', ['uses' => 'Extranetv4\PmsController@hotelDetails']);
        $router->post('booking-details', ['uses' => 'Extranetv4\PmsController@bookingDetails']);
        $router->post('update-inventory', ['uses' => 'Extranetv4\PmsController@updateInventory']);
    });
    //IDS PMS Routes
    $router->group(['prefix' => 'ids'], function ($router) {
        $router->post('update-inventory', ['uses' => 'Extranetv4\IdsController@updateInventory']);
        $router->get('get-response', ['uses' => 'Extranetv4\IdsController@getResponse']);
        $router->get('bookings', ['uses' => 'Extranetv4\IdsController@execute']);
    });
    //rms routes
    $router->group(['prefix' => 'rms'], function ($router) {
        $router->post('/update-inventory', ['uses' => 'Extranetv4\RmsController@updateInventory']);
        $router->post('/getroomtype', ['uses' => 'Extranetv4\RmsController@getRoomType']);
        $router->post('/getrateplan', ['uses' => 'Extranetv4\RmsController@getRatePlan']);
        $router->post('/update-rates', ['uses' => 'Extranetv4\RmsController@updateRates']);
        $router->post('/bookings_rules', ['uses' => 'Extranetv4\RmsController@rmsBookingRules']);
        $router->get('/ota_bookings', ['uses' => 'Extranetv4\CmOtaBookingPushBucketController@actionBookingbucketengine']);
    });
    //pms details
    $router->group(['prefix' => 'pms_details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\PmsDetailsController@addNewCmHotel']);
        $router->post('/update/{ota_id}', ['uses' => 'Extranetv4\PmsDetailsController@updateCmHotel']);
        $router->get('/sync/{ota_id}', ['uses' => 'Extranetv4\PmsDetailsController@multipleFunction']);
    });
    //Dashboard Routes
    $router->get('/invoiceAmount/getById/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@selectInvoice']);
    $router->group(['prefix' => 'dashboard', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/getById/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\DashBoardController@selectInvoice']);
        $router->get('/getAll/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@getOtaDetails']);
        $router->get('/gethotelbooking/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@getHotelBookings']);
        $router->get('/getAllcheckout/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@getOtaDetailsCheckOut']);
        $router->get('/gethotelbookingcheckout/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@getHotelBookingsCheckOut']);
        $router->get('/hotelbooking/{invoice_id}', ['uses' => 'Extranetv4\DashBoardController@hotelBookingCheckInOutInvoice']);
        $router->get('/otabooking/{id}', ['uses' => 'Extranetv4\DashBoardController@otaBookingCheckInOutid']);
        $router->get('/bookingEngeenHelth/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@percentageCount']);
        $router->get('/yearlyhotelbooking/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@yearlyHotelBooking']);
        $router->get('/yearlyotabooking/{hotel_id}', ['uses' => 'Extranetv4\DashBoardController@yearlyOtaBooking']);
    });
    //Unique visitor Route
    $router->group(['prefix' => 'dashboard'], function ($router) {
        $router->post('/uniqueVisitors/{hotel_id}', ['uses' => 'Extranetv4\BeDashboardController@uniqueVisitors']);
        $router->get('/uniqueVisitorsDashboard/{company_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\DashBoardController@uniqueVisitorsDashboard']);
        $router->get('/uniqueVisitorsWB/{company_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\DashBoardController@uniqueVisitorsWB']);
        $router->get('/uniqueBEVisitors/{company_id}/{duration}/{date_from}/{date_to}', ['uses' => 'Extranetv4\BeReportController@uniqueBEVisitors']);
    });
    //Mail-Invoice
    $router->group(['prefix' => 'mailInvoice', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/details/{hotel_id}', ['uses' => 'Extranetv4\MailInvoiceController@getInvoiceDetails']);
        $router->post('/mail/{hotel_id}', ['uses' => 'Extranetv4\MailInvoiceController@sendInvoiceMail']);
        $router->post('/details/{hotel_id}', ['uses' => 'Extranetv4\MailInvoiceController@getInvoiceDetailsNew']);
    });
    $router->post('/manual-booking-success/{booking_id}', ['uses' => 'Extranetv4\MailInvoiceController@manualBookingSuccess']);
    // unpaid bookings report download

    $router->get('/download-report/{hotel_id}', ['uses' => 'Extranetv4\MailInvoiceController@UnpaidBookingReportDownload']);


    //Logs Routes
    $router->group(['prefix' => 'log-details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/inventory/{hotel_id}/{from_date}/{to_date}/{room_type_id}/{selected_be_ota_id}', ['uses' => 'Extranetv4\LogsController@inventoryDetails']);
        $router->get('/rateplan/{hotel_id}/{from_date}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}', ['uses' => 'Extranetv4\LogsController@rateplanDetails']);
        $router->get('/booking/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\LogsController@bookingDetails']);
        $router->get('/session/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\LogsController@userSession']);
    });
    $router->group(['prefix' => 'log-details', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/inventory-test/{hotel_id}/{to_date}/{room_type_id}/{selected_be_ota_id}', ['uses' => 'Extranetv4\LogsControllerTest@inventoryDetails']);
        $router->get('/rateplan-test/{hotel_id}/{to_date}/{rate_plan_id}/{selected_be_ota_id}/{room_type_id}', ['uses' => 'Extranetv4\LogsControllerTest@rateplanDetails']);
        $router->get('/session-test/{hotel_id}/{to_date}', ['uses' => 'Extranetv4\LogsControllerTest@userSession']);
    });
    //blocking ip
    $router->post('/BlockedClientIp/insert', ['uses' => 'Extranetv4\BlockController@blockClientIp']);
    $router->get('/BlockedClientIp/get', ['uses' => 'Extranetv4\BlockController@BlockIpDetails']);
    $router->delete('/BlockedClientIp/delete/{wrong_attempt_id}', ['uses' => 'Extranetv4\BlockController@unBlockIp']);

    //reporting
    $router->group(['prefix' => 'reporting', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/details/{hotel_id}/{type}', ['uses' => 'Extranetv4\ReportingController@bookingDetails']);
        $router->get('/details_dashboard/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\ReportingController@dashboardBookingDetails']);
        $router->get('/total-earning/{hotel_id}/{type}', ['uses' => 'Extranetv4\ReportingController@bookingTotalEarning']);
        $router->get('/occupancy/{hotel_id}/{type}', ['uses' => 'Extranetv4\ReportingController@occupancy']);
        $router->get('/roomtypeSelect/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@roomType']);
        $router->get('/average/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\ReportingController@average']);
        $router->get('/tvcBooking/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@tvcBooking']);
        $router->get('/otaSelect/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@getOtaDetails']);
        $router->get('/cvcBooking/{hotel_id}/{ota_id}', ['uses' => 'Extranetv4\ReportingController@cvcBooking']);
        $router->get('/comission/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@comission']);
        $router->post('/inventory/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@inventory']);
        $router->get('/get-otawise-booking/{ota_id}/{hotel_id}', ['uses' => 'Extranetv4\ReportingController@getOTAtotalBookings']);
    });

    //Device Notfication APi
    $router->post('/device_info/device_details', ['uses' => 'Extranetv4\DeviceNotificationController@deviceInformation']);

    //Test
    $router->get('/test-pushIds', ['uses' => 'Extranetv4\BookingEngineController@testPushIds']);


    //Test airbnb
    $router->get('/get_airbnb_token', ['uses' => 'Extranetv4\MasterRoomTypeController@getAirbnbToken']);

    //no show routes
    $router->group(['prefix' => 'bookingDotCom', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/noshow', ['uses' => 'Extranetv4\BookingdotcomController@noShowPush']);
    });

    //test the booking voucher
    $router->post('/test/test-voucher/', ['uses' => 'Extranetv4\otacontrollers\GoibiboController@actionIndex']);
    $router->get('/test/voucher_mail/{ota_booking_id}/{bucket_booking_status}', ['uses' => 'Extranetv4\OtaAutoPushUpdateController@mailHandler1']);
    $router->post('/test/test-booking-voucher/', ['uses' => 'Extranetv4\otacontrollers\BookingdotcomController@actionIndex']);
    $router->post('/test/test-agoda-voucher/', ['uses' => 'Extranetv4\otacontrollers\AgodaController@actionIndex']);
    $router->post('/test/test-expedia-voucher/', ['uses' => 'Extranetv4\otacontrollers\ExpediaController@actionIndex']);
    $router->post('/test/test-via-voucher/', ['uses' => 'Extranetv4\otacontrollers\ViadotcomController@actionIndex']);
    $router->post('/test/test-cleartrip-voucher/', ['uses' => 'Extranetv4\otacontrollers\CleartripController@actionIndex']);
    $router->post('/test/test-travelguru-voucher/', ['uses' => 'Extranetv4\otacontrollers\TravelguruController@actionIndex']);
    $router->post('/test/test-paytm-voucher/', ['uses' => 'Extranetv4\otacontrollers\PaytmController@actionIndex']);


    //guest details of checkin date
    $router->get('/guest/guest-details/', ['uses' => 'Extranetv4\GuestCheckinNotification@guestInformation']);
    $router->get('/booking-data/download/{booking_data}', ['uses' => 'Extranetv4\BookingDetailsDownloadController@getSearchData']);
    //for testing ota bucket controller
    $router->get('/get-inv-from-ota', ['uses' => 'Extranetv4\CmOtaBookingPushBucketController@actionBookingbucketengine']);
    //for testing inv update from otabookingdatainsert
    $router->get('/update-inv-in-be', ['uses' => 'Extranetv4\otacontrollers\BookingDataInsertationController@updateInvForBe']);

    //new reports
    $router->group(['prefix' => 'newreports'], function ($router) {
        $router->get('/number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@getRoomNightsByDateRange']);
        $router->get('/total-amount/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@totalRevenueOtaWise']);
        $router->get('/total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@numberOfBookings']);
        $router->get('/average-stay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@averageStay']);
        $router->get('/rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@ratePlanPerformance']);
        $router->get('/rate-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\NewReportController@ratePerformance']);
    });

    //display api for inv,rate and bookings

    $router->group(['prefix' => 'get-data'], function ($router) {
        $router->post('/number-of-inventory', ['uses' => 'Extranetv4\InvRateBookingDisplayController@invData']);
        $router->post('/rate_amount', ['uses' => 'Extranetv4\InvRateBookingDisplayController@rateData']);
    });
    //derive plan api

    $router->group(['prefix' => 'derive-plan'], function ($router) {
        $router->get('/check-plan/{hotel_id}', ['uses' => 'Extranetv4\DerivePlanController@checkRoomRatePlan']);
        $router->post('/add-plan', ['uses' => 'Extranetv4\DerivePlanController@addDetailsOfDerivedPlan']);
        $router->get('/update-derived-plan/{room_rate_plan_id}/{master_status}', ['uses' => 'Extranetv4\DerivePlanController@updateMasterPlanStatus']);
        $router->get('/get-plan-details/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\DerivePlanController@getRoomTypeRatePlanName']);
        $router->get('/make-normal/{hotel_id}/{room_rate_plan}/{room_type}/{rate_plan}', ['uses' => 'Extranetv4\DerivePlanController@normalPlan']);
        $router->post('/check_room_occupancy', ['uses' => 'Extranetv4\DerivePlanController@getRoomOccupancy']);
    });

    //get api data for website-builder
    $router->get('/check-website-status/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@fetchwebsitestatus']);
    $router->get('/get-room-details/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@retriveRoomDetails']);
    $router->post('/fetch-subdomain-name/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@fetchSubdomain']);
    $router->get('/fetch-map-details/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@getMapDetails']);
    $router->get('/get-hotel-menu/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@getHotelMenuDetails']);
    $router->get('/get-hotel-details/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@getHotelDetails']);
    $router->get('/get-hotel-banner/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@getHotelBanner']);
    $router->get('/fetch-hotel-details/{company_id}', ['uses' => 'Extranetv4\AdminAuthController@fetchDetails']);
    $router->post('/fetch-hotel-mailid/{hotel_id}', ['uses' => 'Extranetv4\AdminAuthController@getHotelMailId']);
    $router->post('/fetch-hotel-packages/{hotel_id}/{checkin_date}/{checkout_date}', ['uses' => 'Extranetv4\AdminAuthController@getHotelPackages']);


    $router->get('/test-mail-handler', ['uses' => 'Extranetv4\OtaAutoPushUpdateController@testmailhandler']);


    //sync inventory and rate
    $router->group(['prefix' => 'sync-inv-rate'], function ($router) {
        $router->get('/push-ota', ['uses' => 'Extranetv4\invrateupdatecontrollers\MasterInvRateUpdateController@syncInvRateDataPushToOTA']);
        $router->get('/notifications/{hotel_id}', ['uses' => 'Extranetv4\NotificationController@getNotificationDetails']);
        $router->post('/read-status', ['uses' => 'Extranetv4\NotificationController@updateReadStatus']);
    });
    //get all hotel specific details

    $router->get('/get-specific-details', ['uses' => 'Extranetv4\AllHotelDetailsControllers@getHotelSpecificDetails']);

    $router->get('/test-hotelogix', ['uses' => 'Extranetv4\CmOtaBookingPushBucketController@actionBookingbucketengine']);


    $router->get('sqs', function () use ($router) {
        // \App\Jobs\TestJob::dispatch();
        // return $router->app->version();
        $job = new TestJob();
        dispatch($job);
    });

    $router->get('run-bucket', function () use ($router) {
        $job2 = new BucketJob();
        dispatch($job2);
        // echo "Processing...";
    });


    //get BDT currency test
    $router->get('/get-BTD', ['uses' => 'Extranetv4\CurrencyController@getBDT']);

    //get hotel name from hotel id for jini-chat-panel
    $router->get('/retrive-hotel-name/{hotel_id}', ['uses' => 'Extranetv4\AdminAuthController@getHotelName']);


    $router->group(['prefix' => 'dashboard', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/crs-booking/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\CrsDashboardController@dashboardBookingDetails']);

        $router->get('/checkin-crs-booking/{hotel_id}', ['uses' => 'Extranetv4\CrsDashboardController@getHotelBookings']);

        $router->get('/checkout-crs-booking/{hotel_id}', ['uses' => 'Extranetv4\CrsDashboardController@getHotelBookingsCheckOut']);
        $router->get('/select-be-invoice/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\BeDashboardController@selectInvoice']);

        $router->get('/select-be-visitor/{company_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\BeDashboardController@beUniqueVisitors']);

        $router->get('/select-be-room-nights/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeDashboardController@getRoomNightsByDateRange']);

        $router->get('/select-be-revenue/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeDashboardController@totalRevenueOtaWise']);

        $router->get('/select-be-avgstay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeDashboardController@averageStay']);

        $router->get('/select-be-rateplan/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeDashboardController@ratePlanPerformance']);

        $router->get('/be-booking/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\BeDashboardController@dashboardBookingDetails']);

        $router->get('/checkin-be-booking/{hotel_id}', ['uses' => 'Extranetv4\BeDashboardController@getHotelBookings']);

        $router->get('/checkout-be-booking/{hotel_id}', ['uses' => 'Extranetv4\BeDashboardController@getHotelBookingsCheckOut']);

        $router->get('/be-checkinout-invoice/{invoice_id}', ['uses' => 'Extranetv4\BeDashboardController@hotelBookingCheckInOutInvoice']);

        $router->get('/crs-booking/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\CrsDashboardController@dashboardBookingDetails']);

        $router->get('/checkin-crs-booking/{hotel_id}', ['uses' => 'Extranetv4\CrsDashboardController@getCrsBookings']);

        $router->get('/checkout-crs-booking/{hotel_id}', ['uses' => 'Extranetv4\CrsDashboardController@getCrsBookingsCheckOut']);
    });

    //test Bookingengine
    $router->get('/test/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@testbooking']);


    //routes for providing the room details to google hotel ads
    $router->post('/google-hotel-ads-room-details', ['uses' => 'Extranetv4\GoogleHotelAdsController@googleHotelAdsHotelData']);
    $router->get('/google-hotel-ads-creation/{hotel_id}', ['uses' => 'Extranetv4\GoogleHotelAdsController@addHotelToGoogleHotelAds']);
    $router->get('/google-hotel-ads-deletion/{hotel_id}', ['uses' => 'Extranetv4\GoogleHotelAdsController@removeHotelFromGoogleHotelAds']);
    $router->get('/google-hotel-ads-retrieve', ['uses' => 'Extranetv4\GoogleHotelAdsController@retrieveGoogleHotelAds']);
    $router->get('/google-hotel-ads-ari-update/{hotel_id}', ['uses' => 'Extranetv4\GoogleHotelAdsController@syncInventoryRateToGoogleHotelAds']);
    $router->get('/google-hotel-ads-xml-download', ['uses' => 'Extranetv4\GoogleHotelAdsController@downloadXML']);

    // $router->get('/google-inventory-update/{hotel_id}/{room_type_id}/{no_of_rooms}/{from_date}/{to_date}',['uses'=>'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@inventoryUpdateToGoogleAds']);
    // $router->get('/google-rate-update/{hotel_id}/{room_type_id}/{rate_plan_id}/{from_date}/{to_date}/{bar_price}',['uses'=>'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@rateUpdateToGoogleAds']);
    // $router->get('/google-los-update/{hotel_id}/{room_type_id}/{rate_plan_id}/{from_date}/{to_date}/{los}',['uses'=>'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@losUpdateToGoogleAds']);


    $router->get('/test-multiple-join', ['uses' => 'Extranetv4\TestController@multiJoin']);


    //CRM routes listed below.
    $router->get('/customer-details', ['uses' => 'Extranetv4\TestControllerCRM@getCustomerDetails']);
    $router->post('/customer-details', ['uses' => 'Extranetv4\CRMBookingControllers@getBooking']);

    //===========================================================================================================================

    //Added by Saroj
    $router->get('/check_payment_status/{tnx_id}', ['uses' => 'Extranetv4\QuickPaymentLinkController@checkPaymentStatus']);
    $router->get('/manually_update_payment_status/{tnx_id}', 'Extranetv4\BookingEngineController@manuallyUpdateStatus');
    $router->post('/day-wise-price/{invoice_id}', 'Extranetv4\BookingEngineController@dayWisePrice');
    $router->post('/test-bookings', 'Extranetv4\BookingEngineController@bookings');

    $router->get('/payment-testing', 'Extranetv4\PaymentGatewayController@paymentTesting');


    //Booking Engine Basic Setup
    $router->get('/theme-setup/{company_id}', 'Extranetv4\BookingEngineBasicSetupController@themeSetup');
    $router->post('/theme-setup/update', 'Extranetv4\BookingEngineBasicSetupController@themeSetupUpdate');

    $router->get('manage-url/{company_id}', 'Extranetv4\BookingEngineBasicSetupController@manageUrl');
    $router->post('manage-url/update', 'Extranetv4\BookingEngineBasicSetupController@manageUrlUpdate');

    $router->get('get-log-banner/{hotel_id}', 'Extranetv4\BookingEngineBasicSetupController@getLogoBanner');
    $router->post('logo-banner/update', 'Extranetv4\BookingEngineBasicSetupController@logoBannerUpdate');

    $router->get('other-settings/{hotel_id}', 'Extranetv4\BookingEngineBasicSetupController@otherSetting');
    $router->post('other-settings/update', 'Extranetv4\BookingEngineBasicSetupController@otherSettingUpdate');

    $router->get('fetch-other-settings/{hotel_id}', 'Extranetv4\BookingEngineBasicSetupController@fetchOtherSetting');
    $router->post('update-other-settings', 'Extranetv4\BookingEngineBasicSetupController@updateOtherSetting');
    // $router->post('other-settings/update', 'Extranetv4\BookingEngineBasicSetupController@otherSettingUpdate');

    $router->get('check-all-setup/{hotel_id}', 'Extranetv4\BookingEngineBasicSetupController@checkAllSetup');
    $router->get('check-all-setup-old/{hotel_id}', 'Extranetv4\BookingEngineBasicSetupController@checkAllSetupOld');


    //Subscription
    $router->post('/subscription-plan-setup', 'Extranetv4\SubscriptionController@subscriptionPlanSetup');
    $router->post('/subscription-plan-update', 'Extranetv4\SubscriptionController@subscriptionPlanUpdate');
    $router->post('/subscription-plan-get', 'Extranetv4\SubscriptionController@fetchSubscriptionPlan');
    $router->post('/subscription-plan-delete', 'Extranetv4\SubscriptionController@deleteSubscriptionPlan');
    $router->get('/subscription-product-details', 'Extranetv4\SubscriptionController@GetProductDetails');
    $router->post('/hotel-subscription-plan-add', 'Extranetv4\SubscriptionController@hotelSubscriptionPlanAdd');
    $router->post('/hotel-subscription-plan-edit/{subscription_id}/{id}/{hotel_id}', 'Extranetv4\SubscriptionController@hotelSubscriptionPlanEdit');
    $router->post('/activity', 'Extranetv4\SubscriptionController@activity');
    $router->post('/activity-add', 'Extranetv4\SubscriptionController@activityAdd');
    $router->post('/activity-edit/{id}', 'Extranetv4\SubscriptionController@activityEdit');
    $router->post('/user-activity', 'Extranetv4\SubscriptionController@getUserActivity');
    $router->post('/user-activity-add', 'Extranetv4\SubscriptionController@addUserActivity');
    $router->post('/user-activity-edit/{id}', 'Extranetv4\SubscriptionController@editUserActivity');
    $router->post('/update-subscription-plan', 'Extranetv4\SubscriptionController@upgradeHotelSubscriptionPlan');
    $router->get('/check-user-activity', 'Extranetv4\SubscriptionController@checkUserActivity');
    $router->get('/select-subscription/{hotel_id}', ['uses' => 'Extranetv4\SubscriptionController@getHotelSubscriptionPlan']);
    $router->get('/fetch-plan', ['uses' => 'Extranetv4\SubscriptionController@FetchPlans']);

    //bookingenginePromotion
    $router->post('/fetch-promotion', ['uses' => 'Extranetv4\BookingEnginePromotionController@fetchPromotion']);
    $router->post('/update-promotion', ['uses' => 'Extranetv4\BookingEnginePromotionController@UpdatePromotion']);
    $router->post('/promotion-status-change', ['uses' => 'Extranetv4\BookingEnginePromotionController@activePromotion']);
    $router->post('/promotion-delete', ['uses' => 'Extranetv4\BookingEnginePromotionController@deletePromotion']);

    //====
    /* web.php rout end */
    /* be-web.php rout start */
    $router->group(['prefix' => 'bookingEngine', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/bookings/{api_key}', ['uses' => 'Extranetv4\BookingEngineController@bookings']);
        $router->post('/bookings-test/{api_key}', ['uses' => 'Extranetv4\BookingEngineTestController@bookings']);
    });
    $router->post('/bookings-testing/{api_key}', ['uses' => 'Extranetv4\BookingEngineController@bookingsTest']);

    //Coupons
    $router->post('/coupons/check_coupon_code', ['uses' => 'Extranetv4\CouponsController@checkCouponCode']);
    $router->post('/coupons/check_coupon_code_new', ['uses' => 'Extranetv4\CouponsController@checkCouponCodeNew']);
    $router->post('/be_coupons/public', ['uses' => 'Extranetv4\CouponsController@GetCouponsPublic']);
    $router->get('/be_coupons/public/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\BookingEngineController@getAllPublicCupons']);
    //other tax details
    $router->get('/get-other-tax-details/{company_id}/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@getTaxDetails']);
    $router->get('/get-cupons/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\becontroller\BookingEngineController@getAllPublicCupons']);
    //Booking routes
    $router->get('/bookingEngine/get-inventory/{api_key}/{hotel_id}/{date_from}/{date_to}/{currency_name}', ['uses' => 'Extranetv4\BookingEngineController@getInvByHotel']);
    $router->get('/bookingEngine/auth/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccess']);
    //added for testing
    $router->get('/bookingEngine/auth_for/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccess']);


    //added for Booking Widget
    $router->get('/bookingEngine/auth_for_widget/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccessWidget']);

    //end
    $router->get('/bookingEngine/get-room-info/{api_key}/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\BookingEngineController@getRoomDetails']);
    $router->get('/bookingEngine/get-hotel-info/{api_key}/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@getHotelDetails']);
    $router->post('/bookingEngine/success-booking', ['uses' => 'Extranetv4\BookingEngineController@successBooking']);
    $router->get('/bookingEngine/get-hotel-logo/{api_key}/{company_id}', ['uses' => 'Extranetv4\BookingEngineController@getHotelLogo']);
    $router->get('/bookingEngine/invoice-details/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@invoiceDetails']);
    $router->get('/bookingEngine/be-calendar/{api_key}/{hotel_id}/{startDate}/{currency_name}', ['uses' => 'Extranetv4\BookingEngineController@beCalendar']);
    $router->get('/bookingEngine/invoice-data/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchInvoiceData']);

    //Payment related routes
    $router->get('/payment/{invoice_id}/{hash}', ['uses' => 'Extranetv4\PaymentGatewayController@actionIndex']);
    $router->post('/payu-fail', ['uses' => 'Extranetv4\PaymentGatewayController@payuResponse']);
    $router->post('/payu-response', ['uses' => 'Extranetv4\PaymentGatewayController@payuResponse']);
    $router->post('/hdfc-response', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcResponse']);
    $router->post('/airpay-response', ['uses' => 'Extranetv4\PaymentGatewayController@airpayResponse']);
    $router->get('/axis-response', ['uses' => 'Extranetv4\PaymentGatewayController@axisResponse']);
    //   $router->post('/axis-request',['uses'=>'Extranetv4\PaymentGatewayController@axisRequest']);
    $router->post('/hdfc-payu-response', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcPayuResponse']);
    $router->post('/hdfc-payu-fail', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcPayuResponse']);
    $router->post('/worldline-response', ['uses' => 'Extranetv4\PaymentGatewayController@worldLineResponse']);
    $router->post('/sslcommerz-response', ['uses' => 'Extranetv4\PaymentGatewayController@sslcommerzResponse']);
    $router->post('/atompay-response', ['uses' => 'Extranetv4\PaymentGatewayController@atompayResponse']);
    $router->post('/icici-response', ['uses' => 'Extranetv4\PaymentGatewayController@iciciResponse']);
    $router->post('/razorpay-response', ['uses' => 'Extranetv4\PaymentGatewayController@razorpayResponse']);
    $router->get('/razorpay-cancel/{invoice_id}', ['uses' => 'Extranetv4\PaymentGatewayController@razorpayCancel']);
    $router->post('/paytm-response', ['uses' => 'Extranetv4\PaymentGatewayController@paytmResponse']);
    $router->post('/ccavenue-response', ['uses' => 'Extranetv4\PaymentGatewayController@ccavenueResponse']);
    //pay u server to server response
    $router->get('/payu-s2s-response', ['uses' => 'Extranetv4\payUServer2ServerResponseController@payuServerToServerResponse']);

    //stripe
    $router->post('/stripe-response', ['uses' => 'Extranetv4\PaymentGatewayController@stripeResponse']);
    $router->get('/stripe-error-response', ['uses' => 'Extranetv4\PaymentGatewayController@stripeResponse']);

    //be option routes
    $router->group(['prefix' => 'beopt'], function ($router) {
        $router->post('/be_option', ['uses' => 'Extranetv4\SelectController@beOptionAdd']);
        $router->get('/get_city_bycompany/{company_id}', ['uses' => 'Extranetv4\SelectController@getCityByCompanyId']);
        $router->get('/get_hotel_bycity/{company_id}/{city_id}', ['uses' => 'Extranetv4\SelectController@getHotelByCityIdByCompanyId']);
        //$router->get('/get_opt/{hotel_id}',['uses'=>'Extranetv4\SelectController@getBeOption']);
        $router->post('/enq_form', ['uses' => 'Extranetv4\SelectController@saveEnquaryFromDetails']);
    });

    $router->get('/test-rms-push/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@testPushRms']);

    $router->post('/be/ota-rates', ['uses' => 'Extranetv4\BookingEngineController@getOtaWiseRates']);
    $router->get('/getReviews/{property_id}', ['uses' => 'Extranetv4\BookingEngineController@getReviewFromBookingDotCom']);
    //============================================================================================================================================

    //Added and modified by Jigyans dt - : 22-04-2022

    $router->get('/getInventory/{hotel_id}/{date_from}/{date_to}/{room_type_id}', ['uses' => 'Extranetv4\invrateupdatecontrollers\InventoryController@getInventory']);

    $router->get('/be_getInventory/{hotel_id}/{room_type_id}/{date_from}/{date_to}/{mindays}', ['uses' => 'Extranetv4\InventoryService@getInventeryByRoomTYpe']);
    $router->get('/be_getBooking/{hotel_id}/{room_type_id}/{date_from}/{date_to}', ['uses' => 'Extranetv4\InventoryService@getBookingByRoomtype']);
    $router->get('/get_room_rates_by_room_type/{hotel_id}/{date_from}/{date_to}/{room_type_id}', ['uses' => 'Extranetv4\ManageInventoryController@getRatesByRoomType']);
    //Fetch User Details from Kernel User table and insert the data in crm database using CRON JOB
    $router->get('/FetchFromBe', 'FetchFromBEController@FetchFromBe');

    //============================================================================================================================================

    $router->group(['prefix' => 'manage_inventory'], function ($router) {
        $router->get('/get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}', ['uses' => 'Extranetv4\ManageInventoryController@getInventery']);
        $router->get('/get_inventory_by_hotel/{hotel_id}/{date_from}/{date_to}/{room_type_id}', ['uses' => 'Extranetv4\ManageInventoryController@getInvByHotel']);
        $router->get('/get_inventory_by_room_type/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\ManageInventoryController@getInventeryByRoomtype']);
        $router->post('/inventory_update_swati_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateSwatiControllerSwati@bulkInvUpdate']);
        $router->post('/inventory_update', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);
        $router->get('/get_room_rates/{room_type_id}/{rate_plan_id}/{date_from}/{date_to}', ['uses' => 'Extranetv4\ManageInventoryController@getRates']);
        $router->get('/get_room_rates_by_hotel/{hotel_id}/{date_from}/{date_to}', ['uses' => 'Extranetv4\ManageInventoryController@getRatesByHotel']);
        //$router->get('/get_room_rates_by_room_type/{hotel_id}/{date_from}/{date_to}/{room_type_id}',['uses'=>'Extranetv4\ManageInventoryController@getRatesByRoomType']);
        $router->post('/room_rate_update', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@bulkRateUpdate']);
        $router->post('/update-inv', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@singleInventoryUpdate']);
        $router->post('/update-rates', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@singleRateUpdate']);
        $router->post('/block_inventory', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@blockInvForDateRange']);
        $router->post('/block_rate', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@blockRateUpdate']);
        //test for google ads
        $router->post('/inventory_update_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@bulkInvUpdate']);
        $router->post('/room_rate_update_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@bulkRateUpdate']);
        $router->post('/update-inv_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@singleInventoryUpdate']);
        $router->post('/update-rates_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@singleRateUpdate']);
        $router->post('/block_inventory_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@blockInventoryUpdate']);
        $router->post('/block_rate_test', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@blockRateUpdate']);
    });

    $router->group(['prefix' => 'benewreports'], function ($router) {
        $router->get('/be_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@getRoomNightsByDateRange']);
        $router->get('/be_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@totalRevenueOtaWise']);
        $router->get('/be_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@numberOfBookings']);
        $router->get('/be_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@averageStay']);
        $router->get('/be_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@ratePlanPerformance']);
    });

    $router->group(['prefix' => 'crsnewreports'], function ($router) {
        $router->get('/crs_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@getRoomNightsByDateRange']);
        $router->get('/crs_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@totalRevenueOtaWise']);
        $router->get('/crs_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@numberOfBookings']);
        $router->get('/crs_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@averageStay']);
        $router->get('/crs_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@ratePlanPerformance']);
    });

    //bookign push from bookingengine to gems
    $router->get('/get-be-booking-details', ['uses' => 'Extranetv4\BookingDetailsForGemsController@getBookingDetails']);

    //call from cm to get the current inventory details
    $router->post('/get-be-current-inventory', ['uses' => 'Extranetv4\CallInvServiceFromCmController@getCurrentInventory']);
    $router->post('/update-be-inventory', ['uses' => 'Extranetv4\CallInvServiceFromCmController@updateInventoryInBe']);

    $router->group(['prefix' => 'public_user'], function ($router) {
        $router->post('/post', ['uses' => 'Extranetv4\PublicUserController@userLogin']);
        $router->post('/select_details/{hotel_id}', ['uses' => 'Extranetv4\PublicUserController@selectDetails']);
        $router->post('/cancelation_policy', ['uses' => 'Extranetv4\PublicUserController@cancelationPolicy']);
        $router->post('/cancelation_acepted', ['uses' => 'Extranetv4\PublicUserController@cancelationAccepted']);
        $router->get('/getHotelPolicy/{hotel_id}', ['uses' => 'Extranetv4\PublicUserController@getHotelPolicy']);
        $router->post('/change_mobile_number', ['uses' => 'Extranetv4\PublicUserController@changeUserMobileNumber']);
        $router->get('/fetch_user_login_details/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@fetchUserLoginDetails']);
        $router->post('/fetch_booking_details', ['uses' => 'Extranetv4\PublicUserController@fetchBookingDetails']);
        $router->post('/get_user_booking_list', ['uses' => 'Extranetv4\PublicUserController@getUserBookingList']);
        $router->post('/get_user_cancelled_booking_list', ['uses' => 'Extranetv4\PublicUserController@getUserCancelledBookingList']);
        $router->get('/fetch_mobile_number_change_status/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@changeUserMobileNumberStatus']);
        //Fetch the user details
        $router->get('/fetch_user_login_details/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@fetchUserLoginDetails']);
        $router->post('/fetch_cancelled_bookings', ['uses' => 'Extranetv4\PublicUserController@fetchCancelledBookings']);
    });

    //send otp to the user mobile number
    $router->post('/bookingEngine/send-otp', ['uses' => 'Extranetv4\BookingEngineController@sendOtp']);

    //added to login a user without sending otp to the mobile number
    $router->post('/bookingEngine/send-otp-test', ['uses' => 'Extranetv4\BookingEngineController@sendOtpTest']);

    //hotel Information
    $router->get('/hotel_admin/get_all_hotel_by_id_be/{hotel_id}', ['uses' => 'Extranetv4\RetrieveHotelDetailsController@getAllRunningHotelDataByidBE']);
    $router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}', ['uses' => 'Extranetv4\RetrieveHotelDetailsController@getAllHotelsByCompany']);

    //operation on paymentgetwaydetails
    $router->group(['prefix' => 'paymentgetwaydetails', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/getById/{company_id}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelectById']);
        $router->get('/getByName/{provider_name}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelectByName']);
        $router->get('/getall', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelect']);
        $router->post('/insert', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwayInsert']);
        $router->put('/put/{id}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwayUpdate']);
    });
    //be booking
    $router->get('/booking-data/download/{booking_data}', ['uses' => 'Extranetv4\BookingDetailsDownloadController@getSearchData']);
    $router->post('/get-amenities', ['uses' => 'Extranetv4\BeAmenitiesDisplayController@amenityGroup']);

    $router->get('/gems-booking/{invoice_id}/{gems}/{mail_opt}', ['uses' => 'Extranetv4\BookingEngineController@gemsBooking']);


    $router->get('/gems-booking-test/{invoice_id}/{gems}/{mail_opt}', ['uses' => 'Extranetv4\BookingEngineTestController@gemsBooking']);
    //crs routes
    $router->get('/crs-booking/{invoice_id}/{crs}', ['uses' => 'Extranetv4\BookingEngineController@crsBooking']);
    $router->get('/be-booking-modification/{invoice_id}/{modify}', ['uses' => 'Extranetv4\BookingEngineController@bookingModification']);
    $router->get('/quick-payment-link/{invoice_id}/{quickpayment}', ['uses' => 'Extranetv4\BookingEngineController@quickPaymentLink']);
    $router->get('/crs-booking-test/{invoice_id}/{crs}', ['uses' => 'Extranetv4\BookingEngineTestController@crsBooking']);
    $router->get('/otdc-booking-success/{invoice_id}/{otdc_crs}/{transection_id}', ['uses' => 'Extranetv4\BookingEngineController@otdcBookingSuccess']);
    $router->get('/crs-package-booking-success/{invoice_id}/{package_crs}', ['uses' => 'Extranetv4\BookingEngineController@crsPackageBookingSuccess']);

    // {{routes for BE}}
    $router->group(['prefix' => 'superAdmin-report'], function ($router) {
        $router->get('/getBeBooking/{from_date}/{to_date}/{hotel_id}/{question_id}', 'Extranetv4\BeReportController@totalBeBooking');
    });

    $router->group(['prefix' => 'be-report'], function ($router) {
        $router->get('/last-seven-days/{hotel_id}', ['uses' => 'Extranetv4\BeReportController@noOfLastSevenDaysBEBookings']);
    });
    $router->get('/get-paymentgetway-list/{hotel_id}', ['uses' => 'Extranetv4\BeReportController@paymentgetwayList']);
    $router->get('/get-gems-update/{invoice_id}/{gems}', ['uses' => 'Extranetv4\BookingEngineController@pushBookingToGems']);
    $router->get('/paymentgetway-list', ['uses' => 'Extranetv4\BeReportController@getPaymentGetwayList']);
    $router->get('/commission-download', ['uses' => 'Extranetv4\BeReportController@downloadCommissionBooking']);

    //booking flow

    $router->get('/test-ids-flow', ['uses' => 'Extranetv4\BookingEngineController@testIdsFlow']);
    $router->get('/test-memory', ['uses' => 'Extranetv4\TestController@memoryLength']);

    //cancel booking for booking engine
    $router->post('cancell-booking', ['uses' => 'Extranetv4\BookingEngineCancellationController@cancelBooking']);
    //modify booking for booking engine
    $router->post('be_modification', ['uses' => 'Extranetv4\BookingEngineModificationController@beModification']);
    //change user panel
    //Fetch the user all booking details
    //cancelation policy
    $router->group(['prefix' => 'cancellation_policy'], function ($router) {
        $router->get('/fetch_cancellation_policy/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicy']);
        $router->get('/fetch_cancellation_policy_frontview/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyFrontView']);
        $router->post('/update_cancellation_policy', ['uses' => 'Extranetv4\BookingEngineController@updateCancellationPolicy']);
        $router->get('/fetch_cancel_refund_amount/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancelRefundAmount']);
    });

    //be notification system
    $router->get('be-notifications/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchBENotifications']);
    $router->post('be-notifications-popup', ['uses' => 'Extranetv4\BookingEngineController@updateNotificationPopup']);




    //call to BDT
    $router->get('inr-bdt', ['uses' => 'Extranetv4\CurrencyController@getBDT']);
    $router->post('/get-price-wise-hotel', ['uses' => 'Extranetv4\BeReportController@getPriceWiseHotel']);


    //google ads update files.
    $router->post('inventory-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@inventoryFetchForGoogle']);
    $router->post('rate-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@rateFetchForGoogle']);
    $router->post('coupon-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@couponFetchForGoogle']);

    //fetch notification slider details
    $router->get('fetch-be-notification-slider-images/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchNotificationSliderImage']);

    //notification slider image
    $router->post('upload-be-notification-slider-images/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@uploadNotificationSliderImage']);
    $router->post('insert-update-be-notification-slider-images', ['uses' => 'Extranetv4\BookingEngineController@updateNotificationSliderImage']);
    $router->post('delete-be-notification-slider-image', ['uses' => 'Extranetv4\BookingEngineController@deleteNotificationSliderImage']);

    //bookingjini paymentgateway changes.
    $router->get('bookingjini-pay-gate-details', ['uses' => 'Extranetv4\QuickPaymentLinkController@getBookingjiniPaymentGateway']);

    //fetch Country and State
    $router->get('get-all-country', ['uses' => 'Extranetv4\BELocationDetailsController@getAllCountry']);
    $router->get('get-all-states/{country_id}', ['uses' => 'Extranetv4\BELocationDetailsController@getAllStates']);
    $router->get('get-all-city/{state_id}', ['uses' => 'Extranetv4\BELocationDetailsController@getAllCity']);

    //cancellation policy 
    $router->group(['prefix' => 'be_cancellation_policy'], function ($router) {
        $router->get('/fetch_cancellation_policy_master_data', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyMasterData']);
        //Used in Extranet
        $router->get('/fetch_cancellation_policy/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicy']);
        //Used in FrontView
        $router->get('/fetch_cancellation_policy_frontview/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyFrontView']);
        $router->post('/update_cancellation_policy', ['uses' => 'Extranetv4\BookingEngineController@updateCancellationPolicy']);

        //Used in BE
        $router->get('/fetch_cancel_refund_amount/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancelRefundAmount']);
        //Used in BE

    });

    //coupon testing
    $router->group(['prefix' => 'coupon', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('coupon-add-test', ['uses' => 'Extranetv4\CouponsControllerTest@addNewCoupons']);
        $router->post('coupon-edit-test', ['uses' => 'Extranetv4\CouponsControllerTest@DeleteCoupons']);
        $router->post('coupon-delete-test', ['uses' => 'Extranetv4\CouponsControllerTest@Updatecoupons']);
    });

    //Google hotel ads landing page url.
    $router->group(['prefix' => 'google-hotel'], function ($router) {
        $router->get('/landing', ['uses' => 'Extranetv4\PosController@redirectToBe']);
    });

    //geting live price for website builder
    $router->get('/fetch-room-live-rate/{hotel_id}/{from_date}', ['uses' => 'Extranetv4\InventoryService@fetchLiveDiscountRate']);

    //mailsend for booking engine

    $router->get('/send-mail-sms/{id}', ['uses' => 'Extranetv4\BookingEngineController@invoiceMail']);
    //dynamic pricing rate update in bookingengine

    $router->post('dynamic-pricing-rate-update', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@dynamicPricingRateUpdate']);

    //added by manoj on 01-01-22
    $router->get('/get-payment-gateways', ['uses' => 'Extranetv4\PGSetupController@getPaymentGateways']);
    $router->post('/add-payment-gateway', ['uses' => 'Extranetv4\PGSetupController@addPaymentGateway']);
    $router->post('/update-payment-gateway-status', ['uses' => 'Extranetv4\PGSetupController@updatePaymentGatewayStatus']);
    $router->post('/update-payment-gateway', ['uses' => 'Extranetv4\PGSetupController@updatePaymentGateway']);
    $router->get('/get-active-payment-gateways', ['uses' => 'Extranetv4\PGSetupController@getActivePaymentGateways']);
    $router->post('/get-payment-gateway-parameters', ['uses' => 'Extranetv4\PGSetupController@getPaymentGatewayParameters']);

    $router->get('/get_room_type_rate_plans/{hotel_id}', ['uses' => 'Extranetv4\ManageInventoryController@getRoomTypesAndRatePlans']);
    $router->get('/get_room_type_rate_plans_airbnb/{hotel_id}', ['uses' => 'Extranetv4\ManageInventoryController@getRoomTypesAndRatePlansAirbnb']);

    //ids booking push
    $router->post('/ids-booking-push', ['uses' => 'Extranetv4\UpdateInventoryService@updateIDSBooking']);
    //inventory unblock in specify date range.
    // $router->post('/unblock-specific-daterange',['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateControllerTest@UnblockSpecificDateRange']);

    $router->get('/get-be-version-data', ['uses' => 'Extranetv4\BookingEngineController@getBeVersionData']);

    $router->post('/modify-booking-from-gems', ['uses' => 'Extranetv4\ModifyBookingGemsController@processModifyBookingFromGems']);

    $router->group(['prefix' => 'companyDetails', 'middleware' => 'jwt.auth'], function ($router) {

        $router->put('/put/{company_id}', ['uses' => 'Extranetv4\BookingEngineController@updateCompanyDetails']);
    });

    //hold booking 

    $router->get('/insert-room-mechanism/{invoice_id}/{hotel_id}/{check_in}/{check_out}/{no_of_rooms}', ['uses' => 'Extranetv4\BookingController@RoomBlock']);

    $router->post('/be-cronjob', ['uses' => 'Extranetv4\BookingController@BeCronJob']);

    //new paymentgateway


    $router->post('/add-paymentgateway-setup', ['uses' => 'Extranetv4\PaymentgatwayNewController@AddPaymentgatewaySetup']);

    $router->post('/update-paymentgateway-setup/{id}', ['uses' => 'Extranetv4\PaymentgatwayNewController@EditPaymentgatwaySetup']);

    $router->get('/select-paymentgateway-setup/{hotel_id}/{id}', ['uses' => 'Extranetv4\PaymentgatwayNewController@SelectPaymentgatewaySetup']);

    $router->get('/select-paymentgateway/{hotel_id}', ['uses' => 'Extranetv4\PaymentgatwayNewController@SelectPaymentgateway']);

    $router->get('/select-all-paymentgateway/{hotel_id}', ['uses' => 'Extranetv4\PaymentgatwayNewController@SelectAllPaymentgateway']);

    $router->get('/delect-paymentgateway/{hotel_id}/{provider_name}/{is_active}', ['uses' => 'Extranetv4\PaymentgatwayNewController@inactivePaymentgatway']);


    //added by saroj testing function
    $router->get('/testing', ['uses' => 'Extranetv4\BookingEngineController@testfun']);

    //Stripe payment gateway success and cancle 

    $router->get('/success/{check_sum}/{booking_id}/{booking_date}/{amount}', ['uses' => 'Extranetv4\PaymentGatewayController@StripeSuccessResponse']);

    $router->get('/cancle', ['uses' => 'Extranetv4\PaymentGatewayController@StripeCancelResponse']);

    /* be-web.php rout start */

    /* crs-web.php rout start */
    $router->group(['prefix' => 'crs', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/get_room_rates/{user_id}/{hotel_id}/{date_from}/{date_to}', ['uses' => 'Extranetv4\crs\ManageCrsRatePlanController@getRates']);
        $router->post('/room_rate_update', ['uses' => 'Extranetv4\crs\CrsRoomRatePlanController@roomRateUpdate']);
        $router->post('/inline-update-rates', ['uses' => 'Extranetv4\crs\CrsRoomRatePlanController@inlineUpdateRates']);
        $router->get('/get-inventory/{for_user_id}/{hotel_id}/{date_from}/{date_to}', ['uses' => 'Extranetv4\crs\CrsReservationController@getInventory']);
        $router->post('/reservation', ['uses' => 'Extranetv4\crs\CrsReservationController@newReservation']);
        $router->get('/booked-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}', ['uses' => 'Extranetv4\crs\CrsReservationController@getBookedReservations']);
        $router->get('/booking-transactions/{hotel_id}/{for_user_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\crs\CrsReservationController@getcreditTransactions']);
        $router->get('/confirmed-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}', ['uses' => 'Extranetv4\crs\CrsReservationController@getConfirmedReservations']);
        $router->get('/canceled-reservations/{hotel_id}/{for_user_id}/{from_date}/{to_date}/{type}', ['uses' => 'Extranetv4\crs\CrsReservationController@getCanceledReservations']);
        $router->delete('/reservation/{crs_reserve_id}', ['uses' => 'Extranetv4\crs\CrsReservationController@deleteReservation']);
        $router->post('/cancel-reservation/{crs_reserve_id}', ['uses' => 'Extranetv4\crs\CrsReservationController@cancelReservation']);
        $router->get('/agent-credit/{agent_id}', ['uses' => 'Extranetv4\crs\CrsReservationController@getAgentCredit']);
        $router->post('/agent-reservation', ['uses' => 'Extranetv4\crs\AgentReservationController@newReservation']);
        $router->post('/unblock-inventory', ['uses' => 'Extranetv4\crs\ManageCrsRatePlanController@unBlockInventoryByAgent']);
        $router->post('/block-inventory', ['uses' => 'Extranetv4\crs\ManageCrsRatePlanController@blockInventoryByAgent']);
        $router->get('/agent-credit-hotel/{hotel_id}', ['uses' => 'Extranetv4\crs\CrsReservationController@getAgentCreditByHotel']);


        $router->post('/payment-capture', ['uses' => 'Extranetv4\CrsPaymentCaptureController@crsCapturePayment']);
        $router->get('/payment-capture-list/{invoice_id}', ['uses' => 'Extranetv4\CrsPaymentCaptureController@crsCapturePaymentList']);

        $router->get('/sales-executive/{hotel_id}', ['uses' => 'Extranetv4\SalesExecutiveController@salesExecutive']);
        $router->post('/add-sales-executive', ['uses' => 'Extranetv4\SalesExecutiveController@addSalesExecutive']);
        $router->post('/update-sales-executive/{id}', ['uses' => 'Extranetv4\SalesExecutiveController@UpdateSalesExecutive']);
        $router->post('/update-sales-executive-status', ['uses' => 'Extranetv4\SalesExecutiveController@UpdateSalesExecutiveStatus']);
        $router->get('/active-executive-list/{hotel_id}', ['uses' => 'Extranetv4\SalesExecutiveController@activeExecutiveList']);
    });

    $router->get('/crs/payment/{invoice_id}/{pay_status}', ['uses' => 'Extranetv4\crs\CrsPaymentGatewayController@actionIndex']);
    $router->get('/crs/payment-details/{invoice_id}', ['uses' => 'Extranetv4\crs\CrsPaymentGatewayController@actionData']);
    $router->post('/crs/payu-response', ['uses' => 'Extranetv4\crs\CrsPaymentGatewayController@payuResponse']);
    $router->get('/crs/check-payment-status', ['uses' => 'Extranetv4\crs\CrsReservationController@sendFollowUpEmails']);
    $router->get('/crs/testHtml', ['uses' => 'Extranetv4\crs\CrsReservationController@testHtml']);
    $router->get('/crs/pending_payment/{invoice_id}/{pay_status}', ['uses' => 'Extranetv4\crs\CrsPaymentGatewayController@actionIndex']);

    $router->group(['prefix' => 'rate_plan_settings', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/add', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@addRoomRate']);
        $router->post('/update/{room_rateplan_id}', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@updateRoomRate']);
        $router->get('/get/{hotel_id}', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@getRoomRate']);
        $router->get('/get_byid/{room_rateplan_id}', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@getRoomRateById']);
        $router->delete('/delete/{room_rateplan_id}', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@deleteRoomRate']);
        $router->get('/getall_hotels/{company_id}', ['uses' => 'Extranetv4\crs\RoomRateSettingsController@getAllHotels']);
    });

    $router->get('/test', ['uses' => 'Extranetv4\crs\CrsReservationController@testPushIds']);

    $router->group(['prefix' => 'crs'], function ($router) {
        $router->get('/hotel_details/{company_id}', ['uses' => 'Extranetv4\CrsBookingsController@getHotelDetails']);
        $router->post('/crs_bookings', ['uses' => 'Extranetv4\CrsBookingsController@crsBookings']);
        // $router->post('/crs_bookings-test',['uses'=>'Extranetv4\CrsBookingsTest2Controller@crsBookings']);
        $router->get('/crs_mail/{invoice_id}/{payment_type}', ['uses' => 'Extranetv4\CrsBookingsController@crsBookingMail']);
        $router->get('/crs_mail_test/{invoice_id}/{payment_type}/{mail_type}', ['uses' => 'Extranetv4\CrsBookingsController@crsBookingMailTest']);

        $router->post('/crs_cronjob', ['uses' => 'Extranetv4\CrsBookingsController@crsBookingCronJob']);
        $router->get('/crs_pay/{booking_id}', ['uses' => 'Extranetv4\CrsBookingsController@crsPayBooking']);
        $router->post('/crs_cancel_booking', ['uses' => 'Extranetv4\CrsBookingsController@crsCancelBooking']);
        $router->post('/crs_modify_bookings', ['uses' => 'Extranetv4\CrsBookingsController@crsModifyBooking']);
        $router->post('/crs_modify_bookings-old', ['uses' => 'Extranetv4\CrsBookingsController@crsModifyBookingOld']);
        // $router->post('/crs_modify_bookings_test',['uses'=>'Extranetv4\CrsBookingsTest2Controller@crsModifyBooking']);
        $router->get('/crs_cancel_refund/{invoice_id}', ['uses' => 'Extranetv4\CrsBookingsTest2Controller@crsCancelRefund']);
        $router->post('/crs_register_user_modify', ['uses' => 'Extranetv4\CrsBookingsTest2Controller@crsRegisterUserModify']);
        $router->post('/crs_cancel_details', ['uses' => 'Extranetv4\CrsBookingsTest2Controller@crsCacelReportData']);

        //Added by Saroj
        $router->post('/crs-modified-guest-details', ['uses' => 'Extranetv4\CrsBookingsController@crsModifiedGuestDetails']);
        $router->post('/crs_modify_bookings-new', ['uses' => 'Extranetv4\CrsBookingsController@crsModifyBookingTest']);
        $router->post('/crs-booking-details', ['uses' => 'Extranetv4\CrsBookingsController@crsBookingDetails']);
        $router->post('/crs-booking-details-test', ['uses' => 'Extranetv4\CrsBookingsController@crsBookingDetailsTest']);
    });

    //new Api for crs reservation
    $router->post('/crs-reservation-info', ['uses' => 'Extranetv4\CrsBookingsTest2Controller@crsReservation']);
    $router->post('/crs-reservation-info-test', ['uses' => 'Extranetv4\CrsBookingsController@crsReservation']);
    $router->get('/crs-room-type-count/{hotel_id}/{date_from}/{date_to}/{mindays}', ['uses' => 'Extranetv4\CrsBookingsController@getTotalInvByHotel']);
    $router->get('/crs-room-details/{hotel_id}/{room_type_id_info}', ['uses' => 'Extranetv4\CrsBookingsController@getRoomDetails']);

    //new API for crs cancellation booking inv update redirecting controller
    $router->post('/cm_ota_booking_inv_status', ['uses' => 'Extranetv4\CrsCancelBookingInvUpdateRedirectingController@postDetails']);

    //fetch the user details from mobile number for crs
    $router->post('/get-user-info-crs', ['uses' => 'Extranetv4\CrsBookingsTestController@getNumberWiseUser']);

    //select gst wish company details.
    $router->get('/user-gst/{gst_in}', ['uses' => 'Extranetv4\CrsBookingsController@GstWiseCompanyDetail']);

    $router->get('/gst-view', ['uses' => 'Extranetv4\CrsBookingsController@SelectGstIn']);

    //Added by saroj

    $router->get('/booking-details/{hotel_id}', 'Extranetv4\ListViewBookingsController@dashboardBookingDetails');
    $router->post('/booking-lists', ['uses' => 'Extranetv4\ListViewBookingsController@listViewBookings']);
    $router->post('/booking-lists-test', ['uses' => 'Extranetv4\ListViewBookingsController@listViewBookingsTest']);
    $router->get('/source-list/{hotel_id}', ['uses' => 'Extranetv4\ListViewBookingsController@SourceList']);
    $router->post('/testing', ['uses' => 'Extranetv4\CrsBookingsTestController@filterURL']);

    $router->post('/crs-booking-list', ['uses' => 'Extranetv4\ListViewBookingsController@listViewBookingsCrs']);

    //crs bookings
    $router->post('/get-available-rooms', 'Extranetv4\CrsBookingsController@getAvailableRooms');
    $router->post('/crs-modify-booking', 'Extranetv4\CrsBookingsController@crsModifyBooking');
    $router->get('/get-business-source', 'Extranetv4\CrsBookingsController@businessSource');

    $router->post('/crs-booking-enquiry', 'Extranetv4\CrsBookingsController@crsBookingEnquiry');
    $router->get('/crs-booking-inquery-details/{hotel_id}', 'Extranetv4\CrsBookingsController@crsBookingInqueryDetails');

    $router->get('/crs-today-arrival-report/{hotel_id}', 'Extranetv4\CrsBookingsController@crsTodayArrivalReport');
    $router->get('/crs-today-dispatch-report/{hotel_id}', 'Extranetv4\CrsBookingsController@crsTodayDispatchReport');
    $router->get('/crs-hold-booking-report/{hotel_id}/{checkin}/{checkout}', 'Extranetv4\CrsBookingsController@crsHoldBookingReport');

    //crs report download
    $router->get('/crs-repoprt-download/{hotel_id}/{date}/{payment_type}', 'Extranetv4\CrsBookingsController@CrsReportDownload');
    $router->post('/crs-confirm-booking-report', 'Extranetv4\CrsBookingsController@crsBookingReport');

    /* crs-web.php rout start */
    $router->group(['prefix' => 'bookingEngine', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('/bookings/{api_key}', ['uses' => 'Extranetv4\BookingEngineController@bookings']);
        $router->post('/bookings-test/{api_key}', ['uses' => 'Extranetv4\BookingEngineTestController@bookings']);
    });
    //Coupons
    $router->post('/coupons/check_coupon_code', ['uses' => 'Extranetv4\CouponsController@checkCouponCode']);
    $router->post('/be_coupons/public', ['uses' => 'Extranetv4\CouponsController@GetCouponsPublic']);
    $router->get('/be_coupons/public/test/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\BookingEngineTestController@getAllPublicCupons']);
    //other tax details
    $router->get('/get-other-tax-details/{company_id}/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@getTaxDetails']);
    $router->get('/get-cupons/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\becontroller\BookingEngineController@getAllPublicCupons']);
    //Booking routes
    $router->get('/bookingEngine/get-inventory/{api_key}/{hotel_id}/{date_from}/{date_to}/{currency_name}', ['uses' => 'Extranetv4\BookingEngineController@getInvByHotel']);
    $router->get('/bookingEngine/auth/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccess']);
    //added for testing
    $router->get('/bookingEngine/auth_for/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccess']);


    //added for Booking Widget
    $router->get('/bookingEngine/auth_for_widget/{company_url}', ['uses' => 'Extranetv4\BookingEngineController@getAccessWidget']);

    //end
    $router->get('/bookingEngine/get-room-info/{api_key}/{hotel_id}/{room_type_id}', ['uses' => 'Extranetv4\BookingEngineController@getRoomDetails']);
    $router->get('/bookingEngine/get-hotel-info/{api_key}/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@getHotelDetails']);
    $router->post('/bookingEngine/success-booking', ['uses' => 'Extranetv4\BookingEngineController@successBooking']);
    $router->get('/bookingEngine/get-hotel-logo/{api_key}/{company_id}', ['uses' => 'Extranetv4\BookingEngineController@getHotelLogo']);
    $router->get('/bookingEngine/invoice-details/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@invoiceDetails']);
    $router->get('/bookingEngine/be-calendar/{api_key}/{hotel_id}/{startDate}/{currency_name}', ['uses' => 'Extranetv4\BookingEngineController@beCalendar']);
    $router->get('/bookingEngine/invoice-data/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchInvoiceData']);

    //Payment related routes
    $router->get('/payment/{invoice_id}/{hash}', ['uses' => 'Extranetv4\PaymentGatewayController@actionIndex']);
    $router->post('/payu-fail', ['uses' => 'Extranetv4\PaymentGatewayController@payuResponse']);
    $router->post('/payu-response', ['uses' => 'Extranetv4\PaymentGatewayController@payuResponse']);
    $router->post('/hdfc-response', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcResponse']);
    $router->post('/airpay-response', ['uses' => 'Extranetv4\PaymentGatewayController@airpayResponse']);
    $router->get('/axis-response', ['uses' => 'Extranetv4\PaymentGatewayController@axisResponse']);
    //   $router->post('/axis-request',['uses'=>'Extranetv4\PaymentGatewayController@axisRequest']);
    $router->post('/hdfc-payu-response', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcPayuResponse']);
    $router->post('/hdfc-payu-fail', ['uses' => 'Extranetv4\PaymentGatewayController@hdfcPayuResponse']);
    $router->post('/worldline-response', ['uses' => 'Extranetv4\PaymentGatewayController@worldLineResponse']);
    $router->post('/sslcommerz-response', ['uses' => 'Extranetv4\PaymentGatewayController@sslcommerzResponse']);
    $router->post('/atompay-response', ['uses' => 'Extranetv4\PaymentGatewayController@atompayResponse']);
    $router->post('/icici-response', ['uses' => 'Extranetv4\PaymentGatewayController@iciciResponse']);
    $router->post('/razorpay-response', ['uses' => 'Extranetv4\PaymentGatewayController@razorpayResponse']);
    $router->get('/razorpay-cancel/{invoice_id}', ['uses' => 'Extranetv4\PaymentGatewayController@razorpayCancel']);
    $router->post('/paytm-response', ['uses' => 'Extranetv4\PaymentGatewayController@paytmResponse']);
    $router->post('/ccavenue-response', ['uses' => 'Extranetv4\PaymentGatewayController@ccavenueResponse']);
    //pay u server to server response
    $router->get('/payu-s2s-response', ['uses' => 'Extranetv4\payUServer2ServerResponseController@payuServerToServerResponse']);

    //stripe
    $router->post('/stripe-response', ['uses' => 'Extranetv4\StripeController@stripeResponse']);
    $router->get('/stripe-error-response', ['uses' => 'Extranetv4\StripeController@stripeResponse']);
    $router->get('/stripe', ['uses' => 'Extranetv4\StripeController@Stripe']);
    // $router->get('/success/{check_sum}/{booking_id}/{booking_date}/{amount}',['uses'=>'Extranetv4\StripeController@StripeSuccessResponse']);
    // $router->get('/cancle',['uses'=>'Extranetv4\StripeController@StripeCancelResponse']);


    //be option routes
    $router->group(['prefix' => 'beopt'], function ($router) {
        $router->post('/be_option', ['uses' => 'Extranetv4\SelectController@beOptionAdd']);
        $router->get('/get_city_bycompany/{company_id}', ['uses' => 'Extranetv4\SelectController@getCityByCompanyId']);
        $router->get('/get_hotel_bycity/{company_id}/{city_id}', ['uses' => 'Extranetv4\SelectController@getHotelByCityIdByCompanyId']);
        //$router->get('/get_opt/{hotel_id}',['uses'=>'Extranetv4\SelectController@getBeOption']);
        $router->post('/enq_form', ['uses' => 'Extranetv4\SelectController@saveEnquaryFromDetails']);
    });

    $router->get('/test-rms-push/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@testPushRms']);

    $router->post('/be/ota-rates', ['uses' => 'Extranetv4\BookingEngineController@getOtaWiseRates']);
    $router->get('/getReviews/{property_id}', ['uses' => 'Extranetv4\BookingEngineController@getReviewFromBookingDotCom']);

    $router->get('/getInventory/{hotel_id}/{date_from}/{date_to}/{room_type_id}', ['uses' => 'Extranetv4\invrateupdatecontrollers\InventoryController@getInventory']);

    $router->group(['prefix' => 'benewreports'], function ($router) {
        $router->get('/be_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@getRoomNightsByDateRange']);
        $router->get('/be_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@totalRevenueOtaWise']);
        $router->get('/be_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@numberOfBookings']);
        $router->get('/be_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@averageStay']);
        $router->get('/be_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\BeReportingController@ratePlanPerformance']);
    });

    $router->group(['prefix' => 'crsnewreports'], function ($router) {
        $router->get('/crs_number-of-night/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@getRoomNightsByDateRange']);
        $router->get('/crs_total-amount/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@totalRevenueOtaWise']);
        $router->get('/crs_total-bookings/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@numberOfBookings']);
        $router->get('/crs_average-stay/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@averageStay']);
        $router->get('/crs_rate-plan-performance/{hotel_id}/{checkin}/{checkout}', ['uses' => 'Extranetv4\CrsReportingController@ratePlanPerformance']);
    });

    //bookign push from bookingengine to gems
    $router->get('/get-be-booking-details', ['uses' => 'Extranetv4\BookingDetailsForGemsController@getBookingDetails']);

    //call from cm to get the current inventory details
    $router->post('/get-be-current-inventory', ['uses' => 'Extranetv4\CallInvServiceFromCmController@getCurrentInventory']);
    $router->post('/update-be-inventory', ['uses' => 'Extranetv4\CallInvServiceFromCmController@updateInventoryInBe']);

    $router->group(['prefix' => 'public_user'], function ($router) {
        $router->post('/post', ['uses' => 'Extranetv4\PublicUserController@userLogin']);
        $router->post('/select_details/{hotel_id}', ['uses' => 'Extranetv4\PublicUserController@selectDetails']);
        $router->post('/cancelation_policy', ['uses' => 'Extranetv4\PublicUserController@cancelationPolicy']);
        $router->post('/cancelation_acepted', ['uses' => 'Extranetv4\PublicUserController@cancelationAccepted']);
        $router->get('/getHotelPolicy/{hotel_id}', ['uses' => 'Extranetv4\PublicUserController@getHotelPolicy']);
        $router->post('/change_mobile_number', ['uses' => 'Extranetv4\PublicUserController@changeUserMobileNumber']);
        $router->get('/fetch_user_login_details/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@fetchUserLoginDetails']);
        $router->post('/fetch_booking_details', ['uses' => 'Extranetv4\PublicUserController@fetchBookingDetails']);
        $router->post('/get_user_booking_list', ['uses' => 'Extranetv4\PublicUserController@getUserBookingList']);
        $router->post('/get_user_cancelled_booking_list', ['uses' => 'Extranetv4\PublicUserController@getUserCancelledBookingList']);
        $router->get('/fetch_mobile_number_change_status/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@changeUserMobileNumberStatus']);
        //Fetch the user details
        $router->get('/fetch_user_login_details/{mobile_no}/{company_id}', ['uses' => 'Extranetv4\PublicUserController@fetchUserLoginDetails']);
        $router->post('/fetch_cancelled_bookings', ['uses' => 'Extranetv4\PublicUserController@fetchCancelledBookings']);
    });

    //send otp to the user mobile number
    $router->post('/bookingEngine/send-otp', ['uses' => 'Extranetv4\BookingEngineController@sendOtp']);

    //added to login a user without sending otp to the mobile number
    $router->post('/bookingEngine/send-otp-test', ['uses' => 'Extranetv4\BookingEngineController@sendOtpTest']);

    //hotel Information
    $router->get('/hotel_admin/get_all_hotel_by_id_be/{hotel_id}', ['uses' => 'Extranetv4\RetrieveHotelDetailsController@getAllRunningHotelDataByidBE']);
    $router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}', ['uses' => 'Extranetv4\RetrieveHotelDetailsController@getAllHotelsByCompany']);

    //operation on paymentgetwaydetails
    $router->group(['prefix' => 'paymentgetwaydetails', 'middleware' => 'jwt.auth'], function ($router) {
        $router->get('/getById/{company_id}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelectById']);
        $router->get('/getByName/{provider_name}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelectByName']);
        $router->get('/getall', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwaySelect']);
        $router->post('/insert', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwayInsert']);
        $router->put('/put/{id}', ['uses' => 'Extranetv4\PaymentGetwayAllController@paymentGetwayUpdate']);
    });
    //be booking
    $router->get('/booking-data/download/{booking_data}', ['uses' => 'Extranetv4\BookingDetailsDownloadController@getSearchData']);
    $router->post('/get-amenities', ['uses' => 'Extranetv4\BeAmenitiesDisplayController@amenityGroup']);

    $router->get('/gems-booking/{invoice_id}/{gems}/{mail_opt}', ['uses' => 'Extranetv4\BookingEngineController@gemsBooking']);


    $router->get('/gems-booking-test/{invoice_id}/{gems}/{mail_opt}', ['uses' => 'Extranetv4\BookingEngineTestController@gemsBooking']);
    //crs routes
    $router->get('/crs-booking/{invoice_id}/{crs}', ['uses' => 'Extranetv4\BookingEngineController@crsBooking']);
    $router->get('/be-booking-modification/{invoice_id}/{modify}', ['uses' => 'Extranetv4\BookingEngineController@bookingModification']);
    $router->get('/quick-payment-link/{invoice_id}/{quickpayment}', ['uses' => 'Extranetv4\BookingEngineController@quickPaymentLink']);
    $router->get('/crs-booking-test/{invoice_id}/{crs}', ['uses' => 'Extranetv4\BookingEngineTestController@crsBooking']);
    $router->get('/otdc-booking-success/{invoice_id}/{otdc_crs}/{transection_id}', ['uses' => 'Extranetv4\BookingEngineController@otdcBookingSuccess']);
    $router->get('/crs-package-booking-success/{invoice_id}/{package_crs}', ['uses' => 'Extranetv4\BookingEngineController@crsPackageBookingSuccess']);

    // {{routes for BE}}
    $router->group(['prefix' => 'superAdmin-report'], function ($router) {
        $router->get('/getBeBooking/{from_date}/{to_date}/{hotel_id}/{question_id}', 'Extranetv4\BeReportController@totalBeBooking');
    });

    $router->group(['prefix' => 'be-report'], function ($router) {
        $router->get('/last-seven-days/{hotel_id}', ['uses' => 'Extranetv4\BeReportController@noOfLastSevenDaysBEBookings']);
    });
    $router->get('/get-paymentgetway-list/{hotel_id}', ['uses' => 'Extranetv4\BeReportController@paymentgetwayList']);
    $router->get('/get-gems-update/{invoice_id}/{gems}', ['uses' => 'Extranetv4\BookingEngineController@pushBookingToGems']);
    $router->get('/paymentgetway-list', ['uses' => 'Extranetv4\BeReportController@getPaymentGetwayList']);
    $router->get('/commission-download', ['uses' => 'Extranetv4\BeReportController@downloadCommissionBooking']);

    //booking flow

    $router->get('/test-ids-flow', ['uses' => 'Extranetv4\BookingEngineController@testIdsFlow']);
    $router->get('/test-memory', ['uses' => 'Extranetv4\TestController@memoryLength']);

    //cancel booking for booking engine
    $router->post('cancell-booking', ['uses' => 'Extranetv4\BookingEngineCancellationController@cancelBooking']);
    //modify booking for booking engine
    $router->post('be_modification', ['uses' => 'Extranetv4\BookingEngineModificationController@beModification']);
    //change user panel
    //Fetch the user all booking details
    //cancelation policy
    $router->group(['prefix' => 'cancellation_policy'], function ($router) {
        $router->get('/fetch_cancellation_policy/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicy']);
        $router->get('/fetch_cancellation_policy_frontview/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyFrontView']);
        $router->post('/update_cancellation_policy', ['uses' => 'Extranetv4\BookingEngineController@updateCancellationPolicy']);
        $router->get('/fetch_cancel_refund_amount/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancelRefundAmount']);
    });

    //be notification system
    $router->get('be-notifications/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchBENotifications']);
    $router->post('be-notifications-popup', ['uses' => 'Extranetv4\BookingEngineController@updateNotificationPopup']);




    //call to BDT
    $router->get('inr-bdt', ['uses' => 'Extranetv4\CurrencyController@getBDT']);
    $router->post('/get-price-wise-hotel', ['uses' => 'Extranetv4\BeReportController@getPriceWiseHotel']);


    //google ads update files.
    $router->post('inventory-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@inventoryFetchForGoogle']);
    $router->post('rate-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@rateFetchForGoogle']);
    $router->post('coupon-fetch-to-google-hotel-ads', ['uses' => 'Extranetv4\GoogleHotelAdsInvRateFetchController@couponFetchForGoogle']);

    //fetch notification slider details
    $router->get('fetch-be-notification-slider-images/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchNotificationSliderImage']);

    //notification slider image
    $router->post('upload-be-notification-slider-images/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@uploadNotificationSliderImage']);
    $router->post('insert-update-be-notification-slider-images', ['uses' => 'Extranetv4\BookingEngineController@updateNotificationSliderImage']);
    $router->post('delete-be-notification-slider-image', ['uses' => 'Extranetv4\BookingEngineController@deleteNotificationSliderImage']);

    //bookingjini paymentgateway changes.
    $router->get('bookingjini-pay-gate-details', ['uses' => 'Extranetv4\QuickPaymentLinkController@getBookingjiniPaymentGateway']);

    //fetch Country and State
    $router->get('get-all-country', ['uses' => 'Extranetv4\BELocationDetailsController@getAllCountry']);
    $router->get('get-all-states/{country_id}', ['uses' => 'Extranetv4\BELocationDetailsController@getAllStates']);
    $router->get('get-all-city/{state_id}', ['uses' => 'Extranetv4\BELocationDetailsController@getAllCity']);

    //cancellation policy 
    $router->group(['prefix' => 'be_cancellation_policy'], function ($router) {

        $router->get('/fetch_cancellation_policy_master_data', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyMasterData']);
        //Used in Extranet
        $router->get('/fetch_cancellation_policy/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicy']);
        //Used in FrontView
        $router->get('/fetch_cancellation_policy_frontview/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancellationPolicyFrontView']);
        $router->post('/update_cancellation_policy', ['uses' => 'Extranetv4\BookingEngineController@updateCancellationPolicy']);

        //Used in BE
        $router->get('/fetch_cancel_refund_amount/{invoice_id}', ['uses' => 'Extranetv4\BookingEngineController@fetchCancelRefundAmount']);
        //Used in BE

    });

    $router->post('coupon/coupon-add', ['uses' => 'Extranetv4\CouponsControllerTest@addCoupons']);

    //coupon testing
    $router->group(['prefix' => 'coupon', 'middleware' => 'jwt.auth'], function ($router) {
        $router->post('coupon-add-test', ['uses' => 'Extranetv4\CouponsControllerTest@addNewCoupons']);
        // $router->post('coupon-add',['uses'=>'Extranetv4\CouponsControllerTest@addCoupons']);
        $router->post('coupon-edit-test', ['uses' => 'Extranetv4\CouponsControllerTest@DeleteCoupons']);
        $router->post('coupon-delete-test/{coupon_id}', ['uses' => 'Extranetv4\CouponsControllerTest@Updatecoupons']);
    });

    //Google hotel ads landing page url.
    $router->group(['prefix' => 'google-hotel'], function ($router) {
        $router->get('/landing', ['uses' => 'Extranetv4\PosController@redirectToBe']);
    });

    //geting live price for website builder
    $router->get('/fetch-room-live-rate/{hotel_id}/{from_date}', ['uses' => 'Extranetv4\InventoryService@fetchLiveDiscountRate']);

    //mailsend for booking engine

    $router->get('/send-mail-sms/{id}', ['uses' => 'Extranetv4\BookingEngineController@invoiceMail']);
    //dynamic pricing rate update in bookingengine

    $router->post('dynamic-pricing-rate-update', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@dynamicPricingRateUpdate']);

    //added by manoj on 01-01-22
    $router->get('/get-payment-gateways', ['uses' => 'Extranetv4\PGSetupController@getPaymentGateways']);
    $router->post('/add-payment-gateway', ['uses' => 'Extranetv4\PGSetupController@addPaymentGateway']);
    $router->post('/update-payment-gateway-status', ['uses' => 'Extranetv4\PGSetupController@updatePaymentGatewayStatus']);
    $router->post('/update-payment-gateway', ['uses' => 'Extranetv4\PGSetupController@updatePaymentGateway']);
    $router->get('/get-active-payment-gateways', ['uses' => 'Extranetv4\PGSetupController@getActivePaymentGateways']);
    $router->post('/get-payment-gateway-parameters', ['uses' => 'Extranetv4\PGSetupController@getPaymentGatewayParameters']);

    $router->get('/get_room_type_rate_plans/{hotel_id}', ['uses' => 'Extranetv4\ManageInventoryController@getRoomTypesAndRatePlans']);

    //ids booking push
    $router->post('/ids-booking-push', ['uses' => 'Extranetv4\UpdateInventoryService@updateIDSBooking']);
    //inventory unblock in specify date range.
    $router->post('/unblock-specific-dates', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@UnblockInvForSpecificDates']);

    //inventory unblock in date range.
    $router->post('/unblock-daterange', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@UnblockInvForDateRange']);

    //inventory block specific date range.

    $router->post('/block-specific-dates', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@BlockInvForSpecificDates']);

    $router->group(['middleware' => 'jwt.auth'], function ($router) {
        //rate block specific rate range.
        $router->post('/block-rate-specific-dates', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@BlockRateForSpecificDates']);

        //rate unblock specific rate range.

        $router->post('/unblock-rate-specific-dates', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@UnblockRateForSpecificaDates']);

        //rate unblock  rate range.

        $router->post('/unblock-rate-range', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@UnblockRateForDateRange']);
    });

    //block property 

    $router->post('/block-property', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@BlockProperty']);

    //unblock property 

    $router->post('/unblock-property', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@UnblockProperty']);

    //test kernel route

    $router->get('/test-kernel/{company_id}', ['uses' => 'Extranetv4\KernelTestController@Test']);


    $router->post('/listview-bookings-report', ['uses' => 'Extranetv4\ListViewBookingsController@ListviewBookingsReportDownload']);
    $router->get('/list-bookings-report/{data}', ['uses' => 'Extranetv4\ListViewBookingsController@datatoexcel']);
    // $router->post('/data-to-excel',['uses'=>'Extranetv4\ListViewBookingsController@datatoexcel']);



    $router->post('/get-gems-update/{invoice_id}/{gems}', ['uses' => 'Extranetv4\BookingEngineController@pushBookingToGems']);


    /*new crs routes @author swatishree Date@ 21-09-2022*/
    //Crs 2.0 api devlopment by Swatishee date:03-09-22

    //season detils

    $router->post('/add-season', ['uses' => 'Extranetv4\NewCRS\NewCrsController@addSeason']);

    $router->post('/update-season/{season_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@UpdateSeason']);

    $router->get('/select-all-season/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectAllSeason']);

    $router->get('/deactive-season/{season_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@deActiveSeason']);

    $router->get('/active-season/{season_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@activeSeason']);

    //partner details

    $router->post('/add-partner', ['uses' => 'Extranetv4\NewCRS\NewCrsController@addPartner']);

    $router->post('/update-partner/{id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@updatePartnerDetails']);

    $router->get('/select-all-partner/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@getAllPartnerDetails']);

    $router->get('/select-partner-details/{id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectPartnerDetail']);

    $router->get('/partner-contact-details/{hotel_id}/{contact_no}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectPartnerDetailByContactDetails']);

    $router->get('/partner-gst-details/{hotel_id}/{gstin}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectPartnerByGst']);

    // $router->get('/remove-partner/{hotel_id}/{id}',['uses' => 'Extranetv4\NewCRS\NewCrsController@deletePartner']);

    $router->get('/active-partner/{hotel_id}/{id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@activePartner']);
    $router->get('/remove-partner/{hotel_id}/{id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@deactivatePartner']);
    $router->get('/active-partner-list/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@activePartnerList']);



    //partner master 

    $router->post('/add-partner-master', ['uses' => 'Extranetv4\NewCRS\NewCrsController@addPartnerDetails']);

    $router->get('/select-partnermaster/{id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectPartnerDetails']);

    $router->get('/select-all-partnermaster/{partner_name}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectAllPartnerDetails']);

    // rate plan setup

    $router->post('/add-rate', ['uses' => 'Extranetv4\NewCRS\NewCrsController@addPartnerRatePlanSetup']);

    $router->post('/update-rate', ['uses' => 'Extranetv4\NewCRS\NewCrsController@updatePartnerRatePlanSetup']);

    $router->get('/select-all-rate/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectAllPartnerRatePlanSetup']);

    $router->get('/select-rate/{id}/{hotel_id}/{room_type_id}/{season_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectRatePlan']);

    $router->post('/rate-percentage-cal/{hotel_id}/{room_type_id}/{rate_plan_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@percentageCalculation']);

    $router->post('/get-rate-plan', ['uses' => 'Extranetv4\NewCRS\NewCrsController@getRatePlanData']);

    $router->get('/rate-mapping/{hotel_id}/{partner_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@partnerRateMapping']);

    $router->get('/select-all-partner-rate/{hotel_id}/{room_type_id}/{partner_id}/{season_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@selectPartnerRatePlanList']);

    // $router->post('/crs-booking-list',['uses'=>'Extranetv4\CrsBookingsTestController@listViewBookings']);



    //added by saroj
    //crs report download
    $router->get('/crs-repoprt-download/{hotel_id}/{date}/{payment_type}', 'Extranetv4\NewCRS\CrsBookingsController@CrsReportDownload');
    $router->post('/crs-booking-report', ['uses' => 'Extranetv4\NewCRS\NewCrsReportController@crsBookingReport']);
    $router->get('/crs-booking-report-download/{hotel_id}/{from_date}/{to_date}/{payment_options}/{booking_status}/{agent}', ['uses' => 'Extranetv4\NewCRS\NewCrsReportController@crsBookingReportDownload']);
    $router->get('/crs-recent-bookings/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@crsRecentBookings']);

    $router->get('/check-room-dates', ['uses' => 'Extranetv4\BookingEngineTestController@checkRoomDates']);
    $router->get('/agent-corporate-count/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@agentCorporateCount']);
    $router->get('/booking-revenue-report/{hotel_id}/{duration}', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@bookingRevenueReport']);

    $router->get('/occupancy-percentage/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@occupancyPercentage']);

    $router->get('/occupancy-percentage-test/{hotel_id}/{from_date}/{to_date}', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@occupancyPercentageTest']);


    //saroj patel
    //modify booking
    $router->get('/modify-booking-dates/{hotel_id}/{booking_id}', ['uses' => 'Extranetv4\NewCrsModifyBookingController@modifyBookingDates']);
    $router->post('/fetch-available-rooms', ['uses' => 'Extranetv4\NewCrsModifyBookingController@fetchAvailableRooms']);
    $router->post('/save-modify-booking-dates', ['uses' => 'Extranetv4\NewCrsModifyBookingController@saveModifyBookingDates']);

    $router->get('/guest-details/{hotel_id}/{booking_id}', ['uses' => 'Extranetv4\NewCrsModifyBookingController@guestDetails']);
    $router->post('/save-guest-details', ['uses' => 'Extranetv4\NewCrsModifyBookingController@saveGuestDetails']);

    // $router->post('/fetch-price',['uses'=>'NewCrsModifyBookingController@fetchPrice']);
    // $router->get('/fetch-room-type-details/{hotel_id}/{booking_id}',['uses'=>'Extranetv4\NewCrsModifyBookingController@fetchRoomTypeDetails']);
    // $router->get('/room-rate-details/{hotel_id}',['uses'=>'Extranetv4\NewCrsModifyBookingController@RoomRateDetails']);
    // $router->post('/room-type-availability',['uses'=>'Extranetv4\NewCrsModifyBookingController@roomTypeAvailability']);
    // $router->post('/save-room-type',['uses'=>'Extranetv4\NewCrsModifyBookingController@saveRoomType']);



    //Payu Paymentgateway by Swatishee date:02-10-22
    //PayU Money Paymentgatdeway 

    $router->get('/get-accesstoken', ['uses' => 'Extranetv4\PayuIntegrationController@getToken']); //get token for create merchant
    $router->get('/get-merchant-credencial', ['uses' => 'Extranetv4\PayuIntegrationController@getMerchantCredentialsToken']); //get token for get key and salt
    $router->get('/get-kyc-accesstoken', ['uses' => 'Extranetv4\PayuIntegrationController@getKycToken']); //get token for kyc
    $router->get('/get-esign-accesstoken', ['uses' => 'Extranetv4\PayuIntegrationController@eSignToken']); //get token for e-sign agreement
    //$router->get('/get-refresh-token',['uses' => 'PayuIntegrationController@refreshToken']);
    $router->post('/create-merchant', ['uses' => 'Extranetv4\PayuIntegrationController@createMerchant']);  //create merchant
    $router->post('/get-merchant-status', ['uses' => 'Extranetv4\PayuIntegrationController@getStatus']); //get merchant status
    $router->get('/get-merchant/{access_token}/{mid}', ['uses' => 'Extranetv4\PayuIntegrationController@getMerchant']); //get merchant
    $router->get('/get-merchant-id/{hotel_id}/{mid}', ['uses' => 'Extranetv4\PayuIntegrationController@getMarchantByHotelId']); //get merchant by hotel_id
    $router->post('/upload-aadhaar-doc', ['uses' => 'Extranetv4\PayuIntegrationController@uploadAadhaarXML']); //offline aadhaar upload
    $router->get('/get-merchant-agreement/{mid}', ['uses' => 'Extranetv4\PayuIntegrationController@generateMerchantAgreement']); //get merchant agreement
    $router->post('/esign-merchant-agreement/{mid}', ['uses' => 'Extranetv4\PayuIntegrationController@eSignMerchantAgreement']); // esign mmerchant agreement
    $router->get('/get', ['uses' => 'Extranetv4\PayuIntegrationController@get']); //get merchant agreement

    //mail fire
    $router->post('/send-mail-manual', ['uses' => 'BookingEngineController@manualMailFire']);

    //add child
    $router->post('/insert-child-setup', ['uses' => 'Extranetv4\BookingEngineController@insertDataIntoAddonCharges']);
    $router->post('/update-child-setup/{id}', ['uses' => 'Extranetv4\BookingEngineController@updateDataIntoAddonCharges']);
    $router->get('/select-child-setup/{hotel_id}', ['uses' => 'Extranetv4\BookingEngineController@selectDataIntoAddonCharges']);


    $router->get('/partner-list/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsReportController@partnerList']);


    $router->post('/add-update-policy', ['uses' => 'Extranetv4\NewCRS\NewCrsController@addCanclePolicy']);
    $router->get('/select-policy/{hotel_id}', ['uses' => 'Extranetv4\NewCRS\NewCrsController@getHotelPolicies']);

    //voucher display
    $router->get('/voucher-display/{booking_id}/{source}', ['uses' => 'Extranetv4\VoucherDisplayController@voucherDisplay']);
    $router->post('/voucher-send-mail', ['uses' => 'Extranetv4\VoucherDisplayController@voucherSendMail']);


    $router->post('/min-hotel-price', ['uses' => 'Extranetv4\NewCRS\NewCrsDashboardController@minHotelPrice']);

    //unpaid booking
    //@auther swatishree Date : 23-12-2022
    $router->get('/payu-unpaid/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@payuUnpaidBooking']);
    $router->get('/airpay-unpaid/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@airpayUnpaidBooking']);
    $router->get('/razor-unpaid/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@razorUnpaidBooking']);
    $router->get('/stripe-unpaid/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@stripeUnpaidBooking']);

    //paymentgateway refund
    $router->get('/payu-refund/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@payURefund']);
    $router->get('/airpay-refund/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@airpayRefund']);
    $router->get('/razopay-refund/{tnx_id}', ['uses' => 'Extranetv4\UnpaidBookingController@razorpayRefund']);

    $router->group(['prefix' => 'unpaid-booking'], function ($router) {
        $router->post('/payment-check', ['uses' => 'Extranetv4\UnpaidBookingController@checkstatus']);
        $router->post('/payment-refund', ['uses' => 'Extranetv4\UnpaidBookingController@checkRefundStatus']);
    });



    $router->post('/get-applied-coupon-details', ['uses' => 'Extranetv4\CouponsController@getAppliedCouponDetails']);

    $router->get('/payu-unpaid-testkit/{tnx_id}', ['uses' => 'Extranetv4\TestController@payuUnpaidBookingTestkit']);


    //Google Hotel Center
    $router->group(['prefix' => 'ghc'], function ($router) {
        $router->get('/sync-hotel-list', ['uses' => 'Extranetv4\GoogleHotelCenterController@syncHotelList']);
        $router->get('/hotel-list/{filter}', ['uses' => 'Extranetv4\GoogleHotelCenterController@ghcHotelList']);
        $router->get('/live-status/{hotel_id}/{status}', ['uses' => 'Extranetv4\GoogleHotelCenterController@ghcLiveStatus']);
        $router->get('/pending-hotel-list', ['uses' => 'Extranetv4\GoogleHotelCenterController@ghcPendingHotelList']);
    });

    //to check the hotel is present is ghc or not
    $router->get('/google-hotel-status/{hotel_id}', ['uses' => 'Extranetv4\invrateupdatecontrollers\BookingEngineInvRateController@googleHotelStatusNew']);


    $router->group(['prefix' => 'instant-booking','middleware' => 'jwt.auth'], function ($router) {
        $router->post('/setup', ['uses' => 'Extranetv4\InstantBookingController@setupSave']);
    });
    $router->get('/instant-booking/setup/{hotel_id}', ['uses' => 'Extranetv4\InstantBookingController@setupFetch']);

    $router->group(['prefix' => 'bookings'], function ($router) {
        $router->get('/get-booking-details/{booking_id}', ['uses' => 'Extranetv4\ListViewBookingsController@getBookingDetailsById']);
    });

    $router->post('partner-import', ['uses'=>'Extranetv4\crs\CrsPartnerImportController@partnerImport']);


    
   //promotions
   $router->group(['prefix' => 'promotion'], function($router) {
    $router->post('/add',['uses'=>'BEV3\PromotionsController@addNewPromotions']);
    $router->post('/update/{promotion_id}',['uses'=>'BEV3\PromotionsController@updatePromotions']);
    $router->get('/all/{hotel_id}/{promotion_type}/{status}',['uses'=>'BEV3\PromotionsController@getAllPromotions']);
    $router->get('/details/{promotion_id}',['uses'=>'BEV3\PromotionsController@promotionDetails']);
    $router->get('/active-inactive/{promotion_id}/{status}',['uses'=>'BEV3\PromotionsController@promotionStatusChange']);
    $router->get('/aplicable/{check-in}/{check-out}',['uses' =>'BEV3\PromotionsController@aplicablePromotions']);
    $router->get('/room-rate-plan/{hotel_id}',['uses' =>'BEV3\PromotionsController@getAllHotelRateplan']);
    // $router->post('/aplicable-promotions',['uses' =>'BEV3\PromotionsController@aplicablePromotions']);
 });
    
   //Private Coupons
   $router->group(['prefix' => 'coupons'], function($router) {
    $router->post('/add-private-coupon',['uses'=>'BEV3\PromotionsController@addNewPrivateCoupons']);
    $router->post('/update-private-coupon/{coupon_id}',['uses'=>'BEV3\PromotionsController@UpdatePrivatecoupons']);
    $router->get('/fetch-coupons/{coupon_id}',['uses'=>'BEV3\PromotionsController@fetchPrivateCoupon']);
    $router->get('/all-private-coupons/{hotel_id}',['uses'=>'BEV3\PromotionsController@GetAllPrivateCoupons']);
    $router->delete('/delete-private-coupon/{coupon_id}',['uses'=>'BEV3\PromotionsController@DeletePrivateCoupon']);
    });

    // Cancellation Rules
    $router->post('/update-cancellable-status', ['uses'=>'Extranetv4\HotelCancellationController@updateCancellableStatus']);
    $router->get('/get-cancellable-status/{hotel_id}', ['uses'=>'Extranetv4\HotelCancellationController@getCancellableStatus']);
    $router->post('/add-cancellation-rule', ['uses'=>'Extranetv4\HotelCancellationController@addNewCancellationRules']);
    $router->post('/update-cancellation-rule/{policy_id}', ['uses'=>'Extranetv4\HotelCancellationController@updateCancellationRules']);
    $router->get('/get-cancellation-rules/{hotel_id}', ['uses'=>'Extranetv4\HotelCancellationController@getCancellationRules']); 

});

$router->post('payu/payment-status', ['uses' => 'Extranetv4\QuickPaymentLinkController@checkPaymentLinkStatus']);
$router->post('/promotion/aplicable-promotions',['uses' =>'BEV3\PromotionsController@aplicablePromotions']);


