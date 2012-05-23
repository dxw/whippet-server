Whippet
-------

Whippet launches a stand-alone web server for a specific WordPress installation.
It makes WordPress easier to develop with, for example, by adding lots of debug 
information to the terminal without cluttering up or breaking your templates.


Installation
------------

Depends on PHP 5.4. Under Ubuntu, do:

    $ sudo add-apt-repository ppa:ondrej/php5
    $ sudo apt-get update
    $ sudo apt-get install php5

Under OS X, there are some solutions here: http://stackoverflow.com/questions/9534451/how-do-i-install-php-5-4-on-mac-os-x-lion

For other operating systems, please consult Google, or, you can download from PHP.net: http://php.net/downloads.php

To get started, cd to the whippet directory, and do:

    $ ./whippet path/to/a/WordPress

You may get an error, but all the errors should tell you what to do. For a full
listing of Whippet's options and arguments, run:

    $ ./whippet --help


Contribute
----------

We'd welcome help to make Whippet better. If you want to fix a bug or add a feature, 
please fork the project and Github, make changes, and submit a pull request. If it's 
something big, you might want to talk to us first.

Authors
-------

Harry Metcalfe (harry@dxw.com)

Tom Adams (tom@dxw.com)
