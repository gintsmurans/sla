<?php

namespace Defaults\Controllers;

use \Core\Controllers\Controller;
use \Core\Models\Timers;

use \Defaults\Models\Auth;

/**
 * Welcome page controller.
 */

class Welcome extends Controller
{
    public static function construct($class = null, $method = null)
    {
        // Check if user is authenticated
        Auth::checkAuth(true);
    }

    public static function index($param1 = null, $param2 = null)
    {
        // Do something heavy and add timer mark
        Timers::markTime('Before views');

        // Load view
        // Pass [key => value] as second parameter, to get variables available in your view
        self::render('index.html');

        // Or call Load::view('Defaults/Views/index.html');
    }

    public static function example()
    {
        $view_data = [
            'included_files' => []
        ];

        foreach (get_included_files() as $file) {
            $view_data['included_files'][] = $file;
        }

        Load::view('Defaults/Views/example.html', $view_data);
    }
}
