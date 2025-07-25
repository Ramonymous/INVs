<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    /* ---------- reset ---------- */
    * { margin:0; padding:0; box-sizing:border-box; }

    body{
        font-family: Arial, Helvetica, sans-serif;
        background:#f0f0f0;
        padding:20px;
    }

    /* ---------- page ---------- */
    .page{
        width:210mm;
        min-height:297mm;
        margin:0 auto;
        padding:5mm;                 /* 5 mm white border around the sheet */
        background:white;
        box-shadow:0 0 5px rgba(0,0,0,.2);
        page-break-after:always;
    }

    /* ---------- grid ---------- */
    .label-container{
        display:grid;
        grid-template-columns: repeat(3, 1fr);   /* 3 columns */
        grid-auto-rows: 55.8mm;                  /* 5 rows → 55.8 × 5 = 279 mm (inside 287 mm usable) */
        gap:3mm 3mm;                             /* 3 mm between labels */
    }

    /* ---------- label ---------- */
    .label{
        border:1px solid #000;
        border-radius:2mm;
        padding:2mm 3mm;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
        font-size:7px;            /* slightly bigger text for the larger label */
        box-shadow:0 1.5mm 3mm rgba(0,0,0,.08);
    }

    /* ---------- QR ---------- */
    .qr-section{
        text-align:center;
        margin-bottom:2mm;
    }
    .qr-code{
        width:26mm;
        height:26mm;
        margin:0 auto;
        background:#fafafa;
        border:1px solid #ccc;
    }
    .qr-code img{ width:100%; height:100%; }
    .kode-text{
        margin-top:1mm;
        font-size:6px;
        font-weight:bold;
        word-break:break-all;
    }

    /* ---------- details ---------- */
    .details-section{
        flex:1 1 auto;
        line-height:1.2;
    }
    .part-number{
        font-weight:bold;
        font-size:15px;
        margin-bottom:1mm;
        word-break:break-all;
    }
    .part-name{
        font-size:8px;
        margin-bottom:1mm;
        word-break:break-word;
    }
    .qty{ font-weight:bold; margin-bottom:1mm; }
    .batch{ font-weight:bold; margin-bottom:1mm; font-size:8px; color:#2563eb; }
    .penerima, .diterima{ font-size:8px; word-break:break-all; }

    /* ---------- barcode ---------- */
    .barcode-section{
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        height:50px;
        margin-top:1mm;
    }
    .barcode-section img{
        width:220px;
        height:25px;
        display:block;
    }
    .batch-text{
        font-family:monospace;
        font-size:10px;
        font-weight:bold;
        margin-top:0.5mm;
        text-align:center;
    }

    /* ---------- print ---------- */
    @media print{
        body{ background:white; padding:0; }
        .page{
            margin:0;
            padding:5mm;           /* keep 5 mm margin for printers that can't do edge-to-edge */
            box-shadow:none;
        }
        .label{
            border:1px solid #000;
            box-shadow:none;
        }
        .batch{ color:#000 !important; }
        .batch-text{ color:#000 !important; }
    }

    /* ---------- QR and Details Side-by-Side ---------- */
    .qr-details-container {
        display: flex;
        gap: 3mm;
        flex: 1;
        margin-bottom: 2mm;
    }

    .qr-section {
        width: 26mm;
        text-align: center;
        font-size: 6px;
    }

    .details-section {
        flex: 1;
        font-size: 7px;
        line-height: 1.2;
    }
    </style>
</head>
    <body>
        @if (!empty($labels))
            @php
                $chunkedLabels = array_chunk($labels->toArray(), 15);
            @endphp
            @foreach ($chunkedLabels as $pageLabels)
                <div class="page">
                    <div class="label-container">
                        @foreach ($pageLabels as $label)
                            <div class="label">
                                <!-- QR and Details -->
                                <div class="qr-details-container">
                                    <div class="qr-section">
                                        <div class="qr-code">
                                            <img src="data:image/png;base64,{{ $label['qr_base64'] }}" alt="QR">
                                        </div>
                                        <div class="kode-text">{{ $label['code'] }}</div>
                                    </div>
                                    <div class="details-section">
                                        <div class="part-number">{{ $label['part_number'] }}</div>
                                        <div class="part-name">Part Name : {{ $label['part_name'] }}</div>
                                        <div class="qty">Qty: {{ $label['qty'] }}</div>
                                        <div class="penerima">Diterima Oleh: {{ $label['penerima'] }}</div>
                                        <div class="diterima">Tanggal: {{ $label['diterima'] }}</div>
                                    </div>
                                </div>

                                <!-- Barcode with Batch Label -->
                                <div class="barcode-section">
                                    <img src="data:image/png;base64,{{ $label['barcode_base64'] }}" alt="Barcode">
                                    <div class="batch-text">{{ $label['batch'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center">Tidak ada label untuk dicetak.</div>
        @endif
</body>
</html>