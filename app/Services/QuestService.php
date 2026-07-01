<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterQuest;
use App\Models\ItemTemplate;
use App\Models\Quest;
use App\Models\QuestObjective;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestService
{
    public function __construct(
        private QuestProgressResolver $progressResolver,
        private QuestStorageService $questStorageService,
        private CharacterStatsService $characterStatsService,
        private EventStore $eventStore,
    ) {}

    /**
     * @return array{available: array, active: array, finished: array}
     */
    public function listForCharacter(Character $character): array
    {
        $quests = Quest::where('is_active', true)->with('objectives')->orderBy('sort_order')->get();
        $characterQuests = CharacterQuest::where('character_uuid', $character->uuid)
            ->get()
            ->keyBy('quest_slug');

        $available = [];
        $active = [];
        $finished = [];

        foreach ($quests as $quest) {
            $state = $characterQuests->get($quest->slug);
            $formatted = $this->formatQuest($character, $quest, $state);

            if (!$state) {
                if ($this->canAccept($character, $quest, $characterQuests)) {
                    $available[] = $formatted;
                }
                continue;
            }

            if ($state->status === CharacterQuest::STATUS_TURNED_IN) {
                $finished[] = $formatted;
                if ($quest->repeatable && $this->canAccept($character, $quest, $characterQuests)) {
                    $available[] = $this->formatQuest($character, $quest, null);
                }
                continue;
            }

            if ($state->status === CharacterQuest::STATUS_COMPLETED) {
                $active[] = $formatted;
                continue;
            }

            if ($state->status === CharacterQuest::STATUS_ACTIVE
                && $this->progressResolver->isQuestComplete($character, $state)) {
                $state->update([
                    'status' => CharacterQuest::STATUS_COMPLETED,
                    'completed_at' => $state->completed_at ?? now(),
                    'progress' => $this->progressResolver->refreshSnapshotObjectives($character, $state),
                ]);
                $formatted = $this->formatQuest($character, $quest, $state->fresh());
                $active[] = $formatted;
                continue;
            }

            $active[] = $formatted;
        }

        return compact('available', 'active', 'finished');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(Character $character, string $questSlug): array
    {
        $quest = Quest::where('slug', $questSlug)->where('is_active', true)->with('objectives')->firstOrFail();
        $characterQuest = CharacterQuest::where('character_uuid', $character->uuid)
            ->where('quest_slug', $questSlug)
            ->first();

        $mode = 'offer';
        if ($characterQuest) {
            $mode = match ($characterQuest->status) {
                CharacterQuest::STATUS_COMPLETED => 'turn_in',
                CharacterQuest::STATUS_TURNED_IN => 'done',
                default => 'active',
            };
        } elseif (!$this->canAccept($character, $quest, CharacterQuest::where('character_uuid', $character->uuid)->get()->keyBy('quest_slug'))) {
            throw new \RuntimeException('Квест недоступен');
        }

        if ($mode === 'offer') {
            $this->questStorageService->prepareOfferSession($character, $quest);
            $this->questStorageService->ensureRewardPreview($character, $quest);
        } elseif (in_array($mode, ['active', 'turn_in'], true)) {
            $this->questStorageService->ensureActiveSession($character, $quest);
        }

        $characterQuests = CharacterQuest::where('character_uuid', $character->uuid)->get()->keyBy('quest_slug');

        return [
            'quest' => $this->formatQuest($character, $quest, $characterQuest),
            'mode' => $mode,
            'can_accept' => $mode === 'offer' && $this->canAccept($character, $quest, $characterQuests),
            'can_turn_in' => $characterQuest
                && $characterQuest->status !== CharacterQuest::STATUS_TURNED_IN
                && (
                    $characterQuest->status === CharacterQuest::STATUS_COMPLETED
                    || $this->progressResolver->isQuestComplete($character, $characterQuest)
                ),
        ];
    }

    public function isOfferable(Character $character, string $questSlug): bool
    {
        $quest = Quest::where('slug', $questSlug)->where('is_active', true)->first();
        if (!$quest) {
            return false;
        }

        $characterQuests = CharacterQuest::where('character_uuid', $character->uuid)->get()->keyBy('quest_slug');

        return $this->canAccept($character, $quest, $characterQuests);
    }

    public function accept(Character $character, string $questSlug): CharacterQuest
    {
        return DB::transaction(function () use ($character, $questSlug) {
            $quest = Quest::where('slug', $questSlug)->where('is_active', true)->with('objectives')->firstOrFail();
            $existing = CharacterQuest::where('character_uuid', $character->uuid)
                ->where('quest_slug', $questSlug)
                ->first();

            if ($existing && $existing->status !== CharacterQuest::STATUS_TURNED_IN) {
                throw new \RuntimeException('Квест уже принят');
            }

            if ($existing && $existing->status === CharacterQuest::STATUS_TURNED_IN && !$quest->repeatable) {
                throw new \RuntimeException('Квест уже сдан');
            }

            $characterQuests = CharacterQuest::where('character_uuid', $character->uuid)->get()->keyBy('quest_slug');
            if (!$this->canAccept($character, $quest, $characterQuests)) {
                throw new \RuntimeException('Квест недоступен');
            }

            $this->questStorageService->prepareOfferSession($character, $quest);
            $this->questStorageService->assertInventoryFitsGrants($character, $quest);

            $progress = [];
            foreach ($quest->objectives as $objective) {
                $progress[$objective->objective_key] = 0;
            }

            if ($existing && $quest->repeatable) {
                $characterQuest = $existing;
                $characterQuest->update([
                    'status' => CharacterQuest::STATUS_ACTIVE,
                    'progress' => $progress,
                    'accepted_at' => now(),
                    'completed_at' => null,
                    'turned_in_at' => null,
                    'storage_prepared_at' => null,
                ]);
            } else {
                $characterQuest = CharacterQuest::create([
                    'character_uuid' => $character->uuid,
                    'quest_slug' => $questSlug,
                    'status' => CharacterQuest::STATUS_ACTIVE,
                    'progress' => $progress,
                    'accepted_at' => now(),
                ]);
            }

            $this->questStorageService->autolootGrantsToInventory($character, $quest);
            $this->questStorageService->ensureActiveSession($character, $quest);

            $characterQuest = $characterQuest->fresh(['quest.objectives']);
            $characterQuest->progress = $this->progressResolver->refreshSnapshotObjectives($character, $characterQuest);
            if ($this->progressResolver->isQuestComplete($character, $characterQuest)) {
                $characterQuest->status = CharacterQuest::STATUS_COMPLETED;
                $characterQuest->completed_at = now();
            }
            $characterQuest->save();

            $this->eventStore->record(
                'quest.accepted',
                'character_quest',
                $characterQuest->uuid,
                ['quest_slug' => $questSlug, 'character_uuid' => $character->uuid],
                $character->uuid
            );

            return $characterQuest->fresh(['quest.objectives']);
        });
    }

    public function turnIn(Character $character, string $questSlug): CharacterQuest
    {
        return DB::transaction(function () use ($character, $questSlug) {
            $characterQuest = CharacterQuest::where('character_uuid', $character->uuid)
                ->where('quest_slug', $questSlug)
                ->with('quest.objectives')
                ->firstOrFail();

            if ($characterQuest->status === CharacterQuest::STATUS_TURNED_IN) {
                throw new \RuntimeException('Квест уже сдан');
            }

            $this->questStorageService->ensureActiveSession($character, $characterQuest->quest);

            $progress = $this->progressResolver->refreshSnapshotObjectives($character, $characterQuest);
            $characterQuest->update(['progress' => $progress]);

            if (!$this->progressResolver->isQuestComplete($character, $characterQuest)) {
                throw new \RuntimeException('Квест ещё не выполнен');
            }

            $this->questStorageService->executeTurnInExchange($character, $characterQuest->quest, $characterQuest);

            $characterQuest->update([
                'status' => CharacterQuest::STATUS_TURNED_IN,
                'turned_in_at' => now(),
                'completed_at' => $characterQuest->completed_at ?? now(),
            ]);

            $this->characterStatsService->syncLevelFromExperience($character);

            $this->eventStore->record(
                'quest.turned_in',
                'character_quest',
                $characterQuest->uuid,
                ['quest_slug' => $questSlug, 'character_uuid' => $character->uuid],
                $character->uuid
            );

            return $characterQuest->fresh(['quest.objectives']);
        });
    }

    public function handleGameEvent(Character $character, string $eventType, array $payload): void
    {
        $activeQuests = CharacterQuest::where('character_uuid', $character->uuid)
            ->where('status', CharacterQuest::STATUS_ACTIVE)
            ->with('quest.objectives')
            ->get();

        foreach ($activeQuests as $characterQuest) {
            $oldProgress = $characterQuest->progress ?? [];
            $progress = $this->progressResolver->applyEvent($characterQuest, $eventType, $payload);
            $characterQuest->progress = $progress;
            $progress = $this->progressResolver->refreshSnapshotObjectives($character, $characterQuest);

            $wasCompleted = $characterQuest->status === CharacterQuest::STATUS_COMPLETED;
            if ($this->progressResolver->isQuestComplete($character, $characterQuest->fill(['progress' => $progress]))) {
                $characterQuest->status = CharacterQuest::STATUS_COMPLETED;
                $characterQuest->completed_at = $characterQuest->completed_at ?? now();
            }

            $characterQuest->progress = $progress;
            $characterQuest->save();

            if ($progress !== $oldProgress || (!$wasCompleted && $characterQuest->status === CharacterQuest::STATUS_COMPLETED)) {
                $this->eventStore->record(
                    'quest.progress_updated',
                    'character_quest',
                    $characterQuest->uuid,
                    [
                        'quest_slug' => $characterQuest->quest_slug,
                        'character_uuid' => $character->uuid,
                        'progress' => $progress,
                        'completed' => $characterQuest->status === CharacterQuest::STATUS_COMPLETED,
                    ],
                    $character->uuid
                );
            }
        }
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function importFromArray(array $data): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($data, &$created, &$updated) {
            foreach ($data['quests'] ?? [] as $index => $questData) {
                $slug = $questData['slug'];
                $existing = Quest::where('slug', $slug)->exists();

                Quest::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $questData['name'],
                        'description' => $questData['description'] ?? null,
                        'accept_grants' => $questData['accept_grants'] ?? [],
                        'starter_item_template_slug' => $questData['starter_item_template_slug'] ?? null,
                        'giver_character_uuid' => $questData['giver_character_uuid'] ?? null,
                        'prerequisites' => $questData['prerequisites'] ?? [],
                        'rewards' => $questData['rewards'] ?? [],
                        'repeatable' => (bool) ($questData['repeatable'] ?? false),
                        'sort_order' => (int) ($questData['sort_order'] ?? $index),
                        'is_active' => (bool) ($questData['is_active'] ?? true),
                    ]
                );

                QuestObjective::where('quest_slug', $slug)->delete();

                foreach ($questData['objectives'] ?? [] as $objIndex => $objectiveData) {
                    $config = $objectiveData['config'] ?? [];
                    if ($objectiveData['recipe_slug'] ?? null) {
                        $config['recipe_slug'] = $objectiveData['recipe_slug'];
                    }
                    if ($objectiveData['template_slug'] ?? null) {
                        $config['template_slug'] = $objectiveData['template_slug'];
                    }
                    if ($objectiveData['stage'] ?? null) {
                        $config['stage'] = $objectiveData['stage'];
                    }

                    QuestObjective::create([
                        'quest_slug' => $slug,
                        'objective_key' => $objectiveData['key'],
                        'type' => $objectiveData['type'],
                        'config' => $config,
                        'required_count' => (int) ($objectiveData['count'] ?? 1),
                        'sort_order' => (int) ($objectiveData['sort_order'] ?? $objIndex),
                    ]);
                }

                $existing ? $updated++ : $created++;
            }
        });

        return compact('created', 'updated');
    }

    private function canAccept(Character $character, Quest $quest, Collection $characterQuests): bool
    {
        $existing = $characterQuests->get($quest->slug);
        if ($existing && $existing->status !== CharacterQuest::STATUS_TURNED_IN) {
            return false;
        }

        if ($existing && $existing->status === CharacterQuest::STATUS_TURNED_IN && !$quest->repeatable) {
            return false;
        }

        foreach ($quest->prerequisites ?? [] as $prerequisiteSlug) {
            $prerequisite = $characterQuests->get($prerequisiteSlug);
            if (!$prerequisite || $prerequisite->status !== CharacterQuest::STATUS_TURNED_IN) {
                return false;
            }
        }

        if ($quest->starter_item_template_slug
            && !$this->questStorageService->hasStarterItemInInventory($character, $quest->starter_item_template_slug)) {
            return false;
        }

        return true;
    }

    private function formatQuest(Character $character, Quest $quest, ?CharacterQuest $state): array
    {
        $progress = $state?->progress ?? [];
        if ($state && in_array($state->status, [CharacterQuest::STATUS_ACTIVE, CharacterQuest::STATUS_COMPLETED], true)) {
            $progress = $this->progressResolver->refreshSnapshotObjectives($character, $state);
        }

        return [
            'slug' => $quest->slug,
            'name' => $quest->name,
            'description' => $quest->description,
            'accept_grants' => $quest->accept_grants ?? [],
            'starter_item_template_slug' => $quest->starter_item_template_slug,
            'giver_character_uuid' => $quest->giver_character_uuid,
            'prerequisites' => $quest->prerequisites ?? [],
            'rewards' => $quest->rewards ?? [],
            'repeatable' => $quest->repeatable,
            'status' => $state?->status,
            'ready_to_turn_in' => $state?->status === CharacterQuest::STATUS_COMPLETED,
            'progress' => $progress,
            'objectives' => $quest->objectives->map(fn (QuestObjective $objective) => [
                'key' => $objective->objective_key,
                'type' => $objective->type,
                'config' => $objective->config,
                'required_count' => $objective->required_count,
                'current' => (int) ($progress[$objective->objective_key] ?? 0),
                'label' => $this->objectiveLabel($objective),
            ])->values()->all(),
        ];
    }

    private function objectiveLabel(QuestObjective $objective): string
    {
        if (in_array($objective->type, ['have_item', 'collect_resource'], true)) {
            $slug = $objective->config['template_slug'] ?? null;
            if ($slug) {
                $template = ItemTemplate::where('slug', $slug)->first();

                return $template?->name ?? $slug;
            }
        }

        if ($objective->type === 'craft_item' || $objective->type === 'craft_resource') {
            $recipeSlug = $objective->config['recipe_slug'] ?? null;

            return $recipeSlug ?? $objective->type;
        }

        return $objective->type;
    }
}
