php-chat
============

[![Build Status](https://secure.travis-ci.org/pmill/php-chat.svg?branch=master)](http://travis-ci.org/pmill/php-chat) [![Code Climate](https://codeclimate.com/github/pmill/php-chat/badges/gpa.svg)](https://codeclimate.com/github/pmill/php-chat) [![Test Coverage](https://codeclimate.com/github/pmill/php-chat/badges/coverage.svg)](https://codeclimate.com/github/pmill/php-chat/coverage) [![Test Coverage](https://scrutinizer-ci.com/g/pmill/php-chat/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/pmill/php-chat/)

Introduction
------------

A multi-user multi-room ratchet server.

Requirements
------------

This library package requires PHP 5.4 or later.

Installation
------------

### Installing via Composer

The recommended way to install php-chat is through
[Composer](http://getcomposer.org).

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

Next, run the Composer command to install the latest version of php-chat:

```bash
composer.phar require pmill/php-chat
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

Usage
-----

An example is provided in the example/ directory. Start the server with the command:

    php example/server.php

An example HTML client interface is located at example/client.html. You will need to update the chatUrl variable in 
example/chat.js with the host name (or ip address) of the server you ran the previous command on.
 
    var chatUrl = 'ws://your-host-name:9911';

Version History
---------------

0.2.0 (09/07/2015)

*   Separated server and output into separate classes
*   Added user defined message logging

0.1.0 (08/07/2015)

*   First public release of php-chat


Copyright
---------

php-chat
Copyright (c) 2015 pmill (dev.pmill@gmail.com) 
All rights reserved.
