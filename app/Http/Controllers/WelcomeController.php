<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'app' => [
                'name' => 'Portal Financiero CREN',
            ],
            'access' => [
                'domain' => 'crenfcp.edu.mx',
                'registration_enabled' => false,
            ],
        ]);
    }
}
