<?php

namespace Leantime\Plugins\Watchers\Controllers {

    use \Leantime\Core\Controller;
    use \Symfony\Component\HttpFoundation\Response;
    use \Leantime\Domain\Projects\Services\Projects as ProjectService;
    use \Leantime\Domain\Tickets\Services\Tickets as TicketService;
    use \Leantime\Domain\Users\Services\Users as UserService;
    use \Leantime\Plugins\Watchers\Services\Watchers as WatcherService;

    class TicketWatchers extends Controller
    {
        private ProjectService $projectService;
        private TicketService $ticketService;
        private UserService $userService;
        private WatcherService $watcherService;
        private mixed $session;

        /**
         * init controller
         *
         * @param ProjectService $projectService
         * @param TicketService $ticketService
         * @param UserService $userService
         * @param WatcherService $watcherService
         * @return void
         */
        public function init(
            ProjectService $projectService,
            TicketService $ticketService,
            UserService $userService,
            WatcherService $watcherService
        ): void
        {
            $this->projectService = $projectService;
            $this->ticketService = $ticketService;
            $this->userService = $userService;
            $this->watcherService = $watcherService;

            error_log('app("session"):');
            error_log(var_export(app('session'), true));
        }

        /**
         * get
         *
         * @param $params
         * @return Response
         *
         */
        public function get($params): Response
        {
            if (!isset($params['id'])) {
                return $this->tpl->displayJson([
                    'status' => 400,
                ]);
            }

            $id = (int)($params['id']);
            $ticket = $this->ticketService->getTicket($id);
            if ($ticket === false) {
                return $this->tpl->displayJson([
                    'status' => 500,
                ]);
            }

//            if (app('session')->get('currentProject') != $ticket->projectId) {
//                return $this->tpl->displayJson([
//                    'status' => 500,
//                ]);
//            }
            return $this->tpl->displayJson([
                'status' => 200,
                'watchStatus' => $this->watcherService->isWatchingTicket(
                    $ticket->projectId, $id, 1 //app('session')->get('userId')
                )
            ]);
            // return $this->tpl->display("Watchers.showTicketWatchers");
        }

        /**
         * post
         *
         * @param array $params
         * @return void
         */
        public function post(array $params): void
        {
        }
    }
}

