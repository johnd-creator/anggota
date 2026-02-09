<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        @page {
            margin: 0;
            size: A6;
        }
        body { 
            font-family: DejaVu Sans, sans-serif; 
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .card-container {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .header {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            height: 60px;
            padding: 15px 20px;
            position: relative;
        }
        .header-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.2), transparent);
        }
        .header-content {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 4px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .header-text h1 {
            color: white;
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            letter-spacing: 1px;
        }
        .header-text p {
            color: #fecaca;
            font-size: 9px;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        .content {
            padding: 20px 25px;
            text-align: center;
        }
        .photo-section {
            margin-top: -30px;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        .photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #fef2f2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            background: #f3f4f6;
        }
        .photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .status-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .status-aktif { background: #22c55e; }
        .status-cuti { background: #eab308; }
        .status-suspended { background: #ef4444; }
        .status-resign, .status-pensiun { background: #9ca3af; }
        
        .member-name {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin: 8px 0 4px 0;
            line-height: 1.2;
        }
        .unit-name {
            font-size: 13px;
            font-weight: 500;
            color: #dc2626;
            margin: 0 0 20px 0;
        }
        .details {
            max-width: 280px;
            margin: 0 auto;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .detail-label {
            font-size: 9px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .detail-value {
            font-size: 13px;
            color: #374151;
            font-weight: 500;
        }
        .detail-value.mono {
            font-family: DejaVu Sans Mono, monospace;
            font-weight: 700;
            font-size: 12px;
        }
        .barcode-section {
            margin-top: 20px;
            padding: 0 25px;
        }
        .barcode-container {
            background: #f3f4f6;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .barcode-lines {
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(90deg,
                #000 0, #000 1.5px,
                transparent 1.5px, transparent 3px,
                #000 3px, #000 4px,
                transparent 4px, transparent 6px,
                #000 6px, #000 7.5px
            );
            opacity: 0.8;
        }
        .barcode-text {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.9);
            padding: 3px 8px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #111827;
            border-radius: 3px;
        }
        .footer {
            margin-top: 15px;
            padding: 0 25px 20px 25px;
            text-align: center;
        }
        .footer-text {
            font-size: 8px;
            color: #9ca3af;
            line-height: 1.4;
        }
        .bottom-accent {
            height: 8px;
            background: linear-gradient(90deg, #b91c1c 0%, #dc2626 50%, #b91c1c 100%);
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="card-container">
        <div class="header">
            <div class="header-overlay"></div>
            <div class="header-content">
                <div class="logo">
                    <img src="{{ asset('img/logo.png') }}" alt="SP-PIPS Logo" />
                </div>
                <div class="header-text">
                    <h1>SP-PIPS</h1>
                    <p>Kartu Tanda Anggota</p>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="photo-section">
                <div class="photo">
                    @if($member->photo_path)
                        <img src="{{ asset($member->photo_path) }}" alt="{{ $member->full_name }}" />
                    @else
                        <div style="width:100%;height:100%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:24px;">?</div>
                    @endif
                </div>
                @if($member->status)
                <div class="status-badge {{ 'status-' . $member->status }}"></div>
                @endif
            </div>
            
            <h2 class="member-name">{{ $member->full_name }}</h2>
            <p class="unit-name">{{ $member->unit->name ?? '-' }}</p>
            
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">KTA</span>
                    <span class="detail-value mono">{{ $member->kta_number ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">NRA</span>
                    <span class="detail-value mono">{{ $member->nra ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">NIP</span>
                    <span class="detail-value mono">{{ $member->nip ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">{{ ucfirst($member->status ?? '-') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bergabung</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($member->join_date)->locale('id')->translatedFormat('d M Y') }}</span>
                </div>
                @if($member->card_valid_until)
                <div class="detail-row">
                    <span class="detail-label">Berlaku Hingga</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($member->card_valid_until)->locale('id')->translatedFormat('d M Y') }}</span>
                </div>
                @endif
            </div>
        </div>
        
        <div class="barcode-section">
            <div class="barcode-container">
                <div class="barcode-lines"></div>
                <div class="barcode-text">{{ $member->kta_number ?? $member->nra ?? '-' }}</div>
            </div>
        </div>
        
        <div class="footer">
            <p class="footer-text">
                Kartu ini adalah bukti keanggotaan resmi SP-PIPS.<br/>
                Jika ditemukan, harap kembalikan ke kantor sekretariat.
            </p>
        </div>
        
        <div class="bottom-accent"></div>
    </div>
</body>
</html>
