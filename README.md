# WebScrapingAI

WebScraping.AI scraping API provides LLM-powered tools with Chromium JavaScript rendering, rotating proxies, and built-in HTML parsing.

For more information, please visit [https://webscraping.ai](https://webscraping.ai).

## Installation & Usage

### Requirements

PHP 7.4 and later.
Should also work with PHP 8.0.

### Composer

To install the bindings via [Composer](https://getcomposer.org/), add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/webscraping-ai/webscraping-ai-php.git"
    }
  ],
  "require": {
    "webscraping-ai/webscraping-ai-php": "*@dev"
  }
}
```

Then run `composer install`

### Manual Installation

Download the files and include `autoload.php`:

```php
<?php
require_once('/path/to/WebScrapingAI/vendor/autoload.php');
```

## Getting Started

Please follow the [installation procedure](#installation--usage) and then run the following:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\AIApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the target page.
$fields = {"title":"Main product title","price":"Current product price","description":"Full product description"}; // array<string,string> | Object describing fields to extract from the page and their descriptions
$headers = {"Cookie":"session=some_id"}; // array<string,string> | HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&headers[One]=value1&headers=[Another]=value2) or as a JSON encoded object (...&headers={\"One\": \"value1\", \"Another\": \"value2\"}).
$timeout = 10000; // int | Maximum web page retrieval time in ms. Increase it in case of timeout errors (10000 by default, maximum is 30000).
$js = true; // bool | Execute on-page JavaScript using a headless browser (true by default).
$js_timeout = 2000; // int | Maximum JavaScript rendering time in ms. Increase it in case if you see a loading indicator instead of data on the target page.
$wait_for = 'wait_for_example'; // string | CSS selector to wait for before returning the page content. Useful for pages with dynamic content loading. Overrides js_timeout.
$proxy = datacenter; // string | Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default). Note that residential proxy requests are more expensive than datacenter, see the pricing page for details.
$country = us; // string | Country of the proxy to use (US by default).
$custom_proxy = 'custom_proxy_example'; // string | Your own proxy URL to use instead of our built-in proxy pool in \"http://user:password@host:port\" format (<a target=\"_blank\" href=\"https://webscraping.ai/proxies/smartproxy\">Smartproxy</a> for example).
$device = desktop; // string | Type of device emulation.
$error_on_404 = false; // bool | Return error on 404 HTTP status on the target page (false by default).
$error_on_redirect = false; // bool | Return error on redirect on the target page (false by default).
$js_script = document.querySelector('button').click();; // string | Custom JavaScript code to execute on the target page.

try {
    $result = $apiInstance->getFields($url, $fields, $headers, $timeout, $js, $js_timeout, $wait_for, $proxy, $country, $custom_proxy, $device, $error_on_404, $error_on_redirect, $js_script);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AIApi->getFields: ', $e->getMessage(), PHP_EOL;
}

```

## API Endpoints

All URIs are relative to *https://api.webscraping.ai*

Class | Method | HTTP request | Description
------------ | ------------- | ------------- | -------------
*AIApi* | [**getFields**](docs/Api/AIApi.md#getfields) | **GET** /ai/fields | Extract structured data fields from a web page
*AIApi* | [**getQuestion**](docs/Api/AIApi.md#getquestion) | **GET** /ai/question | Get an answer to a question about a given web page
*AccountApi* | [**account**](docs/Api/AccountApi.md#account) | **GET** /account | Information about your account calls quota
*HTMLApi* | [**getHTML**](docs/Api/HTMLApi.md#gethtml) | **GET** /html | Page HTML by URL
*SelectedHTMLApi* | [**getSelected**](docs/Api/SelectedHTMLApi.md#getselected) | **GET** /selected | HTML of a selected page area by URL and CSS selector
*SelectedHTMLApi* | [**getSelectedMultiple**](docs/Api/SelectedHTMLApi.md#getselectedmultiple) | **GET** /selected-multiple | HTML of multiple page areas by URL and CSS selectors
*TextApi* | [**getText**](docs/Api/TextApi.md#gettext) | **GET** /text | Page text by URL

## Models

- [Account](docs/Model/Account.md)
- [Error](docs/Model/Error.md)

## Authorization

Authentication schemes defined for the API:
### api_key

- **Type**: API key
- **API key parameter name**: api_key
- **Location**: URL query string


## Tests

To run the tests, use:

```bash
composer install
vendor/bin/phpunit
```

## Author

support@webscraping.ai

## About this package

This PHP package is automatically generated by the [OpenAPI Generator](https://openapi-generator.tech) project:

- API version: `3.2.0`
    - Package version: `3.2.0`
    - Generator version: `7.11.0`
- Build package: `org.openapitools.codegen.languages.PhpClientCodegen`
