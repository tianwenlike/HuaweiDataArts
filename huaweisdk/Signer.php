<?php
define("BasicDateFormat", "Ymd\THis\Z");
define("Algorithm", "SDK-HMAC-SHA256");
define("HeaderXDate", "X-Sdk-Date");
define("HeaderHost", "host");
define("HeaderAuthorization", "Authorization");
define("HeaderContentSha256", "X-Sdk-Content-Sha256");


class Request
{
    public $method = '';
    public $scheme = '';
    public $host = '';
    public $uri = '';
    public $query = array();
    public $headers = array();
    public $body = '';

    function __construct()
    {
        $args = func_get_args();
        $i = count($args);
        if ($i == 0) {
            $this->construct(NULL, NULL, NULL, NULL);
        } elseif ($i == 1) {
            $this->construct($args[0], NULL, NULL, NULL);
        } elseif ($i == 2) {
            $this->construct($args[0], $args[1], NULL, NULL);
        } elseif ($i == 3) {
            $this->construct($args[0], $args[1], $args[2], NULL);
        } else {
            $this->construct($args[0], $args[1], $args[2], $args[3]);
        }
    }

    function construct($method, $url, $headers, $body)
    {
        if ($method != NULL) {
            $this->method = $method;
        }
        if ($url != NULL) {
            $spl = explode("://", $url, 2);
            $scheme = 'http';
            if (count($spl) > 1) {
                $scheme = $spl[0];
                $url = $spl[1];
            }
            $spl = explode("?", $url, 2);
            $url = $spl[0];
            $query = array();
            if (count($spl) > 1) {
                foreach (explode("&", $spl[1]) as $kv) {
                    $spl = explode("=", $kv, 2);
                    $key = $spl[0];
                    if (count($spl) == 1) {
                        $value = "";
                    } else {
                        $value = $spl[1];
                    }
                    if ($key != "") {
                        $key = urldecode($key);
                        $value = urldecode($value);
                        if (array_key_exists($key, $query)) {
                            array_push($query[$key], $value);
                        } else {
                            $query[$key] = array($value);
                        }
                    }
                }
            }
            $spl = explode("/", $url, 2);
            $host = $spl[0];
            if (count($spl) == 1) {
                $url = "/";
            } else {
                $url = "/" . $spl[1];
            }
            $this->scheme = $scheme;
            $this->host = $host;
            $this->uri = urldecode($url);
            $this->query = $query;
        }
        if ($headers != NULL) {
            $this->headers = $headers;
        }
        if ($body != NULL) {
            $this->body = $body;
        }
    }
}

class Signer
{
    public $Key = '';
    public $Secret = '';

    function escape($string)
    {
        $entities = array('+', "%7E");
        $replacements = array('%20', "~");
        return str_replace($entities, $replacements, urlencode($string));
    }

    function findHeader($r, $header)
    {
        foreach ($r->headers as $key => $value) {
            if (!strcasecmp($key, $header)) {
                return $value;
            }
        }
        return NULL;
    }

    // Build a CanonicalRequest from a regular request string
    //
    // CanonicalRequest =
    //  HTTPRequestMethod + '\n' +
    //  CanonicalURI + '\n' +
    //  CanonicalQueryString + '\n' +
    //  CanonicalHeaders + '\n' +
    //  SignedHeaders + '\n' +
    //  HexEncode(Hash(RequestPayload))
    function CanonicalRequest($r, $signedHeaders)
    {
        $CanonicalURI = $this->CanonicalURI($r);
        $CanonicalQueryString = $this->CanonicalQueryString($r);
        $canonicalHeaders = $this->CanonicalHeaders($r, $signedHeaders);
        $signedHeadersString = join(";", $signedHeaders);
        $hash = $this->findHeader($r, HeaderContentSha256);
        if (!$hash) {
            $hash = hash("sha256", $r->body);
        }
        return "$r->method\n$CanonicalURI\n$CanonicalQueryString\n$canonicalHeaders\n$signedHeadersString\n$hash";
    }

    // CanonicalURI returns request uri
    function CanonicalURI($r)
    {
        $pattens = explode("/", $r->uri);
        $uri = array();
        foreach ($pattens as $v) {
            array_push($uri, $this->escape($v));
        }
        $urlpath = join("/", $uri);
        if (substr($urlpath, -1) != "/") {
            $urlpath = $urlpath . "/";
        }
        return $urlpath;
    }

    // CanonicalQueryString
    function CanonicalQueryString($r)
    {
        $keys = array();
        foreach ($r->query as $key => $value) {
            array_push($keys, $key);
        }
        sort($keys);
        $a = array();
        foreach ($keys as $key) {
            $k = $this->escape($key);
            $value = $r->query[$key];
            if (is_array($value)) {
                sort($value);
                foreach ($value as $v) {
                    $kv = "$k=" . $this->escape($v);
                    array_push($a, $kv);
                }
            } else {
                $kv = "$k=" . $this->escape($value);
                array_push($a, $kv);
            }
        }
        return join("&", $a);
    }

    // CanonicalHeaders
    function CanonicalHeaders($r, $signedHeaders)
    {
        $headers = array();
        foreach ($r->headers as $key => $value) {
            $headers[strtolower($key)] = trim($value);
        }
        $a = array();
        foreach ($signedHeaders as $key) {
            array_push($a, $key . ':' . $headers[$key]);
        }
        return join("\n", $a) . "\n";
    }

    function curlHeaders($r)
    {
        $header = array();
        foreach ($r->headers as $key => $value) {
            array_push($header, strtolower($key) . ':' . trim($value));
        }
        return $header;
    }

    // SignedHeaders
    function SignedHeaders($r)
    {
        $a = array();
        foreach ($r->headers as $key => $value) {
            array_push($a, strtolower($key));
        }
        sort($a);
        return $a;
    }

    // Create a "String to Sign".
    function StringToSign($canonicalRequest, $t)
    {
        date_default_timezone_set('UTC');
        $date = date(BasicDateFormat, $t);
        $hash = hash("sha256", $canonicalRequest);
        return "SDK-HMAC-SHA256\n$date\n$hash";
    }

    // Create the HWS Signature.
    function SignStringToSign($stringToSign, $signingKey)
    {
        return hash_hmac("sha256", $stringToSign, $signingKey);
    }

    // Get the finalized value for the "Authorization" header. The signature parameter is the output from SignStringToSign
    function AuthHeaderValue($signature, $accessKey, $signedHeaders)
    {
        $signedHeadersString = join(";", $signedHeaders);
        return "SDK-HMAC-SHA256 Access=$accessKey, SignedHeaders=$signedHeadersString, Signature=$signature";
    }

    public function Sign($r)
    {
        date_default_timezone_set('UTC');
        $date = $this->findHeader($r, HeaderXDate);
        if ($date) {
            $t = date_timestamp_get(date_create_from_format(BasicDateFormat, $date));
        }
        if (!@$t) {
            $t = time();
            $r->headers[HeaderXDate] = date(BasicDateFormat, $t);
        }
        $queryString = $this->CanonicalQueryString($r);
        if ($queryString != "") {
            $queryString = "?" . $queryString;
        }
        $signedHeaders = $this->SignedHeaders($r);
        $canonicalRequest = $this->CanonicalRequest($r, $signedHeaders);
        $stringToSign = $this->StringToSign($canonicalRequest, $t);
        $signature = $this->SignStringToSign($stringToSign, $this->Secret);
        $authValue = $this->AuthHeaderValue($signature, $this->Key, $signedHeaders);
        $r->headers[HeaderAuthorization] = $authValue;

        $curl = curl_init();
        $uri = str_replace(array("%2F"), array("/"), rawurlencode($r->uri));
        $url = $r->scheme . '://' . $r->host . $uri . $queryString;
        $headers = $this->curlHeaders($r);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $r->method);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $r->body);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_NOBODY, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);//不验证https证书
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);//不验证https证书
        return $curl;
    }
}
