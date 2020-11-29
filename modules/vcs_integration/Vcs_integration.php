<?php

    namespace pachno\modules\vcs_integration;

    use b2db\Criteria;
    use b2db\Update;
    use pachno\core\entities\tables\Settings;
    use pachno\core\framework,
        pachno\modules\vcs_integration\entities\File,
        pachno\modules\vcs_integration\entities\tables\Files,
        pachno\modules\vcs_integration\entities\IssueLink,
        pachno\modules\vcs_integration\entities\tables\IssueLinks,
        pachno\modules\vcs_integration\entities\Commit,
        pachno\modules\vcs_integration\entities\tables\Commits;

/**
     * Module class, vcs_integration
     *
     * @author Philip Kent <kentphilip@gmail.com>
     * @version 3.1
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package pachno
     * @subpackage vcs_integration
     */

    /**
     * Module class, vcs_integration
     *
     * @package pachno
     * @subpackage vcs_integration
     *
     * @Table(name="\pachno\core\entities\tables\Modules")
     */
    class Vcs_integration extends \pachno\core\entities\Module
    {

        const VERSION = '2.0.1';

        const MODE_DISABLED = 0;
        const MODE_ISSUECOMMITS = 1;
        const WORKFLOW_DISABLED = 0;
        const WORKFLOW_ENABLED = 1;
        const ACCESS_DIRECT = 0;
        const ACCESS_HTTP = 1;
        const NOTIFICATION_COMMIT_MENTIONED = 'commit_mentioned';

        protected $_name = 'vcs_integration';
        protected $_longname = 'VCS Integration';
        protected $_description = 'Allows details from source code checkins to be displayed in Pachno. Configure in each project\'s settings.';
        protected $_module_config_title = 'VCS Integration';
        protected $_module_config_description = 'Configure repository settings for source code integration';
        protected $_has_config_settings = false;

        protected function _initialize()
        {

        }

        protected function _upgrade()
        {
            switch ($this->_version) {
                case '2.0':
                    $table = Settings::getTable();
                    $query = $table->getQuery();
                    $criteria = new Criteria();
                    $criteria->where(Settings::NAME, 'diff_url_%', \b2db\Criterion::LIKE);
                    $criteria->or(Settings::NAME, 'log_url_%', \b2db\Criterion::LIKE);
                    $criteria->or(Settings::NAME, 'blob_url_%', \b2db\Criterion::LIKE);
                    $criteria->or(Settings::NAME, 'commit_url_%', \b2db\Criterion::LIKE);
                    $query->where($criteria);
                    $query->where(Settings::MODULE, 'vcs_integration');
                    $urls = $table->rawSelect($query);
                    if ($urls) {
                        while ($url = $urls->getNextRow()) {
                            $value = str_replace(array('%revno%', '%oldrev%', '%file%'), array('%revno', '%oldrev', '%file'), $url[Settings::VALUE]);
                            $update = new Update();
                            $update->add(Settings::VALUE, $value);
                            $table->rawUpdateById($update, $url[Settings::ID]);
                        }
                    }
            }
        }

        protected function _install($scope)
        {

        }

        protected function _loadFixtures($scope)
        {
            if ($scope == framework\Settings::getDefaultScopeID()) {
                Commits::getTable()->createIndexes();
                Files::getTable()->createIndexes();
                IssueLinks::getTable()->createIndexes();
            }
        }

        protected function _addListeners()
        {
            framework\Event::listen('core', 'project_sidebar_links', array($this, 'listen_project_links'));
            framework\Event::listen('core', 'get_backdrop_partial', array($this, 'listen_getcommit'));
            framework\Event::listen('core', 'viewissue_before_tabs', array($this, 'listen_viewissue_panel_tab'));
            framework\Event::listen('core', 'viewissue_after_tabs', array($this, 'listen_viewissue_panel'));
            framework\Event::listen('core', 'config_project_tabs_other', array($this, 'listen_projectconfig_tab'));
            framework\Event::listen('core', 'config_project_panes', array($this, 'listen_projectconfig_panel'));
            framework\Event::listen('core', 'project_header_buttons', array($this, 'listen_projectheader'));
            framework\Event::listen('core', '_notification_view', array($this, 'listen_notificationview'));
            framework\Event::listen('core', '_notification_view_text', array($this, 'listen_notificationviewtext'));
            framework\Event::listen('core', 'pachno\core\entities\Notification::getTarget', array($this, 'listen_pachno_core_entities_Notification_getTarget'));
            framework\Event::listen('core', 'pachno\core\entities\Notification::getTargetUrl', array($this, 'listen_pachno_core_entities_Notification_getTargetUrl'));
            framework\Event::listen('core', 'pachno\core\framework\helpers\TextParser::_parse_line::char_regexes', array($this, 'listen_pachno_core_helpers_textparser_char_regexes'));
            framework\Event::listen('core', 'pachno\core\framework\helpers\TextParserMarkdown::transform', array($this, 'listen_pachno_core_helpers_textparser_char_regexes'));
        }

        protected function _uninstall()
        {
            if (framework\Context::getScope()->getID() == 1)
            {
                Commits::getTable()->drop();
                Files::getTable()->drop();
                IssueLinks::getTable()->drop();
            }
            parent::_uninstall();
        }

        public function hasProjectAwareRoute()
        {
            return false;
        }

        public function listen_pachno_core_helpers_textparser_char_regexes(framework\Event $event)
        {
            $event->addToReturnList(array(array('/([a-f0-9]{40})/'), array($this, '_parse_commit')));

            if (framework\Context::isProjectContext() && framework\Context::getModule('vcs_integration')->getSetting('browser_type_'.framework\Context::getCurrentProject()->getID()) === 'gitlab')
            {
              $event->addToReturnList(array(
                array('/(\![0-9]+)/'), function ($matches) use ($event)
                  {
                    if (!$event->getParameter('target') instanceof Commit) return $matches[0];

                    return link_tag($event->getParameter('target')->getGitlabUrlForMergeRequestID(substr($matches[0], 1)), $matches[0], array('target' => '_blank'));
                  }
              ));
            }
        }

        protected function _getCommitLink($commit)
        {
            return '<a href="javascript:void(0)" onclick="Pachno.UI.Backdrop.show(\''.framework\Context::getRouting()->generate('get_partial_for_backdrop', array('key' => 'vcs_integration_getcommit', 'commit_id' => $commit->getID())).'\');">'.$commit->getRevisionString().'</a>';
        }

        public function _parse_commit($matches)
        {
            if (!framework\Context::isProjectContext())
                return $matches[0];

            /* <a href="javascript:void(0)" onclick="Pachno.UI.Backdrop.show('<?php echo make_url('get_partial_for_backdrop', array('key' => 'vcs_integration_getcommit', 'commit_id' => $commit->getID())); ?>');"><?php echo $commit->getRevisionString(); ?></a> */
            $commit = Commits::getTable()->getCommitByCommitId($matches[0], framework\Context::getCurrentProject()->getID());

            if (!$commit instanceof Commit)
                return $matches[0];

            return $this->_getCommitLink($commit);
        }

        public function listen_sidebar_links(framework\Event $event)
        {
            if (framework\Context::isProjectContext())
            {
                include_component('vcs_integration/menustriplinks', array('project' => framework\Context::getCurrentProject(), 'module' => $this, 'submenu' => $event->getParameter('submenu')));
            }
        }

        public function listen_project_links(framework\Event $event)
        {
            if (framework\Context::getUser()->hasProjectPageAccess('project_commits', framework\Context::getCurrentProject()))
                $event->addToReturnList(array('url' => framework\Context::getRouting()->generate('vcs_commitspage', array('project_key' => framework\Context::getCurrentProject()->getKey())), 'title' => framework\Context::getI18n()->__('Commits')));
        }

        public function listen_projectheader(framework\Event $event)
        {
            include_component('vcs_integration/projectheaderbutton');
        }

        public function listen_projectconfig_tab(framework\Event $event)
        {
            include_component('vcs_integration/projectconfig_tab', array('selected_tab' => $event->getParameter('selected_tab')));
        }

        public function listen_projectconfig_panel(framework\Event $event)
        {
            include_component('vcs_integration/projectconfig_panel', array('selected_tab' => $event->getParameter('selected_tab'), 'access_level' => $event->getParameter('access_level'), 'project' => $event->getParameter('project')));
        }

        public function listen_notificationview(framework\Event $event)
        {
            if ($event->getSubject()->getModuleName() != 'vcs_integration')
                return;

            include_component('vcs_integration/notification_view', array('notification' => $event->getSubject()));
            $event->setProcessed();
        }

        public function listen_notificationviewtext(framework\Event $event)
        {
            if ($event->getSubject()->getModuleName() != 'vcs_integration')
                return;

            include_component('vcs_integration/notification_view_text', array('notification' => $event->getSubject()));
            $event->setProcessed();
        }

        public function listen_pachno_core_entities_Notification_getTarget(framework\Event $event)
        {
            if ($event->getSubject()->getModuleName() != 'vcs_integration')
                return;

            $commit = Commits::getTable()->selectById($event->getSubject()->getTargetID());
            $event->setReturnValue($commit);
            $event->setProcessed();
        }

        public function listen_pachno_core_entities_Notification_getTargetUrl(framework\Event $event)
        {
            if ($event->getSubject()->getModuleName() != 'vcs_integration')
                return;

            switch ($event->getSubject()->getNotificationType()) {
                case self::NOTIFICATION_COMMIT_MENTIONED:
                    $event->setReturnValue(framework\Context::getRouting()->generate('get_partial_for_backdrop', array(
                        'key' => 'vcs_integration_getcommit', 'commit_id' => $event->getSubject()
                          ->getTargetID()
                      )));
                    $event->setProcessed();
                    break;
            }
        }


        public function listen_getcommit(framework\Event $event)
        {
            if ($event->getSubject() == 'vcs_integration_getcommit')
            {
                $event->setReturnValue('vcs_integration/commitbackdrop');
                $event->addToReturnList(framework\Context::getRequest()->getParameter('commit_id'), 'commit_id');
                $event->setProcessed();
            }
        }

        public function listen_viewissue_panel_tab(framework\Event $event)
        {
            if (framework\Context::getModule('vcs_integration')->getSetting('vcs_mode_' . framework\Context::getCurrentProject()->getID()) == self::MODE_DISABLED)
                return;

            $links_total_count = IssueLinks::getTable()->countByIssueID($event->getSubject()->getID());
            include_component('vcs_integration/viewissue_activities_tab', array('count' => $links_total_count));
        }

        public function listen_viewissue_panel(framework\Event $event)
        {
            if (framework\Context::getModule('vcs_integration')->getSetting('vcs_mode_' . framework\Context::getCurrentProject()->getID()) == self::MODE_DISABLED)
                return;

            $links = IssueLink::getCommitsByIssue($event->getSubject());
            $links_total_count = IssueLinks::getTable()->countByIssueID($event->getSubject()->getID());
            include_component('vcs_integration/viewissue_commits', array('issue' => $event->getSubject(), 'links' => $links, 'links_total_count' => $links_total_count, 'selected_project' => $event->getSubject()->getProject()));
        }

        public static function processCommit(\pachno\core\entities\Project $project, $commit_msg, $old_rev, $new_rev, $date = null, $changed, $author, $branch = null, \Closure $callback = null)
        {
            $output = '';
            framework\Context::setCurrentProject($project);

            if ($project->isArchived())
                return;

            if (Commits::getTable()->isProjectCommitProcessed($new_rev, $project->getID()))
                return;

            try
            {
                framework\Context::getI18n();
            }
            catch (\Exception $e)
            {
                framework\Context::reinitializeI18n(null);
            }

            // Is VCS Integration enabled?
            if (framework\Settings::get('vcs_mode_' . $project->getID(), 'vcs_integration') == self::MODE_DISABLED)
            {
                $output .= '[VCS ' . $project->getKey() . '] This project does not use VCS Integration' . "\n";
                return $output;
            }

            // Parse the commit message, and obtain the issues and transitions for issues.
            $parsed_commit = \pachno\core\entities\Issue::getIssuesFromTextByRegex($commit_msg);
            $issues = $parsed_commit["issues"];
            $transitions = $parsed_commit["transitions"];

            // Build list of affected files
            $file_lines = preg_split('/[\n\r]+/', $changed);
            $files = array();

            foreach ($file_lines as $aline)
            {
                $action = mb_substr($aline, 0, 1);

                if ($action == "A" || $action == "U" || $action == "D" || $action == "M")
                {
                    $theline = trim(mb_substr($aline, 1));
                    $files[] = array($action, $theline);
                }
            }

            // Find author of commit, fallback is guest
            /*
             * Some VCSes use a different format of storing the committer's name. Systems like bzr, git and hg use the format
             * Joe Bloggs <me@example.com>, instead of a classic username. Therefore a user will be found via 4 queries:
             * a) First we extract the email if there is one, and find a user with that email
             * b) If one is not found - or if no email was specified, then instead test against the real name (using the name part if there was an email)
             * c) the username or full name is checked against the friendly name field
             * d) and if we still havent found one, then we check against the username
             * e) and if we STILL havent found one, we use the guest user
             */

            // a)
            $user = \pachno\core\entities\tables\Users::getTable()->getByEmail($author);

            if (!$user instanceof \pachno\core\entities\User && preg_match("/(?<=<)(.*)(?=>)/", $author, $matches))
            {
                $email = $matches[0];

                // a2)
                $user = \pachno\core\entities\tables\Users::getTable()->getByEmail($email);

                if (!$user instanceof \pachno\core\entities\User)
                {
                    // Not found by email
                    preg_match("/(?<=^)(.*)(?= <)/", $author, $matches);
                    $author = $matches[0];
                }
            }

            // b)
            if (!$user instanceof \pachno\core\entities\User)
                $user = \pachno\core\entities\tables\Users::getTable()->getByRealname($author);

            // c)
            if (!$user instanceof \pachno\core\entities\User)
                $user = \pachno\core\entities\tables\Users::getTable()->getByBuddyname($author);

            // d)
            if (!$user instanceof \pachno\core\entities\User)
                $user = \pachno\core\entities\tables\Users::getTable()->getByUsername($author);

            // e)
            if (!$user instanceof \pachno\core\entities\User)
                $user = framework\Settings::getDefaultUser();

            framework\Context::setUser($user);
            framework\Settings::forceSettingsReload();
            framework\Context::cacheAllPermissions();

            $output .= '[VCS ' . $project->getKey() . '] Commit to be logged by user ' . $user->getName() . "\n";

            if ($date == null):
                $date = NOW;
            endif;

            // Create the commit data
            $commit = new Commit();
            $commit->setAuthor($user);
            $commit->setDate($date);
            $commit->setLog($commit_msg);
            $commit->setPreviousRevision($old_rev);
            $commit->setRevision($new_rev);
            $commit->setProject($project);

            if ($branch !== null)
            {
                $data = 'branch:' . $branch;
                $commit->setMiscData($data);
            }

            if ($callback !== null) $commit = $callback($commit);

            $commit->save();

            $output .= '[VCS ' . $project->getKey() . '] Commit logged with revision ' . $commit->getRevision() . "\n";

            // Iterate over affected issues and update them.
            foreach ($issues as $issue)
            {
                $inst = new IssueLink();
                $inst->setIssue($issue);
                $inst->setCommit($commit);
                $inst->save();

                // Process all commit-message transitions for an issue.
                foreach ($transitions[$issue->getFormattedIssueNo()] as $transition)
                {
                    if (framework\Settings::get('vcs_workflow_' . $project->getID(), 'vcs_integration') == self::WORKFLOW_ENABLED)
                    {
                        framework\Context::setUser($user);
                        framework\Settings::forceSettingsReload();
                        framework\Context::cacheAllPermissions();

                        if ($issue->isWorkflowTransitionsAvailable())
                        {
                            // Go through the list of possible transitions for an issue. Only
                            // process transitions that are applicable to issue's workflow.
                            foreach ($issue->getAvailableWorkflowTransitions() as $possible_transition)
                            {
                                if (mb_strtolower($possible_transition->getName()) == mb_strtolower($transition[0]))
                                {
                                    $output .= '[VCS ' . $project->getKey() . '] Running transition ' . $transition[0] . ' on issue ' . $issue->getFormattedIssueNo() . "\n";
                                    // String representation of parameters. Used for log message.
                                    $parameters_string = "";

                                    // Iterate over the list of this transition's parameters, and
                                    // set them.
                                    foreach ($transition[1] as $parameter => $value)
                                    {
                                        $parameters_string .= "$parameter=$value ";

                                        switch ($parameter)
                                        {
                                            case 'resolution':
                                                if (($resolution = \pachno\core\entities\Resolution::getByKeyish($value)) instanceof \pachno\core\entities\Resolution)
                                                {
                                                    framework\Context::getRequest()->setParameter('resolution_id', $resolution->getID());
                                                }
                                                break;
                                            case 'status':
                                                if (($status = \pachno\core\entities\Status::getByKeyish($value)) instanceof \pachno\core\entities\Status)
                                                {
                                                    framework\Context::getRequest()->setParameter('status_id', $status->getID());
                                                }
                                                break;
                                        }
                                    }

                                    // Run the transition.
                                    $possible_transition->transitionIssueToOutgoingStepFromRequest($issue, framework\Context::getRequest());

                                    // Log an informative message about the transition.
                                    $output .= '[VCS ' . $project->getKey() . '] Ran transition ' . $possible_transition->getName() . ' with parameters \'' . $parameters_string . '\' on issue ' . $issue->getFormattedIssueNo() . "\n";
                                }
                            }
                        }
                    }
                }

                $issue->addSystemComment(framework\Context::getI18n()->__('This issue has been updated with the latest changes from the code repository.%commit_msg', array('%commit_msg' => '<div class="commit_main">' . $commit_msg . '</div>')), $user->getID(), 'vcs_integration');
                $output .= '[VCS ' . $project->getKey() . '] Updated issue ' . $issue->getFormattedIssueNo() . "\n";
            }

            // Create file links
            foreach ($files as $afile)
            {
                // index 0 is action, index 1 is file
                $inst = new File();
                $inst->setAction($afile[0]);
                $inst->setFile($afile[1]);
                $inst->setCommit($commit);
                $inst->save();

                $output .= '[VCS ' . $project->getKey() . '] Added with action ' . $afile[0] . ' file ' . $afile[1] . "\n";
            }

            framework\Event::createNew('vcs_integration', 'new_commit')->trigger(array('commit' => $commit));

            return $output;
        }

        public function isCore()
        {
            return false;
        }

    }
