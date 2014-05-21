<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Common {

    public function __construct() {
        
    }

    public function getKeysArray($tableName) {
        $responseArray = array();
        switch ($tableName) {
            case 'users':
                $responseArray = array('id', 'name', 'email', 'password_hash', 'api_key', 'status', 'created_at', 'resetMd5');
                break;
            case 'quotes':
                $responseArray = array('id', 'quote', 'wrNctg_ref');
                break;
            case 'writersnctgs':
                $responseArray = array('id', 'name', 'imageURL', 'description');
                break;
            default:
        }
        return $responseArray;
    }

    public function getRowArrayUsingKeys($row, $keysArray) {
        $rowArray = array();
        for ($n = 0; $n < sizeof($keysArray); $n++) {
            $rowArray[$keysArray[$n]] = $row[$keysArray[$n]];
        }
        return $rowArray;
    }

}
