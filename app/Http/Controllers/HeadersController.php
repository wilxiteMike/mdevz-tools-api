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
                $this->headers = get_headers ($this->site, false);

                return response()->json($this->headers);
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

    private function url_parser($url) {
        // multiple /// messes up parse_url, replace 2+ with 2
        $url = preg_replace('/(\/{2,})/','//',$url);
        
        $parse_url = parse_url($url);
        
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
        if(!in_array($parse_url["scheme"], array("http", "https", "gopher"))) {
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

    //
}
