<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class FactureController extends Controller
{
    /**
     * Liste des factures de l'utilisateur
     * 
     * GET /api/factures
     */
    public function index(Request $request)
    {
        $query = Facture::with(['quotation'])
            ->forUser(auth()->id())
            ->latestFirst();

        // Filtre par status
        if ($request->has('status') && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        // Recherche par numéro ou client
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhereHas('quotation', function ($q2) use ($search) {
                      $q2->where('client_name', 'like', "%{$search}%")
                         ->orWhere('reference', 'like', "%{$search}%");
                  });
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $factures = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $factures->items(),
            'meta' => [
                'current_page' => $factures->currentPage(),
                'last_page' => $factures->lastPage(),
                'per_page' => $factures->perPage(),
                'total' => $factures->total(),
            ],
        ]);
    }

    /**
     * Détail d'une facture
     * 
     * GET /api/factures/{id}
     */
    public function show($id)
    {
        $facture = Facture::with(['quotation.rooms.works.items', 'quotation.user'])
            ->forUser(auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $facture->id,
                'numero' => $facture->numero,
                'date_emission' => $facture->date_emission,
                'status' => $facture->status,
                'status_label' => $facture->status_label,
                'total' => $facture->total,
                'portal_url' => $facture->portal_url,
                'order' => $facture->order,
                'created_at' => $facture->created_at,
                'updated_at' => $facture->updated_at,
                'quotation' => $facture->quotation,
                'user' => $facture->quotation?->user,
            ],
        ]);
    }

    /**
     * Mettre à jour le status d'une facture
     * 
     * PATCH /api/factures/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $facture = Facture::forUser(auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:en_attente,payee,annulee',
        ]);

        $oldStatus = $facture->status;
        $facture->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $facture->id,
                'old_status' => $oldStatus,
                'new_status' => $facture->status,
                'status_label' => $facture->status_label,
            ],
        ]);
    }

    /**
     * Télécharger le PDF d'une facture
     * 
     * GET /api/factures/{id}/pdf
     */
    public function downloadPdf($id)
    {
        $facture = Facture::with(['quotation.rooms.works.items', 'quotation.user'])
            ->forUser(auth()->id())
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'quotation' => $facture->quotation,
            'user' => $facture->quotation?->user,
        ]);

        $filename = "facture-{$facture->numero}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Statistiques des factures
     * 
     * GET /api/factures/stats
     */
    public function stats()
    {
        $userId = auth()->id();

        $stats = [
            'total' => Facture::forUser($userId)->count(),
            'en_attente' => Facture::forUser($userId)->byStatus('en_attente')->count(),
            'payee' => Facture::forUser($userId)->byStatus('payee')->count(),
            'annulee' => Facture::forUser($userId)->byStatus('annulee')->count(),
            'total_payee' => Facture::forUser($userId)->byStatus('payee')->sum('total'),
            'total_en_attente' => Facture::forUser($userId)->byStatus('en_attente')->sum('total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}