<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidatedReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/Consolidated', [
            'message' => 'Group-consolidated reporting across all properties · coming soon.',
        ]);
    }
}
