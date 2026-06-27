<?php

declare(strict_types=1);

namespace App\Dto\Content;

class NpcDto
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $description,
    ) {}

    public static function fromArray(array $data): self
    {
        $errors = [];
        $slug = $data['slug'] ?? '(unknown)';

        if (empty($data['slug']) || !is_string($data['slug'])) {
            $errors[] = 'npc.slug is required';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['slug'])) {
            $errors[] = "npc.slug '{$data['slug']}' must be lowercase alphanumeric with underscores";
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = "npc[{$slug}].name is required";
        }

        if (empty($data['email']) || !is_string($data['email'])) {
            $errors[] = "npc[{$slug}].email is required";
        }

        if ($errors) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        return new self(
            slug: $data['slug'],
            name: $data['name'],
            email: $data['email'],
            description: $data['description'] ?? null,
        );
    }
}
