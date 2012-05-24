Whippet
-------

Whippet launches a stand-alone web server for a specific WordPress installation.
It makes WordPress easier to develop with, for example, by adding lots of debug 
information to the terminal without cluttering up or breaking your templates.

Whippet allows you to run and work on a WordPress website without having to use 
Apache and without having to set up a virtualhost. You don't even have to have 
your WordPress files -- Whippet will happily run from a wp-content folder by itself.


Installation
------------

Clone the repository and run:

    $ git submodule update --init

Whippet requires PHP 5.4. Under Ubuntu, do:

    $ sudo add-apt-repository ppa:ondrej/php5
    $ sudo apt-get update
    $ sudo apt-get install php5

Under OS X, do:

    $ brew install php --devel

You will need Homebrew: http://mxcl.github.com/homebrew/

If you now have more than one version of PHP on your system, you may need to change the first line of the whippet 
script to:

    #!/usr/local/bin/php

You can check by running php -v. It should be 5.4.x. At some point, we will figure out a more elegant solution.

For other operating systems, please consult Google, or download and install from PHP.net: http://php.net/downloads.php

You might also want to symlink Whippet to something in your path:

    $ sudo ln -s /path/to/the/script/called/whippet /usr/bin/whippet

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
    $ path/to/whippet

Whippet will detect that it's being launched from a wp-content folder. The first time you do
this, it will ask to download and store the latest WordPress core. It'll then use those files 
to launch the site. You can also specify the version of WordPress you'd like to use:

    $ path/to/whippet --wp-version 3.1

Whippet will ask to download the files if they don't already exist, and will prompt you for 
your database configuration the first time you run it for a particular wp-content diretory.


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
