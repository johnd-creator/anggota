<?php

use App\Services\ReportExporter;

test('ReportExporter streams csv', function(){
    $resp = ReportExporter::streamCsv('t.csv', ['A','B'], function($out){
        fputcsv($out, [1,2]);
        fputcsv($out, [3,4]);
    });
    expect($resp->headers->get('content-type'))->toStartWith('text/csv');
});
