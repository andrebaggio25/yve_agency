<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InternalCommentRepository;

class InternalCommentService
{
    private const ALLOWED_TYPES = ['content_plan_item', 'task', 'content_plan'];

    public function __construct(
        private readonly InternalCommentRepository $repo,
    ) {}

    public function get(string $type, int $entityId, int $agencyId): array
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) return [];
        return $this->repo->getForEntity($type, $entityId, $agencyId);
    }

    public function add(string $type, int $entityId, int $agencyId, int $userId, string $message): int
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException("Tipo inválido: {$type}");
        }

        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Mensagem não pode ser vazia.');
        }

        return $this->repo->add([
            'agency_id'   => $agencyId,
            'entity_type' => $type,
            'entity_id'   => $entityId,
            'user_id'     => $userId,
            'message'     => $message,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
