<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Dompdf\Dompdf;

class CardPdfController extends Controller
{
    public function download()
    {
        $member = Auth::user()?->member; // assume relation member on user
        if (!$member) abort(404);
        $html = View::make('pdf.card', ['member' => $member])->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A6', 'portrait');
        $dompdf->render();
        ActivityLog::create([
            'actor_id' => Auth::id(),
            'action' => 'card_pdf_download',
            'subject_type' => Member::class,
            'subject_id' => $member->id,
            'payload' => ['format' => 'pdf'],
        ]);
        return response($dompdf->output(), 200)->header('Content-Type', 'application/pdf');
    }
}
