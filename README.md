DB patcher
==========

    Small PHP CLI script to patch DB. Supports .sql and .php patches.

Installation
------------

    Include as a requirement in your composer json.
    db-patcher.php will be available in your bin directory (usually vendor/bin)

Usage
-----

    Usage: bin/db-patcher.php [options]

    Options:
        -n, --new Install automatically only new patches
        -f, --force Install all patches (installed, errors, changed, new)
        -i, --interactive Interactive mode
        -s, --stop-on-error Stop patches on error
        -c, --config [filename] Config json filename
        -d, --dir [path] Patches directory path
        -p, --patch [name] Patch name to run (relative to patches directory)
        -h, --help Output usage information
