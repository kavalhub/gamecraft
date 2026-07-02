<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;

class SlotFitService
{
    public function __construct(
        private SpecialSlotService $specialSlotService,
        private CraftingActionResolver $craftingActionResolver,
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private QuestStorageService $questStorageService,
    ) {}

    public function resolveOccupantSlotType(Item|Resources $occupant): string
    {
        if ($occupant instanceof Item) {
            $template = ItemTemplate::where('slug', $occupant->template_slug)->first();

            return (string) ($occupant->slot_type ?? $template?->slot_type ?? '');
        }

        $template = ItemTemplate::where('slug', $occupant->template_slug)->first();

        return (string) ($occupant->slot_type ?? $template?->slot_type ?? $occupant->template_slug);
    }

    /**
     * @param  array{kind: string, cell: Slot|TemporarySlot}  $target
     */
    public function occupantFitsTarget(Character $character, Item|Resources $occupant, array $target): bool
    {
        if ($target['kind'] === 'temporary') {
            /** @var TemporarySlot $tempSlot */
            $tempSlot = $target['cell'];

            return $this->occupantFitsTemporarySlot($character, $occupant, $tempSlot);
        }

        /** @var Slot $slot */
        $slot = $target['cell'];

        return $this->occupantFitsRegularSlot($occupant, $slot);
    }

    /**
     * @param  array{kind: string, cell: Slot|TemporarySlot}  $from
     * @param  array{kind: string, cell: Slot|TemporarySlot}  $to
     */
    public function isManualMoveBlocked(
        Character $character,
        Item|Resources $occupant,
        array $from,
        array $to,
    ): bool {
        if ($occupant instanceof Resources
            && in_array($occupant->template_slug, ['gold', 'experience'], true)) {
            return true;
        }

        if ($to['kind'] === 'temporary') {
            /** @var TemporarySlot $toTemp */
            $toTemp = $to['cell'];
            $storage = Storage::where('uuid', $toTemp->storage_uuid)->first();
            if ($storage?->storage_type === 'quest'
                && $this->questStorageService->slotRole($toTemp) === 'quest_grant') {
                return true;
            }

            if ($storage?->storage_type === 'disassemble'
                && $this->disassembleStationService->isOutputSlot($toTemp)) {
                return true;
            }

            if ($storage?->storage_type === 'encounter_loot') {
                return true;
            }
        }

        if ($from['kind'] === 'regular' && $to['kind'] === 'regular') {
            /** @var Slot $fromSlot */
            $fromSlot = $from['cell'];
            /** @var Slot $toSlot */
            $toSlot = $to['cell'];
            if ($this->isStationBackingSlot($fromSlot) || $this->isStationBackingSlot($toSlot)) {
                return true;
            }
        }

        return false;
    }

    public function occupantFitsRegularSlot(Item|Resources $occupant, Slot $slot): bool
    {
        if ($this->isStationBackingSlot($slot)) {
            return false;
        }

        if ($slot->slot_type !== null && str_starts_with($slot->slot_type, 'equipment_')) {
            return $occupant instanceof Item
                && $this->resolveOccupantSlotType($occupant) === $slot->slot_type;
        }

        if ($occupant instanceof Item) {
            return $this->occupantFitsTypedSlot($this->resolveOccupantSlotType($occupant), $slot->slot_type);
        }

        return $this->occupantFitsTypedSlot($this->resolveOccupantSlotType($occupant), $slot->slot_type);
    }

    public function occupantFitsTemporarySlot(Character $character, Item|Resources $occupant, TemporarySlot $tempSlot): bool
    {
        $storage = Storage::where('uuid', $tempSlot->storage_uuid)->first();
        if (!$storage) {
            return false;
        }

        if ($storage->storage_type === 'disassemble') {
            if ($this->disassembleStationService->isOutputSlot($tempSlot)) {
                return false;
            }

            if ($tempSlot->slot_index !== DisassembleStationService::CENTER_SLOT_INDEX) {
                return false;
            }

            return $this->occupantFitsCenterFormulaSlot($occupant, 'disassemble');
        }

        if ($storage->storage_type === 'craft') {
            if ($tempSlot->slot_index === CraftStationService::CENTER_SLOT_INDEX) {
                return $this->occupantFitsCenterFormulaSlot($occupant, 'craft');
            }

            if ($occupant instanceof Item) {
                return false;
            }

            return $this->occupantFitsTypedSlot(
                $this->resolveOccupantSlotType($occupant),
                $tempSlot->slot_type
            );
        }

        if ($storage->storage_type === 'quest') {
            $role = $this->questStorageService->slotRole($tempSlot);
            if ($role === 'quest_grant') {
                return false;
            }

            return $this->occupantFitsTypedSlot(
                $this->resolveOccupantSlotType($occupant),
                $tempSlot->slot_type
            );
        }

        if ($storage->storage_type === 'trade') {
            return true;
        }

        if ($storage->storage_type === 'encounter_loot') {
            return false;
        }

        return $this->occupantFitsTypedSlot(
            $this->resolveOccupantSlotType($occupant),
            $tempSlot->slot_type
        );
    }

    private function occupantFitsCenterFormulaSlot(Item|Resources $occupant, string $station): bool
    {
        if ($station === 'craft') {
            return $this->craftingActionResolver->isAllowedInCraftCenter($occupant);
        }

        return $this->craftingActionResolver->isAllowedInDisassembleCenter($occupant);
    }

    private function occupantFitsTypedSlot(string $occupantSlotType, ?string $targetSlotType): bool
    {
        if ($targetSlotType === null) {
            return $occupantSlotType !== '';
        }

        if ($targetSlotType === 'disabled') {
            return false;
        }

        if ($occupantSlotType === '') {
            return false;
        }

        $template = ItemTemplate::where('slug', $occupantSlotType)->first()
            ?? ItemTemplate::where('slot_type', $occupantSlotType)->first();

        if ($template) {
            return $this->specialSlotService->resourceMatchesSlotType($template, $targetSlotType)
                || $occupantSlotType === $targetSlotType;
        }

        return $occupantSlotType === $targetSlotType;
    }

    private function isStationBackingSlot(Slot $slot): bool
    {
        if ($slot->slot_type === null) {
            return false;
        }

        return str_starts_with($slot->slot_type, 'craft_')
            || str_starts_with($slot->slot_type, 'disassemble_')
            || str_starts_with($slot->slot_type, 'encounter_loot_');
    }

    /**
     * @return list<string>
     */
    public function ingredientSlotTypesForCraftCenter(Character $character): array
    {
        $centerItem = $this->craftStationService->getCenterItem($character);
        if ($centerItem?->stage === 'blueprint' && $centerItem->recipe_slug) {
            $recipe = Recipe::where('slug', $centerItem->recipe_slug)->where('type', 'blueprint')->first();
            $formula = $recipe?->craftFormulas()->first();

            return $this->slotTypesFromFormula($formula);
        }

        $centerResource = $this->craftStationService->getCenterResource($character);
        if (!$centerResource) {
            return [];
        }

        $recipe = Recipe::where('slug', $centerResource->template_slug)->where('type', 'resource')->first();
        $formula = $recipe?->craftFormulas()->first();

        return $this->slotTypesFromFormula($formula);
    }

    /**
     * @return list<string>
     */
    private function slotTypesFromFormula(?Formula $formula): array
    {
        if (!$formula || $formula->formula === []) {
            return [];
        }

        $types = [];
        foreach (array_keys($formula->formula) as $templateSlug) {
            $template = ItemTemplate::where('slug', (string) $templateSlug)->first();
            if (!$template?->slot_type) {
                continue;
            }
            $types[] = $template->slot_type;
        }

        return $types;
    }
}
