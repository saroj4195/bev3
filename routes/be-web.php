<?php
/********************************************************/
/********************************************************/
/********************************************************/
////////////////Booking Engine Routes////////////////////////
/********************************************************/
/********************************************************/
/********************************************************/
//Booing Engiene Routes
$router->group(['prefix' => 'bookingEngine','middleware' => 'jwt.auth'], function($router) {
    $router->post('/bookings/{api_key}',['uses'=>'BookingEngineController@bookings']);
    $router->post('/bookings-test/{api_key}',['uses'=>'BookingEngineTestController@bookings']);
});
//Coupons
// $router->post('/coupons/check_coupon_code',['uses'=>'CouponsController@checkCouponCode']);
$router->post('/coupons/check_coupon_code',['uses'=>'CouponsController@checkCouponCodeNew']);
$router->post('/be_coupons/public',['uses'=>'CouponsController@GetCouponsPublic']);
$router->get('/be_coupons/public/{hotel_id}/{from_date}/{to_date}',['uses'=>'BookingEngineController@getAllPublicCupons']);
$router->get('/be_coupons-test/public/{hotel_id}/{from_date}/{to_date}',['uses'=>'BookingEngineController@getAllPublicCuponsTest']);
//other tax details
$router->get('/get-other-tax-details/{company_id}/{hotel_id}',['uses'=>'BookingEngineController@getTaxDetails']);
$router->get('/get-cupons/{hotel_id}/{from_date}/{to_date}',['uses'=>'becontroller\BookingEngineController@getAllPublicCupons']);
//Booking routes
$router->get('/bookingEngine/get-inventory/{api_key}/{hotel_id}/{date_from}/{date_to}/{currency_name}',['uses'=>'BookingEngineController@getInvByHotel']);
$router->get('/bookingEngine/get-inventory-app/{api_key}/{hotel_id}/{date_from}/{date_to}/{currency_name}',['uses'=>'BookingEngineController@getInvByApp']);

$router->get('/bookingEngine/auth/{company_url}',['uses'=>'BookingEngineController@getAccess']);
//added for testing
$router->get('/bookingEngine/auth_for/{company_url}',['uses'=>'BookingEngineController@getAccess']);


//added for Booking Widget
$router->get('/bookingEngine/auth_for_widget/{company_url}',['uses'=>'BookingEngineController@getAccessWidget']);

//end
$router->get('/bookingEngine/get-room-info/{api_key}/{hotel_id}/{room_type_id}',['uses'=>'BookingEngineController@getRoomDetails']);
$router->get('/bookingEngine/get-hotel-info/{api_key}/{hotel_id}',['uses'=>'BookingEngineController@getHotelDetails']);
$router->post('/bookingEngine/success-booking',['uses'=>'BookingEngineController@successBooking']);
$router->get('/bookingEngine/get-hotel-logo/{api_key}/{company_id}',['uses'=>'BookingEngineController@getHotelLogo']);
$router->get('/bookingEngine/invoice-details/{invoice_id}',['uses'=>'BookingEngineController@invoiceDetails']);
$router->get('/bookingEngine/be-calendar/{api_key}/{hotel_id}/{startDate}/{currency_name}',['uses'=>'BookingEngineController@beCalendar']);
$router->get('/bookingEngine/invoice-data/{invoice_id}',['uses'=>'BookingEngineController@fetchInvoiceData']);

// $router->get('/bookingEngine/be-calendar-test/{api_key}/{hotel_id}/{startDate}/{currency_name}',['uses'=>'BookingEngineTestController@beCalendarNew']);

//Payment related routes
$router->get('/payment/{invoice_id}/{hash}',['uses'=>'PaymentGatewayController@actionIndex']);
$router->post('/payu-fail',['uses'=>'PaymentGatewayController@payuResponse']);
$router->post('/payu-response',['uses'=>'PaymentGatewayController@payuResponse']);
$router->post('/hdfc-response',['uses'=>'PaymentGatewayController@hdfcResponse']);
$router->post('/airpay-response',['uses'=>'PaymentGatewayController@airpayResponse']);
$router->get('/axis-response',['uses'=>'PaymentGatewayController@axisResponse']);
//   $router->post('/axis-request',['uses'=>'PaymentGatewayController@axisRequest']);
$router->post('/hdfc-payu-response',['uses'=>'PaymentGatewayController@hdfcPayuResponse']);
$router->post('/hdfc-payu-fail',['uses'=>'PaymentGatewayController@hdfcPayuResponse']);
$router->post('/worldline-response',['uses'=>'PaymentGatewayController@worldLineResponse']);
$router->post('/sslcommerz-response',['uses'=>'PaymentGatewayController@sslcommerzResponse']);
$router->post('/atompay-response',['uses'=>'PaymentGatewayController@atompayResponse']);
$router->post('/icici-response',['uses'=>'PaymentGatewayController@iciciResponse']);
$router->post('/razorpay-response',['uses'=>'PaymentGatewayController@razorpayResponse']);
$router->get('/razorpay-cancel/{invoice_id}',['uses'=>'PaymentGatewayController@razorpayCancel']);
$router->post('/paytm-response',['uses'=>'PaymentGatewayController@paytmResponse']);
$router->post('/ccavenue-response',['uses'=>'PaymentGatewayController@ccavenueResponse']);
//pay u server to server response
$router->get('/payu-s2s-response',['uses'=>'payUServer2ServerResponseController@payuServerToServerResponse']);

//sabpaisa-response

$router->post('/sabpaisa-response/{invoiceId}',['uses'=>'PaymentGatewayController@sabPaisaResponse']);
// $router->post('/ccavenue-response',['uses'=>'PaymentGatewayController@ccavenueResponse']);


$router->post('/easebuzz-response',['uses'=>'PaymentGatewayController@easeBuzzResponse']);

//this url is use for onpage 
$router->post('/onpage-easebuzz-response',['uses'=>'PaymentGatewayController@onpageEaseBuzzResponse']);

//be option routes
$router->group(['prefix'=>'beopt'],function($router){
    $router->post('/be_option',['uses'=>'SelectController@beOptionAdd']);
    $router->get('/get_city_bycompany/{company_id}',['uses'=>'SelectController@getCityByCompanyId']);
    $router->get('/get_hotel_bycity/{company_id}/{city_id}',['uses'=>'SelectController@getHotelByCityIdByCompanyId']);
    //$router->get('/get_opt/{hotel_id}',['uses'=>'SelectController@getBeOption']);
    $router->post('/enq_form',['uses'=>'SelectController@saveEnquaryFromDetails']);
});

$router->get('/test-rms-push/{invoice_id}',['uses'=>'BookingEngineController@testPushRms']);

$router->post('/be/ota-rates',['uses'=>'BookingEngineController@getOtaWiseRates']);
$router->get('/getReviews/{property_id}',['uses'=>'BookingEngineController@getReviewFromBookingDotCom']);

$router->group(['prefix' => 'manage_inventory','middleware' => 'jwt.auth'], function($router) {
    $router->get('/get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInventery']);
    $router->get('/get_inventory_by_hotel/{hotel_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInvByHotel']);
    $router->get('/get_inventory_by_room_type/{hotel_id}/{room_type_id}',['uses'=>'ManageInventoryController@getInventeryByRoomtype']);
    $router->post('/inventory_update',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@bulkInvUpdate']);
    $router->get('/get_room_rates/{room_type_id}/{rate_plan_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRates']);
    $router->get('/get_room_rates_by_hotel/{hotel_id}/{date_from}/{date_to}',['uses'=>'ManageInventoryController@getRatesByHotel']);
    $router->get('/get_room_rates_by_room_type/{hotel_id}/{date_from}/{date_to}/{room_type_id}',['uses'=>'ManageInventoryController@getRatesByRoomType']);
    $router->post('/room_rate_update',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@bulkRateUpdate']);
    $router->post('/update-inv',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@singleInventoryUpdate']);
    $router->post('/update-rates',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@singleRateUpdate']);
    $router->post('/block_inventory',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@blockInventoryUpdate']);
    $router->post('/block_rate',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@blockRateUpdate']);
    //test for google ads
    $router->post('/inventory_update_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@bulkInvUpdate']);
    $router->post('/room_rate_update_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@bulkRateUpdate']);
    $router->post('/update-inv_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@singleInventoryUpdate']);
    $router->post('/update-rates_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@singleRateUpdate']);
    $router->post('/block_inventory_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@blockInventoryUpdate']);
    $router->post('/block_rate_test',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateControllerTest@blockRateUpdate']);
    });

    $router->group(['prefix'=>'benewreports'],function($router){
    $router->get('/be_number-of-night/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeReportingController@getRoomNightsByDateRange']);
    $router->get('/be_total-amount/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeReportingController@totalRevenueOtaWise']);
    $router->get('/be_total-bookings/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeReportingController@numberOfBookings']);
    $router->get('/be_average-stay/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeReportingController@averageStay']);
    $router->get('/be_rate-plan-performance/{hotel_id}/{checkin}/{checkout}',['uses'=>'BeReportingController@ratePlanPerformance']);
});

$router->group(['prefix'=>'crsnewreports'],function($router){
    $router->get('/crs_number-of-night/{hotel_id}/{checkin}/{checkout}',['uses'=>'CrsReportingController@getRoomNightsByDateRange']);
    $router->get('/crs_total-amount/{hotel_id}/{checkin}/{checkout}',['uses'=>'CrsReportingController@totalRevenueOtaWise']);
    $router->get('/crs_total-bookings/{hotel_id}/{checkin}/{checkout}',['uses'=>'CrsReportingController@numberOfBookings']);
    $router->get('/crs_average-stay/{hotel_id}/{checkin}/{checkout}',['uses'=>'CrsReportingController@averageStay']);
    $router->get('/crs_rate-plan-performance/{hotel_id}/{checkin}/{checkout}',['uses'=>'CrsReportingController@ratePlanPerformance']);
});

//bookign push from bookingengine to gems
$router->get('/get-be-booking-details',['uses'=>'BookingDetailsForGemsController@getBookingDetails']);

//call from cm to get the current inventory details
$router->post('/get-be-current-inventory',['uses'=>'CallInvServiceFromCmController@getCurrentInventory']);
$router->post('/update-be-inventory',['uses'=>'CallInvServiceFromCmController@updateInventoryInBe']);

$router->group(['prefix'=>'public_user'],function($router){
    $router->post('/post',['uses'=>'PublicUserController@userLogin']);
    $router->post('/select_details/{hotel_id}',['uses'=>'PublicUserController@selectDetails']);
    $router->post('/cancelation_policy',['uses'=>'PublicUserController@cancelationPolicy']);
    $router->post('/cancelation_acepted',['uses'=>'PublicUserController@cancelationAccepted']);
    $router->get('/getHotelPolicy/{hotel_id}',['uses'=>'PublicUserController@getHotelPolicy']);
    $router->post('/change_mobile_number',['uses'=>'PublicUserController@changeUserMobileNumber']);
    $router->get('/fetch_user_login_details/{mobile_no}/{company_id}',['uses'=>'PublicUserController@fetchUserLoginDetails']);
    $router->post('/fetch_booking_details',['uses'=>'PublicUserController@fetchBookingDetails']);
    $router->post('/get-booking-details',['uses'=>'PublicUserController@getBEBookingDetails']);
    $router->post('/upcoming-bookings',['uses'=>'PublicUserController@upcomingBookings']);
    $router->post('/cancelled-bookings',['uses'=>'PublicUserController@cancelledBookings']);
    $router->post('/completed-bookings',['uses'=>'PublicUserController@completedBookings']);
    $router->post('/get_user_booking_list',['uses'=>'PublicUserController@getUserBookingList']);
    $router->post('/get_user_cancelled_booking_list',['uses'=>'PublicUserController@getUserCancelledBookingList']);
    $router->get('/fetch_mobile_number_change_status/{mobile_no}/{company_id}',['uses'=>'PublicUserController@changeUserMobileNumberStatus']);
    //Fetch the user details
    $router->get('/fetch_user_login_details/{mobile_no}/{company_id}',['uses'=>'PublicUserController@fetchUserLoginDetails']);
    $router->post('/fetch_cancelled_bookings',['uses'=>'PublicUserController@fetchCancelledBookings']);

    $router->post('/fetch_guest_booking_details/{type}',['uses'=>'PublicUserController@fetchGuestBookings']);

});

//send otp to the user mobile number
$router->post('/bookingEngine/send-otp',['uses'=>'BookingEngineController@sendOtp']);

//added to login a user without sending otp to the mobile number
$router->post('/bookingEngine/send-otp-test',['uses'=>'BookingEngineController@sendOtpTest']);

//hotel Information
$router->get('/hotel_admin/get_all_hotel_by_id_be/{hotel_id}',['uses'=>'RetrieveHotelDetailsController@getAllRunningHotelDataByidBE']);
$router->get('/hotel_admin/hotels_by_company/{comp_hash}/{company_id}',['uses'=>'RetrieveHotelDetailsController@getAllHotelsByCompany']);

$router->get('/package/hotels_by_company/{comp_hash}/{company_id}',['uses'=>'RetrieveHotelDetailsController@getAllHotelsByCompanywithPackage']);

//operation on paymentgetwaydetails
$router->group(['prefix'=>'paymentgetwaydetails','middleware' => 'jwt.auth'],function($router){
    $router->get('/getById/{company_id}',['uses'=>'PaymentGetwayAllController@paymentGetwaySelectById']);
    $router->get('/getByName/{provider_name}',['uses'=>'PaymentGetwayAllController@paymentGetwaySelectByName']);
    $router->get('/getall',['uses'=>'PaymentGetwayAllController@paymentGetwaySelect']);
    $router->post('/insert',['uses'=>'PaymentGetwayAllController@paymentGetwayInsert']);
    $router->put('/put/{id}',['uses'=>'PaymentGetwayAllController@paymentGetwayUpdate']);
});
//be booking
$router->get('/booking-data/download/{booking_data}',['uses'=>'BookingDetailsDownloadController@getSearchData']);
$router->post('/get-amenities',['uses'=>'BeAmenitiesDisplayController@amenityGroup']);

$router->get('/gems-booking/{invoice_id}/{gems}/{mail_opt}',['uses'=>'BookingEngineController@gemsBooking']);


$router->get('/gems-booking-test/{invoice_id}/{gems}/{mail_opt}',['uses'=>'BookingEngineTestController@gemsBooking']);
//crs routes
$router->get('/crs-booking/{invoice_id}/{crs}',['uses'=>'BookingEngineController@crsBooking']);
$router->get('/be-booking-modification/{invoice_id}/{modify}',['uses'=>'BookingEngineController@bookingModification']);
$router->get('/quick-payment-link/{invoice_id}/{quickpayment}',['uses'=>'BookingEngineController@quickPaymentLink']);
$router->get('/crs-booking-test/{invoice_id}/{crs}',['uses'=>'BookingEngineTestController@crsBooking']);
$router->get('/otdc-booking-success/{invoice_id}/{otdc_crs}/{transection_id}',['uses'=>'BookingEngineController@otdcBookingSuccess']);
$router->get('/crs-package-booking-success/{invoice_id}/{package_crs}',['uses'=>'BookingEngineController@crsPackageBookingSuccess']);

$router->get('/pay-at-hotel-success/{invoice_id}/{pay_at_hotel}',['uses'=>'BookingEngineController@payAtHotelBooking']);


// {{routes for BE}}
$router->group(['prefix'=>'superAdmin-report'],function($router){
    $router->get('/getBeBooking/{from_date}/{to_date}/{hotel_id}/{question_id}','BeReportController@totalBeBooking');
});

$router->group(['prefix' => 'be-report'],function($router){
    $router->get('/last-seven-days/{hotel_id}',['uses'=>'BeReportController@noOfLastSevenDaysBEBookings']);
    $router->get('/bookings-between-days/{hotel_id}/{from_date}/{to_date}',['uses'=>'BeReportController@noOfBEBookingsBetweenDays']);
    $router->get('/bookings-between-dates/{hotel_id}/{from_date}/{to_date}/{date_type}',['uses'=>'BeReportController@noOfBEBookingsBetweenDates']);
});
$router->get('/get-paymentgetway-list/{hotel_id}',['uses'=>'BeReportController@paymentgetwayList']);
$router->get('/get-gems-update/{invoice_id}/{gems}',['uses'=>'BookingEngineController@pushBookingToGems']);
$router->get('/paymentgetway-list',['uses'=>'BeReportController@getPaymentGetwayList']);
$router->get('/commission-download',['uses'=>'BeReportController@downloadCommissionBooking']);

//booking flow

$router->get('/test-ids-flow',['uses'=>'BookingEngineController@testIdsFlow']);
$router->get('/test-memory',['uses'=>'TestController@memoryLength']);

//cancel booking for booking engine
$router->post('cancell-booking',['uses'=>'BookingEngineCancellationController@cancelBooking']);
//modify booking for booking engine
$router->post('be_modification',['uses'=>'BookingEngineModificationController@beModification']);
//change user panel
//Fetch the user all booking details
//cancelation policy
$router->group(['prefix'=>'cancellation_policy'],function($router){
    $router->get('/fetch_cancellation_policy/{hotel_id}',['uses'=>'BookingEngineController@fetchCancellationPolicy']);
    $router->get('/fetch_cancellation_policy_frontview/{hotel_id}',['uses'=>'BookingEngineController@fetchCancellationPolicyFrontView']);
    $router->post('/update_cancellation_policy',['uses'=>'BookingEngineController@updateCancellationPolicy']);
    $router->get('/fetch_cancel_refund_amount/{invoice_id}',['uses'=>'BookingEngineController@fetchCancelRefundAmount']);
});

//be notification system
$router->get('be-notifications/{hotel_id}',['uses'=>'BookingEngineController@fetchBENotifications']);
$router->post('be-notifications-popup',['uses'=>'BookingEngineController@updateNotificationPopup']);




//call to BDT
$router->get('inr-bdt',['uses'=>'CurrencyController@getBDT']);
$router->post('/get-price-wise-hotel',['uses'=>'BeReportController@getPriceWiseHotel']);


//google ads update files.
$router->post('inventory-fetch-to-google-hotel-ads',['uses'=>'GoogleHotelAdsInvRateFetchController@inventoryFetchForGoogle']);
$router->post('rate-fetch-to-google-hotel-ads',['uses'=>'GoogleHotelAdsInvRateFetchController@rateFetchForGoogle']);
$router->post('coupon-fetch-to-google-hotel-ads',['uses'=>'GoogleHotelAdsInvRateFetchController@couponFetchForGoogle']);

//fetch notification slider details
$router->get('fetch-be-notification-slider-images/{hotel_id}',['uses'=>'BookingEngineController@fetchNotificationSliderImage']);

//notification slider image
$router->post('upload-be-notification-slider-images/{hotel_id}',['uses'=>'BookingEngineController@uploadNotificationSliderImage']);
$router->post('insert-update-be-notification-slider-images',['uses'=>'BookingEngineController@updateNotificationSliderImage']);
$router->post('delete-be-notification-slider-image',['uses'=>'BookingEngineController@deleteNotificationSliderImage']);

//bookingjini paymentgateway changes.
$router->get('bookingjini-pay-gate-details',['uses'=>'QuickPaymentLinkController@getBookingjiniPaymentGateway']);

//fetch Country and State
$router->get('get-all-country',['uses'=>'BELocationDetailsController@getAllCountry']);
$router->get('get-all-states/{country_id}',['uses'=>'BELocationDetailsController@getAllStates']);
$router->get('get-all-city/{state_id}',['uses'=>'BELocationDetailsController@getAllCity']);

//cancellation policy 
$router->group(['prefix'=>'be_cancellation_policy'],function($router){

    $router->get('/fetch_cancellation_policy_master_data',['uses'=>'BookingEngineController@fetchCancellationPolicyMasterData']);
    //Used in Extranet
    $router->get('/fetch_cancellation_policy/{hotel_id}',['uses'=>'BookingEngineController@fetchCancellationPolicy']);
    //Used in FrontView
    $router->get('/fetch_cancellation_policy_frontview/{hotel_id}',['uses'=>'BookingEngineController@fetchCancellationPolicyFrontView']);
    $router->post('/update_cancellation_policy',['uses'=>'BookingEngineController@updateCancellationPolicy']);

    //Used in BE
    $router->get('/fetch_cancel_refund_amount/{invoice_id}',['uses'=>'BookingEngineController@fetchCancelRefundAmount']);
    //Used in BE

});

//coupon testing
$router->group(['prefix'=>'coupon','middleware' => 'jwt.auth'],function($router){
        $router->post('coupon-add-test',['uses'=>'CouponsControllerTest@addNewCoupons']);
        $router->post('coupon-edit-test',['uses'=>'CouponsControllerTest@DeleteCoupons']);
        $router->post('coupon-delete-test',['uses'=>'CouponsControllerTest@Updatecoupons']);
});

//Google hotel ads landing page url.
$router->group(['prefix' => 'google-hotel'], function($router) {
    $router->get('/landing',['uses'=>'PosController@redirectToBe']);
});

//geting live price for website builder
$router->get('/fetch-room-live-rate/{hotel_id}/{from_date}',['uses' => 'InventoryService@fetchLiveDiscountRate']);

//mailsend for booking engine

$router->get('/send-mail-sms/{id}',['uses' => 'BookingEngineController@invoiceMail']);
//dynamic pricing rate update in bookingengine

$router->post('dynamic-pricing-rate-update',['uses'=>'invrateupdatecontrollers\BookingEngineInvRateController@dynamicPricingRateUpdate']);

//added by manoj on 01-01-22
$router->get('/get-payment-gateways',['uses' => 'PGSetupController@getPaymentGateways']);
$router->post('/add-payment-gateway',['uses' => 'PGSetupController@addPaymentGateway']);
$router->post('/update-payment-gateway-status',['uses' => 'PGSetupController@updatePaymentGatewayStatus']);
$router->post('/update-payment-gateway',['uses' => 'PGSetupController@updatePaymentGateway']);
$router->get('/get-active-payment-gateways',['uses' => 'PGSetupController@getActivePaymentGateways']);
$router->post('/get-payment-gateway-parameters',['uses' => 'PGSetupController@getPaymentGatewayParameters']);

$router->get('/get_room_type_rate_plans/{hotel_id}',['uses'=>'ManageInventoryController@getRoomTypesAndRatePlans']);

//ids booking push
$router->post('/ids-booking-push',['uses' => 'UpdateInventoryService@updateIDSBooking']);

//Gems modification booking.
$router->post('/modify-booking-from-gems',['uses' => 'ModifyBookingGemsController@processModifyBookingFromGems']);


//crs cancel mail fire 

$router->get('/crs-cancel-mail/{invoice_id}/{payment_type}/{mail_type}',['uses'=>'CrsBookingsController@crsBookingMail']);

$router->get('/get-be-version-data',['uses'=>'BookingEngineController@getBeVersionData']);

//unpaid booking report @author Swati Date: 08-07-2022
$router->get('/download-report/{hotel_id}',['uses'=>'MailInvoiceController@UnpaidBookingReportDownload']);

//convenienceFee routes @ranjit @date : 25-07-2022
$router->get('/add-on-charges/{hotel_id}',['uses'=>'BookingEngineController@addOnCharges']);

$router->get('/get-inventory/{api_key}/{hotel_id}/{date_from}/{date_to}/{currency_name}',['uses'=>'BookingEngineTestController@getInvByHotelTest']);

$router->get('/bookingEngine/ghc-test',['uses'=>'BookingEngineTestController@ghcTest']);

$router->post('cancell-booking-test',['uses'=>'BookingEngineCancellationController@bookingCancelTest']);

//Stripe payment gateway success and cancle 

$router->get('/success/{check_sum}/{booking_id}/{booking_date}/{amount}',['uses'=>'PaymentGatewayController@StripeSuccessResponse']);

$router->get('/cancel',['uses'=>'PaymentGatewayController@StripeCancelResponse']);


//added by saroj
$router->group(['prefix'=>'b2c'],function($router){
    $router->post('/bookinglist/{type}',['uses' =>'B2CController@bookingList']);
    $router->get('/booking-details/{booking_id}',['uses' =>'B2CController@BookingDetails']);
    $router->post('/min-rates',['uses' =>'BookingEngineTestController@minRates']);
});


//added by manoranjan
$router->post('/testRatebydate',['uses'=>'TestController@testGetRatesByRoomnRatePlan']);

$router->post('/payment-link-easybuzz',['uses'=>'BookingEngineTestController@paymentLinkEasybuzz']);
$router->post('/easybuzz/payment-status',['uses'=>'QuickPaymentLinkController@paymentEasyCollect']);

$router->get('/razorpay-orderid/{invoice_id}',['uses'=>'PaymentGatewayController@RazorpayOrderId']);
$router->post('/onpage-razorpay-response',['uses'=>'PaymentGatewayController@onpageRazorpayResponse']);

$router->get('/easebuzz/access-key/{invoice_id}',['uses'=>'PaymentGatewayController@easebuzzAccesskey']);
$router->get('day-booking/easebuzz/access-key/{invoice_id}',['uses'=>'DayBookingPaymentGatewayController@dayBookingEasebuzzAccesskey']);



