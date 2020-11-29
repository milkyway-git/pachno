<?php

    namespace pachno\core\entities;

    use pachno\core\entities\common\IdentifiableScoped;
    use pachno\core\entities\tables\ArticleFiles;
    use pachno\core\framework\Context;
    use pachno\core\framework\Event;
    use pachno\core\framework\Settings;

    /**
     * @Table(name="\pachno\core\entities\tables\Files")
     */
    class File extends IdentifiableScoped
    {

        const TYPE_PROJECT_ICON = 'project_icon';

        /**
         * @Column(type="string", length=200)
         */
        protected $_content_type;

        /**
         * @Column(type="integer", length=10)
         */
        protected $_uploaded_at;

        /**
         * @Column(type="string", length=200)
         */
        protected $_real_filename;

        /**
         * @Column(type="string", length=200, name="original_filename")
         */
        protected $_name;

        /**
         * @Column(type="blob")
         */
        protected $_content;

        /**
         * @Column(type="string", length=200)
         */
        protected $_description;

        /**
         * @Column(type="string", length=200)
         */
        protected $_size;

        /**
         * @Column(type="string", length=200)
         */
        protected $_type;

        /**
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\User")
         */
        protected $_uid;

        /**
         * The project
         *
         * @var Project
         * @Column(type="integer", length=10)
         * @Relates(class="\pachno\core\entities\Project")
         */
        protected $_project_id;

        public static function getByIssueID($issue_id)
        {
            return tables\IssueFiles::getTable()->getByIssueID($issue_id);
        }

        public static function countByIssueID($issue_id)
        {
            return tables\IssueFiles::getTable()->countByIssueID($issue_id);
        }

        public static function getByArticleID($article_id)
        {
            return ArticleFiles::getTable()->getByArticleID($article_id);
        }

        /**
         * Returns the parent project
         *
         * @return Project
         */
        public function getProject()
        {
            return $this->_b2dbLazyLoad('_project_id');
        }

        public function setProject($project)
        {
            $this->_project_id = $project;
        }

        public static function getMimeType($filename)
        {
            $content_type = null;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
                $content_type = finfo_file($finfo, $filename);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $content_type = mime_content_type($filename);
            }

            return $content_type;
        }

        public function getContentType()
        {
            return $this->_content_type;
        }

        public function setContentType($content_type)
        {
            $this->_content_type = $content_type;
        }

        public function isImage()
        {
            return in_array($this->_content_type, self::getImageContentTypes());
        }

        public static function getImageContentTypes()
        {
            return ['image/png', 'image/jpeg', 'image/jpg', 'image/bmp', 'image/gif'];
        }

        public function getUploadedBy()
        {
            return $this->_b2dbLazyLoad('_uid');
        }

        public function setUploadedBy($uploaded_by)
        {
            $this->_uid = $uploaded_by;
        }

        public function getUploadedAt()
        {
            return $this->_uploaded_at;
        }

        public function setUploadedAt($uploaded_at)
        {
            $this->_uploaded_at = $uploaded_at;
        }

        public function getOriginalFilename()
        {
            return $this->getName();
        }

        public function getName()
        {
            return $this->_name;
        }

        public function setName($name)
        {
            $this->_name = $name;
        }

        public function setOriginalFilename($original_filename)
        {
            $this->setName($original_filename);
        }

        public function getContent()
        {
            return $this->_content;
        }

        public function setContent($content)
        {
            $this->_content = $content;
        }

        public function getReadableFilesize()
        {
            $size = $this->getSize();
            if ($size > 1024 * 1024) {
                return round(($size * 100 / (1024 * 1024)) / 100, 2) . 'MB';
            } else {
                return round(($size * 100 / 1024) / 100, 2) . 'KB';
            }
        }

        public function getSize()
        {
            return $this->_size;
        }

        public function hasDescription()
        {
            return (bool)($this->getDescription() != '');
        }

        public function getDescription()
        {
            return $this->_description;
        }

        public function setDescription($description)
        {
            $this->_description = $description;
        }

        public function move($target_path)
        {
            if (Settings::getUploadStorage() == 'files') {
                rename($this->getFullpath(), Settings::getUploadsLocalpath() . $target_path);
            }
            $this->setRealFilename($target_path);
            $this->save();
        }

        public function hasAccess()
        {
            $issue_ids = tables\IssueFiles::getTable()->getIssuesByFileID($this->getID());

            foreach ($issue_ids as $issue_id) {
                $issue = new Issue($issue_id);
                if (!$issue->hasAccess())
                    return false;
            }

            if ($this->getProject() instanceof Project) {
                return $this->getProject()->hasAccess();
            }

            return false;
//            $event = Event::createNew('core', 'pachno\core\entities\File::hasAccess', $this);
//            $event->setReturnValue(false);
//            $event->triggerUntilProcessed();
//
//            return $event->getReturnValue();
        }

        protected function _preDelete()
        {
            if ($this->doesFileExistOnDisk()) {
                unlink($this->getFullpath());
            }
        }

        public function doesFileExistOnDisk()
        {
            return file_exists($this->getFullpath());
        }

        public function getFullpath()
        {
            return Settings::getUploadsLocalpath() . $this->getRealFilename();
        }

        public function getRealFilename()
        {
            return $this->_real_filename;
        }

        public function setRealFilename($real_filename)
        {
            $this->_real_filename = $real_filename;
        }

        /**
         * @return mixed
         */
        public function getType()
        {
            return $this->_type;
        }

        /**
         * @param mixed $type
         */
        public function setType($type)
        {
            $this->_type = $type;
        }

        protected function _preSave($is_new)
        {
            parent::_preSave($is_new);
            if ($is_new) {
                $this->_uploaded_at = NOW;
            }
            if ($this->doesFileExistOnDisk()) {
                $this->_size = filesize($this->getFullpath());
            }
        }

        public function getURL($relative = true)
        {
            return Context::getRouting()->generate('showfile', ['id' => $this->getID()], $relative);
        }

        public function toJSON($detailed = true)
        {
            return [
                'id' => $this->getID(),
                'url' => $this->getUrl(true)
            ];
        }

    }
