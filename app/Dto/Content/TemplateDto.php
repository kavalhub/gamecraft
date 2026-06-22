<?php

declare(strict_types=1);

namespace App\Dto\Content;

class TemplateDto
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $type,
        public readonly string $icon,
        public readonly bool $isStackable,
        public readonly int $maxStack,
        public readonly ?string $description,
        public readonly ?array $disassembleData,
        public readonly ?array $stats,
    ) {}

    public static function fromArray(array $data): self
    {
        $errors = [];

        if (empty($data['slug']) || !is_string($data['slug'])) {
            $errors[] = 'template.slug is required and must be string';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['slug'])) {
            $errors[] = "template.slug '{$data['slug']}' must be lowercase alphanumeric with underscores";
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = "template[{$data['slug']}].name is required";
        }

        $allowedTypes = ['material', 'equipment', 'consumable', 'recipe'];
        if (!in_array($data['type'] ?? null, $allowedTypes, true)) {
            $errors[] = "template[{$data['slug']}].type must be one of: " . implode(', ', $allowedTypes);
        }

        if (!isset($data['icon']) || !is_string($data['icon'])) {
            $errors[] = "template[{$data['slug']}].icon is required";
        }

        if ($errors) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'],
            icon: $data['icon'],
            isStackable: (bool)($data['is_stackable'] ?? true),
            maxStack: (int)($data['max_stack'] ?? 999),
            description: $data['description'] ?? null,
            disassembleData: $data['disassemble_data'] ?? null,
            stats: $data['stats'] ?? null,
        );
    }
}
