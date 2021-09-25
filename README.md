# property

# Instructions to run the application

Fill out config.example.php and then rename to config.php.

Run 'php request_data.php' in the root folder to store the data from the api into the database.

# Rationale

If I had more time I would tidy up the ui and make it more user friendly, also add validation to search.
I would move some of the code in index.php into other places where they would be best suited, like some sort of helper file or class.

I would add a feature to change results displayed per page, currently this can only be done through changing the query string parameter page_size.

Sensitive data including api keys is stored in config.php to keep it out of accidently being commited to version control for data security, a config.example.php file has been including.

Database schema has been included in property.sql file.