<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Http\Controllers\InventoryService;
use App\Http\Controllers\UpdateInventoryService;
//create a new class ManageInventoryController
/**Call for getting current inventory from be
* @author Ranjit @date 2020-06-11
*@userstore : This contrller is used as a bridge between cm and be for getting the current inventory from be.
**/

class CallInvServiceFromCmController extends Controller{
    protected $invService;
    protected $updateInv;
    public function __construct(InventoryService $invService,UpdateInventoryService $updateInv){
      $this->invService = $invService;
      $this->updateInv  = $updateInv;
    }
    public function getCurrentInventory(Request $request){
        $data = $request->all();
        $call_to_invService  = $this->invService->getInventeryByRoomTYpe($data['room_type_id'],$data['date_form'],$data['date_to'],$data['mindays']);
        if($call_to_invService){
            return $call_to_invService;
        }
    }
    public function updateInventoryInBe(Request $request){
       $data = $request->all();
       $update_inventory = $this->updateInv->updateInvBe($data);
       if($update_inventory){
          return $update_inventory;
       }
    }
}
