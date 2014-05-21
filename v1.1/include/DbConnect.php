<?php

/**
 * Handling database connection
 */
class DbConnect {

  private $conn;

  function __construct() {
  }

  /**
   * Establishing database connection
   * @return database connection handler
   */
  function connect() {
    include_once 'Config.php';
    require_once 'libs/NotORM.php';
    $dsn = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
    $this -> conn = new NotORM($pdo);

    // returing connection resource
    return $this -> conn;
  }

}
?>