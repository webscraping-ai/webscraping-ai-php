# OpenAPI\Client\HtmlApi

All URIs are relative to *https://webscraping.ai/api*

Method | HTTP request | Description
------------- | ------------- | -------------
[**getPage**](HtmlApi.md#getPage) | **GET** / | Get page HTML by URL (renders JS in Chrome and uses rotating proxies)



## getPage

> \OpenAPI\Client\Model\ScrappedPage getPage($url, $selector, $outer_html, $proxy, $disable_js, $inline_css)

Get page HTML by URL (renders JS in Chrome and uses rotating proxies)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\HtmlApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the page to get
$selector = html; // string | CSS selector to get a part of the page (null by default, returns whole page HTML)
$outer_html = false; // bool | Return outer HTML of the selected element (false by default, returns inner HTML)
$proxy = US; // string | Proxy country code, for geotargeting (US by default)
$disable_js = false; // bool | Disable JS execution (false by default)
$inline_css = false; // bool | Inline included CSS files to make page viewable on other domains (false by default)

try {
    $result = $apiInstance->getPage($url, $selector, $outer_html, $proxy, $disable_js, $inline_css);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling HtmlApi->getPage: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters


Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **url** | **string**| URL of the page to get |
 **selector** | **string**| CSS selector to get a part of the page (null by default, returns whole page HTML) | [optional]
 **outer_html** | **bool**| Return outer HTML of the selected element (false by default, returns inner HTML) | [optional]
 **proxy** | **string**| Proxy country code, for geotargeting (US by default) | [optional]
 **disable_js** | **bool**| Disable JS execution (false by default) | [optional]
 **inline_css** | **bool**| Inline included CSS files to make page viewable on other domains (false by default) | [optional]

### Return type

[**\OpenAPI\Client\Model\ScrappedPage**](../Model/ScrappedPage.md)

### Authorization

[api_key](../../README.md#api_key)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints)
[[Back to Model list]](../../README.md#documentation-for-models)
[[Back to README]](../../README.md)

