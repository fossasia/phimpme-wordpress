In order to install Wordpress Phimpme and test the system on your local host, please follow the following steps:


1. Restore database from sql file in wordpress/sql folder
  a. Login to mysql use command line:
     mysql -uroot -p
  b. create phimpme_drupal database : 
     CREATE DATABASE phimpme_wordpress;
     exit;

  c. Restore database :
     mysql -u root -p phimpme_wordpress < link to phimpme_wordpress.sql file
     e.g :
     mysql -u root -p phimpme_wordpress < ~/phimpme.cms/wordpress/sql/phimpme_wordpress.sql


2. Move wordpress website folder to /var/www/ or your specific lolalhost directory and change permission for this folder.
    - Command: cd /var/www/wordpress
    - Command: sudo chmod -R 777 wordpress/

3. Open file wp-config.php and change value of Database in line 18 like this :

/** The name of the database for WordPress */
define('DB_NAME', 'phimpme_wordpress');

/** MySQL database username */
define('DB_USER', 'mysql_username');

/** MySQL database password */
define('DB_PASSWORD', 'mysql_password');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('WP_HOME','http://'.$_SERVER['HTTP_HOST'].'/wordpress');
define('WP_SITEURL','http://'.$_SERVER['HTTP_HOST'].'/wordpress');

Save this file.


4. In your browser go to localhost/wordpress/ and check the site.
The credentials of the administrators account of the test site are:

Login: test
Password: test


5. Upload a picture with the phimpme app of the user 'test'.

   To test Wordpress website with Phimp.Me app
    - Connect your phone and your computer same network.
    - Type ifconfig to detect your ip address.
       Command: ifconfig
    Read IP address and type the following into the phimpme wordpress form on the app:
    Username: test
    Password: test
    Services link: e.g. on your localhost with the following IP http://192.168.1.19/wordpress/
