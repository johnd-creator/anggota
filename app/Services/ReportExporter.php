<?php

namespace App\Services;

class ReportExporter
{
    public static function streamCsv(string $filename, array $headers, \Closure $writeRows)
    {
        return response()->streamDownload(function() use ($headers, $writeRows){
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            $writeRows($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

