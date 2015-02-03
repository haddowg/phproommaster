<?php
namespace haddowg\phproommaster;

/**
 * @package     PHPRoomMaster
 * @author      Gregory Haddow
 * @copyright   Copyright (c) 2014, Gregory Haddow, http://www.greghaddow.co.uk/
 * @license     http://opensource.org/licenses/gpl-3.0.html The GPL-3 License with additional attribution clause as detailed below.
 * @version     0.1
 * @link        http://www.greghaddow.co.uk/
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program has the following attribution requirement (GPL Section 7):
 *     - you agree to retain in PHPRoomMaster and any modifications to PHPRoomMaster the copyright, author attribution and
 *       URL information as provided in this notice and repeated in the licence.txt document provided with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use COM;
use VARIANT;

final class PHPRM_IQResWiz{

    const DEFAULT_RATE_TYPE = 'BAR';

    private static $instance = null;
    private $com;
    private $util;
    public $sessionID;
    public $reservationData;
    public $error;
    public $promoCode;
    public $promoData;
    public $complete = false;

    final public static function getInstance(){
        register_shutdown_function('haddowg\phproommaster\PHPRM_IQResWiz::shutdown');
        if(null !== self::$instance){
            return self::$instance;
        }
        if($_SESSION['rm_IQResWiz']){
            if($_SESSION['rm_IQResWiz'] instanceof self){
                self::$instance = $_SESSION['rm_IQResWiz'];
                self::$instance->init_com();
                return self::$instance;
            }
        }
        self::$instance = new PHPRM_IQResWiz();
        $_SESSION['rm_IQResWiz'] = self::$instance;

        return self::$instance;
    }

    public static function shutdown(){
        $_SESSION['rm_IQResWiz'] = self::$instance;
    }

    protected function __construct()
    {
       $this->init_com();
    }

    private function init_com(){
        try {
        $temp = new COM("IQReservations.IQResWiz");
        $this->util = new COM("MSScriptControl.ScriptControl");
        $this->util->Language = 'VBScript';
        $this->util->AllowUI = false;
        $this->util->AddObject('comobject',$temp ,false);
        $this->util->AddCode('
            Function getArrayVal(arr, indexX, indexY)
                getArrayVal = arr(indexX, indexY)
            End Function
            Function getArraySize(arr)
                getArraySize = Ubound(arr, 2)
            End Function
        ');
        $this->com = $temp;
        }catch (\com_exception $e){
            $this->error=$e;
               error_log($e->getMessage());
            return false;
        }
    }

    public function dumpCom(){
        com_print_typeinfo($this->com);
    }

    public function RMUpdateCounter($counter){
        try{
            $return = $this->com->RMUpdateCounter(new VARIANT($counter,VT_UI1));
        }catch (\com_exception $e){
            $this->error=$e;
            error_log($e->getMessage());
            return false;
        }
        return $return;
    }

    public function GetIQAFunction($type,$p1,$p2,$p3,&$out){
        if(! $out instanceof VARIANT){
           die('$out parameter must be a VARIANT object.');
        }
        $type = new VARIANT($type,VT_I4);
        $p1 = new VARIANT($p1);
        $p2 = new VARIANT($p2);
        $p3 = new VARIANT($p3);
        try{
            $return =  $this->com->GetIQAFunction($type,$p1,$p2,$p3,$out);
        }catch (\com_exception $e){
            $this->error=$e;
            error_log($e->getMessage());
            return false;
        }
        return $return;
    }

    /*
     * @todo    reform return into array of objects with named members
     */
    public function GetRateTypes(){
        try{
            $rates = $this->com->GetRateTypes();
        }catch (\com_exception $e){
            $this->error=$e;
            error_log($e->getMessage());
            $rates=array();
        }
        if(!empty($rates)){
            $ySize = $this->util->Run('getArraySize',$rates);
            $ySize = (($ySize+1)/6);
            $out =array();
            for ($x=0; $x < $ySize; $x++) {
                for($y=0;$y < 6; $y++) {
                    if(!isset($out[$x]) || !is_array($out[$x])){
                        $out[$x]= array();
                    }
                    $myY = ($x*6)+$y;
                    $myX=0;
                    $out[$x][$y]  = $this->util->Run('getArrayVal', $rates, $myX, $myY );
                }
            }
        }else{
            return false;
        }
        return $out;
    }

    public function GetPromoCode($code,$checkIn, $checkOut){

        $checkInDateMDY = date('mdY',$checkIn);
        $checkOutDateMDY = date('mdY',$checkOut);
        $code = new VARIANT(strtoupper($code), VT_BSTR);
        $checkInDateMDY  = new VARIANT($checkInDateMDY,VT_I4);
        $checkOutDateMDY = new VARIANT($checkOutDateMDY,VT_I4);
        try{
            $promo = $this->com->GetPromoCode($code,$checkInDateMDY, $checkOutDateMDY);
        }catch (\com_exception $e){
            $this->error=$e;
            error_log($e->getMessage());
            $promo='';
        }
        //clear any existing promo
        $this->promoCode='';
        $this->promoData=null;

        if(strlen($promo)>1){
            $promoData = new \stdClass();
            $promoData->rateType                = trim(substr($promo,0,4));
		    $promoData->rateTypeDescription     = trim(substr($promo,4,96));
		    $promoData->discountTypeDefault	    = trim(substr($promo,100,1));
		    $promoData->discountDaysDefault	    = trim(substr($promo,101,3));
		    $promoData->discountAmountDefault	= trim(substr($promo,104,10));
		    $promoData->discountFmtAmtDefault	= trim(substr($promo,114,10));
		    $promoData->groupCode			    = trim(substr($promo,124,6));
		    $promoData->user1Override			= trim(substr($promo,130,4));
		    $promoData->user2Override			= trim(substr($promo,134,4));
            $this->promoCode = strtoupper($code);
            $this->promoData = $promoData;
            return $promoData;
        }elseif($promo ==1){
            return 'Invalid Promo Code entered.';
        }elseif($promo==2){
            return 'This Promo Code has expired.';
        }
        return 'A Unknown Error occurred please try again.';
    }

    public function GetRoomAvailability($checkIn, $checkOut, $adults, $children, $rateType, $discountType, $discountAmount, $discountDays, $travelId, $groupCode, $numRooms){

        if(empty($rateType)){
            $rateType = self::DEFAULT_RATE_TYPE;
        }

        if(!(empty($this->promoCode) || is_object($this->promoData))){
            if(!empty($this->promoData->rateType)){
                $rateType = $this->promoData->rateType;
            }
        }

        $checkInDateMDY = date('mdY',$checkIn);
        $checkInDateMDY  = new VARIANT($checkInDateMDY,VT_I4);
        $checkOutDateMDY = date('mdY',$checkOut);
        $checkOutDateMDY = new VARIANT($checkOutDateMDY,VT_I4);
        $adultsV = new VARIANT($adults,VT_UI1);
        $childrenV = new VARIANT($children,VT_UI1);
        $rateTypeV = new VARIANT($rateType, VT_BSTR);
        $discountTypeV = new VARIANT($discountType, VT_BSTR);
        $discountAmountV = new VARIANT($discountAmount, VT_BSTR);
        $discountDaysV = new VARIANT($discountDays,VT_UI1);
        $travelIdV = new VARIANT($travelId,VT_I4);
        $groupCodeV = new VARIANT($groupCode, VT_BSTR);
        $numRoomsV = new VARIANT($numRooms,VT_I4);

        try{
            $avail = $this->com->GetRoomAvailability($checkInDateMDY, $checkOutDateMDY, $adultsV, $childrenV, $rateTypeV, $discountTypeV, $discountAmountV, $discountDaysV, $travelIdV, $groupCodeV, $numRoomsV);
        } catch (\com_exception $e) {
            $this->error=$e;
            $msg = $e->getMessage();
            if(strpos($msg,'Source: 702-1')!== false){
                if($numRooms < 1){
                    return '';
                }else{
                    return array();
                }
            }else{
                error_log($msg);
                if($numRooms < 1){
                    return '';
                }else{
                    return array();
                }
            }
        }

        if($numRooms < 1){
            return $avail;
        }

        $reservationData = new \stdClass();
        $reservationData->checkIn = $checkIn;
        $reservationData->checkOut = $checkOut;
        $reservationData->adults = $adults;
        $reservationData->children = $children;
        $reservationData->rateType = $rateType;
        $reservationData->discountType = $discountType;
        $reservationData->discountAmount = $discountAmount;
        $reservationData->discountDays = $discountDays;
        $reservationData->travelId = $travelId;
        $reservationData->groupCode = $groupCode;
        $reservationData->rooms = $numRooms;
        $reservationData->availData = array();



        $ySize = $this->util->Run('getArraySize',$avail);

        $out =array();
        for ($x=0; $x < count($avail); $x++) {
            for($y=0;$y < $ySize+1; $y++) {
            if(!isset($out[$y]) || !is_array($out[$y])){
                 $out[$y]= array();
            }
            $out[$y][$x]  = $this->util->Run('getArrayVal', $avail, $x, $y);
            }
        }

        $this->sessionID = $out[0][0];

        $available =0;
        $index =1;
        foreach($out as $roomType){
            $rt = new \stdClass();
            $rt->id = $roomType[1];
            $rt->title = $roomType[2];
            $rt->cost = $roomType[3];
            $rt->available=false;
            if(substr($rt->cost,0,1)!='!'){
                $rt->available=true;
                $available++;
            }
            $rt->vat = $roomType[4];
            $rt->total = $roomType[5];
            $rt->deposit = $roomType[6];
            $rt->daily = $roomType[8];
            $rt->rm_index=$index;
            $index++;
            $reservationData->availData[$rt->id] = $rt;
        }

        $reservationData->available = $available;

        $this->reservationData = $reservationData;
        $this->complete = false;
        return $this->reservationData;
    }

    public function CreateReservation($checkInDate, $checkOutDate, $adults, $children, $roomType, $rateType, $entryNum,
                                      $usrTitle, $usrFirstName, $usrLastName, $usrAddr1, $usrAddr2, $usrTownCity, $usrPostCode, $usrCountry, $usrPhone1, $usrPhone2, $usrEmail,
                                      $cardNum, $cardExp, $cardName,
                                      $madeBy, $company, $travelID, $assist, $discountType, $discountAmnt, $discountDays,
                                      $groupCode,$cityLedger,
                                      $notes, $numRooms, $usrField1, $usrField2){

        $checkInDateMDY = date('mdY',$checkInDate);
        $checkInDateMDY  = new VARIANT($checkInDateMDY,VT_I4);
        $checkOutDateMDY = date('mdY',$checkOutDate);
        $checkOutDateMDY = new VARIANT($checkOutDateMDY,VT_I4);
        $adults = new VARIANT($adults,VT_UI1);
        $children = new VARIANT($children,VT_UI1);
        $roomType = new VARIANT($roomType,VT_BSTR);
        $rateType = new VARIANT($rateType,VT_BSTR);
        $entryNum = new VARIANT($entryNum,VT_UI1);
        $usrTitle = new VARIANT($usrTitle,VT_BSTR);
        $usrFirstName = new VARIANT($usrFirstName,VT_BSTR);
        $usrLastName = new VARIANT($usrLastName,VT_BSTR);
        $usrAddr1 = new VARIANT($usrAddr1,VT_BSTR);
        $usrAddr2 = new VARIANT( $usrAddr2,VT_BSTR);
        $usrTownCity = new VARIANT($usrTownCity,VT_BSTR);
        $usrPostCode = new VARIANT($usrPostCode,VT_BSTR);
        $usrCountry = new VARIANT($usrCountry,VT_BSTR);
        $usrPhone1 = new VARIANT($usrPhone1,VT_BSTR);
        $usrPhone2 = new VARIANT($usrPhone2,VT_BSTR);
        $usrEmail =  new VARIANT($usrEmail, VT_BSTR);
        $cardNum =  new VARIANT($cardNum, VT_BSTR);
        $cardExp=  new VARIANT($cardExp, VT_BSTR);
        $cardName =  new VARIANT($cardName, VT_BSTR);
        $madeBy =  new VARIANT($madeBy, VT_BSTR);
        $company =  new VARIANT(company, VT_BSTR);
        $travelID = new VARIANT($travelID,VT_BSTR);
        $assist = new VARIANT($assist, VT_BSTR);
        $discountType = new VARIANT($discountType, VT_BSTR);
        $discountAmnt = new VARIANT($discountAmnt, VT_BSTR);
        $discountDays = new VARIANT($discountDays,VT_UI1);
        $groupCode = new VARIANT($groupCode, VT_BSTR);
        $cityLedger = new VARIANT($cityLedger,VT_I4);
        $notes = new VARIANT($notes,VT_BSTR);
        $numRooms = new VARIANT($numRooms,VT_BSTR);
        $usrField1 = new VARIANT($usrField1,VT_BSTR);
        $usrField2 = new VARIANT($usrField2,VT_BSTR);

        $sessionID = new VARIANT($this->sessionID, VT_BSTR);


        try{
            $res = $this->com->CreateReservation(
                $checkInDateMDY, $checkOutDateMDY,
                $adults, $children,
                $roomType, $rateType,
                $sessionID, $entryNum,
                $usrTitle, $usrFirstName, $usrLastName,
                $usrAddr1, $usrAddr2, $usrTownCity, $usrPostCode,
                $usrCountry, $usrPhone1, $usrPhone2, $usrEmail,
                $cardNum, $cardExp, $cardName, $madeBy,
                $company, $travelID,
                $assist,
                $discountType, $discountAmnt, $discountDays,
                $groupCode, $cityLedger,
                $notes, $numRooms, $usrField1, $usrField2);
        }catch (\com_exception $e){
            $this->error=$e;
            error_log($e->getMessage());
            return false;
        }

        $ySize = $this->util->Run('getArraySize',$res);

        $out =array();
        for ($x=0; $x < count($res); $x++) {
            for($y=0;$y < $ySize+1; $y++) {
                if(!isset($out[$y]) || !is_array($out[$y])){
                    $out[$y]= array();
                }
                $out[$y][$x]  = $this->util->Run('getArrayVal', $res, $x, $y);
            }
        }
        if($ySize==0){
            return $out[0];
        }
        return $out;

    }

    public function GetSCEntry( $entryNumber=null, $itemID=null)
    {
        $sessionID = new VARIANT($this->sessionID,VT_BSTR);
        if(is_null($entryNumber) && isset($this->reservationData) && isset($this->reservationData->roomData)){
            $entryNumber = $this->reservationData->roomData->rm_index;
        }
        $entryNumber = new VARIANT($entryNumber, VT_UI1);
        $itemID = new VARIANT($itemID,VT_BSTR);
        $itemIDOut = new VARIANT('',VT_BSTR);
        $groupHeaderOut = new VARIANT('',VT_BSTR);
        $shortDescriptionOut = new VARIANT('',VT_BSTR);
        $longDescriptionOut = new VARIANT('',VT_BSTR);
        $pictureFileOut = new VARIANT('',VT_BSTR);
        $priceOut = new VARIANT('',VT_BSTR);
        $priceDisplayOut = new VARIANT('',VT_BSTR);
        $qtyTypeOut = new VARIANT('',VT_BSTR);
        $datesAvailableOut = new VARIANT('',VT_BSTR);
        $confirmDesc = new VARIANT('',VT_BSTR);
        $commentOut = new VARIANT('',VT_BSTR);

        $options =$this->com->GetSCEntry($sessionID, $entryNumber, $itemID,
                                        $itemIDOut,$groupHeaderOut,$shortDescriptionOut,$longDescriptionOut,
                                        $pictureFileOut,$priceOut,$priceDisplayOut,$qtyTypeOut,
                                        $datesAvailableOut,$confirmDesc,$commentOut);
        $return=array();
        $count = count(explode(chr(254),$itemIDOut)) -1;
        $output = compact('itemIDOut','groupHeaderOut','shortDescriptionOut','longDescriptionOut',
            'pictureFileOut','priceOut','priceDisplayOut','qtyTypeOut',
            'datesAvailableOut','confirmDesc','commentOut');
        foreach($output as $k=>$out){
            $output[$k] = explode(chr(254),$out);
        }
        for($i=0;$i<$count;$i++){
            $newOption = new \stdClass();
            $newOption->itemID = $output['itemIDOut'][$i];
            $newOption->group_header = $output['groupHeaderOut'][$i];
            $newOption->short_desc = $output['shortDescriptionOut'][$i];
            $newOption->long_desc = $output['longDescriptionOut'][$i];
            $newOption->picture = $output['pictureFileOut'][$i];
            $newOption->price = $output['priceOut'][$i];
            $newOption->price_display = $output['priceDisplayOut'][$i];
            $newOption->qty_type = $output['qtyTypeOut'][$i];
            $newOption->dates_avail = $output['datesAvailableOut'][$i];
            $newOption->confirm_desc = $output['confirmDesc'][$i];
            $newOption->comment = $output['commentOut'][$i];

            if($newOption->dates_avail!=1 && $newOption->dates_avail !=2 ){
                $newOption->dates_avail = str_split($newOption->dates_avail,6);
            }
            $return[$newOption->itemID] = $newOption;
        }

        $this->reservationData->extraOptions = $return;
        return $return;
    }

    public function GetSCTotal($itemIDs, $itemQTYs,$entryNumber=null, $taID=null)
    {
        $sessionID = new VARIANT($this->sessionID,VT_BSTR);
        if(is_null($entryNumber) && isset($this->reservationData) && isset($this->reservationData->roomData)){
            $entryNumber = $this->reservationData->roomData->rm_index;
        }
        $entryNumber = new VARIANT($entryNumber, VT_UI1);
        $taID = new VARIANT($taID,VT_I4);

        if(is_array($itemIDs)){
            $itemIDs = implode(chr(254),$itemIDs) . chr(254);
        }
        $itemIDs = new VARIANT($itemIDs,VT_BSTR);

        if(is_array($itemQTYs)){
            $itemQTYs = implode(chr(254),$itemQTYs) . chr(254);
        }
        $itemQTYs = new VARIANT($itemQTYs,VT_BSTR);
        $roomOut = new VARIANT('',VT_BSTR);
        $roomTaxOut = new VARIANT('',VT_BSTR);
        $extrasOut = new VARIANT('',VT_BSTR);
        $extrasTaxOut = new VARIANT('',VT_BSTR);
        $totalOut = new VARIANT('',VT_BSTR);
        $depositOut = new VARIANT('',VT_BSTR);
        $remainingOut = new VARIANT('',VT_BSTR);

        $totals = $this->com->GetSCTotal($sessionID,$entryNumber,$taID,$itemIDs,$itemQTYs,$roomOut,$roomTaxOut,$extrasOut,$extrasTaxOut,$totalOut,$depositOut,$remainingOut);

        $return = new \stdClass();

        $return->room_total = (float) $roomOut;
        $return->room_tax = (float) $roomTaxOut;
        $return->room_inc = (float) $roomOut;

        $return->extras_total = (float) $extrasOut;
        $return->extras_tax = (float) $extrasTaxOut;
        $return->extras_inc = (float) $extrasOut;

        $return->total = (float) $totalOut;
        $return->total_inc = (float) $totalOut;
        $return->total_tax = ((float) $roomTaxOut) +((float) $extrasTaxOut);

        $return->deposit = (float) $depositOut;
        $return->remaining = (float) $remainingOut;

        return $return;
    }

    public function AddSCItems($confirmation, $itemIDs, $itemQTYs, $itemDays, $itemComments){
        $confirmation = new VARIANT($confirmation,VT_BSTR);

        if(is_array($itemIDs)){
            $itemIDs = implode(chr(254),$itemIDs) . chr(254);
        }
        $itemIDs = new VARIANT($itemIDs,VT_BSTR);
        if(is_array($itemQTYs)){
            $itemQTYs = implode(chr(254),$itemQTYs) . chr(254);
        }
        $itemQTYs = new VARIANT($itemQTYs,VT_BSTR);
        if(is_array($itemDays)){
            $itemDays = implode(chr(254),$itemDays) . chr(254);
        }
        $itemDays = new VARIANT($itemDays,VT_BSTR);
        if(is_array($itemComments)){
            $itemComments = implode(chr(254),$itemComments) . chr(254);
        }
        $itemComments = new VARIANT($itemComments,VT_BSTR);

        $return  = $this->com->AddSCItems($confirmation, $itemIDs, $itemQTYs, $itemDays, $itemComments);
        return $return;
    }

    private function __clone()
    {
    }

    public function has_promo(){
        return (isset($this->promoCode) &&
                (!empty($this->promoCode)) &&
                isset($this->promoData) &&
                is_object($this->promoData));
    }

    public function has_avail(){
        return (isset($this->reservationData) &&
                is_object($this->reservationData));
    }
    public function has_room(){
        return ($this->has_avail() &&
                isset($this->reservationData->roomData) &&
                is_object($this->reservationData->roomData));
    }

    public function has_extras(){
        return ($this->has_room() &&
                isset($this->reservationData->extrasData) &&
                is_object($this->reservationData->extrasData) &&
                is_array($this->reservationData->extrasData->extras) &&
                !empty($this->reservationData->extrasData->extras));
    }

    public function has_customer(){
        return ($this->has_room() &&
            isset($this->reservationData->customerData) &&
            is_object($this->reservationData->customerData));
    }
}