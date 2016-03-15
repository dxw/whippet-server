Whippet Server
-------

Whippet Server launches a stand-alone web server for a specific WordPress installation.
It makes WordPress easier to develop with, for example, by adding lots of debug
information to the terminal without cluttering up or breaking your templates.

Whippet Server allows you to run and work on a WordPress website without having to use
Apache and without having to set up a virtualhost. You don't even have to have
your WordPress files -- Whippet Server will happily run from a wp-content folder by itself.

Note: Whippet Server is Alpha software. We're sure it still has problems that need to be
fixed, and we know the install process is a bit labourious. Please do let us know
how you get on, or open an issue if you have problems. Thanks!


Installation
------------

Clone the repository and run:

    $ composer install

You might also want to symlink Whippet Server to something in your path:

    $ sudo ln -s /path/to/the/script/called/whippet-server /usr/bin/whippet-server

### PHP >= 5.4

Whippet Server requires PHP 5.4 or greater. It must have been compiled with the --enable_pcntl flag otherwise you will see "Call to undefined function pcntl_signal()" errors.

Check which version you're currently using by running php -v.

To install do:

#### Under Ubuntu 12.04 LTS

    $ sudo add-apt-repository ppa:ondrej/php5
    $ sudo apt-get update
    $ sudo apt-get install php5

#### Under Ubuntu 12.10

    $ sudo apt-get install php5

#### Under OSX

See http://php-osx.liip.ch/ for an easy install

Depending on how your path is set up, you may need to add the install location to your path (edit your .bashrc or similar)

Using the above method you may get an error when trying to run whippet-server:

    Error: Unable to find file /etc/mime.types, and failed to load fallback

In which case you can obtain the most recent mime file here: http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
Instruct Whippet Server to use this file using the --mime-file argument, or save it to /etc/mime.types

#### Other operating systems
For Windows, I think you might be out of luck. If you manage to get it working, we'd love to know what you did.

For other operating systems, please consult Google, or download and install from PHP.net: http://php.net/downloads.php

#### Handling multiple versions of PHP
If for some reason you don't want to use 5.4 as your system's version of PHP, make sure your system PHP comes up first in your PATH,
and then tell Whippet Server where to find PHP5.4 on your system:

    $ WHIPPET_PHP=/path/to/php/5.4 whippet

### MYSQL
If you've directly installed MYSQL on your system, Whippet Server should just work. If you encounter any problems, please raise an issue.

#### MYSQL through MAMP (OSX)

If you're using MAMP, and don't want to install MYSQL directly for whatever reason, you need to tell PHP5.4 how to use MAMP's MySQL server. To fix this, update your php.ini:

    $ sudo vi /usr/local/etc/php/5.4/php.ini

And put in the option for mysql.default_socket:

    mysql.default_socket = /Applications/MAMP/tmp/mysql/mysql.sock


Docker usage
------------

Run it on port 80:

    $ docker run -ti --rm -v /path/to/wp-content:/app -p 80:80 thedxw/whippet-server

The default command is "whippet-server -i 0.0.0.0 -p 80 --show-wp-errors --siteurl=http://localhost", so if you need WordPress to put a different domain in absolute URIs, or you want to have it listen on a different port:

    $ docker run -ti --rm -v /path/to/wp-content:/app -p 8000:80 thedxw/whippet-server whippet-server -i 0.0.0.0 -p 80 --show-wp-errors --siteurl=http://mysite.local:8000

(Note that the --siteurl option merely sets the WP_SITEURL and WP_HOME constants - you'll have problems if those are set differently to the equivalent options in the database).

If you're making changes to whippet-server or for whatever reason the image isn't available on dockerhub, you'll need to build it before you do that:

    $ docker build -t thedxw/whippet-server .

The below section includes notes about options which may be useful.


Usage
-----

### The server
The simplest way to use Whippet Server is on an existing WordPress installation. If you have
a working installation, cd to the root of the WordPress installation and do:

    $ /path/to/whippet-server

You should see the server start. Visit http://localhost:8000 and you should see a normal
WordPress website. If you look at your terminal, you'll see quite a bit of output about
the queries being exected, templates being loaded, and so on.

### Filtering server output 
Sometimes Whippet Server's output can be a bit too noisy. You can customise what gets displayed:

    $ /path/to/whippet-server --no-sql

For a full listing of Whippet Server's options, do:

    $ /path/to/whippet-server --help
    
#### Setting Defaults

Any of these options can be set as defaults for Whippet by creating `~/.whippetrc` or `/etc/whippetrc`. Both files will be read if present. Your local defaults will override system-wide defaults. The file should be in ini format:

```ini
p = 8080
i = my-machine.local
show-wp-errors = true
; this is a comment
```

### Output to the console
A common way to debug WordPress sites is to output to the browser with `var_dump()` or `die()`. When running Whippet you can instead output to the console:

    Whippet::print_r(thing-to-be-output)

### Managing WordPress
Whippet Server can also make it easier to manage multiple WordPress installations. Instead of having
to keep a full WordPress installation for each site that you work on, you can just keep the
wp-content folder, along with the database for that site:

    $ cd /path/to/wp-content
    $ /path/to/whippet-server

Whippet Server will detect that it's being launched from a wp-content folder. The first time you do
this, it will ask to download and store the latest WordPress core. It'll then use those files
to launch the site. You can also specify the version of WordPress you'd like to use:

    $ /path/to/whippet-server --wp-version 4.1

Whippet Server will ask to download the files if they don't already exist, and will prompt you for
your database configuration the first time you run it for a particular wp-content diretory.

### Multisite

`WP_ALLOW_MULTISITE` is always enabled, so you can do Tools > Network Setup at any time. Once
multisite has been setup in the database, you should send whippet-server a SIGTERM and restart it
with `--multisite` and `-p 80`.

Note that if you don't want to do a search/replace on your database you should use `-p 80`
before you enable multisite.

Remember that `sudo` is required for port 80 and you may need to kill a server if you already
have on listening on port 80.

### Troubleshooting
#### Issue:
After installing on OSX and running whippet-server you get the following error in your browser:

    Your PHP installation appears to be missing the MySQL extension which is required by WordPress

#### Solution:
Reinstall php, ensuring that you include the `--with-mysql` option.


Contribute
----------

We'd welcome help to make Whippet Server better. If you want to fix a bug or add a feature,
please fork the project and Github, make changes, and submit a pull request. If it's
something big, you might want to talk to us first. If you need inspiration, check the
TODO page on the wiki.

Authors
-------

Harry Metcalfe (harry@dxw.com)

Tom Adams (tom@dxw.com)
