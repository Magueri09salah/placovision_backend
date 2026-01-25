<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use App\Models\QuotationRoom;
use App\Models\QuotationWork;
use App\Models\QuotationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotationController extends Controller
{
    /**
     * Liste des devis
     * GET /api/quotations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Quotation::with(['rooms.works'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('site_city', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $quotations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => QuotationResource::collection($quotations),
            'meta' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Créer un devis avec simulation
     * POST /api/quotations
     */
    public function store(QuotationRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            // 1. Créer le devis principal
            $quotation = Quotation::create([
                'user_id' => $user->id,
                'company_id' => $user->isProfessionnel() ? $user->companies()->first()?->id : null,
                'client_name' => $request->client_name,
                'client_email' => $request->client_email,
                'client_phone' => $request->client_phone,
                'site_address' => $request->site_address,
                'site_city' => $request->site_city,
                'site_postal_code' => $request->site_postal_code,
                'tva_rate' => $request->tva_rate ?? 20,
                'notes' => $request->notes,
            ]);

            // 2. Créer les pièces et travaux
            if ($request->has('rooms')) {
                foreach ($request->rooms as $roomIndex => $roomData) {
                    $room = QuotationRoom::create([
                        'quotation_id' => $quotation->id,
                        'room_type' => $roomData['room_type'],
                        'room_name' => $roomData['room_name'] ?? null,
                        'sort_order' => $roomIndex,
                    ]);

                    // Créer les travaux pour cette pièce
                    if (isset($roomData['works'])) {
                        foreach ($roomData['works'] as $workIndex => $workData) {
                            $workType = QuotationWork::WORK_TYPES[$workData['work_type']] ?? null;
                            
                            $work = QuotationWork::create([
                                'quotation_room_id' => $room->id,
                                'work_type' => $workData['work_type'],
                                'surface' => $workData['surface'],
                                'unit' => $workType['unit'] ?? 'm2',
                                'sort_order' => $workIndex,
                            ]);

                            // Générer automatiquement les matériaux
                            $work->generateItems();
                        }
                    }

                    // Recalculer le sous-total de la pièce
                    $room->recalculateSubtotal();
                }
            }

            // 3. Recalculer les totaux du devis
            $quotation->recalculateTotals();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devis créé avec succès.',
                'data' => new QuotationResource($quotation->load(['rooms.works.items'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du devis.',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Voir un devis
     * GET /api/quotations/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $quotation = Quotation::with(['rooms.works.items'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Mettre à jour un devis
     * PUT /api/quotations/{id}
     */
    public function update(QuotationRequest $request, $id): JsonResponse
    {
        $user = $request->user();

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        // Vérifier si le devis peut être modifié
        if (!in_array($quotation->status, ['draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être modifié.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Mettre à jour les infos de base
            $quotation->update($request->only([
                'client_name',
                'client_email',
                'client_phone',
                'site_address',
                'site_city',
                'site_postal_code',
                'tva_rate',
                'discount_percent',
                'notes',
            ]));

            // Si des pièces sont fournies, les mettre à jour
            if ($request->has('rooms')) {
                // Supprimer les anciennes pièces
                $quotation->rooms()->delete();

                foreach ($request->rooms as $roomIndex => $roomData) {
                    $room = QuotationRoom::create([
                        'quotation_id' => $quotation->id,
                        'room_type' => $roomData['room_type'],
                        'room_name' => $roomData['room_name'] ?? null,
                        'sort_order' => $roomIndex,
                    ]);

                    if (isset($roomData['works'])) {
                        foreach ($roomData['works'] as $workIndex => $workData) {
                            $workType = QuotationWork::WORK_TYPES[$workData['work_type']] ?? null;
                            
                            $work = QuotationWork::create([
                                'quotation_room_id' => $room->id,
                                'work_type' => $workData['work_type'],
                                'surface' => $workData['surface'],
                                'unit' => $workType['unit'] ?? 'm2',
                                'sort_order' => $workIndex,
                            ]);

                            $work->generateItems();
                        }
                    }

                    $room->recalculateSubtotal();
                }
            }

            $quotation->recalculateTotals();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devis mis à jour.',
                'data' => new QuotationResource($quotation->fresh(['rooms.works.items'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour.',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Supprimer un devis
     * DELETE /api/quotations/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        $quotation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Devis supprimé.',
        ]);
    }

    /**
     * Dupliquer un devis
     * POST /api/quotations/{id}/duplicate
     */
    public function duplicate(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        $newQuotation = $quotation->duplicate();

        return response()->json([
            'success' => true,
            'message' => 'Devis dupliqué.',
            'data' => new QuotationResource($newQuotation->load(['rooms.works.items'])),
        ], 201);
    }

    /**
     * Changer le statut d'un devis
     * PATCH /api/quotations/{id}/status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        $quotation->update([
            'status' => $request->status,
            'accepted_at' => $request->status === 'accepted' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour.',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Mettre à jour les quantités d'un item
     * PATCH /api/quotations/{id}/items/{itemId}
     */
    public function updateItem(Request $request, $id, $itemId): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'quantity_adjusted' => 'required|numeric|min:0',
        ]);

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        // Vérifier si le devis peut être modifié
        if (!in_array($quotation->status, ['draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être modifié.',
            ], 422);
        }

        // Trouver l'item
        $item = QuotationItem::whereHas('work.room', function ($q) use ($quotation) {
            $q->where('quotation_id', $quotation->id);
        })->findOrFail($itemId);

        // Mettre à jour la quantité
        $item->adjustQuantity($request->quantity_adjusted);

        // Recalculer les totaux
        $quotation->recalculateTotals();

        return response()->json([
            'success' => true,
            'message' => 'Quantité mise à jour.',
            'data' => new QuotationResource($quotation->fresh(['rooms.works.items'])),
        ]);
    }

    /**
     * Réinitialiser un item à sa valeur calculée
     * POST /api/quotations/{id}/items/{itemId}/reset
     */
    public function resetItem(Request $request, $id, $itemId): JsonResponse
    {
        $user = $request->user();

        $quotation = Quotation::where('user_id', $user->id)->findOrFail($id);

        if (!in_array($quotation->status, ['draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être modifié.',
            ], 422);
        }

        $item = QuotationItem::whereHas('work.room', function ($q) use ($quotation) {
            $q->where('quotation_id', $quotation->id);
        })->findOrFail($itemId);

        $item->resetToCalculated();

        $quotation->recalculateTotals();

        return response()->json([
            'success' => true,
            'message' => 'Quantité réinitialisée.',
            'data' => new QuotationResource($quotation->fresh(['rooms.works.items'])),
        ]);
    }

    /**
     * Simuler un calcul (sans créer de devis)
     * POST /api/quotations/simulate
     */
    public function simulate(Request $request): JsonResponse
    {
        $request->validate([
            'work_type' => 'required|in:habillage_mur,plafond_ba13,cloison,gaine_creuse',
            'surface' => 'required|numeric|min:0.1',
        ]);

        $workType = $request->work_type;
        $surface = $request->surface;

        // Créer un work temporaire pour le calcul
        $tempWork = new QuotationWork([
            'work_type' => $workType,
            'surface' => $surface,
            'unit' => QuotationWork::WORK_TYPES[$workType]['unit'] ?? 'm2',
        ]);

        $materials = $tempWork->calculateMaterials();
        $total = array_sum(array_column($materials, 'total_ht'));

        return response()->json([
            'success' => true,
            'data' => [
                'work_type' => $workType,
                'work_type_label' => QuotationWork::WORK_TYPES[$workType]['label'] ?? $workType,
                'surface' => $surface,
                'unit' => QuotationWork::WORK_TYPES[$workType]['unit'] ?? 'm2',
                'materials' => $materials,
                'total_ht' => round($total, 2),
            ],
        ]);
    }

    /**
     * Obtenir les options disponibles (types de pièces, types de travaux)
     * GET /api/quotations/options
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'room_types' => QuotationRoom::ROOM_TYPES,
                'work_types' => collect(QuotationWork::WORK_TYPES)->map(function ($type, $key) {
                    return [
                        'value' => $key,
                        'label' => $type['label'],
                        'unit' => $type['unit'],
                        'unit_label' => $type['unit'] === 'm2' ? 'm²' : 'ml',
                    ];
                })->values(),
                'statuses' => Quotation::getStatusLabels(),
            ],
        ]);
    }

    /**
     * Statistiques des devis pour le Dashboard
     * GET /api/quotations/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // ============ STATISTIQUES DE BASE ============
        $baseQuery = Quotation::where('user_id', $user->id);
        
        // Compteurs par statut (tout le temps)
        $total = (clone $baseQuery)->count();
        $draft = (clone $baseQuery)->where('status', 'draft')->count();
        $sent = (clone $baseQuery)->where('status', 'sent')->count();
        $accepted = (clone $baseQuery)->where('status', 'accepted')->count();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->count();
        $expired = (clone $baseQuery)->where('status', 'expired')->count();
        
        // Pending = sent (en attente de réponse)
        $pending = $sent;

        // ============ CHIFFRE D'AFFAIRES ============
        // CA total (devis acceptés)
        $totalRevenue = (clone $baseQuery)
            ->where('status', 'accepted')
            ->sum('total_ttc');

        // CA ce mois
        $revenueThisMonth = (clone $baseQuery)
            ->where('status', 'accepted')
            ->where('accepted_at', '>=', $startOfMonth)
            ->sum('total_ttc');

        // CA mois dernier
        $revenueLastMonth = (clone $baseQuery)
            ->where('status', 'accepted')
            ->whereBetween('accepted_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_ttc');

        // Variation CA
        $revenueTrend = $revenueLastMonth > 0 
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        // ============ DEVIS CE MOIS ============
        $quotationsThisMonth = (clone $baseQuery)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $quotationsLastMonth = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $quotationsTrend = $quotationsLastMonth > 0
            ? $quotationsThisMonth - $quotationsLastMonth
            : $quotationsThisMonth;

        // ============ TAUX DE CONVERSION ============
        // Conversion = acceptés / (acceptés + refusés) * 100
        $totalProcessed = $accepted + $rejected;
        $conversionRate = $totalProcessed > 0 
            ? round(($accepted / $totalProcessed) * 100, 1) 
            : 0;

        // Conversion mois dernier
        $acceptedLastMonth = (clone $baseQuery)
            ->where('status', 'accepted')
            ->whereBetween('accepted_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $rejectedLastMonth = (clone $baseQuery)
            ->where('status', 'rejected')
            ->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $processedLastMonth = $acceptedLastMonth + $rejectedLastMonth;
        $conversionLastMonth = $processedLastMonth > 0
            ? round(($acceptedLastMonth / $processedLastMonth) * 100, 1)
            : 0;

        $conversionTrend = $conversionRate - $conversionLastMonth;

        // ============ MONTANTS EN ATTENTE ============
        $pendingAmount = (clone $baseQuery)
            ->whereIn('status', ['draft', 'sent'])
            ->sum('total_ttc');

        // ============ ÉVOLUTION MENSUELLE (6 derniers mois) ============
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
            $monthName = $monthStart->locale('fr')->isoFormat('MMM');

            $monthRevenue = Quotation::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->whereBetween('accepted_at', [$monthStart, $monthEnd])
                ->sum('total_ttc');

            $monthQuotations = Quotation::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $monthlyData[] = [
                'name' => ucfirst($monthName),
                'ca' => round($monthRevenue, 0),
                'devis' => $monthQuotations,
            ];
        }

        // ============ RÉPARTITION PAR STATUT ============
        $statusDistribution = [
            ['name' => 'Acceptés', 'value' => $accepted, 'color' => '#22c55e'],
            ['name' => 'En attente', 'value' => $sent, 'color' => '#3b82f6'],
            ['name' => 'Refusés', 'value' => $rejected, 'color' => '#ef4444'],
            ['name' => 'Brouillons', 'value' => $draft, 'color' => '#9ca3af'],
        ];

        // ============ RÉPARTITION PAR TYPE DE TRAVAUX ============
        $workTypeStats = QuotationWork::select('work_type', DB::raw('COUNT(*) as count'))
            ->whereHas('room.quotation', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->groupBy('work_type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                $colors = [
                    'plafond_ba13' => '#9E3D36',
                    'cloison' => '#c45d55',
                    'habillage_mur' => '#d4817b',
                    'gaine_creuse' => '#e5a5a0',
                ];
                $labels = [
                    'plafond_ba13' => 'Plafond BA13',
                    'cloison' => 'Cloison',
                    'habillage_mur' => 'Habillage mur',
                    'gaine_creuse' => 'Gaine creuse',
                ];
                return [
                    'name' => $labels[$item->work_type] ?? $item->work_type,
                    'count' => $item->count,
                    'fill' => $colors[$item->work_type] ?? '#9E3D36',
                ];
            });

        // ============ DEVIS EXPIRANTS BIENTÔT ============
        $expiringCount = (clone $baseQuery)
            ->where('status', 'sent')
            ->whereNotNull('validity_date')
            ->where('validity_date', '<=', $now->copy()->addDays(7))
            ->where('validity_date', '>', $now)
            ->count();

        // ============ RÉPONSE ============
        return response()->json([
            'success' => true,
            'data' => [
                // Compteurs de base
                'total' => $total,
                'draft' => $draft,
                'sent' => $sent,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'expired' => $expired,
                'pending' => $pending,

                // Chiffre d'affaires
                'total_revenue' => round($totalRevenue, 0),
                'revenue_this_month' => round($revenueThisMonth, 0),
                'revenue_last_month' => round($revenueLastMonth, 0),
                'revenue_trend' => $revenueTrend,

                // Devis ce mois
                'quotations_this_month' => $quotationsThisMonth,
                'quotations_trend' => $quotationsTrend,

                // Taux de conversion
                'conversion_rate' => $conversionRate,
                'conversion_trend' => round($conversionTrend, 1),

                // Montants
                'pending_amount' => round($pendingAmount, 0),

                // Données pour graphiques
                'monthly_data' => $monthlyData,
                'status_distribution' => $statusDistribution,
                'work_type_distribution' => $workTypeStats,

                // Alertes
                'expiring_soon' => $expiringCount,
            ],
        ]);
    }
}