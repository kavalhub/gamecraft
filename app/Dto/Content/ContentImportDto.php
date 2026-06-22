<?php

declare(strict_types=1);

namespace App\Dto\Content;

class ContentImportDto
{
    /**
     * @param TemplateDto[] $templates
     * @param RecipeDto[] $recipes
     * @param NpcDto[] $npcs
     * @param array $shopLots
     */
    public function __construct(
        public readonly string $version,
        public readonly array $templates,
        public readonly array $recipes,
        public readonly array $npcs,
        public readonly array $shopLots,
    ) {}

    public static function fromArray(array $data): self
    {
        $errors = [];

        if (empty($data['version']) || !is_string($data['version'])) {
            $errors[] = 'version is required';
        }

        if (!isset($data['templates']) || !is_array($data['templates'])) {
            $errors[] = 'templates must be array';
        }

        if (!isset($data['recipes']) || !is_array($data['recipes'])) {
            $errors[] = 'recipes must be array';
        }

        $npcs = $data['npcs'] ?? [];
        if (!is_array($npcs)) {
            $errors[] = 'npcs must be array';
        }

        $shopLots = $data['shop_lots'] ?? [];
        if (!is_array($shopLots)) {
            $errors[] = 'shop_lots must be array';
        }

        if ($errors) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $templates = [];
        $templateSlugs = [];
        foreach ($data['templates'] as $templateData) {
            $template = TemplateDto::fromArray($templateData);
            if (in_array($template->slug, $templateSlugs, true)) {
                throw new \InvalidArgumentException("Duplicate template slug: {$template->slug}");
            }
            $templateSlugs[] = $template->slug;
            $templates[] = $template;
        }

        $recipes = [];
        $recipeSlugs = [];
        foreach ($data['recipes'] as $recipeData) {
            $recipe = RecipeDto::fromArray($recipeData);
            if (in_array($recipe->slug, $recipeSlugs, true)) {
                throw new \InvalidArgumentException("Duplicate recipe slug: {$recipe->slug}");
            }
            $recipeSlugs[] = $recipe->slug;
            $recipes[] = $recipe;
        }

        $npcDtos = [];
        $npcSlugs = [];
        foreach ($npcs as $npcData) {
            $npc = NpcDto::fromArray($npcData);
            if (in_array($npc->slug, $npcSlugs, true)) {
                throw new \InvalidArgumentException("Duplicate npc slug: {$npc->slug}");
            }
            $npcSlugs[] = $npc->slug;
            $npcDtos[] = $npc;
        }

        // Валидация shop_lots
        foreach ($shopLots as $index => $shopLot) {
            if (empty($shopLot['npc_slug']) || !is_string($shopLot['npc_slug'])) {
                throw new \InvalidArgumentException("shop_lots[{$index}].npc_slug is required");
            }
            if (empty($shopLot['template_slug']) || !is_string($shopLot['template_slug'])) {
                throw new \InvalidArgumentException("shop_lots[{$index}].template_slug is required");
            }
            if (!isset($shopLot['quantity']) || !is_int($shopLot['quantity']) || $shopLot['quantity'] < 1) {
                throw new \InvalidArgumentException("shop_lots[{$index}].quantity must be positive integer");
            }
            if (!isset($shopLot['price']) || !is_int($shopLot['price']) || $shopLot['price'] < 1) {
                throw new \InvalidArgumentException("shop_lots[{$index}].price must be positive integer");
            }
            if (!in_array($shopLot['template_slug'], $templateSlugs, true)) {
                throw new \InvalidArgumentException(
                    "shop_lots[{$index}] references unknown template slug: {$shopLot['template_slug']}"
                );
            }
            if (!in_array($shopLot['npc_slug'], $npcSlugs, true)) {
                throw new \InvalidArgumentException(
                    "shop_lots[{$index}] references unknown npc slug: {$shopLot['npc_slug']}"
                );
            }
        }

        // Проверяем, что все ссылки на шаблоны существуют
        foreach ($recipes as $recipe) {
            if (!in_array($recipe->resultTemplateSlug, $templateSlugs, true)) {
                throw new \InvalidArgumentException(
                    "Recipe [{$recipe->slug}] references unknown template slug: {$recipe->resultTemplateSlug}"
                );
            }
            foreach ($recipe->components as $component) {
                if (!in_array($component->templateSlug, $templateSlugs, true)) {
                    throw new \InvalidArgumentException(
                        "Recipe [{$recipe->slug}] component references unknown template slug: {$component->templateSlug}"
                    );
                }
            }
        }

        return new self(
            version: $data['version'],
            templates: $templates,
            recipes: $recipes,
            npcs: $npcDtos,
            shopLots: $shopLots,
        );
    }
}
