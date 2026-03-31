<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;

class CommandeController extends Controller
{
    /**
     * Liste des commandes de l'utilisateur
     * 
     * GET /api/commandes
     */
    public function index(Request $request)
    {
        $query = Commande::with(['quotation', 'facture'])
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
        $commandes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $commandes->items(),
            'meta' => [
                'current_page' => $commandes->currentPage(),
                'last_page' => $commandes->lastPage(),
                'per_page' => $commandes->perPage(),
                'total' => $commandes->total(),
            ],
        ]);
    }

    /**
     * Détail d'une commande
     * 
     * GET /api/commandes/{id}
     */
    public function show($id)
    {
        $commande = Commande::with(['quotation.rooms.works.items', 'facture', 'user'])
            ->forUser(auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $commande->id,
                'numero' => $commande->numero,
                'status' => $commande->status,
                'status_label' => $commande->status_label,
                'prix_total' => $commande->prix_total,
                'order' => $commande->order,
                'created_at' => $commande->created_at,
                'updated_at' => $commande->updated_at,
                'quotation' => $commande->quotation,
                'facture' => $commande->facture ? [
                    'id' => $commande->facture->id,
                    'numero' => $commande->facture->numero,
                    'status' => $commande->facture->status,
                    'status_label' => $commande->facture->status_label,
                    'total' => $commande->facture->total,
                    'date_emission' => $commande->facture->date_emission,
                ] : null,
            ],
        ]);
    }

    /**
     * Mettre à jour le status d'une commande
     * 
     * PATCH /api/commandes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $commande = Commande::forUser(auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:en_attente,en_cours,livree,annulee',
        ]);

        $oldStatus = $commande->status;
        $commande->update(['status' => $validated['status']]);

        // Si la commande est annulée, annuler aussi la facture
        if ($validated['status'] === 'annulee' && $commande->facture) {
            $commande->facture->update(['status' => 'annulee']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $commande->id,
                'old_status' => $oldStatus,
                'new_status' => $commande->status,
                'status_label' => $commande->status_label,
            ],
        ]);
    }

    /**
     * Statistiques des commandes
     * 
     * GET /api/commandes/stats
     */
    public function stats()
    {
        $userId = auth()->id();

        $stats = [
            'total' => Commande::forUser($userId)->count(),
            'en_attente' => Commande::forUser($userId)->byStatus('en_attente')->count(),
            'en_cours' => Commande::forUser($userId)->byStatus('en_cours')->count(),
            'livree' => Commande::forUser($userId)->byStatus('livree')->count(),
            'annulee' => Commande::forUser($userId)->byStatus('annulee')->count(),
            'total_revenue' => Commande::forUser($userId)
                ->whereIn('status', ['en_attente', 'en_cours', 'livree'])
                ->sum('prix_total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}