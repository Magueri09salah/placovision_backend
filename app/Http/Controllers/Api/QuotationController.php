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
                            
                            // ✅ Utiliser longueur et hauteur pour les calculs DTU 25.41
                            $longueur = $workData['longueur'] ?? 0;
                            $hauteur = $workData['hauteur'] ?? 0;
                            $surface = $workData['surface'] ?? ($longueur * $hauteur);
                            $epaisseur = $workData['epaisseur'] ?? '72';
                            
                            $work = QuotationWork::create([
                                'quotation_room_id' => $room->id,
                                'work_type' => $workData['work_type'],
                                'epaisseur' => $epaisseur,
                                'longueur' => $longueur,
                                'hauteur' => $hauteur,
                                'surface' => $surface,
                                'unit' => $workType['unit'] ?? 'm2',
                                'sort_order' => $workIndex,
                            ]);

                            // ✅ Si des items sont fournis par le frontend, les utiliser
                            if (isset($workData['items']) && is_array($workData['items']) && count($workData['items']) > 0) {
                                foreach ($workData['items'] as $itemIndex => $itemData) {
                                    $work->items()->create([
                                        'designation' => $itemData['designation'],
                                        'quantity_calculated' => $itemData['quantity_calculated'],
                                        'quantity_adjusted' => $itemData['quantity_adjusted'] ?? $itemData['quantity_calculated'],
                                        'unit' => $itemData['unit'],
                                        'unit_price' => $itemData['unit_price'],
                                        'total_ht' => round(($itemData['quantity_adjusted'] ?? $itemData['quantity_calculated']) * $itemData['unit_price'], 2),
                                        'is_modified' => ($itemData['quantity_adjusted'] ?? $itemData['quantity_calculated']) != $itemData['quantity_calculated'],
                                        'sort_order' => $itemIndex,
                                    ]);
                                }
                                $work->recalculateSubtotal();
                            } else {
                                // Sinon, générer automatiquement les matériaux selon DTU
                                $work->generateItems();
                            }
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
    public function show(Request $request, $id)
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
                            
                            // ✅ Utiliser longueur et hauteur pour les calculs DTU 25.41
                            $longueur = $workData['longueur'] ?? 0;
                            $hauteur = $workData['hauteur'] ?? 0;
                            $surface = $workData['surface'] ?? ($longueur * $hauteur);
                            $epaisseur = $workData['epaisseur'] ?? '72';
                            
                            $work = QuotationWork::create([
                                'quotation_room_id' => $room->id,
                                'work_type' => $workData['work_type'],
                                'epaisseur' => $epaisseur,
                                'longueur' => $longueur,
                                'hauteur' => $hauteur,
                                'surface' => $surface,
                                'unit' => $workType['unit'] ?? 'm2',
                                'sort_order' => $workIndex,
                            ]);

                            // ✅ Si des items sont fournis par le frontend, les utiliser
                            if (isset($workData['items']) && is_array($workData['items']) && count($workData['items']) > 0) {
                                foreach ($workData['items'] as $itemIndex => $itemData) {
                                    $work->items()->create([
                                        'designation' => $itemData['designation'],
                                        'quantity_calculated' => $itemData['quantity_calculated'],
                                        'quantity_adjusted' => $itemData['quantity_adjusted'] ?? $itemData['quantity_calculated'],
                                        'unit' => $itemData['unit'],
                                        'unit_price' => $itemData['unit_price'],
                                        'total_ht' => round(($itemData['quantity_adjusted'] ?? $itemData['quantity_calculated']) * $itemData['unit_price'], 2),
                                        'is_modified' => ($itemData['quantity_adjusted'] ?? $itemData['quantity_calculated']) != $itemData['quantity_calculated'],
                                        'sort_order' => $itemIndex,
                                    ]);
                                }
                                $work->recalculateSubtotal();
                            } else {
                                $work->generateItems();
                            }
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
            'work_type' => 'required|in:habillage_mur,cloison,plafond_ba13',
            'longueur' => 'required|numeric|min:0.1',
            'hauteur' => 'required|numeric|min:0.1',
            'room_type' => 'nullable|string',
            'epaisseur' => 'nullable|string|in:72,100,140',
        ]);

        $workType = $request->work_type;
        $longueur = $request->longueur;
        $hauteur = $request->hauteur;
        $surface = $longueur * $hauteur;
        $epaisseur = $request->epaisseur ?? '72';

        // Créer un work temporaire pour le calcul
        $tempWork = new QuotationWork([
            'work_type' => $workType,
            'epaisseur' => $epaisseur,
            'longueur' => $longueur,
            'hauteur' => $hauteur,
            'surface' => $surface,
            'unit' => QuotationWork::WORK_TYPES[$workType]['unit'] ?? 'm2',
        ]);

        // Simuler une room pour obtenir le type de plaque correct
        if ($request->room_type) {
            $tempRoom = new QuotationRoom(['room_type' => $request->room_type]);
            $tempWork->setRelation('room', $tempRoom);
        }

        $materials = $tempWork->calculateMaterials();
        $total = array_sum(array_column($materials, 'total_ht'));

        return response()->json([
            'success' => true,
            'data' => [
                'work_type' => $workType,
                'work_type_label' => QuotationWork::WORK_TYPES[$workType]['label'] ?? $workType,
                'work_type_description' => QuotationWork::WORK_TYPES[$workType]['description'] ?? '',
                'epaisseur' => $epaisseur,
                'longueur' => $longueur,
                'hauteur' => $hauteur,
                'surface' => round($surface, 2),
                'unit' => QuotationWork::WORK_TYPES[$workType]['unit'] ?? 'm2',
                'materials' => $materials,
                'total_ht' => round($total, 2),
                'dtu_notice' => 'Les calculs sont établis conformément au DTU 25.41.',
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
                        'description' => $type['description'] ?? '',
                        'unit' => $type['unit'],
                        'unit_label' => $type['unit'] === 'm2' ? 'm²' : 'ml',
                    ];
                })->values(),
                'epaisseur_options' => QuotationWork::EPAISSEUR_OPTIONS,
                'statuses' => Quotation::getStatusLabels(),
                'dtu' => [
                    'version' => '25.41',
                    'entraxe' => QuotationWork::DTU['ENTRAXE'],
                    'plaque_surface' => QuotationWork::DTU['PLAQUE_SURFACE'],
                    'profil_longueur' => QuotationWork::DTU['PROFIL_LONGUEUR'],
                ],
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

        // Statistiques de base
        $stats = [
            'total' => Quotation::where('user_id', $user->id)->count(),
            'draft' => Quotation::where('user_id', $user->id)->where('status', 'draft')->count(),
            'sent' => Quotation::where('user_id', $user->id)->where('status', 'sent')->count(),
            'accepted' => Quotation::where('user_id', $user->id)->where('status', 'accepted')->count(),
            'rejected' => Quotation::where('user_id', $user->id)->where('status', 'rejected')->count(),
            'pending' => Quotation::where('user_id', $user->id)->whereIn('status', ['draft', 'sent'])->count(),
            'total_accepted_amount' => Quotation::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->sum('total_ttc'),
            'total_pending_amount' => Quotation::where('user_id', $user->id)
                ->whereIn('status', ['draft', 'sent'])
                ->sum('total_ttc'),
        ];

        // Devis ce mois-ci
        $quotationsThisMonth = Quotation::where('user_id', $user->id)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Devis le mois dernier
        $quotationsLastMonth = Quotation::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $stats['quotations_this_month'] = $quotationsThisMonth;
        $stats['quotations_trend'] = $quotationsThisMonth - $quotationsLastMonth;

        // Taux de conversion
        $totalProcessed = $stats['accepted'] + $stats['rejected'];
        $stats['conversion_rate'] = $totalProcessed > 0 
            ? round(($stats['accepted'] / $totalProcessed) * 100, 1) 
            : 0;

        // Taux de conversion le mois dernier pour comparaison
        $acceptedLastMonth = Quotation::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $rejectedLastMonth = Quotation::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $totalProcessedLastMonth = $acceptedLastMonth + $rejectedLastMonth;
        $conversionRateLastMonth = $totalProcessedLastMonth > 0 
            ? round(($acceptedLastMonth / $totalProcessedLastMonth) * 100, 1) 
            : 0;
        $stats['conversion_trend'] = round($stats['conversion_rate'] - $conversionRateLastMonth, 1);

        // ✅ Distribution des types de travaux
        $workTypeLabels = [
            'habillage_mur' => 'Habillage mur',
            'cloison' => 'Cloison',
            'plafond_ba13' => 'Plafond BA13',
            // Anciens types pour compatibilité
            'cloison_simple' => 'Cloison simple',
            'cloison_double' => 'Cloison double',
            // 'gaine_technique' => 'Gaine technique',
        ];

        $workTypeColors = [
            'habillage_mur' => '#9E3D36',
            'cloison' => '#3B82F6',
            'plafond_ba13' => '#10B981',
            'cloison_simple' => '#F59E0B',
            'cloison_double' => '#8B5CF6',
            // 'gaine_technique' => '#EC4899',
        ];

        $workTypeCounts = QuotationWork::whereHas('room.quotation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->select('work_type', DB::raw('count(*) as count'))
        ->groupBy('work_type')
        ->get();

        $stats['work_type_distribution'] = $workTypeCounts->map(function ($item) use ($workTypeLabels, $workTypeColors) {
            return [
                'name' => $workTypeLabels[$item->work_type] ?? $item->work_type,
                'count' => $item->count,
                'fill' => $workTypeColors[$item->work_type] ?? '#6B7280',
            ];
        })->values()->toArray();

        // ✅ Données mensuelles pour le graphique (6 derniers mois)
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            $monthCA = Quotation::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->whereBetween('accepted_at', [$startOfMonth, $endOfMonth])
                ->sum('total_ttc');

            $monthQuotations = Quotation::where('user_id', $user->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $monthlyData[] = [
                'name' => $month->translatedFormat('M'),
                'ca' => round($monthCA, 2),
                'devis' => $monthQuotations,
            ];
        }
        $stats['monthly_data'] = $monthlyData;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function exportPdf(Request $request, $id)
    {
        $user = $request->user();

        $quotation = Quotation::with(['rooms.works.items', 'user', 'company'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // Générer le PDF avec les informations DTU
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'dtu_notice' => 'Les calculs et quantités issus de ce document sont établis conformément aux règles de calcul et de mise en œuvre du DTU 25.41. Ils sont destinés à un usage de simulation et peuvent être ajustés selon les contraintes réelles du chantier.',
        ]);

        $pdf->setPaper('a4');

        return $pdf->download("devis-{$quotation->reference}.pdf");
    }
}