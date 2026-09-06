<?php

namespace App\Http\Controllers;

use App\Enums\MediaKind;
use App\Http\Resources\GroupResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\ShareLinkResource;
use App\Queries\StorageUsage;
use App\Queries\UserGroups;
use App\Queries\UserMedia;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserMedia $userMedia,
        private readonly UserGroups $userGroups,
        private readonly StorageUsage $storageUsage,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tag = $request->string('tag')->value() ?: null;
        $kind = $request->string('type')->value() ?: null;
        $search = $request->string('q')->value() ?: null;

        return Inertia::render('Dashboard', [
            'storage' => $this->storageUsage->forUser($user),
            'filters' => ['tag' => $tag, 'type' => $kind, 'q' => $search],
            'kinds' => MediaKind::options(),
            'tags' => $user->tags()->orderBy('name')->pluck('name')->values()->all(),
            'media' => MediaResource::collection($this->userMedia->forGallery($user, $tag, $kind, $search))->resolve(),
            'groups' => GroupResource::collection($this->userGroups->forDashboard($user))->resolve(),
            'share_links' => ShareLinkResource::collection(
                $user->shareLinks()->with('media')->latest('id')->get(),
            )->resolve(),
            'upload_url' => route('uploads.store'),
            'group_url' => route('groups.store'),
        ]);
    }
}
