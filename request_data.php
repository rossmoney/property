<?php
namespace App;

require "vendor/autoload.php";
require_once "config.php";

use App\PropertiesData;

$requester = new PropertiesData();

$requester->refreshTables();
$requester->saveDataToDatabase();
$requester->pdo()->close();
