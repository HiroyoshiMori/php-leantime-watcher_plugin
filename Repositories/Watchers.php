<?php

namespace Leantime\Plugins\Watchers\Repositories;

use Illuminate\Contracts\Container\BindingResolutionException;
use \Leantime\Core\Db;
use PDOException;

class Watchers
{
    private Db $db;
    /** @var string slug for tables to prevent conflict with official tables */
    private string $slug = 'plugin_';

    /**
     * @throws BindingResolutionException
     */
    public function __construct()
    {
        // Get DB Instance
        $this->db = app()->make(Db::class);
    }

    /**
     * Get watchers for the ticket
     *
     * @param int $id Ticket ID
     * @param int $limit Limit number to fetch at once. Default to -1, it means all
     * @return array
     */
    public function getTicketWatcher(int $id, int $limit=-1): array
    {
        $sql = <<<EOS
        SELECT
            w.projectId,
            w.ticketId,
            w.userId
        FROM
            zp_{$this->slug}watchers AS w,
            zp_tickets AS t
        WHERE
            w.ticketId = t.id
            AND w.projectId = t.projectId
            AND w.ticketId = :id
        ORDER BY
            w.userId ASC
        EOS;

        if ($limit > -1) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        if ($limit > -1) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $values = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $values;
    }

    /**
     * Get watchers for the project
     *
     * @param int $id Project ID
     * @param int $limit Limit number to fetch at once. Default to -1, it means all
     * @return array
     */
    public function getProjectWatcher(int $id, int $limit=-1): array
    {
        $sql = <<<EOS
        SELECT
            w.projectId,
            w.ticketId,
            w.userId
        FROM
            zp_{$this->slug}watchers AS w,
            zp_projects AS p
        WHERE
            w.projectId = p.id
            AND w.projectId = :id
            AND w.ticketId = 0
        ORDER BY
            w.userId ASC
        EOS;

        if ($limit > -1) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        if ($limit > -1) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $values = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $values;
    }

    /**
     * Get watching projects/tickets for the user
     *
     * @param int $id Project ID
     * @param int $limit Limit number to fetch at once. Default to -1, it means all
     * @return array
     */
    public function getWatchingTickets(int $id, int $limit=-1): array
    {
        $sql = <<<EOS
        SELECT
            w.projectId,
            w.ticketId,
            w.userId
        FROM
            zp_{$this->slug}watchers AS w
        WHERE
            AND w.usertId = :id
        ORDER BY
            w.projectId ASC,
            w.ticketId ASC
        EOS;

        if ($limit > -1) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        if ($limit > -1) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $values = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $values;
    }

    public function isWatchingTicket(int $projectId, int $ticketId, int $userId): bool
    {
        $sql = <<<EOS
        SELECT COUNT(*) as cnt
        FROM
            zp_{$this->slug}watchers AS w
        WHERE
            w.projectId = :projectId
            AND w.ticketId = :ticketId
            AND w.userId = :userId
        EOS;

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':projectId', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':ticketId', $ticketId, \PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $values = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $values->cnt > 0;
    }

    public function addTicketWatcher($projectId, int $ticketId, int $userId): array|bool
    {
        $sql = <<<EOS
        INSERT INTO {$this->slug}watchers (projectId, ticketId, userId) VALUES (:projectId, :ticketId, :userId)
        EOS;

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':projectId', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':ticketId', $ticketId, \PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $result = $stmt->execute();
        $stmt->closeCursor();

        return $result;
    }

    public function removeTicketWatcher($projectId, int $ticketId, int $userId): array|bool
    {
        $sql = <<<EOS
        DELETE FROM
            {$this->slug}watchers
        WHERE
            projectId = :projectId
            AND ticketId = :ticketId
            AND userId = :userId
        EOS;

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':projectId', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':ticketId', $ticketId, \PDO::PARAM_INT);
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $result = $stmt->execute();
        $stmt->closeCursor();

        return $result;
    }
}

