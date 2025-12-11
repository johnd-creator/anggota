<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class MembersTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    public function headings(): array
    {
        return [
            'personal_full_name',
            'personal_nip',
            'personal_birth_place',
            'personal_birth_date',
            'personal_gender',
            'personal_phone',
            'personal_email',
            'company_email',
            'address',
            'union_position_code',
            'employment_type',
            'join_date',
            'company_join_date',
            'status',
            'job_title',
            'notes',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Agus Santoso',
                '89123456',
                'Jakarta',
                '1990-05-15',
                'L',
                '+628123456789',
                '',
                'agus@plnipservices.co.id',
                'Jl. Merdeka No 1',
                'ANGGOTA',
                'organik',
                '2020-01-01',
                '2018-05-01',
                'aktif',
                'Staff Senior',
                'Catatan opsional',
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_TEXT, // personal_phone column
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('F')->getNumberFormat()->setFormatCode('@');
                $sheet->setCellValueExplicit('F2', '+628123456789', DataType::TYPE_STRING);
            },
        ];
    }
}
