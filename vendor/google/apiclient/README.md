[![Build Status](https://travis-ci.org/google/google-api-php-client.svg)](https://travis-ci.org/google/google-api-php-client.svg?branch=master)

# Google APIs Client Library for PHP #

> ### NOTE: If you arrived here from `developers.google.com`, you should be using the [v1-branch](https://github.com/google/google-api-php-client/tree/v1-master) of this repo

## Description ##
The Google API Client Library enables you to work with Google APIs such as Google+, Drive, or YouTube on your server.

## Beta ##
This library is in Beta. We're comfortable enough with the stability and features of the library that we want you to build real production applications on it. We will make an effort to support the public and protected surface of the library and maintain backwards compatibility in the future. While we are still in Beta, we reserve the right to make incompatible changes. If we do remove some functionality (typically because better functionality exists or if the feature proved infeasible), our intention is to deprecate and provide ample time for developers to update their code.

## Requirements ##
* [PHP 5.4.0 or higher](http://www.php.net/)

## Developer Documentation ##
http://developers.google.com/api-client-library/php

## Installation ##

You can use **Composer** or simply **Download the Release**

### Composer

The preferred method is via [composer](https://getcomposer.org). Follow the
[installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have
composer installed.

Once composer is installed, execute the following command in your project root to install this library:

```sh
composer require google/apiclient:^2.0.0@RC
```

Finally, be sure to include the autoloader:

```php
require_once '/path/to/your-project/vendor/autoload.php';
```

### Download the Release

If you abhor using composer, you can download the package in its entirety. The [Releases](https://github.com/google/google-api-php-client/releases) page lists all stable versions. Download any file
with the name `google-api-php-client-[RELEASE_NAME].zip` for a package including this library and its dependencies.

Uncompress the zip file you download, and include the autoloader in your project:

```php
require_once '/path/to/google-api-php-client/vendor/autoload.php';
```

For additional installation and setup instructions, see [the documentation](https://developers.google.com/api-client-library/php/start/installation).

## Basic Example ##
See the examples/ directory for examples of the key client features. You can
view them in your browser by running the php built-in web server.

```
$ php -S localhost:8000 -t examples/
```

And then browsing to the host and port you specified
(in the above example, `http://localhost:8000`).

```PHP
// include your composer dependencies
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName("Client_Library_Examples");
$client->setDeveloperKey("YOUR_APP_KEY");

$service = new Google_Service_Books($client);
$optParams = array('filter' => 'free-ebooks');
$results = $service->volumes->listVolumes('Henry David Thoreau', $optParams);

foreach ($results as $item) {
  echo $item['volumeInfo']['title'], "<br /> \n";
}
```

### Service Specific Examples ###

YouTube: https://github.com/youtube/api-samples/tree/master/php

## Frequently Asked Questions ##

### What do I do if something isn't working? ###

For support with the library the best place to ask is via the  google-api-php-client tag on StackOverflow: http://stackoverflow.com/questions/tagged/google-api-php-client

If there is a specific bug with the library, please file a issue in the Github issues tracker, including a (minimal) example of the failing code and any specific errors retrieved. Feature requests can also be filed, as long as they are core library requests, and not-API specific: for those, refer to the documentation for the individual APIs for the best place to file requests. Please try to provide a clear statement of the problem that the feature would address.

### How do I contribute? ###

We accept contributions via Github Pull Requests, but all contributors need to be covered by the standard Google Contributor License Agreement. You can find links, and more instructions, in the documentation: https://developers.google.com/api-client-library/php/contribute

### I want an example of X! ###

If X is a feature of the library, file away! If X is an example of using a specific service, the best place to go is to the teams for those specific APIs - our preference is to link to their examples rather than add them to the library, as they can then pin to specific versions of the library. If you have any examples for other APIs, let us know and we will happily add a link to the README above!

### Why do you still support 5.2? ###

When we started working on the 1.0.0 branch we knew there were several fundamental issues to fix with the 0.6 releases of the library. At that time we looked at the usage of the library, and other related projects, and determined that there was still a large and active base of PHP 5.2 installs. You can see this in statistics such as the PHP versions chart in the WordPress stats: http://wordpress.org/about/stats/. We will keep looking at the types of usage we see, and try to take advantage of newer PHP features where possible.

### Why does Google_..._Service have weird names? ###

The _Service classes are generally automatically generated from the API discovery documents: https://developers.google.com/discovery/. Sometimes new features are added to APIs with unusual names, which can cause some unexpected or non-standard style naming in the PHP classes.

### How do I deal with non-JSON response types? ###

Some services return XML or similar by default, rather than JSON, which is what the library supports. You can request a JSON response by adding an 'alt' argument to optional params that is normally the last argument to a method call:

```
$opt_params = array(
  'alt' => "json"
);
```

### How do I set a field to null? ###

The library strips out nulls from the objects sent to the Google APIs as its the default value of all of the uninitialised properties. To work around this, set the field you want to null to Google_Model::NULL_VALUE. This is a placeholder that will be replaced with a true null when sent over the wire.

## Code Quality ##

Run the PHPUnit tests with PHPUnit. You can configure an API key and token in BaseTest.php to run all calls, but this will require some setup on the Google Developer Console.

    phpunit tests/

### Coding Style

To check for coding style violations, run

```
vendor/bin/phpcs src --standard=style/ruleset.xml -np
```

To automatically fix (fixable) coding style violations, run

```
vendor/bin/phpcbf src --standard=style/ruleset.xml
```
