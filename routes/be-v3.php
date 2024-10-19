<?php
/********************************************************/
////////////////Booking Engine V3 Routes////////////////////////
/********************************************************/

$router->group(['prefix' => 'BEV3'], function($router) {
});
$router->post('/be/ota-rates',['uses'=>'BEV3\TestingController@getOtaWiseRates']);

//bookings api
$router->post('/bookings/{api_key}',['uses'=>'BEV3\BookingController@bookings']);
$router->post('/bookings-test/{api_key}',['uses'=>'BookingEngineTestController@bookingsTest']);
$router->post('/bookings-testing/{api_key}',['uses'=>'BEV3\TestingController@bookingsTestNew']);

//Get Inv api
$router->get('/currency', ['uses' => 'BEV3\InventoryRatesController@currency']);
$router->get('/footer/payment-icon', ['uses' => 'BEV3\InventoryRatesController@paymentIcon']);

$router->get('/min-rates/{hotel_id}/{date_from}/{base_currency}/{currency}',['uses' =>'BEV3\InventoryRatesController@minRates']);
$router->get('/test-min-rates/{hotel_id}/{date_from}/{base_currency}/{currency}',['uses' =>'BEV3\InventoryRatesController@minRatesTest']);
$router->post('/get-inventory',['uses'=>'BEV3\InventoryRatesController@getInvByHotel']);
$router->post('/get-inventory-test',['uses'=>'BEV3\InventoryRatesController@getInvByHotelTest']);
$router->get('/hotel-amenities/{hotel_id}', ['uses' => 'BEV3\InventoryRatesController@hotelAmenities']);
$router->post('/get-inventory-by-hotel-test',['uses'=>'BEV3\TestingController@getInvByHotelTest']);

$router->post('/user-signin', ['uses' => 'BEV3\UserAuthController@userSignIn']);
$router->post('/send-otp', ['uses' => 'BEV3\UserAuthController@sendOtp']);
$router->post('/verify-otp', ['uses' => 'BEV3\UserAuthController@verifyOtp']);

$router->group(['middleware' => 'jwt.auth'],function($router){
    $router->post('/guest-booking-list', ['uses' => 'BEV3\UserAuthController@fetchGuestBookingList']);
    $router->post('/guest-info', ['uses' => 'BEV3\UserAuthController@guestInfo']);
});

$router->post('/check-private-coupon', ['uses' => 'BEV3\CouponsController@checkPrivateCoupon']);
$router->get('/booking-invoice-details/{invoice_id}', ['uses' => 'BEV3\BookingController@bookingInvoiceDetails']);
$router->get('/package-booking-invoice-details/{invoice_id}', ['uses' => 'BEV3\PackageBookingController@packageBookingInvoiceDetails']);
$router->post('/alternative-dates-by-roomtype', ['uses' => 'BEV3\InventoryRatesController@altAvailableDatesByroomTypes']);

$router->get('/get-hotel-info/{api_key}/{hotel_id}',['uses'=>'BEV3\BookingController@getHotelDetails']);
$router->post('/package-bookings/{api_key}',['uses'=>'BEV3\PackageBookingController@packageBookings']);
$router->get('/download-invoice-details/{invoice_id}',['uses'=>'BEV3\BookingController@downloadInvoiceDetails']);
$router->post('/pms-bookings-testing/{api_key}',['uses'=>'BEV3\PmsBookingController@bookings']);

$router->post('/instamojo/access-token',['uses'=>'BEV3\PmsBookingController@instamojoAccessToken']);
$router->get('/phonepay-request',['uses'=>'BEV3\TestingController@phonepayRequest']);
$router->post('/phonepay-response',['uses'=>'BEV3\TestingController@phonepayResponse']);
$router->get('/repush-ids-booking/{invoice_id}',['uses'=>'BEV3\BookingController@repushIdsBooking']);

$router->get('/get-hotel-banner/{company_id}',['uses'=>'BEV3\BasicSetupController@getHotelbanner']);

$router->post('/phonepay-response',['uses'=>'BEV3\TestingController@phonepayResponse']);
// $router->post('/guest-booking-list',['uses'=>'BEV3\TestingController@fetchGuestBookingList']);
// $router->get('/paymentgateway-hotel-details',['uses'=>'BEV3\TestingController@paymentGatewayHotelDetails']);
$router->get('/min-rates-testing/{hotel_id}/{date_from}/{base_currency}/{currency}',['uses' =>'BEV3\TestingController@minRatetest']);
$router->post('/connected-ota-rates',['uses'=>'BEV3\InventoryRatesController@connectedOtaRates']);

//
$router->post('/check-cancel-eligibility', ['uses'=>'BEV3\RoomNightCancellationController@checkCancelEligibility']);
$router->post('/create-refund', ['uses'=>'BEV3\RoomNightCancellationController@createRefund']);
$router->post('/check-refund-status', ['uses'=>'BEV3\RoomNightCancellationController@checkRefundStatus']);




$router->post('/get-inventory-rate',['uses'=>'BEV3\TestingController@getInvByHotelTest']);







