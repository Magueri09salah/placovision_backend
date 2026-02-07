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
        'epaisseur',
        'longueur',
        'hauteur',
        'surface',
        'ouvertures',
        'unit',
        'subtotal_ht',
        'sort_order',
    ];

    protected $casts = [
        'longueur' => 'decimal:2',
        'hauteur' => 'decimal:2',
        'surface' => 'decimal:2',
        'subtotal_ht' => 'decimal:2',
        'ouvertures' => 'array',
    ];

    public const DTU = [
        'ENTRAXE' => 0.60,
        'PLAQUE_SURFACE' => 3.00,
        'PROFIL_LONGUEUR' => 3.00,
        'VIS_PAR_BOITE' => 1000,
        'KG_PAR_SAC_ENDUIT' => 25,
    ];

    // ✅ 3 types simplifiés
    public const WORK_TYPES = [
        'habillage_mur' => [
            'label' => 'Habillage BA13 / Contre-cloison',
            'description' => 'Ouvrage vertical – 1 face',
            'unit' => 'm2',
        ],
        'cloison' => [
            'label' => 'Cloison',
            'description' => 'Selon épaisseur : M48/M70/Double',
            'unit' => 'm2',
        ],
        'plafond_ba13' => [
            'label' => 'Plafond BA13',
            'description' => 'Sur ossature métallique',
            'unit' => 'm2',
        ],
    ];

    // ✅ Épaisseur cloison
    public const EPAISSEUR_OPTIONS = [
        '100' => ['montant' => 'montant_48', 'rail' => 'rail_48', 'is_double' => false, 'label' => '< 100 mm (M48/R48)'],
        '140' => ['montant' => 'montant_70', 'rail' => 'rail_70', 'is_double' => false, 'label' => '< 140 mm (M70/R70)'],
        '+ 140' => ['montant' => 'montant_48', 'rail' => 'rail_48', 'is_double' => true, 'label' => '> + 140 mm (Double M48/R48)'],
    ];

    public const PRIX_UNITAIRES = [
        'plaque_ba13_standard' => 24.12,
        'plaque_hydro' => 34.20,
        'plaque_feu' => 42.00,
        'plaque_outguard' => 97.2,
        'montant_48' => 26.16,
        'montant_70' => 33.00,
        'rail_48' => 21.12,
        'rail_70' => 28.20,
        'fourrure' => 21.12,
        'isolant_verre' => 18.00,
        // 'isolant_roche' => 47.00,
        'vis_25mm_boite' => 62.40,
        'vis_9mm_boite' => 69.60,
        'suspente' => 0.00,
        'corniere' => 13.44,
        'bande_joint_150' => 48.00,
        'bande_joint_300' => 85.00,
        'enduit_sac' => 163.20,
    ];

    public const PLAQUE_BY_ROOM = [
        'salon_sejour' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'chambre' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'cuisine' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'salle_de_bain' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'wc' => ['designation' => 'Plaque Hydro', 'prix_key' => 'plaque_hydro'],
        'bureau' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
        'garage' => ['designation' => 'Plaque Feu', 'prix_key' => 'plaque_feu'],
        'exterieur' => ['designation' => 'plaque_outguard', 'prix_key' => 'plaque_outguard'],
        'autre' => ['designation' => 'Plaque BA13 standard', 'prix_key' => 'plaque_ba13_standard'],
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(QuotationRoom::class, 'quotation_room_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

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

    public function getEpaisseurLabelAttribute(): string
    {
        return self::EPAISSEUR_OPTIONS[$this->epaisseur]['label'] ?? '';
    }

    private static function arrondiSup(float $value): int
    {
        return (int) ceil($value);
    }

    private static function visToBoites(int $nombreVis): int
    {
        if ($nombreVis <= 0) return 0;
        return max(1, self::arrondiSup($nombreVis / self::DTU['VIS_PAR_BOITE']));
    }

    private static function kgToSacs(float $kg): int
    {
        if ($kg <= 0) return 0;
        return self::arrondiSup($kg / self::DTU['KG_PAR_SAC_ENDUIT']);
    }

    private static function bandeToRouleaux(float $ml): array
    {
        if ($ml <= 0) return ['designation' => 'Bande à joint 150m', 'quantity' => 0, 'prix_key' => 'bande_joint_150'];
        if ($ml <= 150) return ['designation' => 'Bande à joint 150m', 'quantity' => 1, 'prix_key' => 'bande_joint_150'];
        if ($ml <= 300) return ['designation' => 'Bande à joint 300m', 'quantity' => 1, 'prix_key' => 'bande_joint_300'];
        return ['designation' => 'Bande à joint 300m', 'quantity' => self::arrondiSup($ml / 300), 'prix_key' => 'bande_joint_300'];
    }

    private function getPlaqueInfo(): array
    {
        $roomType = $this->room->room_type ?? 'autre';
        $plaqueInfo = self::PLAQUE_BY_ROOM[$roomType] ?? self::PLAQUE_BY_ROOM['autre'];
        return [
            'designation' => $plaqueInfo['designation'],
            'prix' => self::PRIX_UNITAIRES[$plaqueInfo['prix_key']] ?? self::PRIX_UNITAIRES['plaque_ba13_standard'],
        ];
    }

    public function calculateMaterials(): array
    {
        $L = (float) ($this->longueur ?? 0);
        $H = (float) ($this->hauteur ?? 0);
        $surface = $L * $H;
        if ($surface <= 0) return [];

        $plaque = $this->getPlaqueInfo();
        $materials = [];
        $index = 0;

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
            case 'habillage_mur':
                // Plaques (vendu au m²)
                $addMaterial($plaque['designation'], self::arrondiSup($surface), 'm²', $plaque['prix']);
                
                // Montants : formule = 2 × (Lignes - 1) × Montants/ligne
                // Doublement des lignes intérieures, retrait de 2 par ligne
                $nbLignesMontants = self::arrondiSup(($L / self::DTU['ENTRAXE']) + 1);
                $montantsParLigne = max(1, self::arrondiSup($H / self::DTU['PROFIL_LONGUEUR']));
                $totalMontants = 2 * ($nbLignesMontants - 1) * $montantsParLigne;
                $addMaterial('Montant M48', $totalMontants, 'unité', self::PRIX_UNITAIRES['montant_48']);
                
                // Rails : haut + bas
                $addMaterial('Rail R48', self::arrondiSup(($L * 2) / self::DTU['PROFIL_LONGUEUR']), 'unité', self::PRIX_UNITAIRES['rail_48']);
                $addMaterial('Fourrure', self::arrondiSup(($surface / 10) * 4), 'unité', self::PRIX_UNITAIRES['fourrure']);
                $addMaterial('Isolant (laine de verre)', self::arrondiSup($surface), 'm²', self::PRIX_UNITAIRES['isolant_verre']);
                // $addMaterial('Isolant (laine de roche)', self::arrondiSup($surface), 'm²', self::PRIX_UNITAIRES['isolant_roche']);
                $addMaterial('Vis TTPC 25 mm', self::visToBoites(self::arrondiSup($surface * 20)), 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);
                $addMaterial('Vis TTPC 9 mm', self::visToBoites(self::arrondiSup($surface * 3)), 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);
                $bande = self::bandeToRouleaux(self::arrondiSup($surface * 3));
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);
                $addMaterial('Enduit', self::kgToSacs($surface * 0.5), 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            case 'cloison':
                $epaisseur = $this->epaisseur ?? '72';
                $config = self::EPAISSEUR_OPTIONS[$epaisseur] ?? self::EPAISSEUR_OPTIONS['72'];
                $isDouble = $config['is_double'];
                $montantLabel = $config['montant'] === 'montant_48' ? 'Montant M48' : 'Montant M70';
                $railLabel = $config['rail'] === 'rail_48' ? 'Rail R48' : 'Rail R70';

                // Plaques (2 faces, vendu au m²)
                $addMaterial($plaque['designation'], self::arrondiSup($surface * 2), 'm²', $plaque['prix']);
                
                // Montants : formule = 2 × (Lignes - 1) × Montants/ligne
                // Doublement des lignes intérieures, retrait de 2 par ligne
                $nbLignesMontants = self::arrondiSup(($L / self::DTU['ENTRAXE']) + 1);
                $montantsParLigne = max(1, self::arrondiSup($H / self::DTU['PROFIL_LONGUEUR']));
                $totalMontants = 2 * ($nbLignesMontants - 1) * $montantsParLigne;
                
                // Rails : haut + bas
                $totalRails = self::arrondiSup(($L * 2) / self::DTU['PROFIL_LONGUEUR']);
                
                if ($isDouble) {
                    // Double ossature : × 2
                    $addMaterial($montantLabel, $totalMontants * 2, 'unité', self::PRIX_UNITAIRES[$config['montant']]);
                    $addMaterial($railLabel, $totalRails * 2, 'unité', self::PRIX_UNITAIRES[$config['rail']]);
                    $addMaterial('Fourrure', self::arrondiSup(($surface / 10) * 4) * 2, 'unité', self::PRIX_UNITAIRES['fourrure']);
                    $addMaterial('Isolant (laine de verre)', self::arrondiSup($surface * 2), 'm²', self::PRIX_UNITAIRES['isolant_verre']);
                    // $addMaterial('Isolant (laine de roche)', self::arrondiSup($surface * 2), 'm²', self::PRIX_UNITAIRES['isolant_roche']);
                    $addMaterial('Vis TTPC 25 mm', self::visToBoites(self::arrondiSup($surface * 45)), 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);
                    $addMaterial('Vis TTPC 9 mm', self::visToBoites(self::arrondiSup($surface * 6)), 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);
                } else {
                    // Simple ossature
                    $addMaterial($montantLabel, $totalMontants, 'unité', self::PRIX_UNITAIRES[$config['montant']]);
                    $addMaterial($railLabel, $totalRails, 'unité', self::PRIX_UNITAIRES[$config['rail']]);
                    $addMaterial('Fourrure', self::arrondiSup(($surface / 10) * 4), 'unité', self::PRIX_UNITAIRES['fourrure']);
                    $addMaterial('Isolant (laine de verre)', self::arrondiSup($surface), 'm²', self::PRIX_UNITAIRES['isolant_verre']);
                    // $addMaterial('Isolant (laine de roche)', self::arrondiSup($surface), 'm²', self::PRIX_UNITAIRES['isolant_roche']);
                    $addMaterial('Vis TTPC 25 mm', self::visToBoites(self::arrondiSup($surface * 40)), 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);
                    $addMaterial('Vis TTPC 9 mm', self::visToBoites(self::arrondiSup($surface * 4)), 'boîte', self::PRIX_UNITAIRES['vis_9mm_boite']);
                }
                $bande = self::bandeToRouleaux(self::arrondiSup($surface * 6));
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);
                $addMaterial('Enduit', self::kgToSacs($isDouble ? $surface * 1.2 : $surface), 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;

            case 'plafond_ba13':
                $l = $H;
                // Plaques (vendu au m²)
                $addMaterial($plaque['designation'], self::arrondiSup($surface), 'm²', $plaque['prix']);
                $addMaterial('Fourrure', self::arrondiSup(($l / self::DTU['ENTRAXE']) * $L / self::DTU['PROFIL_LONGUEUR']), 'unité', self::PRIX_UNITAIRES['fourrure']);
                $addMaterial('Suspente', self::arrondiSup($surface * 2.5), 'unité', self::PRIX_UNITAIRES['suspente']);
                $addMaterial('Cornière périphérique', self::arrondiSup((($L + $l) * 2) / self::DTU['PROFIL_LONGUEUR']), 'unité', self::PRIX_UNITAIRES['corniere']);
                $addMaterial('Vis TTPC 25 mm', self::visToBoites(self::arrondiSup($surface * 22)), 'boîte', self::PRIX_UNITAIRES['vis_25mm_boite']);
                $bande = self::bandeToRouleaux(self::arrondiSup($surface * 3));
                $addMaterial($bande['designation'], $bande['quantity'], 'rlx', self::PRIX_UNITAIRES[$bande['prix_key']]);
                $addMaterial('Enduit', self::kgToSacs($surface * 0.5), 'sac', self::PRIX_UNITAIRES['enduit_sac']);
                break;
        }

        return $materials;
    }

    public function generateItems(): void
    {
        $this->items()->delete();
        foreach ($this->calculateMaterials() as $material) {
            $this->items()->create($material);
        }
        $this->recalculateSubtotal();
    }

    public function recalculateSubtotal(): void
    {
        $subtotal = $this->items()->sum(\DB::raw('quantity_adjusted * unit_price'));
        $this->update(['subtotal_ht' => round($subtotal, 2)]);
    }
}