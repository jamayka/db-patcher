DB patcher
==========

Small PHP CLI script to patch DB. Supports .sql and .php patches.

Installation
------------

Include as a requirement in your composer json.
db-patcher.php will be available in your bin directory (usually vendor/bin)

Also needs configuration in json or php format. It tries to find db-patcher.json or db-patcher.php in your project
directory etc or data/etc. But if you have specific vendor directory or you have configuration in other directories you
should specify config file using command option --config

Example of PHP configuration file:

    <?php
    return array(
        'db' => array(
            'dbname' => 'dbname',
            'user' => 'user',
            'password' => 'password',
            'host' => 'localhost',
            'port' => '5432',
            'charset' => 'UTF8'
        ),
        'directory' => '../scripts/update/'
    );

Example of JSON configuration file:

    {
        "db": {
            "dbname": "dbname",
            "user": "user",
            "password": "password",
            "host": "localhost",
            "port": "5432",
            "charset": "UTF8"
        },
        "directory": "../scripts/update/"
    }

Usage
-----

    Usage: vendor/bin/db-patcher.php [options]

    Options:
        -l, --list Just output list of patches
        -n, --new Install new patches
        -c, --changed Install changed patches
        -e, --error Install error patches
        -a, --all Install all patches (installed, errors, changed, new)
        -i, --interactive Interactive mode
        -m, --mark-installed Do not actually apply patch just mark as installed
        -s, --stop-on-error Stop patches on error
        -p, --patch [name] Patch name to run (relative to patches directory)
        --pattern [pattern] Shell wildcard pattern for patch file name
        --config [filename] Config json filename
        -h, --help Output usage information
