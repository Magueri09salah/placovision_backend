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
        $query = Facture::with(['commande.quotation'])
            ->forUser(auth()->id())
            ->latestFirst();

        // Filtre par status
        if ($request->has('status') && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        // Recherche par numéro
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhereHas('commande', function ($q2) use ($search) {
                      $q2->where('numero', 'like', "%{$search}%")
                         ->orWhereHas('quotation', function ($q3) use ($search) {
                             $q3->where('client_name', 'like', "%{$search}%");
                         });
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
        $facture = Facture::with(['commande.quotation.rooms.works.items', 'commande.user'])
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
                'order' => $facture->order,
                'created_at' => $facture->created_at,
                'updated_at' => $facture->updated_at,
                'commande' => [
                    'id' => $facture->commande->id,
                    'numero' => $facture->commande->numero,
                    'status' => $facture->commande->status,
                    'status_label' => $facture->commande->status_label,
                    'prix_total' => $facture->commande->prix_total,
                ],
                'quotation' => $facture->commande->quotation,
                'user' => $facture->commande->user,
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
        $facture = Facture::with(['commande.quotation.rooms.works.items', 'commande.user'])
            ->forUser(auth()->id())
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'commande' => $facture->commande,
            'quotation' => $facture->commande->quotation,
            'user' => $facture->commande->user,
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