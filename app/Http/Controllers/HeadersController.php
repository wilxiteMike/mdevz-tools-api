<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HeadersController extends Controller
{
    private $site = "";

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
        $this->site = $request->input('site');

        if($this->site):
            return response()->json(get_headers ($this->site, false));
        else:
            return response()->json(array("error" => "No Site Given"), 400);
        endif;
        
        return $this->site;
    }

    //
}
