<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Label 30x58mm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            padding: 20px;
        }
        
        .label {
            width: 30mm;
            height: 58mm;
            background: white;
            border: 2px solid #333;
            border-radius: 2mm;
            padding: 1mm;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2mm 4mm rgba(0,0,0,0.1);
        }
        
        .qr-section {
            height: 24mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1mm;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 1mm;
        }
        
        .qr-code {
            width: 22mm;
            height: 22mm;
            border: 2px solid #666;
            border-radius: 1mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f8f8;
            box-shadow: inset 0 0 2mm rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .kode-text {
            font-size: 6px;
            font-weight: bold;
            text-align: center;
            margin-top: 0.5mm;
            word-break: break-all;
        }
        
        .details-section {
            flex: 1;
            font-size: 6px;
            line-height: 1.2;
        }
        
        .part-number {
            font-weight: bold;
            font-size: 7px;
            margin-bottom: 1mm;
            word-break: break-all;
        }
        
        .part-name {
            font-size: 6px;
            margin-bottom: 1mm;
            word-break: break-word;
            line-height: 1.1;
        }
        
        .qty {
            font-weight: bold;
            font-size: 6px;
            margin-bottom: 1mm;
        }
        
        .penerima {
            font-size: 5px;
            margin-bottom: 1mm;
            word-break: break-all;
        }
        
        .diterima {
            font-size: 5px;
            word-break: break-all;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .label {
                margin: 0;
                border: 2px solid #000;
                border-radius: 2mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="qr-section">
            <div class="qr-code">
                <img src="https://placehold.co/83x83?text=RDevs">
            </div>
            <div class="kode-text">{{kode}}</div>
        </div>
        <div class="details-section">
            <div class="part-number">{{part_number}}</div>
            <div class="part-name">{{part_name}}</div>
            <div class="qty">Qty: {{qty}}</div>
            <div class="penerima">To: {{penerima}}</div>
            <div class="diterima">{{diterima}}</div>
        </div>
    </div>
</body>
</html>