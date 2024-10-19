<?php

//For Group Website
$router->get('/group-hotel-list/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelList']);
$router->get('/query/{group_id}/{query_text}', ['uses' => 'hotel_chain\HotelController@getQueryResult']);
// $router->get('/query-by-area/{group_id}/{area_name}', ['uses' => 'hotel_chain\HotelController@getQueryResult']);
$router->get('/filter', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList']);
$router->get('/filter2', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList2']);
$router->get('/filter3', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList3']);
$router->get('/filter4', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList4']);
$router->get('/filter5', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList5']);
$router->get('/filter6', ['uses' => 'hotel_chain\HotelController@getFilteredHotelList6']);
$router->get('/hotel-details', ['uses' => 'hotel_chain\HotelController@getHotelDetails']);
$router->get('/group-package-list/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupPackageList']);
$router->get('/group-package-hotel-list', ['uses' => 'hotel_chain\HotelController@getPackageHotelList']);
$router->get('/group-package-hotel-list-by-destination', ['uses' => 'hotel_chain\HotelController@getPackageHotelListByDestination']);
$router->get('/group-destination-list/{group_id}/{filter}', ['uses' => 'hotel_chain\HotelController@getGroupHotelDestinations']);
$router->get('/group-city-list/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelCities']);
$router->get('/group-hotels-by-city/{group_id}/{city_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelsByCity']);
$router->get('/group-city-and-hotels/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelCityAndHotels']);
$router->get('/group-hotel-amenities/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelAmenities']);
$router->get('/check-availability', ['uses' => 'hotel_chain\HotelController@checkAvailability']);
$router->get('/get-available-hotel-list', ['uses' => 'hotel_chain\HotelController@checkAvailabilityByCityAndDates']);
$router->get('/get-hotel-availability', ['uses' => 'hotel_chain\HotelController@checkAvailabilityByHotelID']);
$router->get('/get-available-group-hotel-list', ['uses' => 'hotel_chain\HotelController@checkGroupAvailabilityByCityAndDates']);
$router->get('/group-hotels-categories/{group_id}', ['uses' => 'hotel_chain\HotelController@getGroupHotelsCategory']);
$router->get('/hotel-faqs/{hotel_id}', ['uses' => 'hotel_chain\HotelController@getHotelFAQ']); 
$router->get('/package-banners/{group_id}', ['uses' => 'hotel_chain\HotelController@getPackageBanners']); 
//$router->get('/get-hotels-by-category/{group_id}/{category_name}', ['uses' => 'hotel_chain\HotelController@getGroupHotelsByCategory']);

$router->get('/popular-hotels/{group_id}', ['uses' => 'hotel_chain\HotelController@getPopularHotels']); 
$router->get('/recent-viewed-hotels/{group_id}/{user_id}', ['uses' => 'hotel_chain\HotelController@getRecentViewedHotels']); 
$router->get('/popular-destinations/{group_id}', ['uses' => 'hotel_chain\HotelController@getPopularDestinations']); 
$router->get('/booking-engine-iframe/{hotel_id}/{checkin_date}/{checkout_date}', ['uses' => 'hotel_chain\HotelController@getBEIFrameURL']); 
$router->get('/booking-engine-url/{hotel_id}', ['uses' => 'hotel_chain\HotelController@openBEURL']); 


$router->get('/ecoretreatbookings', ['uses' => 'hotel_chain\BookingsController@ecoretreatBookings']);
$router->get('/agentbookings', ['uses' => 'hotel_chain\BookingsController@getOTDCAgentBookings']);
$router->get('/groupbookings', ['uses' => 'hotel_chain\BookingsController@getOTDCGroupBookings']);
$router->get('/otabookings', ['uses' => 'hotel_chain\BookingsController@getOTDCOTABookings']);
$router->get('/occupancy', ['uses' => 'hotel_chain\BookingsController@getOTDCOccupancy']);
$router->get('/viewinvoice/{invoice_id}', ['uses' => 'hotel_chain\BookingsController@getInvoice']);
$router->get('/bookingsbydate/{booking_date}', ['uses' => 'hotel_chain\BookingsController@getOTDCBookingsByDate']);
$router->get('/bookingsbyunitanddate/{booking_date}/{hotel_id}', ['uses' => 'hotel_chain\BookingsController@getOTDCBookingsByHotelIDAndDate']);
$router->get('/bookingsbystaydate/{stay_date}', ['uses' => 'hotel_chain\BookingsController@getOTDCBookingsByStayDate']);
$router->get('/invoices/{unit_code}', ['uses' => 'hotel_chain\BookingsController@getOTDCBookingsInvoices']);
$router->get('/crsbookings', ['uses' => 'hotel_chain\BookingsController@getOTDCCRSBookings']);
$router->get('/otabookingdetails', ['uses' => 'hotel_chain\BookingsController@getOTABookingDetails']);
$router->get('/cancelledbookings', ['uses' => 'hotel_chain\BookingsController@getWebsiteCancelledBookings']);
$router->get('/bookingamount/{start_date}/{end_date}', ['uses' => 'hotel_chain\BookingsController@getOTDCBookingAmountByCheckinDate']);
$router->get('/cancellationPercentage', ['uses' => 'hotel_chain\BookingsController@cancellationPercentage']);
$router->get('/getuserbookings', ['uses' => 'hotel_chain\BookingsController@getUserBookings']);
$router->get('/bookingsbyfinyear/{duration}', ['uses' => 'hotel_chain\BookingsController@bookingsByFinYear']);
$router->get('/pilgrimagebookings/{duration}', ['uses' => 'hotel_chain\BookingsController@pilgrimageBookings']);
$router->get('/hotelBookingsdata/{hotel_id}/{start_date}/{end_date}', ['uses' => 'hotel_chain\BookingsController@getHotelBookingsData']);
$router->get('/get-occupancy-rm-hotels', ['uses' => 'hotel_chain\BookingsController@getOccupancyOfRMHotels']);

$router->get('/update-hotel-statistics/{month}/{year}', ['uses' => 'hotel_chain\HotelController@updateHotelStatistics']);
$router->get('/get-hotel-statistics', ['uses' => 'hotel_chain\HotelController@getHotelStatistics']);
$router->get('/update-csm-kpi-achievements/{month}/{year}', ['uses' => 'hotel_chain\HotelController@updateCSMKPIAchievements']);
$router->get('/get-csm-kpi-achievements/{month}/{year}/{super_admin_id}', ['uses' => 'hotel_chain\HotelController@getCSMKPIAchievements']);
$router->get('/set-csm-kpi-target/{month}/{year}', ['uses' => 'hotel_chain\HotelController@setCSMKPITarget']);

//B2C

$router->get('/popular-cities', ['uses' => 'hotel_chain\HotelController@getPopularCities']); 
$router->get('/top-destinations', ['uses' => 'hotel_chain\HotelController@getTopDestinations']); 


$router->post('/unpaid-bookings-list', ['uses' => 'hotel_chain\BookingsController@getUnpaidBookingList']); 



