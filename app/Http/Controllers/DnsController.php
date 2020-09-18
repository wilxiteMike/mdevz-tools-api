<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DnsController extends Controller
{
    private $site       = null;
    private $headers    = null;
    private $dns        = null;

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
                $hostname = "";
                if(isset($this->headers["url_parsed"]["host"])) 
                    $hostname = $this->headers["url_parsed"]["host"];
                elseif(isset($this->headers["url_parsed"]["path"])) 
                    $hostname = $this->headers["url_parsed"]["path"];
                else
                    $hostname = $this->site;

                $this->dns["hostname"] = $hostname;
                
                $this->dns["records"]["a"]     = dns_get_record($hostname, DNS_A);
                $this->dns["records"]["cname"] = dns_get_record($hostname, DNS_CNAME);
                $this->dns["records"]["ns"]    = dns_get_record($hostname, DNS_NS);
                $this->dns["records"]["mx"]    = dns_get_record($hostname, DNS_MX);
                $this->dns["records"]["soa"]   = dns_get_record($hostname, DNS_SOA);
                $this->dns["records"]["txt"]   = dns_get_record($hostname, DNS_TXT);
                $this->dns["records"]["aaaa"]  = dns_get_record($hostname, DNS_AAAA);
                $this->dns["records"]["hinfo"] = dns_get_record($hostname, DNS_HINFO);
                $this->dns["records"]["ptr"]   = dns_get_record($hostname, DNS_PTR);
                $this->dns["records"]["srv"]   = dns_get_record($hostname, DNS_SRV);
                $this->dns["records"]["naptr"] = dns_get_record($hostname, DNS_NAPTR);
                $this->dns["records"]["a6"]    = dns_get_record($hostname, DNS_A6);

                foreach($this->dns["records"] as $key => $value)
                    if(!$value) unset($this->dns["records"][$key]);


                return response()->json(array("dns" => $this->dns));
            }
            else 
            {
                return response()->json(array("error" => "Invalid Site Provided"), 400);
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
}
