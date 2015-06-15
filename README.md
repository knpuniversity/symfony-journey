Journey to the Center of Symfony
================================

See http://knpuniversity.com/screencast/symfony-journey.

Installation
------------

1) Download the "Code" from any screencast page (available
   once you're susbcribed). Or, you can clone this repository
   from GitHub

2) If you downloaded the code, unzip it, open up a terminal,
   and move into the `start` directory:

```
cd start
```

3) Download the vendor libraries via [Composer](https://getcomposer.org/):

```
composer install
```

You will be asked for your database credentials at the end, which save
into the app/config/parameters.yml file.

4) Build the database and load in the test data!

```
php app/console doctrine:database:create
php app/console doctrine:schema:create
php app/console doctrine:fixtures:load
```

5) Start the built-in PHP web server:

```
php app/console server:run
```

6) Load up the app in your browser!

    http://localhost:8000

Have fun!

Collaboration
-------------

As we start writing the content for this tutorial, we invite you to read
through it, try things out, and offer improvements, either as issues on this
repository or as pull requests. Stuff is hard, so the more smart minds we
can have on it, the better it will be for everyone.

Cheers!
