<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Resources;
use App\Models\SlugAction;
use Illuminate\Support\Collection;

class CraftingActionResolver
{
    /**
     * @return Collection<int, array{recipe_slug: string, target_slot: string, action_slug: string, action_label: string, formula_type: string}>
     */
    public function craftActionsForItem(Item $item): Collection
    {
        if ($item->stage === 'blueprint' && $item->recipe_slug) {
            $recipe = Recipe::where('slug', $item->recipe_slug)->where('type', 'blueprint')->first();
            if (!$recipe) {
                return collect();
            }

            $formula = $recipe->craftFormulas()->with('action')->first();
            if (!$formula || $formula->formula === []) {
                return collect();
            }

            return collect([$this->formatAction($recipe, $formula, 'center')]);
        }

        return collect();
    }

    /**
     * @return Collection<int, array{recipe_slug: string, target_slot: string, action_slug: string, action_label: string, formula_type: string}>
     */
    public function craftActionsForResource(Resources $resource): Collection
    {
        $actions = collect();

        foreach ($this->resourceCraftRecipesForInput($resource->template_slug) as $recipe) {
            $formula = $recipe->craftFormulas()->with('action')->firstOrFail();
            $actions->push($this->formatAction($recipe, $formula, 'center'));
            $actions->push($this->formatAction($recipe, $formula, 'material'));
        }

        return $actions->unique(fn (array $a) => $a['recipe_slug'] . ':' . $a['target_slot']);
    }

    /**
     * @return Collection<int, array{recipe_slug: string, target_slot: string, action_slug: string, action_label: string, formula_type: string}>
     */
    public function disassembleActionsForItem(Item $item): Collection
    {
        if ($item->stage !== 'item' || !$item->recipe_slug) {
            return collect();
        }

        $recipe = Recipe::where('slug', $item->recipe_slug)->first();
        if (!$recipe) {
            return collect();
        }

        $formula = $recipe->disassembleFormulas()->with('action')->first();
        if (!$formula || $formula->formula === []) {
            return collect();
        }

        return collect([$this->formatAction($recipe, $formula, 'center')]);
    }

    /**
     * @return Collection<int, array{recipe_slug: string, target_slot: string, action_slug: string, action_label: string, formula_type: string}>
     */
    public function disassembleActionsForResource(Resources $resource): Collection
    {
        $actions = collect();

        foreach (Recipe::where('type', 'resource')->get() as $recipe) {
            if ($this->resolveResultTemplateSlug($recipe) !== $resource->template_slug) {
                continue;
            }

            $formula = $recipe->disassembleFormulas()->with('action')->first();
            if (!$formula || $formula->formula === []) {
                continue;
            }

            $actions->push($this->formatAction($recipe, $formula, 'center'));
        }

        return $actions;
    }

    public function isAllowedInCraftCenter(Item|Resources $occupant): bool
    {
        if ($occupant instanceof Item) {
            return $this->craftActionsForItem($occupant)->isNotEmpty();
        }

        return $this->resourceCraftRecipesForInput($occupant->template_slug)->isNotEmpty();
    }

    public function isAllowedInDisassembleCenter(Item|Resources $occupant): bool
    {
        if ($occupant instanceof Item) {
            return $this->disassembleActionsForItem($occupant)->isNotEmpty();
        }

        return $this->disassembleActionsForResource($occupant)->isNotEmpty();
    }

    public function isAllowedInCraftMaterial(Resources $resource): bool
    {
        if ($this->resourceCraftRecipesForInput($resource->template_slug)->isNotEmpty()) {
            return true;
        }

        return $this->isBlueprintCraftIngredient($resource->template_slug);
    }

    private function isBlueprintCraftIngredient(string $templateSlug): bool
    {
        foreach (Recipe::where('type', 'blueprint')->get() as $recipe) {
            $formula = $recipe->craftFormulas()->first();
            if ($formula && array_key_exists($templateSlug, $formula->formula)) {
                return true;
            }
        }

        return false;
    }

    public function actionLabelForFormula(Formula $formula): string
    {
        if ($formula->action_slug) {
            $action = SlugAction::find($formula->action_slug);

            return $action?->label ?? $this->defaultLabelForType($formula->type);
        }

        return $this->defaultLabelForType($formula->type);
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function resourceCraftRecipesForInput(string $templateSlug): Collection
    {
        return Recipe::where('type', 'resource')->get()->filter(function (Recipe $recipe) use ($templateSlug) {
            $formula = $recipe->craftFormulas()->first();
            if (!$formula || $formula->formula === []) {
                return false;
            }

            return array_key_exists($templateSlug, $formula->formula);
        });
    }

    /**
     * @return array{recipe_slug: string, target_slot: string, action_slug: string, action_label: string, formula_type: string}
     */
    private function formatAction(Recipe $recipe, Formula $formula, string $targetSlot): array
    {
        $actionSlug = $formula->action_slug ?? ($formula->type === 'disassemble' ? 'disassemble' : 'create');

        return [
            'recipe_slug' => $recipe->slug,
            'target_slot' => $targetSlot,
            'action_slug' => $actionSlug,
            'action_label' => $this->actionLabelForFormula($formula),
            'formula_type' => $formula->type,
        ];
    }

    private function defaultLabelForType(string $type): string
    {
        return $type === 'disassemble' ? 'Разобрать' : 'Создать';
    }

    private function resolveResultTemplateSlug(Recipe $recipe): ?string
    {
        return app(CraftingService::class)->resolveResultTemplateSlug($recipe);
    }
}
