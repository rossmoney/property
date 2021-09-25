<?php
//remove 'page' parameter from the query string so it can be replaced with other values for page, but the search terms are retained
$queryString = remove_url_query('?' . $_SERVER['QUERY_STRING'], 'page');
$queryString = str_replace("?", '&', $queryString);
?>

<nav>
    <ul class="pagination" style="flex-wrap: wrap;">
        <li class="page-item <?php echo $page == 1 ? 'disabled' : '' //disable previous when at first page ?>"><a class="page-link" href="?page=<?php echo ($page - 1) . $queryString; ?>">Previous</a></li>
        <?php for($i = 1; $i <= ($pageCount > 100 ? 100 : $pageCount); $i++) : //only show up to 30 page links, you can go further with 'Next' but page isn't cluttered ?>
        <li class="page-item <?php echo $page == $i ? 'active' : '' ?>"><a class="page-link" href="?page=<?php echo $i  . $queryString; ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?php echo ($page == $pageCount) ? 'disabled' : '' //disable next when end of pages reached ?>"><a class="page-link" href="?page=<?php echo ($page + 1) . $queryString; ?>">Next</a></li>
    </ul>
</nav>