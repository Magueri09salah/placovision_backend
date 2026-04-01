<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur
     * 
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $query = Notification::forUser(auth()->id())
            ->latestFirst();

        // Filtre par status (read/unread)
        if ($request->has('status')) {
            if ($request->status === 'unread') {
                $query->unread();
            } elseif ($request->status === 'read') {
                $query->read();
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Compter les notifications non lues
     * 
     * GET /api/notifications/unread-count
     */
    public function unreadCount()
    {
        $count = Notification::forUser(auth()->id())
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Marquer une notification comme lue
     * 
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $notification = Notification::forUser(auth()->id())
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     * 
     * POST /api/notifications/mark-all-read
     */
    public function markAllAsRead()
    {
        Notification::forUser(auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues',
        ]);
    }

    /**
     * Supprimer une notification
     * 
     * DELETE /api/notifications/{id}
     */
    public function destroy($id)
    {
        $notification = Notification::forUser(auth()->id())
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification supprimée',
        ]);
    }

    /**
     * Supprimer toutes les notifications lues
     * 
     * DELETE /api/notifications/clear-read
     */
    public function clearRead()
    {
        Notification::forUser(auth()->id())
            ->read()
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications lues supprimées',
        ]);
    }
}