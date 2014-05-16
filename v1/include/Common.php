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

    /**
     * Redirect with POST data.
     *
     * @param string $url URL.
     * @param array $post_data POST data. Example: array('foo' => 'var', 'id' => 123)
     * @param array $headers Optional. Extra headers to send.
     */
    public function redirect_post($url, array $data, array $headers = null) {
        $params = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );
        if (!is_null($headers)) {
            $params['http']['header'] = '';
            foreach ($headers as $k => $v) {
                $params['http']['header'] .= "$k: $v\n";
            }
        }
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if ($fp) {
            echo @stream_get_contents($fp);
            die();
        } else {
            // Error
            throw new Exception("Error loading '$url', $php_errormsg");
        }
    }

}
