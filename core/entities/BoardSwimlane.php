<?php

    namespace pachno\core\entities;

    use b2db\QueryColumnSort;
    use framework\Context;
    use pachno\core\entities\common\Identifiable;
    use pachno\core\entities\tables\Issues;

    /**
     * Agile board swimlane class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 3.1
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package pachno
     * @subpackage agile
     */

    /**
     * Agile board swimlane class
     *
     * @package pachno
     * @subpackage agile
     */
    class BoardSwimlane
    {

        /**
         * The identifiable objects for this swimlane
         *
         * @var array|Identifiable
         */
        protected $_identifiables;

        /**
         * @var AgileBoard
         */
        protected $_board;

        /**
         * Milestone
         * @var Milestone
         */
        protected $_milestone;

        /**
         * Cached search object
         * @var SavedSearch
         */
        protected $_search_object;

        protected $_name;

        protected $_identifier;

        public function getIdentifiables()
        {
            return $this->_identifiables;
        }

        public function setIdentifiables($identifiables)
        {
            $this->_identifiables = (is_array($identifiables)) ? $identifiables : [$identifiables];
        }

        public function getName()
        {
            if ($this->_name === null) {
                $names = [];
                foreach ($this->_identifiables as $identifiable) {
                    $names[] = ($identifiable instanceof Identifiable) ? $identifiable->getName() : Context::getI18n()->__('Unknown / not set');
                }
                $this->_name = join(', ', $names);
            }

            return $this->_name;
        }

        public function hasIdentifiables()
        {
            foreach ($this->_identifiables as $identifiable) {
                if ($identifiable instanceof Identifiable) return true;
            }

            return false;
        }

        public function getIssues()
        {
            if (!$this->getBoard()->usesSwimlanes() || in_array($this->getBoard()->getSwimlaneType(), [AgileBoard::SWIMLANES_EXPEDITE, AgileBoard::SWIMLANES_GROUPING])) {
                $this->_setupSearchObject();

                return $this->_search_object->getIssues();
            } else {
                if ($this->getIdentifierIssue() instanceof Issue) {
                    return $this->getIdentifierIssue()->getChildIssues();
                } else {
                    $this->_setupSearchObject();

                    return $this->_search_object->getIssues();
                }
            }
        }

        public function getBoard()
        {
            return $this->_board;
        }

        public function setBoard(AgileBoard $board)
        {
            $this->_board = $board;
        }

        protected function _setupSearchObject()
        {
            if ($this->_search_object === null) {
                $this->_search_object = new SavedSearch();
                $this->_search_object->setFilter('project_id', SearchFilter::createFilter('project_id', ['o' => '=', 'v' => $this->getBoard()->getProject()->getID()]));
                $this->_search_object->setFilter('milestone', SearchFilter::createFilter('milestone', ['o' => '=', 'v' => $this->getMilestone()->getID()]));
                $this->_search_object->setFilter('state', SearchFilter::createFilter('state', ['o' => '=', 'v' => [Issue::STATE_CLOSED, Issue::STATE_OPEN]]));
                $this->_search_object->setFilter('issuetype', SearchFilter::createFilter('issuetype', ['o' => '!=', 'v' => $this->getBoard()->getEpicIssuetypeID()]));
                if ($this->getBoard()->usesSwimlanes() && $this->getBoard()->getSwimlaneType() == AgileBoard::SWIMLANES_ISSUES) {
                    $values = [];
                    foreach ($this->getBoard()->getMilestoneSwimlanes($this->getMilestone()) as $swimlane) {
                        if ($swimlane->getIdentifier() == $this->getIdentifier()) continue;
                        $values[] = $swimlane->getIdentifierIssue()->getID();
                        foreach ($swimlane->getIssues() as $issue) $values[] = $issue->getID();
                    }
                    $this->_search_object->setFilter('id', SearchFilter::createFilter('id', ['o' => '!=', 'v' => $values]));
                } else {
                    if ($this->getBoard()->usesSwimlanes()) {
                        $values = [];
                        foreach ($this->_identifiables as $identifiable) $values[] = ($identifiable instanceof Identifiable) ? $identifiable->getID() : $identifiable;
                        $this->_search_object->setFilter($this->getBoard()->getSwimlaneIdentifier(), SearchFilter::createFilter($this->getBoard()->getSwimlaneIdentifier(), ['o' => '=', 'v' => $values]));
                    }
                }
                $this->_search_object->setIssuesPerPage(0);
                $this->_search_object->setOffset(0);
                $this->_search_object->setSortFields([Issues::MILESTONE_ORDER => QueryColumnSort::SORT_ASC]);
                $this->_search_object->setGroupby(null);
            }
        }

        /**
         * @return Milestone
         */
        public function getMilestone()
        {
            return $this->_milestone;
        }

        public function setMilestone(Milestone $milestone)
        {
            $this->_milestone = $milestone;
        }

        public function getIdentifier()
        {
            if ($this->_identifier === null) {
                $identifiers = [];
                foreach ($this->_identifiables as $identifiable) {
                    $identifiers[] = ($identifiable instanceof Identifiable) ? $identifiable->getId() : $identifiable;
                }
                $this->_identifier = 'swimlane_' . join('_', $identifiers);
            }

            return $this->_identifier;
        }

        /**
         * @return Issue
         */
        public function getIdentifierIssue()
        {
            return reset($this->_identifiables);
        }

    }
