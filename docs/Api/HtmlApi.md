# OpenAPI\Client\HTMLApi

All URIs are relative to *https://api.webscraping.ai*

Method | HTTP request | Description
------------- | ------------- | -------------
[**getHTML**](HTMLApi.md#getHTML) | **GET** /html | Page HTML by URL
[**postHTML**](HTMLApi.md#postHTML) | **POST** /html | Page HTML by URL with POST request to the target page



## getHTML

> string getHTML($url, $headers, $timeout, $js, $proxy)

Page HTML by URL

Returns just HTML on success, JSON on error

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\HTMLApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the target page
$headers = {"Cookie":"session=some_id"}; // map[string,string] | HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&headers[One]=value1&headers=[Another]=value2) or as a JSON encoded object (...&headers={\"One\": \"value1\", \"Another\": \"value2\"})
$timeout = 5000; // int | Maximum processing time in ms. Increase it in case of timeout errors (5000 by default, maximum is 30000)
$js = true; // bool | Execute on-page JavaScript using a headless browser (true by default), costs 2 requests
$proxy = datacenter; // string | Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default)

try {
    $result = $apiInstance->getHTML($url, $headers, $timeout, $js, $proxy);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling HTMLApi->getHTML: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters


Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **url** | **string**| URL of the target page |
 **headers** | [**map[string,string]**](../Model/string.md)| HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&amp;headers[One]&#x3D;value1&amp;headers&#x3D;[Another]&#x3D;value2) or as a JSON encoded object (...&amp;headers&#x3D;{\&quot;One\&quot;: \&quot;value1\&quot;, \&quot;Another\&quot;: \&quot;value2\&quot;}) | [optional]
 **timeout** | **int**| Maximum processing time in ms. Increase it in case of timeout errors (5000 by default, maximum is 30000) | [optional] [default to 5000]
 **js** | **bool**| Execute on-page JavaScript using a headless browser (true by default), costs 2 requests | [optional] [default to true]
 **proxy** | **string**| Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default) | [optional] [default to &#39;datacenter&#39;]

### Return type

**string**

### Authorization

[api_key](../../README.md#api_key)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: application/json, text/html

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints)
[[Back to Model list]](../../README.md#documentation-for-models)
[[Back to README]](../../README.md)


## postHTML

> string postHTML($url, $headers, $timeout, $js, $proxy, $request_body)

Page HTML by URL with POST request to the target page

Returns just HTML on success, JSON on error. Request body will be passed to the target page.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\HTMLApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://httpbin.org/post; // string | URL of the target page
$headers = {"Cookie":"session=some_id"}; // map[string,string] | HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&headers[One]=value1&headers=[Another]=value2) or as a JSON encoded object (...&headers={\"One\": \"value1\", \"Another\": \"value2\"})
$timeout = 5000; // int | Maximum processing time in ms. Increase it in case of timeout errors (5000 by default, maximum is 30000)
$js = true; // bool | Execute on-page JavaScript using a headless browser (true by default), costs 2 requests
$proxy = datacenter; // string | Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default)
$request_body = array('key' => new \stdClass); // map[string,object] | Request body to pass to the target page

try {
    $result = $apiInstance->postHTML($url, $headers, $timeout, $js, $proxy, $request_body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling HTMLApi->postHTML: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters


Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **url** | **string**| URL of the target page |
 **headers** | [**map[string,string]**](../Model/string.md)| HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&amp;headers[One]&#x3D;value1&amp;headers&#x3D;[Another]&#x3D;value2) or as a JSON encoded object (...&amp;headers&#x3D;{\&quot;One\&quot;: \&quot;value1\&quot;, \&quot;Another\&quot;: \&quot;value2\&quot;}) | [optional]
 **timeout** | **int**| Maximum processing time in ms. Increase it in case of timeout errors (5000 by default, maximum is 30000) | [optional] [default to 5000]
 **js** | **bool**| Execute on-page JavaScript using a headless browser (true by default), costs 2 requests | [optional] [default to true]
 **proxy** | **string**| Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default) | [optional] [default to &#39;datacenter&#39;]
 **request_body** | [**map[string,object]**](../Model/object.md)| Request body to pass to the target page | [optional]

### Return type

**string**

### Authorization

[api_key](../../README.md#api_key)

### HTTP request headers

- **Content-Type**: application/json, application/x-www-form-urlencoded, application/xml, text/plain
- **Accept**: application/json, text/html

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints)
[[Back to Model list]](../../README.md#documentation-for-models)
[[Back to README]](../../README.md)

