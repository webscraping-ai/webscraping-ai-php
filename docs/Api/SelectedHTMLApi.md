# OpenAPI\Client\SelectedHTMLApi

All URIs are relative to https://api.webscraping.ai, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getSelected()**](SelectedHTMLApi.md#getSelected) | **GET** /selected | HTML of a selected page area by URL and CSS selector |
| [**getSelectedMultiple()**](SelectedHTMLApi.md#getSelectedMultiple) | **GET** /selected-multiple | HTML of multiple page areas by URL and CSS selectors |


## `getSelected()`

```php
getSelected($url, $selector, $headers, $timeout, $js, $js_timeout, $proxy, $country, $device, $error_on_404, $error_on_redirect, $js_script): string
```

HTML of a selected page area by URL and CSS selector

Returns HTML of a selected page area by URL and CSS selector. Useful if you don't want to do the HTML parsing on your side.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\SelectedHTMLApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the target page.
$selector = h1; // string | CSS selector (null by default, returns whole page HTML)
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
    $result = $apiInstance->getSelected($url, $selector, $headers, $timeout, $js, $js_timeout, $proxy, $country, $device, $error_on_404, $error_on_redirect, $js_script);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SelectedHTMLApi->getSelected: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **url** | **string**| URL of the target page. | |
| **selector** | **string**| CSS selector (null by default, returns whole page HTML) | [optional] |
| **headers** | [**array<string,string>**](../Model/string.md)| HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&amp;headers[One]&#x3D;value1&amp;headers&#x3D;[Another]&#x3D;value2) or as a JSON encoded object (...&amp;headers&#x3D;{\&quot;One\&quot;: \&quot;value1\&quot;, \&quot;Another\&quot;: \&quot;value2\&quot;}). | [optional] |
| **timeout** | **int**| Maximum web page retrieval time in ms. Increase it in case of timeout errors (10000 by default, maximum is 30000). | [optional] [default to 10000] |
| **js** | **bool**| Execute on-page JavaScript using a headless browser (true by default). | [optional] [default to true] |
| **js_timeout** | **int**| Maximum JavaScript rendering time in ms. Increase it in case if you see a loading indicator instead of data on the target page. | [optional] [default to 2000] |
| **proxy** | **string**| Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default). Note that residential proxy requests are more expensive than datacenter, see the pricing page for details. | [optional] [default to &#39;datacenter&#39;] |
| **country** | **string**| Country of the proxy to use (US by default). Only available on Startup and Custom plans. | [optional] [default to &#39;us&#39;] |
| **device** | **string**| Type of device emulation. | [optional] [default to &#39;desktop&#39;] |
| **error_on_404** | **bool**| Return error on 404 HTTP status on the target page (false by default). | [optional] [default to false] |
| **error_on_redirect** | **bool**| Return error on redirect on the target page (false by default). | [optional] [default to false] |
| **js_script** | **string**| Custom JavaScript code to execute on the target page. | [optional] |

### Return type

**string**

### Authorization

[api_key](../../README.md#api_key)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`, `text/html`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getSelectedMultiple()`

```php
getSelectedMultiple($url, $selectors, $headers, $timeout, $js, $js_timeout, $proxy, $country, $device, $error_on_404, $error_on_redirect, $js_script): string[]
```

HTML of multiple page areas by URL and CSS selectors

Returns HTML of multiple page areas by URL and CSS selectors. Useful if you don't want to do the HTML parsing on your side.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\SelectedHTMLApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the target page.
$selectors = ["h1"]; // string[] | Multiple CSS selectors (null by default, returns whole page HTML)
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
    $result = $apiInstance->getSelectedMultiple($url, $selectors, $headers, $timeout, $js, $js_timeout, $proxy, $country, $device, $error_on_404, $error_on_redirect, $js_script);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SelectedHTMLApi->getSelectedMultiple: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **url** | **string**| URL of the target page. | |
| **selectors** | [**string[]**](../Model/string.md)| Multiple CSS selectors (null by default, returns whole page HTML) | [optional] |
| **headers** | [**array<string,string>**](../Model/string.md)| HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&amp;headers[One]&#x3D;value1&amp;headers&#x3D;[Another]&#x3D;value2) or as a JSON encoded object (...&amp;headers&#x3D;{\&quot;One\&quot;: \&quot;value1\&quot;, \&quot;Another\&quot;: \&quot;value2\&quot;}). | [optional] |
| **timeout** | **int**| Maximum web page retrieval time in ms. Increase it in case of timeout errors (10000 by default, maximum is 30000). | [optional] [default to 10000] |
| **js** | **bool**| Execute on-page JavaScript using a headless browser (true by default). | [optional] [default to true] |
| **js_timeout** | **int**| Maximum JavaScript rendering time in ms. Increase it in case if you see a loading indicator instead of data on the target page. | [optional] [default to 2000] |
| **proxy** | **string**| Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default). Note that residential proxy requests are more expensive than datacenter, see the pricing page for details. | [optional] [default to &#39;datacenter&#39;] |
| **country** | **string**| Country of the proxy to use (US by default). Only available on Startup and Custom plans. | [optional] [default to &#39;us&#39;] |
| **device** | **string**| Type of device emulation. | [optional] [default to &#39;desktop&#39;] |
| **error_on_404** | **bool**| Return error on 404 HTTP status on the target page (false by default). | [optional] [default to false] |
| **error_on_redirect** | **bool**| Return error on redirect on the target page (false by default). | [optional] [default to false] |
| **js_script** | **string**| Custom JavaScript code to execute on the target page. | [optional] |

### Return type

**string[]**

### Authorization

[api_key](../../README.md#api_key)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
