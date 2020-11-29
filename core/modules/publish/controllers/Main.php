<?php

    namespace pachno\core\modules\publish\controllers;

    use Exception;
    use pachno\core\entities\Article;
    use pachno\core\entities\Comment;
    use pachno\core\entities\Project;
    use pachno\core\entities\tables\Articles;
    use pachno\core\entities\User;
    use pachno\core\framework;
    use pachno\core\framework\Request;

    /**
     * actions for the publish module
     *
     * @property Article $article
     * @property Project $selected_project
     *
     * @Routes(name_prefix="publish_")
     */
    class Main extends framework\Action
    {

        /**
         * Pre-execute function
         *
         * @param Request $request
         */
        public function preExecute(Request $request, $action)
        {
            $this->article = null;
            $article_name = $request['article_name'];
            $article_id = $request['article_id'];

            try {
                if ($project_key = $request['project_key']) {
                    $this->selected_project = Project::getByKey($project_key);
                }
            } catch (Exception $e) {
            }

            if ($this->selected_project instanceof Project) {
                if (!$this->selected_project->hasAccess()) {
                    $this->forward403();
                } else {
                    framework\Context::setCurrentProject($this->selected_project);
                }
            }

            $this->article = Articles::getTable()->selectById($article_id);
//            }
//            elseif ($this->article_name)
//            {
//                $this->article = Articles::getTable()->getArticleByName($this->article_name);
//            }

            if (!$this->article instanceof Article) {
                $this->article = new Article();
                $this->article->setProject($this->selected_project);
                $this->article->setName($article_name);
                if ($request->hasParameter('parent_article_id')) {
                    $this->article->setParentArticle(Articles::getTable()->selectById($request['parent_article_id']));
                }
            }
        }

        public function runSpecialArticle(Request $request)
        {
            $this->component = null;
            if (framework\ActionComponent::doesComponentExist("publish/special{$this->article_name}", false)) {
                $this->component = $this->article_name;
                $this->projectnamespace = ($this->selected_project instanceof Project) ? ucfirst($this->selected_project->getKey()) . ':' : '';
            }
        }

        /**
         * Show an article
         *
         * @Route(name="project_redirect_articles", url="/:project_key/r/docs")
         * @param Request $request
         */
        public function runProjectRedirectArticles(Request $request)
        {
            $this->redirect('redirectarticles');
        }

        /**
         * Show an article
         *
         * @Route(name="redirect_articles", url="/r/docs")
         * @param Request $request
         */
        public function runRedirectArticles(Request $request)
        {
        }

        /**
         * Show an article
         *
         * @Route(name="project_article", url="/:project_key/docs/:article_id/:article_name")
         * @param Request $request
         */
        public function runProjectArticle(Request $request)
        {
            return $this->redirect('showarticle');
        }

        /**
         * Show an article
         *
         * @Route(name="article", url="/docs/:article_id/:article_name")
         * @param Request $request
         */
        public function runShowArticle(Request $request)
        {
            if ($this->special)
                $this->redirect('specialArticle');

            $this->message = framework\Context::getMessageAndClear('publish_article_message');
            $this->error = framework\Context::getMessageAndClear('publish_article_error');
            $this->redirected_from = framework\Context::getMessageAndClear('publish_redirected_article');
            $this->comment_count = 0;

            if ($this->redirected_from) {
                $this->redirected_from = Articles::getTable()->selectById($this->redirected_from);
            }

            if ($this->article instanceof Article) {
                if (!$this->article->hasAccess()) {
                    $this->error = framework\Context::getI18n()->__("You don't have access to read this article");
//                    $this->article = null;
                } else {
                    $this->getUser()->markNotificationsRead('article', $this->article->getID());

                    if (!$request->hasParameter('no_redirect') && $this->article->isRedirect()) {
                        $redirect_article = $this->article->getRedirectArticle();
                        if ($redirect_article instanceof Article) {
                            framework\Context::setMessage('publish_redirected_article', $this->article->getID());
                            $this->forward($redirect_article->getLink());
                        }
                    }
                    try {
                        if ($request->hasParameter('revision')) {
                            $this->revision = $request['revision'];
                            $this->article->setRevision($this->revision);
                        }
                    } catch (Exception $e) {
                        $this->error = framework\Context::getI18n()->__('There was an error trying to show this revision');
                    }
                    $this->comment_count = Comment::countComments($this->article->getID(), Comment::TYPE_ARTICLE);
                }
            }
        }

        public function runArticleAttachments(Request $request)
        {

        }

        public function runArticlePermissions(Request $request)
        {
            if ($this->article instanceof Article) {
                $this->forward403unless($this->article->canEdit());
                $namespaces = $this->article->getCombinedNamespaces();
                $namespaces[] = $this->article->getName();
                array_unshift($namespaces, 0);
                $this->namespaces = array_reverse($namespaces);
            }
        }

        /**
         * @param Request $request
         * @Route(name="article_history", url="/docs/:article_id/:article_name/history")
         */
        public function runArticleHistory(Request $request)
        {
            $this->history_action = $request['history_action'];
            if ($this->article instanceof Article) {
                $this->history = $this->article->getHistory();
                $this->revision_count = count($this->history);

                switch ($this->history_action) {
                    case 'list':
                        break;
                    case 'diff':
                        $from_revision = $request['from_revision'];
                        $to_revision = $request['to_revision'];

                        if (!$from_revision || !$to_revision) {
                            $this->error = framework\Context::getI18n()->__('Please specify a from- and to-revision to compare');
                        } else {
                            list ($content, $diff) = $this->article->compareRevisions($from_revision, $to_revision);

                            $this->from_revision = $from_revision;
                            $this->from_revision_author = $content[$from_revision]['author'];
                            $this->from_revision_date = $content[$from_revision]['date'];
                            $this->to_revision = $to_revision;
                            $this->to_revision_author = $content[$to_revision]['author'];
                            $this->to_revision_date = $content[$to_revision]['date'];

                            $this->diff = explode("\n", $diff);
                        }
                        break;
                    case 'revert':
                        $article_name = $this->article->getName();
                        if (!framework\Context::getModule('publish')->canUserEditArticle($this)) {
                            framework\Context::setMessage('publish_article_error', framework\Context::getI18n()->__('You do not have permission to edit this article'));
                            $this->forward(framework\Context::getRouting()->generate('publish_article_history', ['article_name' => $article_name]));
                        }
                        $revision = $request['revision'];
                        if ($revision) {
                            $this->article->restoreRevision($revision);
                            $this->forward(framework\Context::getRouting()->generate('publish_article_history', ['article_name' => $article_name]));
                        } else {
                            $this->forward(framework\Context::getRouting()->generate('publish_article_history', ['article_name' => $this->article->getName()]));
                        }
                }
            }
        }

        /**
         * Delete an article
         *
         * @Route(name="article_delete", url="/docs/:article_id/delete")
         * @param Request $request
         */
        public function runDeleteArticle(Request $request)
        {
            try {
                if (!$this->article instanceof Article) {
                    throw new Exception($this->getI18n()->__('This article does not exist'));
                }
                if (!framework\Context::getModule('publish')->canUserDeleteArticle($this->article)) {
                    throw new Exception($this->getI18n()->__('You do not have permission to delete this article'));
                }
                if (!$request['article_name']) {
                    throw new Exception($this->getI18n()->__('Please specify an article name'));
                } else {
                    Article::deleteByName($request['article_name']);
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['title' => $this->getI18n()->__('An error occured'), 'error' => $e->getMessage()]);
            }

            return $this->renderJSON(['message' => $this->getI18n()->__('The article was deleted')]);
        }

        /**
         * Get avilable parent articles for an article
         * @Route(name="article_parents", url="/docs-api/:article_id/getparents")
         *
         * @param Request $request
         */
        public function runGetAvailableParents(Request $request)
        {
            $articles = Articles::getTable()->findArticles($this->article, $request['find_article'], $this->article->getProject());

            $parent_articles = [];
            foreach ($articles as $article) {
                $parent_articles[$article->getID()] = $article;
            }
            $article_counts = Articles::getTable()->getArticleParentCounts(array_keys($parent_articles));
            usort($parent_articles, function ($a, $b) use ($article_counts) {
                if ($a->isCategory() || $b->isCategory()) {
                    return ($a->isCategory() > $b->isCategory()) ? -1 : 1;
                }

                if (!isset($article_counts[$a->getID()]) && isset($article_counts[$b->getID()])) {
                    return 1;
                }

                if (!isset($article_counts[$b->getID()])) {
                    return -1;
                }

                return ($article_counts[$a->getID()] < $article_counts[$b->getID()]) ? -1 : 1;
            });
            $article = $this->article;

            return $this->renderJSON(['list' => $this->getComponentHTML('publish/getavailableparents', compact('parent_articles', 'article_counts', 'article'))]);
        }

        /**
         * Show an article
         *
         * @Route(name="project_article_edit", url="/:project_key/docs/:article_id")
         * @param Request $request
         */
        public function runProjectEditArticle(Request $request)
        {
            return $this->redirect('editarticle');
        }

        /**
         * Show an article
         *
         * @Route(name="article_edit", url="/docs/:article_id")
         * @param Request $request
         */
        public function runEditArticle(Request $request)
        {
            if (!$this->article) {
                $this->article = new Article();
                if ($this->selected_project instanceof Project) {
                    $this->article->setProject($this->selected_project);
                }
            }
            if (!$this->article->canEdit()) {
                framework\Context::setMessage('publish_article_error', framework\Context::getI18n()->__('You do not have permission to edit this article'));
                $this->forward($this->article->getLink());
            }

            if ($request['convert']) {
                $this->article->setContentSyntax(framework\Settings::SYNTAX_EDITOR_JS);
                $json = ['time' => $this->article->getLastUpdatedDate() * 1000, 'blocks' => [
                    ['type' => 'paragraph', 'data' => ['text' => $this->article->getContent()]]
                ], 'version' => '2.17.0'];
                $this->article->setContent(json_encode($json));
                $this->convert = true;
            }

            if ($request->isPost()) {
                $this->preview = (bool) $request['preview'];
                $this->change_reason = $request['change_reason'];
                try {
                    if ($request['article_name'] && $this->article->getName() !== 'Main Page') {
                        $this->article->setName($request['article_name']);
                        $this->article->setParentArticle($request['parent_article_id']);
                    }
                    $this->article->setContentSyntax($request['article_content_syntax']);
                    $this->article->setContent($request->getRawParameter('article_content'));

                    if (!trim($this->article->getName()))
                        throw new Exception(framework\Context::getI18n()->__('You need to specify a valid article name'));

                    if (!$this->preview && framework\Context::getModule('publish')->getSetting('require_change_reason') == 1 && (!$this->change_reason || trim($this->change_reason) == ''))
                        throw new Exception(framework\Context::getI18n()->__('You have to provide a reason for the changes'));

                    if ($this->article->getID() && $this->article->getLastUpdatedDate() != $request['last_modified'])
                        throw new Exception(framework\Context::getI18n()->__('The file has been modified since you last opened it'));

                    if (!$this->preview) {
                        $this->article->doSave([], $request['change_reason']);
                        framework\Context::setMessage('publish_article_message', framework\Context::getI18n()->__('The article was saved'));
                        return $this->renderJSON(['forward' => $this->article->getLink()]);
                    }
                } catch (Exception $e) {
                    $this->getResponse()->setHttpStatus(400);
                    return $this->renderJSON(['error' => $e->getMessage()]);
                }
            }
        }

        /**
         * Show an article
         *
         * @Route(name="api_article_menu", url="/docs-api/:article_id/menu/:selected_article_id")
         * @param Request $request
         */
        public function runGetSidebarMenu(Request $request)
        {
            $main_article = $this->article;
            $selected_article = Articles::getTable()->selectById($request['selected_article_id']);

            $menu = $this->getComponentHTML('publish/manualsidebarlinkchildren', [
                'main_article' => $main_article,
                'article' => $selected_article,
                'is_selected' => false,
                'is_parent' => false,
                'parents' => [],
                'loaded' => true,
                'has_children' => $main_article->hasChildren(),
                'children' => $main_article->getChildren()
            ]);

            return $this->renderJSON(['menu' => $menu]);
        }

        public function runFindArticles(Request $request)
        {
            $this->articlename = $request['articlename'];

            if ($this->articlename) {
                list ($this->resultcount, $this->articles) = Article::findArticlesByContentAndProject($this->articlename, framework\Context::getCurrentProject(), 10);
            }
        }

        /**
         * Toggle favourite article (starring)
         *
         * @Route(name="toggle_favourite_article", url="/docs/:article_id/:user_id")
         * @param Request $request
         */
        public function runToggleFavouriteArticle(Request $request)
        {
            // Read request parameters.
            $article_id = $request['article_id'];
            $user_id = $request['user_id'];

            // Validate request parameters.
            if ($article_id === null) {
                return $this->return400(framework\Context::getI18n()->__('Article ID not specified'));
            }

            if ($user_id === null) {
                return $this->return400(framework\Context::getI18n()->__('User ID not specified'));
            }

            // Retrieve article and user from database, making sure they exist.
            $article = Articles::getTable()->selectById($article_id);
            $user = User::getB2DBTable()->selectById($user_id);

            if (!$article instanceof Article || !$user instanceof User) {
                // Try not to reveal any additional information to caller about existence of user/article.
                $this->forward403();
            }

            // Grab current user (user sending the request).
            $current_user = framework\Context::getUser();

            // Check permissions.
            if ($user->getID() !== $current_user->getID() || !$article->hasAccess()) {
                // Try not to reveal any additional information to caller about existence of user/article.
                $this->forward403();
            }

            if ($user->isArticleStarred($article_id)) {
                $retval = !$user->removeStarredArticle($article_id);
            } else {
                $retval = $user->addStarredArticle($article_id);
                if ($user->getID() != $this->getUser()->getID()) {
                    framework\Event::createNew('core', 'article_subscribe_user', $article, compact('user'))->trigger();
                }
            }

            return $this->renderText(json_encode(['starred' => $retval, 'subscriber' => $this->getComponentHTML('publish/articlesubscriber', ['user' => $user, 'article' => $article])]));
        }

        protected function _getArticleNameDetails($article_name)
        {
            $namespaces = explode(':', $article_name);
            $namespace = array_shift($namespaces);

            if (strtolower($namespace) == 'special') {
                $this->special = true;
                $namespace = null;
                if (count($namespaces) > 1) {
                    $namespace = array_shift($namespaces);
                }
                $article_name = mb_strtolower(array_shift($namespaces));
            } elseif ($namespace == 'Category') {
                $namespace = array_shift($namespaces);
            }

            if (!is_null($namespace)) {
                $key = mb_strtolower($namespace);
                $this->selected_project = Project::getByKey($key);
            }

            return $article_name;
        }

    }
