<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Liste des projets
     * GET /api/projects
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Project::with(['creator', 'company'])
            ->forUser($user)
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        $projects = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Créer un projet
     * POST /api/projects
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:50|unique:projects',
            'description' => 'nullable|string',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_completion_date' => 'nullable|date',
            'status' => 'nullable|in:draft,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'estimated_budget' => 'nullable|numeric|min:0',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
        ], [
            'name.required' => 'Le nom du projet est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être après la date de début.',
        ]);

        // Ajouter company_id si professionnel
        if ($user->isProfessionnel()) {
            $validated['company_id'] = $user->companies()->first()?->id;
        }

        $validated['created_by'] = $user->id;

        $project = Project::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Projet créé avec succès.',
            'data' => $project->load(['creator', 'company']),
        ], 201);
    }

    /**
     * Voir un projet
     * GET /api/projects/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $project = Project::with(['creator', 'company'])
            ->forUser($user)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Modifier un projet
     * PUT /api/projects/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $project = Project::forUser($user)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'reference' => 'nullable|string|max:50|unique:projects,reference,' . $id,
            'description' => 'nullable|string',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_completion_date' => 'nullable|date',
            'status' => 'nullable|in:draft,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'estimated_budget' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Projet mis à jour.',
            'data' => $project->load(['creator', 'company']),
        ]);
    }

    /**
     * Supprimer un projet
     * DELETE /api/projects/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $project = Project::forUser($user)->findOrFail($id);

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projet supprimé.',
        ]);
    }
}