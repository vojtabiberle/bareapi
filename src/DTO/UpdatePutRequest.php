<?php

declare(strict_types=1);

namespace Bareapi\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdatePutRequest
{
    /**
     * @param array<string, mixed> $data Complete replacement data
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name,
        #[Assert\NotBlank]
        public readonly array $data,
        public readonly ?string $schemaVersion = null,
        public readonly ?string $branch = null,
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
            name: isset($input['name']) && is_string($input['name']) ? $input['name'] : '',
            data: $data,
            schemaVersion: isset($input['schemaVersion']) && is_string($input['schemaVersion']) ? $input['schemaVersion'] : null,
            branch: isset($input['branch']) && is_string($input['branch']) ? $input['branch'] : null,
        );
    }
}
