<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaRef;
use Bareapi\Exception\DeleteRestrictedException;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Repository\MetaRefRepositoryInterface;
use Bareapi\Schema\OnDeleteBehavior;

/**
 * Plans and executes delete operations with referential integrity.
 *
 * Implements the onDelete semantics from x-metastore.refersTo:
 * - restrict: Block delete if any references exist
 * - cascade: Soft-delete referrers before deleting target
 */
final class DeletePlannerService
{
    /**
     * @var array<string, bool> Track visited objects to prevent loops
     */
    private array $visited = [];

    public function __construct(
        private MetaObjectRepositoryInterface $metaObjectRepository,
        private MetaRefRepositoryInterface $metaRefRepository,
        private SchemaServiceInterface $schemaService,
        private ReferenceIndexService $referenceIndexService,
    ) {
    }

    /**
     * Execute deletion with referential integrity checks.
     *
     * @throws DeleteRestrictedException If restricted references exist
     */
    public function executeDelete(MetaObject $targetObject): void
    {
        $this->visited = [];
        $this->processDelete($targetObject);
    }

    /**
     * Process deletion of a single object, handling cascades.
     */
    private function processDelete(MetaObject $target): void
    {
        $key = $target->getObjectType() . ':' . $target->getUuid()->toString();
        if (isset($this->visited[$key])) {
            return; // Prevent infinite loops
        }
        $this->visited[$key] = true;

        // Get all inbound references
        $inboundRefs = $this->metaRefRepository->findInboundRefs(
            $target->getProjectId(),
            $target->getObjectType(),
            $target->getUuid()
        );

        if (empty($inboundRefs)) {
            // No references, safe to delete
            $this->metaObjectRepository->softDelete($target);
            $this->referenceIndexService->removeRefsForObject($target);

            return;
        }

        // Group by (from_type, path) to determine behavior
        $grouped = $this->groupRefsByPath($inboundRefs);

        // Check for restrict violations
        $restrictViolations = [];
        foreach ($grouped as $groupKey => $group) {
            $behavior = $this->getOnDeleteBehavior($group['fromType'], $group['path']);
            if ($behavior === OnDeleteBehavior::Restrict && count($group['refs']) > 0) {
                $restrictViolations[] = [
                    'fromType' => $group['fromType'],
                    'path' => $group['path'],
                    'count' => count($group['refs']),
                    'sample' => array_slice(
                        array_map(fn (MetaRef $r) => $r->getFromUuid()->toString(), $group['refs']),
                        0,
                        5
                    ),
                ];
            }
        }

        if (! empty($restrictViolations)) {
            throw new DeleteRestrictedException($target, $restrictViolations);
        }

        // Process cascades
        foreach ($grouped as $groupKey => $group) {
            $behavior = $this->getOnDeleteBehavior($group['fromType'], $group['path']);
            if ($behavior === OnDeleteBehavior::Cascade) {
                foreach ($group['refs'] as $ref) {
                    $referrer = $this->metaObjectRepository->findByUuid($ref->getFromUuid());
                    if ($referrer !== null && ! $referrer->isDeleted()) {
                        $this->processDelete($referrer);
                    }
                }
            }
        }

        // Finally, soft-delete the target and clean up its refs
        $this->metaObjectRepository->softDelete($target);
        $this->referenceIndexService->removeRefsForObject($target);
    }

    /**
     * Get onDelete behavior for a specific reference path.
     */
    private function getOnDeleteBehavior(string $fromType, string $path): OnDeleteBehavior
    {
        try {
            $definitions = $this->schemaService->getRefersToDefinitions($fromType);
            foreach ($definitions as $def) {
                if ($def->path === $path) {
                    return $def->onDelete;
                }
            }
        } catch (\Exception) {
            // Schema not found, default to restrict
        }

        return OnDeleteBehavior::Restrict;
    }

    /**
     * Group refs by (from_type, path).
     *
     * @param MetaRef[] $refs
     * @return array<string, array{fromType: string, path: string, refs: MetaRef[]}>
     */
    private function groupRefsByPath(array $refs): array
    {
        $grouped = [];

        foreach ($refs as $ref) {
            $key = $ref->getFromType() . '|' . $ref->getPath();
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'fromType' => $ref->getFromType(),
                    'path' => $ref->getPath(),
                    'refs' => [],
                ];
            }
            $grouped[$key]['refs'][] = $ref;
        }

        return $grouped;
    }
}
