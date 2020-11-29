<?php

    namespace pachno\core\entities;

    use b2db\Row;
    use EditorJS\EditorJS;
    use Exception;
    use pachno\core\entities\common\Identifiable;
    use pachno\core\entities\common\IdentifiableScoped;
    use pachno\core\entities\tables\ArticleCategoryLinks;
    use pachno\core\entities\tables\ArticleFiles;
    use pachno\core\entities\tables\ArticleHistory;
    use pachno\core\entities\tables\ArticleLinks;
    use pachno\core\entities\tables\Articles;
    use pachno\core\entities\tables\UserArticles;
    use pachno\core\entities\tables\Users;
    use pachno\core\framework;
    use pachno\core\framework\Event;
    use pachno\core\framework\Settings;
    use pachno\core\helpers\Attachable;
    use pachno\core\helpers\ContentParser;
    use pachno\core\helpers\TextDiff;
    use pachno\core\helpers\TextParser;
    use pachno\core\helpers\TextParserEditorJS;
    use pachno\core\helpers\TextParserMarkdown;
    use pachno\core\modules\publish\Publish;

    /**
     * @Table(name="\pachno\core\entities\tables\Articles")
     */
    class Article extends IdentifiableScoped implements Attachable
    {

        const TYPE_WIKI = 1;

        const TYPE_MANUAL = 2;

        /**
         * The article author
         *
         * @var User
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_author = null;

        /**
         * @Column(type="string", length=200)
         */
        protected $_name;

        /**
         * @Column(type="string", length=200)
         */
        protected $_manual_name;

        /**
         * @Column(type="string", length=200, default="")
         */
        protected $_redirect_slug = '';

        /**
         * When the article was posted
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_date = null;

        /**
         * What type of article this is
         *
         * @var integer
         * @Column(type="integer", length=10, default=2)
         */
        protected $_article_type = self::TYPE_MANUAL;

        /**
         * The old article content, used for history when saving
         *
         * @var string
         */
        protected $_old_content = null;

        /**
         * The article content
         *
         * @var string
         * @Column(type="text")
         */
        protected $_content = '';

        /**
         * The article content syntax
         *
         * @var integer
         * @Column(type="integer", length=3, default=1)
         */
        protected $_content_syntax = Settings::SYNTAX_EDITOR_JS;

        /**
         * Whether the article is published or not
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_is_published = false;

        /**
         * The parent article, if this article has one
         *
         * @var Article
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Article")
         */
        protected $_parent_article_id = 0;

        /**
         * Child article, if this article has any
         *
         * @var array|Article
         * @Relates(class="\pachno\core\entities\Article", collection=true, foreign_column="parent_article_id", orderby="name")
         */
        protected $_child_articles = null;

        /**
         * Array of users that are subscribed to this article
         *
         * @var array
         * @Relates(class="\pachno\core\entities\User", collection=true, manytomany=true, joinclass="\pachno\core\entities\tables\UserArticles")
         */
        protected $_subscribers = null;

        /**
         * Related project
         *
         * @var Project
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Project")
         */
        protected $_project_id;

        /**
         * Whether or not this page is a category page
         *
         * @Column(type="boolean", default=false)
         * @var boolean
         */
        protected $_is_category = false;

        /**
         * A list of articles that links to this article
         *
         * @var array
         */
        protected $_linking_articles = null;

        /**
         * A list of categories this article is in
         *
         * @var ArticleCategoryLink[]
         */
        protected $_categories = null;

        /**
         * Array of files attached to this article
         *
         * @var array
         */
        protected $_files = null;

        /**
         * A list of subcategories for this category
         *
         * @var array
         */
        protected $_subcategories = null;

        /**
         * A list of page in this category
         *
         * @var array
         */
        protected $_category_articles = null;

        protected $_history = null;

        protected $_category_name = null;

        protected $_namespaces = null;

        /**
         * The article this slug redirects to
         *
         * @var Article
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Article")
         */
        protected $_redirect_article_id = 0;

        protected $_new_subscribers = [];

        /**
         * @var Article[]
         */
        protected $_children = null;

        protected $_has_children = null;

        protected $_parser = null;

        public static function findArticlesByContentAndProject($content, $project, $limit = 5, $offset = 0)
        {
            [$resultcount, $articles] = Articles::getTable()->findArticlesContaining($content, $project, $limit, $offset);

            if ($resultcount) {
                foreach ($articles as $key => $article) {
                    if (!$article->hasAccess()) {
                        unset($articles[$key]);
                        $resultcount--;
                    }
                }
            }

            return [$resultcount, $articles];
        }

        public static function getByName($article_name)
        {
            return Articles::getTable()->getArticleByName($article_name);
        }

        public static function doesArticleExist($article_name)
        {
            return Articles::getTable()->doesArticleExist($article_name);
        }

        public static function deleteByName($article_name)
        {
            Articles::getTable()->deleteArticleByName($article_name);
            ArticleLinks::getTable()->deleteLinksByArticle($article_name);
        }

        public static function createNew($name, $content, $scope = null, $options = [], $project = null)
        {
            $user_id = (framework\Context::getUser() instanceof User) ? framework\Context::getUser()->getID() : 0;

            $article = new Article();
            $article->setName($name);
            $article->setContent($content);
            if ($project !== null) {
                $article->setProject($project->getID());
            }

            if (!isset($options['noauthor']))
                $article->setAuthor($user_id);
            else
                $article->setAuthor(0);

            if ($scope !== null)
                $article->setScope($scope);

            $article->doSave($options);

            return $article->getID();
        }

        public static function sortArticleChildren ($a, $b)
        {
            /**
             * @var Article $a
             * @var Article $b
             */
            if ($a->isCategory() != $b->isCategory()) {
                return ($a->isCategory() > $b->isCategory()) ? -1 : 1;
            }

            if (!$a->isCategory() && !$b->isCategory()) {
                $a_has_children = $a->hasChildren();
                $b_has_children = $b->hasChildren();
                if ($a_has_children != $b_has_children) {
                    return ($a_has_children > $b_has_children) ? -1 : 1;
                }
            }

            return strnatcmp($a->getName(), $b->getName());
        }

        public function setProject($project_id)
        {
            $this->_project_id = $project_id;
        }

        public function doSave($options = [], $reason = null)
        {
            $user_id = (framework\Context::getUser() instanceof User) ? framework\Context::getUser()->getID() : 0;

            if (!isset($options['revert']) || !$options['revert']) {
                $revision = ArticleHistory::getTable()->addArticleHistory($this->_name, $this->_old_content, $this->_content, $user_id, $reason);
            } else {
                $revision = null;
            }

//            ArticleLinks::getTable()->deleteLinksByArticle($this->_name);
//            ArticleCategoryLinks::getTable()->deleteCategoriesByArticle($this->_name);

            $this->save();

            $this->_old_content = $this->_content;

            $links = $this->_retrieveLinksFromContent($options);

            foreach ($links as $link => $occurrences) {
                $linked_article = Publish::getArticleLink($link, $this->getProject());
                if ($linked_article instanceof Article) {
                    $article_link = new ArticleLink();
                    $article_link->setArticle($this);
                    $article_link->setLinkedArticle($linked_article);
                    $article_link->save();
                }
            }

            $this->_history = null;

            Event::createNew('core', 'pachno\core\entities\Article::doSave', $this, compact('reason', 'revision', 'user_id'))->trigger();

            return true;
        }

        public function getArticleType()
        {
            return $this->_article_type;
        }

        public function setArticleType($article_type)
        {
            $this->_article_type = $article_type;
        }

        public function getContent()
        {
            return $this->_content;
        }

        public function setContent($content)
        {
            $this->_content = str_replace("\r\n", "\n", $content);
            if ($this->_content_syntax == Settings::SYNTAX_MW) {
                $parser = new TextParser($content);
                $parser->doParse();
            }
        }

        protected function _retrieveLinksFromContent($options = [])
        {
            $parser = new TextParser($this->_content);
            $options['no_code_highlighting'] = true;
            $parser->doParse($options);

            return $parser->getInternalLinks();
        }

        public function isCategory()
        {
            return $this->_is_category;
        }

        /**
         * Article constructor
         *
         * @param Row $row
         */
        public function _construct(Row $row, $foreign_key = null)
        {
            $this->_content = str_replace("\r\n", "\n", $this->_content);
            $this->_old_content = $this->_content;
        }

        public function __toString()
        {
            return $this->_content;
        }

        public function hasContent()
        {
            return (trim($this->_content) != '') ? true : false;
        }

        public function getParsedContent($options = [])
        {
            return $this->_parseContent($options);
        }

        protected function _parseContent($options = [])
        {
            if (!isset($options['article'])) {
                $options['article'] = $this;
            }

//            if (!$this->_content) {
//                return '';
//            }

            switch ($this->_content_syntax) {
                case Settings::SYNTAX_EDITOR_JS:
                    $parser = new TextParserEditorJS($this->_content, $options);
                    $text = $parser->getContent();
                    break;
                case Settings::SYNTAX_MD:
                    $parser = new TextParserMarkdown();
                    $text = $parser->transform($this->_content);
                    break;
                case Settings::SYNTAX_PT:
                    $options = ['plain' => true];
                case Settings::SYNTAX_MW:
                default:
                    $parser = new TextParser($this->_content, true, $this->getID());
                    foreach ($options as $option => $value) {
                        $parser->setOption($option, $value);
                    }
                    $text = $parser->getParsedText();
                    break;
            }

            if (isset($parser)) {
                $this->_parser = $parser;
            }

            return $text;
        }

        public function getTableOfContents()
        {
            $parser = $this->_getParser();
            $toc = [];
            if ($parser instanceof TextParser || $this->getContentSyntax() == Settings::SYNTAX_EDITOR_JS) {
                $toc = $parser->getTableOfContents();
            }

            return $toc;
        }

        public function getContentSyntax()
        {
            return $this->_content_syntax;
        }

        public function setContentSyntax($syntax)
        {
            if (!is_numeric($syntax))
                $syntax = Settings::getSyntaxValue($syntax);

            $this->_content_syntax = $syntax;
        }

        public function getTitle()
        {
            return $this->getName();
        }

        public function getLastUpdatedDate()
        {
            return $this->getPostedDate();
        }

        public function getPostedDate()
        {
            return $this->_date;
        }

        public function getLinkingArticles()
        {
            $this->_populateLinkingArticles();

            return $this->_linking_articles;
        }

        protected function _populateLinkingArticles()
        {
            if ($this->_linking_articles === null) {
                $this->_linking_articles = Articles::getTable()->getAllByLinksToArticleName($this->_name);
                foreach ($this->_linking_articles as $k => $article)
                    if (!$article->hasAccess())
                        unset($this->_linking_articles[$k]);
            }
        }

        /**
         * @return Article[]
         */
        public function getSubCategories()
        {
            $this->_populateSubCategories();

            return $this->_subcategories;
        }

        protected function _populateSubCategories()
        {
            if ($this->_subcategories === null) {
                $this->_subcategories = Articles::getTable()->getArticlesByParentId($this->getID(), true);
            }
        }

        public function getCategoryName()
        {
            if ($this->_category_name === null) {
                $this->_category_name = mb_substr($this->_name, mb_strpos($this->_name, ':') + 1);
            }

            return $this->_category_name;
        }

        /**
         * @return ArticleCategoryLink[]
         */
        public function getCategoryArticles(): array
        {
            $this->_populateCategoryArticles();

            return $this->_category_articles;
        }

        protected function _populateCategoryArticles()
        {
            if ($this->_category_articles === null) {
                $this->_category_articles = ArticleCategoryLinks::getTable()->getArticlesByCategoryId($this->getID());
            }
        }

        public function getNumberOfArticlesInCategory()
        {
            if ($this->_category_articles !== null) {
                return count($this->_category_articles);
            }

            return ArticleCategoryLinks::getTable()->countArticlesByCategoryId($this->getID());
        }

        /**
         * @return ArticleCategoryLink[]
         */
        public function getCategories()
        {
            $this->_populateCategories();

            return $this->_categories;
        }

        protected function _populateCategories()
        {
            if ($this->_categories === null) {
                $this->_categories = ArticleCategoryLinks::getTable()->getCategoriesByArticleId($this->getID());
            }
        }

        public function setIsCategory($is_category = true)
        {
            $this->_is_category = $is_category;
        }

        public function getSpacedName()
        {
            return preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $this->getName());
        }

        public function isRedirect()
        {
            return (bool) $this->_redirect_article_id;
        }

        /**
         * @return Project
         */
        public function getProject()
        {
            return $this->_b2dbLazyLoad('_project_id');
        }

        /**
         * Compare to revisions of this article, and return the diff output, as well as revision information
         *
         * @param integer $from_revision
         * @param integer $to_revision
         *
         * @return array
         */
        public function compareRevisions($from_revision, $to_revision)
        {
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $from_revision, $to_revision);
            $old_content = htmlspecialchars($content[$from_revision]['new_content']);
            $new_content = htmlspecialchars($content[$to_revision]['new_content']);

            $diff = new TextDiff();
            $result = $diff->stringDiff($old_content, $new_content);
            $changes = $diff->sequentialChanges($result);

            return [$content, $diff->renderDiff($result)];
        }

        public function restoreRevision($revision)
        {
            ArticleHistory::getTable()->removeArticleRevisionsSince($this->getName(), $revision);
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $revision);
            $this->setContent($content['new_content']);
            $this->doSave(['revert' => true]);
        }

        public function setRevision($revision = null)
        {
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $revision);
            if (array_key_exists('new_content', $content)) {
                $this->setContent($content['new_content']);
                $this->_date = $content['date'];
                $this->_author = $content['author'];
            } else {
                throw new Exception('No such revision');
            }
        }

        public function getCombinedNamespaces()
        {
            $namespaces = $this->getNamespaces();
            if (count($namespaces) > 1) {
                $composite_ns = '';
                $return_array = [];
                foreach ($namespaces as $namespace) {
                    $composite_ns .= ($composite_ns != '') ? ":{$namespace}" : $namespace;
                    $return_array[] = $composite_ns;
                }

                return $return_array;
            } else {
                return $namespaces;
            }
        }

        public function getNamespaces()
        {
            if ($this->_namespaces === null) {
                $this->_namespaces = [];
                $namespaces = explode(':', $this->getName());
                if (count($namespaces)) {
                    array_pop($namespaces);
                    $this->_namespaces = $namespaces;
                }
            }

            return $this->_namespaces;
        }

        /**
         * Return an array with all files attached to this issue
         *
         * @return array
         */
        public function getNumberOfFiles()
        {
            return count($this->getFiles());
        }

        /**
         * Return an array with all files attached to this issue
         *
         * @return array
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
                $this->_files = File::getByArticleID($this->getID());
            }
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
         * Attach a file to the issue
         *
         * @param File $file The file to attach
         */
        public function attachFile(File $file, $file_comment = '', $file_description = '')
        {
            ArticleFiles::getTable()->addByArticleIDandFileID($this->getID(), $file->getID());
            if ($this->_files !== null) {
                $this->_files[$file->getID()] = $file;
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
            ArticleFiles::getTable()->removeByArticleIDandFileID($this->getID(), $file->getID());
            if (is_array($this->_files) && array_key_exists($file->getID(), $this->_files)) {
                unset($this->_files[$file->getID()]);
            }
            $file->delete();
        }

        public function canDelete()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0) {
                $key = $namespaces[0];
                $project = Project::getByKey($key);
                if ($project instanceof Project) {
                    if ($project->isArchived())
                        return false;
                }
            }

            return framework\Context::getModule('publish')->canUserDeleteArticle($this);
        }

        public function canEdit()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0) {
                $key = $namespaces[0];
                $project = Project::getByKey($key);
                if ($project instanceof Project) {
                    if ($project->isArchived())
                        return false;
                }
            }

            return framework\Context::getModule('publish')->canUserEditArticle($this);
        }

        public function getProjectFromName()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0) {
                $key = $namespaces[0];
                $project = Project::getByKey(strtolower($key));

                return $project;
            }

            return null;
        }

        public function hasAccess()
        {
            $project = $this->getProject();

            if ($project instanceof Project && $project->isArchived())
                return false;

            return $this->canRead();
        }

        public function canRead()
        {
            return framework\Context::getModule('publish')->canUserReadArticle($this);
        }

        /**
         * Return the items name
         *
         * @return string
         */
        public function getManualName()
        {
            return $this->_manual_name;
        }

        /**
         * Set the edition name
         *
         * @param string $name
         */
        public function setManualName($name)
        {
            $this->_manual_name = $name;
        }

        public function setParentArticle($parent_article)
        {
            $this->_parent_article_id = $parent_article;
        }

        public function getParentArticleName()
        {
            $article = $this->getParentArticle();

            return ($article instanceof Article) ? $article->getName() : null;
        }

        /**
         * Return the parent article (if any)
         *
         * @return Article
         */
        public function getParentArticle()
        {
            return $this->_b2dbLazyLoad('_parent_article_id');
        }

        public function setRedirectArticle($redirect_article_id)
        {
            $this->_redirect_article_id = $redirect_article_id;
        }

        public function getRedirectArticleName()
        {
            $article = $this->getRedirectArticle();

            return ($article instanceof self) ? $article->getName() : null;
        }

        /**
         * Return the redirect article (if any)
         *
         * @return Article
         */
        public function getRedirectArticle()
        {
            return $this->_b2dbLazyLoad('_redirect_article_id');
        }

        protected function _populateChildArticles()
        {
            if ($this->_child_articles === null) {
                $this->_child_articles = Articles::getTable()->getArticlesByParentId($this->getID(), false);
            }
        }

        public function getChildArticles()
        {
            $this->_populateChildArticles();

            return $this->_child_articles;
        }

        public function hasChildren()
        {
            if ($this->_has_children !== null) {
                return $this->_has_children;
            }

            if ($this->_children !== null) {
                return (bool) count($this->_children);
            }

            $this->_has_children = (bool) Articles::getTable()->countArticlesByParentId($this->getID(), $this->isCategory());

            return $this->_has_children;
        }

        /**
         * @return Article[]
         */
        public function getChildren()
        {
            $this->_populateChildren();

            return $this->_children;
        }

        public function _populateChildren()
        {
            if ($this->_children === null) {
//                if ($this->isCategory()) {
//                    foreach ($this->getSubCategories() as $subCategory) {
//                        $this->_children[] = $subCategory;
//                    }
//                } else {
//                    foreach ($this->getChildArticles() as $childArticle) {
//                        $this->_children[] = $childArticle;
//                    }
//                }
                $this->_children = ($this->isCategory()) ? $this->getSubCategories() : $this->getChildArticles();

                usort($this->_children, 'self::sortArticleChildren');
            }
        }

        public function getHistoryUserIDs()
        {
            static $uids = null;
            if ($uids === null)
                $uids = ArticleHistory::getTable()->getUserIDsByArticleName($this->getName());

            return $uids;
        }

        public function getMentionedUsers()
        {
            $users = [];
            if ($this->hasMentions()) {
                foreach ($this->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }
            foreach (Comment::getComments($this->getID(), Comment::TYPE_ARTICLE) as $comment) {
                foreach ($comment->getMentions() as $user) {
                    $users[$user->getID()] = $user;
                }
            }

            return $users;
        }

        public function hasMentions()
        {
            $parser = $this->_getParser();
            return ($parser instanceof ContentParser) ? $parser->hasMentions() : false;
        }

        public function getMentions()
        {
            $parser = $this->_getParser();
            return ($parser instanceof ContentParser) ? $parser->getMentions() : [];
        }

        public function getLink($mode = 'show')
        {
            switch ($mode) {
                case 'show':
                    if ($this->getProject() instanceof Project) {
                        return framework\Context::getRouting()->generate('publish_project_article', ['project_key' => $this->getProject()->getKey(), 'article_id' => (int)$this->getId(), 'article_name' => $this->getName()]);
                    }

                    return framework\Context::getRouting()->generate('publish_article', ['article_id' => (int)$this->getId(), 'article_name' => $this->getName()]);

                case 'history':
                    return framework\Context::getRouting()->generate('publish_article_history', ['article_id' => (int)$this->getId(), 'article_name' => $this->getName()]);

                case 'edit':
                    if ($this->getProject() instanceof Project) {
                        return framework\Context::getRouting()->generate('publish_project_article_edit', ['project_key' => $this->getProject()->getKey(), 'article_id' => (int)$this->getId()]);
                    }

                    return framework\Context::getRouting()->generate('publish_article_edit', ['article_id' => (int)$this->getId()]);
            }

            return '';
        }

        protected function _preSave($is_new)
        {
            parent::_preSave($is_new);
            if (!framework\Context::isCLI()) {
                $this->_date = NOW;
                $this->_author = framework\Context::getUser();
            }
        }

        protected function _postDelete()
        {
//            ArticleLinks::getTable()->deleteLinksByArticle($this->getName());
            ArticleCategoryLinks::getTable()->deleteByArticleId($this->getID());
//            ArticleHistory::getTable()->deleteHistoryByArticle($this->getName());
            ArticleFiles::getTable()->deleteFilesByArticleID($this->getID());
        }

        /**
         * Return the article name
         *
         * @return string
         */
        public function getName()
        {
            return $this->_name;
        }

        /**
         * Set the article name
         *
         * @param string $name
         */
        public function setName($name)
        {
//            $this->_name = preg_replace('/[^\p{L}\p{N} :]/u', '', $name);
            $this->_name = $name;
        }

        protected function _postSave($is_new)
        {
            if ($is_new) {
                if ($this->hasMentions()) {
                    foreach ($this->getMentions() as $user) {
                        if ($user->getID() == framework\Context::getUser()->getID()) continue;

                        if (($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_MENTIONED, false)->isOn())) $this->_addNotificationIfNotNotified(Notification::TYPE_ARTICLE_MENTIONED, $user, $this->getAuthor());
                    }
                }
                if ($this->getAuthor() instanceof User) {
                    $this->_addCreateNotifications($this->getAuthor());
                }
            } else {
                $history = $this->getHistory();
                $history_item = array_shift($history);

                if ($history_item !== null) $this->_addUpdateNotifications($history_item['author']);
            }

            if (framework\Context::getUser() instanceof User && framework\Context::getUser()->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_CREATED_UPDATED_COMMENTED_ARTICLES, false)->isOn() && !$this->isSubscriber(framework\Context::getUser())) {
                $this->addSubscriber(framework\Context::getUser()->getID());
            }
        }

        /**
         * Returns the associated parser object
         *
         * @return ContentParser
         */
        protected function _getParser(): ?ContentParser
        {
            if (!isset($this->_parser)) {
                $this->_parseContent();
            }

            return $this->_parser;
        }

        protected function _addNotificationIfNotNotified($type, $user, $updated_by)
        {
            if (!$this->shouldUserBeNotified($user, $updated_by)) return;

            $this->_addNotification($type, $user, $updated_by);
        }

        public function shouldUserBeNotified($user, $updated_by)
        {
            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_UPDATED_SELF, false)->isOff() && $user->getID() === $updated_by->getID()) return false;

            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE, false)->isOff()) return true;

            if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE . '_article_' . $this->getID(), false)->isOff()) {
                $user->setNotificationSetting(Settings::SETTINGS_USER_NOTIFY_ITEM_ONCE . '_article_' . $this->getID(), true);

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

        protected function _addCreateNotifications($updated_by)
        {
            foreach ($this->getRelatedUsers() as $user) {
                if ($this->shouldAutomaticallySubscribeUser($user)) $this->addSubscriber($user->getID());

                if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_NEW_ARTICLES_MY_PROJECTS, false)->isOn()) {
                    $this->_addNotificationIfNotNotified(Notification::TYPE_ARTICLE_CREATED, $user, $updated_by);
                }
            }
        }

        /**
         * Returns an array with everyone related to this project
         *
         * @return array|User
         */
        public function getRelatedUsers()
        {
            $uids = [];
            $teams = [];

            // Add the author
            $uids[$this->getAuthorID()] = $this->getAuthorID();

            if ($this->getProject() instanceof Project) {
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
            }

            foreach ($teams as $team) {
                foreach ($team->getMembers() as $user) {
                    $uids[$user->getID()] = $user->getID();
                }
            }

            if (framework\Context::getUser() and isset($uids[framework\Context::getUser()->getID()])) unset($uids[framework\Context::getUser()->getID()]);
            $users = Users::getTable()->getByUserIDs($uids);

            return $users;
        }

        /**
         * Return the author id
         *
         * @return integer
         */
        public function getAuthorID()
        {
            $author = $this->getAuthor();

            return ($author instanceof Identifiable) ? $author->getID() : null;
        }

        /**
         * Returns the author
         *
         * @return User
         */
        public function getAuthor()
        {
            return $this->_b2dbLazyLoad('_author');
        }

        public function setAuthor($author)
        {
            if (is_object($author)) {
                $author = $author->getID();
            }
            $this->_author = $author;
        }

        public function shouldAutomaticallySubscribeUser($user)
        {
            if ($this->isSubscriber($user)) return false;

            if (!$user instanceof User || $user->getNotificationSetting(Settings::SETTINGS_USER_SUBSCRIBE_NEW_ARTICLES_MY_PROJECTS, null)->getValue() != 1) return false;

            return true;
        }

        public function isSubscriber($user)
        {
            if (!$user instanceof User) return false;

            $user_id = (string)$user->getID();
            $subscribers = (array)$this->getSubscribers();
            $new_subscribers = (array)$this->_new_subscribers;

            return (bool)in_array($user_id, $new_subscribers) || (bool)array_key_exists($user_id, $subscribers);
        }

        public function getSubscribers()
        {
            $this->_b2dbLazyLoad('_subscribers');

            return $this->_subscribers;
        }

        public function addSubscriber($user_id)
        {
            UserArticles::getTable()->addStarredArticle($user_id, $this->getID());
            $this->_new_subscribers[] = $user_id;
        }

        public function getHistory()
        {
            $this->_populateHistory();

            return $this->_history;
        }

        protected function _populateHistory()
        {
            if ($this->_history === null) {
                $this->_history = [];
                $history = ArticleHistory::getTable()->getHistoryByArticleName($this->getName());

                if ($history) {
                    while ($row = $history->getNextRow()) {
                        $author = ($row->get(ArticleHistory::AUTHOR)) ? new User($row->get(ArticleHistory::AUTHOR)) : null;
                        $this->_history[$row->get(ArticleHistory::REVISION)] = ['old_content' => $row->get(ArticleHistory::OLD_CONTENT), 'new_content' => $row->get(ArticleHistory::NEW_CONTENT), 'change_reason' => $row->get(ArticleHistory::REASON), 'updated' => $row->get(ArticleHistory::DATE), 'author' => $author];
                    }
                }
            }
        }

        protected function _addUpdateNotifications($updated_by)
        {
            if (!$updated_by instanceof User) return;

            foreach ($this->getSubscribers() as $user) {
                if ($user->getNotificationSetting(Settings::SETTINGS_USER_NOTIFY_SUBSCRIBED_ARTICLES, false)->isOn() && $this->isSubscriber($user)) {
                    $this->_addNotificationIfNotNotified(Notification::TYPE_ARTICLE_UPDATED, $user, $updated_by);
                }
            }
        }

        /**
         * @return string
         */
        public function getRedirectSlug()
        {
            return $this->_redirect_slug;
        }

        /**
         * @param string $redirect_slug
         */
        public function setRedirectSlug($redirect_slug)
        {
            $this->_redirect_slug = $redirect_slug;
        }

        public function getParentsArray()
        {
            $parents = [];
            $article = $this;

            do {
                $parent = $article->getParentArticle();
                if ($parent instanceof self) {
                    $parents[$parent->getId()] = $parent->getId();
                    $article = $parent;
                }
            } while ($parent instanceof self);

            foreach ($article->getCategories() as $articleCategoryLink) {
                $category_id = $articleCategoryLink->getCategory()->getID();
                $parents[$category_id] = $category_id;
            }

            return $parents;
        }

        public function isMainPage()
        {
            $name = str_replace(' ', '', mb_strtolower(trim($this->getName())));
            return $name == 'mainpage';
        }

    }
