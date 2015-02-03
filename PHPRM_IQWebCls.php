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

final class PHPRM_IQWebCls{

    private static $instance = null;
    private static $com;
    private static $util;

    final public static function getInstance(){
        if(null !== self::$instance){
            return self::$instance;
        }
        static::$instance = new PHPRM_IQWebCls();
        return self::$instance;
    }

    protected function __construct()
    {
        self::$com = new COM("IQWebWiz.IQWebCls") or die('Failed to Initialize IQWebWiz.IQWebCls COM object');
        self::$util = new COM("MSScriptControl.ScriptControl");
        self::$util->Language = 'VBScript';
        self::$util->AllowUI = false;
        self::$util->AddCode('
            Function getArrayVal(arr, indexX, indexY)
                getArrayVal = arr(indexX, indexY)
            End Function
        ');
    }


    public function GetMessage($mgsID, $numMsgs){

        $messages =array();
        $message= self::$com->GetMessage($mgsID,$numMsgs);
        for ($x=0; $x < count($message); $x++) {
            for($y=0;$y < $numMsgs; $y++) {
            $messages[]  = self::$util->Run('getArrayVal', $message, $x, $y);
            }
        }
        return $messages;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

}