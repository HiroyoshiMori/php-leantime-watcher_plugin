<?php

namespace Leantime\Plugins\Watchers\Models;

use Carbon\CarbonImmutable;

class Watchers
{
    public int $projectId;
    public ?int $ticketId = null;
    public int $userId;
    public CarbonImmutable $createdAt;
    public CarbonImmutable $modified;

    public function __construct(array|bool $values = false)
    {
        if ($values !== false) {
            $this->projectId = $values['projectId'];
            $this->ticketId = $values['ticketId'] ?? null;
            $this->userId = $values['userId'];
            $this->createdAt = $values['createdAt'] ?? CarbonImmutable::now();
            $this->modified = $values['modified'] ?? CarbonImmutable::now();
        }
    }
}
