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
            font-size: 11pt;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }

        .body-content td,
        .body-content th {
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }

        /* Letter body HTML content styling */
        .letter-body p {
            margin-bottom: 0.75em;
        }

        .letter-body p:last-child {
            margin-bottom: 0;
        }

        .letter-body ul,
        .letter-body ol {
            padding-left: 24px;
            margin-bottom: 0.75em;
        }

        .letter-body li {
            margin-bottom: 0.25em;
        }

        .letter-body a {
            color: #2563eb;
            text-decoration: underline;
        }

        .letter-body h2 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 0.5em;
        }

        .letter-body h3 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 0.5em;
        }

        .letter-body strong,
        .letter-body b {
            font-weight: bold;
        }

        .letter-body em,
        .letter-body i {
            font-style: italic;
        }

        .letter-body u {
            text-decoration: underline;
        }

        /* Table styling for PDF - borderless formal layout */
        .letter-body table {
            border-collapse: collapse;
            width: 100%;
            border: none;
            margin: 0.5em 0;
        }

        .letter-body th,
        .letter-body td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }

        .letter-body table.formal-letter-table {
            table-layout: fixed;
            width: 100%;
        }

        .letter-body .formal-letter-table td:first-child {
            width: 140px;
        }

        .letter-body .formal-letter-table td:nth-child(2) {
            width: 16px;
        }

        .letter-body .formal-letter-table td:nth-child(3) {
            width: auto;
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
            @if($letter->to_external_address)
                <br>{!! nl2br(e($letter->to_external_address)) !!}
            @endif
        @endif
        <br>di Tempat
    </div>

    <!-- Body -->
    <div class="body-content letter-body">{!! $bodyHtml !!}</div>

    <!-- Signature Block -->
    <div class="signature-section">
        @if($letter->signer_type_secondary)
            <!-- Dual signature layout -->
            <table style="width: 100%;">
                <tr>
                    <!-- Primary Signer (Ketua/Sekretaris) -->
                    <td style="width: 50%; text-align: center; vertical-align: top;">
                        <div class="signature-date">{{ $unitCity }}, {{ $letterDate }}</div>
                        <div class="signer-title">{{ $letter->signer_type === 'ketua' ? 'Ketua' : 'Sekretaris' }}</div>
                        @if($qrBase64)
                            <img src="data:{{ $qrMime ?? 'image/png' }};base64,{{ $qrBase64 }}" class="qr-code" alt="QR"
                                style="margin: 0 auto;">
                        @else
                            <div style="height: 70px;"></div>
                        @endif
                        <div class="signer-name">{{ $letter->approvedBy?->name ?? '(Menunggu Persetujuan)' }}</div>
                    </td>
                    <!-- Secondary Signer (Bendahara) -->
                    <td style="width: 50%; text-align: center; vertical-align: top;">
                        <div class="signature-date">{{ $unitCity }}, {{ $letterDate }}</div>
                        <div class="signer-title">
                            {{ $letter->signer_type_secondary === 'bendahara' ? 'Bendahara' : 'Penandatangan 2' }}
                        </div>
                        <div style="height: 70px;"></div>
                        <div class="signer-name">{{ $letter->approvedSecondaryBy?->name ?? '(Menunggu Persetujuan)' }}</div>
                    </td>
                </tr>
            </table>
            @if($qrBase64)
                <div style="text-align: center; margin-top: 4px;">
                    <div class="qr-label">Scan untuk verifikasi</div>
                </div>
            @endif
        @else
            <!-- Single signature layout -->
            <div class="signature-box">
                <div class="signature-date">{{ $unitCity }}, {{ $letterDate }}</div>
                <div class="signer-title">{{ $letter->signer_type === 'ketua' ? 'Ketua' : 'Sekretaris' }}</div>
                @if($qrBase64)
                    <img src="data:{{ $qrMime ?? 'image/png' }};base64,{{ $qrBase64 }}" class="qr-code" alt="QR">
                @else
                    <div style="font-size:8pt;color:#888;word-break:break-all;margin:10px 0;">{{ $verifyUrl }}</div>
                @endif
                <div class="qr-label">Scan untuk verifikasi</div>
                <div class="signer-name">{{ $letter->approvedBy?->name ?? '(nama)' }}</div>
            </div>
        @endif
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