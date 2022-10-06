# YandexRestAPI
Class for working with YandexRestAPI in PHP. All functions from the [API](https://yandex.ru/dev/disk/api/concepts/about.html) are supported.
 
Getting started
---
First you need to get a unique OAuth token. You can find out how to do it from this [link](https://yandex.ru/dev/id/doc/dg/oauth/concepts/about.html).<br>
The next step is to include the module, create a class, do not forget to specify the token.
```php
require_once "yandexRestAPI.php";
define("OAUTH", "your OAuth token");
$api = new YandexRestAPI(OAUTH);
```

How to get information
---
To get disk data, you can refer to the functions of the created class. The names of all functions are exactly the same as the API requests.<br>
All requests return the YandexReturn class, which contains several variables:
- **$info** - HTTP request information. [Learn more.](https://www.php.net/manual/ru/function.curl-getinfo.php)
- **$data_type** - Response type. In case of an error, it is "Error", otherwise it depends on the request. [Learn more.](https://yandex.ru/dev/disk/api/reference/response-objects.html)
- **$data** - Information received from the request. [Learn more.](https://yandex.ru/dev/disk/api/reference/response-objects.html)

Example
---
To get meta information about a file or folder, you need to write the following code:
```php
$answer = $api->getMetaInfo("Path to the file or folder");

if ($answer->data_type != "Error") {
     var_dump($answer->data);
}
```
