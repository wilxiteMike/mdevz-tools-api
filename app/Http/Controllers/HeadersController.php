<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HeadersController extends Controller
{
    private $site = null;
    private $headers = null;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function check(Request $request) 
    {
        $this->site = $this->url_parser($request->input('site'));

        if($this->site)
        {
            // validate URL 
            if (filter_var($this->site, FILTER_VALIDATE_URL)) {
                try{
                    $this->headers = array_merge($this->headers, $this->header_parser(get_headers ($this->site, true)));
                    $this->headers["https_redirect"] = $this->check_https($this->site);
                    if(isset($this->headers["url_parsed"]["host"])) 
                        $this->headers["ip"] = gethostbyname($this->headers["url_parsed"]["host"]);
                    elseif(isset($this->headers["url_parsed"]["path"])) 
                        $this->headers["ip"] = gethostbyname($this->headers["url_parsed"]["path"]);
                    else 
                        $this->headers["ip"] = "unavailable";
                }
                catch (\Exception $e) 
                {
                    return response()->json(array("error" => "Error getting site headers, site might not exist", "real_error" => $e->getMessage(), "error_headers" => $this->headers), 400);
                } 
                catch (\Error $e) 
                {
                    return response()->json(array("error" => "Unexpected error", "real_error" => $e->getMessage(), "error_headers" => $this->headers), 400);
                }

                return response()->json(array("headers" => $this->headers));
            }
            else 
            {
                return response()->json(array("error" => "Invalid URL Provided"), 400);
            }
        }
        else 
        {
            return response()->json(array("error" => "No Site Given"), 400);
        }
        
        return response()->json(array("error" => "Unexpected error"), 400);
    }

    private function url_parser(String $url) {
        // multiple /// messes up parse_url, replace 2+ with 2
        $url = preg_replace('/(\/{2,})/','//',$url);
        
        $parse_url = parse_url($url);
        $this->headers["url_parsed"] = $parse_url;
        
        if(empty($parse_url["scheme"])) {
            $parse_url["scheme"] = "http";
        }
        if(empty($parse_url["host"]) && !empty($parse_url["path"])) {
            // Strip slash from the beginning of path
            $parse_url["host"] = ltrim($parse_url["path"], '\/');
            $parse_url["path"] = "";
        }   
        
        $return_url = "";
        
        // Check if scheme is correct
        if(!in_array($parse_url["scheme"], array("http", "https"))) {
            $return_url .= 'http'.'://';
        } else {
            $return_url .= $parse_url["scheme"].'://';
        }
        
        // Check if the right amount of "www" is set.
        $explode_host = explode(".", $parse_url["host"]);
        
        // Remove empty entries
        $explode_host = array_filter($explode_host);
        // And reassign indexes
        $explode_host = array_values($explode_host);
        
        // Contains subdomain
        if(count($explode_host) > 2) {
            // Check if subdomain only contains the letter w(then not any other subdomain).
            if(substr_count($explode_host[0], 'w') == strlen($explode_host[0])) {
                // Replace with "www" to avoid "ww" or "wwww", etc.
                $explode_host[0] = "www";
            }
        }
        $return_url .= implode(".",$explode_host);
        
        if(!empty($parse_url["port"])) {
            $return_url .= ":".$parse_url["port"];
        }
        if(!empty($parse_url["path"])) {
            $return_url .= $parse_url["path"];
        }
        if(!empty($parse_url["query"])) {
            $return_url .= '?'.$parse_url["query"];
        }
        if(!empty($parse_url["fragment"])) {
            $return_url .= '#'.$parse_url["fragment"];
        }
        
        return $return_url;
    }

    private function header_parser(Array $headers) {
        $parse_headers = array();
        $original_headers = $headers;

        foreach($original_headers as $key => $value)
        {
            $parse_headers["raw_headers"][] = array("key" => $key, "value" => $value); 
        }

        $response_code = substr($parse_headers["raw_headers"][0]["value"], 9, 3);
        if($response_code == 300 || $response_code == 301 || $response_code == 302 || $response_code == 303) 
        {
            $final_redirect = max(array_keys($original_headers));
            $parse_headers["redirected"] = true;
            $parse_headers["site_headers"][] = array("key" => "HTTP", "value" => $original_headers[$final_redirect]);
            foreach($parse_headers["raw_headers"] as $key => $value)
            {
                if(!is_numeric($value["key"]) && !is_array($value["value"]))
                {
                    $parse_headers["site_headers"][] = array("key" => $value["key"], "value" => $value["value"]);
                }
                else if(is_array($value["value"]) && $value["value"][$final_redirect] && $value["key"] != "Set-Cookie")
                {
                    $parse_headers["site_headers"][] = array("key" => $value["key"], "value" => $value["value"][1]);
                }
                else if(is_array($value["value"]) && $value["key"] == "Set-Cookie")
                {
                    foreach($value["value"] as $key2 => $value2)
                    {
                        $parse_headers["site_headers"][] = array("key" => $value["key"], "value" => $value2);
                    }
                }
            }
        }
        else
        {
            $parse_headers["redirected"] = false;
            $parse_headers["site_headers"] = $parse_headers["raw_headers"];
        }

        $parse_headers["response_code"] = $response_code;

        return $parse_headers;
    }

    private function check_https(String $url) 
    {
        // Check if https url is specified
        if(strpos($url, 'https') !== false) $insecure_url = str_replace("https", "http", $url);
        else $insecure_url = $url;

        $headers = get_headers ($insecure_url, true);

        if(strpos($headers["Location"] , 'https') !== false) return true;
        else return false;

    }
    //
}
