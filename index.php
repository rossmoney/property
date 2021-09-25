<?php
require "vendor/autoload.php";
require_once "config.php";
require_once "helpers.php";

use App\PropertiesData;

//initialize class doing the majority of the work
$properties = new PropertiesData();

//initialize variables from input form parameters
$town = $_REQUEST['town'] ?? ''; 
$propertyType = $_REQUEST['property_type'] ?? '';
$priceMin = isset($_REQUEST['price_min']) ? $_REQUEST['price_min'] : '';
$priceMax = $_REQUEST['price_max'] ?? '';
$numBedrooms = $_REQUEST['num_bedrooms'] ?? '';

$page = $_REQUEST["page"] ?? 1;
$pageSize = $_REQUEST["page_size"] ?? 30;

//specific which fields we want to search on, then call helper function to generate array of search parameters for paginate function 
//based on what fields we can search + the sanitized search queries
$searchFields = ['num_bedrooms' => 'int', 'property_type' => 'string', 'price' => 'range', 'town' => 'like'];
$search = generate_search_params($searchFields);

//call properties class to retrieve the filtered data
list($pageCount, $totalResults, $result) = $properties->paginate($page, $pageSize, $search);
$properties->pdo()->close(); //close MySQL connection
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Properties :: Ross Money</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        <!-- Page content-->
            <div class="text-center mt-5">
                <h1>Properties</h1>

                <h2>Page <?php echo $page; ?>: Results <?php echo ((($page-1) * $pageSize) + 1). ' - ' . ($page * $pageSize); ?> of <?php echo $totalResults; ?></h2>

                <?php 
                //pagination buttons are duplicated top and bottom of result table, use include file to reduce repetition of code
                include('includes/pagination.php'); ?>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Town</th>
                            <th>County</th>
                            <th>Country</th>
                            <th>Description</th>
                            <th>Address</th>
                            <th>Image</th>
                            <th>Thumbnail</th>
                            <th>Location</th>
                            <th>Num Beds</th>
                            <th>Num Baths</th>
                            <th>Price</th>
                            <th>Property Type</th>
                            <th style="width: 100px;">For Sale / For Rent</th>
                        </tr>
                        <form method="get" action="/property">
                        <tr>
                            <th>
                                <input type="hidden" name="page_size" value="<?php echo $pageSize; ?>" />
                                <input type="text" name="town" class="form-control" value="<?php echo $town; ?>" /></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th><input type="number" step="1" min="1" style="width: 50px;" name="num_bedrooms" class="form-control" value="<?php echo $numBedrooms; ?>" /></th>
                            <th></th>
                            <th>
                                <input type="number" step="0.01" min="1" name="price_min" class="form-control" value="<?php echo $priceMin; ?>" /> - 
                                <input type="number" step="0.01" min="1" name="price_max" class="form-control" value="<?php echo $priceMax; ?>" />
                            </th>
                            <th><input type="text" name="property_type" class="form-control" value="<?php echo $propertyType; ?>" /></th>
                            <th><button class="btn btn-primary" type="submit">Search</button></th>
                        </tr>
                        </form>
                    </thead>
                    <tbody>
                        <?php foreach($result as $row) : ?>
                        <tr>
                            <td><?php echo $row['town']; ?></td>
                            <td><?php echo $row['county']; ?></td>
                            <td><?php echo $row['country']; ?></td>
                            <td><?php echo $row['description']; ?></td>
                            <td><?php echo $row['address']; ?></td>
                            <td><img style="width: 200px;" src="<?php echo $row['image_full']; ?>"></img></td>
                            <td><img style="width: 100px;" src="<?php echo $row['image_thumbnail']; ?>"></img></td>
                            <td><?php echo $row['latitude'] .',<br>' . $row['longitude']; ?></td>
                            <td><?php echo $row['num_bedrooms']; ?></td>
                            <td><?php echo $row['num_bathrooms']; ?></td>
                            <?php //format price in pound format ?>
                            <td>&pound;<?php echo number_format($row['price'], 2, '.', ','); ?></td>
                            <td><?php echo $row['property_type_title']; ?></td>
                            <td><?php echo $row['type']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php include('includes/pagination.php'); ?>
            </div>

        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>