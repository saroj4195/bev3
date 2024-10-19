<?php

namespace App\Http\Controllers\Extranetv4\invrateupdatecontrollers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\DynamicPricingCurrentInventory;
use App\DynamicPricingCurrentInventoryBe;
use App\Http\Controllers\Controller;

/**
 * This controller is used for fetch inventory date range.
 * @auther Saroj Patel
 * created date 26/07/22.
 */
class GetDataForInventoryController extends Controller
{
	public function getinventorydata($data, $ota_name, $ota_id)
	{
		$from_date = date('Y-m-d', strtotime($data['date_from']));
		$to_date = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
		$p_start = $from_date;
		$p_end = $to_date;
		$period     = new \DatePeriod(
			new \DateTime($p_start),
			new \DateInterval('P1D'),
			new \DateTime($p_end)
		);
		foreach ($period as $key => $value) {
			$index = $value->format('Y-m-d');
			$get_block_status = DynamicPricingCurrentInventory::where('hotel_id', $data['hotel_id'])
				->where('room_type_id', $data['room_type_id'])
				->where('stay_day', $index)
				->where('ota_id', $ota_id)
				->where('block_status', 1)
				->orderBy('id', 'DESC')
				->first();
			if ($get_block_status) {
				$current_inv = array(
					"hotel_id"      => $data['hotel_id'],
					"room_type_id"  => $data['room_type_id'],
					"ota_id"        => $ota_id,
					"stay_day"      => $index,
					"no_of_rooms"   => $data['no_of_rooms'],
					"los"  			=> $data['los'],
					"ota_name"      => $ota_name
				);

				$cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
					[
						'hotel_id' => $data['hotel_id'],
						'room_type_id' => $data['room_type_id'],
						'ota_id' => $ota_id,
						'stay_day' => $index,
						'ota_name' => $ota_name
					],
					$current_inv
				);

					$cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
						[
							'hotel_id' => $data['hotel_id'],
							'room_type_id' => $data['room_type_id'],
							'ota_id' => $ota_id,
							'stay_day' => $index,
							'ota_name' => $ota_name
						],
						$current_inv
					);

				$date_range[$index] = 1;
			} else {
				$date_range[$index] = 0;
			}
		}
		$dur_info = [];
		$end_dur_info = [];
		$start_date_info = [];
		$end_date_info = [];
		foreach ($date_range as $key_val => $val) {
			if ($val == 1) {
				if (sizeof($end_dur_info) > 0) {
					$end_ind = sizeof($end_dur_info) - 1;
					$diff  = strtotime($end_dur_info[$end_ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					} else {
						$end_dur_info[] = $key_val;
					}
				} else {
					if (sizeof($start_date_info) > 0) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					}
				}
			} else {
				if (sizeof($dur_info) > 0) {
					$ind = sizeof($dur_info) - 1;
					$diff  = strtotime($dur_info[$ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$start_date_info[] = $key_val;
						$end_dur_info = [];
					} else {
						$dur_info[] = $key_val;
					}
				} else {
					$dur_info[] = $key_val;
					$start_date_info[] = $key_val;
					$end_dur_info = [];
				}
			}
		}
		if ($date_range[$key_val] == 0) {
			$end_date_info[] = $key_val;
		}

		$resulted_array = array("start_date_info" => $start_date_info, "end_date_info" => $end_date_info);
		return $resulted_array;
	}

	public function updateDataToCurrentInventory($data, $otaName, $startDate, $endDate)
	{
		$p_start = date('Y-m-d', strtotime($startDate));
		$p_end = date('Y-m-d', strtotime($endDate . '+1 day'));
		$period     = new \DatePeriod(
			new \DateTime($p_start),
			new \DateInterval('P1D'),
			new \DateTime($p_end)
		);

		$weekdays = array();
		if(isset($data['multiple_days']))
		{
			$weekdays = json_decode($data['multiple_days'],true);
		}
		else
		{
			$weekdays = array(
				'Mon' => 1,
				'Tue' => 1,
				'Wed' => 1,
				'Thu' => 1,
				'Fri' => 1,
				'Sat' => 1,
				'Sun' => 1
			);
		}
		
		$weekday = array();
		foreach ($period as $key => $value) {
			$index = $value->format('Y-m-d');
			$weekday = $value->format('D');
			
			if($weekdays[$weekday] == 1)
			{
				$current_inv = array(
					"hotel_id"      => $data['hotel_id'],
					"room_type_id"  => $data['room_type_id'],
					"ota_id"        => -1,
					"stay_day"      => $index,
					"los"  => $data['los'],
					"no_of_rooms"   => $data['no_of_rooms'],
					"ota_name"      => $otaName
				);

				$cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
					[
						'hotel_id' => $data['hotel_id'],
						'room_type_id' => $data['room_type_id'],
						'ota_id' => $data['ota_id'],
						'stay_day' => $index,
						'ota_name' => $otaName
					],
					$current_inv
				);

					$cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
						[
							'hotel_id' => $data['hotel_id'],
							'room_type_id' => $data['room_type_id'],
							'ota_id' => $data['ota_id'],
							'stay_day' => $index,
							'ota_name' => $otaName
						],
						$current_inv
					);
			}
			else
			{
				continue;
			}	
		}
		return 1;
	}

	public function getinventorydataForUnblock($data, $ota_name)
	{
		$from_date = date('Y-m-d', strtotime($data['date_from']));
		$to_date = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
		$p_start = $from_date;
		$p_end = $to_date;
		$period     = new \DatePeriod(
			new \DateTime($p_start),
			new \DateInterval('P1D'),
			new \DateTime($p_end)
		);
		foreach ($period as $key => $value) {
			$index = $value->format('Y-m-d');
			$get_block_status = DynamicPricingCurrentInventory::where('hotel_id', $data['hotel_id'])
				->where('room_type_id', $data['room_type_id'])
				->where('stay_day', $index)
				->where('ota_id', $data['ota_id'])
				->where('block_status', 0)
				->orderBy('id', 'DESC')
				->first();
			if ($get_block_status) {
				$date_range[$index] = 0;
			} else {
				$date_range[$index] = 1;
			}
		}
		$dur_info = [];
		$end_dur_info = [];
		$start_date_info = [];
		$end_date_info = [];
		foreach ($date_range as $key_val => $val) {
			if ($val == 0) {
				if (sizeof($end_dur_info) > 0) {
					$end_ind = sizeof($end_dur_info) - 1;
					$diff  = strtotime($end_dur_info[$end_ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					} else {
						$end_dur_info[] = $key_val;
					}
				} else {
					if (sizeof($start_date_info) > 0) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					}
				}
			} else {
				if (sizeof($dur_info) > 0) {
					$ind = sizeof($dur_info) - 1;
					$diff  = strtotime($dur_info[$ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$start_date_info[] = $key_val;
						$end_dur_info = [];
					} else {
						$dur_info[] = $key_val;
					}
				} else {
					$dur_info[] = $key_val;
					$start_date_info[] = $key_val;
					$end_dur_info = [];
				}
			}
		}
		if ($date_range[$key_val] == 1) {
			$end_date_info[] = $key_val;
		}
		$resulted_array = array("start_date_info" => $start_date_info, "end_date_info" => $end_date_info);
		return $resulted_array;
	}

	public function getinventorydataForUpdate($data, $hotel_id,$ota_id)
	{
		foreach ($data as $value) {
			$get_block_status = DynamicPricingCurrentInventory::where('hotel_id', $hotel_id)
				->where('room_type_id', $value['room_type_id'])
				->where('stay_day', $value['date'])
				->where('ota_id', $ota_id)
				->where('block_status', 0)
				->orderBy('id', 'DESC')
				->first();
			if ($get_block_status) {
				$date_range[$value['date']] = 0;
			} else {
				$date_range[$value['date']] = 1;
			}
		}
		$dur_info = [];
		$end_dur_info = [];
		$start_date_info = [];
		$end_date_info = [];
		foreach ($date_range as $key_val => $val) {
			if ($val == 0) {
				if (sizeof($end_dur_info) > 0) {
					$end_ind = sizeof($end_dur_info) - 1;
					$diff  = strtotime($end_dur_info[$end_ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					} else {
						$end_dur_info[] = $key_val;
					}
				} else {
					if (sizeof($start_date_info) > 0) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					}
				}
			} else {
				if (sizeof($dur_info) > 0) {
					$ind = sizeof($dur_info) - 1;
					$diff  = strtotime($dur_info[$ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$start_date_info[] = $key_val;
						$end_dur_info = [];
					} else {
						$dur_info[] = $key_val;
					}
				} else {
					$dur_info[] = $key_val;
					$start_date_info[] = $key_val;
					$end_dur_info = [];
				}
			}
		}
		if ($date_range[$key_val] == 1) {
			$end_date_info[] = $key_val;
		}
		$resulted_array = array("start_date_info" => $start_date_info, "end_date_info" => $end_date_info);
		return $resulted_array;
	}


	public function getinventorydataforMultipledays($data, $ota_name, $ota_id)
	{
		$from_date = date('Y-m-d', strtotime($data['date_from']));
		$to_date = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
		$p_start = $from_date;
		$p_end = $to_date;
		$period     = new \DatePeriod(
			new \DateTime($p_start),
			new \DateInterval('P1D'),
			new \DateTime($p_end)
		);

		if(isset($data['multiple_days']))
		{
			$weekdays = json_decode($data['multiple_days'],true);
		}
		else
		{
			$weekdays = array(
				'Mon' => 1,
				'Tue' => 1,
				'Wed' => 1,
				'Thu' => 1,
				'Fri' => 1,
				'Sat' => 1,
				'Sun' => 1
			);
		}

		$weekday = "";
		foreach ($period as $key => $value) {
			$index = $value->format('Y-m-d');
			$weekday = $value->format('D');
			$get_block_status = DynamicPricingCurrentInventory::where('hotel_id', $data['hotel_id'])
				->where('room_type_id', $data['room_type_id'])
				->where('stay_day', $index)
				->where('ota_id', $ota_id)
				->where('block_status', 1)
				->orderBy('id', 'DESC')
				->first();
			if($get_block_status || $weekdays[$weekday] === 0){
				$current_inv = array(
					"hotel_id"      => $data['hotel_id'],
					"room_type_id"  => $data['room_type_id'],
					"ota_id"        => $ota_id,
					"stay_day"      => $index,
					"no_of_rooms"   => $data['no_of_rooms'],
					"los"  			=> $data['los'],
					"ota_name"      => $ota_name
				);
				if($weekdays[$weekday] == 1)
				{
					$cur_inv = DynamicPricingCurrentInventory::updateOrInsert(
						[
							'hotel_id' => $data['hotel_id'],
							'room_type_id' => $data['room_type_id'],
							'ota_id' => $ota_id,
							'stay_day' => $index,
							'ota_name' => $ota_name
						],
						$current_inv
					);

						$cur_inv_be = DynamicPricingCurrentInventoryBe::updateOrInsert(
							[
								'hotel_id' => $data['hotel_id'],
								'room_type_id' => $data['room_type_id'],
								'ota_id' => $ota_id,
								'stay_day' => $index,
								'ota_name' => $ota_name
							],
							$current_inv
						);
				}
				$date_range[$index] = 1;
			} else {
				$date_range[$index] = 0;
			}
		}
		$dur_info = [];
		$end_dur_info = [];
		$start_date_info = [];
		$end_date_info = [];
		foreach ($date_range as $key_val => $val) {
			if ($val == 1) {
				if (sizeof($end_dur_info) > 0) {
					$end_ind = sizeof($end_dur_info) - 1;
					$diff  = strtotime($end_dur_info[$end_ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					} else {
						$end_dur_info[] = $key_val;
					}
				} else {
					if (sizeof($start_date_info) > 0) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					}
				}
			} else {
				if (sizeof($dur_info) > 0) {
					$ind = sizeof($dur_info) - 1;
					$diff  = strtotime($dur_info[$ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$start_date_info[] = $key_val;
						$end_dur_info = [];
					} else {
						$dur_info[] = $key_val;
					}
				} else {
					$dur_info[] = $key_val;
					$start_date_info[] = $key_val;
					$end_dur_info = [];
				}
			}
		}
		if ($date_range[$key_val] == 0) {
			$end_date_info[] = $key_val;
		}

		$resulted_array = array("start_date_info" => $start_date_info, "end_date_info" => $end_date_info);
		return $resulted_array;
	}

	public function getinventorydataForUnblockforMultipleDays($data, $ota_name)
	{
		$from_date = date('Y-m-d', strtotime($data['date_from']));
		$to_date = date('Y-m-d', strtotime($data['date_to'] . '+1 day'));
		$p_start = $from_date;
		$p_end = $to_date;
		$period     = new \DatePeriod(
			new \DateTime($p_start),
			new \DateInterval('P1D'),
			new \DateTime($p_end)
		);

		if(isset($data['multiple_days']))
		{
			$weekdays = $data['multiple_days'];
		}
		else
		{
			$weekdays = array(
				'Mon' => 1,
				'Tue' => 1,
				'Wed' => 1,
				'Thu' => 1,
				'Fri' => 1,
				'Sat' => 1,
				'Sun' => 1
			);
		}
		
		$weekday = "";
		foreach ($period as $key => $value) {
			$index = $value->format('Y-m-d');
			$weekday = $value->format('D');
			$get_block_status = DynamicPricingCurrentInventory::where('hotel_id', $data['hotel_id'])
				->where('room_type_id', $data['room_type_id'])
				->where('stay_day', $index)
				->where('ota_id', $data['ota_id'])
				->where('block_status', 0)
				->orderBy('id', 'DESC')
				->first();
			if($get_block_status || $weekdays[$weekday] === 0){
				$date_range[$index] = 0;
			} else {
				$date_range[$index] = 1;
			}
		}
		$dur_info = [];
		$end_dur_info = [];
		$start_date_info = [];
		$end_date_info = [];
		foreach ($date_range as $key_val => $val) {
			if ($val == 0) {
				if (sizeof($end_dur_info) > 0) {
					$end_ind = sizeof($end_dur_info) - 1;
					$diff  = strtotime($end_dur_info[$end_ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					} else {
						$end_dur_info[] = $key_val;
					}
				} else {
					if (sizeof($start_date_info) > 0) {
						$end_date_info[] = date('Y-m-d', strtotime($key_val . '-1 day'));
						$dur_info = [];
						$end_dur_info[] = $key_val;
					}
				}
			} else {
				if (sizeof($dur_info) > 0) {
					$ind = sizeof($dur_info) - 1;
					$diff  = strtotime($dur_info[$ind]) - strtotime($key_val);
					$diff = abs(round($diff / 86400));
					if ($diff > 1) {
						$start_date_info[] = $key_val;
						$end_dur_info = [];
					} else {
						$dur_info[] = $key_val;
					}
				} else {
					$dur_info[] = $key_val;
					$start_date_info[] = $key_val;
					$end_dur_info = [];
				}
			}
		}
		if ($date_range[$key_val] == 1) {
			$end_date_info[] = $key_val;
		}
		$resulted_array = array("start_date_info" => $start_date_info, "end_date_info" => $end_date_info);
		return $resulted_array;
	}

}
