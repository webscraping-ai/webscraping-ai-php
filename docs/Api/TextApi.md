# OpenAPI\Client\TextApi

All URIs are relative to https://api.webscraping.ai, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getText()**](TextApi.md#getText) | **GET** /text | Page text by URL |


## `getText()`

```php
getText($url, $text_format, $return_links, $headers, $timeout, $js, $js_timeout, $wait_for, $proxy, $country, $custom_proxy, $device, $error_on_404, $error_on_redirect, $js_script): string
```

Page text by URL

Returns the visible text content of a webpage specified by the URL. Can be used to feed data to LLM models. The response can be in plain text, JSON, or XML format based on the text_format parameter. Proxies and Chromium JavaScript rendering are used for page retrieval and processing. Returns JSON on error.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: api_key
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKey('api_key', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api_key', 'Bearer');


$apiInstance = new OpenAPI\Client\Api\TextApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$url = https://example.com; // string | URL of the target page.
$text_format = plain; // string | Format of the text response (plain by default). \"plain\" will return only the page body text. \"json\" and \"xml\" will return a json/xml with \"title\", \"description\" and \"content\" keys.
$return_links = false; // bool | [Works only with text_format=json] Return links from the page body text (false by default). Useful for building web crawlers.
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
    $result = $apiInstance->getText($url, $text_format, $return_links, $headers, $timeout, $js, $js_timeout, $wait_for, $proxy, $country, $custom_proxy, $device, $error_on_404, $error_on_redirect, $js_script);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TextApi->getText: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **url** | **string**| URL of the target page. | |
| **text_format** | **string**| Format of the text response (plain by default). \&quot;plain\&quot; will return only the page body text. \&quot;json\&quot; and \&quot;xml\&quot; will return a json/xml with \&quot;title\&quot;, \&quot;description\&quot; and \&quot;content\&quot; keys. | [optional] [default to &#39;plain&#39;] |
| **return_links** | **bool**| [Works only with text_format&#x3D;json] Return links from the page body text (false by default). Useful for building web crawlers. | [optional] [default to false] |
| **headers** | [**array<string,string>**](../Model/string.md)| HTTP headers to pass to the target page. Can be specified either via a nested query parameter (...&amp;headers[One]&#x3D;value1&amp;headers&#x3D;[Another]&#x3D;value2) or as a JSON encoded object (...&amp;headers&#x3D;{\&quot;One\&quot;: \&quot;value1\&quot;, \&quot;Another\&quot;: \&quot;value2\&quot;}). | [optional] |
| **timeout** | **int**| Maximum web page retrieval time in ms. Increase it in case of timeout errors (10000 by default, maximum is 30000). | [optional] [default to 10000] |
| **js** | **bool**| Execute on-page JavaScript using a headless browser (true by default). | [optional] [default to true] |
| **js_timeout** | **int**| Maximum JavaScript rendering time in ms. Increase it in case if you see a loading indicator instead of data on the target page. | [optional] [default to 2000] |
| **wait_for** | **string**| CSS selector to wait for before returning the page content. Useful for pages with dynamic content loading. Overrides js_timeout. | [optional] |
| **proxy** | **string**| Type of proxy, use residential proxies if your site restricts traffic from datacenters (datacenter by default). Note that residential proxy requests are more expensive than datacenter, see the pricing page for details. | [optional] [default to &#39;datacenter&#39;] |
| **country** | **string**| Country of the proxy to use (US by default). | [optional] [default to &#39;us&#39;] |
| **custom_proxy** | **string**| Your own proxy URL to use instead of our built-in proxy pool in \&quot;http://user:password@host:port\&quot; format (&lt;a target&#x3D;\&quot;_blank\&quot; href&#x3D;\&quot;https://webscraping.ai/proxies/smartproxy\&quot;&gt;Smartproxy&lt;/a&gt; for example). | [optional] |
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
- **Accept**: `application/json`, `text/html`, `text/xml`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
