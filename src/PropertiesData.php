<?php
namespace App;

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

        $this->setupGuzzle();
        $this->pdo = new PDOHelper();
    }

    public function paginate(int $page, int $pageSize) {
        $result = $this->pdo->conn()->query("SELECT COUNT(id) FROM properties")->fetch();

        $stmt = $this->pdo->conn()->prepare("
        SELECT p.*, pt.title as property_type_title, pt.description as property_type_description FROM properties p 
        LEFT JOIN property_types pt ON pt.id = p.property_type_id 
        LIMIT :offset, :rows");

        $stmt->bindParam(':offset', $page, PDO::PARAM_INT);
        $stmt->bindParam(':rows', $pageSize, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    public function refreshTables() {
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
                if (!empty($res) && $res->getStatusCode() == 429) {
                    echo 'Error ... retrying...';
                    sleep(10);
                    continue;
                }
            }

            echo "\n";

            if (!empty($res) && $res->getStatusCode() == 200) {
                $response = json_decode($res->getBody(), 1);
                if ($response['last_page'] != $this->lastPage) {
                    $this->lastPage = $response['last_page'];
                    echo $this->lastPage . ' total pages...' . "\n";
                }

                $query = "INSERT INTO properties(uuid, property_type_id, county, country, town, `description`, `address`, image_full, image_thumbnail, latitude, longitude, num_bedrooms, num_bathrooms, price, `type`, created_at, updated_at) VALUES (" . implode(",", array_fill(0, 17, '?')) . ");";

                $stmt = $this->pdo->conn()->prepare($query);

                //try {
                    $this->pdo->conn()->beginTransaction();

                    foreach($response['data'] as $row) {
                        
                        $sanitized = [];

                        foreach($row as $key => $value) {
                            if ($key == 'property_type') {
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

                /*} catch (Exception $e) {
                    $this->pdo->conn()->rollback();
                    throw $e;
                }*/
            }
            
            $this->pageNumber++;
        }
    }

    public function pdo() {
        return $this->pdo;
    }
}