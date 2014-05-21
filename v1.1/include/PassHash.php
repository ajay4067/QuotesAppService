<?php

class PassHash {

  // blowfish
  private static $algo = '$2a';
  // cost parameter
  private static $cost = '$10';

  // mainly for internal use
  public static function unique_salt() {
    return substr(sha1(mt_rand()), 0, 22);
  }

  // this will be used to generate a hash
  public static function hash($password) {

    return crypt($password, self::$algo . self::$cost . '$' . self::unique_salt());
  }

  // this will be used to compare a password against a hash
  public static function check_password($hash, $password) {
    $full_salt = substr($hash, 0, 29);
    $new_hash = crypt($password, $full_salt);
    return ($hash == $new_hash);
  }

  /**
   * simple method to encrypt or decrypt a plain text string
   * initialization vector(IV) has to be the same when encrypting and decrypting
   * PHP 5.4.9
   *
   * this is a beginners template for simple encryption decryption
   * before using this in production environments, please read about encryption
   *
   * @param string $action: can be 'encrypt' or 'decrypt'
   * @param string $string: string to encrypt or decrypt
   *
   * @return string
   */
  public static function encrypt_decrypt($action, $string) {
    $output = false;

    $encrypt_method = "AES-256-CBC";
    $secret_key = 'This is my secret key';
    $secret_iv = 'This is my secret iv';

    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action == 'encrypt') {
      $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
      $output = base64_encode($output);
    } else if ($action == 'decrypt') {
      $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }

    return $output;
  }

}
