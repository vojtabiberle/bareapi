<?php

declare(strict_types=1);

namespace Bareapi\Response;

use Bareapi\DTO\MetaObjectResponse;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class JsonApiSerializer
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    private const PREFIX = '/api/v1/repository';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param MetaObjectResponse|MetaObjectResponse[] $data
     */
    public function success(MetaObjectResponse|array $data, int $statusCode = 200): JsonResponse
    {
        $baseUrl = $this->getBaseUrl();
        $payload = $this->serialize($data, $baseUrl);

        return new JsonResponse($payload, $statusCode, [
            'Content-Type' => self::CONTENT_TYPE,
        ]);
    }

    public function created(MetaObjectResponse $data): JsonResponse
    {
        return $this->success($data, 201);
    }

    /**
     * Success response with enriched relationships.
     *
     * @param array<string, array<string, mixed>> $enrichedRelationships
     */
    public function successWithRelationships(
        MetaObjectResponse $data,
        array $enrichedRelationships,
        int $statusCode = 200,
    ): JsonResponse {
        $baseUrl = $this->getBaseUrl();
        $serialized = $this->serializeOne($data, $baseUrl);

        // Merge enriched relationships
        if (! empty($enrichedRelationships)) {
            $serialized['enrichedRelationships'] = $enrichedRelationships;
        }

        return new JsonResponse([
            'data' => $serialized,
        ], $statusCode, [
            'Content-Type' => self::CONTENT_TYPE,
        ]);
    }

    /**
     * @param MetaObjectResponse|MetaObjectResponse[] $data
     * @return array{data: array<string, mixed>|array<int, array<string, mixed>>}
     */
    private function serialize(MetaObjectResponse|array $data, string $baseUrl): array
    {
        if (is_array($data)) {
            return [
                'data' => array_map(fn (MetaObjectResponse $item) => $this->serializeOne($item, $baseUrl), $data),
            ];
        }

        return [
            'data' => $this->serializeOne($data, $baseUrl),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(MetaObjectResponse $response, string $baseUrl): array
    {
        $selfUrl = sprintf(
            '%s%s/%s/%s',
            $baseUrl,
            self::PREFIX,
            $response->objectType,
            $response->uuid
        );

        $data = [
            'type' => $response->objectType,
            'id' => $response->uuid,
            'attributes' => [
                'schemaVersion' => $response->schemaVersion,
                'branch' => $response->branch,
                'name' => $response->name,
                'projectId' => $response->projectId,
                'organizationId' => $response->organizationId,
                'lastUpdated' => $response->lastUpdated->format(DateTimeInterface::RFC3339),
                'createdAt' => $response->createdAt->format(DateTimeInterface::RFC3339),
                'revision' => $response->revision,
                'data' => $response->data,
                'revisionCreatedAt' => $response->revisionCreatedAt->format(DateTimeInterface::RFC3339),
            ],
            'links' => [
                'self' => $selfUrl,
            ],
            'relationships' => [
                'schema' => [
                    'data' => [
                        'type' => 'schemas',
                        'id' => sprintf('%s-%s', $response->objectType, $response->schemaVersion),
                    ],
                ],
                'revisions' => [
                    'data' => [
                        'type' => 'revisions',
                        'id' => (string) $response->revision,
                    ],
                ],
            ],
        ];

        if ($response->projectId !== null) {
            $data['relationships']['project'] = [
                'data' => [
                    'type' => 'projects',
                    'id' => (string) $response->projectId,
                ],
            ];
        }

        return $data;
    }

    private function getBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        return sprintf(
            '%s://%s',
            $request->getScheme(),
            $request->getHttpHost()
        );
    }
}
