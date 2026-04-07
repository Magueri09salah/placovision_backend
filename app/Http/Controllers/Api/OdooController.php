<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Facture;
use App\Models\Notification;
use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooController extends Controller
{
    private const ODOO_API_URL = 'http://51.178.142.207:8069/api/placovision/quotation';
    private const TIMEOUT = 30;

    private const PRODUCT_MAPPING = [
        // ============ PLAQUES ============
        'Plaque BA13 standard' => ['category' => 'PLAQUES', 'designation' => 'PLAQUE STANDARD – BA13'],
        'Plaque Hydro' => ['category' => 'PLAQUES', 'designation' => 'PLAQUE HYDRO – BA13'],
        'Plaque Feu' => ['category' => 'PLAQUES', 'designation' => 'Plaque Feu – BA13'],
        'Plaque Outguard' => ['category' => 'PLAQUES', 'designation' => 'PLAQUE OUTGUARD – BA13'],
        'Plaque haute dureté' => ['category' => 'PLAQUES', 'designation' => 'Plaque haute dureté – BA13'],
        // ============ STRUCTURE ============
        'Montant M48' => ['category' => 'STRUCTURE', 'designation' => 'Montant 48 – Longueur 3 ml'],
        'Montant M70' => ['category' => 'STRUCTURE', 'designation' => 'Montant 70 – Longueur 3 ml'],
        'Rail R48' => ['category' => 'STRUCTURE', 'designation' => 'Rail 48 – Longueur 3 ml'],
        'Rail R70' => ['category' => 'STRUCTURE', 'designation' => 'Rail 70 – Longueur 3 ml'],
        'Fourrure' => ['category' => 'STRUCTURE', 'designation' => 'Fourrure 47 – Longueur 3 ml'],
        'Suspente' => ['category' => 'STRUCTURE', 'designation' => 'Suspente Pivot F47 (100U)'],
        'Cornière périphérique' => ['category' => 'STRUCTURE', 'designation' => 'Cornière d\'Angle 55/50 – Longueur 3 ml'],
        'Tige filetée' => ['category' => 'STRUCTURE', 'designation' => 'Tige filetée – Longueur 1 ml'],
        'Pivot' => ['category' => 'STRUCTURE', 'designation' => 'Suspente Pivot F47 (100U)'],
        'Cheville en laiton' => ['category' => 'STRUCTURE', 'designation' => 'Cheville laiton'],
        // ============ FINITION ============
        'Bande à joint 150m' => ['category' => 'FINITION', 'designation' => 'Bande à joint', 'variant' => 'Longueur: 150 m'],
        'Bande à joint 300m' => ['category' => 'FINITION', 'designation' => 'Bande à joint', 'variant' => 'Longueur: 300 m'],
        'Enduit' => ['category' => 'FINITION', 'designation' => 'Enduit pour plaques de plâtre'],
        'Vis TTPC 25 mm' => ['category' => 'FINITION', 'designation' => 'Vis TTPC 25 mm'],
        'Vis TTPC 9 mm' => ['category' => 'FINITION', 'designation' => 'Vis TTPC 9 mm'],
        // ============ ISOLATION ============
        'Isolant (laine de verre)' => ['category' => 'ISOLATION', 'designation' => 'Laine de verre'],
        'Laine de verre' => ['category' => 'ISOLATION', 'designation' => 'Laine de verre'],
        'Laine de roche' => ['category' => 'ISOLATION', 'designation' => 'Laine de roche'],
        'Laine de roche ROCKMUR' => ['category' => 'ISOLATION', 'designation' => 'Laine de roche'],
        'Laine minérale' => ['category' => 'ISOLATION', 'designation' => 'Laine minérale'],
    ];

    // ============================================================
    //  PlacoVision → Odoo (outgoing)
    // ============================================================

    public function sendToOdoo(Request $request, $quotationId)
    {
        try {
            $quotation = Quotation::with(['rooms.works.items'])
                ->where('user_id', auth()->id())
                ->findOrFail($quotationId);

            if (empty($quotation->client_email)) {
                return response()->json([
                    'success' => false,
                    'message' => "L'email du client est requis pour l'envoi vers Odoo.",
                ], 422);
            }

            $payload = $this->transformForOdoo($quotation);
            Log::info('Odoo payload', ['payload' => $payload]);

            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post(self::ODOO_API_URL, $payload);

            if ($response->failed()) {
                Log::error('Odoo sync failed', ['quotation_id' => $quotationId, 'status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['success' => false, 'message' => 'Erreur de communication avec Odoo: ' . $response->status()], 502);
            }

            $odooResponse = $response->json();

            if (isset($odooResponse['status']) && $odooResponse['status'] === 'error') {
                return response()->json(['success' => false, 'message' => $odooResponse['message'] ?? 'Erreur retournée par Odoo'], 400);
            }

            $quotation->update([
                'odoo_order_id' => $odooResponse['order_id'] ?? null,
                'odoo_order_name' => $odooResponse['order_name'] ?? null,
                'odoo_status' => 'draft',
                'odoo_synced_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $odooResponse['message'] ?? 'Devis synchronisé avec succès',
                'data' => [
                    'order_id' => $odooResponse['order_id'] ?? null,
                    'order_name' => $odooResponse['order_name'] ?? null,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Odoo connection failed', ['quotation_id' => $quotationId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Impossible de contacter le serveur Odoo.'], 503);
        } catch (\Exception $e) {
            Log::error('Odoo sync exception', ['quotation_id' => $quotationId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => "Une erreur inattendue s'est produite."], 500);
        }
    }

    public function notifyAcceptance(Request $request, $quotationId)
    {
        try {
            $quotation = Quotation::where('user_id', auth()->id())->findOrFail($quotationId);

            if (!$quotation->odoo_order_id) {
                return response()->json(['success' => false, 'message' => 'Ce devis n\'est pas synchronisé avec Odoo.'], 422);
            }
            if ($quotation->odoo_status !== 'sent') {
                return response()->json(['success' => false, 'message' => 'Ce devis ne peut pas être accepté (statut actuel: ' . $quotation->odoo_status . ').'], 422);
            }

            $payload = [
                'placovision_id' => $quotation->reference,
                'odoo_order_id' => $quotation->odoo_order_id,
                'action' => 'accept',
                'accepted_at' => now()->toIso8601String(),
                'accepted_by' => auth()->user()->name ?? 'Client',
            ];

            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post('http://51.178.142.207:8069/api/placovision/accept', $payload);

            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Erreur de communication avec Odoo: ' . $response->status()], 502);
            }

            return response()->json(['success' => true, 'message' => $response->json()['message'] ?? 'Acceptation envoyée à Odoo']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de contacter le serveur Odoo.'], 503);
        } catch (\Exception $e) {
            Log::error('Odoo acceptance exception', ['quotation_id' => $quotationId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => "Une erreur inattendue s'est produite."], 500);
        }
    }

    public function notifyRejection(Request $request, $quotationId)
    {
        try {
            $quotation = Quotation::where('user_id', auth()->id())->findOrFail($quotationId);

            if (!$quotation->odoo_order_id) {
                return response()->json(['success' => false, 'message' => 'Ce devis n\'est pas synchronisé avec Odoo.'], 422);
            }
            if ($quotation->odoo_status !== 'sent') {
                return response()->json(['success' => false, 'message' => 'Ce devis ne peut pas être refusé (statut actuel: ' . $quotation->odoo_status . ').'], 422);
            }

            $validated = $request->validate(['reason' => 'nullable|string|max:500']);

            $payload = [
                'placovision_id' => $quotation->reference,
                'odoo_order_id' => $quotation->odoo_order_id,
                'action' => 'reject',
                'reason' => $validated['reason'] ?? '',
                'rejected_at' => now()->toIso8601String(),
                'rejected_by' => auth()->user()->name ?? 'Client',
            ];

            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post('http://51.178.142.207:8069/api/placovision/reject', $payload);

            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Erreur de communication avec Odoo: ' . $response->status()], 502);
            }

            return response()->json(['success' => true, 'message' => $response->json()['message'] ?? 'Refus envoyé à Odoo']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de contacter le serveur Odoo.'], 503);
        } catch (\Exception $e) {
            Log::error('Odoo rejection exception', ['quotation_id' => $quotationId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => "Une erreur inattendue s'est produite."], 500);
        }
    }

    // ============================================================
    //  Odoo → PlacoVision webhooks (incoming)
    // ============================================================

    /**
     * Status webhook — Odoo notifies us when order status changes
     * POST /api/odoo/webhook/status
     */
    public function handleStatusWebhook(Request $request)
    {
        Log::info('Odoo webhook received', ['payload' => $request->all(), 'ip' => $request->ip()]);

        $validated = $request->validate([
            'placovision_id' => 'required|string',
            'odoo_order_id' => 'required|integer',
            'odoo_order_name' => 'required|string',
            'status' => 'required|string|in:draft,sent,sale,cancel',
        ]);

        $quotation = Quotation::where('reference', $validated['placovision_id'])->first();

        if (!$quotation) {
            return response()->json(['status' => 'error', 'message' => 'Quotation not found'], 404);
        }

        $oldStatus = $quotation->odoo_status;

        $quotation->update([
            'odoo_order_id' => $validated['odoo_order_id'],
            'odoo_order_name' => $validated['odoo_order_name'],
            'odoo_status' => $validated['status'],
            'odoo_synced_at' => now(),
        ]);

        Log::info('Odoo webhook: Status updated', [
            'quotation_id' => $quotation->id,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
        ]);

        // Create notification
        $this->createNotificationForStatusChange($quotation, $oldStatus, $validated['status']);

        // Sync facture status when Odoo status changes
        $this->syncFactureStatus($quotation, $validated['status']);

        return response()->json(['status' => 'success', 'message' => 'Status updated successfully']);
    }

    /**
     * Invoice webhook — Odoo notifies us when an invoice is posted
     * POST /api/odoo/webhook/invoice
     */
    public function handleInvoiceWebhook(Request $request)
    {
        Log::info('Odoo invoice webhook received', ['payload' => $request->all()]);

        $validated = $request->validate([
            'event_type'     => 'required|string|in:invoice_sync',
            'placovision_id' => 'required|string',
            'invoice_name'   => 'required|string',
            'amount_total'   => 'required|numeric',
            'portal_url'     => 'nullable|string',
            'type'           => 'required|string|in:invoice_posted',
        ]);

        $quotation = Quotation::where('reference', $validated['placovision_id'])->first();

        if (!$quotation) {
            return response()->json(['status' => 'error', 'message' => 'Quotation not found'], 404);
        }

        $facture = Facture::where('quotation_id', $quotation->id)->first();

        if ($facture) {
            $facture->update([
                'numero'     => $validated['invoice_name'],
                'total'      => $validated['amount_total'],
                'portal_url' => $validated['portal_url'] ?? null,
            ]);
        } else {
            $facture = Facture::create([
                'quotation_id'  => $quotation->id,
                'user_id'       => $quotation->user_id,
                'numero'        => $validated['invoice_name'],
                'total'         => $validated['amount_total'],
                'portal_url'    => $validated['portal_url'] ?? null,
                'date_emission' => now(),
                'status'        => 'en_attente',
                'order'         => Facture::getNextOrder(),
            ]);
        }

        // Notification
        $notification = Notification::create([
            'user_id' => $quotation->user_id,
            'type'    => 'invoice_synced',
            'title'   => "Facture {$validated['invoice_name']} reçue",
            'message' => "Une facture de {$validated['amount_total']} DH a été émise pour le devis {$quotation->reference}.",
            'data'    => json_encode([
                'facture_id'   => $facture->id,
                'quotation_id' => $quotation->id,
                'portal_url'   => $validated['portal_url'],
            ]),
        ]);

        broadcast(new NotificationCreated($notification))->toOthers();

        return response()->json(['status' => 'success', 'message' => 'Invoice synced']);
    }

    // ============================================================
    //  Private helpers
    // ============================================================

    /**
     * Sync facture status based on Odoo order status change
     *
     * Odoo status → Facture status:
     *   sale   → en_attente (facture created/kept active)
     *   cancel → annulee (facture cancelled)
     */
    private function syncFactureStatus(Quotation $quotation, string $odooStatus): void
    {
        $facture = Facture::where('quotation_id', $quotation->id)->first();

        if (!$facture) {
            return;
        }

        switch ($odooStatus) {
            case 'cancel':
                // Odoo cancelled → cancel the facture too
                if ($facture->status !== 'annulee') {
                    $facture->update(['status' => 'annulee']);
                    Log::info('Facture cancelled via Odoo status sync', [
                        'facture_id' => $facture->id,
                        'quotation_id' => $quotation->id,
                    ]);
                }
                break;

            // Add more mappings here if needed, e.g.:
            // case 'sale':
            //     // Could re-activate a cancelled facture if Odoo re-confirms
            //     break;
        }
    }

    private function createNotificationForStatusChange(Quotation $quotation, ?string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $notification = null;

        switch ($newStatus) {
            case 'sent':
                $notification = Notification::createOdooSent($quotation);
                break;
            case 'sale':
                $notification = Notification::createOdooSale($quotation);
                break;
            case 'cancel':
                $notification = Notification::createOdooCancel($quotation);
                break;
        }

        if ($notification) {
            broadcast(new NotificationCreated($notification))->toOthers();
        }
    }

    private function mapItemToOdoo($item): array
    {
        $designation = $item->designation;

        if (isset(self::PRODUCT_MAPPING[$designation])) {
            $mapping = self::PRODUCT_MAPPING[$designation];
            $result = [
                'category' => $mapping['category'],
                'designation' => $mapping['designation'],
                'quantity_adjusted' => (float) ($item->quantity_adjusted ?? $item->quantity_calculated ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
            ];
            if (isset($mapping['variant'])) {
                $result['variant'] = $mapping['variant'];
            }
            return $result;
        }

        return [
            'category' => $this->guessCategory($designation),
            'designation' => $designation,
            'quantity_adjusted' => (float) ($item->quantity_adjusted ?? $item->quantity_calculated ?? 0),
            'unit_price' => (float) ($item->unit_price ?? 0),
        ];
    }

    private function guessCategory(string $designation): string
    {
        $d = strtolower($designation);

        if (str_contains($d, 'plaque') || str_contains($d, 'ba13')) return 'PLAQUES';
        if (str_contains($d, 'montant') || str_contains($d, 'rail') || str_contains($d, 'fourrure') || str_contains($d, 'suspente') || str_contains($d, 'cornière') || str_contains($d, 'tige') || str_contains($d, 'pivot') || str_contains($d, 'cheville')) return 'STRUCTURE';
        if (str_contains($d, 'laine') || str_contains($d, 'isolant') || str_contains($d, 'isolation')) return 'ISOLATION';
        if (str_contains($d, 'vis') || str_contains($d, 'bande') || str_contains($d, 'enduit')) return 'FINITION';

        return 'DIVERS';
    }

    private function transformForOdoo(Quotation $quotation): array
    {
        $appUrl = config('app.url', 'https://www.placovision.com');

        return [
            'placovision_id' => $quotation->reference,
            'placovision_url' => "{$appUrl}/quotations/{$quotation->id}",
            'client_name' => $quotation->client_name,
            'client_email' => $quotation->client_email ?? '',
            'client_phone' => $quotation->client_phone ?? '',
            'site_address' => $quotation->site_address ?? '',
            'site_city' => $quotation->site_city ?? '',
            'site_postal_code' => $quotation->site_postal_code ?? '',
            'rooms' => $quotation->rooms->map(function ($room) {
                return [
                    'room_name' => $room->display_name ?? $room->room_name,
                    'works' => $room->works->map(function ($work) {
                        return [
                            'items' => $work->items->map(fn($item) => $this->mapItemToOdoo($item))->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }
}