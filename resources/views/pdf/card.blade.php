<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; }
        .row { display: flex; justify-content: space-between; }
        .badge { display:inline-block; padding:2px 6px; border-radius: 10px; background:#eee; }
    </style>
    </head>
<body>
    <div class="card">
        <div><strong>{{ $member->full_name }}</strong></div>
        <div>{{ $member->unit->name ?? '-' }} • KTA {{ $member->kta_number }} • NRA {{ $member->nra }}</div>
        <div>NIP: {{ $member->nip }}</div>
        <div class="badge">Status: {{ $member->status }}</div>
        <div>Valid until: {{ $member->card_valid_until ?? '-' }}</div>
        <div>Token: {{ $member->qr_token }}</div>
    </div>
</body>
</html>
