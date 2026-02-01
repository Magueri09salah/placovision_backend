<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_room_id',
        'work_type',
        'longueur',
        'hauteur',
        'surface',
        'unit',
        'subtotal_ht',
        'sort_order',
    ];

    protected $casts = [
        'longueur' => 'decimal:2',
        'hauteur' => 'decimal:2',
        'surface' => 'decimal:2',
        'subtotal_ht' => 'decimal:2',
    ];

    /**
     * Hypothèses générales DTU 25.41
     */
    public const DTU = [
        'ENTRAXE' => 0.60,           // Entraxe standard : 60 cm
        'PLAQUE_SURFACE' => 3.00,     // Plaque BA13 standard : 120 × 250 cm = 3 m²
        'PROFIL_LONGUEUR' => 3.00,    // Longueur standard des profils : 3 m
        'VIS_PAR_BOITE' => 1000,      // Nombre de vis par boîte
        'KG_PAR_SAC_ENDUIT' => 25,    // 25 kg par sac d'enduit
        'BANDE_ROULEAU_150' => 150,   // Rouleau de bande à joint 150 m
        'BANDE_ROULEAU_300' => 300,   // Rouleau de bande à joint 300 m
    ];

    /**
     * Types d'ouvrages selon DTU 25.41
     */
    public const WORK_TYPES = [
        'habillage_mur' => [
            'label' => 'Habillage BA13 / Contre-cloison',
            'description' => 'Ouvrage vertical – 1 face',
            'unit' => 'm2',
        ],
        'cloison_simple' => [
            'label' => 'Cloison simple ossature',
            'description' => 'M48 / M70 / M90',
            'unit' => 'm2',
        ],
        'cloison_double' => [
            'label' => 'Cloison double ossature',
            'description' => 'Épaisseur ≥ 140mm',
            'unit' => 'm2',
        ],
        'gaine_technique' => [
            'label' => 'Gaine technique BA13',
            'description' => 'Ouvrage vertical technique',
            'unit' => 'm2',
        ],
        'plafond_ba13' => [
            'label' => 'Plafond BA13',
            'description' => 'Sur ossature métallique',
            'unit' => 'm2',
        ],
    ];

    /**
     * Prix unitaires en DH
     */
    public const PRIX_UNITAIRES = [
        'plaque_ba13_standard' => 24.00,
        'plaque_hydro' => 34.20,
        'plaque_feu' => 00.00,
        'montant_48' => 26.16,
        'montant_70' => 33.00,
        'montant_90' => 00.00,
        'rail_48' => 21.12,
        'rail_70' => 28.20,
        'rail_90' => 00.00,
        'fourrure' => 21.12,
        'isolant' => 00.00,           // par m²
        'vis_25mm_boite' => 62.40,   // boîte de 1000
        'vis_9mm_boite' => 69.60,    // boîte de 1000
        'suspente' => 00.00,
        'corniere' => 13.44,
        'bande_joint_150' => 48.00,   // rouleau 150m
        'bande_joint_300' => 85.00,   // rouleau 300m
        'enduit_sac' => 163.20,       // sac de 25kg
    ];

   /**
     * Type de plaque selon le type de pièce
     */
    public const PLAQUE_BY_ROOM = [
        'salon_sejour' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'chambre' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'cuisine' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'salle_de_bain' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'wc' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'bureau' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'garage' => ['designation' => 'Plaque Feu', 'prix_key' => 'plaque_feu'],
        'exterieur' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'autre' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
    ];

    // ========== RELATIONS ==========

    public function room(): BelongsTo
    {
        return $this->belongsTo(QuotationRoom::class, 'quotation_room_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    // ========== METHODES ==========

    public function getWorkTypeLabelAttribute(): string
    {
        return self::WORK_TYPES[$this->work_type]['label'] ?? $this->work_type;
    }

    public function getWorkTypeDescriptionAttribute(): string
    {
        return self::WORK_TYPES[$this->work_type]['description'] ?? '';
    }

    public function getUnitLabelAttribute(): string
    {
        return $this->unit === 'm2' ? 'm²' : 'ml';
    }

    /**
     * Arrondi à l'unité supérieure (règle DTU)
     */
    private static function arrondiSup(float $value): int
    {
        return (int) ceil($value);
    }

     /**
     * Convertit les vis en nombre de boîtes (1 boîte = 1000 vis, minimum 1)
     */
    private static function visToBoites(int $nombreVis): int
    {
        if ($nombreVis <= 0) return 0;
        return max(1, self::arrondiSup($nombreVis / self::DTU['VIS_PAR_BOITE']));
    }

     /**
     * Convertit les kg d'enduit en sacs (1 sac = 25 kg)
     */
    private static function kgToSacs(float $kg): int
    {
        if ($kg <= 0) return 0;
        return self::arrondiSup($kg / self::DTU['KG_PAR_SAC_ENDUIT']);
    }

    /**
     * Convertit les mètres linéaires de bande à joint en rouleaux
     */
    private static function bandeToRouleaux(float $ml): array
    {
        if ($ml <= 0) {
            return ['designation' => 'Bande à joint 150m', 'quantity' => 0, 'prix_key' => 'bande_joint_150'];
        }

        if ($ml <= 150) {
            return ['designation' => 'Bande à joint 150m', 'quantity' => 1, 'prix_key' => 'bande_joint_150'];
        } elseif ($ml <= 300) {
            return ['designation' => 'Bande à joint 300m', 'quantity' => 1, 'prix_key' => 'bande_joint_300'];
        } else {
            $nbRouleaux = self::arrondiSup($ml / 300);
            return ['designation' => 'Bande à joint 300m', 'quantity' => $nbRouleaux, 'prix_key' => 'bande_joint_300'];
        }
    }

    /**
     * Obtenir le type de plaque selon la pièce
     */
    private function getPlaqueInfo(): array
    {
        $roomType = $this->room->room_type ?? 'autre';
        $plaqueInfo = self::PLAQUE_BY_ROOM[$roomType] ?? self::PLAQUE_BY_ROOM['autre'];
        
        return [
            'designation' => $plaqueInfo['designation'],
            'prix' => self::PRIX_UNITAIRES[$plaqueInfo['prix_key']] ?? self::PRIX_UNITAIRES['plaque_ba13_standard'],
        ];
    }



    /**
     * Calculer les matériaux nécessaires selon les formules DTU 25.41
     */
    public function calculateMaterials(): array
    {
        $L = (float) ($this->longueur ?? 0);
        $H = (float) ($this->hauteur ?? 0);
        $surface = $L * $H;

        if ($surface <= 0) {
            return [];
        }

        $plaque = $this->getPlaqueInfo();
        $materials = [];
        $index = 0;

        // Helper pour ajouter un matériau
        $addMaterial = function($designation, $quantity, $unit, $unitPrice) use (&$materials, &$index) {
            $materials[] = [
                'designation' => $designation,
                'quantity_calculated' => $quantity,
                'quantity_adjusted' => $quantity,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'total_ht' => round($quantity * $unitPrice, 2),
                'is_modified' => false,
                'sort_order' => $index++,
            ];
        };

        switch ($this->work_type) {
            // ============ 1. HABILLAGE BA13 / CONTRE-CLOISON ============
            case 'habillage_mur':
                // Plaques BA13 = Surface ÷ 3
                $nbPlaques = self::arrondiSup($surface / self::DTU['PLAQUE_SURFACE']);
                $addMaterial($plaque['designation'], $nbPlaques, 'unité', $plaque['prix']);

                // Montants = (L ÷ 0,60) + 1
                $nbMontants = self::arrondiSup(($L / self::DTU['ENTRAXE']) + 1);
                $addMaterial('Montant 48', $nbMontants, 'unité', self::PRIX_UNITAIRES['montant_48']);

                // Rails = (L × 2) ÷ 3,00
                $nbRails = self::arrondiSup(($L * 2) / self::DTU['PROFIL_LONGUEUR']);
                $addMaterial('Rail 48', $nbRails, 'unité', self::PRIX_UNITAIRES['rail_48']);

                // Isolant = Surface (m²)
                $isolant = self::arrondiSup($surface);
                $addMaterial('Isolant (laine de verre)', $isolant, 'm²', self::PRIX_UNITAIRES['isolant']);

                // Vis TTPC 25 mm ≈ 20 vis / m²
                $vis25 = self::arrondiSup($surface * 20);
                $boitesVis25 = self::visToBoites($vis25);
                $addMaterial('Vis TTPC 25 mm', $boitesVis25, 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);

                // Vis TTPC 9 mm ≈ 3 vis / m²
                $vis9 = self::arrondiSup($surface * 3);
                $boitesVis9 = self::visToBoites($vis9);
                $addMaterial('Vis TTPC 9 mm', $boitesVis9, 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);

                // Bandes à joint ≈ 3 ml / m²
                $bandeML = self::arrondiSup($surface * 3);
                $bande = self::bandeToRouleaux($bandeML);
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);

                // Enduit ≈ 0,5 kg / m²
                $enduitKg = $surface * 0.5;
                $enduitSacs = self::kgToSacs($enduitKg);
                $addMaterial('Enduit', $enduitSacs, 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            // ============ 2. CLOISON SIMPLE OSSATURE ============
            case 'cloison_simple':
                // Plaques BA13 = (Surface × 2) ÷ 3 (2 faces)
                $nbPlaques = self::arrondiSup(($surface * 2) / self::DTU['PLAQUE_SURFACE']);
                $addMaterial($plaque['designation'], $nbPlaques, 'unité', $plaque['prix']);

                // Montants = (L ÷ 0,60) + 1
                $nbMontants = self::arrondiSup(($L / self::DTU['ENTRAXE']) + 1);
                $addMaterial('Montant 70', $nbMontants, 'unité', self::PRIX_UNITAIRES['montant_70']);

                // Rails = (L × 2) ÷ 3,00
                $nbRails = self::arrondiSup(($L * 2) / self::DTU['PROFIL_LONGUEUR']);
                $addMaterial('Rail 70', $nbRails, 'unité', self::PRIX_UNITAIRES['rail_70']);

                // Isolant = Surface (m²)
                $isolant = self::arrondiSup($surface);
                $addMaterial('Isolant (laine de verre)', $isolant, 'm²', self::PRIX_UNITAIRES['isolant']);

                // Vis TTPC 25 mm ≈ 40 vis / m²
                $vis25 = self::arrondiSup($surface * 40);
                $boitesVis25 = self::visToBoites($vis25);
                $addMaterial('Vis TTPC 25 mm', $boitesVis25, 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);

                // Vis TTPC 9 mm ≈ 4 vis / m²
                $vis9 = self::arrondiSup($surface * 4);
                $boitesVis9 = self::visToBoites($vis9);
                $addMaterial('Vis TTPC 9 mm', $boitesVis9, 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);

                // Bandes à joint ≈ 6 ml / m²
                $bandeML = self::arrondiSup($surface * 6);
                $bande = self::bandeToRouleaux($bandeML);
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);

                // Enduit ≈ 1 kg / m²
                $enduitKg = $surface * 1;
                $enduitSacs = self::kgToSacs($enduitKg);
                $addMaterial('Enduit', $enduitSacs, 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            // ============ 3. CLOISON DOUBLE OSSATURE ============
            case 'cloison_double':
                // Plaques BA13 = (Surface × 2) ÷ 3 (2 faces)
                $nbPlaques = self::arrondiSup(($surface * 2) / self::DTU['PLAQUE_SURFACE']);
                $addMaterial($plaque['designation'], $nbPlaques, 'unité', $plaque['prix']);

                // Montants = 2 × ((L ÷ 0,60) + 1) (double ossature)
                $nbMontants = self::arrondiSup(2 * (($L / self::DTU['ENTRAXE']) + 1));
                $addMaterial('Montant 70', $nbMontants, 'unité', self::PRIX_UNITAIRES['montant_70']);

                // Rails = 2 × ((L × 2) ÷ 3,00)
                $nbRails = self::arrondiSup(2 * (($L * 2) / self::DTU['PROFIL_LONGUEUR']));
                $addMaterial('Rail 70', $nbRails, 'unité', self::PRIX_UNITAIRES['rail_70']);

                // Isolant = Surface × 2 (m²)
                $isolant = self::arrondiSup($surface * 2);
                $addMaterial('Isolant (laine de verre)', $isolant, 'm²', self::PRIX_UNITAIRES['isolant']);

                // Vis TTPC 25 mm ≈ 45 vis / m²
                $vis25 = self::arrondiSup($surface * 45);
                $boitesVis25 = self::visToBoites($vis25);
                $addMaterial('Vis TTPC 25 mm', $boitesVis25, 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);

                // Vis TTPC 9 mm ≈ 6 vis / m²
                $vis9 = self::arrondiSup($surface * 6);
                $boitesVis9 = self::visToBoites($vis9);
                $addMaterial('Vis TTPC 9 mm', $boitesVis9, 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);

                // Bandes à joint ≈ 6 ml / m²
                $bandeML = self::arrondiSup($surface * 6);
                $bande = self::bandeToRouleaux($bandeML);
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);

                // Enduit ≈ 1,2 kg / m²
                $enduitKg = $surface * 1.2;
                $enduitSacs = self::kgToSacs($enduitKg);
                $addMaterial('Enduit', $enduitSacs, 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            // ============ 4. GAINE TECHNIQUE BA13 ============
            case 'gaine_technique':
                // Plaques BA13 = Surface ÷ 3
                $nbPlaques = self::arrondiSup($surface / self::DTU['PLAQUE_SURFACE']);
                $addMaterial($plaque['designation'], $nbPlaques, 'unité', $plaque['prix']);

                // Montants = (L ÷ 0,60) + 1 (L = développement)
                $nbMontants = self::arrondiSup(($L / self::DTU['ENTRAXE']) + 1);
                $addMaterial('Montant 48', $nbMontants, 'unité', self::PRIX_UNITAIRES['montant_48']);

                // Rails = (L × 2) ÷ 3,00
                $nbRails = self::arrondiSup(($L * 2) / self::DTU['PROFIL_LONGUEUR']);
                $addMaterial('Rail 48', $nbRails, 'unité', self::PRIX_UNITAIRES['rail_48']);

                // Vis TTPC 25 mm ≈ 15 vis / m²
                $vis25 = self::arrondiSup($surface * 15);
                $boitesVis25 = self::visToBoites($vis25);
                $addMaterial('Vis TTPC 25 mm', $boitesVis25, 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);

                // Vis TTPC 9 mm ≈ 3 vis / m²
                $vis9 = self::arrondiSup($surface * 3);
                $boitesVis9 = self::visToBoites($vis9);
                $addMaterial('Vis TTPC 9 mm', $boitesVis9, 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);

                // Bandes à joint ≈ 2 ml / m²
                $bandeML = self::arrondiSup($surface * 2);
                $bande = self::bandeToRouleaux($bandeML);
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);

                // Enduit ≈ 0,3 kg / m²
                $enduitKg = $surface * 0.3;
                $enduitSacs = self::kgToSacs($enduitKg);
                $addMaterial('Enduit', $enduitSacs, 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            // ============ 5. PLAFOND BA13 ============
            case 'plafond_ba13':
                // Pour le plafond: L = longueur, H = largeur (l)
                $l = $H; // largeur

                // Plaques BA13 = Surface ÷ 3
                $nbPlaques = self::arrondiSup($surface / self::DTU['PLAQUE_SURFACE']);
                $addMaterial($plaque['designation'], $nbPlaques, 'unité', $plaque['prix']);

                // Fourrures = (l ÷ 0,60) × L ÷ 3,00
                $nbFourrures = self::arrondiSup(($l / self::DTU['ENTRAXE']) * $L / self::DTU['PROFIL_LONGUEUR']);
                $addMaterial('Fourrure', $nbFourrures, 'unité', self::PRIX_UNITAIRES['fourrure']);

                // Suspentes ≈ Surface × 2,5
                $nbSuspentes = self::arrondiSup($surface * 2.5);
                $addMaterial('Suspente', $nbSuspentes, 'unité', self::PRIX_UNITAIRES['suspente']);

                // Cornières périphériques = ((L + l) × 2) ÷ 3,00
                $nbCornieres = self::arrondiSup((($L + $l) * 2) / self::DTU['PROFIL_LONGUEUR']);
                $addMaterial('Cornière périphérique', $nbCornieres, 'unité', self::PRIX_UNITAIRES['corniere']);

                // Vis TTPC 25 mm ≈ 22 vis / m²
                $vis25 = self::arrondiSup($surface * 22);
                $boitesVis25 = self::visToBoites($vis25);
                $addMaterial('Vis TTPC 25 mm', $boitesVis25, 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);

                // Bandes à joint ≈ 3 ml / m²
                $bandeML = self::arrondiSup($surface * 3);
                $bande = self::bandeToRouleaux($bandeML);
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);

                // Enduit ≈ 0,5 kg / m²
                $enduitKg = $surface * 0.5;
                $enduitSacs = self::kgToSacs($enduitKg);
                $addMaterial('Enduit', $enduitSacs, 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            default:
                break;
        }

        return $materials;
    }

    /**
     * Générer les lignes de matériaux
     */
    public function generateItems(): void
    {
        // Supprimer les anciens items
        $this->items()->delete();

        // Calculer et créer les nouveaux items
        $materials = $this->calculateMaterials();

        foreach ($materials as $material) {
            $this->items()->create($material);
        }

        // Recalculer le sous-total
        $this->recalculateSubtotal();
    }

    /**
     * Recalculer le sous-total du travail
     */
    public function recalculateSubtotal(): void
    {
        $subtotal = $this->items()->sum(\DB::raw('quantity_adjusted * unit_price'));
        $this->update(['subtotal_ht' => round($subtotal, 2)]);
    }
}