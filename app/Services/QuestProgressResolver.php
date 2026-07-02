<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterQuest;
use App\Models\QuestObjective;

class QuestProgressResolver
{
    public function __construct(
        private InventoryService $inventoryService,
        private QuestStorageService $questStorageService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function applyEvent(CharacterQuest $characterQuest, string $eventType, array $payload): array
    {
        $progress = $characterQuest->progress ?? [];
        $quest = $characterQuest->quest()->with('objectives')->first();
        if (!$quest) {
            return $progress;
        }

        foreach ($quest->objectives as $objective) {
            $key = $objective->objective_key;
            $current = (int) ($progress[$key] ?? 0);

            if ($this->eventMatchesObjective($eventType, $payload, $objective)) {
                $progress[$key] = $current + 1;
            }
        }

        return $progress;
    }

    public function refreshSnapshotObjectives(Character $character, CharacterQuest $characterQuest): array
    {
        $progress = $characterQuest->progress ?? [];
        $quest = $characterQuest->quest()->with('objectives')->first();
        if (!$quest) {
            return $progress;
        }

        foreach ($quest->objectives as $objective) {
            if ($objective->type === 'collect_resource') {
                $templateSlug = $objective->config['template_slug'] ?? null;
                if (!$templateSlug) {
                    continue;
                }

                $owned = $this->inventoryService->getResourceQuantity($character, $templateSlug);
                $progress[$objective->objective_key] = min($owned, $objective->required_count);
            }

            if ($objective->type === 'have_item') {
                $templateSlug = $objective->config['template_slug'] ?? null;
                $stage = $objective->config['stage'] ?? 'item';
                if (!$templateSlug) {
                    continue;
                }

                $inInventory = $this->inventoryService->countItemsInStorage($character, $templateSlug, $stage);
                $inRequirements = $this->questStorageService->countRequirementItems(
                    $character,
                    $characterQuest->quest_slug,
                    $templateSlug,
                    $stage
                );
                $owned = $inInventory + $inRequirements;
                $progress[$objective->objective_key] = min($owned, $objective->required_count);
            }
        }

        return $progress;
    }

    public function isQuestComplete(Character $character, CharacterQuest $characterQuest): bool
    {
        $progress = $this->refreshSnapshotObjectives($character, $characterQuest);
        $quest = $characterQuest->quest()->with('objectives')->first();
        if (!$quest) {
            return false;
        }

        foreach ($quest->objectives as $objective) {
            $current = (int) ($progress[$objective->objective_key] ?? 0);
            if ($current < $objective->required_count) {
                return false;
            }
        }

        return true;
    }

    private function eventMatchesObjective(string $eventType, array $payload, QuestObjective $objective): bool
    {
        return match ($objective->type) {
            'craft_item' => $eventType === 'item.crafted'
                && ($payload['recipe_slug'] ?? null) === ($objective->config['recipe_slug'] ?? null),
            'craft_resource' => in_array($eventType, ['resource.crafted', 'resource.disassembled'], true)
                && ($payload['recipe_slug'] ?? null) === ($objective->config['recipe_slug'] ?? null),
            'collect_resource' => in_array($eventType, ['resource.crafted', 'resource.received'], true)
                && ($payload['template_slug'] ?? $payload['result_template_slug'] ?? null)
                    === ($objective->config['template_slug'] ?? null),
            default => false,
        };
    }
}
