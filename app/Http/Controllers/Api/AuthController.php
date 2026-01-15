<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function googleAuth(Request $request)
    {
        $validated = $request->validate([
            'google_token' => 'required|string',
        ]);

        try {
            // Vérifier le token Google
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($validated['google_token']);
            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token Google invalide.',
                ], 401);
            }

            // Récupérer les infos du payload Google
            $googleEmail = $payload['email'];
            $googleFirstName = $payload['given_name'] ?? '';
            $googleLastName = $payload['family_name'] ?? '';
            $googleAvatar = $payload['picture'] ?? null;
            $googleId = $payload['sub']; // ID unique Google

            // Vérifier si user existe déjà (par email ou google_id)
            $user = User::where('email', $googleEmail)
                ->orWhere('google_id', $googleId)
                ->first();

            if ($user) {
                // Vérifier si le compte est actif
                if (!$user->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Votre compte est désactivé.',
                    ], 403);
                }

                // Mettre à jour google_id si pas encore lié
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleId]);
                }

                // USER EXISTE → LOGIN DIRECT
                $user->update(['last_login_at' => now()]);
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Connexion réussie.',
                    'action' => 'login',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'full_name' => $user->fullName(),
                            'email' => $user->email,
                            'account_type' => $user->account_type,
                            'avatar' => $user->avatar,
                            'roles' => $user->getRoleNames(),
                            'permissions' => $user->getAllPermissions()->pluck('name'),
                        ],
                        'token' => $token,
                        'token_type' => 'Bearer',
                    ]
                ], 200);
            } else {
                // USER N'EXISTE PAS → RETOURNER INFOS POUR CHOISIR TYPE COMPTE
                return response()->json([
                    'success' => true,
                    'action' => 'choose_account_type',
                    'message' => 'Compte non trouvé. Veuillez choisir votre type de compte.',
                    'data' => [
                        'google_data' => [
                            'email' => $googleEmail,
                            'first_name' => $googleFirstName,
                            'last_name' => $googleLastName,
                            'avatar' => $googleAvatar,
                            'google_id' => $googleId,
                        ]
                    ]
                ], 200);
            }

        } catch (\Google_Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation du token Google.',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'authentification Google.',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function googleAuthComplete(Request $request)
    {
        $validated = $request->validate([
            'google_id' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'avatar' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'account_type' => 'required|in:particulier,professionnel',

            // Champs entreprise (obligatoires si professionnel)
            'company_name' => 'nullable|required_if:account_type,professionnel|string|max:255',
            'company_ice' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:255',
            'company_city' => 'nullable|string|max:100',
            'company_phone' => 'nullable|string|max:20',
        ], [
            'email.unique' => 'Cet email est déjà utilisé.',
            'account_type.required' => 'Veuillez choisir un type de compte.',
            'company_name.required_if' => 'Le nom de l\'entreprise est obligatoire pour un compte professionnel.',
        ]);

        try {
            DB::beginTransaction();

            // Créer l'utilisateur
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => null, // Pas de mot de passe pour auth Google
                'phone' => $validated['phone'] ?? null,
                'account_type' => $validated['account_type'],
                'google_id' => $validated['google_id'],
                'avatar' => $validated['avatar'] ?? null,
                'is_active' => true,
                'email_verified_at' => now(), // Google vérifie déjà l'email
            ]);


            // Si professionnel → créer entreprise
            if ($validated['account_type'] === 'professionnel') {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'ice' => $validated['company_ice'] ?? null,
                    'address_line1' => $validated['company_address'] ?? null,
                    'city' => $validated['company_city'] ?? null,
                    'phone' => $validated['company_phone'] ?? null,
                    'is_active' => true,
                ]);

                // Lier user → company avec rôle admin
                $user->companies()->attach($company->id, [
                    'role' => 'admin',
                    'is_active' => true,
                    'joined_at' => now(),
                ]);

                $user->assignRole('admin_entreprise');
            } else {
                $user->assignRole('particulier');
            }

            // Générer token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->fullName(),
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'account_type' => $user->account_type,
                        'avatar' => $user->avatar,
                        'roles' => $user->getRoleNames(),
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription.',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function register(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone' => 'nullable|string|max:20',
            'account_type' => 'required|in:particulier,professionnel',
            
            // Champs entreprise (obligatoires si professionnel)
            'company_name' => 'required_if:account_type,professionnel|string|max:255',
            'company_ice' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:255',
            'company_city' => 'nullable|string|max:100',
            'company_phone' => 'nullable|string|max:20',
        ], [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'account_type.required' => 'Veuillez choisir un type de compte.',
            'company_name.required_if' => 'Le nom de l\'entreprise est obligatoire pour un compte professionnel.',
        ]);

        try {
            DB::beginTransaction();

            // 1. Créer l'utilisateur
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'account_type' => $validated['account_type'],
                'is_active' => true,
            ]);

            // 2. Si professionnel → créer entreprise
            if ($validated['account_type'] === 'professionnel') {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'ice' => $validated['company_ice'] ?? null,
                    'address_line1' => $validated['company_address'] ?? null,
                    'city' => $validated['company_city'] ?? null,
                    'phone' => $validated['company_phone'] ?? null,
                    'is_active' => true,
                ]);

                // Lier user → company avec rôle admin
                $user->companies()->attach($company->id, [
                    'role' => 'admin',
                    'is_active' => true,
                    'joined_at' => now(),
                ]);

                // Assigner rôle Spatie
                $user->assignRole('admin_entreprise');
            } else {
                // Assigner rôle particulier
                $user->assignRole('particulier');
            }

            // 3. Générer token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->fullName(),
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'account_type' => $user->account_type,
                        'roles' => $user->getRoleNames(),
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login - Connexion
     * POST /api/login
     */
    public function login(Request $request)
    {
        $validated = $request->validate(
            [
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'L\'email est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Check if user exists
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // If user registered via Google and has no password
        if (is_null($user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez vous connecter via Google ou définir un mot de passe.',
            ], 403);
        }

        // Normal password check
        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->fullName(),
                    'email' => $user->email,
                    'account_type' => $user->account_type,
                    'avatar' => $user->avatar,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }


    /**
     * Logout
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ], 200);
    }

    /**
     * Me - User actuel
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        // Charger les relations si professionnel
        $company = null;
        if ($user->isProfessionnel()) {
            $company = $user->companies()->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->fullName(),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'account_type' => $user->account_type,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'ice' => $company->ice,
                    'address' => $company->address_line1,
                    'city' => $company->city,
                    'phone' => $company->phone,
                    'logo' => $company->logo,
                ] : null,
            ]
        ], 200);
    }

    /**
     * Update Profile
     * PUT /api/profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048',
            'preferences' => 'nullable|array',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->fullName(),
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ]
        ], 200);
    }

    /**
     * Update Company Profile (professionnel only)
     * PUT /api/company/profile
     */
    public function updateCompanyProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isProfessionnel()) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux professionnels.',
            ], 403);
        }

        $company = $user->companies()->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Entreprise non trouvée.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'ice' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $path;
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil entreprise mis à jour.',
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'ice' => $company->ice,
                'email' => $company->email,
                'phone' => $company->phone,
                'address_line1' => $company->address_line1,
                'city' => $company->city,
                'logo' => $company->logo,
            ]
        ], 200);
    }

    /**
     * Change Password
     * POST /api/change-password
     */
    public function changePassword(Request $request)
    {
        
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'new_password.required' => 'Le nouveau mot de passe est obligatoire.',
            'new_password.confirmed' => 'Les mots de passe ne correspondent pas.',
        ]);
    
        $user = $request->user();

        // if (!Hash::check($validated['current_password'], $user->password)) {
        //     throw ValidationException::withMessages([
        //         'current_password' => ['Le mot de passe actuel est incorrect.'],
        //     ]);
        // }
        if ($user->password && !Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Supprimer anciens tokens
        $user->tokens()->delete();

        // Nouveau token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe changé.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }
}
