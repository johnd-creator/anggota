<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat {{ $letter->letter_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        /* Header / Letterhead - matching Preview.vue */
        .header {
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .logo-cell {
            display: table-cell;
            width: 90px;
            vertical-align: middle;
        }

        .logo {
            width: 80px;
            height: 80px;
        }

        .title-cell {
            display: table-cell;
            text-align: center;
            vertical-align: middle;
        }

        .org-name-main {
            font-size: 16pt;
            font-weight: 600;
            letter-spacing: 1px;
            color: #1a1a1a;
        }

        .org-name-sub {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a1a1a;
        }

        .unit-type-name {
            font-size: 12pt;
            font-weight: 600;
            text-transform: uppercase;
            color: #1a1a1a;
        }

        .contact-info {
            font-size: 10pt;
            color: #444;
            margin-top: 4px;
        }

        .disnaker-info {
            font-size: 10pt;
            color: #444;
        }

        /* Letter Meta - matching Preview.vue layout */
        .letter-meta {
            margin-bottom: 16px;
        }

        .letter-meta table {
            width: 100%;
            font-size: 11pt;
        }

        .letter-meta td {
            vertical-align: top;
        }

        .letter-meta .label {
            font-weight: bold;
        }

        /* Recipient */
        .recipient {
            margin-bottom: 16px;
            font-size: 11pt;
        }

        /* Body */
        .body-content {
            text-align: justify;
            white-space: pre-wrap;
            font-size: 11pt;
            line-height: 1.6;
        }

        /* Signature Block - matching Preview.vue (right aligned) */
        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 220px;
            margin-left: auto;
            text-align: center;
        }

        .signature-date {
            font-size: 11pt;
            margin-bottom: 4px;
        }

        .signer-title {
            font-size: 11pt;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .qr-code {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }

        .qr-label {
            font-size: 10pt;
            color: #666;
            margin-top: 4px;
        }

        .signer-name {
            font-size: 11pt;
            font-weight: 600;
            text-decoration: underline;
            margin-top: 8px;
        }

        /* Tembusan */
        .tembusan-section {
            margin-top: 30px;
            font-size: 11pt;
            page-break-inside: avoid;
        }

        .tembusan-section .label {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .tembusan-section ol {
            margin: 0;
            padding-left: 20px;
        }

        .tembusan-section li {
            margin-bottom: 2px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
    </style>
</head>

<body>
    @php
        $unitName = $letter->fromUnit?->name ?? 'Pusat';
        $unitType = $letter->fromUnit?->organization_type;
        $unitTypeLine = ($unitType === 'DPP') ? 'DEWAN PIMPINAN PUSAT (DPP)' : 'DEWAN PIMPINAN DAERAH (DPD)';

        $unitAddress = $letter->fromUnit?->letterhead_address ?: ($letter->fromUnit?->address ?? null);
        $unitCity = $letter->fromUnit?->letterhead_city ?? 'Jakarta';
        $unitPostal = $letter->fromUnit?->letterhead_postal_code;
        $unitPhone = $letter->fromUnit?->letterhead_phone ?: $letter->fromUnit?->phone;
        $unitEmail = $letter->fromUnit?->letterhead_email ?: $letter->fromUnit?->email;

        $addressParts = array_filter([$unitAddress, $unitCity, $unitPostal]);
        $disnakerInfo = 'No Bukti Pencatatan Disnaker : 951/SP/JS/X/2024, Tanggal 1 Oktober 2024';

        $attachmentCount = $letter->attachments?->count() ?? 0;
        $attachmentLabel = $attachmentCount > 0 ? $attachmentCount . ' berkas' : '-';

        $letterDate = $letter->approved_at?->translatedFormat('d F Y') ?? $letter->created_at?->translatedFormat('d F Y');
    @endphp

    <!-- Header / Letterhead -->
    <div class="header">
        <div class="header-content">
            <div class="logo-cell">
                @if($letter->fromUnit?->letterhead_logo_path && file_exists(public_path('storage/' . $letter->fromUnit->letterhead_logo_path)))
                    <img src="{{ public_path('storage/' . $letter->fromUnit->letterhead_logo_path) }}" class="logo"
                        alt="Logo">
                @elseif(file_exists(public_path('img/logo.png')))
                    <img src="{{ public_path('img/logo.png') }}" class="logo" alt="Logo">
                @else
                    <div class="logo" style="text-align:center;font-size:8pt;line-height:80px;">SP PLN IPS</div>
                @endif
            </div>
            <div class="title-cell">
                <div class="org-name-main">SERIKAT PEKERJA</div>
                <div class="org-name-sub">PT PLN INDONESIA POWER SERVICES (SP PIPS)</div>
                <div class="unit-type-name">{{ $unitTypeLine }} {{ strtoupper($unitName) }}</div>
                @if(count($addressParts) > 0)
                    <div class="contact-info">{{ implode(', ', $addressParts) }}</div>
                @endif
                @if($unitPhone || $unitEmail)
                    <div class="contact-info">
                        @if($unitPhone) No Telepon : {{ $unitPhone }} @endif
                        @if($unitPhone && $unitEmail) | @endif
                        @if($unitEmail) Email : {{ $unitEmail }} @endif
                    </div>
                @endif
                <div class="disnaker-info">{{ $disnakerInfo }}</div>
            </div>
            <div class="logo-cell"></div>
        </div>
    </div>

    <!-- Letter Meta -->
    <div class="letter-meta">
        <table>
            <tr>
                <td style="width: 60%;">
                    <span class="label">Nomor:</span> {{ $letter->letter_number ?? '(Belum digenerate)' }}<br>
                    <span class="label">Lampiran:</span> {{ $attachmentLabel }}<br>
                    <span class="label">Perihal:</span> {{ $letter->subject }}
                </td>
                <td style="text-align: right;">
                    {{ $unitCity }}, {{ $letterDate }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Recipient -->
    <div class="recipient">
        <strong>Kepada Yth,</strong><br>
        @if($letter->to_type === 'unit')
            {{ $letter->toUnit?->name ?? 'Unit' }}
        @elseif($letter->to_type === 'member')
            {{ $letter->toMember?->full_name ?? 'Anggota' }}
        @elseif($letter->to_type === 'admin_pusat')
            Admin Pusat
        @elseif($letter->to_type === 'eksternal')
            {{ $letter->to_external_name ?? 'Pihak Eksternal' }}
            @if($letter->to_external_org)
                <br>{{ $letter->to_external_org }}
            @endif
        @endif
        <br>di Tempat
    </div>

    <!-- Body -->
    <div class="body-content">{{ $letter->body }}</div>

    <!-- Signature Block (right aligned like Preview.vue) -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-date">{{ $unitCity }}, {{ $letterDate }}</div>
            <div class="signer-title">{{ $letter->signer_type === 'ketua' ? 'Ketua' : 'Sekretaris' }}</div>
            @if($qrBase64)
                <img src="data:image/png;base64,{{ $qrBase64 }}" class="qr-code" alt="QR">
            @else
                <div style="font-size:8pt;color:#888;word-break:break-all;margin:10px 0;">{{ $verifyUrl }}</div>
            @endif
            <div class="qr-label">Scan untuk verifikasi</div>
            <div class="signer-name">{{ $letter->approvedBy?->name ?? '(nama)' }}</div>
        </div>
    </div>

    <!-- Tembusan -->
    @php
        $tembusanLines = $letter->cc_text
            ? array_filter(array_map(function ($l) {
                $l = trim($l);
                $l = preg_replace('/^\s*(?:\d+\s*[\)\.\-]|[-•])\s*/u', '', $l);
                return $l;
            }, explode("\n", $letter->cc_text)), fn($l) => strlen($l) > 0)
            : [];
    @endphp
    @if(count($tembusanLines) > 0)
        <div class="tembusan-section">
            <div class="label">Tembusan:</div>
            <ol>
                @foreach($tembusanLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        </div>
    @endif

    <!-- Footer -->
    @if($letter->fromUnit?->letterhead_footer_text)
        <div class="footer">{{ $letter->fromUnit->letterhead_footer_text }}</div>
    @endif
</body>

</html>