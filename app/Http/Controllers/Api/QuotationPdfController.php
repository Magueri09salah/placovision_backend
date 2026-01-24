<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuotationPdfController extends Controller
{
    /**
     * Generate PDF for a quotation
     * GET /api/quotations/{id}/pdf
     */
    public function generate(Request $request, $id)
    {
        $user = $request->user();

        // Get quotation with all relations
        $quotation = Quotation::with([
            'rooms.works.items',
            'company',
            'project'
        ])
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id);
            if ($user->isProfessionnel()) {
                $companyId = $user->companies()->first()?->id;
                if ($companyId) {
                    $query->orWhere('company_id', $companyId);
                }
            }
        })
        ->findOrFail($id);

        // Get company info
        $company = $quotation->company;
        if (!$company && $user->isProfessionnel()) {
            $company = $user->companies()->first();
        }

        // Generate PDF
        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
        ]);

        // PDF options
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        // Return as download or stream
        $filename = "devis-{$quotation->reference}.pdf";

        if ($request->query('download') === 'true') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Preview PDF in browser (HTML version)
     * GET /api/quotations/{id}/pdf/preview
     */
    public function preview(Request $request, $id)
    {
        $user = $request->user();

        $quotation = Quotation::with([
            'rooms.works.items',
            'company',
            'project'
        ])
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id);
            if ($user->isProfessionnel()) {
                $companyId = $user->companies()->first()?->id;
                if ($companyId) {
                    $query->orWhere('company_id', $companyId);
                }
            }
        })
        ->findOrFail($id);

        $company = $quotation->company;
        if (!$company && $user->isProfessionnel()) {
            $company = $user->companies()->first();
        }

        return view('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
        ]);
    }
}