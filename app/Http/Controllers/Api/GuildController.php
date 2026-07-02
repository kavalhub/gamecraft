<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\GuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuildController extends Controller
{
    public function __construct(
        private GuildService $guildService,
    ) {}

    public function options(): JsonResponse
    {
        return response()->json([
            'emblems' => config('game.guild_emblems', []),
        ]);
    }

    public function index(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json([
            'guild' => $this->guildService->formatGuildState($character),
            'invites' => $this->guildService->getPendingInvitesFor($character),
            'catalog' => $this->guildService->listPublicGuilds()->map(fn (Character $g) => [
                'uuid' => $g->uuid,
                'name' => $g->name,
                'emblem_icon' => $g->emblemIcon(),
                'member_count' => count($this->guildService->getMembers($g)),
            ])->values()->all(),
        ]);
    }

    public function create(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:40',
            'emblem' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $guild = $this->guildService->create(
                $character,
                $request->string('name')->toString(),
                $request->string('emblem')->toString(),
            );

            return response()->json([
                'success' => true,
                'guild' => $this->guildService->formatGuildState($character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function join(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'guild_uuid' => 'required|string|exists:characters,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $this->guildService->join($character, $request->string('guild_uuid')->toString());

            return response()->json([
                'success' => true,
                'guild' => $this->guildService->formatGuildState($character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function leave(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $this->guildService->leave($character);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function invite(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'target_uuid' => 'required|string|exists:characters,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $target = Character::where('uuid', $request->string('target_uuid')->toString())->firstOrFail();

        try {
            $invite = $this->guildService->invite($character, $target);

            return response()->json([
                'success' => true,
                'invite_uuid' => $invite->uuid,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function declineInvite(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'invite_uuid' => 'required|string|exists:guild_invites,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $this->guildService->declineInvite(
                $character,
                $request->string('invite_uuid')->toString()
            );

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
