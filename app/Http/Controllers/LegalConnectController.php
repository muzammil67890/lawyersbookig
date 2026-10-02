<?php
namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalConnectController extends Controller
{
    public function index(): View
    {
        return view('pages.legal-connect');
    }
}
