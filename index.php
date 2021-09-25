<?php
require "vendor/autoload.php";
require_once "config.php";

use App\PropertiesData;

$page = $_GET["page"] ?? 1;
$pageSize = $_GET["page_size"] ?? 30;

$properties = new PropertiesData();
$result = $properties->paginate($page, $pageSize);
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
                            <th>For Sale / For Rent</th>
                        </tr>
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
                            <td>&pound;<?php echo number_format($row['price'], 2, '.', ','); ?></td>
                            <td><?php echo $row['property_type_title']; ?></td>
                            <td><?php echo $row['type']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>