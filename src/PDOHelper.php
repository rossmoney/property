<?php
namespace App;

//include credentials securely
require_once __DIR__ . '/../config.php';

use PDO;
use PDOException;

class PDOHelper {
    private $pdo;
    private $hostname;
    private $username;
    private $password;
    private $database;
    private $port;

    public function __construct() {
        //load data from constants stored in config.php
        $this->hostname = MYSQL_HOSTNAME;
        $this->username = MYSQL_USERNAME;
        $this->password = MYSQL_PASSWORD;
        $this->database = MYSQL_DATABASE;
        $this->port = MYSQL_PORT;

        //setup new connection to mysql
        $this->connect();
    }

    public function conn()
    {
        return $this->pdo;
    }

    public function close()
    {
        return $this->pdo = null;
    }

    public function connect() {
        try {
            //do connection, fail gracefully if cannot connect
            $this->pdo = new PDO('mysql:host=' . $this->hostname . ';dbname=' . $this->database . ';port=' . $this->port, $this->username, $this->password);
        } catch(PDOException $ex){
            die('Connect to mysql failed.');
        }
    }
}