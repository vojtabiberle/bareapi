<?php

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Controller\ControllerUtil;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataUpdateController
{
    public function __construct(
        private MetaObjectRepository $repo,
        private Validator $validator,
        private string $kernelProjectDir
    ) {}

    #[Route('/data/{type}/{id}', name: 'data_update', methods: ['PUT'])]
    public function __invoke(string $type, string $id, Request $request): JsonResponse
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

        $obj = $this->repo->find($id);
        if (!$obj || $obj->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $obj->setData(ControllerUtil::toStringKeyedArray($payload));
        $this->repo->save($obj);

        return new JsonResponse($obj);
    }
}
