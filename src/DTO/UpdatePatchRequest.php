<?php

declare(strict_types=1);

namespace Bareapi\DTO;

class UpdatePatchRequest
{
    /**
     * @param array<string, mixed> $data Partial data to merge with existing
     */
    public function __construct(
        public readonly array $data,
        public readonly ?string $schemaVersion = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        /** @var array<string, mixed> $data */
        $data = isset($input['data']) && is_array($input['data']) ? $input['data'] : [];

        return new self(
            data: $data,
            schemaVersion: isset($input['schemaVersion']) && is_string($input['schemaVersion']) ? $input['schemaVersion'] : null,
        );
    }
}
