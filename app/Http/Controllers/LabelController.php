<?php

namespace App\Http\Controllers;

use App\Models\InvReceipt;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelController extends Controller
{
    public function receiptLabels(string $receipt_number)
    {
        $receipt = InvReceipt::where('receipt_number', $receipt_number)->firstOrFail();
        $barcodeGenerator = new BarcodeGeneratorPNG();

        $labels = $receipt->items->map(function ($i) use ($receipt, $barcodeGenerator) {
            $code = $i->code;
            $batch = $receipt->receipt_number;

            return [
                'code'        => $code,
                'part_number' => $i->part->part_number,
                'part_name'   => $i->part->part_name,
                'qty'         => $i->quantity,
                'penerima'    => $receipt->user->name,
                'batch'       => $receipt->receipt_number,
                'diterima'    => $receipt->received_at->format('d/m/Y H:i'),
                'qr_base64'   => base64_encode(QrCode::format('png')->size(260)->generate($code)),
                'barcode_base64' => base64_encode($barcodeGenerator->getBarcode($batch, $barcodeGenerator::TYPE_CODE_128)),
            ];
        });

        return view('livewire.inventory.labels.a4', compact('labels'));
    }
}
