<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class CardPdfController extends Controller
{
    public function download()
    {
        $member = Auth::user()?->member;

        if (! $member) {
            abort(404, 'Data member tidak ditemukan. Silakan hubungi admin.');
        }

        try {
            $html = View::make('pdf.card', ['member' => $member])->render();
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A6', 'portrait');
            $dompdf->render();
            $pdfContent = $dompdf->output();

            ActivityLog::create([
                'actor_id' => Auth::id(),
                'action' => 'card_pdf_download',
                'subject_type' => Member::class,
                'subject_id' => $member->id,
                'payload' => ['format' => 'pdf'],
            ]);

            $sanitizedKta = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $member->kta_number ?? 'NRA');
            $sanitizedName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $member->full_name);
            $filename = 'KTA-'.$sanitizedKta.'-'.$sanitizedName.'.pdf';

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
                ->header('Content-Transfer-Encoding', 'binary')
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'member_id' => $member->id,
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Gagal membuat PDF. Silakan coba lagi atau hubungi admin.');
        }
    }
}
