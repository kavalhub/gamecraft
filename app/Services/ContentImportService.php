<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\Content\ContentImportDto;
use App\Dto\Content\NpcDto;
use App\Dto\Content\RecipeDto;
use App\Dto\Content\TemplateDto;
use App\Models\AuctionLot;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\RecipeComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ContentImportService
{
    public function import(ContentImportDto $dto): array
    {
        $report = [
            'version' => $dto->version,
            'templates' => ['created' => 0, 'updated' => 0, 'total' => count($dto->templates)],
            'recipes' => ['created' => 0, 'updated' => 0, 'total' => count($dto->recipes)],
            'npcs' => ['created' => 0, 'updated' => 0, 'total' => count($dto->npcs)],
            'shop_lots' => ['created' => 0, 'updated' => 0, 'total' => count($dto->shopLots)],
        ];

        DB::transaction(function () use ($dto, &$report) {
            foreach ($dto->templates as $templateDto) {
                $this->importTemplate($templateDto, $report);
            }

            foreach ($dto->npcs as $npcDto) {
                $this->importNpc($npcDto, $report);
            }

            foreach ($dto->recipes as $recipeDto) {
                $this->importRecipe($recipeDto, $report);
            }

            foreach ($dto->shopLots as $shopLot) {
                $this->importShopLot($shopLot, $report);
            }
        });

        return $report;
    }

    private function importTemplate(TemplateDto $dto, array &$report): void
    {
        $existing = ItemTemplate::where('slug', $dto->slug)->first();
        $isNew = $existing === null;

        ItemTemplate::updateOrCreate(
            ['slug' => $dto->slug],
            [
                'name' => $dto->name,
                'type' => $dto->type,
                'icon' => $dto->icon,
                'is_stackable' => $dto->isStackable,
                'max_stack' => $dto->maxStack,
                'description' => $dto->description,
                'disassemble_data' => $dto->disassembleData,
                'stats' => $dto->stats,
            ]
        );

        if ($isNew) {
            $report['templates']['created']++;
        } else {
            $report['templates']['updated']++;
        }
    }

    private function importNpc(NpcDto $dto, array &$report): void
    {
        $existing = User::where('email', $dto->email)->first();
        $isNew = $existing === null;

        User::updateOrCreate(
            ['email' => $dto->email],
            [
                'name' => $dto->name,
                'password' => Hash::make('npc_' . $dto->slug),
                'gold' => 999999,
            ]
        );

        if ($isNew) {
            $report['npcs']['created']++;
        } else {
            $report['npcs']['updated']++;
        }
    }

    private function importRecipe(RecipeDto $dto, array &$report): void
    {
        $resultTemplate = ItemTemplate::where('slug', $dto->resultTemplateSlug)->firstOrFail();

        $existing = Recipe::where('slug', $dto->slug)->first();
        $isNew = $existing === null;

        $recipe = Recipe::updateOrCreate(
            ['slug' => $dto->slug],
            [
                'name' => $dto->name,
                'description' => $dto->description,
                'result_template_id' => $resultTemplate->id,
                'result_quantity' => $dto->resultQuantity,
            ]
        );

        RecipeComponent::where('recipe_id', $recipe->id)->delete();

        foreach ($dto->components as $componentDto) {
            $componentTemplate = ItemTemplate::where('slug', $componentDto->templateSlug)->firstOrFail();
            RecipeComponent::create([
                'recipe_id' => $recipe->id,
                'template_id' => $componentTemplate->id,
                'quantity' => $componentDto->quantity,
            ]);
        }

        if ($isNew) {
            $report['recipes']['created']++;
        } else {
            $report['recipes']['updated']++;
        }
    }

    private function importShopLot(array $shopLot, array &$report): void
    {
        $template = ItemTemplate::where('slug', $shopLot['template_slug'])->firstOrFail();
        $npc = User::where('email', $this->getNpcEmail($shopLot['npc_slug']))->firstOrFail();

        $existing = AuctionLot::where('template_id', $template->id)
            ->where('is_infinite', true)
            ->first();

        $isNew = $existing === null;

        AuctionLot::updateOrCreate(
            ['template_id' => $template->id, 'is_infinite' => true],
            [
                'seller_id' => $npc->id,
                'quantity' => $shopLot['quantity'],
                'price' => $shopLot['price'],
                'status' => 'active',
                'is_infinite' => true,
                'commission_percent' => 0,
            ]
        );

        if ($isNew) {
            $report['shop_lots']['created']++;
        } else {
            $report['shop_lots']['updated']++;
        }
    }

    private function getNpcEmail(string $npcSlug): string
    {
        // Простая маппинг - в будущем можно хранить в БД
        $mapping = [
            'wulfric_goldsmyth' => 'wulfric@game.npc',
        ];

        return $mapping[$npcSlug] ?? throw new \RuntimeException("Unknown NPC slug: {$npcSlug}");
    }
}
