<?php

namespace App\Http\Controllers;

use App\Services\ReportExportStatus;
use Illuminate\Http\Request;

class ReportsExportStatusController extends Controller
{
    public function __invoke(Request $request, ReportExportStatus $statusService)
    {
        $status = $statusService->get($request->user());
        return response()->json($status);
    }
}
