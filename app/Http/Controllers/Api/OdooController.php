<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooController extends Controller
{
    /**
     * URL de l'API Odoo
     */
    private const ODOO_API_URL = 'http://51.178.142.207:8069/api/placovision/quotation';
    
    /**
     * Timeout en secondes
     */
    private const TIMEOUT = 30;

    /**
     * Envoyer un devis vers Odoo
     * 
     * @param Request $request
     * @param int $quotationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToOdoo(Request $request, $quotationId)
    {
        try {
            // Récupérer le devis avec ses relations
            $quotation = Quotation::with(['rooms.works.items'])
                ->where('user_id', auth()->id())
                ->findOrFail($quotationId);

            // Valider les données requises
            if (empty($quotation->client_email)) {
                return response()->json([
                    'success' => false,
                    'message' => "L'email du client est requis pour l'envoi vers Odoo.",
                ], 422);
            }

            // Transformer les données pour Odoo
            $payload = $this->transformForOdoo($quotation);

            // Envoyer vers Odoo
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::ODOO_API_URL, $payload);

            // Vérifier la réponse
            if ($response->failed()) {
                Log::error('Odoo sync failed', [
                    'quotation_id' => $quotationId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de communication avec Odoo: ' . $response->status(),
                ], 502);
            }

            $odooResponse = $response->json();

            // Vérifier si Odoo a retourné une erreur
            if (isset($odooResponse['status']) && $odooResponse['status'] === 'error') {
                Log::warning('Odoo returned error', [
                    'quotation_id' => $quotationId,
                    'odoo_message' => $odooResponse['message'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $odooResponse['message'] ?? 'Erreur retournée par Odoo',
                ], 400);
            }

            // Succès
            Log::info('Odoo sync successful', [
                'quotation_id' => $quotationId,
                'odoo_order_id' => $odooResponse['order_id'] ?? null,
                'odoo_order_name' => $odooResponse['order_name'] ?? null,
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
            return response()->json([
                'success' => false,
                'message' => 'Devis introuvable',
            ], 404);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Odoo connection failed', [
                'quotation_id' => $quotationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de contacter le serveur Odoo. Réessayez plus tard.',
            ], 503);

        } catch (\Exception $e) {
            Log::error('Odoo sync exception', [
                'quotation_id' => $quotationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur inattendue s'est produite.",
            ], 500);
        }
    }

    /**
     * Transformer le devis PlacoVision vers le format Odoo
     * 
     * @param Quotation $quotation
     * @return array
     */
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
                            'items' => $work->items->map(function ($item) {
                                return [
                                    'designation' => $item->designation,
                                    'quantity_adjusted' => (float) ($item->quantity_adjusted ?? $item->quantity_calculated ?? 0),
                                    'unit_price' => (float) ($item->unit_price ?? 0),
                                ];
                            })->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }

        public function handleStatusWebhook(Request $request)
    {
        // Vérifier la clé API
        // $apiKey = $request->header('X-PlacoVision-Api-Key');
        // $expectedKey = config('services.odoo.webhook_key');
        
        // if (!$expectedKey || $apiKey !== $expectedKey) {
        //     Log::warning('Odoo webhook: Invalid API key', [
        //         'ip' => $request->ip(),
        //     ]);
            
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Invalid API key',
        //     ], 401);
        // }

        // Valider le payload
        $validated = $request->validate([
            'placovision_id' => 'required|string',
            'odoo_order_id' => 'required|integer',
            'odoo_order_name' => 'required|string',
            'status' => 'required|string|in:draft,sent,sale,cancel',
        ]);

        // Trouver le devis par sa référence PlacoVision
        $quotation = Quotation::where('reference', $validated['placovision_id'])->first();

        if (!$quotation) {
            Log::warning('Odoo webhook: Quotation not found', [
                'placovision_id' => $validated['placovision_id'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Quotation not found',
            ], 404);
        }

        // Mettre à jour le devis avec les infos Odoo
        $quotation->update([
            'odoo_order_id' => $validated['odoo_order_id'],
            'odoo_order_name' => $validated['odoo_order_name'],
            'odoo_status' => $validated['status'],
            'odoo_synced_at' => now(),
        ]);

        Log::info('Odoo webhook: Status updated', [
            'quotation_id' => $quotation->id,
            'reference' => $quotation->reference,
            'odoo_status' => $validated['status'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
        ]);
    }
}