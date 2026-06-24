<?php

declare(strict_types=1);

namespace App\Dto\Content;

class RecipeDto
{
    /**
     * @param RecipeComponentDto[] $components
     * @param array[] $disassembleFormulas
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $resultTemplateSlug,
        public readonly int $resultQuantity,
        public readonly array $components,
        public readonly array $disassembleFormulas = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $errors = [];
        $slug = $data['slug'] ?? '(unknown)';

        if (empty($data['slug']) || !is_string($data['slug'])) {
            $errors[] = 'recipe.slug is required';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['slug'])) {
            $errors[] = "recipe.slug '{$data['slug']}' must be lowercase alphanumeric with underscores";
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = "recipe[{$slug}].name is required";
        }

        if (empty($data['result']) || !is_array($data['result'])) {
            $errors[] = "recipe[{$slug}].result is required";
        } else {
            if (empty($data['result']['template_slug']) || !is_string($data['result']['template_slug'])) {
                $errors[] = "recipe[{$slug}].result.template_slug is required";
            }
            if (!isset($data['result']['quantity']) || !is_int($data['result']['quantity']) || $data['result']['quantity'] < 1) {
                $errors[] = "recipe[{$slug}].result.quantity must be positive integer";
            }
        }

        if (!isset($data['components']) || !is_array($data['components']) || count($data['components']) === 0) {
            $errors[] = "recipe[{$slug}].components must be non-empty array";
        }

        if ($errors) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $components = [];
        foreach ($data['components'] as $index => $componentData) {
            $components[] = RecipeComponentDto::fromArray($componentData, $slug, $index);
        }

        $disassembleFormulas = $data['disassemble_formulas'] ?? [];
        if (!is_array($disassembleFormulas)) {
            throw new \InvalidArgumentException("recipe[{$slug}].disassemble_formulas must be array");
        }

        return new self(
            slug: $data['slug'],
            name: $data['name'],
            description: $data['description'] ?? null,
            resultTemplateSlug: $data['result']['template_slug'],
            resultQuantity: $data['result']['quantity'],
            components: $components,
            disassembleFormulas: $disassembleFormulas,
        );
    }
}
