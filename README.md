Whippet
-------

Whippet launches a stand-alone web server for a specific WordPress installation.
It makes WordPress easier to develop with, for example, by adding lots of debug 
information to the terminal without cluttering up or breaking your templates.

Whippet allows you to run and work on a WordPress website without having to use 
Apache and without having to set up a virtualhost. You don't even have to have 
your WordPress files -- Whippet will happily run from a wp-content folder by itself.

Note: Whippet is Alpha software. We're sure it still has problems that need to be
fixed, and we know the install process is a bit labourious. Please do let us know
how you get on, or open an issue if you have problems. Thanks!


Installation
------------

Clone the repository and run:

    $ git submodule update --init
    
You might also want to symlink Whippet to something in your path:

    $ sudo ln -s /path/to/the/script/called/whippet /usr/bin/whippet

Whippet requires PHP 5.4. To install do:

### Under Ubuntu 12.04 LTS

    $ sudo add-apt-repository ppa:ondrej/php5
    $ sudo apt-get update
    $ sudo apt-get install php5

### Under Ubuntu 12.10

    $ sudo apt-get install php5

### Under OS X

    $ brew install php --devel --with-mysql

You will need Homebrew: http://mxcl.github.com/homebrew/

If you now have more than one version of PHP on your system, you may need to tell Whippet which one to
use:
   
    $ WHIPPET_PHP=/usr/bin/local/php whippet 

You can check by running php -v. It should be 5.4.x. At some point, we will figure out a more elegant solution.

### Other operating systems
For Windows, I think you might be out of luck. If you manage to get it working, we'd love to know what you did.

For other operating systems, please consult Google, or download and install from PHP.net: http://php.net/downloads.php
    

Usage
-----

The simplest way to use Whippet is on an existing WordPress installation. If you have
a working installation, cd to the root of the WordPress installation and do:

    $ /path/to/whippet

You should see the server start. Visit http://localhost:8000 and you should see a normal
WordPress website. If you look at your terminal, you'll see quite a bit of output about
the queries being exected, templates being loaded, and so on.

Sometimes Whippet's output can be a bit too noisy. You can customise what gets displayed:

    $ /path/to/whippet --no-sql

For a full listing of Whippet's options, do:

    $ /path/to/whippet --help

Whippet can also make it easier to manage multiple WordPress installations. Instead of having
to keep a full WordPress installation for each site that you work on, you can just keep the 
wp-content folder, along with the database for that site:

    $ cd /path/to/wp-content
    $ /path/to/whippet

Whippet will detect that it's being launched from a wp-content folder. The first time you do
this, it will ask to download and store the latest WordPress core. It'll then use those files 
to launch the site. You can also specify the version of WordPress you'd like to use:

    $ /path/to/whippet --wp-version 3.1

Whippet will ask to download the files if they don't already exist, and will prompt you for 
your database configuration the first time you run it for a particular wp-content diretory.

### Multisite

`WP_ALLOW_MULTISITE` is always enabled, so you can do Tools > Network Setup at any time. Once
multisite has been setup in the database, you should send whippet a SIGTERM and restart it
with `--multisite` and `-p 80`.

### Troubleshooting
#### Issue:
After installing on OSX and running whippet you get the following error in your browser:

    Your PHP installation appears to be missing the MySQL extension which is required by WordPress
 
#### Solution:  
Reinstall php, ensuring that you include the `--with-mysql` option.


Contribute
----------

We'd welcome help to make Whippet better. If you want to fix a bug or add a feature, 
please fork the project and Github, make changes, and submit a pull request. If it's 
something big, you might want to talk to us first. If you need inspiration, check the
TODO page on the wiki.

Authors
-------

Harry Metcalfe (harry@dxw.com)

Tom Adams (tom@dxw.com)
