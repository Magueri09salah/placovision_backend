<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Facture;

use App\Models\Notification;
use App\Events\NotificationCreated;

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
     * Mapping des produits PlacoVision vers les noms EXACTS Odoo
     * Les noms doivent correspondre EXACTEMENT aux produits dans Odoo
     */
    private const PRODUCT_MAPPING = [
        // ============ PLAQUES ============
        'Plaque BA13 standard' => [
            'category' => 'PLAQUES',
            'designation' => 'PLAQUE STANDARD – BA13',
        ],
        'Plaque Hydro' => [
            'category' => 'PLAQUES',
            'designation' => 'PLAQUE HYDRO – BA13',
        ],
        'Plaque Feu' => [
            'category' => 'PLAQUES',
            'designation' => 'Plaque Feu – BA13',
        ],
        'Plaque Outguard' => [
            'category' => 'PLAQUES',
            'designation' => 'PLAQUE OUTGUARD – BA13',
        ],
        'Plaque haute dureté' => [
            'category' => 'PLAQUES',
            'designation' => 'Plaque haute dureté – BA13',
        ],
        
        // ============ STRUCTURE ============
        'Montant M48' => [
            'category' => 'STRUCTURE',
            'designation' => 'Montant 48 – Longueur 3 ml',
        ],
        'Montant M70' => [
            'category' => 'STRUCTURE',
            'designation' => 'Montant 70 – Longueur 3 ml',
        ],
        'Rail R48' => [
            'category' => 'STRUCTURE',
            'designation' => 'Rail 48 – Longueur 3 ml',
        ],
        'Rail R70' => [
            'category' => 'STRUCTURE',
            'designation' => 'Rail 70 – Longueur 3 ml',
        ],
        'Fourrure' => [
            'category' => 'STRUCTURE',
            'designation' => 'Fourrure 47 – Longueur 3 ml',
        ],
        'Suspente' => [
            'category' => 'STRUCTURE',
            'designation' => 'Suspente Pivot F47 (100U)',
        ],
        'Cornière périphérique' => [
            'category' => 'STRUCTURE',
            'designation' => 'Cornière d\'Angle 55/50 – Longueur 3 ml',
        ],
        'Tige filetée' => [
            'category' => 'STRUCTURE',
            'designation' => 'Tige filetée – Longueur 1 ml',
        ],
        'Pivot' => [
            'category' => 'STRUCTURE',
            'designation' => 'Suspente Pivot F47 (100U)',
        ],
        'Cheville en laiton' => [
            'category' => 'STRUCTURE',
            'designation' => 'Cheville laiton',
        ],
        
        // ============ FINITION ============
        'Bande à joint 150m' => [
            'category' => 'FINITION',
            'designation' => 'Bande à joint',
            'variant' => 'Longueur: 150 m',
        ],
        'Bande à joint 300m' => [
            'category' => 'FINITION',
            'designation' => 'Bande à joint',
            'variant' => 'Longueur: 300 m',
        ],
        'Enduit' => [
            'category' => 'FINITION',
            'designation' => 'Enduit pour plaques de plâtre',
        ],
        'Vis TTPC 25 mm' => [
            'category' => 'FINITION',
            'designation' => 'Vis TTPC 25 mm',
        ],
        'Vis TTPC 9 mm' => [
            'category' => 'FINITION',
            'designation' => 'Vis TTPC 9 mm',
        ],
        
        // ============ ISOLATION ============
        'Isolant (laine de verre)' => [
            'category' => 'ISOLATION',
            'designation' => 'Laine de verre',
        ],
        'Laine de verre' => [
            'category' => 'ISOLATION',
            'designation' => 'Laine de verre',
        ],
        'Laine de roche' => [
            'category' => 'ISOLATION',
            'designation' => 'Laine de roche',
        ],
        'Laine de roche ROCKMUR' => [
            'category' => 'ISOLATION',
            'designation' => 'Laine de roche',
        ],
        'Laine minérale' => [
            'category' => 'ISOLATION',
            'designation' => 'Laine minérale',
        ],
    ];

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

            // Log du payload pour debug
            Log::info('Odoo payload', ['payload' => $payload]);

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

            // Mettre à jour le devis avec les infos Odoo
            $quotation->update([
                'odoo_order_id' => $odooResponse['order_id'] ?? null,
                'odoo_order_name' => $odooResponse['order_name'] ?? null,
                'odoo_status' => 'draft',
                'odoo_synced_at' => now(),
            ]);

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
     * Mapper un item PlacoVision vers le format Odoo
     * 
     * @param object $item
     * @return array
     */
    private function mapItemToOdoo($item): array
    {
        $designation = $item->designation;
        
        // Chercher dans le mapping
        if (isset(self::PRODUCT_MAPPING[$designation])) {
            $mapping = self::PRODUCT_MAPPING[$designation];
            
            $result = [
                'category' => $mapping['category'],
                'designation' => $mapping['designation'],
                'quantity_adjusted' => (float) ($item->quantity_adjusted ?? $item->quantity_calculated ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
            ];
            
            // Ajouter variant si présent
            if (isset($mapping['variant'])) {
                $result['variant'] = $mapping['variant'];
            }
            
            return $result;
        }
        
        // Fallback : déterminer la catégorie automatiquement
        $category = $this->guessCategory($designation);
        
        return [
            'category' => $category,
            'designation' => $designation,
            'quantity_adjusted' => (float) ($item->quantity_adjusted ?? $item->quantity_calculated ?? 0),
            'unit_price' => (float) ($item->unit_price ?? 0),
        ];
    }

    /**
     * Deviner la catégorie d'un produit non mappé
     * 
     * @param string $designation
     * @return string
     */
    private function guessCategory(string $designation): string
    {
        $designationLower = strtolower($designation);
        
        // PLAQUES
        if (str_contains($designationLower, 'plaque') || str_contains($designationLower, 'ba13')) {
            return 'PLAQUES';
        }
        
        // STRUCTURE
        if (str_contains($designationLower, 'montant') || 
            str_contains($designationLower, 'rail') || 
            str_contains($designationLower, 'fourrure') ||
            str_contains($designationLower, 'suspente') ||
            str_contains($designationLower, 'cornière') ||
            str_contains($designationLower, 'tige') ||
            str_contains($designationLower, 'pivot') ||
            str_contains($designationLower, 'cheville')) {
            return 'STRUCTURE';
        }
        
        // ISOLATION
        if (str_contains($designationLower, 'laine') || 
            str_contains($designationLower, 'isolant') ||
            str_contains($designationLower, 'isolation')) {
            return 'ISOLATION';
        }
        
        // FINITION (par défaut pour vis, bande, enduit)
        if (str_contains($designationLower, 'vis') || 
            str_contains($designationLower, 'bande') || 
            str_contains($designationLower, 'enduit')) {
            return 'FINITION';
        }
        
        // Catégorie par défaut
        return 'DIVERS';
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
                                return $this->mapItemToOdoo($item);
                            })->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Recevoir les mises à jour de statut depuis Odoo
     * 
     * POST /api/odoo/webhook/status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleStatusWebhook(Request $request)
    {
        // Log incoming request for debug
        Log::info('Odoo webhook received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

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

        // Sauvegarder l'ancien status pour vérifier le changement
        $oldStatus = $quotation->odoo_status;

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
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
        ]);

        $this->createNotificationForStatusChange($quotation, $oldStatus, $validated['status']);

        // ============ CRÉATION AUTOMATIQUE COMMANDE + FACTURE ============
        // Si le status passe à 'sale' (confirmé), créer la commande et la facture
        if ($validated['status'] === 'sale' && $oldStatus !== 'sale') {
            try {
                $this->createCommandeAndFacture($quotation);
            } catch (\Exception $e) {
                Log::error('Odoo webhook: Failed to create commande/facture', [
                    'quotation_id' => $quotation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
        ]);
    }

    private function createNotificationForStatusChange(Quotation $quotation, ?string $oldStatus, string $newStatus): void
    {
        // Éviter les doublons si le status n'a pas changé
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

        // Broadcaster la notification via WebSocket
        if ($notification) {
            Log::info('Notification created and broadcasting', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'user_id' => $notification->user_id,
            ]);

            broadcast(new NotificationCreated($notification))->toOthers();
        }
    }

    

    private function createCommandeAndFacture(Quotation $quotation): void
    {
        // Vérifier si une commande existe déjà pour ce devis
        $existingCommande = \App\Models\Commande::where('quotation_id', $quotation->id)->first();
        $existingFacture = \App\Models\Facture::whereHas('commande', function ($q) use ($quotation) {
            $q->where('quotation_id', $quotation->id);
        })->first();
        
        if ($existingCommande) {
            Log::info('Commande already exists for quotation', [
                'quotation_id' => $quotation->id,
                'commande_id' => $existingCommande->id,
            ]);
            return;
        }

        // Créer la commande
        $commande = \App\Models\Commande::createFromQuotation($quotation);

        Log::info('Commande created from quotation', [
            'quotation_id' => $quotation->id,
            'commande_id' => $commande->id,
            'commande_numero' => $commande->numero,
        ]);

        if ($existingFacture) {
            Log::info('Facture already exists for quotation', [
                'quotation_id' => $quotation->id,
                'facture_id' => $existingFacture->id,
            ]);
            return;
        }

        // Créer la facture
        $facture = \App\Models\Facture::createFromCommande($commande);

        Log::info('Facture created from commande', [
            'commande_id' => $commande->id,
            'facture_id' => $facture->id,
            'facture_numero' => $facture->numero,
        ]);
    }

    public function notifyAcceptance(Request $request, $quotationId)
    {
        try {
            // Récupérer le devis (vérifie que l'utilisateur est propriétaire)
            $quotation = Quotation::where('user_id', auth()->id())
                ->findOrFail($quotationId);
 
            // Vérifier que le devis est synchronisé avec Odoo
            if (!$quotation->odoo_order_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce devis n\'est pas synchronisé avec Odoo.',
                ], 422);
            }
 
            // Vérifier que le statut Odoo est 'sent' (en attente)
            if ($quotation->odoo_status !== 'sent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce devis ne peut pas être accepté (statut actuel: ' . $quotation->odoo_status . ').',
                ], 422);
            }
 
            // Préparer le payload pour Odoo
            $payload = [
                'placovision_id' => $quotation->reference,
                'odoo_order_id' => $quotation->odoo_order_id,
                'action' => 'accept',
                'accepted_at' => now()->toIso8601String(),
                'accepted_by' => auth()->user()->name ?? 'Client',
            ];
 
            // Log pour debug
            Log::info('Sending acceptance to Odoo', [
                'quotation_id' => $quotationId,
                'payload' => $payload,
            ]);
 
            // Envoyer la notification à Odoo
            // URL de l'endpoint Odoo pour recevoir les acceptations
            $odooAcceptUrl = 'http://51.178.142.207:8069/api/placovision/accept';
 
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($odooAcceptUrl, $payload);
 
            // Vérifier la réponse
            if ($response->failed()) {
                Log::error('Odoo acceptance failed', [
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
 
            // Log succès
            Log::info('Odoo acceptance successful', [
                'quotation_id' => $quotationId,
                'odoo_response' => $odooResponse,
            ]);
 
            // Succès - Note: Le statut sera mis à jour par le webhook Odoo
            return response()->json([
                'success' => true,
                'message' => $odooResponse['message'] ?? 'Acceptation envoyée à Odoo',
            ]);
 
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Devis introuvable',
            ], 404);
 
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Odoo connection failed for acceptance', [
                'quotation_id' => $quotationId,
                'error' => $e->getMessage(),
            ]);
 
            return response()->json([
                'success' => false,
                'message' => 'Impossible de contacter le serveur Odoo. Réessayez plus tard.',
            ], 503);
 
        } catch (\Exception $e) {
            Log::error('Odoo acceptance exception', [
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

    public function notifyRejection(Request $request, $quotationId)
    {
        try {
            // Récupérer le devis (vérifie que l'utilisateur est propriétaire)
            $quotation = Quotation::where('user_id', auth()->id())
                ->findOrFail($quotationId);

            // Vérifier que le devis est synchronisé avec Odoo
            if (!$quotation->odoo_order_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce devis n\'est pas synchronisé avec Odoo.',
                ], 422);
            }

            // Vérifier que le statut Odoo est 'sent' (en attente)
            if ($quotation->odoo_status !== 'sent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce devis ne peut pas être refusé (statut actuel: ' . $quotation->odoo_status . ').',
                ], 422);
            }

            // Valider la raison (optionnelle)
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            // Préparer le payload pour Odoo
            $payload = [
                'placovision_id' => $quotation->reference,
                'odoo_order_id' => $quotation->odoo_order_id,
                'action' => 'reject',
                'reason' => $validated['reason'] ?? '',
                'rejected_at' => now()->toIso8601String(),
                'rejected_by' => auth()->user()->name ?? 'Client',
            ];

            // Log pour debug
            Log::info('Sending rejection to Odoo', [
                'quotation_id' => $quotationId,
                'payload' => $payload,
            ]);

            // Envoyer la notification à Odoo
            // URL de l'endpoint Odoo pour recevoir les refus
            $odooRejectUrl = 'http://51.178.142.207:8069/api/placovision/reject';

            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($odooRejectUrl, $payload);

            // Vérifier la réponse
            if ($response->failed()) {
                Log::error('Odoo rejection failed', [
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

            // Log succès
            Log::info('Odoo rejection successful', [
                'quotation_id' => $quotationId,
                'odoo_response' => $odooResponse,
            ]);

            // Succès - Note: Le statut sera mis à jour par le webhook Odoo
            return response()->json([
                'success' => true,
                'message' => $odooResponse['message'] ?? 'Refus envoyé à Odoo',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Devis introuvable',
            ], 404);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Odoo connection failed for rejection', [
                'quotation_id' => $quotationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de contacter le serveur Odoo. Réessayez plus tard.',
            ], 503);

        } catch (\Exception $e) {
            Log::error('Odoo rejection exception', [
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
 * Recevoir la synchro facture depuis Odoo
 * POST /api/odoo/webhook/invoice
 */
public function handleInvoiceWebhook(Request $request)
{
    Log::info('Odoo invoice webhook received', [
        'payload' => $request->all(),
    ]);

    $validated = $request->validate([
        'event_type'     => 'required|string|in:invoice_sync',
        'placovision_id' => 'required|string',
        'invoice_name'   => 'required|string',
        'amount_total'   => 'required|numeric',
        'portal_url'     => 'nullable|string',
        'type'           => 'required|string|in:invoice_posted',
    ]);

    // Find quotation by reference
    $quotation = Quotation::where('reference', $validated['placovision_id'])->first();

    if (!$quotation) {
        return response()->json([
            'status' => 'error',
            'message' => 'Quotation not found',
        ], 404);
    }

    // Check if facture already exists for this quotation
    $facture = Facture::where('quotation_id', $quotation->id)->first();

    if ($facture) {
        // Update existing facture with Odoo data
        $facture->update([
            'numero'       => $validated['invoice_name'],
            'total'        => $validated['amount_total'],
            'portal_url'   => $validated['portal_url'] ?? null,
            'status'       => 'en_attente',
        ]);
    } else {
        // Create new facture from Odoo data
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

    // Create notification
    $notification = Notification::create([
        'user_id' => $quotation->user_id,
        'type'    => 'invoice_synced',
        'title'   => "Facture {$validated['invoice_name']} reçue",
        'message'    => "Une facture de {$validated['amount_total']} DH a été émise pour le devis {$quotation->reference}.",
        'data'    => json_encode([
            'facture_id'  => $facture->id,
            'quotation_id' => $quotation->id,
            'portal_url'  => $validated['portal_url'],
        ]),
    ]);

    broadcast(new \App\Events\NotificationCreated($notification))->toOthers();

    return response()->json([
        'status'  => 'success',
        'message' => 'Invoice synced',
    ]);
}


}