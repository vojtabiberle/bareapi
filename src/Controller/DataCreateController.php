<?php

namespace Bareapi\Controller;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Controller\ControllerUtil;
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
    ) {}

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
                /**
                 * @param mixed $e
                 */
                fn($e) => is_array($e)
                    ? sprintf(
                        '[%s] %s',
                        array_key_exists('property', $e) ? ControllerUtil::toStringSafe($e['property']) : '',
                        array_key_exists('message', $e) ? ControllerUtil::toStringSafe($e['message']) : ''
                    )
                    : '',
                $this->validator->getErrors()
            );
            return new JsonResponse(['errors' => $errors], 422);
        }

        $obj = new MetaObject(
            $type,
            (is_object($schema) && isset($schema->version)) ? ControllerUtil::toStringSafe($schema->version) : '1.0',
            ControllerUtil::toStringKeyedArray((array)$payload)
        );
        $this->repo->save($obj);

        return new JsonResponse($obj, 201);
    }
}
