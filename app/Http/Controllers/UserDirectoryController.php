<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Teammate lookup for the share dialog (an internal tool, so the directory is visible to members). */
class UserDirectoryController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        $users = User::query()
            ->whereKeyNot($request->user()->id)
            ->when($q, function ($query, string $q) {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return UserResource::collection($users);
    }
}
