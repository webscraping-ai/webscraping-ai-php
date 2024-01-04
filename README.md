# WebScrapingAI

WebScraping.AI scraping API provides GPT-powered tools with Chromium JavaScript rendering, rotating proxies, and built-in HTML parsing.

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
$question = What is the summary of this page content?; // string | Question or instructions to ask the LLM model about the target page.
$context_limit = 4000; // int | Maximum number of tokens to use as context for the LLM model (4000 by default).
$response_tokens = 100; // int | Maximum number of tokens to return in the LLM model response. The total context size (context_limit) includes the question, the target page content and the response, so this parameter reserves tokens for the response (see also on_context_limit).
$on_context_limit = truncate; // string | What to do if the context_limit parameter is exceeded (truncate by default). The context is exceeded when the target page content is too long.
$headers = {"Cookie":"session=some_id"}; // array<string,string> | HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&headers[One]=value1&headers=[Another]=value2) or as a JSON encoded object (...&headers={\"One\": \"value1\", \"Another\": \"value2\"}).
$timeout = 10000; // int | Maximum web page retrieval time in ms. Increase it in case of timeout errors (10000 by default, maximum is 30000).
$js = true; // bool | Execute on-page JavaScript using a headless browser (true by default).
$js_timeout = 2000; // int | Maximum JavaScript rendering time in ms. Increase it in case if you see a loading indicator instead of data on the target page.
$proxy = datacenter; // string | Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default). Note that residential proxy requests are more expensive than datacenter, see the pricing page for details.
$country = us; // string | Country of the proxy to use (US by default). Only available on Startup and Custom plans.
$device = desktop; // string | Type of device emulation.
$error_on_404 = false; // bool | Return error on 404 HTTP status on the target page (false by default).
$error_on_redirect = false; // bool | Return error on redirect on the target page (false by default).
$js_script = document.querySelector('button').click();; // string | Custom JavaScript code to execute on the target page.

try {
    $result = $apiInstance->getQuestion($url, $question, $context_limit, $response_tokens, $on_context_limit, $headers, $timeout, $js, $js_timeout, $proxy, $country, $device, $error_on_404, $error_on_redirect, $js_script);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AIApi->getQuestion: ', $e->getMessage(), PHP_EOL;
}

```

## API Endpoints

All URIs are relative to *https://api.webscraping.ai*

Class | Method | HTTP request | Description
------------ | ------------- | ------------- | -------------
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

- API version: `3.1.3`
    - Package version: `3.1.3`
- Build package: `org.openapitools.codegen.languages.PhpClientCodegen`
