<?php
namespace App;

//enables use of composer packages for guzzle and rate limiting.
require __DIR__ . "/../vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;

use App\PDOHelper;
use PDO;

class PropertiesData {
    private $endPoint;
    private $apiKey;
    private $pageSize;
    private $pageNumber;
    private $lastPage;

    private $guzzle;
    private $pdo;

    private function setupGuzzle() {
        //Create new guzzle client for later use, and set up ratelimiting
        $stack = HandlerStack::create();
        $stack->push(RateLimiterMiddleware::perSecond(3));

        $this->guzzle = new Client([
            'handler' => $stack,
        ]);
    }

    public function __construct(string $endPoint = ENDPOINT, string $apiKey = APIKEY, int $startPage = 1, int $pageSize = 100) {
        $this->endPoint = $endPoint;
        $this->apiKey = $apiKey;
        $this->pageSize = $pageSize;
        $this->pageNumber = $this->lastPage = $startPage;

        //Startup Guzzle with ratelimiting provided by package.
        $this->setupGuzzle();

        //Connect to MySQL
        $this->pdo = new PDOHelper();
    }

    public function paginate(int $page, int $pageSize, array $search = []) {

        //does the majority of work to search for database entries for frontend and returns results

        $selectFields = "p.*, pt.title as property_type_title, pt.description as property_type_description";
        $query = "FROM properties p LEFT JOIN property_types pt ON pt.id = p.property_type_id";

        if (!empty($search)) {
            $query .= ' WHERE';
        }

        $i = 0;

        //example search array format - $search = [ ':num_bedrooms' => [ 'type' => 'int', 'value' => 2]];
        //type can be one of range, like, int, string
        foreach ($search as $field => $details) {

            //append new field pair, if last pair, or one pair only do not add another AND
            if ($i > 0) {
                $query .= ' AND';
            }

            //append different fields to query string depending on type of field
            if ($details['type'] == 'int' || $details['type'] == 'string') {
                $query .= ' ' . str_replace(":", "", $field) . ' = ' . $field;
            }
            
            if ($details['type'] == 'range') {
                $query .= ' (' . str_replace(":", "", $field) . ' BETWEEN ' . $field . 'min AND ' . $field . 'max)';
            }

            if ($details['type'] == 'like') {
                $query .= ' ' . str_replace(":", "", $field) . ' LIKE ' . $field;
            }

            $i++;
        }

        //prepare statements for getting count of all results, and a single page of result data, utilising same base query
        $countStmt = $this->pdo->conn()->prepare("SELECT COUNT(*) ". $query);
        $resultStmt = $this->pdo->conn()->prepare("SELECT $selectFields " . $query . " LIMIT :offset, :rows");
        
        //sanitize and append search query values, for each field type 
        foreach($search as $field => $details) {

            if ($details['type'] == 'int') {
                $details['value'] = filter_var($details['value'], FILTER_SANITIZE_NUMBER_FLOAT);
                $countStmt->bindParam($field, $details['value'], PDO::PARAM_INT);
                $resultStmt->bindParam($field, $details['value'], PDO::PARAM_INT);
            }

            if ($details['type'] == 'range') {
                $details['value'] = filter_var($details['value'], FILTER_SANITIZE_NUMBER_INT);
                $countStmt->bindParam($field . 'min', $details['value_min'], PDO::PARAM_INT);
                $countStmt->bindParam($field . 'max', $details['value_max'], PDO::PARAM_INT);

                $resultStmt->bindParam($field . 'min', $details['value_min'], PDO::PARAM_INT);
                $resultStmt->bindParam($field . 'max', $details['value_max'], PDO::PARAM_INT);
            }

            if ($details['type'] == 'like') {
                $details['value'] = '%' . $details['value'] . '%';
            }

            if ($details['type'] == 'string' || $details['type'] == 'like') {
                $details['value'] = filter_var($details['value'], FILTER_SANITIZE_STRING);
                $countStmt->bindParam($field, $details['value'], PDO::PARAM_STR);
                $resultStmt->bindParam($field, $details['value'], PDO::PARAM_STR);
            }
        }

        //append page specific values to result query
        $resultStmt->bindParam(':offset', $page, PDO::PARAM_INT);
        $resultStmt->bindParam(':rows', $pageSize, PDO::PARAM_INT);

        if ($countStmt->execute()) {
            //Calculate total number of results 
            $result = $countStmt->fetch();
            $totalResults = $result['COUNT(*)'];
            //Calculate total number of pages
            $pageCount = ceil($totalResults / $pageSize);
        }

        if ($resultStmt->execute()) {
            //retrieve results
            $result = $resultStmt->fetchAll();
        }

        return [$pageCount, $totalResults, $result];
    }

    public function refreshTables() {
        //Delete and recreate all tables
        $this->pdo->conn()->query("DROP TABLE `properties`");

        $this->pdo->conn()->query("CREATE TABLE IF NOT EXISTS `properties` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `property_type_id` int(10) NOT NULL,
            `county` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `town` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
            `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `image_full` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `image_thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `latitude` decimal(10,8) NOT NULL,
            `longitude` decimal(11,8) NOT NULL,
            `num_bedrooms` int(10) NOT NULL,
            `num_bathrooms` int(10) NOT NULL,
            `price` decimal(10,2) NOT NULL,
            `type` set('rent','sale') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->pdo->conn()->query("DROP TABLE IF EXISTS `property_types`");

        $this->pdo->conn()->query("CREATE TABLE `property_types` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(50) NOT NULL,
            `description` TEXT NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (`id`)
        )
        COLLATE='utf8mb4_unicode_ci'
        ;");

        //$this->pdo->conn()->query("ALTER TABLE `property_types`
          //  ADD CONSTRAINT `FK_property_types_properties` FOREIGN KEY (`id`) REFERENCES `properties` (`property_type_id`);");

    }

    public function saveDataToDatabase() {
        //pulls data from api and saves to database
        //utilises ratelimiting and saves data to database in chunks to speed up performance
        $seenPropertyTypes = [];

        while ($this->pageNumber <= $this->lastPage) {

            echo 'Requesting page ' . $this->pageNumber . '...';

            try {

                $res = $this->guzzle->request('GET', $this->endPoint . '/api/properties', [
                    'api_key' => $this->apiKey,
                    'query' => [
                        'page' => ['size' => $this->pageSize, 'number' => $this->pageNumber]
                    ]
                ]);

            } catch (\Exception $e) {
                //if we have gone too fast and we aren't allowed to request anything else, pause for 10 seconds then retry
                if (!empty($res) && $res->getStatusCode() == 429) { //too many requests
                    echo 'Error ... retrying...';
                    sleep(10);
                    continue;
                }
            }

            echo "\n";

            if (!empty($res) && $res->getStatusCode() == 200) {
                //request was sucessful begin to process data
                $response = json_decode($res->getBody(), 1);
                if ($response['last_page'] != $this->lastPage) {
                    $this->lastPage = $response['last_page'];
                    echo $this->lastPage . ' total pages...' . "\n";
                }

                //prepare statement, with 17 slots for data, matching fields
                $query = "INSERT INTO properties(uuid, property_type_id, county, country, town, `description`, `address`, image_full, image_thumbnail, latitude, longitude, num_bedrooms, num_bathrooms, price, `type`, created_at, updated_at) VALUES (" . implode(",", array_fill(0, 17, '?')) . ");";

                $stmt = $this->pdo->conn()->prepare($query);

                //try {
                    $this->pdo->conn()->beginTransaction();

                    foreach($response['data'] as $row) {
                        
                        $sanitized = [];

                        foreach($row as $key => $value) {
                            if ($key == 'property_type') {
                                //store these seperately in property_types table and link via foreign key to properties
                                $ptSanitized = [];

                                if (!in_array($value['id'], $seenPropertyTypes)) {
                                    $seenPropertyTypes[] = $value['id'];

                                    $ptQuery = "INSERT INTO property_types(id, title, `description`, created_at, updated_at) VALUES(?,?,?,?,?);";
                                    $ptStmt = $this->pdo->conn()->prepare($ptQuery);

                                    foreach($value as $ptKey => $ptValue) {
                                        if ($ptKey == 'updated_at' && empty($ptValue)) {
                                            $ptSanitized[] = date("Y-m-d H:i:s");
                                            continue;
                                        }

                                        $ptSanitized[] = filter_var($ptValue, FILTER_SANITIZE_STRING);
                                    }

                                    if (!$ptStmt->execute($ptSanitized)) {
                                        //print debug data to console if execution failed
                                        var_dump($ptQuery, $ptSanitized, $ptStmt->error, $ptStmt->param_count);
                                    }
                                }

                                continue; //do not try to insert into properties table
                            }

                            $sanitized[] = filter_var($value, FILTER_SANITIZE_STRING);
                        }

                        if (!$stmt->execute($sanitized)) {
                            var_dump($query, $sanitized, $stmt->error, $stmt->param_count);
                        }
                    }

                    $this->pdo->conn()->commit();
                    //commit queries to db before next api request

                /*} catch (Exception $e) {
                    $this->pdo->conn()->rollback();
                    throw $e;
                }*/
            }
            
            //run for next page
            $this->pageNumber++;
        }
    }

    public function pdo() {
        //return pdo object so it can be closed from elsewhere
        return $this->pdo;
    }
}