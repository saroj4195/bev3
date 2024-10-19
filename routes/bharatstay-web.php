<?php
//BharatStay
$router->get('/bs/b2c/top-destinations', ['uses' => 'bharatstay\SearchController@getTopDestinations']); 
$router->get('/bs/b2c/top-rated-hotels', ['uses' => 'bharatstay\SearchController@getTopRatedHotels']); 
$router->post('/bs/b2c/upcoming-booking-details',['uses' =>'bharatstay\BookingController@upcomingBookingDetails']);
$router->get('/bs/b2c/get-available-hotel-list',['uses' =>'bharatstay\SearchController@getAvailableHotelListByCityAndDate']);
$router->get('/bs/b2c/query/{user_id}/{query_text}', ['uses' => 'bharatstay\SearchController@getQueryResult']);
$router->get('/bs/b2c/hotel-details', ['uses' => 'bharatstay\SearchController@getHotelDetails']);
$router->get('/bs/b2c/recent-search/{user_id}/', ['uses' => 'bharatstay\SearchController@recentSearch']);

?>