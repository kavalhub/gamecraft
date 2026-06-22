<?php

declare(strict_types=1);

namespace App\Dto\Content;

class RecipeComponentDto
{
    public function __construct(
        public readonly string $templateSlug,
        public readonly int $quantity,
    ) {}

    public static function fromArray(array $data, string $recipeSlug): self
    {
        $errors = [];

        if (empty($data['template_slug']) || !is_string($data['template_slug'])) {
            $errors[] = "recipe[{$recipeSlug}].components[].template_slug is required";
        }

        if (!isset($data['quantity']) || !is_int($data['quantity']) || $data['quantity'] < 1) {
            $errors[] = "recipe[{$recipeSlug}].components[].quantity must be positive integer";
        }

        if ($errors) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return new self(
            templateSlug: $data['template_slug'],
            quantity: $data['quantity'],
        );
    }
}
