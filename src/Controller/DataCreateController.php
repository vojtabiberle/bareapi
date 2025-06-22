<?php

namespace Bareapi\Controller;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepository;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataCreateController
{
    public function __construct(
        private MetaObjectRepository $repo,
        private Validator $validator,
        private string $kernelProjectDir
    ) {
    }

    #[Route('/data/{type}', name: 'data_create', methods: ['POST'])]
    public function __invoke(string $type, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent());
        $schemaFile = sprintf('%s/config/schemas/%s.json', $this->kernelProjectDir, $type);

        if (!file_exists($schemaFile)) {
            return new JsonResponse(['error' => 'Unknown type'], 400);
        }

        $schema = json_decode((string) file_get_contents($schemaFile));
        $this->validator->validate($payload, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if (!$this->validator->isValid()) {
            $errors = array_map(
                fn(array $e) => sprintf('[%s] %s', $e['property'], $e['message']),
                $this->validator->getErrors()
            );
            return new JsonResponse(['errors' => $errors], 422);
        }

        $obj = new MetaObject($type, $schema->version ?? '1.0', (array) $payload);
        $this->repo->save($obj);

        return new JsonResponse($obj, 201);
    }
}
