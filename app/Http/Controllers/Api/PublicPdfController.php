<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

class PublicPdfController extends Controller
{
    /**
     * Afficher le PDF d'un devis via son token public (pour QR code)
     * GET /api/pdf/{token}
     * 
     * SANS authentification - Accessible publiquement
     */
    public function show(string $token)
    {
        $quotation = Quotation::where('public_token', $token)
            ->with(['user', 'rooms.works.items'])
            ->first();

        if (!$quotation) {
            abort(404, 'Devis introuvable');
        }

        $user = $quotation->user;
        $company = $user->companies()->first();

        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'user' => $user,
            'company' => $company,
        ]);

        $pdf->setPaper('A4', 'portrait');

        // Afficher le PDF dans le navigateur (inline)
        return $pdf->stream("Devis-{$quotation->reference}.pdf");
    }
}