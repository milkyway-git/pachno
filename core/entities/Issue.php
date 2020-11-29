<?php

    namespace pachno\core\entities;

    use b2db\Row;
    use Exception;
    use pachno\core\entities\common\Identifiable;
    use pachno\core\entities\common\Ownable;
    use pachno\core\entities\traits\Commentable;
    use pachno\core\framework;
    use pachno\core\framework\Context;
    use pachno\core\framework\Logging;
    use pachno\core\framework\Settings;
    use pachno\core\helpers\Attachable;
    use pachno\core\helpers\MentionableProvider;
    use pachno\core\helpers\TextParser;
    use pachno\core\helpers\TextParserMarkdown;
    use Webit\Util\EvalMath\EvalMath;

    /**
     * Issue class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 3.1
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package pachno
     * @subpackage main
     */

    /**
     * Issue class
     *
     * @package pachno
     * @subpackage main
     *
     * @method static tables\Issues getB2DBTable()
     *
     * @Table(name="\pachno\core\entities\tables\Issues")
     */
    class Issue extends Ownable implements MentionableProvider, Attachable
    {

        use Commentable;

        /**
         * Open issue state
         *
         * @static integer
         */
        const STATE_OPEN = 0;

        /**
         * Closed issue state
         *
         * @static integer
         */
        const STATE_CLOSED = 1;

        const COVER_STYLE_NONE = 'none';
        const COVER_STYLE_SOLID = 'solid';
        const COVER_STYLE_SHADED = 'shaded';

        /**
         * @Column(type="string", name="name", length=1000)
         */
        protected $_title;

        /**
         * @Column(type="string", name="shortname", length=1000)
         */
        protected $_shortname;

        /**
         * Array of links attached to this issue
         *
         * @var array
         */
        protected $_links = null;

        /**
         * Array of files attached to this issue
         *
         * @var array
         */
        protected $_files = null;

        /**
         * Number of attached files
         *
         * @var integer
         */
        protected $_num_files = null;

        /**
         * The issue number
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_issue_no;

        /**
         * The issue type
         *
         * @var Issuetype
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Issuetype")
         */
        protected $_issuetype;

        /**
         * The project which this issue affects
         *
         * @var Project
         * @access protected
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Project")
         */
        protected $_project_id;

        /**
         * The affected editions for this issue
         *
         * @var array
         */
        protected $_editions = null;

        /**
         * The affected builds for this issue
         *
         * @var array
         */
        protected $_builds = null;

        /**
         * The affected components for this issue
         *
         * @var array
         */
        protected $_components = null;

        /**
         * This issues long description
         *
         * @var string
         * @Column(type="text")
         */
        protected $_description;

        /**
         * The syntax used for this issue's long description
         *
         * @var integer
         * @Column(type="integer", length=2, default=1)
         */
        protected $_description_syntax;

        protected $_description_parser = null;

        /**
         * This issues reproduction steps
         *
         * @var string
         * @Column(type="text")
         */
        protected $_reproduction_steps;

        /**
         * The syntax used for this issue's reproduction steps
         *
         * @var integer
         * @Column(type="integer", length=2, default=1)
         */
        protected $_reproduction_steps_syntax;

        protected $_reproduction_steps_parser = null;

        /**
         * When the issue was posted
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_posted;

        /**
         * When the issue was last updated
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_last_updated;

        /**
         * Who posted the issue
         *
         * @var User
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_posted_by;

        /**
         * The project assignee if team
         *
         * @var Team
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Team")
         */
        protected $_assignee_team;

        /**
         * The project assignee if user
         *
         * @var User
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_assignee_user;

        /**
         * What kind of bug this is
         *
         * @var integer
         * @Column(type="integer", length=3)
         */
        protected $_pain_bug_type;

        /**
         * What effect this bug has on users
         *
         * @var integer
         * @Column(type="integer", length=3)
         */
        protected $_pain_effect;

        /**
         * How likely users are to experience this bug
         *
         * @var integer
         * @Column(type="integer", length=3)
         */
        protected $_pain_likelihood;

        /**
         * Calculated user pain score
         *
         * @var float
         * @Column(type="float")
         */
        protected $_user_pain = 0.00;

        /**
         * The resolution
         *
         * @var Resolution
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Resolution")
         */
        protected $_resolution;

        /**
         * The issues' state (open or closed)
         *
         * @var integer
         * @Column(type="integer", length=2)
         */
        protected $_state = self::STATE_OPEN;

        /**
         * The category
         *
         * @var Category
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Category")
         */
        protected $_category;

        /**
         * The status
         *
         * @var Status
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Status")
         */
        protected $_status;

        /**
         * The prioroty
         *
         * @var Priority
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Priority")
         */
        protected $_priority;

        /**
         * The reproducability
         *
         * @var Reproducability
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Reproducability")
         */
        protected $_reproducability;

        /**
         * The severity
         *
         * @var Severity
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Severity")
         */
        protected $_severity;

        /**
         * The scrum color
         *
         * @var string
         * @Column(type="string", length=7, default="")
         */
        protected $_cover_color = '';

        /**
         * The scrum color
         *
         * @var string
         * @Column(type="string", length=10, default="")
         */
        protected $_cover_style = self::COVER_STYLE_NONE;

        /**
         * A cover image, if present
         *
         * @var File
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\File")
         */
        protected $_cover_image_file_id = 0;

        /**
         * The estimated time (months) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_months;

        /**
         * The estimated time (weeks) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_weeks;

        /**
         * The estimated time (days) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_days;

        /**
         * The estimated time (hours) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_hours;

        /**
         * The estimated time (minutes) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_minutes;

        /**
         * The estimated time (points) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_estimated_points;

        /**
         * The time spent (months) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_months;

        /**
         * The time spent (weeks) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_weeks;

        /**
         * The time spent (days) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_days;

        /**
         * The time spent (hours) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_hours;

        /**
         * The time spent (minutes) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_minutes;

        /**
         * The time spent (points) to fix this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_spent_points;

        /**
         * How far along the issus is
         *
         * @var integer
         * @Column(type="integer", length=2)
         */
        protected $_percent_complete;

        /**
         * Which user is currently working on this issue
         *
         * @var User
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_being_worked_on_by_user;

        /**
         * When the last user started working on the issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_being_worked_on_by_user_since;

        /**
         * Whether the issue is deleted
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_deleted = false;

        /**
         * Whether the issue is deleted
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_archived = false;

        /**
         * Whether the issue is blocking the next release
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_blocking = false;

        /**
         * Votes for this issue
         *
         * @var array
         */
        protected $_votes = null;

        /**
         * Sum of votes for this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_votes_total = null;

        /**
         * Milestone sorting order for this issue
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_milestone_order = null;

        /**
         * The issue this issue is a duplicate of
         *
         * @var Issue
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Issue")
         */
        protected $_duplicate_of;

        /**
         * The milestone this issue is assigned to
         *
         * @var Milestone
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Milestone")
         */
        protected $_milestone;

        /**
         * List of issues this issue depends on
         *
         * @var array
         */
        protected $_parent_issues;

        /**
         * List of issues that depends on this issue
         *
         * @var array
         */
        protected $_child_issues;

        /**
         * List of issues which are duplicates of this one
         *
         * @var Issue[]
         * @Relates(class="\pachno\core\entities\Issue", collection=true, foreign_column="duplicate_of")
         */
        protected $_duplicate_issues;

        /**
         * List of log entries
         *
         * @var array
         */
        protected $_log_entries;

        /**
         * Whether the issue is locked for changes
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_locked;

        /**
         * Whether the issue is locked for changes to category
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_locked_category;

        /**
         * The issues current step in the associated workflow
         *
         * @var WorkflowStep
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\WorkflowStep")
         */
        protected $_workflow_step_id;

        /**
         * An array of \pachno\core\entities\IssueSpentTimes
         *
         * @var array
         * @Relates(class="\pachno\core\entities\IssueSpentTime", collection=true, foreign_column="issue_id")
         */
        protected $_spent_times;

        protected $_num_user_comments;

        protected $_custom_populated = false;

        protected $_log_items_added = [];

        protected $_save_comment = '';

        protected $_can_permission_cache = [];

        protected $_editable;

        protected $_updateable;

        /**
         * Array of users that are subscribed to this issue
         *
         * @var User[]
         * @Relates(class="\pachno\core\entities\User", collection=true, manytomany=true, joinclass="\pachno\core\entities\tables\UserIssues")
         */
        protected $_subscribers = null;

        /**
         * Array of tags attached to this issue
         *
         * @var User[]
         * @Relates(class="\pachno\core\entities\User", collection=true, manytomany=true, joinclass="\pachno\core\entities\tables\UserIssues")
         */
        protected $_tags = null;

        protected $_new_subscribers = [];

        /**
         * List of changed properties
         *
         * @var array
         */
        protected $_changed_items = [];

        /**
         * Should log entry be added
         *
         * @var bool
         */
        protected $should_log_entry = true;

        /**
         * Custom sums columns.
         *
         * @var array
         */
        protected $_sums = [];

        /**
         * All custom data type properties
         *
         * @property $_customfield*
         * @var mixed
         */

        /**
         * Count the number of open and closed issues for a specific project id
         *
         * @param integer $project_id The project ID
         *
         * @return array
         */
        public static function getIssueCountsByProjectID($project_id)
        {
            return tables\Issues::getTable()->getCountsByProjectID($project_id);
        }

        /**
         * Count the number of open and closed issues for a specific project id
         * and issue type id
         *
         * @param integer $project_id The project ID
         * @param integer $issuetype_id The issue type ID
         *
         * @return array
         */
        public static function getIssueCountsByProjectIDandIssuetype($project_id, $issuetype_id)
        {
            return tables\Issues::getTable()->getCountsByProjectIDandIssuetype($project_id, $issuetype_id);
        }

        /**
         * Count the number of open and closed issues for a specific project id
         * and milestone id
         *
         * @param integer $project_id The project ID
         * @param integer $milestone_id The milestone ID
         * @param array $allowed_status_ids
         *
         * @return array
         */
        public static function getIssueCountsByProjectIDandMilestone($project_id, $milestone_id, $allowed_status_ids = [])
        {
            return tables\Issues::getTable()->getCountsByProjectIDandMilestone($project_id, $milestone_id, $allowed_status_ids);
        }

        /**
         * Retrieves issue by identifier, taking into account access
         * permissions.
         *
         * This method behaves the same as Issue::getIssueFromLink method,
         * except it performs an additional permission check to ensure
         * the current user has access to the issue.
         *
         * @param string $identifier Issue identifier.
         *
         * @return Issue
         */
        public static function getIssue($identifier)
        {
            $issue = self::getIssueFromLink($identifier);

            if ($issue instanceof Issue && $issue->hasAccess()) {
                return $issue;
            }

            return null;
        }

        /**
         * Returns a \pachno\core\entities\Issue from an issue no
         *
         * @param string $issue_number An integer or issue number
         *
         * @return Issue
         */
        public static function getIssueFromLink($issue_number)
        {
            $project = Context::getCurrentProject();
            $found_issue = null;
            $issue_no = self::extractIssueNoFromNumber($issue_number);
            if (is_numeric($issue_no)) {
                try {
                    if (!$project instanceof Project) return null;
                    if ($project->usePrefix()) return null;
                    $found_issue = tables\Issues::getTable()->getByProjectIDAndIssueNo($project->getID(), (integer)$issue_no);
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                $issue_no = explode('-', mb_strtoupper($issue_no));
                Logging::log('exploding');
                if (count($issue_no) == 2 && ($found_issue = tables\Issues::getTable()->getByPrefixAndIssueNo($issue_no[0], $issue_no[1])) instanceof Issue) {
                    if (!$found_issue->getProject()->usePrefix()) return null;
                }
                Logging::log('exploding done');
            }

            return ($found_issue instanceof Issue) ? $found_issue : null;
        }

        /**
         * Extract issue no from issue integer or string with prefix '#'.
         *
         * @param string $issue_number An integer or issue number
         *
         * @return string
         */
        public static function extractIssueNoFromNumber($issue_number)
        {
            $issue_no = mb_strtolower(trim($issue_number));
            if (mb_strpos($issue_no, ' ') !== false) {
                $issue_no = mb_substr($issue_no, strrpos($issue_no, ' ') + 1);
            }
            if (mb_substr($issue_no, 0, 1) == '#') $issue_no = mb_substr($issue_no, 1);

            return $issue_no;
        }

        /**
         * Returns the project for this issue
         *
         * @return Project
         */
        public function getProject(): Project
        {
            return $this->_b2dbLazyLoad('_project_id');
        }

        /**
         * Whether or not the current or target user can access the issue
         *
         * @param null $target_user
         *
         * @return boolean
         */
        public function hasAccess($target_user = null)
        {
            Logging::log('checking access to issue ' . $this->getFormattedIssueNo());
            $i_id = $this->getID();
            $user = ($target_user === null) ? Context::getUser() : $target_user;
            $specific_access = $user->hasPermission("canviewissue", $i_id, 'core');
            if ($specific_access !== null) {
                Logging::log('done checking, returning specific access ' . (($specific_access) ? 'allowed' : 'denied'));

                return $specific_access;
            }
            if ($this->getPostedByID() == $user->getID()) {
                Logging::log('done checking, allowed since this user posted it');

                return true;
            }
            if ($this->getOwner() instanceof User && $this->getOwner()->getID() == $user->getID()) {
                Logging::log('done checking, allowed since this user owns it');

                return true;
            }
            if ($this->getAssignee() instanceof User && $this->getAssignee()->getID() == $user->getID()) {
                Logging::log('done checking, allowed since this user is assigned to it');

                return true;
            }
            if ($user->hasPermission('canseegroupissues', 0, 'core') &&
                $this->getPostedBy() instanceof User &&
                $this->getPostedBy()->getGroupID() == $user->getGroupID()) {
                Logging::log('done checking, allowed since this user is in same group as user that posted it');

                return true;
            }
            if ($user->hasPermission('canseeallissues', $this->getProjectID(), 'core') === true) {

                if ($this->getProject()->hasAccess($user)) {
                    Logging::log('done checking, allowed since this user may see all issues in this project');

                    return true;
                }
            }
            if ($user->hasPermission('canseeallissues', 0, 'core') === false) {
                Logging::log('done checking, not allowed to access issues not posted by themselves');

                return false;
            }
            if ($this->isLockedCategory() && $this->getCategory() instanceof Category && !$this->getCategory()->hasAccess($user)) {
                Logging::log('done checking, not allowed to access issues in this category');

                return false;
            }
            if ($this->getProject()->hasAccess($user)) {
                Logging::log('done checking, can access project');

                return true;
            }
            Logging::log('done checking, denied');

            return false;
        }

        /**
         * Returns a complete issue no
         *
         * @param boolean $link_formatted [optional] Whether to include the # if it's only numeric (default false)
         *
         * @return string
         */
        public function getFormattedIssueNo($link_formatted = false): string
        {
            if ($this->getProject()->usePrefix()) {
                return $this->getProject()->getPrefix() . '-' . $this->getIssueNo();
            } else {
                return (string) (($link_formatted) ? '#' : '') . $this->getIssueNo();
            }
        }

        /**
         * Returns the issue type for this issue
         *
         * @return Issuetype
         */
        public function getIssueType()
        {
            return $this->_b2dbLazyLoad('_issuetype');
        }

        /**
         * Set the issue type
         *
         * @param integer $issuetype_id The issue type ID you want to set
         */
        public function setIssuetype($issuetype_id)
        {
            $this->_addChangedProperty('_issuetype', $issuetype_id);
            $project = $this->getProject();
            $issueType = Issuetype::getB2DBTable()->selectById($issuetype_id);
            if (!$issueType instanceof Issuetype || !$project instanceof Project) {
                return;
            }
            $workflowStep = $project->getWorkflowScheme()->getWorkflowForIssuetype($issueType)->getFirstStep();
            if (!$workflowStep instanceof WorkflowStep) {
                return;
            }
            if ($workflowStep->hasLinkedStatus()) {
                $this->_addChangedProperty('_status', $workflowStep->getLinkedStatusID());
            }
            $this->_addChangedProperty('_workflow_step_id', $workflowStep->getID());
        }

        /**
         * Returns the issue no for this issue
         *
         * @return string
         */
        public function getIssueNo(): string
        {
            return $this->_issue_no;
        }

        /**
         * Set the issue no
         *
         * @param integer $no
         */
        public function setIssueNo($no)
        {
            $this->_issue_no = $no;
        }

        /**
         * Return the poster id
         *
         * @return integer
         */
        public function getPostedByID()
        {
            $poster = $this->getPostedBy();

            return ($poster instanceof common\Identifiable) ? $poster->getID() : null;
        }

        /**
         * Return the poster
         *
         * @return User
         */
        public function getPostedBy(): ?User
        {
            $this->_posted_by = $this->_b2dbLazyLoad('_posted_by');

            return $this->_posted_by;
        }

        /**
         * Set issue poster
         *
         * @param common\Identifiable|integer $poster The user/team you want to have posted the issue
         */
        public function setPostedBy($poster)
        {
            $posted_by_id = ($poster instanceof common\Identifiable) ? $poster->getID() : $poster;
            $this->_addChangedProperty('_posted_by', $posted_by_id);
        }

        /**
         * Return the current owner
         *
         * @return common\Identifiable
         */
        public function getOwner()
        {
            $this->_b2dbLazyLoad('_owner_team');
            $this->_b2dbLazyLoad('_owner_user');

            if ($this->_owner_team instanceof Team) {
                return $this->_owner_team;
            } elseif ($this->_owner_user instanceof User) {
                return $this->_owner_user;
            } else {
                return null;
            }
        }

        /**
         * Return the currently assigned user or team
         *
         * @return common\Identifiable
         */
        public function getAssignee(): ?common\Identifiable
        {
            $this->_b2dbLazyLoad('_assignee_team');
            $this->_b2dbLazyLoad('_assignee_user');

            if ($this->_assignee_team instanceof Team) {
                return $this->_assignee_team;
            } elseif ($this->_assignee_user instanceof User) {
                return $this->_assignee_user;
            } else {
                return null;
            }
        }

        /**
         * Returns the project id for this issue
         *
         * @return integer
         */
        public function getProjectID()
        {
            $project = $this->getProject();

            return ($project instanceof Project) ? $project->getID() : null;
        }

        /**
         * Returns whether or not this item is locked to category
         *
         * @return boolean
         * @access public
         */
        public function isLockedCategory()
        {
            return $this->_locked_category;
        }

        /**
         * Specify whether or not this item is locked to category
         *
         * @param boolean $locked [optional]
         */
        public function setLockedCategory($locked = true)
        {
            $this->_locked_category = (bool)$locked;
        }

        /**
         * Returns the category
         *
         * @return Category
         */
        public function getCategory()
        {
            return $this->_b2dbLazyLoad('_category');
        }

        /**
         * Set the category
         *
         * @param integer $category_id The category ID to change to
         */
        public function setCategory($category_id)
        {
            $this->_addChangedProperty('_category', $category_id);
        }

        /**
         * Finds issues that contain the passed-in text in one of
         * their (text-based) fields (such as title, description,
         * custom fields etc). Only a limited number of results is
         * returned - see Issue::findIssues for default.
         *
         * @param string $text Text to search for in issue fields.
         * @param Project $project Project to limit the search under. If null, issues are search for within all projects.
         *
         * @return List of matched issues.
         */
        public static function findIssuesByText($text, $project = null)
        {
            $filters = ['text' => SearchFilter::createFilter('text', ['v' => $text, 'o' => '='])];

            if ($project instanceof Project) {
                $filters['project_id'] = SearchFilter::createFilter('project_id', ['v' => $project->getID(), 'o' => '=']);
            }

            list($issues, $total_count) = self::findIssues($filters);

            return $issues;
        }

        /**
         * Finds all issues satisfying the passed-in filters to which
         * the current user has access.
         *
         * Method comes with paging controls and ability to perform
         * sorting/grouping of issues.
         *
         * @param array $filters Filters for matching the issues. Each element should be an instance of \pachno\core\entities\SearchFilter.
         * @param int $results_per_page Number of results per page.
         * @param int $offset Offset (in number of issues, not pages) for performing paged searches.
         * @param string $groupby Group issues by field. Supported values are:
         *   category, status, milestone, assignee, posted_by, state, posted, severity, user_pain, votes, resolution, edition, build, component.
         * @param string $grouporder Sorting order for returned issues. Set to 'asc' for ascending order, anything else for descending.
         * @param array $sortfields Fields to sort issues by, in order of preference.
         * @param bool $include_deleted Specify if deleted issues should be included in the search or not.
         *
         * @return Array where first element is an array of matched issues (taking into account offsent and results per page), and second is the total count of issues found.
         */
        public static function findIssues($filters = [], $results_per_page = 30, $offset = 0, $groupby = null, $grouporder = null, $sortfields = [tables\Issues::LAST_UPDATED => 'desc'], $include_deleted = false)
        {
            $issues = [];
            list ($rows, $count, $ids, $sums) = tables\Issues::getTable()->findIssues($filters, $results_per_page, $offset, $groupby, $grouporder, $sortfields, $include_deleted);
            if ($rows) {
                if (Context::isProjectContext()) {
                    Context::getCurrentProject()->preloadValues();
                }
                tables\IssueCustomFields::getTable()->preloadValuesByIssueIDs($ids);
                $build_ids = tables\IssueAffectsBuild::getTable()->preloadValuesByIssueIDs($ids);
                tables\Builds::getTable()->preloadBuilds($build_ids);
                $edition_ids = tables\IssueAffectsEdition::getTable()->preloadValuesByIssueIDs($ids);
                tables\Editions::getTable()->preloadEditions($edition_ids);
                $component_ids = tables\IssueAffectsComponent::getTable()->preloadValuesByIssueIDs($ids);
                tables\Components::getTable()->preloadComponents($component_ids);
                tables\Comments::getTable()->preloadCommentCounts(Comment::TYPE_ISSUE, $ids);
                tables\IssueFiles::getTable()->preloadIssueFileCounts($ids);
                tables\IssueRelations::getTable()->preloadIssueRelations($ids);
                $user_ids = [];
                foreach ($rows as $key => $row) {
                    try {
                        $issue = new Issue($row->get(tables\Issues::ID), $row);
                        $user_ids[$row['issues.posted_by']] = true;
                        $issues[] = $issue;
                        $issue->setSums($sums[$row->get(tables\Issues::ID)]);
                        unset($rows[$key]);
                    } catch (Exception $e) {
                    }
                }
                if (count($user_ids)) {
                    tables\Users::getTable()->preloadUsers(array_keys($user_ids));
                }
                foreach ($issues as $key => $issue) {
                    if (!$issue->hasAccess() || $issue->getProject()->isDeleted()) {
                        unset($issues[$key]);
                    }
                }
                tables\IssueCustomFields::getTable()->clearPreloadedValues();
                tables\IssueAffectsBuild::getTable()->clearPreloadedValues();
                tables\IssueAffectsEdition::getTable()->clearPreloadedValues();
                tables\IssueAffectsComponent::getTable()->clearPreloadedValues();
                tables\Comments::getTable()->clearPreloadedCommentCounts(Comment::TYPE_ISSUE);
                tables\IssueFiles::getTable()->clearPreloadedIssueFileCounts();
            }

            return [$issues, $count];
        }

        /**
         * Runs one or more regular expressions against a supplied text, extracts
         * issue numbers from it, and then obtains corresponding issues. The
         * function will also obtain information about transitions (if this was
         * specified in the text). This data can be used for transitioning the
         * issues through a workflow.
         *
         * Once the function finishes processing, it will return an array of format:
         *
         * array('issues' => issues, 'transitions' => transitions).
         *
         * issues is an array consisting of \pachno\core\entities\Issue instances.
         *
         * transitions is an array containing transition arrays. The transition
         * arrays are accessed with issue numbers as keys (e.g. 'PREFIX-1',
         * 'PREFIX-5' or '2', '3' etc). Each transition array has the following
         * format:
         *
         * array(0 => command, 1 => parameters)
         *
         * command is a string representing the transision command (for example
         * 'Resolve issue') from the workflow definition. parameters is an array
         * that contains parameters and their values that should be passed to the
         * transition step:
         *
         * array( 'PARAM1' => 'VALUE1', 'PARAM2' => 'VALUE2', ...)
         *
         *
         * @param string $text Text that should be parsed for issue numbers and transitions.
         *
         * @return array An array with two elements, one denoting the matched issues, one
         * denoting the transitions for issues. These elements can be accessed using
         * keys 'issues', and 'transitions'. The key 'issues' can be used for
         * accessing an array made-up of \pachno\core\entities\Issue instances. The key 'transitions'
         * can be used for accessing an array containing transition information
         * about each issue. The 'transitions' array uses issue numbers as keys,
         * and contains ordered transition information (see above for detailed
         * description of format).
         */
        public static function getIssuesFromTextByRegex($text)
        {
            $issue_match_regexes = TextParser::getIssueRegex();
            $issue_numbers = []; // Issue numbers
            $issues = []; // Issue objects
            $transitions = []; // Transition information

            // Iterate over all regular expressions that should be used for
            // issue/transition matching in commit message.
            foreach ($issue_match_regexes as $issue_match_regex) {
                $matched_issue_data = []; // All data from regexp

                $lines = explode("\n", $text);
                foreach ($lines as $line) {
                    if (mb_substr($line, -1) == "\r") {
                        $line = mb_substr($line, 0, -1);
                    }

                    // If any match is found using the current regular expression, extract
                    // the information.
                    if (preg_match_all($issue_match_regex, $line, $matched_issue_data)) {

                        // Identified issues are kept inside of named regex group.
                        foreach ($matched_issue_data["issues"] as $key => $issue_number) {
                            // Get the matched transitions for the issue.
                            $matched_issue_transitions = $matched_issue_data["transitions"][$key];

                            // Create an empty array to store transitions for an issue. Don't
                            // overwrite it. Use issue number as key for transitions.
                            if (!array_key_exists($issue_number, $transitions)) {
                                $transitions[$issue_number] = [];
                            }

                            // Add the transition information (if any) for an issue.
                            if ($matched_issue_transitions) {
                                // Parse the transition information. Each transition string is in
                                // format:
                                // 'TRANSITION1: PARAM1_1=VALUE1_1 PARAM1_2=VALUE1_2; TRANSITION2: PARAM2_1=VALUE2_1 PARAM2_2=VALUE2_2'
                                foreach (explode("; ", $matched_issue_transitions) as $transition) {
                                    // Split command from its parameters.
                                    $transition_data = explode(": ", $transition);
                                    $transition_command = $transition_data[0];
                                    // Set-up array that will contain parameters
                                    $transition_parameters = [];

                                    // Process parameters if they were present.
                                    if (count($transition_data) == 2) {
                                        // Split into induvidual parameters.
                                        foreach (explode(" ", $transition_data[1]) as $parameter) {
                                            // Only process proper parameters (of format 'PARAM=VALUE')
                                            if (mb_strpos($parameter, '=')) {
                                                list($param_key, $param_value) = explode('=', $parameter);
                                                $transition_parameters[$param_key] = $param_value;
                                            }
                                        }
                                    }
                                    // Append the transition information for the current issue number.
                                    $transitions[$issue_number][] = [$transition_command, $transition_parameters];
                                }
                            }

                            // Add the issue number to the list.
                            $issue_numbers[] = $issue_number;
                        }

                    }
                }
            }

            // Make sure that each issue gets procssed only once for a single commit
            // (avoid duplication of commits).
            $unique_issue_numbers = array_unique($issue_numbers);

            // Fetch all issues affected by the commit.
            foreach ($unique_issue_numbers as $issue_no) {
                $issue = Issue::getIssueFromLink($issue_no);
                if ($issue instanceof Issue) $issues[] = $issue;
            }

            // Return array consisting out of two arrays - one with Issue
            // instances, and the second one with transition information for those
            // issues.
            return compact('issues', 'transitions');
        }

        /**
         * Class constructor
         *
         * @param Row $row
         */
        public function _construct(Row $row, $foreign_key = null)
        {
            $this->_initializeCustomfields();
            $this->_num_user_comments = tables\Comments::getTable()->getPreloadedCommentCount(Comment::TYPE_ISSUE, $this->_id);
            $this->_num_files = tables\IssueFiles::getTable()->getPreloadedIssueFileCount($this->_id);
//            if ($this->isDeleted())
//            {
//                throw new \Exception(framework\Context::geti18n()->__('This issue has been deleted'));
//            }
        }

        protected function _initializeCustomfields()
        {
            foreach (CustomDatatype::getAll() as $key => $customdatatype) {
                $var_name = "_customfield" . $key;
                $this->$var_name = null;
            }
            if ($rows = tables\IssueCustomFields::getTable()->getAllValuesByIssueID($this->getID())) {
                foreach ($rows as $row) {
                    $datatype = CustomDatatype::getB2DBTable()->selectById($row->get(tables\IssueCustomFields::CUSTOMFIELDS_ID));
                    if ($datatype instanceof CustomDatatype) {
                        $var_name = "_customfield" . $datatype->getKey();

                        if ($datatype->hasCustomOptions()) {
                            $option = tables\CustomFieldOptions::getTable()->selectById((int)$row->get(tables\IssueCustomFields::CUSTOMFIELDOPTION_ID));
                            if ($option instanceof CustomDatatypeOption) {
                                $this->$var_name = $option;
                            }
                        } elseif ($datatype->hasPredefinedOptions()) {
                            $this->$var_name = $row->get(tables\IssueCustomFields::CUSTOMFIELDOPTION_ID);
                        } else {
                            $this->$var_name = $row->get(tables\IssueCustomFields::OPTION_VALUE);
                        }
                    }
                }
            }
        }

        /**
         * Print the issue number and title nicely formatted
         *
         * @param boolean $link_formatted [optional] Whether to include the # if it's only numeric (default false)
         *
         * @return string
         */
        public function getFormattedTitle($link_formatted = false): string
        {
            return $this->getFormattedIssueNo($link_formatted) . ' - ' . $this->getTitle();
        }

        /**
         * Returns the issue title
         *
         * @return string
         */
        public function getTitle(): string
        {
            return htmlentities($this->_title, ENT_COMPAT, Context::getI18n()->getCharset());
        }

        /**
         * Set the title
         *
         * @param string $title The new title to set
         */
        public function setTitle($title)
        {
            if (trim($title) == '') {
                throw new Exception("Can't set an empty title");
            }
            $this->_addChangedProperty('_title', $title);
        }

        public function getAccessList()
        {
            $permissions = tables\Permissions::getTable()->getByPermissionTargetIDAndModule('canviewissue', $this->getID());

            return $permissions;
        }

        public function setProject($project)
        {
            $this->_project_id = $project;
        }

        /**
         * Return the current workflow
         *
         * @return Workflow
         */
        public function getWorkflow()
        {
            return $this->getProject()->getWorkflowScheme()->getWorkflowForIssuetype($this->getIssueType());
        }

        public function setWorkflowStep(WorkflowStep $step)
        {
            $this->_addChangedProperty('_workflow_step_id', $step->getID());
        }

        /**
         * Adds a property to list of changed properties
         *
         * @param string $property The property key that was changed
         * @param mixed $value The new value
         */
        protected function _addChangedProperty($property, $value)
        {
            if ($this->_id && !defined('bin/pachno')) {
                if ($value instanceof Identifiable) {
                    $value = $value->getID();
                }

                if (!property_exists($this, $property)) {
                    $this->$property = null;
                } elseif ($this->$property instanceof Identifiable) {
                    $this->$property = $this->$property->getID();
                }

                if ($this->$property != $value) {
                    if (array_key_exists($property, $this->_changed_items)) {
                        if ($this->_changed_items[$property]['original_value'] == $value) {
                            unset($this->_changed_items[$property]);
                        } else {
                            $this->_changed_items[$property]['current_value'] = $value;
                        }
                    } else {
                        $this->_changed_items[$property] = [
                            'original_value' => $this->$property,
                            'current_value' => $value
                        ];
                    }
                    $this->$property = $value;
                }
            } else {
                $this->$property = $value;
            }
        }

        /**
         * Returns an array of workflow transitions
         *
         * @return WorkflowTransition[]
         */
        public function getAvailableWorkflowStatusIDsAndTransitions()
        {
            $status_ids = [];
            $transitions = [];
            $available_statuses = Status::getAll();
            $rule_status_valid = false;

            if (!$this->isWorkflowTransitionsAvailable()) return [$status_ids, $transitions, $rule_status_valid];

            foreach ($this->getAvailableWorkflowTransitions() as $transition) {
                if ($transition->getOutgoingStep()->hasLinkedStatus()) {
                    $status_ids[] = $transition->getOutgoingStep()->getLinkedStatusID();

                    if (!isset($transitions[$transition->getOutgoingStep()->getLinkedStatusID()]))
                        $transitions[$transition->getOutgoingStep()->getLinkedStatusID()] = [];

                    $transitions[$transition->getOutgoingStep()->getLinkedStatusID()][] = $transition;
                } elseif ($transition->hasPostValidationRule(WorkflowTransitionValidationRule::RULE_STATUS_VALID)) {
                    $values = explode(',', $transition->getPostValidationRule(WorkflowTransitionValidationRule::RULE_STATUS_VALID)->getRuleValue());

                    foreach ($values as $value) {
                        if (!array_key_exists($value, $available_statuses)) continue;
                        if (!$rule_status_valid) $rule_status_valid = true;
                        if (!isset($transitions[$value])) $transitions[$value] = [];

                        $transitions[$value][] = $transition;
                        $status_ids[] = $value;
                    }
                }
            }

            return [$status_ids, $transitions, $rule_status_valid];
        }

        public function isWorkflowTransitionsAvailable()
        {
            return $this->getProject()->isArchived() ? false : $this->_permissionCheck('cantransitionissue');
        }

        /**
         * Perform a permission check based on a key, and whether or not to
         * check for the equivalent "*own" permission if the issue is posted
         * by the same user
         *
         * @param string $key The permission key to check for
         * @param boolean $exclusive Whether to perform a similar check for "own"
         *
         * @return boolean
         */
        protected function _permissionCheck($key, $exclusive = false, $defaultPermissiveSetting = true)
        {
            if (Context::getUser()->isGuest()) return false;
            if (isset($this->_can_permission_cache[$key])) return $this->_can_permission_cache[$key];
            $permitted = ($this->isInvolved() && !$exclusive) ? $this->getProject()->permissionCheck($key . 'own', true) : null;
            $permitted = ($permitted !== null) ? $permitted : $this->getProject()->permissionCheck($key, !$this->isInvolved());

            if ($defaultPermissiveSetting) {
                $permitted = ($permitted !== null) ? $permitted : Settings::isPermissive();
            }

            $this->_can_permission_cache[$key] = $permitted;

            return $permitted;
        }

        public function isInvolved()
        {
            $user_id = Context::getUser()->getID();

            return (bool)($this->getPostedByID() == $user_id || ($this->isAssigned() && $this->getAssignee()->getID() == $user_id && $this->getAssignee() instanceof User) || ($this->isOwned() && $this->getOwner()->getID() == $user_id && $this->getOwner() instanceof User));
        }

        public function isAssigned(): bool
        {
            return (bool)($this->getAssignee() instanceof common\Identifiable);
        }

        public function isOwned()
        {
            return (bool)($this->getOwner() instanceof common\Identifiable);
        }

        /**
         * Returns an array of workflow transitions
         *
         * @return WorkflowTransition[]
         */
        public function getAvailableWorkflowTransitions()
        {
            return ($this->getWorkflowStep() instanceof WorkflowStep && $this->isWorkflowTransitionsAvailable()) ? $this->getWorkflowStep()->getAvailableTransitionsForIssue($this) : [];
        }

        /**
         * Return the issues current step in the workflow
         *
         * @return WorkflowStep
         */
        public function getWorkflowStep()
        {
            return $this->_b2dbLazyLoad('_workflow_step_id');
        }

        /**
         * Get current available statuses
         *
         * @return Status[]
         */
        public function getAvailableStatuses()
        {
            $statuses = [];

            if (!$this->isWorkflowTransitionsAvailable()) return $statuses;

            $available_statuses = Status::getAll();
            foreach ($this->getAvailableWorkflowTransitions() as $transition) {
                if ($transition->getOutgoingStep()->hasLinkedStatus()) {
                    if ($status = $transition->getOutgoingStep()->getLinkedStatus()) {
                        $statuses[$status->getID()] = $status;
                    }
                } elseif ($transition->hasPostValidationRule(WorkflowTransitionValidationRule::RULE_STATUS_VALID)) {
                    $values = explode(',', $transition->getPostValidationRule(WorkflowTransitionValidationRule::RULE_STATUS_VALID)->getRuleValue());
                    foreach ($values as $value) {
                        if (array_key_exists($value, $available_statuses)) {
                            $statuses[$value] = $available_statuses[$value];
                        }
                    }
                }
            }

            return $statuses;
        }

        /**
         * Returns the title for this issue
         *
         * @return string
         */
        public function getName()
        {
            return $this->getTitle();
        }

        /**
         * Whether or not this issue is a duplicate of another issue
         *
         * @return boolean
         */
        public function isDuplicate()
        {
            return ($this->getDuplicateOf() instanceof Issue) ? true : false;
        }

        /**
         * Returns the issue which this is a duplicate of
         *
         * @return Issue
         */
        public function getDuplicateOf()
        {
            return $this->_b2dbLazyLoad('_duplicate_of');
        }

        /**
         * Mark this issue as a duplicate of another issue
         *
         * @param integer $d_id Issue ID for the duplicated issue
         */
        public function setDuplicateOf($d_id)
        {
            tables\Issues::getTable()->setDuplicate($this->getID(), $d_id);
            if ($d_id) {
                tables\UserIssues::getTable()->copyStarrers($this->getID(), $d_id);
            }
            $this->_duplicate_of = $d_id;
        }

        /**
         * Clears the issue from being a duplicate
         */
        public function clearDuplicate()
        {
            $this->setDuplicateOf(0);
        }

        public function hasDuplicateIssues()
        {
            return (bool)$this->getNumberOfDuplicateIssues();
        }

        public function getNumberOfDuplicateIssues()
        {
            return count($this->getDuplicateIssues());
        }

        /**
         * Returns an array of all issues which are duplicates of this one
         *
         * @return Issue[]
         */
        public function getDuplicateIssues()
        {
            $this->_populateDuplicateIssues();

            return $this->_duplicate_issues;
        }

        /**
         * populates list of issues which are duplicates of this one
         */
        protected function _populateDuplicateIssues()
        {
            if ($this->_duplicate_issues === null) {
                $this->_b2dbLazyLoad('_duplicate_issues');
                foreach ($this->_duplicate_issues as $issue_id => $issue) {
                    if (!$issue->hasAccess()) unset($this->_duplicate_issues[$issue_id]);
                }
            }
        }

        /**
         * Returns whether or not this item is locked
         *
         * @return boolean
         * @access public
         */
        public function isUnlocked()
        {
            return !$this->isLocked();
        }

        /**
         * Returns whether or not this item is locked
         *
         * @return boolean
         * @access public
         */
        public function isLocked()
        {
            return $this->_locked;
        }

        /**
         * Specify whether or not this item is locked
         *
         * @param boolean $locked [optional]
         */
        public function setLocked($locked = true)
        {
            $this->_locked = (bool)$locked;
        }

        /**
         * Returns whether or not this item is locked to category
         *
         * @return boolean
         * @access public
         */
        public function isUnlockedCategory()
        {
            return !$this->isLockedCategory();
        }

        /**
         * Specify whether or not this item is locked / locked to category based on project new issues lock type
         *
         * @param Project $project
         */
        public function setLockedFromProject(Project $project)
        {
            switch ($project->getIssuesLockType()) {
                case Project::ISSUES_LOCK_TYPE_PUBLIC_CATEGORY:
                    $this->setLocked(false);
                    $this->setLockedCategory(true);
                    break;
                case Project::ISSUES_LOCK_TYPE_PUBLIC:
                    $this->setLocked(false);
                    $this->setLockedCategory(false);
                    break;
                case Project::ISSUES_LOCK_TYPE_RESTRICTED:
                    $this->setLocked(true);
                    $this->setLockedCategory(false);
                    break;
            }
        }

        public function isEditable()
        {
            if ($this->_editable !== null) return $this->_editable;

            if ($this->getProject()->isArchived()) $this->_editable = false;
            else $this->_editable = ($this->isOpen() && ($this->getProject()->useStrictWorkflowMode() || ($this->getWorkflowStep() instanceof WorkflowStep && $this->getWorkflowStep()->isEditable())));

            return $this->_editable;
        }

        /**
         * Whether or not the issue is open
         *
         * @return boolean
         * @see isClosed()
         *
         * @see getState()
         */
        public function isOpen()
        {
            return !$this->isClosed();
        }

        /**
         * Whether or not the issue is closed
         *
         * @return boolean
         * @see isOpen()
         *
         * @see getState()
         */
        public function isClosed(): bool
        {
            return $this->getState() == self::STATE_CLOSED;
        }

        /**
         * Returns the issues state
         *
         * @return integer
         */
        public function getState(): int
        {
            return $this->_state;
        }

        /**
         * Set the issue state
         *
         * @param integer $state The state
         */
        public function setState($state)
        {
            if (!in_array($state, [self::STATE_CLOSED, self::STATE_OPEN])) {
                return false;
            }

            $this->_addChangedProperty('_state', $state);

            return true;
        }

        public function isUpdateable()
        {
            if ($this->_updateable !== null) return $this->_updateable;

            if ($this->getProject()->isArchived()) $this->_updateable = false;
            else $this->_updateable = ($this->isOpen() && ($this->getProject()->useStrictWorkflowMode() || !$this->getWorkflowStep() instanceof WorkflowStep || !$this->getWorkflowStep()->isClosed()));

            return $this->_updateable;
        }

        /**
         * Return if the user can edit title
         *
         * @return boolean
         */
        public function canEditAccessPolicy()
        {
            return $this->_permissionCheck('canlockandeditlockedissues', true);
        }

        /**
         * Check whether or not this user can edit issue details
         *
         * @return boolean
         */
        public function canEditIssueDetails()
        {
            return $this->_permissionCheck('caneditissuebasic');
        }

        /**
         * Return if the user can edit title
         *
         * @return boolean
         */
        public function canEditTitle()
        {
            return $this->_permissionCheck('caneditissuetitle');
        }

        /**
         * Return if the user can edit description
         *
         * @return boolean
         */
        public function canEditIssuetype()
        {
            return $this->_permissionCheck('caneditissuebasic');
        }

        /**
         * Return if the user can edit description
         *
         * @return boolean
         */
        public function canEditUserPain()
        {
            return $this->_permissionCheck('caneditissueuserpain');
        }

        /**
         * Return if the user can edit description
         *
         * @return boolean
         */
        public function canEditDescription()
        {
            return $this->_permissionCheck('caneditissuedescription');
        }

        /**
         * Return if the user can edit shortname
         *
         * @return boolean
         */
        public function canEditShortname()
        {
            return $this->_permissionCheck('caneditissueshortname');
        }

        /**
         * Return if the user can edit description
         *
         * @return boolean
         */
        public function canEditReproductionSteps()
        {
            return $this->_permissionCheck('caneditissuereproduction_steps');
        }

        /**
         * Return if the user can edit basic parameters
         *
         * @return boolean
         */
        public function canEditIssue()
        {
            return $this->_permissionCheck('caneditissue', true);
        }

        /**
         * Return if the user can edit posted by
         *
         * @return boolean
         */
        public function canEditPostedBy()
        {
            return $this->_permissionCheck('caneditissueposted_by');
        }

        /**
         * Return if the user can edit assigned to
         *
         * @return boolean
         */
        public function canEditAssignee()
        {
            return $this->_permissionCheck('caneditissueassigned_to');
        }

        /**
         * Return if the user can edit owned by
         *
         * @return boolean
         */
        public function canEditOwner()
        {
            return $this->_permissionCheck('caneditissueowned_by');
        }

        /**
         * Return if the user can edit status
         *
         * @return boolean
         */
        public function canEditStatus()
        {
            return $this->_canEditIssueField("status");
        }

        protected function _canEditIssueField($type)
        {
            return $this->_permissionCheck('caneditissue' . $type) || ($this->isInvolved() && $this->_permissionCheck("set_datatype_" . $type));
        }

        /**
         * Return if the user can edit category
         *
         * @return boolean
         */
        public function canEditCategory()
        {
            return $this->_canEditIssueField("category");
        }

        /**
         * Return if the user can edit resolution
         *
         * @return boolean
         */
        public function canEditResolution()
        {
            return $this->_canEditIssueField("resolution");
        }

        /**
         * Return if the user can edit reproducability
         *
         * @return boolean
         */
        public function canEditReproducability()
        {
            return $this->_canEditIssueField("reproducability");
        }

        /**
         * Return if the user can edit severity
         *
         * @return boolean
         */
        public function canEditSeverity()
        {
            return $this->_canEditIssueField("severity");
        }

        /**
         * Return if the user can edit priority
         *
         * @return boolean
         */
        public function canEditPriority()
        {
            return $this->_canEditIssueField("priority");
        }

        /**
         * Return if the user can edit estimated time
         *
         * @return boolean
         */
        public function canEditEstimatedTime()
        {
            return $this->_permissionCheck('caneditissueestimated_time');
        }

        /**
         * Return if the user can edit progress (percent)
         *
         * @return boolean
         */
        public function canEditPercentage()
        {
            return $this->_permissionCheck('caneditissuepercent_complete');
        }

        /**
         * Return if the user can edit milestone
         *
         * @return boolean
         */
        public function canEditMilestone()
        {
            return $this->_permissionCheck('caneditissuemilestone');
        }

        /**
         * Return if the user can delete the issue
         *
         * @return boolean
         */
        public function canDeleteIssue()
        {
            return $this->_permissionCheck('candeleteissues', false);
        }

        /**
         * Return if the user can edit any custom fields
         *
         * @return boolean
         */
        public function canEditCustomFields($key = '')
        {
            $permission_key = 'caneditissuecustomfields' . $key;

            return $this->_permissionCheck($permission_key);
        }

        /**
         * Return if the user can close the issue
         *
         * @return boolean
         */
        public function canCloseIssue()
        {
            return $this->_permissionCheck('cancloseissues');
        }

        /**
         * Return if the user can close or reopen the issue
         *
         * @return boolean
         */
        public function canReopenIssue()
        {
            return $this->_permissionCheck('canreopenissues');
        }

        /**
         * Return if the user can post comments on this issue
         *
         * @return boolean
         */
        public function canPostComments()
        {
            return $this->_permissionCheck('canpostcomments');
        }

        /**
         * Return if the user can attach files
         *
         * @return boolean
         */
        public function canAttachFiles()
        {
            return $this->_permissionCheck('canaddfilestoissues');
        }

        /**
         * Return if the user can add related issues to this issue
         *
         * @return boolean
         */
        public function canAddRelatedIssues()
        {
            return $this->_permissionCheck('canaddrelatedissues');
        }

        /**
         * Return if the user can add related issues to this issue
         *
         * @return boolean
         */
        public function canEditAffectedComponents()
        {
            return $this->_permissionCheck('canaddcomponents');
        }

        /**
         * Return if the user can add related issues to this issue
         *
         * @return boolean
         */
        public function canEditAffectedEditions()
        {
            return $this->_permissionCheck('canaddeditions');
        }

        /**
         * Return if the user can add related issues to this issue
         *
         * @return boolean
         */
        public function canEditAffectedBuilds()
        {
            return $this->_permissionCheck('canaddbuilds');
        }

        /**
         * Return if the user can remove attachments
         *
         * @return boolean
         */
        public function canRemoveAttachments()
        {
            return $this->_permissionCheck('canremovefilesfromissues');
        }

        /**
         * Return if the user can attach links
         *
         * @return boolean
         */
        public function canAttachLinks()
        {
            return $this->_permissionCheck('canaddlinkstoissues');
        }

        /**
         * Return if the user can start working on the issue
         *
         * @return boolean
         */
        public function canStartWorkingOnIssue()
        {
            if ($this->isBeingWorkedOn()) return false;

            return $this->canEditSpentTime();
        }

        /**
         * Return whether or not this issue is being worked on by a user
         *
         * @return boolean
         */
        public function isBeingWorkedOn()
        {
            return ($this->getUserWorkingOnIssue() instanceof User) ? true : false;
        }

        /**
         * Return the user working on this issue if any
         *
         * @return User
         */
        public function getUserWorkingOnIssue()
        {
            return ($this->getAssignee() instanceof User) ? $this->getAssignee() : null;
        }

        /**
         * Return if the user can edit spent time
         *
         * @return boolean
         */
        public function canEditSpentTime()
        {
            return $this->_permissionCheck('caneditissuespent_time');
        }

        /**
         * Set the created at time
         *
         * @param integer $time
         *
         * @see Issue::setPosted()
         */
        public function setCreatedAt($time)
        {
            $this->setPosted($time);
        }

        public function isEditionAffected(Edition $edition)
        {
            $editions = $this->getEditions();
            if (count($editions)) {
                foreach ($editions as $info) {
                    if ($info['edition']->getID() == $edition->getID())
                        return true;
                }
            }

            return false;
        }

        /**
         * Returns the editions for this issue
         *
         * @return array Returns an array with 'edition' (Edition), 'status' (Datatype), 'confirmed' (boolean) and 'a_id'
         */
        public function getEditions()
        {
            $this->_populateAffected();

            return $this->_editions;
        }

        /**
         * Populates the affected items
         */
        protected function _populateAffected()
        {
            if ($this->_editions === null && $this->_builds === null && $this->_components === null) {
                $this->_editions = [];
                $this->_builds = [];
                $this->_components = [];

                if ($res = tables\IssueAffectsEdition::getTable()->getByIssueID($this->getID())) {
                    foreach ($res as $row) {
                        try {
                            $edition = tables\Editions::getTable()->selectById((int)$row->get(tables\IssueAffectsEdition::EDITION), null, null);
                            if ($edition instanceof Edition) {
                                $status_id = $row->get(tables\IssueAffectsEdition::STATUS);
                                $this->_editions[$row->get(tables\IssueAffectsEdition::ID)] = [
                                    'edition' => $edition,
                                    'status' => ($status_id) ? Status::getB2DBTable()->selectById((int)$status_id) : null,
                                    'confirmed' => (bool)$row->get(tables\IssueAffectsEdition::CONFIRMED),
                                    'a_id' => $row->get(tables\IssueAffectsEdition::ID)];
                            }
                        } catch (Exception $e) {
                        }
                    }
                }

                if ($res = tables\IssueAffectsBuild::getTable()->getByIssueID($this->getID())) {
                    foreach ($res as $row) {
                        try {
                            $build = tables\Builds::getTable()->selectById((int)$row->get(tables\IssueAffectsBuild::BUILD), null, null);
                            if ($build instanceof Build) {
                                $status_id = $row->get(tables\IssueAffectsBuild::STATUS);
                                $this->_builds[$row->get(tables\IssueAffectsBuild::ID)] = [
                                    'build' => $build,
                                    'status' => ($status_id) ? Status::getB2DBTable()->selectById((int)$status_id) : null,
                                    'confirmed' => (bool)$row->get(tables\IssueAffectsBuild::CONFIRMED),
                                    'a_id' => $row->get(tables\IssueAffectsBuild::ID)];
                            }
                        } catch (Exception $e) {
                        }
                    }
                }

                if ($res = tables\IssueAffectsComponent::getTable()->getByIssueID($this->getID())) {
                    foreach ($res as $row) {
                        try {
                            $component = tables\Components::getTable()->selectById((int)$row->get(tables\IssueAffectsComponent::COMPONENT), null, null);
                            if ($component instanceof Component) {
                                $status_id = $row->get(tables\IssueAffectsComponent::STATUS);
                                $this->_components[$row->get(tables\IssueAffectsComponent::ID)] = [
                                    'component' => $component,
                                    'status' => ($status_id) ? Status::getB2DBTable()->selectById((int)$status_id) : null,
                                    'confirmed' => (bool)$row->get(tables\IssueAffectsComponent::CONFIRMED),
                                    'a_id' => $row->get(tables\IssueAffectsComponent::ID)];
                            }
                        } catch (Exception $e) {
                        }
                    }
                }
            }
        }

        /**
         * Return the first affected edition, if any
         *
         * @return Edition
         */
        public function getFirstAffectedEdition()
        {
            $editions = $this->getEditions();
            if (count($editions)) {
                foreach ($editions as $info) {
                    return $info['edition'];
                }
            }
        }

        public function isAffectingBuilds()
        {
            $builds = $this->getBuilds();

            return (bool)count($builds);
        }

        /**
         * Returns the builds for this issue
         *
         * @return array Returns an array with 'build' (\pachno\core\entities\Build), 'status' (\pachno\core\entities\Datatype), 'confirmed' (boolean) and 'a_id'
         */
        public function getBuilds()
        {
            $this->_populateAffected();

            return $this->_builds;
        }

        public function isBuildAffected(Build $build)
        {
            $builds = $this->getBuilds();
            if (count($builds)) {
                foreach ($builds as $info) {
                    if ($info['build']->getID() == $build->getID())
                        return true;
                }
            }

            return false;
        }

        /**
         * Return the first affected build, if any
         *
         * @return Build
         */
        public function getFirstAffectedBuild()
        {
            $builds = $this->getBuilds();
            if (count($builds)) {
                foreach ($builds as $info) {
                    return $info['build'];
                }
            }
        }

        public function isAffectingComponents()
        {
            $components = $this->getComponents();

            return (bool)count($components);
        }

        /**
         * Returns the components for this issue
         *
         * @return array Returns an array with 'component' (\pachno\core\entities\Component), 'status' (\pachno\core\entities\Datatype), 'confirmed' (boolean) and 'a_id'
         */
        public function getComponents()
        {
            $this->_populateAffected();

            return $this->_components;
        }

        public function isComponentAffected(Component $component)
        {
            $components = $this->getComponents();
            if (count($components)) {
                foreach ($components as $info) {
                    if ($info['component']->getID() == $component->getID())
                        return true;
                }
            }

            return false;
        }

        public function getComponentNames()
        {
            $components = $this->getComponents();
            $names = [];
            foreach ($components as $info) {
                $names[] = $info['component']->getName();
            }

            return $names;
        }

        /**
         * Return the first affected component, if any
         *
         * @return Component
         */
        public function getFirstAffectedComponent()
        {
            $components = $this->getComponents();
            if (count($components)) {
                foreach ($components as $info) {
                    return $info['component'];
                }
            }
        }

        /**
         * Attach a link to the issue
         *
         * @param string $url The url of the link
         * @param string $description [optional] a description
         */
        public function attachLink($url, $description = null)
        {
            $link_id = tables\Links::getTable()->addLinkToIssue($this->getID(), $url, $description);

            return $link_id;
        }

        /**
         * Attach a file to the issue
         *
         * @param File $file The file to attach
         */
        public function attachFile(File $file, $file_comment = '', $file_description = '', $return_comment = false)
        {
            $existed = !tables\IssueFiles::getTable()->addByIssueIDandFileID($this->getID(), $file->getID());
            if (!$existed) {
                $comment = new Comment();
                $comment->setPostedBy(Context::getUser()->getID());
                $comment->setSystemComment(true);
                $comment->setTargetID($this->getID());
                $comment->setTargetType(Comment::TYPE_ISSUE);
                if ($file_comment) {
                    $comment->setContent(Context::getI18n()->__("A file was uploaded.%link_to_file \n\nThis comment was attached: %comment", ['%comment' => "\n\n" . $file_comment, '%link_to_file' => "\n\n[[File:{$file->getRealFilename()}|thumb|{$file_description}]]"]));
                } else {
                    $comment->setContent(Context::getI18n()->__('A file was uploaded.%link_to_file', ['%link_to_file' => "\n\n[[File:{$file->getRealFilename()}|thumb|{$file_description}]]"]));
                }
                $comment->save();
                if ($this->_files !== null) {
                    $this->_files[$file->getID()] = $file;
                }
                $this->touch();
                if ($return_comment) return $comment;
            }
            if ($return_comment) return null;
        }

        public function touch($last_updated = null)
        {
            tables\Issues::getTable()->touchIssue($this->getID(), $last_updated);

            foreach ($this->getParentIssues() as $parent_issue) {
                tables\Issues::getTable()->touchIssue($parent_issue->getID(), $last_updated);
            }
        }

        /**
         * Return issues relating to this
         *
         * @return Issue[]
         */
        public function getParentIssues()
        {
            $this->_populateRelatedIssues();

            return $this->_parent_issues;
        }

        /**
         * populates related issues
         */
        protected function _populateRelatedIssues()
        {
            if ($this->_parent_issues === null || $this->_child_issues === null) {
                $related_issues = tables\IssueRelations::getTable()->getRelatedIssues($this->getID());
                $this->_parent_issues = $related_issues['parents'];
                $this->_child_issues = $related_issues['children'];
            }
        }

        public function hasParentIssuetype($issuetype)
        {
            $issuetype_id = ($issuetype instanceof Issuetype) ? $issuetype->getID() : $issuetype;

            if (!count($this->getParentIssues())) return false;

            foreach ($this->getParentIssues() as $issue) {
                if ($issue->getIssueType()->getID() != $issuetype_id) return false;
            }

            return true;
        }

        public function hasChildIssues()
        {
            return (bool)$this->countChildIssues();
        }

        public function countChildIssues()
        {
            if ($this->_child_issues !== null) {
                return count($this->_child_issues);
            } else {
                return tables\IssueRelations::getTable()->countChildIssues($this->getID());
            }
        }

        /**
         * Returns the vote sum for this issue
         *
         * @return integer
         */
        public function getVotes()
        {
            return (int)$this->_votes_total;
        }

        /**
         * Set total number of votes
         *
         * @param integer
         */
        public function setVotes($votes)
        {
            $this->_votes_total = $votes;
        }

        /**
         * Vote for this issue, returns false if user cant vote or has voted the same before
         *
         * @return boolean
         */
        public function vote($up = true)
        {
            $user_id = Context::getUser()->getID();
            if (!$this->hasUserVoted($user_id, $up)) {
                tables\Votes::getTable()->addByUserIdAndIssueId($user_id, $this->getID(), $up);
                $this->_votes[$user_id] = ($up) ? 1 : -1;
                $this->_votes_total = array_sum($this->_votes);
                tables\Issues::getTable()->saveVotesTotalForIssueID($this->_votes_total, $this->getID());

                return true;
            } else {
                return false;
            }
        }

        /**
         * Whether or not the current user has voted
         *
         * @return boolean
         */
        public function hasUserVoted($user_id, $up)
        {
            $user_id = (is_object($user_id)) ? $user_id->getID() : $user_id;
            $this->_setupVotes();

            if (($user_id == Settings::getDefaultUserID() && Settings::isDefaultUserGuest()) || !$this->getProject()->canVoteOnIssues()) {
                return true;
            }

            if (array_key_exists($user_id, $this->_votes)) {
                return ($up) ? ((int)$this->_votes[$user_id] > 0) : ((int)$this->_votes[$user_id] < 0);
            } else {
                return false;
            }
        }

        /**
         * Load user votes
         */
        protected function _setupVotes()
        {
            if ($this->_votes === null) {
                $this->_votes = [];
                if ($res = tables\Votes::getTable()->getByIssueId($this->getID())) {
                    while ($row = $res->getNextRow()) {
                        $this->_votes[$row->get(tables\Votes::UID)] = $row->get(tables\Votes::VOTE);
                    }
                }
            }

        }

        /**
         * Returns an array of tags
         *
         * @return array
         */
        public function getTags()
        {
            $this->_b2dbLazyLoad('_tags');

            return $this->_tags;
        }

        /**
         * Returns the issue shortname
         *
         * @return string
         */
        public function getRawShortname()
        {
            return $this->_shortname;
        }

        /**
         * Return whether or not this issue has a description set
         *
         * @return boolean
         */
        public function hasDescription()
        {
            return (bool)(trim($this->getDescription()) != '');
        }

        /**
         * Return whether or not this issue has a shortname set
         *
         * @return boolean
         */
        public function hasShortname()
        {
            return (bool)(trim($this->getShortname()) != '');
        }

        /**
         * Returns the issue shortname
         *
         * @return string
         */
        public function getShortname()
        {
            return htmlentities($this->_shortname, ENT_COMPAT, Context::getI18n()->getCharset());
        }

        /**
         * Set the shortname
         *
         * @param string $shortname The new shortname to set
         */
        public function setShortname($shortname)
        {
            $this->_addChangedProperty('_shortname', $shortname);
        }

        /**
         * Get all custom fields and their values
         *
         * @return array
         */
        public function getCustomFields()
        {
            $retarr = [];
            foreach (CustomDatatype::getAll() as $key => $customdatatype) {
                $var_name = '_customfield' . $key;
                $retarr[$key] = $this->$var_name;
            }

            return $retarr;
        }

        public function getCustomFieldsOfType($type)
        {
            $retarr = [];
            foreach (CustomDatatype::getAll() as $key => $customdatatype) {
                if ($customdatatype->getType() != $type) continue;

                $var_name = '_customfield' . $key;
                $retarr[$key] = $this->$var_name;
            }

            return $retarr;
        }

        public function getCustomFieldsOfTypes($types)
        {
            $retarr = [];
            foreach (CustomDatatype::getAll() as $key => $customdatatype) {
                if (!in_array($customdatatype->getType(), $types)) continue;

                $var_name = '_customfield' . $key;
                $retarr[$key] = $this->$var_name;
            }

            return $retarr;
        }

        /**
         * Set the value of a custom field
         *
         * @param string $key
         * @param mixed $value
         */
        public function setCustomField($key, $value)
        {
            $this->_addChangedProperty('_customfield' . $key, $value);
        }

        public function clearCustomField($key)
        {
            $this->_addChangedProperty('_customfield' . $key, '');
        }

        /**
         * Get string value of any built-in or custom field for this issue
         *
         * @param $key Key of field
         *
         * @return string
         */
        public function getFieldValue($key)
        {
            $methodname = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

            if (method_exists($this, $methodname)) {
                // Use existing getter if available
                return $this->$methodname();

            } elseif ($key == 'component' || $key == 'edition' || $key == 'build') {
                $valueString = '';
                $methodname .= 's'; // Turn getComponent to getComponents
                $items = $this->$methodname();
                foreach ($items as $item) {
                    $valueString .= ', ' . $item[$key]->getName();
                }
                if (strlen($valueString) > 0) {
                    $valueString = substr($valueString, 2);
                }

                return $valueString;

            } elseif ($key == 'percent_complete') {
                return $this->getPercentCompleted();

            } else {
                return $this->getCustomField($key);
            }
        }

        /**
         * Returns the percentage completed
         *
         * @return integer
         */
        public function getPercentCompleted()
        {
            return (int)$this->_percent_complete;
        }

        /**
         * Return the value of a custom field
         *
         * @param string $key
         *
         * @return mixed
         */
        public function getCustomField($key)
        {
            $var_name = "_customfield{$key}";
            if (property_exists($this, $var_name)) {
                $customtype = CustomDatatype::getByKey($key);
                if ($customtype->getType() == CustomDatatype::CALCULATED_FIELD) {
                    $result = null;
                    $options = $customtype->getOptions();
                    if (!empty($options)) {
                        $formula = array_pop($options)->getValue();

                        preg_match_all('/{([[:alnum:]]+)}/', $formula, $matches);

                        $hasValues = false;
                        $matchCount = count($matches[0]);
                        for ($i = 0; $i < $matchCount; $i++) {
                            $value = $this->getCustomField($matches[1][$i]);
                            if ($value instanceof CustomDatatypeOption) {
                                $value = $value->getValue();
                            }
                            if (is_numeric($value)) {
                                $hasValues = true;
                            }
                            $value = floatval($value);
                            $formula = str_replace($matches[0][$i], $value, $formula);
                        }

                        // Check to verify formula only includes numbers and allowed operators
                        if ($hasValues && !preg_match('/[^0-9\+-\/*\(\)%]/', $formula)) {
                            try {
                                $m = new EvalMath();
                                $m->suppress_errors = true;
                                $result = $m->evaluate($formula);
                                if (!empty($m->last_error)) {
                                    $result = $m->last_error;
                                } else {
                                    $result = round($result, 2);
                                }
                            } catch (Exception $e) {
                                $result = 'N/A';
                            }
                        }
                    }

                    return $result;
                } elseif ($this->$var_name && $customtype->hasCustomOptions() && !$this->$var_name instanceof CustomDatatypeOption) {
                    $this->$var_name = tables\CustomFieldOptions::getTable()->selectById($this->$var_name);
                } elseif ($this->$var_name && $customtype->hasPredefinedOptions() && !$this->$var_name instanceof common\Identifiable) {
                    try {
                        switch ($customtype->getType()) {
                            case CustomDatatype::EDITIONS_CHOICE:
                                $this->$var_name = tables\Editions::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::COMPONENTS_CHOICE:
                                $this->$var_name = tables\Components::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::RELEASES_CHOICE:
                                $this->$var_name = tables\Builds::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::MILESTONE_CHOICE:
                                $this->$var_name = tables\Milestones::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::CLIENT_CHOICE:
                                $this->$var_name = tables\Clients::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::USER_CHOICE:
                                $this->$var_name = tables\Users::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::TEAM_CHOICE:
                                $this->$var_name = tables\Teams::getTable()->selectById($this->$var_name);
                                break;
                            case CustomDatatype::STATUS_CHOICE:
                                $this->$var_name = Status::getB2DBTable()->selectById($this->$var_name);
                                break;
                        }
                    } catch (Exception $e) {
                    }
                }

                return $this->$var_name;
            } else {
                return null;
            }
        }

        /**
         * Returns the agile board color
         *
         * @return string
         */
        public function getCoverColor()
        {
            return $this->_cover_color;
        }

        /**
         * Set the agile board color for this issue
         *
         * @param integer $color The color to change to
         */
        public function setCoverColor($color)
        {
            $this->_addChangedProperty('_cover_color', $color);
        }

        /**
         * Returns the agile board style
         *
         * @return string
         */
        public function getCoverStyle()
        {
            return $this->_cover_style;
        }

        /**
         * Set the agile board style for this issue
         *
         * @param integer $style The style to change to
         */
        public function setCoverStyle($style)
        {
            $this->_addChangedProperty('_cover_style', $style);
        }

        /**
         * Returns the cover image file if any
         *
         * @return File
         */
        public function getCoverImageFile()
        {
            return $this->_b2dbLazyLoad('_cover_image_file_id');
        }

        /**
         * Set the cover image file
         *
         * @param int|File $cover_image_file_id The cover image file or id
         */
        public function setCoverImageFile($cover_image_file_id)
        {
            $this->_addChangedProperty('_cover_image_file_id', $cover_image_file_id);
        }

        /**
         * Remove a dependant issue
         *
         * @param integer $issue_id The issue ID to remove
         */
        public function removeDependantIssue($issue_id)
        {
            if ($row = tables\IssueRelations::getTable()->getIssueRelation($this->getID(), $issue_id)) {
                $related_issue = Issue::getB2DBTable()->selectById($issue_id);
                $relation_id = $row->get(tables\IssueRelations::ID);
                if ($row->get(tables\IssueRelations::PARENT_ID) == $this->getID()) {
                    $this->_removeChildIssue($related_issue, $relation_id);
                } else {
                    $this->_removeParentIssue($related_issue, $relation_id);
                }
                $this->touch();
                $related_issue->touch();
                tables\IssueRelations::getTable()->rawDeleteById($relation_id);
            }
        }

        /**
         * Removes a child issue
         *
         * @param Issue $related_issue The issue to remove relations from
         * @param integer $relation_id The relation id to delete
         *
         * @see removeDependantIssue()
         *
         */
        protected function _removeChildIssue($related_issue, $relation_id)
        {
            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('Issue %issue_no no longer depends on the solution of this issue', ['%issue_no' => $related_issue->getFormattedIssueNo()]), $this->getID(), 0);
            $related_issue->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('This issue no longer depends on the solution of issue %issue_no', ['%issue_no' => $this->getFormattedIssueNo()]), $related_issue->getID(), 0);
            $this->calculateTime();

            if ($this->_child_issues !== null && array_key_exists($relation_id, $this->_child_issues)) {
                unset($this->_child_issues[$relation_id]);
            }
        }

        /**
         * Adds a log entry
         *
         * @param integer $change_type Type of log entry
         * @param string $text The text to log
         * @param boolean $system Whether this is a user entry or a system entry
         */
        public function addLogEntry($change_type, $text = null, $previous_value = null, $current_value = null, $system = false, $time = null, $uid = null)
        {
            if (!$this->should_log_entry) return;
            if ($uid === null) {
                $uid = ($system) ? 0 : Context::getUser()->getID();
            }
            $log_item = new LogItem();
            $log_item->setChangeType($change_type);
            $log_item->setText($text);
            $log_item->setTargetType(LogItem::TYPE_ISSUE);
            $log_item->setProject($this->getProject());
            $log_item->setTarget($this->getID());
            $log_item->setUser($uid);
            if ($time !== null) {
                $log_item->setTime($time);
            }
            if ($previous_value !== null) {
                $log_item->setPreviousValue($previous_value);
            }
            if ($current_value !== null) {
                $log_item->setCurrentValue($current_value);
            }

            $log_item->save();

            $this->_log_items_added[$log_item->getID()] = $log_item;

            framework\Event::createNew('core', 'pachno\core\entities\Issue::addLogEntry', $this)->trigger(['log_item' => $log_item]);

            return $log_item;
        }

        public function calculateTime()
        {
            $estimated_times = $spent_times = common\Timeable::getZeroedUnitsWithPoints();
            foreach ($this->getChildIssues() as $issue) {
                foreach ($issue->getEstimatedTime() as $key => $value) $estimated_times[$key] += $value;
                foreach ($issue->getSpentTime() as $key => $value) $spent_times[$key] += $value;
            }

            $spent_times['hours'] *= 100;

            $this->setEstimatedTime($estimated_times);
            $this->setSpentTime($spent_times);
        }

        /**
         * Return related issues
         *
         * @return Issue[]
         */
        public function getChildIssues()
        {
            $this->_populateRelatedIssues();

            return $this->_child_issues;
        }

        /**
         * Returns an array with the estimated time
         *
         * @param bool $append_minutes
         * @param bool $subtract_hours
         *
         * @return array
         */
        public function getEstimatedTime($append_minutes = false, $subtract_hours = false)
        {
            return ['months' => (int)$this->_estimated_months, 'weeks' => (int)$this->_estimated_weeks, 'days' => (int)$this->_estimated_days, 'hours' => (int)$this->getEstimatedHours($append_minutes), 'minutes' => (int)$this->getEstimatedMinutes($subtract_hours), 'points' => (int)$this->_estimated_points];
        }

        /**
         * Returns the estimated hours
         *
         * @param bool $append_minutes
         *
         * @return integer
         */
        public function getEstimatedHours($append_minutes = false): int
        {
            return (int)$this->_estimated_hours + ($append_minutes ? (int)floor($this->getEstimatedMinutes() / 60) : 0);
        }

        /**
         * Set estimated hours
         *
         * @param integer $hours The number of hours estimated
         */
        public function setEstimatedHours($hours)
        {
            $this->_addChangedProperty('_estimated_hours', $hours);
        }

        /**
         * Returns the estimated minutes
         *
         * @param bool $subtract_hours
         *
         * @return integer
         */
        public function getEstimatedMinutes($subtract_hours = false): int
        {
            $minutes = (int)$this->_estimated_minutes;

            return $subtract_hours ? $minutes % 60 : $minutes;
        }

        /**
         * Set estimated minutes
         *
         * @param integer $minutes The number of minutes estimated
         */
        public function setEstimatedMinutes($minutes)
        {
            $this->_addChangedProperty('_estimated_minutes', $minutes);
        }

        /**
         * Returns an array with the spent time
         *
         * @param bool $append_minutes
         * @param bool $subtract_hours
         *
         * @return array
         */
        public function getSpentTime($append_minutes = false, $subtract_hours = false)
        {
            return ['months' => (int)$this->_spent_months, 'weeks' => (int)$this->_spent_weeks, 'days' => (int)$this->_spent_days, 'hours' => (int)$this->getSpentHours($append_minutes), 'minutes' => (int)$this->getSpentMinutes($subtract_hours), 'points' => (int)$this->_spent_points];
        }

        /**
         * Returns the spent hours
         *
         * @param bool $append_minutes
         *
         * @return integer
         */
        public function getSpentHours($append_minutes = false): int
        {
            return (int)round($this->_spent_hours / 100, 2) + ($append_minutes ? (int)floor($this->getSpentMinutes() / 60) : 0);
        }

        /**
         * Set spent hours
         *
         * @param integer $hours The number of hours spent
         */
        public function setSpentHours($hours)
        {
            $this->_addChangedProperty('_spent_hours', $hours);
        }

        /**
         * Returns the spent minutes
         *
         * @param bool $subtract_hours
         *
         * @return integer
         */
        public function getSpentMinutes($subtract_hours = false): int
        {
            $minutes = (int)$this->_spent_minutes;

            return $subtract_hours ? $minutes % 60 : $minutes;
        }

        /**
         * Set spent minutes
         *
         * @param integer $minutes The number of minutes spent
         */
        public function setSpentMinutes($minutes)
        {
            $this->_addChangedProperty('_spent_minutes', $minutes);
        }

        /**
         * Set estimated time
         *
         * @param integer $time
         */
        public function setEstimatedTime($time)
        {
            if (is_numeric($time)) {
                $this->_addChangedProperty('_estimated_months', 0);
                $this->_addChangedProperty('_estimated_weeks', 0);
                $this->_addChangedProperty('_estimated_days', 0);
                $this->_addChangedProperty('_estimated_hours', 0);
                $this->_addChangedProperty('_estimated_minutes', 0);
                $this->_addChangedProperty('_estimated_points', 0);
            } elseif (is_array($time)) {
                foreach ($time as $key => $value) {
                    $this->_addChangedProperty('_estimated_' . $key, $value);
                }
            } else {
                $time = self::convertFancyStringToTime($time, $this);
                $this->_addChangedProperty('_estimated_months', $time['months']);
                $this->_addChangedProperty('_estimated_weeks', $time['weeks']);
                $this->_addChangedProperty('_estimated_days', $time['days']);
                $this->_addChangedProperty('_estimated_hours', $time['hours']);
                $this->_addChangedProperty('_estimated_minutes', $time['minutes']);
                $this->_addChangedProperty('_estimated_points', $time['points']);
            }
        }

        /**
         * Turns a string into a months/weeks/days/hours/minutes/points array
         *
         * @param string $string The string to convert
         * @param Issue $issue
         *
         * @return array
         */
        public static function convertFancyStringToTime($string, self $issue)
        {
            $retarr = common\Timeable::getZeroedUnitsWithPoints();
            $string = mb_strtolower(trim($string));
            $time_arr = preg_split('/(\,|\/|and|or|plus)/', $string);
            foreach ($time_arr as $time_elm) {
                $time_parts = explode(' ', trim($time_elm));
                if (is_array($time_parts) && count($time_parts) > 1) {
                    switch (true) {
                        case mb_stristr($time_parts[1], 'month'):
                            $retarr['months'] = (int)trim($time_parts[0]);
                            break;
                        case mb_stristr($time_parts[1], 'week'):
                            $retarr['weeks'] = (int)trim($time_parts[0]);
                            break;
                        case mb_stristr($time_parts[1], 'day'):
                            $retarr['days'] = (int)trim($time_parts[0]);
                            break;
                        case mb_stristr($time_parts[1], 'hour'):
                            $retarr['hours'] = trim($time_parts[0]);
                            break;
                        case mb_stristr($time_parts[1], 'minute'):
                            $retarr['minutes'] = trim($time_parts[0]);
                            break;
                        case mb_stristr($time_parts[1], 'point'):
                            $retarr['points'] = (int)trim($time_parts[0]);
                            break;
                    }
                }
            }

            return $retarr;
        }

        public function setSpentTime($time)
        {
            if (is_array($time)) {
                foreach ($time as $key => $value) {
                    $this->_addChangedProperty('_spent_' . $key, $value);
                }
            }
        }

        /**
         * Removes a parent issue
         *
         * @param Issue $related_issue The issue to remove relations from
         * @param integer $relation_id The relation id to delete
         *
         * @see removeDependantIssue()
         *
         */
        protected function _removeParentIssue($related_issue, $relation_id)
        {
            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('This issue no longer depends on the solution of issue %issue_no', ['%issue_no' => $related_issue->getFormattedIssueNo()]), $related_issue->getID(), 0);
            $related_issue->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('Issue %issue_no no longer depends on the solution of this issue', ['%issue_no' => $this->getFormattedIssueNo()]), $this->getID(), 0);
            $related_issue->calculateTime();

            if ($this->_parent_issues !== null && array_key_exists($relation_id, $this->_parent_issues)) {
                unset($this->_parent_issues[$relation_id]);
            }
        }

        /**
         * Add a related issue
         *
         * @param Issue $related_issue
         *
         * @return boolean
         */
        public function addParentIssue(Issue $related_issue)
        {
            if (!$row = tables\IssueRelations::getTable()->getIssueRelation($this->getID(), $related_issue->getID())) {
                tables\IssueRelations::getTable()->addParentIssue($this->getID(), $related_issue->getID());
                $this->_parent_issues = null;

                $related_issue->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('This %this_issuetype now depends on the solution of %issuetype %issue_no', ['%this_issuetype' => $related_issue->getIssueType()->getName(), '%issuetype' => $this->getIssueType()->getName(), '%issue_no' => $this->getFormattedIssueNo()]));
                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('%issuetype %issue_no now depends on the solution of this %this_issuetype', ['%this_issuetype' => $this->getIssueType()->getName(), '%issuetype' => $related_issue->getIssueType()->getName(), '%issue_no' => $related_issue->getFormattedIssueNo()]));
                $related_issue->calculateTime();
                $related_issue->save();
                $this->touch();
                $related_issue->touch();

                return true;
            }

            return false;
        }

        /**
         * Add a related issue
         *
         * @param Issue $related_issue
         *
         * @return boolean
         */
        public function addChildIssue(Issue $related_issue, $epic = false)
        {
            if (!$row = tables\IssueRelations::getTable()->getIssueRelation($this->getID(), $related_issue->getID())) {
                if (!$epic && !$this->getMilestone() instanceof Milestone && $related_issue->getMilestone() instanceof Milestone) {
                    $related_issue->removeMilestone();
                    $related_issue->save();
                } elseif ($this->getMilestone() instanceof Milestone) {
                    $related_issue->setMilestone($this->getMilestone()->getID());
                    $related_issue->save();
                }

                $res = tables\IssueRelations::getTable()->addChildIssue($this->getID(), $related_issue->getID());
                $this->_child_issues = null;

                $related_issue->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('%issuetype %issue_no now depends on the solution of this %this_issuetype', ['%this_issuetype' => $related_issue->getIssueType()->getName(), '%issuetype' => $this->getIssueType()->getName(), '%issue_no' => $this->getFormattedIssueNo()]));
                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RELATED_ISSUE, Context::getI18n()->__('This %this_issuetype now depends on the solution of %issuetype %issue_no', ['%this_issuetype' => $this->getIssueType()->getName(), '%issuetype' => $related_issue->getIssueType()->getName(), '%issue_no' => $related_issue->getFormattedIssueNo()]));
                $this->calculateTime();
                $this->save();
                $this->touch();
                $related_issue->touch();

                return true;
            }

            return false;
        }

        /**
         * Returns the assigned milestone if any
         *
         * @return Milestone
         */
        public function getMilestone()
        {
            return $this->_b2dbLazyLoad('_milestone');
        }

        /**
         * Set the milestone
         *
         * @param integer|Milestone $milestone_id The milestone id to assign
         */
        public function setMilestone($milestone_id)
        {
            $this->_addChangedProperty('_milestone', $milestone_id);
        }

        /**
         * Remove the assigned milestone
         */
        public function removeMilestone()
        {
            $this->setMilestone(0);
        }

        /**
         * Whether or not the issue is posted by someone
         *
         * @return boolean
         */
        public function isPostedBy()
        {
            return (bool)($this->getPostedBy() instanceof common\Identifiable);
        }

        public function getEstimatedPercentCompleted()
        {
            if ($this->getEstimatedPoints() > 0) {
                $estimated = $this->getEstimatedPoints();
                $spent = $this->getSpentPoints();
            } else {
                $estimated = $this->getEstimatedMinutes();
                $estimated += $this->getEstimatedHours() * 60;
                $estimated += $this->getEstimatedDays() * 8;
                $estimated += $this->getEstimatedWeeks() * 8 * 5;
                $estimated += $this->getEstimatedMonths() * 8 * 22;

                $spent = $this->getSpentMinutes();
                $spent *= $this->getSpentHours() * 60;
                $spent += $this->getSpentDays() * 8;
                $spent += $this->getSpentWeeks() * 8 * 5;
                $spent += $this->getSpentMonths() * 8 * 22;
            }
            if ($estimated <= 0) return 0;

            $multiplier = 100 / $estimated;
            $pct = $spent * $multiplier;

            return ($pct <= 100) ? $pct : 100;
        }

        /**
         * Returns the estimated points
         *
         * @return integer
         */
        public function getEstimatedPoints(): int
        {
            return (int)$this->_estimated_points;
        }

        /**
         * Set estimated points
         *
         * @param integer $points The number of points estimated
         */
        public function setEstimatedPoints($points)
        {
            $this->_addChangedProperty('_estimated_points', $points);
        }

        /**
         * Returns the spent points
         *
         * @return integer
         */
        public function getSpentPoints(): int
        {
            return (int)$this->_spent_points;
        }

        /**
         * Set spent points
         *
         * @param integer $points The number of points spent
         */
        public function setSpentPoints($points)
        {
            $this->_addChangedProperty('_spent_points', $points);
        }

        /**
         * Returns the estimated days
         *
         * @return integer
         */
        public function getEstimatedDays(): int
        {
            return (int)$this->_estimated_days;
        }

        /**
         * Set estimated days
         *
         * @param integer $days The number of days estimated
         */
        public function setEstimatedDays($days)
        {
            $this->_addChangedProperty('_estimated_days', $days);
        }

        /**
         * Returns the estimated weeks
         *
         * @return integer
         */
        public function getEstimatedWeeks(): int
        {
            return (int)$this->_estimated_weeks;
        }

        /**
         * Set estimated weeks
         *
         * @param integer $weeks The number of weeks estimated
         */
        public function setEstimatedWeeks($weeks)
        {
            $this->_addChangedProperty('_estimated_weeks', $weeks);
        }

        /**
         * Returns the estimated months
         *
         * @return integer
         */
        public function getEstimatedMonths(): int
        {
            return (int)$this->_estimated_months;
        }

        /**
         * Set estimated months
         *
         * @param integer $months The number of months estimated
         */
        public function setEstimatedMonths($months)
        {
            $this->_addChangedProperty('_estimated_months', $months);
        }

        /**
         * Returns the spent days
         *
         * @return integer
         */
        public function getSpentDays(): int
        {
            return (int)$this->_spent_days;
        }

        /**
         * Set spent days
         *
         * @param integer $days The number of days spent
         */
        public function setSpentDays($days)
        {
            $this->_addChangedProperty('_spent_days', $days);
        }

        /**
         * Returns the spent weeks
         *
         * @return integer
         */
        public function getSpentWeeks(): int
        {
            return (int)$this->_spent_weeks;
        }

        /**
         * Set spent weeks
         *
         * @param integer $weeks The number of weeks spent
         */
        public function setSpentWeeks($weeks)
        {
            $this->_addChangedProperty('_spent_weeks', $weeks);
        }

        /**
         * Returns the spent months
         *
         * @return integer
         */
        public function getSpentMonths(): int
        {
            return (int)$this->_spent_months;
        }

        /**
         * Set spent months
         *
         * @param integer $months The number of months spent
         */
        public function setSpentMonths($months)
        {
            $this->_addChangedProperty('_spent_months', $months);
        }

        /**
         * Set percentage completed
         *
         * @param integer $percentage
         */
        public function setPercentCompleted($percentage)
        {
            $this->_addChangedProperty('_percent_complete', (int)$percentage);
        }

        /**
         * Returns the estimated hours and minutes formatted
         *
         * @param bool $append_minutes
         * @param bool $subtract_hours
         *
         * @return integer|string
         */
        public function getEstimatedHoursAndMinutes($append_minutes = false, $subtract_hours = false)
        {
            return common\Timeable::formatHoursAndMinutes($this->getEstimatedHours($append_minutes), $this->getEstimatedMinutes($subtract_hours));
        }

        /**
         * Set issue number
         *
         * @param integer $no New issue number
         */
        public function setIssueNumber($no)
        {
            $this->_issue_no = $no;
        }

        /**
         * Returns the spent hours and minutes formatted
         *
         * @param bool $append_minutes
         * @param bool $subtract_hours
         *
         * @return integer|string
         */
        public function getSpentHoursAndMinutes($append_minutes = false, $subtract_hours = false)
        {
            return common\Timeable::formatHoursAndMinutes($this->getSpentHours($append_minutes), $this->getSpentMinutes($subtract_hours));
        }

        /**
         * Returns an array with the spent time
         *
         * @param bool $append_minutes
         * @param bool $subtract_hours
         *
         * @return array
         * @see getSpentTime()
         *
         */
        public function getTimeSpent($append_minutes = false, $subtract_hours = false)
        {
            return $this->getSpentTime($append_minutes, $subtract_hours);
        }

        /**
         * (Re-)open the issue
         */
        public function open()
        {
            $this->setState(self::STATE_OPEN);
        }

        /**
         * Add a build to the list of affected builds
         *
         * @param Build $build The build to add
         *
         * @return boolean
         */
        public function addAffectedBuild($build)
        {
            if ($this->getProject() && $this->getProject()->isBuildsEnabled()) {
                $retval = tables\IssueAffectsBuild::getTable()->setIssueAffected($this->getID(), $build->getID());
                if ($retval !== false) {
                    $this->touch();
                    $this->addLogEntry(LogItem::ACTION_ISSUE_ADD_AFFECTED_ITEM, Context::getI18n()->__("'%release_name' added", ['%release_name' => $build->getName()]));

                    return ['a_id' => $retval, 'build' => $build, 'confirmed' => 0, 'status' => null];
                }
                foreach ($this->getChildIssues() as $issue) {
                    $issue->addAffectedBuild($build);
                }
            }

            return false;
        }

        /**
         * Add an edition to the list of affected editions
         *
         * @param Edition $edition The edition to add
         *
         * @return boolean
         */
        public function addAffectedEdition($edition)
        {
            if ($this->getProject() && $this->getProject()->isEditionsEnabled()) {
                $retval = tables\IssueAffectsEdition::getTable()->setIssueAffected($this->getID(), $edition->getID());
                if ($retval !== false) {
                    $this->touch();
                    $this->addLogEntry(LogItem::ACTION_ISSUE_ADD_AFFECTED_ITEM, Context::getI18n()->__("'%edition_name' added", ['%edition_name' => $edition->getName()]));

                    return ['a_id' => $retval, 'edition' => $edition, 'confirmed' => 0, 'status' => null];
                }
            }

            return false;
        }

        /**
         * Add a component to the list of affected components
         *
         * @param Component $component The component to add
         *
         * @return boolean
         */
        public function addAffectedComponent($component)
        {
            if ($this->getProject() && $this->getProject()->isComponentsEnabled()) {
                $retval = tables\IssueAffectsComponent::getTable()->setIssueAffected($this->getID(), $component->getID());
                if ($retval !== false) {
                    $this->touch();
                    $this->addLogEntry(LogItem::ACTION_ISSUE_ADD_AFFECTED_ITEM, Context::getI18n()->__("'%component_name' added", ['%component_name' => $component->getName()]));

                    return ['a_id' => $retval, 'component' => $component, 'confirmed' => 0, 'status' => null];
                }
            }

            return false;
        }

        /**
         * Remove an affected edition
         *
         * @param Edition $item The edition to remove
         *
         * @return boolean
         * @see removeAffectedComponent()
         *
         * @see removeAffectedItem()
         * @see removeAffectedBuild()
         */
        public function removeAffectedEdition($item)
        {
            if (tables\IssueAffectsEdition::getTable()->deleteByIssueIDandEditionID($this->getID(), $item->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' removed", ['%item_name' => $item->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Remove an affected build
         *
         * @param Build $item The build to remove
         *
         * @return boolean
         * @see removeAffectedComponent()
         *
         * @see removeAffectedItem()
         * @see removeAffectedEdition()
         */
        public function removeAffectedBuild($item)
        {
            if (tables\IssueAffectsBuild::getTable()->deleteByIssueIDandBuildID($this->getID(), $item->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' removed", ['%item_name' => $item->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Remove an affected component
         *
         * @param Component $item The component to remove
         *
         * @return boolean
         * @see removeAffectedBuild()
         *
         * @see removeAffectedItem()
         * @see removeAffectedEdition()
         */
        public function removeAffectedComponent($item)
        {
            if (tables\IssueAffectsComponent::getTable()->deleteByIssueIDandComponentID($this->getID(), $item->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' removed", ['%item_name' => $item->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Remove an affected edition
         *
         * @param Edition $item The edition to remove
         * @param boolean $confirmed [optional] Whether it's confirmed or not
         *
         * @return boolean
         * @see confirmAffectedItem()
         * @see confirmAffectedBuild()
         * @see confirmAffectedComponent()
         *
         */
        public function confirmAffectedEdition($item, $confirmed = true)
        {
            if (tables\IssueAffectsEdition::getTable()->confirmByIssueIDandEditionID($this->getID(), $item->getID(), $confirmed)) {
                $this->touch();
                if ($confirmed) {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%edition' is now confirmed for this issue", ['%edition' => $item->getName()]));
                } else {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%edition' is now unconfirmed for this issue", ['%edition' => $item->getName()]));
                }

                return true;
            }

            return false;
        }

        /**
         * Remove an affected build
         *
         * @param Build $item The build to remove
         * @param boolean $confirmed [optional] Whether it's confirmed or not
         *
         * @return boolean
         * @see confirmAffectedItem()
         * @see confirmAffectedEdition()
         * @see confirmAffectedComponent()
         *
         */
        public function confirmAffectedBuild($item, $confirmed = true)
        {
            if (tables\IssueAffectsBuild::getTable()->confirmByIssueIDandBuildID($this->getID(), $item->getID(), $confirmed)) {
                $this->touch();
                if ($confirmed) {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%build' is now confirmed for this issue", ['%build' => $item->getName()]));
                } else {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%build' is now unconfirmed for this issue", ['%build' => $item->getName()]));
                }

                return true;
            }

            return false;
        }

        /**
         * Remove an affected component
         *
         * @param Component $item The component to remove
         * @param boolean $confirmed [optional] Whether it's confirmed or not
         *
         * @return boolean
         * @see confirmAffectedItem()
         * @see confirmAffectedEdition()
         * @see confirmAffectedBuild()
         *
         */
        public function confirmAffectedComponent($item, $confirmed = true)
        {
            if (tables\IssueAffectsComponent::getTable()->confirmByIssueIDandComponentID($this->getID(), $item->getID(), $confirmed)) {
                $this->touch();
                if ($confirmed) {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%component' is now confirmed for this issue", ['%component' => $item->getName()]));
                } else {
                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_AFFECTED_ITEM, Context::getI18n()->__("'%component' is now unconfirmed for this issue", ['%component' => $item->getName()]));
                }

                return true;
            }

            return false;
        }

        /**
         * Set status for affected edition
         *
         * @param Edition $item The edition to set status for
         * @param Datatype $status The status to set
         *
         * @return boolean
         * @see setAffectedItemStatus()
         * @see setAffectedBuildStatus()
         * @see setAffectedComponentStatus()
         *
         */
        public function setAffectedEditionStatus($item, $status)
        {
            if (tables\IssueAffectsEdition::getTable()->setStatusByIssueIDandEditionID($this->getID(), $item->getID(), $status->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' -> '%status_name", ['%item_name' => $item->getName(), '%status_name' => $status->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Set status for affected build
         *
         * @param Build $item The build to set status for
         * @param Datatype $status The status to set
         *
         * @return boolean
         * @see setAffectedItemStatus()
         * @see setAffectedEditionStatus()
         * @see setAffectedComponentStatus()
         *
         */
        public function setAffectedBuildStatus($item, $status)
        {
            if (tables\IssueAffectsBuild::getTable()->setStatusByIssueIDandBuildID($this->getID(), $item->getID(), $status->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' -> '%status_name", ['%item_name' => $item->getName(), '%status_name' => $status->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Set status for affected component
         *
         * @param Component $item The component to set status for
         * @param Datatype $status The status to set
         *
         * @return boolean
         * @see setAffectedItemStatus()
         * @see setAffectedBuildStatus()
         * @see setAffectedEditionStatus()
         *
         */
        public function setAffectedComponentStatus($item, $status)
        {
            if (tables\IssueAffectsComponent::getTable()->setStatusByIssueIDandComponentID($this->getID(), $item->getID(), $status->getID())) {
                $this->touch();
                $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_AFFECTED_ITEM, Context::getI18n()->__("'%item_name' -> '%status_name", ['%item_name' => $item->getName(), '%status_name' => $status->getName()]));

                return true;
            }

            return false;
        }

        /**
         * Updates the issue's last_updated time to "now"
         */
        public function updateTime()
        {
            $this->_addChangedProperty('_last_updated', NOW);
        }

        /**
         * Delete this issue
         */
        public function deleteIssue()
        {
            $this->_deleted = true;
            $this->touch();
            tables\IssueRelations::getTable()->removeIssueRelations($this->getID());
        }

        /**
         * Return an array with all the links:
         *         'id' => array('url', 'description')
         *
         * @return array
         */
        public function getLinks()
        {
            $this->_populateLinks();

            return $this->_links;
        }

        /**
         * Populate the internal links array
         */
        protected function _populateLinks()
        {
            if ($this->_links === null) {
                $this->_links = tables\Links::getTable()->getByIssueID($this->getID());
            }
        }

        /**
         * Remove a link
         *
         * @param integer $link_id The link ID to remove
         */
        public function removeLink($link_id)
        {
            if ($res = tables\Links::getTable()->removeByIssueIDandLinkID($this->getID(), $link_id)) {
                if (is_array($this->_links) && array_key_exists($link_id, $this->_links)) {
                    unset($this->_links[$link_id]);
                }
            }
        }

        public function countAttachments()
        {
            return $this->getNumberOfFiles();
        }

        public function getNumberOfFiles(): int
        {
            if ($this->_num_files === null) {
                if ($this->_files !== null) {
                    $this->_num_files = count($this->_files);
                } else {
                    $this->_num_files = File::countByIssueID($this->getID());
                }
            }

            return $this->_num_files;
        }

        /**
         * Return a file by the filename if it is attached to this issue
         *
         * @param string $filename The original filename to match against
         *
         * @return File
         */
        public function getFileByFilename($filename)
        {
            foreach ($this->getFiles() as $file_id => $file) {
                if (mb_strtolower($filename) == mb_strtolower($file->getRealFilename()) || mb_strtolower($filename) == mb_strtolower($file->getOriginalFilename())) {
                    return $file;
                }
            }

            return null;
        }

        /**
         * Return an array with all files attached to this issue
         *
         * @return File[]
         */
        public function getFiles()
        {
            $this->_populateFiles();

            return $this->_files;
        }

        /**
         * Populate the files array
         */
        protected function _populateFiles()
        {
            if ($this->_files === null) {
                $this->_files = File::getByIssueID($this->getID());
            }
        }

        /**
         * Remove a file
         *
         * @param File $file The file to be removed
         *
         * @return boolean
         */
        public function detachFile(File $file)
        {
            tables\IssueFiles::getTable()->removeByIssueIDandFileID($this->getID(), $file->getID());
            if (is_array($this->_files) && array_key_exists($file->getID(), $this->_files)) {
                unset($this->_files[$file->getID()]);
            }
            $file->delete();
            $this->touch();
        }

        /**
         * Retrieve all log entries for this issue
         *
         * @return array
         */
        public function getLogEntries()
        {
            $this->_populateLogEntries();

            return $this->_log_entries;
        }

        /**
         * Populate log entries array
         */
        protected function _populateLogEntries()
        {
            if ($this->_log_entries === null) {
                $this->_log_entries = tables\LogItems::getTable()->getByIssueID($this->getID());
            }
        }

        /**
         * Retrieve all spent times for this issue
         *
         * @return IssueSpentTime[]
         */
        public function getSpentTimes()
        {
            $this->_populateSpentTimes();

            return $this->_spent_times;
        }

        /**
         * Populate comments array
         */
        protected function _populateSpentTimes()
        {
            if ($this->_spent_times === null) {
                $this->_b2dbLazyLoad('_spent_times');
            }
        }

        public function getNumberOfUserComments(): int
        {
            if ($this->_num_user_comments === null) {
                $this->_num_user_comments = Comment::countComments($this->getID(), Comment::TYPE_ISSUE);
            }

            return (int)$this->_num_user_comments;
        }

        /**
         * Return whether or not the triaging fields for user pain are visible
         *
         * @return boolean
         */
        public function isUserPainVisible()
        {
            return (bool)($this->isFieldVisible('user_pain'));
        }

        /**
         * Return whether or not a specific field is visible
         *
         * @param string $fieldname the fieldname key
         *
         * @return boolean
         */
        public function isFieldVisible($fieldname)
        {
            if (!$this->hasIssueType()) return false;
            try {
                $fields_array = $this->getProject()->getVisibleFieldsArray($this->getIssueType()->getID());

                return array_key_exists($fieldname, $fields_array);
            } catch (Exception $e) {
                return false;
            }
        }

        public function hasIssueType()
        {
            try {
                return ($this->getIssueType() instanceof Issuetype);
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Return whether or not voting is enabled for this issue type
         *
         * @return boolean
         */
        public function isVotesVisible()
        {
            return (bool)($this->isFieldVisible('votes'));
        }

        /**
         * Return whether or not the "owned by" field is visible
         *
         * @return boolean
         */
        public function isOwnedByVisible()
        {
            return (bool)($this->isFieldVisible('owned_by') || $this->isOwned());
        }

        /**
         * Return whether or not the "description" field is visible
         *
         * @return boolean
         */
        public function isDescriptionVisible()
        {
            return (bool)($this->isFieldVisible('description') || $this->getDescription() != '');
        }

        /**
         * Return whether or not the "shortname" field is visible
         *
         * @return boolean
         */
        public function isShortnameVisible()
        {
            return (bool)($this->isFieldVisible('shortname') || $this->getShortname() != '');
        }

        /**
         * Return whether or not the "reproduction steps" field is visible
         *
         * @return boolean
         */
        public function isReproductionStepsVisible()
        {
            return (bool)($this->isFieldVisible('reproduction_steps') || $this->getReproductionSteps());
        }

        /**
         * Return whether or not the "category" field is visible
         *
         * @return boolean
         */
        public function isCategoryVisible()
        {
            return (bool)($this->isFieldVisible('category') || $this->getCategory() instanceof Datatype);
        }

        /**
         * Return whether or not the "resolution" field is visible
         *
         * @return boolean
         */
        public function isResolutionVisible()
        {
            return (bool)($this->isFieldVisible('resolution') || $this->getResolution() instanceof Datatype);
        }

        /**
         * Returns the resolution
         *
         * @return Resolution
         */
        public function getResolution()
        {
            return $this->_b2dbLazyLoad('_resolution');
        }

        /**
         * Set the resolution
         *
         * @param integer $resolution_id The resolution ID you want to set it to
         */
        public function setResolution($resolution_id)
        {
            $this->_addChangedProperty('_resolution', $resolution_id);
        }

        /**
         * Return whether or not the "editions" field is visible
         *
         * @return boolean
         */
        public function isEditionsVisible()
        {
            return (bool)($this->isFieldVisible('edition') || count($this->getEditions()) > 0);
        }

        /**
         * Return whether or not the "builds" field is visible
         *
         * @return boolean
         */
        public function isBuildsVisible()
        {
            return (bool)($this->isFieldVisible('build') || count($this->getBuilds()) > 0);
        }

        /**
         * Return whether or not the "components" field is visible
         *
         * @return boolean
         */
        public function isComponentsVisible()
        {
            return (bool)($this->isFieldVisible('component') || count($this->getComponents()) > 0);
        }

        /**
         * Return whether or not the "reproducability" field is visible
         *
         * @return boolean
         */
        public function isReproducabilityVisible()
        {
            return (bool)($this->isFieldVisible('reproducability') || $this->getReproducability() instanceof Datatype);
        }

        /**
         * Returns the reproducability
         *
         * @return Reproducability
         */
        public function getReproducability()
        {
            return $this->_b2dbLazyLoad('_reproducability');
        }

        /**
         * Set the reproducability
         *
         * @param integer $reproducability_id The reproducability id to change to
         */
        public function setReproducability($reproducability_id)
        {
            $this->_addChangedProperty('_reproducability', $reproducability_id);
        }

        /**
         * Return whether or not the "severity" field is visible
         *
         * @return boolean
         */
        public function isSeverityVisible()
        {
            return (bool)($this->isFieldVisible('severity') || $this->getSeverity() instanceof Datatype);
        }

        /**
         * Returns the severity
         *
         * @return Severity
         */
        public function getSeverity()
        {
            return $this->_b2dbLazyLoad('_severity');
        }

        /**
         * Set the severity
         *
         * @param integer $severity_id The severity ID you want to set it to
         */
        public function setSeverity($severity_id)
        {
            $this->_addChangedProperty('_severity', $severity_id);
        }

        /**
         * Return whether or not the "priority" field is visible
         *
         * @return boolean
         */
        public function isPriorityVisible()
        {
            return (bool)($this->isFieldVisible('priority') || $this->getPriority() instanceof Datatype);
        }

        /**
         * Returns the priority
         *
         * @return Priority
         */
        public function getPriority(): ?Priority
        {
            return $this->_b2dbLazyLoad('_priority');
        }

        /**
         * Set the priority
         *
         * @param integer $priority_id The priority id to change to
         */
        public function setPriority($priority_id)
        {
            $this->_addChangedProperty('_priority', $priority_id);
        }

        /**
         * Return whether or not the "estimated time" field is visible
         *
         * @return boolean
         */
        public function isEstimatedTimeVisible()
        {
            return (bool)($this->isFieldVisible('estimated_time') || $this->hasEstimatedTime());
        }

        /**
         * Returns whether or not there is an estimated time for this issue
         *
         * @return boolean
         */
        public function hasEstimatedTime()
        {
            $time = $this->getEstimatedTime();

            return (array_sum($time) > 0) ? true : false;
        }

        /**
         * Return whether or not the "spent time" field is visible
         *
         * @return boolean
         */
        public function isSpentTimeVisible()
        {
            return (bool)($this->getProject()->canSeeTimeSpent() && ($this->isFieldVisible('spent_time') || $this->hasSpentTime()));
        }

        /**
         * Returns whether or not there is an spent time for this issue
         *
         * @return boolean
         */
        public function hasSpentTime()
        {
            $time = $this->getSpentTime();

            return (array_sum($time) > 0) ? true : false;
        }

        /**
         * Return whether or not the "milestone" field is visible
         *
         * @return boolean
         */
        public function isMilestoneVisible()
        {
            return (bool)($this->isFieldVisible('milestone') || $this->getMilestone() instanceof Milestone);
        }

        /**
         * Return whether or not the "percent_complete" field is visible
         *
         * @return boolean
         */
        public function isPercentCompletedVisible()
        {
            return (bool)($this->isFieldVisible('percent_complete') || $this->getPercentCompleted() > 0);
        }

        /**
         * Return the time when the issue was closed
         *
         * @return false if closed, otherwise a timestamp
         */
        public function whenClosed()
        {
            if (!$this->isClosed()) return false;

            $item = tables\LogItems::getTable()->getByTargetAndChangeAndType($this->_id, LogItem::ACTION_ISSUE_CLOSE, LogItem::TYPE_ISSUE);

            if ($item instanceof LogItem) {
                return $item->getTime();
            }
        }

        /**
         * Return the time when the issue was reopened
         *
         * @return false if closed, otherwise a timestamp
         */
        public function whenReopened()
        {
            if ($this->isClosed()) return false;
            $item = tables\LogItems::getTable()->getByTargetAndChangeAndType($this->_id, LogItem::ACTION_ISSUE_REOPEN, LogItem::TYPE_ISSUE);

            if ($item instanceof LogItem) {
                return $item->getTime();
            }
        }

        /**
         * Stop working on the issue, and save time spent
         *
         * @param User $user
         * @param integer $timespent_activitytype
         * @param string $timespent_comment
         *
         * @return null
         */
        public function stopWorkingOnIssue(User $user, $timespent_activitytype, $timespent_comment)
        {
            $time_spent = $this->calculateTimeSpent();
            $this->clearUserWorkingOnIssue();

            if ($time_spent['minutes'] > 0 || $time_spent['hours'] > 0 || $time_spent['days'] > 0 || $time_spent['weeks'] > 0) {
                $time_spent['hours'] *= 100;
                $spenttime = new IssueSpentTime();
                $spenttime->setIssue($this);
                $spenttime->setUser(Context::getUser());
                $spenttime->setSpentPoints(0);
                $spenttime->setSpentMinutes($time_spent['minutes']);
                $spenttime->setSpentHours($time_spent['hours']);
                $spenttime->setSpentDays($time_spent['days']);
                $spenttime->setSpentWeeks($time_spent['weeks']);
                $spenttime->setSpentMonths(0);
                $spenttime->setActivityType($timespent_activitytype);
                $spenttime->setComment($timespent_comment);
                $spenttime->save();
            }
        }

        public function calculateTimeSpent()
        {
            $ts_array = array_fill_keys(common\Timeable::getUnitsWithout(['months']), 0);
            $time_spent = ($this->_being_worked_on_by_user_since) ? NOW - $this->_being_worked_on_by_user_since : 0;
            if ($time_spent > 0) {
                $weeks_spent = floor($time_spent / 604800);
                $days_spent = floor(($time_spent - ($weeks_spent * 604800)) / 86400);
                $hours_spent = floor(($time_spent - ($weeks_spent * 604800) - ($days_spent * 86400)) / 3600);
                $minutes_spent = ceil(($time_spent - ($weeks_spent * 604800) - ($days_spent * 86400) - ($hours_spent * 3600)) / 60);

                $ts_array['minutes'] = ($minutes_spent < 0) ? 0 : $minutes_spent;
                $ts_array['hours'] = ($hours_spent < 0) ? 0 : $hours_spent;
                $ts_array['days'] = ($days_spent < 0) ? 0 : $days_spent;
                $ts_array['weeks'] = ($weeks_spent < 0) ? 0 : $weeks_spent;
            }

            return $ts_array;
        }

        /**
         * Clear the user currently working on this issue
         *
         * @return null
         */
        public function clearUserWorkingOnIssue()
        {
            $this->_addChangedProperty('_being_worked_on_by_user', null);
            $this->_being_worked_on_by_user_since = null;
        }

        public function getWorkedOnSince()
        {
            return $this->_being_worked_on_by_user_since;
        }

        public function getPainBugType()
        {
            return $this->_pain_bug_type;
        }

        public function setPainBugType($value)
        {
            $this->_addChangedProperty('_pain_bug_type', (int)$value);
            $this->_calculateUserPain();
        }

        protected function _calculateUserPain()
        {
            $this->_addChangedProperty('_user_pain', round($this->_pain_bug_type * $this->_pain_likelihood * $this->_pain_effect / 1.75, 1));
        }

        public function getPainBugTypeLabel()
        {
            return self::getPainTypesOrLabel('pain_bug_type', $this->_pain_bug_type);
        }

        public function getPainLikelihood()
        {
            return $this->_pain_likelihood;
        }

        public function setPainLikelihood($value)
        {
            $this->_addChangedProperty('_pain_likelihood', (int)$value);
            $this->_calculateUserPain();
        }

        public function getPainLikelihoodLabel()
        {
            return self::getPainTypesOrLabel('pain_likelihood', $this->_pain_likelihood);
        }

        public function getPainEffect()
        {
            return $this->_pain_effect;
        }

        public function setPainEffect($value)
        {
            $this->_addChangedProperty('_pain_effect', (int)$value);
            $this->_calculateUserPain();
        }

        public function getPainEffectLabel()
        {
            return self::getPainTypesOrLabel('pain_effect', $this->_pain_effect);
        }

        public function getUserPainDiffText()
        {
            return $this->getUserPain(true) . ' + ' . ($this->getUserPain() - $this->getUserPain(true));
        }

        public function getUserPain($real = false)
        {
            return (int)(($real) ? $this->getRealUserPain() : $this->_calculateDatePain());
        }

        protected function getRealUserPain()
        {
            return $this->_user_pain;
        }

        protected function _calculateDatePain()
        {
            $user_pain = $this->_user_pain;
            if ($this->_user_pain > 0 && $this->_user_pain < 100) {
                $offset = NOW - $this->getPosted();
                $user_pain += floor($offset / 60 / 60 / 24) * 0.1;
            }

            return $user_pain;
        }

        public function hasPainBugType()
        {
            return (bool)($this->_pain_bug_type > 0);
        }

        public function hasPainLikelihood()
        {
            return (bool)($this->_pain_likelihood > 0);
        }

        public function hasPainEffect()
        {
            return (bool)($this->_pain_effect > 0);
        }

        public function toJSON($detailed = true)
        {
            $return_values = [
                'id' => $this->getID(),
                'issue_no' => $this->getFormattedIssueNo(true),
                'state' => $this->getState(),
                'closed' => $this->isClosed(),
                'deleted' => $this->isDeleted(),
                'archived' => $this->isArchived(),
                'blocking' => $this->isBlocking(),
                'created_at' => $this->getPosted(),
                'created_at_iso' => date('c', $this->getPosted()),
                'updated_at' => $this->getLastUpdatedTime(),
                'updated_at_iso' => date('c', $this->getLastUpdatedTime()),
                'title' => $this->getRawTitle(),
                'cover_color' => $this->getCoverColor(),
                'cover_style' => $this->getCoverStyle(),
                'issue_type' => $this->getIssueType()->toJSON(false),
                'cover_image_file_id' => ($this->getCoverImageFile() instanceof File) ? $this->getCoverImageFile()->getID() : 0,
                'cover_image_url' => ($this->getCoverImageFile() instanceof File) ? Context::getRouting()->generate('showfile', ['id' => $this->getCoverImageFile()->getID()]) : '',
                'href' => Context::getRouting()->generate('viewissue', ['project_key' => $this->getProject()->getKey(), 'issue_no' => $this->getFormattedIssueNo()], false),
                'more_actions_url' => Context::getRouting()->generate('issue_moreactions', ['project_key' => $this->getProject()->getKey(), 'issue_id' => $this->getID()]),
                'card_url' => Context::getRouting()->generate('get_partial_for_backdrop', ['key' => 'viewissue', 'issue_id' => $this->getID()]),
                'posted_by' => ($this->getPostedBy() instanceof common\Identifiable) ? $this->getPostedBy()->toJSON() : null,
                'assignee' => ($this->getAssignee() instanceof common\Identifiable) ? $this->getAssignee()->toJSON() : null,
                'status' => ($this->getStatus() instanceof common\Identifiable) ? $this->getStatus()->toJSON() : null,
                'category' => ($this->getCategory() instanceof common\Identifiable) ? $this->getCategory()->toJSON() : null,
                'priority' => ($this->getPriority() instanceof common\Identifiable) ? $this->getPriority()->toJSON() : null,
                'severity' => ($this->getSeverity() instanceof common\Identifiable) ? $this->getSeverity()->toJSON() : null,
                'milestone' => ($this->getMilestone() instanceof common\Identifiable) ? $this->getMilestone()->toJSON() : null,
                'number_of_comments' => $this->getNumberOfUserComments(),
                'number_of_files' => $this->getNumberOfFiles(),
                'tags' => []
            ];

            if ($this->isChildIssue()) {
                foreach ($this->getParentIssues() as $parentIssue) {
                    $return_values['parent_issue_id'] = $parentIssue->getID();
                }
            }

            foreach ($this->getTags() as $tag) {
                $return_values['tags'][] = $tag->toJSON(false);
            }

            if ($detailed) {
                $fields = $this->getProject()->getVisibleFieldsArray($this->getIssueType());

                foreach ($fields as $field => $details) {
                    $identifiable = true;
                    switch ($field) {
                        case 'shortname':
                        case 'description':
                        case 'votes':
                            $identifiable = false;
                        case 'resolution':
                        case 'priority':
                        case 'severity':
                        case 'category':
                        case 'reproducability':
                            $method = 'get' . ucfirst($field);
                            $value = $this->$method();
                            break;
                        case 'owner':
                            $value = $this->getOwner();
                            break;
                            break;
                        case 'percent_complete':
                            $value = $this->getPercentCompleted();
                            $identifiable = false;
                            break;
                        case 'user_pain':
                            $value = $this->getUserPain();
                            $identifiable = false;
                            break;
                        case 'reproduction_steps':
                            $value = $this->getReproductionSteps();
                            $identifiable = false;
                            break;
                        case 'estimated_time':
                            $value = $this->getEstimatedTime(true, true);
                            $identifiable = false;
                            break;
                        case 'spent_time':
                            $value = $this->getSpentTime(true, true);
                            $identifiable = false;
                            break;
                        case 'milestone':
                        case 'assignee':
                        case 'build':
                        case 'edition':
                        case 'component':
                            break;
                        default:
                            $value = $this->getCustomField($field);
                            $identifiable = false;
                            break;
                    }
                    if (isset($value)) {
                        if ($identifiable)
                            $return_values[$field] = ($value instanceof common\Identifiable) ? $value->toJSON() : null;
                        else
                            $return_values[$field] = $value;
                    }

                }

                $comments = [];
                foreach ($this->getComments() as $comment) {
                    $comments[$comment->getCommentNumber()] = $comment->toJSON();
                }

                $return_values['comments'] = $comments;
                $return_values['visible_fields'] = $fields;
            }

            return $return_values;
        }

        /**
         * Returns whether or not the issue has been deleted
         *
         * @return boolean
         */
        public function isDeleted()
        {
            return $this->_deleted;
        }

        /**
         * Returns whether or not the issue has been archived
         *
         * @return bool
         */
        public function isArchived(): bool
        {
            return $this->_archived;
        }

        /**
         * Archive the issue
         */
        public function archive()
        {
            $this->setArchived(true);
        }

        public function unArchive()
        {
            $this->setArchived(false);
        }

        /**
         * Set whether the issue is archived
         *
         * @param bool $archived
         */
        public function setArchived(bool $archived)
        {
            $this->_archived = $archived;
        }

        /**
         * Returns the timestamp for when the issue was last updated
         *
         * @return integer
         */
        public function getLastUpdatedTime(): int
        {
            return $this->_last_updated;
        }

        /**
         * Returns the issue title
         *
         * @return string
         */
        public function getRawTitle()
        {
            return $this->_title;
        }

        public function hasAssignee()
        {
            return (bool)($this->getAssignee() instanceof common\Identifiable);
        }

        public function setAssignee(common\Identifiable $assignee)
        {
            if ($assignee instanceof Team) {
                $this->_addChangedProperty('_assignee_user', null);
                $this->_addChangedProperty('_assignee_team', $assignee->getID());
            } else {
                $this->_addChangedProperty('_assignee_user', $assignee->getID());
                $this->_addChangedProperty('_assignee_team', null);

                if ($assignee instanceof User && $assignee->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_ASSIGNED_ISSUES, false)->isOn() && !$this->isSubscriber($assignee)) {
                    $this->addSubscriber($assignee->getID());
                }
            }
        }

        public function clearAssignee()
        {
            $this->_addChangedProperty('_assignee_user', null);
            $this->_addChangedProperty('_assignee_team', null);
        }

        public function hasOwner()
        {
            return (bool)($this->getOwner() instanceof common\Identifiable);
        }

        public function setOwner(common\Identifiable $owner)
        {
            if ($owner instanceof Team) {
                $this->_addChangedProperty('_owner_user', null);
                $this->_addChangedProperty('_owner_team', $owner);
            } else {
                $this->_addChangedProperty('_owner_user', $owner);
                $this->_addChangedProperty('_owner_team', null);
            }
        }

        public function clearOwner()
        {
            $this->_owner_team = null;
            $this->_owner_user = null;
        }

        public function setSaveComment($comment)
        {
            $this->_save_comment = $comment;
        }

        /**
         * Return an array of users available for mention autocompletion
         *
         * @return User[]
         */
        public function getMentionableUsers()
        {
            $users = [];
            foreach ($this->getRelatedUsers() as $user) {
                $users[$user->getID()] = $user;
            }
            foreach ($this->getComments() as $comment) {
                $users[$comment->getPostedBy()->getID()] = $comment->getPostedBy();
                foreach ($comment->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }

            return $users;
        }

        public function getMentionedUsers()
        {
            $users = [];
            $_description_parser = $this->_getDescriptionParser();
            $_reproduction_steps_parser = $this->_getReproductionStepsParser();
            if (!is_null($_description_parser) && $_description_parser->hasMentions()) {
                foreach ($_description_parser->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }
            if (!is_null($_reproduction_steps_parser) && $_reproduction_steps_parser->hasMentions()) {
                foreach ($_reproduction_steps_parser->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }
            foreach ($this->getComments() as $comment) {
                foreach ($comment->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }

            return $users;
        }

        public function getMilestoneOrder()
        {
            return $this->_milestone_order;
        }

        /**
         * Return if the user can edit scrum color
         *
         * @return boolean
         */
        public function canEditColor()
        {
            return $this->_permissionCheck('caneditissuecolor');
        }

        /**
         * Get spent time units with points and their description.
         *
         * @return array
         */
        public function getSpentTimeUnitsWithPoints()
        {
            $spent_time_units = array_intersect_key(['minutes' => __('%number_of minute(s)', ['%number_of' => '']), 'hours' => __('%number_of hour(s)', ['%number_of' => '']), 'days' => __('%number_of day(s)', ['%number_of' => '']), 'weeks' => __('%number_of week(s)', ['%number_of' => '']), 'months' => __('%number_of month(s)', ['%number_of' => ''])], array_flip($this->getProject()->getTimeUnits()));

            return ['points' => __('%number_of point(s)', ['%number_of' => ''])] + $spent_time_units;
        }

        /**
         * Get something summary text for transition time logger
         *
         * @return string
         */
        public function getTimeLoggerSomethingSummaryText()
        {
            $time_logger_units = array_intersect_key(['weeks' => '%weeks week(s)', 'days' => '%days day(s)', 'hours' => '%hours hour(s)', 'minutes' => '%minutes minute(s)'], array_flip($this->getProject()->getTimeUnits()));
            $last_time_unit = array_pop($time_logger_units);

            return 'Adds ' . implode(', ', $time_logger_units) . ' and ' . $last_time_unit;
        }

        /**
         * Get sums columns.
         *
         * @return array
         */
        public function getSums()
        {
            return $this->_sums;
        }

        /**
         * Set sums columns.
         *
         * @param array $sums
         */
        public function setSums(array $sums)
        {
            $this->_sums = $sums;
        }

        /**
         * Get sums spent time columns.
         *
         * @return string
         */
        public function getSumsSpentTime()
        {
            $any_exists = false;
            $time = [];

            foreach (common\Timeable::getUnits() as $time_unit) {
                if (!array_key_exists('spent_' . $time_unit, $this->_sums)) {
                    $time[$time_unit] = 0;
                    continue;
                }

                $time[$time_unit] = $this->_sums['spent_' . $time_unit];

                if (!$any_exists)
                    $any_exists = true;
            }

            if (isset($time['hours']) && $time['hours'] != 0)
                $time['hours'] = $time['hours'] / 100;

            if (isset($time['minutes']) && $time['minutes'] != 0) {
                $time['hours'] += floor($time['minutes'] / 60);
                $time['minutes'] = $time['minutes'] % 60;
            }

            if (!$any_exists)
                $time = $this->getSpentTime(true, true);

            return $this->getFormattedTime($time);
        }

        /**
         * Save changes made to the issue since last time
         *
         * @return boolean
         */
        protected function _preSave($is_new)
        {
            parent::_preSave($is_new);
            if ($is_new) {
                if (!$this->_issue_no)
                    $this->_issue_no = tables\Issues::getTable()->getNextIssueNumberForProductID($this->getProject()->getID());

                if (!$this->_posted) $this->_posted = NOW;
                if (!$this->_last_updated) $this->_last_updated = NOW;
                if (!$this->_posted_by) $this->_posted_by = Context::getUser();

                $step = $this->getProject()->getWorkflowScheme()->getWorkflowForIssuetype($this->getIssueType())->getFirstStep();
                $step->applyToIssue($this);

                return;
            }

            $this->_last_updated = NOW;
        }

        protected function _postSave($is_new)
        {
            $this->_saveCustomFieldValues();

            if (!$is_new) {
                $related_issues_to_save = $this->_processChanges();
                $comment = (isset($this->_save_comment)) ? $this->_save_comment : $this->addSystemComment('', Context::getUser()->getID());

                $this->triggerSaveEvent($comment, Context::getUser());

                foreach ($related_issues_to_save as $related_issue) {
                    $related_issue->save();
                }
            } else {
                framework\Event::createNew('core', 'pachno\core\entities\Issue::createNew_pre_notifications', $this)->trigger();
                $_description_parser = $this->_getDescriptionParser();
                $_reproduction_steps_parser = $this->_getReproductionStepsParser();
                if (!is_null($_description_parser) && $_description_parser->hasMentions()) {
                    foreach ($_description_parser->getMentions() as $user) {
                        if ($user->getID() == Context::getUser()->getID()) continue;

                        if (($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_MENTIONED, false)->isOn())) $this->_addNotificationIfNotNotified(Notification::TYPE_ISSUE_MENTIONED, $user, $this->getPostedBy());
                    }
                }
                if (!is_null($_reproduction_steps_parser) && $_reproduction_steps_parser->hasMentions()) {
                    foreach ($_reproduction_steps_parser->getMentions() as $user) {
                        if ($user->getID() == Context::getUser()->getID()) continue;

                        if (($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_MENTIONED, false)->isOn())) $this->_addNotificationIfNotNotified(Notification::TYPE_ISSUE_MENTIONED, $user, $this->getPostedBy());
                    }
                }
                $this->addLogEntry(LogItem::ACTION_ISSUE_CREATED, null, null, null, false, $this->getPosted(), $this->getPostedByID());

                if ($this->shouldAutomaticallySubscribeUser(Context::getUser())) $this->addSubscriber(Context::getUser()->getID());

                $this->_addCreateNotifications($this->getPostedBy());
                framework\Event::createNew('core', 'pachno\core\entities\Issue::createNew', $this)->trigger();
            }

            if (Context::getUser() instanceof User && Context::getUser()->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_CREATED_UPDATED_COMMENTED_ISSUES, false)->isOn() && !$this->isSubscriber(Context::getUser())) {
                $this->addSubscriber(Context::getUser()->getID());
            }

            $this->_log_items_added = [];
            $this->getProject()->clearRecentActivities();

            if ($this->isChildIssue() && ($this->hasEstimatedTime() || $this->hasSpentTime())) {
                foreach ($this->getParentIssues() as $issue) {
                    $issue->calculateTime();
                    $issue->save();
                }
            }

            if ($this->getMilestone() instanceof Milestone) {
                $this->getMilestone()->updateStatus();
                $this->getMilestone()->save();
            }

            return true;
        }

        protected function _saveCustomFieldValues()
        {
            foreach (CustomDatatype::getAll() as $key => $customdatatype) {
                switch ($customdatatype->getType()) {
                    case CustomDatatype::INPUT_TEXT:
                    case CustomDatatype::INPUT_TEXTAREA_SMALL:
                    case CustomDatatype::INPUT_TEXTAREA_MAIN:
                    case CustomDatatype::DATE_PICKER:
                    case CustomDatatype::DATETIME_PICKER:
                        $option_id = $this->getCustomField($key);
                        tables\IssueCustomFields::getTable()->saveIssueCustomFieldValue($option_id, $customdatatype->getID(), $this->getID());
                        break;
                    case CustomDatatype::EDITIONS_CHOICE:
                    case CustomDatatype::COMPONENTS_CHOICE:
                    case CustomDatatype::RELEASES_CHOICE:
                    case CustomDatatype::MILESTONE_CHOICE:
                    case CustomDatatype::STATUS_CHOICE:
                    case CustomDatatype::USER_CHOICE:
                    case CustomDatatype::TEAM_CHOICE:
                    case CustomDatatype::CLIENT_CHOICE:
                        $option_object = null;
                        try {
                            switch ($customdatatype->getType()) {
                                case CustomDatatype::EDITIONS_CHOICE:
                                case CustomDatatype::COMPONENTS_CHOICE:
                                case CustomDatatype::RELEASES_CHOICE:
                                case CustomDatatype::MILESTONE_CHOICE:
                                case CustomDatatype::CLIENT_CHOICE:
                                case CustomDatatype::STATUS_CHOICE:
                                case CustomDatatype::USER_CHOICE:
                                case CustomDatatype::TEAM_CHOICE:
                                    $option_object = $this->getCustomField($key);
                                    break;
                            }
                        } catch (Exception $e) {
                        }
                        $option_id = (is_object($option_object)) ? $option_object->getID() : null;
                        tables\IssueCustomFields::getTable()->saveIssueCustomFieldOption($option_id, $customdatatype->getID(), $this->getID());
                        break;
                    default:
                        $option_id = ($this->getCustomField($key) instanceof CustomDatatypeOption) ? $this->getCustomField($key)->getID() : null;
                        tables\IssueCustomFields::getTable()->saveIssueCustomFieldOption($option_id, $customdatatype->getID(), $this->getID());
                        break;
                }
            }
        }

        /**
         * Processes field changes for an issue. Two types of processing occur
         * within this function:
         *
         * - Logging the change in issue history. This happens for every field.
         * - Updates to other related objects (such as projects, milestones,
         *   other issues etc). This type of processing is dependant on specific
         *   field that gets changed.
         *
         *
         * @return array Array of related issues that have been affected in some way and need to be saved.
         */
        protected function _processChanges()
        {
            $related_issues_to_save = [];
            $changed_properties = $this->_getChangedProperties();

            if (count($changed_properties)) {
                $is_saved_estimated = false;
                $is_saved_spent = false;
                $is_saved_assignee = false;
                $is_saved_owner = false;
                foreach ($changed_properties as $property => $value) {
                    $compare_value = (is_object($this->$property)) ? $this->$property->getID() : $this->$property;
                    $original_value = $value['original_value'];
                    if ($original_value != $compare_value) {
                        switch ($property) {
                            case '_title':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_TITLE, Context::getI18n()->__("Title updated"), $original_value, $compare_value);
                                break;
                            case '_shortname':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_SHORT_LABEL, Context::getI18n()->__("Issue label updated"), $original_value, $compare_value);
                                break;
                            case '_description':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_DESCRIPTION, Context::getI18n()->__("Description updated"), $original_value, $compare_value);
                                break;
                            case '_reproduction_steps':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_REPRODUCTION_STEPS, Context::getI18n()->__("Reproduction steps updated"), $original_value, $compare_value);
                                break;
                            case '_category':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Category::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Not determined');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getCategory() instanceof Datatype) ? $this->getCategory()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_CATEGORY, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_pain_bug_type':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = self::getPainTypesOrLabel('pain_bug_type', $original_value)) ? $old_item : Context::getI18n()->__('Not determined');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($new_item = self::getPainTypesOrLabel('pain_bug_type', $value['current_value'])) ? $new_item : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PAIN_BUG_TYPE, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_pain_effect':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = self::getPainTypesOrLabel('pain_effect', $original_value)) ? $old_item : Context::getI18n()->__('Not determined');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($new_item = self::getPainTypesOrLabel('pain_effect', $value['current_value'])) ? $new_item : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PAIN_EFFECT, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_pain_likelihood':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = self::getPainTypesOrLabel('pain_likelihood', $original_value)) ? $old_item : Context::getI18n()->__('Not determined');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($new_item = self::getPainTypesOrLabel('pain_likelihood', $value['current_value'])) ? $new_item : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PAIN_LIKELIHOOD, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_user_pain':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PAIN_SCORE, $original_value . ' &rArr; ' . $value['current_value']);
                                break;
                            case '_status':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Status::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getStatus() instanceof Datatype) ? $this->getStatus()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_STATUS, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_reproducability':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Reproducability::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getReproducability() instanceof Datatype) ? $this->getReproducability()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_REPRODUCABILITY, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_priority':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Priority::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getPriority() instanceof Datatype) ? $this->getPriority()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PRIORITY, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_assignee_team':
                            case '_assignee_user':
                                if (!$is_saved_assignee) {
                                    $new_name = ($this->getAssignee() instanceof common\Identifiable) ? $this->getAssignee()->getNameWithUsername() : Context::getI18n()->__('Not assigned');

                                    if ($this->getAssignee() instanceof User) {
                                        $this->startWorkingOnIssue($this->getAssignee());
                                        $new_name = $this->getAssignee()->getNameWithUsername();
                                    }

                                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_ASSIGNEE, $new_name);
                                    $is_saved_assignee = true;
                                }
                                break;
                            case '_posted_by':
                                $old_identifiable = ($original_value) ? User::getB2DBTable()->selectById($original_value) : Context::getI18n()->__('Unknown');
                                $old_name = ($old_identifiable instanceof User) ? $old_identifiable->getNameWithUsername() : Context::getI18n()->__('Unknown');
                                $new_name = $this->getPostedBy()->getNameWithUsername();

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_POSTED_BY, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_being_worked_on_by_user':
                                if ($original_value != 0) {
                                    $old_identifiable = User::getB2DBTable()->selectById($original_value);
                                    $old_name = ($old_identifiable instanceof User) ? $old_identifiable->getNameWithUsername() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not being worked on');
                                }
                                $new_name = ($this->getUserWorkingOnIssue() instanceof User) ? $this->getUserWorkingOnIssue()->getNameWithUsername() : Context::getI18n()->__('Not being worked on');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_USER_WORKING_ON_ISSUE, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_owner_team':
                            case '_owner_user':
                                if (!$is_saved_owner) {
                                    $new_name = ($this->getOwner() instanceof common\Identifiable) ? $this->getOwner()->getNameWithUsername() : Context::getI18n()->__('Not owned by anyone');

                                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_OWNER, $new_name);
                                    $is_saved_owner = true;
                                }
                                break;
                            case '_percent_complete':
                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_PERCENT_COMPLETE, $original_value . '% &rArr; ' . $this->getPercentCompleted() . '%', $original_value, $compare_value);
                                break;
                            case '_resolution':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Resolution::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getResolution() instanceof Datatype) ? $this->getResolution()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_RESOLUTION, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_severity':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Severity::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getSeverity() instanceof Datatype) ? $this->getSeverity()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_SEVERITY, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_milestone':
                                if ($original_value != 0) {
                                    $old_milestone = Milestone::getB2DBTable()->selectById($original_value);
                                    $old_milestone->updateStatus();
                                    $old_milestone->save();
                                    $old_name = $old_milestone ? $old_milestone->getName() : Context::getI18n()->__('Not determined');
                                } else {
                                    $old_name = Context::getI18n()->__('Not determined');
                                }
                                $new_name = ($this->getMilestone() instanceof Milestone) ? $this->getMilestone()->getName() : Context::getI18n()->__('Not determined');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_MILESTONE, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                $this->_milestone_order = 0;
                                break;
                            case '_issuetype':
                                if ($original_value != 0) {
                                    $old_name = ($old_item = Issuetype::getB2DBTable()->selectById($original_value)) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                } else {
                                    $old_name = Context::getI18n()->__('Unknown');
                                }
                                $new_name = ($this->getIssuetype() instanceof Issuetype) ? $this->getIssuetype()->getName() : Context::getI18n()->__('Unknown');

                                $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_ISSUETYPE, $old_name . ' &rArr; ' . $new_name, $original_value, $compare_value);
                                break;
                            case '_estimated_months':
                            case '_estimated_weeks':
                            case '_estimated_days':
                            case '_estimated_hours':
                            case '_estimated_minutes':
                            case '_estimated_points':
                                if (!$is_saved_estimated) {
                                    $time_units = common\Timeable::getUnitsWithPoints();
                                    $old_time = array_fill_keys($time_units, 0);
                                    foreach ($time_units as $time_unit) {
                                        if ($this->isPropertyChanged('_estimated_' . $time_unit)) {
                                            $old_time[$time_unit] = $this->getChangedPropertyOriginal('_estimated_' . $time_unit);
                                        } else {
                                            $old_time[$time_unit] = $this->{'_estimated_' . $time_unit};
                                        }
                                    }
                                    $old_formatted_time = (array_sum($old_time) > 0) ? Issue::getFormattedTime($old_time) : Context::getI18n()->__('Not estimated');
                                    $new_formatted_time = ($this->hasEstimatedTime()) ? Issue::getFormattedTime($this->getEstimatedTime()) : Context::getI18n()->__('Not estimated');
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_ESTIMATED_TIME, $old_formatted_time . ' &rArr; ' . $new_formatted_time, serialize($old_time), serialize($this->getEstimatedTime()));
                                    $is_saved_estimated = true;
                                }
                                break;
                            case '_spent_months':
                            case '_spent_weeks':
                            case '_spent_days':
                            case '_spent_hours':
                            case '_spent_minutes':
                            case '_spent_points':
                                if (!$is_saved_spent) {
                                    $time_units = common\Timeable::getUnitsWithPoints();
                                    $old_time = array_fill_keys($time_units, 0);
                                    foreach ($time_units as $time_unit) {
                                        if ($this->isPropertyChanged('_spent_' . $time_unit)) {
                                            $old_time[$time_unit] = $this->getChangedPropertyOriginal('_spent_' . $time_unit);
                                        } else {
                                            $old_time[$time_unit] = $this->{'_spent_' . $time_unit};
                                        }
                                    }
                                    $old_time['hours'] = round($old_time['hours'] / 100, 2);
                                    $old_formatted_time = (array_sum($old_time) > 0) ? Issue::getFormattedTime($old_time) : Context::getI18n()->__('No time spent');
                                    $new_formatted_time = ($this->hasSpentTime()) ? Issue::getFormattedTime($this->getSpentTime()) : Context::getI18n()->__('No time spent');
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_TIME_SPENT, $old_formatted_time . ' &rArr; ' . $new_formatted_time, serialize($old_time), serialize($this->getSpentTime()));
                                    $is_saved_spent = true;
                                }
                                break;
                            case '_state':
                                if ($this->isClosed()) {
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_CLOSE);
                                    if ($this->getMilestone() instanceof Milestone) {
                                        if ($this->getMilestone()->isSprint()) {
                                            if (!$this->getIssueType()->isTask()) {
                                                $this->setSpentPoints($this->getEstimatedPoints());
                                            } else {
                                                if ($this->getSpentHours() < $this->getEstimatedHours()) {
                                                    $this->setSpentHours($this->getEstimatedHours());
                                                }
                                                if ($this->getSpentMinutes() < $this->getEstimatedMinutes()) {
                                                    $this->setSpentMinutes($this->getEstimatedMinutes());
                                                }
                                                foreach ($this->getParentIssues() as $parent_issue) {
                                                    if ($parent_issue->checkTaskStates()) {
                                                        $related_issues_to_save[] = $parent_issue;
                                                    }
                                                }
                                            }
                                        }
                                        $this->getMilestone()->updateStatus();
                                        $this->getMilestone()->save();
                                    }
                                } else {
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_REOPEN);
                                }
                                break;
                            case '_blocking':
                                if ($this->isBlocking()) {
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_ADD_BLOCKING);
                                } else {
                                    $this->addLogEntry(LogItem::ACTION_ISSUE_REMOVE_BLOCKING);
                                }
                                break;
                            default:
                                if (mb_substr($property, 0, 12) == '_customfield') {
                                    $key = mb_substr($property, 12);
                                    $customdatatype = CustomDatatype::getByKey($key);

                                    switch ($customdatatype->getType()) {
                                        case CustomDatatype::INPUT_TEXT:
                                            $new_value = ($this->getCustomField($key) != '') ? $this->getCustomField($key) : Context::getI18n()->__('Unknown');
                                            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_CUSTOMFIELD, $key . ': ' . $new_value, $original_value, $compare_value);
                                            break;
                                        case CustomDatatype::INPUT_TEXTAREA_SMALL:
                                        case CustomDatatype::INPUT_TEXTAREA_MAIN:
                                            $new_value = ($this->getCustomField($key) != '') ? $this->getCustomField($key) : Context::getI18n()->__('Unknown');
                                            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_CUSTOMFIELD, $key . ': ' . $new_value, $original_value, $compare_value);
                                            break;
                                        case CustomDatatype::EDITIONS_CHOICE:
                                        case CustomDatatype::COMPONENTS_CHOICE:
                                        case CustomDatatype::RELEASES_CHOICE:
                                        case CustomDatatype::MILESTONE_CHOICE:
                                        case CustomDatatype::STATUS_CHOICE:
                                        case CustomDatatype::TEAM_CHOICE:
                                        case CustomDatatype::USER_CHOICE:
                                        case CustomDatatype::CLIENT_CHOICE:
                                            $old_object = null;
                                            $new_object = null;
                                            try {
                                                switch ($customdatatype->getType()) {
                                                    case CustomDatatype::EDITIONS_CHOICE:
                                                        $old_object = Edition::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::COMPONENTS_CHOICE:
                                                        $old_object = Component::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::RELEASES_CHOICE:
                                                        $old_object = Build::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::MILESTONE_CHOICE:
                                                        $old_object = Milestone::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::STATUS_CHOICE:
                                                        $old_object = Status::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::TEAM_CHOICE:
                                                        $old_object = Team::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::USER_CHOICE:
                                                        $old_object = User::getB2DBTable()->selectById($original_value);
                                                        break;
                                                    case CustomDatatype::CLIENT_CHOICE:
                                                        $old_object = Client::getB2DBTable()->selectById($original_value);
                                                        break;
                                                }
                                            } catch (Exception $e) {
                                            }
                                            try {
                                                switch ($customdatatype->getType()) {
                                                    case CustomDatatype::EDITIONS_CHOICE:
                                                    case CustomDatatype::COMPONENTS_CHOICE:
                                                    case CustomDatatype::RELEASES_CHOICE:
                                                    case CustomDatatype::MILESTONE_CHOICE:
                                                    case CustomDatatype::STATUS_CHOICE:
                                                    case CustomDatatype::TEAM_CHOICE:
                                                    case CustomDatatype::USER_CHOICE:
                                                    case CustomDatatype::CLIENT_CHOICE:
                                                        $new_object = $this->getCustomField($key);
                                                        break;
                                                }
                                            } catch (Exception $e) {
                                            }
                                            $old_value = (is_object($old_object)) ? $old_object->getName() : Context::getI18n()->__('Unknown');
                                            $new_value = (is_object($new_object)) ? $new_object->getName() : Context::getI18n()->__('Unknown');
                                            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_CUSTOMFIELD, $key . ': ' . $old_value . ' &rArr; ' . $new_value, $original_value, $compare_value);
                                            break;
                                        default:
                                            $old_item = null;
                                            try {
                                                $old_item = ($original_value) ? new CustomDatatypeOption($original_value) : null;
                                            } catch (Exception $e) {
                                            }
                                            $old_value = ($old_item instanceof CustomDatatypeOption) ? $old_item->getName() : Context::getI18n()->__('Unknown');
                                            $new_value = ($this->getCustomField($key) instanceof CustomDatatypeOption) ? $this->getCustomField($key)->getName() : Context::getI18n()->__('Unknown');
                                            $this->addLogEntry(LogItem::ACTION_ISSUE_UPDATE_CUSTOMFIELD, $key . ': ' . $old_value . ' &rArr; ' . $new_value, $original_value, $compare_value);
                                            break;
                                    }
                                }
                                break;
                        }
                    }
                }

                if ($is_saved_estimated) {
                    tables\IssueEstimates::getTable()->saveEstimate($this->getID(), $this->_estimated_months, $this->_estimated_weeks, $this->_estimated_days, $this->_estimated_hours, $this->_estimated_minutes, $this->_estimated_points);
                }

            }

            return $related_issues_to_save;
        }

        /**
         * Returns a list of changed properties:
         *         array('property_name' => 'old_value')
         *
         * @return array
         */
        protected function _getChangedProperties()
        {
            return $this->_changed_items;
        }

        public static function getPainTypesOrLabel($type, $id = null)
        {
            $i18n = Context::getI18n();

            $bugtypes = [];
            $bugtypes[7] = $i18n->__('Crash: Bug causes crash or data loss / asserts in the debug release');
            $bugtypes[6] = $i18n->__('Major usability: Impairs usability in key scenarios');
            $bugtypes[5] = $i18n->__('Minor usability: Impairs usability in secondary scenarios');
            $bugtypes[4] = $i18n->__('Balancing: Enables degenerate usage strategies that harm the experience');
            $bugtypes[3] = $i18n->__('Visual and Sound Polish: Aesthetic issues');
            $bugtypes[2] = $i18n->__('Localization');
            $bugtypes[1] = $i18n->__('Documentation: A documentation issue');

            $effects = [];
            $effects[5] = $i18n->__('Blocking further progress on the daily build');
            $effects[4] = $i18n->__('A User would return the product / cannot RTM / the team would hold the release for this bug');
            $effects[3] = $i18n->__('A User would likely not purchase the product / will show up in review / clearly a noticeable issue');
            $effects[2] = $i18n->__("A Pain - users won't like this once they notice it / a moderate number of users won't buy");
            $effects[1] = $i18n->__('Nuisance - not a big deal but noticeable / extremely unlikely to affect sales');

            $likelihoods = [];
            $likelihoods[5] = $i18n->__('Will affect all users');
            $likelihoods[4] = $i18n->__('Will affect most users');
            $likelihoods[3] = $i18n->__('Will affect average number of users');
            $likelihoods[2] = $i18n->__('Will only affect a few users');
            $likelihoods[1] = $i18n->__('Will affect almost no one');

            if ($id === 0) return null;

            switch ($type) {
                case 'pain_bug_type':
                    return ($id === null) ? $bugtypes : $bugtypes[$id];
                    break;
                case 'pain_likelihood':
                    return ($id === null) ? $likelihoods : $likelihoods[$id];
                    break;
                case 'pain_effect':
                    return ($id === null) ? $effects : $effects[$id];
                    break;
            }

            return ($id === null) ? [] : null;
        }

        /**
         * Returns the issue status
         *
         * @return Status
         */
        public function getStatus(): ?Status
        {
            return $this->_b2dbLazyLoad('_status');
        }

        /**
         * Set the status
         *
         * @param integer|Status $status_id The status ID to change to
         */
        public function setStatus($status_id)
        {
            $this->_addChangedProperty('_status', $status_id);
        }

        /**
         * Register a user as working on the issue
         *
         * @param User $user
         */
        public function startWorkingOnIssue(User $user)
        {
            $this->_addChangedProperty('_being_worked_on_by_user', $user->getID());
            $this->_being_worked_on_by_user_since = NOW;
        }

        /**
         * Checks to see whether a property has unsaved changes
         *
         * @param string $property The field key
         *
         * @return boolean
         */
        public function isPropertyChanged($property)
        {
            if (empty($this->_changed_items)) return false;

            return array_key_exists($property, $this->_changed_items);
        }

        /**
         * Returns a single changed propertys original value
         *
         * @param $property
         *
         * @return mixed
         */
        protected function getChangedPropertyOriginal($property)
        {
            if ($this->isPropertyChanged($property)) {
                return $this->_changed_items[$property]['original_value'];
            }

            return null;
        }

        /**
         * Returns a string-formatted time based on project setting
         *
         * @param array $time array of weeks, days, hours and minutes
         *
         * @return string
         */
        public static function getFormattedTime($time, $strict = true)
        {
            $values = [];
            $i18n = Context::getI18n();
            if (!is_array($time)) throw new Exception("That's not a valid time");
            if (array_key_exists('months', $time) && $time['months'] > 0) {
                $values[] = ($time['months'] == 1) ? $i18n->__('1 month') : $i18n->__('%number_of months', ['%number_of' => $time['months']]);
            }
            if (array_key_exists('weeks', $time) && $time['weeks'] > 0) {
                $values[] = ($time['weeks'] == 1) ? $i18n->__('1 week') : $i18n->__('%number_of weeks', ['%number_of' => $time['weeks']]);
            }
            if (array_key_exists('days', $time) && ($time['days'] > 0 || !$strict)) {
                $values[] = ($time['days'] == 1) ? $i18n->__('1 day') : $i18n->__('%number_of days', ['%number_of' => $time['days']]);
            }
            if (array_key_exists('hours', $time) && ($time['hours'] > 0 || !$strict)) {
                $values[] = ($time['hours'] == 1) ? $i18n->__('1 hour') : $i18n->__('%number_of hours', ['%number_of' => $time['hours']]);
            }
            if (array_key_exists('minutes', $time) && ($time['minutes'] > 0 || !$strict)) {
                $values[] = ($time['minutes'] == 1) ? $i18n->__('1 minute') : $i18n->__('%number_of minutes', ['%number_of' => $time['minutes']]);
            }
            $retval = join(', ', $values);

            if (array_key_exists('points', $time) && ($time['points'] > 0 || !$strict)) {
                if (!empty($values)) {
                    $retval .= ' / ';
                }
                $retval .= ($time['points'] == 1) ? $i18n->__('1 point') : $i18n->__('%number_of points', ['%number_of' => $time['points']]);
            }

            return ($retval != '') ? $retval : $i18n->__('No time');
        }

        public function checkTaskStates()
        {
            if ($this->isOpen()) {
                $open_issues = false;
                foreach ($this->getChildIssues() as $child_issue) {
                    if ($child_issue->getIssueType()->isTask()) {
                        if ($child_issue->isOpen()) {
                            $open_issues = true;
                            break;
                        }
                    }
                }
                if (!$open_issues) {
                    $this->close();

                    return true;
                }
            }

            return false;
        }

        /**
         * Close the issue
         */
        public function close()
        {
            $this->setState(self::STATE_CLOSED);
        }

        /**
         * Return whether the issue is blocking the next release or not
         *
         * @return boolean
         */
        public function isBlocking(): bool
        {
            return (bool) $this->_blocking;
        }

        /**
         * Mark issue as blocking or not blocking
         *
         * @param boolean $blocking [optional] Whether it's blocking or not
         */
        public function setBlocking($blocking = true)
        {
            $this->_addChangedProperty('_blocking', (bool)$blocking);
        }

        /**
         * Adds a system comment
         *
         * @param string $text Comment text
         * @param integer $uid The user ID that posted the comment
         *
         * @return Comment
         */
        public function addSystemComment($text, $uid, $module = 'core')
        {
            $comment = new Comment();
            $comment->setContent($text);
            $comment->setPostedBy($uid);
            $comment->setTargetID($this->getID());
            $comment->setTargetType(Comment::TYPE_ISSUE);
            $comment->setSystemComment();
            $comment->setModuleName($module);
            if (!Settings::isCommentTrailClean()) {
                $comment->save();
            }

            return $comment;
        }

        public function triggerSaveEvent($comment, $updated_by)
        {
            $log_items = $this->_log_items_added;
            if ($comment instanceof Comment && count($log_items)) {
                if ($comment->getID()) {
                    foreach ($log_items as $item) {
                        $item->setComment($comment);
                        $item->save();
                    }
                    $comment->setHasAssociatedChanges(true);
                    $comment->save();
                }
            }
            framework\Event::createNew('core', 'pachno\core\entities\Issue::save_pre_notifications', $this)->trigger();
            $this->_addUpdateNotifications($updated_by);
            $event = framework\Event::createNew('core', 'pachno\core\entities\Issue::save', $this, compact('comment', 'log_items', 'updated_by'));
            $event->trigger();
        }

        protected function _addUpdateNotifications($updated_by)
        {
            $uids = tables\UserIssues::getTable()->getUserIDsByIssueID($this->getID());
            $users = tables\Users::getTable()->getByUserIDs($uids);

            foreach ($users as $user) {
                if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_SUBSCRIBED_ISSUES, false)->isOn() && $this->isSubscriber($user)) {
                    $this->_addNotificationIfNotNotified(Notification::TYPE_ISSUE_UPDATED, $user, $updated_by);
                }
            }
        }

        public function isSubscriber($user)
        {
            if (!$user instanceof User) return false;

            $user_id = (string)$user->getID();
            $subscribers = (array)$this->getSubscribers();
            $new_subscribers = (array)$this->_new_subscribers;

            return (bool)in_array($user_id, $new_subscribers) || (bool)array_key_exists($user_id, $subscribers);
        }

        /**
         * Return an array of subscribed users
         *
         * @return User[]
         */
        public function getSubscribers()
        {
            $this->_b2dbLazyLoad('_subscribers');

            return $this->_subscribers;
        }

        protected function _addNotificationIfNotNotified($type, $user, $updated_by)
        {
            if (!$this->shouldUserBeNotified($user, $updated_by)) return;

            $this->_addNotification($type, $user, $updated_by);
        }

        public function shouldUserBeNotified($user, $updated_by)
        {
            if (!$this->hasAccess($user)) return false;

            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_UPDATED_SELF, false)->isOff() && $user->getID() === $updated_by->getID()) return false;

            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE, false)->isOff()) return true;

            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE . '_issue_' . $this->getID(), false)->isOff()) {
                $user->setNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE . '_issue_' . $this->getID(), true);

                return true;
            }

            return false;
        }

        protected function _addNotification($type, $user, $updated_by)
        {
            $notification = new Notification();
            $notification->setTarget($this);
            $notification->setNotificationType($type);
            $notification->setTriggeredByUser($updated_by);
            $notification->setUser($user);
            $notification->save();
        }

        /**
         * @return TextParser
         */
        protected function _getDescriptionParser()
        {
            if (is_null($this->_description_parser)) {
                $this->getParsedDescription([]);
            }

            return $this->_description_parser;
        }

        public function getParsedDescription($options)
        {
            return $this->_getParsedText($this->getDescription(), $this->getDescriptionSyntax(), $options, '_description_parser');
        }

        protected function _getParsedText($text, $syntax, $options = [], $parser_ref = null)
        {
            switch ($syntax) {
                default:
                case Settings::SYNTAX_PT:
                    $options = ['plain' => true];
                case Settings::SYNTAX_MW:
                    $parser = new TextParser($text);
                    foreach ($options as $option => $value) {
                        $parser->setOption($option, $value);
                    }
                    $text = $parser->getParsedText();
                    break;
                case Settings::SYNTAX_MD:
                    $parser = new TextParserMarkdown();
                    $text = $parser->transform($text);
                    break;
            }

            if (isset($parser) && !is_null($parser_ref)) {
                $this->$parser_ref = $parser;
            }

            return $text;
        }

        /**
         * Returns the description
         *
         * @return string
         */
        public function getDescription()
        {
            return $this->_description;
        }

        /**
         * Set the description
         *
         * @param string $description
         */
        public function setDescription($description)
        {
            $this->_addChangedProperty('_description', $description);
        }

        /**
         * Returns the description syntax
         *
         * @return integer
         */
        public function getDescriptionSyntax()
        {
            return $this->_description_syntax;
        }

        /**
         * Set the description syntax
         *
         * @param integer $syntax
         */
        public function setDescriptionSyntax($syntax)
        {
            if (!is_numeric($syntax)) $syntax = Settings::getSyntaxValue($syntax);

            $this->_addChangedProperty('_description_syntax', $syntax);
        }

        protected function _getReproductionStepsParser()
        {
            if (is_null($this->_reproduction_steps_parser)) {
                $this->getParsedReproductionSteps([]);
            }

            return $this->_reproduction_steps_parser;
        }

        public function getParsedReproductionSteps($options)
        {
            return $this->_getParsedText($this->getReproductionSteps(), $this->getReproductionStepsSyntax(), $options, '_reproduction_steps_parser');
        }

        /**
         * Returns the issues reproduction steps
         *
         * @return string
         */
        public function getReproductionSteps()
        {
            return $this->_reproduction_steps;
        }

        /**
         * Set the reproduction steps
         *
         * @param string $reproduction_steps
         */
        public function setReproductionSteps($reproduction_steps)
        {
            $this->_addChangedProperty('_reproduction_steps', $reproduction_steps);
        }

        /**
         * Returns the issues reproduction steps syntax
         *
         * @return integer
         */
        public function getReproductionStepsSyntax()
        {
            return $this->_reproduction_steps_syntax;
        }

        /**
         * Return timestamp for when the issue was posted
         *
         * @return integer
         */
        public function getPosted(): int
        {
            return $this->_posted;
        }

        /**
         * Set the posted time
         *
         * @param integer $time
         */
        public function setPosted($time)
        {
            $this->_posted = $time;
        }

        public function shouldAutomaticallySubscribeUser($user)
        {
            if (!$this->hasAccess($user) || $this->isSubscriber($user)) return false;

            if (!$user instanceof User) return false;

            if ($this->getCategory() instanceof Category) {
                if ($user->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_NEW_ISSUES_MY_PROJECTS_CATEGORY . '_' . $this->getCategory()->getID(), false)->isOn())
                    return true;
            }

            return ($user->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_NEW_ISSUES_MY_PROJECTS, false)->isOn() || $user->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_NEW_ISSUES_MY_PROJECTS . '_' . $this->getProject()->getId(), false)->isOn());
        }

        public function addSubscriber($user_id)
        {
            tables\UserIssues::getTable()->addStarredIssue($user_id, $this->getID());
            $this->_new_subscribers[] = $user_id;
        }

        protected function _addCreateNotifications($updated_by)
        {
            foreach ($this->getRelatedUsers() as $user) {
                if ($this->shouldAutomaticallySubscribeUser($user)) $this->addSubscriber($user->getID());

                if ($this->getCategory() instanceof Category && $user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_NEW_ISSUES_MY_PROJECTS_CATEGORY . '_' . $this->getCategory()->getID(), false)->isOn())
                    $this->_addNotificationIfNotNotified(Notification::TYPE_ISSUE_CREATED, $user, $updated_by);
            }
        }

        /**
         * Returns an array with everyone related to this project
         *
         * @return User[]
         */
        public function getRelatedUsers()
        {
            $uids = [];
            $teams = [];

            // Add the poster
            $uids[$this->getPostedByID()] = $this->getPostedByID();

            // Add all users from the team owning the issue if valid
            // or add the owning user if a user owns the issue
            if ($this->getOwner() instanceof Team) {
                $teams[$this->getOwner()] = $this->getOwner();
            } elseif ($this->getOwner() instanceof User) {
                $uids[$this->getOwner()->getID()] = $this->getOwner()->getID();
            }

            // Add all users from the team assigned to the issue if valid
            // or add the assigned user if a user is assigned to the issue
            if ($this->getAssignee() instanceof Team) {
                $teams[$this->getAssignee()->getID()] = $this->getAssignee();
            } elseif ($this->getAssignee() instanceof User) {
                $uids[$this->getAssignee()->getID()] = $this->getAssignee()->getID();
            }

            // Add all users in the team who leads the project, if valid
            // or add the user who leads the project, if valid
            if ($this->getProject()->getLeader() instanceof Team) {
                $teams[$this->getProject()->getLeader()->getID()] = $this->getProject()->getLeader();
            } elseif ($this->getProject()->getLeader() instanceof User) {
                $uids[$this->getProject()->getLeader()->getID()] = $this->getProject()->getLeader()->getID();
            }

            // Same for QA
            if ($this->getProject()->getQaResponsible() instanceof Team) {
                $teams[$this->getProject()->getQaResponsible()->getID()] = $this->getProject()->getQaResponsible();
            } elseif ($this->getProject()->getQaResponsible() instanceof User) {
                $uids[$this->getProject()->getQaResponsible()->getID()] = $this->getProject()->getQaResponsible()->getID();
            }

            foreach ($this->getProject()->getAssignedTeams() as $team) {
                $teams[$team->getID()] = $team;
            }
            foreach ($this->getProject()->getAssignedUsers() as $member) {
                $uids[$member->getID()] = $member->getID();
            }

            // Add all users relevant for all affected editions
            foreach ($this->getEditions() as $edition_list) {
                if ($edition_list['edition']->getLeader() instanceof Team) {
                    $teams[$edition_list['edition']->getLeaderID()] = $edition_list['edition']->getLeader();
                } elseif ($edition_list['edition']->getLeader() instanceof User) {
                    $uids[$edition_list['edition']->getLeaderID()] = $edition_list['edition']->getLeaderID();
                }
                if ($edition_list['edition']->getQaResponsible() instanceof Team) {
                    $teams[$edition_list['edition']->getQaResponsibleID()] = $edition_list['edition']->getQaResponsible();
                } elseif ($edition_list['edition']->getQaResponsible() instanceof User) {
                    $uids[$edition_list['edition']->getQaResponsibleID()] = $edition_list['edition']->getQaResponsibleID();
                }
            }

            foreach ($teams as $team) {
                foreach ($team->getMembers() as $user) {
                    $uids[$user->getID()] = $user->getID();
                }
            }

            if (isset($uids[Context::getUser()->getID()])) unset($uids[Context::getUser()->getID()]);
            $users = tables\Users::getTable()->getByUserIDs($uids);

            return $users;
        }

        public function isChildIssue()
        {
            return (bool)count($this->getParentIssues());
        }

        /**
         * Set the reproduction steps syntax
         *
         * @param integer $syntax
         */
        public function setReproductionStepsSyntax($syntax)
        {
            if (!is_numeric($syntax)) $syntax = Settings::getSyntaxValue($syntax);

            $this->_addChangedProperty('_reproduction_steps_syntax', $syntax);
        }

    }
