<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\QuotationResource;

class QuotationController extends Controller
{

    public function index()
    {
        $quotations = Quotation::query()
            ->orderByDesc('created_at')
            ->get();

        return QuotationResource::collection($quotations);
    }

    public function show(Quotation $quotation)
    {
        return new QuotationResource($quotation);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_name' => 'required',
            'client_email'=> 'required',
            'client_phone'=> 'required',
            'site_address' => 'required',
            'site_city' => 'required',
            'work_type' => 'required',
            'measurements' => 'required|array',
        ]);

        $totalSurface = collect($data['measurements'])->sum('surface');

        $priceM2 = match ($data['work_type']) {
            'cloison' => 350,
            'plafond' => 420,
            default => 300,
        };

        $quotation = Quotation::create([
            ...$data,
            'total_surface' => $totalSurface,
            'estimated_amount' => $totalSurface * $priceM2,
            'assumptions' => [
                'DTU' => 'DTU 25.41',
                'entraxe' => '60 cm',
                'plaque' => 'BA13 standard',
                'note' => 'Estimation indicative'
            ],
        ]);

        return response()->json($quotation, 201);
    }

    public function exportPdf($id)
    {
        $quotation = Quotation::findOrFail($id);

        $pdf = Pdf::loadView('pdf.devis', [
            'devis' => $quotation
        ]);

        $path = "devis/{$quotation->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $quotation->update(['pdf_path' => $path]);

        return response()->download(
            storage_path("app/public/$path")
        );
    }
}
