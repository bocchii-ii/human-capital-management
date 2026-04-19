<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user   = $request->user();
        $tenant = app('tenant');

        $query = AppNotification::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->latest();

        $paginated    = $query->paginate($request->integer('per_page', 20));
        $unreadCount  = AppNotification::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return AppNotificationResource::collection($paginated)
            ->additional(['meta' => ['unread_count' => $unreadCount]]);
    }

    public function show(Request $request, AppNotification $appNotification): AppNotificationResource
    {
        abort_if($appNotification->user_id !== $request->user()->id, 403);
        abort_if($appNotification->tenant_id !== app('tenant')->id, 403);

        $appNotification->markAsRead();

        return new AppNotificationResource($appNotification);
    }

    public function markRead(Request $request, AppNotification $appNotification): AppNotificationResource
    {
        abort_if($appNotification->user_id !== $request->user()->id, 403);
        abort_if($appNotification->tenant_id !== app('tenant')->id, 403);

        $appNotification->markAsRead();

        return new AppNotificationResource($appNotification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user   = $request->user();
        $tenant = app('tenant');

        AppNotification::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, AppNotification $appNotification): JsonResponse
    {
        abort_if($appNotification->user_id !== $request->user()->id, 403);
        abort_if($appNotification->tenant_id !== app('tenant')->id, 403);

        $appNotification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }
}
