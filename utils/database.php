<?php

class Database {

  private $conn;

  public function __construct() {
    $this->conn = mysqli_connect("localhost", "v18u130", "Pw130", "v18db130");
  }

  public function disconnect() {
    $this->conn->close();
  }

  public function getRow($query, $params = []) {
    var_dump($query);
    var_dump(...$params);
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param("s", ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
  }

  public function getRows() {

  }

  public function insertRow() {

  }

  public function updateRow() {

  }

  public function deleteRow() {

  }

}

?>
