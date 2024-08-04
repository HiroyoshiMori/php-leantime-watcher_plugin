<?php

namespace Leantime\Plugins\Watchers\Services;

use PDOException;
use Leantime\Core\Db as DbCore;
use Leantime\Core\Language as LanguageCore;
use Leantime\Domain\Notifications\Models\Notification as NotificationModel;
use Leantime\Domain\Notifications\Services\Messengers as MessengerService;
use Leantime\Domain\Notifications\Services\Notifications as NotificationService;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Queue\Repositories\Queue as QueueRepository;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Plugins\Watchers\Core\Language as PluginLanguage;
use Leantime\Plugins\Watchers\Repositories\Watchers as WatcherRepository;

class Watchers
{
    private DbCore $db;
    /**
     * @var string Version of this plugin
     */
    private string $dbVersion = '0.0.1';
    /**
     * @var array DB update scripts listed out by version number with leading zeros A.BB.CC => ABBCC
     */
    private array $dbUpdates = array();

    /** @var string slug for tables to prevent conflict with official tables */
    private string $slug = 'plugin_';
    /**
     * @var PluginLanguage Language information to translate
     */
    private PluginLanguage $language;
    /**
     * @var \Leantime\Domain\Users\Repositories\Users User repository to search users
     */
    private \Leantime\Domain\Users\Repositories\Users $userRepository;
    /**
     * @var \Leangime\Plugins\Watchers\Repositories\Watchers Rpository for this plugin
     */
    private WatcherRepository $watcherRepository;

    public function __construct(
        DbCore $db,
    )
    {
        $this->language = app()->make(PluginLanguage::class);
        $this->db = $db;
        $this->userRepository = app()->make(UserRepository::class);
        $this->watcherRepository = new \Leantime\Plugins\Watchers\Repositories\Watchers();

        $this->updateDb();
    }

    /**
     * Install necessary tables and records
     *
     * @return void
     * @throws \Exception
     */
    public function install(): void
    {
        // Repo call to create tables.
        $errors = array();
        $sql_queries = array(
            <<<EOS
            CREATE TABLE IF NOT EXISTS `zp_{$this->slug}watchers` (
                `projectId` int(11) NOT NULL,
                `ticketId` int(11) DEFAULT 0,
                `userId` int(11) NOT NULL,
                `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`projectId`, `ticketId`, `userId`),
                KEY `ticketId` (`ticketId`),
                KEY `userId` (`userId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            EOS,
        );

        foreach ($sql_queries as $sql) {
            try {
                $stmt = $this->db->database->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                $errors[] = $sql . ' Failed:' . $e->getMessage();
            }
        }
        if (count($errors) > 0) {
            error_log('[ERROR] Failed to install Watchers plugin.');
            error_log(join("\n", $errors));
            throw new \Exception(join("\n", $errors));
        }
    }

    /**
     * Uninstall installed tables
     *
     * @throws \Exception
     */
    public function uninstall(): void
    {
        // Remove tables
        $errors = array();
        $sql_queries = array(
            "DROP TABLE IF EXISTS `zp_{$this->slug}watchers`;"
        );

        foreach ($sql_queries as $sql) {
            try {
                $stmt = $this->db->database->prepare($sql);
                $stmt->execute();
            } catch (PDOException $e) {
                $errors[] = $sql . ' Failed:' . $e->getMessage();
            }
        }

        if (count($errors) > 0) {
            error_log('[ERROR] Failed to uninstall Watchers plugin.');
            error_log(join("\n", $errors));
            throw new \Exception(join("\n", $errors));
        }
    }

    /**
     * Main entry point to update the db based on version number. Executes all missing db update scripts
     *
     * @return void
     */
    private function updateDb(): void
    {
        $errors = array();
        $versionArray = explode(".", $this->dbVersion);
        if (is_array($versionArray) && count($versionArray) == 3) {
            $major = $versionArray[0];
            $minor = str_pad($versionArray[1], 2, "0", STR_PAD_LEFT);
            $patch = str_pad($versionArray[2], 2, "0", STR_PAD_LEFT);
            $newDbVersion = $major .$minor . $patch;
        } else {
            throw new Exception("Problem identifying the version number");
        }

        $dbVersionKey = "{$this->slug}watchers.db-version";
        $settingRepo = app()->make(SettingRepository::class);
        $dbVersion = $settingRepo->getSetting($dbVersionKey);
        $currentDbVersion = 0;
        if ($dbVersion) {
            $versionArray = explode('.', $dbVersion);
            if (is_array($versionArray) && count($versionArray) == 3) {
                $major = $versionArray[0];
                $minor = str_pad($versionArray[1], 2, "0", STR_PAD_LEFT);
                $patch = str_pad($versionArray[2], 2, "0", STR_PAD_LEFT);
                $currentDbVersion = $major .$minor . $patch;
            } else {
                throw new Exception("Problem identifying the current version number");
            }
        }

        if ($currentDbVersion == $newDbVersion) {
            return;
        }

        foreach ($this->dbUpdates as $updateVersion) {
            if ($currentDbVersion < $updateVersion) {
                $functionName = "update_sql_" . $updateVersion;

                if (!property_exists($this, $functionName)) {
                    $errors[] = 'Called function which does not exist.';
                    return;
                }

                $result = $this->$functionName();
                if ($result !== true) {
                    $errors = array_merge($errors, $result);
                } else {
                    try {
                        $sql = <<<EOS
                        INSERT INTO zp_settings(`key`, `value`) values (
                            '{$dbVersionKey}', '{$this->dbVersion}'                                         
                        ) ON DUPLICATE KEY UPDATE `value` = '{$this->dbVersion}'
                        EOS;

                        $stmt = $this->db->database->prepare($sql);
                        $stmt->execute();
                        $currentDbVersion = $updateVersion;
                    } catch (PDOException $e) {
                        error_log($e);
                        error_log($e->getTraceAsString());
                        throw new Exception("There was a problem updating the database");
                    }
                }

                if (count($errors) > 0) {
                    throw new Exception(join("\n", $errors));
                }
            }
        }
    }

    /**
     * Check watching ticket or not.
     *
     * @param int $projectId
     * @param int $ticketId
     * @param int $userId
     * @return bool
     */
    public function isWatchingTicket(int $projectId, int $ticketId, int $userId): bool
    {
        return $this->watcherRepository->isWatchingTicket($projectId, $ticketId, $userId);

    }

    /**
     * Toggle watcher status. True if successfully toggled.

     * @param int $projectId
     * @param int $ticketId
     * @param int $userId
     * @return bool
     */
    public function toggleWatching(int $projectId, int $ticketId, int $userId): bool
    {
        return $this->isWatchingTicket($projectId, $ticketId, $userId)
            ? $this->watcherRepository->deleteWatcher($projectId, $ticketId, $userId)
            : $this->watcherRepository->addWatcher($projectId, $ticketId, $userId);
    }

    /**
     * Get target users to notify
     *
     * @param string $type
     * @param string $module
     * @param int $moduleId
     * @return array
     */
    public function getTargetUsers(string $type, string $module, int $moduleId): array
    {
        $results = [];

        $settingService = app()->make(SettingService::class);
        $targetUsers = [];
        switch (strtolower($module)) {
            case 'tickets':
                $ticketService = app()->make(TicketService::class);
                $ticket = $ticketService->getTicket($moduleId);

                // get author info
                $userId = $ticket->userId;
                $user = $this->userRepository->getUser($userId);
                $targetUsers[] = $user;

                // get assigned to info
                if ($ticket->editorId !== $ticket->userId) {
                    $userId = $ticket->editorId;
                    $user = $this->userRepository->getUser($userId);
                    $targetUsers[] = $user;
                }
                $results = [
                    'type' => $type,
                    'module' => $module,
                    'id' => $ticket->id,
                    'projectId' => $ticket->projectId,
                    'headline' => $ticket->headline,
                    'authorId' => $ticket->editorId,
                ];
                break;
            default:
        }

        // Get users' message frequency
        for ($i = 0; $i < count($targetUsers); $i++) {
            $messageFrequency = null;
            $language = null;
            if ($targetUsers[$i]) {
                if ($targetUsers[$i]['notifications'] === 1) {
                    $key = sprintf('usersettings.%s.messageFrequency', $targetUsers[$i]['id']);
                    $messageFrequency = $settingService->getSetting($key)
                        ? $settingService->getSetting($key)
                        : $settingService->getSetting('companysettings.messageFrequency');
                    $targetUsers[$i]['messageFrequency'] = $messageFrequency;
                }
                $key = sprintf('usersettings.%s.language', $targetUsers[$i]['id']);
                $language = $settingService->getSetting($key)
                    ? $settingService->getSetting($key)
                    : (
                    $settingService->getSetting('companysettings.language')
                        ? $settingService->getSetting('companysettings.language')
                        : 'en-US'
                    );

                $targetUsers[$i]['language'] = $language;
            }
        }
        $results['users'] = $targetUsers;

        return $results;
    }

    /**
     * Send notifications to users
     *
     * Keys in values
     * - users
     *   array of target users
     * - module
     *   name of module
     * - type
     *   name of type
     *   [compatible type/module]
     *   - projectUpdate/tickets
     * - authorId
     *   ID of author
     * - projectId
     *   ID of project
     *
     * @param array $values values to be used for notification
     * @return bool
     */
    public function sendNotifications(array $values): bool
    {
        if (empty($values) || !isset($values['module']) || !isset($values['type'])) {
            return false;
        }

        $targetUsers = $values['users'];
        for ($i = 0; $i < count($targetUsers); $i++) {
            $this->language->setLanguage($targetUsers[$i]['language']);
            $subject = sprintf(
                $this->language->__(
                    sprintf(
                        'temporary_notifications.%1$s_%2$s_subject',
                        $values['type'], $values['module']
                    )
                ), $values['id'], $values['headline']
            );
            $actual_link = '';
            switch (strtolower($values['module'])) {
                case 'tickets':
                    $actual_link = BASE_URL . "/dashboard/home#/tickets/showTicket/" . $values['id'];
                    break;
                case 'projects':
                    $actual_link = BASE_URL . "/projects/changeCurrentProject/" . $values['id'];
                    break;
                default:
            }
            $message = sprintf(
                $this->language->__(
                    sprintf(
                        'temporary_notifications.%1$s_%2$s_message',
                        $values['type'], $values['module']
                    )
                ),
                $targetUsers[$i]['firstname'], $values['headline']
            );

            $notification = app()->make(NotificationModel::class);
            $notification->url = array(
                'url' => $actual_link,
                'text' => $this->language->__(
                    sprintf(
                        'temporary_notifications.%1$s_%2$s_cta',
                        $values['type'], $values['module']
                    )
                ),
            );
            $notification->entity = $values;
            $notification->module = $values['module'];
            $notification->projectId = $values['projectId'];
            $notification->subject = $subject;
            $notification->authorId = $values['authorId'];
            $notification->message = $message;

            $this->notifyProjectUsers($notification, [$targetUsers[$i]]);
        }

        return true;
    }

    public function notifyProjectUsers(NotificationModel $notification, array $users): void
    {
        $users = array_filter($users, function ($user) use ($notification) {
            return $user != $notification->authorId;
        }, ARRAY_FILTER_USE_BOTH);

        $emailMessage = $notification->message;
        if ($notification->url !== false) {
            $emailMessage .= " <a href='" . $notification->url['url'] . "'>" . $notification->url['text'] . "</a>";
        }

        // NEW Queuing messaging system
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = $user['id'];
        }
        $queue = new QueueRepository($this->db, $this->userRepository);
        $queue->queueMessageToUsers($userIds, $emailMessage, $notification->subject, $notification->projectId);

        /*
        //Send to messengers
        $projectService = app()->make(ProjectService::class);
        $projectName = $projectService->getProjectName($notification->projectId);

        $messengerService = app()->make(MessengerService::class);
        $messengerService->sendNotificationToMessengers($notification, $projectName);

        //Notify users about mentions
        //Fields that should be parsed for mentions
        $mentionFields = array(
            "comments" => array("text"),
            "projects" => array("details"),
            "tickets" => array("description"),
            "canvas" => array("description", "data", "conclusion", "assumptions"),
        );

        $contentToCheck = '';
        //Find entity ID & content
        //Todo once all entities are models this if statement can be reduced
        if (isset($notification->entity) && is_array($notification->entity) && isset($notification->entity["id"])) {
            $entityId = $notification->entity["id"];

            if (isset($mentionFields[$notification->module])) {
                $fields = $mentionFields[$notification->module];

                foreach ($fields as $field) {
                    if (isset($notification->entity[$field])) {
                        $contentToCheck .= $notification->entity[$field];
                    }
                }
            }
        } elseif (isset($notification->entity) && is_object($notification->entity) && isset($notification->entity->id)) {
            $entityId = $notification->entity->id;

            if (isset($mentionFields[$notification->module])) {
                $fields = $mentionFields[$notification->module];

                foreach ($fields as $field) {
                    if (isset($notification->entity->$field)) {
                        $contentToCheck .= $notification->entity->$field;
                    }
                }
            }
        } else {
            //Entity id not set use project id
            $entityId = $notification->projectId;
        }

        if ($contentToCheck != '') {
            $notificationService = app()->make(NotificationService::class);
            $notificationService->processMentions(
                $contentToCheck,
                $notification->module,
                (int)$entityId,
                $notification->authorId,
                $notification->url["url"]
            );
        }
        */
    }
}
