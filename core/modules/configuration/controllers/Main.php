<?php

    namespace pachno\core\modules\configuration\controllers;

    use Exception;
    use InvalidArgumentException;
    use pachno\core\entities;
    use pachno\core\entities\CustomDatatype;
    use pachno\core\entities\tables;
    use pachno\core\entities\tables\Builds;
    use pachno\core\entities\tables\Clients;
    use pachno\core\entities\tables\Components;
    use pachno\core\entities\tables\Editions;
    use pachno\core\entities\tables\ListTypes;
    use pachno\core\entities\tables\Milestones;
    use pachno\core\framework;
    use pachno\core\helpers\Pagination;

    /**
     * Main controller for settings
     *
     * @Routes(name_prefix="configure_", url_prefix="/configure")
     * @package pachno\core\modules\configuration\controllers
     */
    class Main extends framework\Action
    {

        public function getAuthenticationMethodForAction($action)
        {
            $value = (framework\Settings::isElevatedLoginRequired()) ? framework\Action::AUTHENTICATION_METHOD_ELEVATED : framework\Action::AUTHENTICATION_METHOD_CORE;
            $event = framework\Event::createNew('core', 'pachno\core\modules\configuration\controllers\Main\getAuthenticationMethodForAction', $action);
            $event->setReturnValue($value);
            $event->trigger();

            return $event->getReturnValue();
        }

        /**
         * Pre-execute function
         *
         * @param framework\Request $request
         * @param string $action
         */
        public function preExecute(framework\Request $request, $action)
        {
            if (!$request->hasParameter('section'))
                return;

            // forward 403 if you're not allowed here
            if ($request->isAjaxCall() == false) // for avoiding empty error when an user disables himself its own permissions
            {
                $this->forward403unless(framework\Context::getUser()->canAccessConfigurationPage($request['section']));
            }

            $this->access_level = $this->getAccessLevel($request['section'], 'core');

            if (!$request->isAjaxCall()) {
                $this->getResponse()->setPage('config');
                framework\Context::loadLibrary('ui');
            }
        }

        public function getAccessLevel($section, $module)
        {
            return framework\Settings::getAccessLevel($section, $module);
        }

        /**
         * Configuration main page
         *
         * @param framework\Request $request
         */
        public function runIndex(framework\Request $request)
        {
            $this->config_sections = framework\Settings::getConfigSections(framework\Context::getI18n());
            $this->outdated_modules = framework\Context::getOutdatedModules();
        }

        /**
         * check for updates
         *
         * @param framework\Request $request
         */
        public function runCheckUpdates(framework\Request $request)
        {
            $latest_version = framework\Context::getLatestAvailableVersionInformation();

            if ($latest_version === null) {
                $this->getResponse()->setHttpStatus(400);
                $uptodate = null;
                $title = framework\Context::getI18n()->__('Failed to check for updates');
                $message = framework\Context::getI18n()->__('The response from Pachno website was invalid');
            } else {
                $update_available = framework\Context::isUpdateAvailable($latest_version);

                if ($update_available) {
                    $uptodate = false;
                    $title = framework\Context::getI18n()->__('Pachno is out of date');
                    $message = framework\Context::getI18n()->__('The latest version is %ver. Update now from pachno.com.', ['%ver' => $latest_version->nicever]);
                } else {
                    $uptodate = true;
                    $title = framework\Context::getI18n()->__('Pachno is up to date');
                    $message = framework\Context::getI18n()->__('The latest version is %ver', ['%ver' => $latest_version->nicever]);
                }
            }

            return $this->renderJSON([
                'uptodate' => $uptodate,
                'title' => $title,
                'message' => $message
            ]);
        }

        /**
         * Configure general and server settings
         *
         * @Route(name="settings", url="/settings")
         * @Parameters(config_module="core", section=12)
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureSettings(framework\Request $request)
        {
            if (framework\Context::getRequest()->isPost()) {
                $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);

                $settings = [framework\Settings::SETTING_USER_DISPLAYNAME_FORMAT, framework\Settings::SETTING_ENABLE_GRAVATARS, framework\Settings::SETTING_IS_SINGLE_PROJECT_TRACKER,
                    framework\Settings::SETTING_REQUIRE_LOGIN, framework\Settings::SETTING_ALLOW_REGISTRATION, framework\Settings::SETTING_USER_GROUP,
                    framework\Settings::SETTING_RETURN_FROM_LOGIN, framework\Settings::SETTING_RETURN_FROM_LOGOUT,
                    framework\Settings::SETTING_REGISTRATION_DOMAIN_WHITELIST, framework\Settings::SETTING_SHOW_PROJECTS_OVERVIEW, framework\Settings::SETTING_KEEP_COMMENT_TRAIL_CLEAN,
                    framework\Settings::SETTING_SITE_NAME, framework\Settings::SETTING_SITE_NAME_HTML, framework\Settings::SETTING_DEFAULT_CHARSET, framework\Settings::SETTING_DEFAULT_LANGUAGE,
                    framework\Settings::SETTING_SERVER_TIMEZONE, framework\Settings::SETTING_HEADER_LINK,
                    framework\Settings::SETTING_MAINTENANCE_MESSAGE, framework\Settings::SETTING_MAINTENANCE_MODE, framework\Settings::SETTING_ELEVATED_LOGIN_DISABLED,
                    framework\Settings::SETTING_NOTIFICATION_POLL_INTERVAL];

                foreach ($settings as $setting) {
                    if (framework\Context::getRequest()->getParameter($setting) !== null) {
                        $value = framework\Context::getRequest()->getParameter($setting);
                        switch ($setting) {
                            case framework\Settings::SETTING_SITE_NAME:
                                $value = framework\Context::getRequest()->getParameter($setting, null, false);
                                break;
                            case framework\Settings::SETTING_DEFAULT_CHARSET:
                                framework\Context::loadLibrary('common');
                                if ($value && !pachno_check_syntax($value, "CHARSET")) {
                                    $this->getResponse()->setHttpStatus(400);

                                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid setting for charset')]);
                                }
                                break;
                            case framework\Settings::SETTING_NOTIFICATION_POLL_INTERVAL:
                                if (!ctype_digit($value)) {
                                    $this->getResponse()->setHttpStatus(400);

                                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid setting for notification poll interval')]);
                                }
                        }
                        framework\Settings::saveSetting($setting, $value);
                    }
                }

                return $this->renderJSON(['title' => framework\Context::getI18n()->__('All settings saved')]);
            }
        }

        /**
         * Configure projects
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureProjects(framework\Request $request)
        {
            $this->active_projects = entities\Project::getAllRootProjects(false);
            $this->archived_projects = entities\Project::getAllRootProjects(true);
        }

        /**
         * Configure issue fields
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureIssuefields(framework\Request $request)
        {
            $this->builtin_types = $this->getBuiltInIssueFields();
            $this->custom_types = CustomDatatype::getAll();
        }

        protected function getBuiltInIssueFields()
        {
            $i18n = framework\Context::getI18n();
            $builtin_types = [];
            $builtin_types[entities\Datatype::STATUS] = ['description' => $i18n->__('Status types'), 'key' => entities\Datatype::STATUS];
            $builtin_types[entities\Datatype::RESOLUTION] = ['description' => $i18n->__('Resolution types'), 'key' => entities\Datatype::RESOLUTION];
            $builtin_types[entities\Datatype::PRIORITY] = ['description' => $i18n->__('Priority levels'), 'key' => entities\Datatype::PRIORITY];
            $builtin_types[entities\Datatype::SEVERITY] = ['description' => $i18n->__('Severity levels'), 'key' => entities\Datatype::SEVERITY];
            $builtin_types[entities\Datatype::CATEGORY] = ['description' => $i18n->__('Categories'), 'key' => entities\Datatype::CATEGORY];
            $builtin_types[entities\Datatype::REPRODUCABILITY] = ['description' => $i18n->__('Reproducability'), 'key' => entities\Datatype::REPRODUCABILITY];
            $builtin_types[entities\Datatype::ACTIVITYTYPE] = ['description' => $i18n->__('Activity types'), 'key' => entities\Datatype::ACTIVITYTYPE];

            return $builtin_types;
        }

        /**
         * Configure issue fields
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureIssuetypes(framework\Request $request)
        {
            $this->issue_types = entities\Issuetype::getAll();
        }

        /**
         * Configure issue fields
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureIssuetypeSchemes(framework\Request $request)
        {
            if (tables\IssuetypeSchemes::getTable()->getNumberOfSchemesInCurrentScope() == 1) {
                $scheme_id = tables\IssuetypeSchemes::getTable()->getNumberOfSchemesInCurrentScope();
                $this->forward($this->getRouting()->generate('configure_issuetypes_scheme', ['scheme_id' => $scheme_id]));
            } else {
                $this->schemes = entities\IssuetypeScheme::getAll();
            }
        }

        /**
         * Configure issue fields
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureIssuetypeSchemePost(framework\Request $request)
        {
            $scheme_id = $request['scheme_id'];
            $scheme = ($scheme_id) ? tables\IssuetypeSchemes::getTable()->selectById($scheme_id) : new entities\IssuetypeScheme();

            if (!$scheme instanceof entities\IssuetypeScheme) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name for the issue type')]);
            }

            if ($request['name']) {
                $scheme->setName($request['name']);
            }

            $scheme->save();

            return $this->renderJSON(['message' => framework\Context::getI18n()->__('Issue type scheme saved')]);
        }

        /**
         * Configure issue fields
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureIssuetypeScheme(framework\Request $request)
        {
            $this->number_of_schemes = tables\IssuetypeSchemes::getTable()->getNumberOfSchemesInCurrentScope();
            $this->issue_types = entities\Issuetype::getAll();
            $this->icons = entities\Issuetype::getIcons();
            $this->scheme = entities\IssuetypeScheme::getB2DBTable()->selectById((int)$request['scheme_id']);

//                if ($this->mode == 'copy_scheme')
//                {
//                    if ($new_name = $request['new_name'])
//                    {
//                        $new_scheme = new entities\IssuetypeScheme();
//                        $new_scheme->setName($new_name);
//                        $new_scheme->save();
//                        foreach ($this->scheme->getIssuetypes() as $issuetype)
//                        {
//                            $new_scheme->setIssuetypeEnabled($issuetype);
//                            $new_scheme->setIssuetypeRedirectedAfterReporting($issuetype, $this->scheme->isIssuetypeRedirectedAfterReporting($issuetype));
//                            $new_scheme->setIssuetypeReportable($issuetype, $this->scheme->isIssuetypeReportable($issuetype));
//                        }
//                        tables\IssueFields::getTable()->copyBySchemeIDs($this->scheme->getID(), $new_scheme->getID());
//                        return $this->renderJSON(array('content' => $this->getComponentHTML('configuration/issuetypescheme', array('scheme' => $new_scheme))));
//                    }
//                    else
//                    {
//                        $this->error = framework\Context::getI18n()->__('Please enter a valid name');
//                    }
//                }
//                elseif ($this->mode == 'delete_scheme')
//                {
//                    $this->scheme->delete();
//                    return $this->renderJSON(array('success' => true, 'message' => framework\Context::getI18n()->__('The issuetype scheme was deleted')));
        }

        /**
         * Get issue type options for a specific issue type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuetypesGetOptionsForScheme(framework\Request $request)
        {
            $issue_type = tables\IssueTypes::getTable()->selectById($request['issue_type_id']);
            $scheme = tables\IssuetypeSchemes::getTable()->selectById($request['scheme_id']);

            return $this->renderJSON(['content' => $this->getComponentHTML('configuration/issuetypeschemeoptions', ['issue_type' => $issue_type, 'scheme' => $scheme])]);
        }

        /**
         * Get issue type field for a specific issue type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuetypesGetFieldForScheme(framework\Request $request)
        {
            $issue_type = tables\IssueTypes::getTable()->selectById($request['issue_type_id']);
            $scheme = tables\IssuetypeSchemes::getTable()->selectById($request['scheme_id']);

            $builtin_types = entities\DatatypeBase::getAvailableFields(true);
            $custom_types = CustomDatatype::getAll();
            $visible_fields = $scheme->getVisibleFieldsForIssuetype($issue_type);
            $key = $request['key'];
            $item = (in_array($key, $builtin_types)) ? $key : $custom_types[$key];

            $visible_fields[$key] = [
                'required' => false,
                'reportable' => false
            ];

            return $this->renderJSON(['content' => $this->getComponentHTML('configuration/issuetypeschemeoption', ['key' => $key, 'item' => $item, 'issue_type' => $issue_type, 'scheme' => $scheme, 'visible_fields' => $visible_fields, 'expanded' => true])]);
        }

        /**
         * Get issue type options for a specific issue type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuetypesSaveOptionsForScheme(framework\Request $request)
        {
            $issue_type = tables\IssueTypes::getTable()->selectById($request['issue_type_id']);
            $scheme = tables\IssuetypeSchemes::getTable()->selectById($request['scheme_id']);

            if (!$issue_type instanceof entities\Issuetype || !$scheme instanceof entities\IssuetypeScheme) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid issue type and scheme')]);
            }

            $scheme->clearAvailableFieldsForIssuetype($issue_type);
            foreach ($request->getParameter('field', []) as $key => $details) {
                $scheme->setFieldAvailableForIssuetype($issue_type, $key, $details);
            }

            return $this->renderJSON(['message' => framework\Context::getI18n()->__('Available choices updated')]);
        }

        /**
         * @Route(name="add_issuetype", url="/issuetypes", methods="POST")
         *
         * @param framework\Request $request
         */
        public function runAddIssuetype(framework\Request $request)
        {
            return $this->redirect('editissuetype');
        }

        /**
         * @Route(name="edit_issuetype", url="/issuetypes/:issuetype_id")
         *
         * @param framework\Request $request
         */
        public function runEditIssuetype(framework\Request $request)
        {
            $is_new = !$request->getParameter('issuetype_id');

            if ($is_new) {
                $issuetype = new entities\Issuetype();
            } else {
                $issuetype = entities\Issuetype::getB2DBTable()->selectById($request['issuetype_id']);
            }

            if ($request->hasParameter('icon') && !$request['icon']) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please choose an icon')]);
            }

            if ($request->hasParameter('name') && !trim($request['name'])) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please pick a name')]);
            }

            if ($request['icon']) {
                $issuetype->setIcon($request['icon']);
            }

            if ($request['name']) {
                $issuetype->setName(trim($request['name']));
            }

            if ($request['description']) {
                $issuetype->setDescription(trim($request['description']));
            }

            $issuetype->save();

            if ($request['scheme_id']) {
                $scheme = tables\IssuetypeSchemes::getTable()->selectById($request['scheme_id']);
                tables\IssuetypeSchemeLink::getTable()->associateIssuetypeWithScheme($issuetype->getID(), $scheme->getID());
                $scheme->setIssuetypeEnabled($issuetype);

                return $this->renderJSON([
                    'content' => $this->getComponentHTML('schemeissuetype', ['type' => $issuetype, 'scheme' => $scheme]),
                    'scheme' => $scheme->toJSON(),
                    'issue_type' => $issuetype->toJSON(),
                ]);
            }

            return $this->renderJSON([
                'content' => $this->getComponentHTML('issuetype', ['type' => $issuetype]),
                'issue_type' => $issuetype->toJSON(),
            ]);
        }

        /**
         * Perform an action on an issue type
         * @Route(name="toggle_issuetype_for_scheme", url="/issuetypes/toggle/:issue_type_id/:scheme_id")
         * @param framework\Request $request
         */
        public function runConfigureToggleIssuetypeForScheme(framework\Request $request)
        {
            if (($issuetype = entities\Issuetype::getB2DBTable()->selectById($request['issue_type_id'])) instanceof entities\Issuetype) {
                $this->scheme = entities\IssuetypeScheme::getB2DBTable()->selectById((int)$request['scheme_id']);
                if ($this->scheme instanceof entities\IssuetypeScheme) {
                    $new_value = !$this->scheme->isSchemeAssociatedWithIssuetype($issuetype);
                    $this->scheme->setIssuetypeEnabled($issuetype, $new_value);

                    return $this->renderJSON(['value' => $new_value]);
                }
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid action for this issue type / scheme')]);
        }

        /**
         * Perform an action on an issue type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuetype(framework\Request $request)
        {
            $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);
            switch ($request['mode']) {
                case 'edit':
                    if ($request['name']) {
                        $issuetype = new entities\Issuetype();
                        $issuetype->setName($request['name']);
                        $issuetype->setIcon($request['icon']);
                        $issuetype->save();

                        return $this->renderJSON(['title' => framework\Context::getI18n()->__('Issue type created'), 'content' => $this->getComponentHTML('issuetype', ['type' => $issuetype])]);
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name for the issue type')]);
                case 'update':
                    if (($issuetype = entities\Issuetype::getB2DBTable()->selectById($request['id'])) instanceof entities\Issuetype) {
                        if ($this->scheme instanceof entities\IssuetypeScheme) {
                            $this->scheme->setIssuetypeRedirectedAfterReporting($issuetype, $request['redirect_after_reporting']);
                            $this->scheme->setIssuetypeReportable($issuetype, $request['reportable']);

                            return $this->renderJSON(['title' => framework\Context::getI18n()->__('The issue type details were updated'), 'description' => $issuetype->getDescription(), 'name' => $issuetype->getName()]);
                        } elseif ($request['name']) {
                            $issuetype->setDescription($request['description']);
                            $issuetype->setName($request['name']);
                            $issuetype->setIcon($request['icon']);
                            $issuetype->save();

                            return $this->renderJSON(['title' => framework\Context::getI18n()->__('The issue type was updated'), 'description' => $issuetype->getDescription(), 'name' => $issuetype->getName()]);
                        } else {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name for the issue type')]);
                        }
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid issue type')]);
                case 'updatechoices':
                    if (($issuetype = entities\Issuetype::getB2DBTable()->selectById($request['id'])) instanceof entities\Issuetype) {
                        $this->scheme->clearAvailableFieldsForIssuetype($issuetype);
                        foreach ($request->getParameter('field', []) as $key => $details) {
                            $this->scheme->setFieldAvailableForIssuetype($issuetype, $key, $details);
                        }

                        return $this->renderJSON(['title' => framework\Context::getI18n()->__('Available choices updated')]);
                    } else {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid issue type')]);
                    }
                case 'delete':
                    if (($issuetype = entities\Issuetype::getB2DBTable()->selectById($request['id'])) instanceof entities\Issuetype) {
                        $issuetype->delete();

                        return $this->renderJSON(['message' => framework\Context::getI18n()->__('Issue type deleted')]);
                    } else {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid issue type')]);
                    }
                case 'toggletype':
                    if (($issuetype = entities\Issuetype::getB2DBTable()->selectById($request['id'])) instanceof entities\Issuetype) {
                        if ($this->scheme instanceof entities\IssuetypeScheme) {
                            $this->scheme->setIssuetypeEnabled($issuetype, !$this->scheme->isSchemeAssociatedWithIssuetype($issuetype));

                            return $this->renderJSON(['issuetype_id' => $issuetype->getID()]);
                        }
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid action for this issue type / scheme')]);
                default:
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid action for this issue type')]);
            }
        }

        /**
         * Get issue fields list for a specific field type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuefieldsGetOptions(framework\Request $request)
        {
            return $this->renderJSON([
                'content' => $this->getComponentHTML('editissuefield', ['type' => $request['type'], 'access_level' => $this->access_level])
            ]);
        }

        /**
         * Add or delete an issue field option
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuefieldsAction(framework\Request $request)
        {
            $i18n = framework\Context::getI18n();
            $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);
            $types = entities\Datatype::getTypes();

            switch ($request['mode']) {
                case 'saveorder':
                    $itemtype = $request['type'];
                    if (array_key_exists($itemtype, $types)) {
                        ListTypes::getTable()->saveOptionOrder($request[$itemtype . '_list'], $itemtype);
                    } else {
                        $customtype = CustomDatatype::getByKey($request['type']);
                        tables\CustomFieldOptions::getTable()->saveOptionOrder($request[$itemtype . '_list'], $customtype->getID());
                    }

                    return $this->renderJSON('ok');
                    break;
                case 'add':
                    if ($request['name']) {
                        if (array_key_exists($request['type'], $types)) {
                            $type = $types[$request['type']];
                            $item = new $type();
                            $item->setName($request['name']);
                            $item->setItemdata($request['itemdata']);
                            $item->save();
                        } else {
                            $type = CustomDatatype::getByKey($request['type']);
                            $item = $type->createNewOption($request['name'], $request['value'], $request['itemdata']);
                        }

                        return $this->renderJSON([
                            'title' => framework\Context::getI18n()->__('The option was added'),
                            'item' => $item->toJSON(),
                            'component' => $this->getComponentHTML('editissuefieldoption', ['item' => $item, 'type' => $type, 'access_level' => $this->access_level])
                        ]);
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name')]);
                case 'edit':
                    if ($request['name']) {
                        $type = $request['type'];
                        if (array_key_exists($type, $types)) {
                            $classname = $types[$type];
                            $item = $classname::getB2DBTable()->selectByID($request['id']);
                        } else {
                            $customtype = CustomDatatype::getByKey($type);
                            $item = entities\CustomDatatypeOption::getB2DBTable()->selectById($request['id']);
                        }

                        if ($item instanceof entities\DatatypeBase) {
                            $item->setName($request['name']);
                            $item->setItemdata($request['itemdata']);
                            if ($item instanceof entities\CustomDatatypeOption) {
                                $item->setValue($request['value']);
                            }
                            $item->save();

                            return $this->renderJSON([
                                'title' => framework\Context::getI18n()->__('The option was updated'),
                                'item' => $item->toJSON(),
                                'component' => $this->getComponentHTML('editissuefieldoption', ['item' => $item, 'type' => $type, 'access_level' => $this->access_level])
                            ]);
                        } else {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid id')]);
                        }
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name')]);
                case 'delete':
                    if ($request->hasParameter('id')) {
                        if (array_key_exists($request['type'], $types)) {
                            $classname = $types[$request['type']];
                            $item = $classname::getB2DBTable()->rawDeleteById($request['id']);

                            return $this->renderJSON(['title' => $i18n->__('The option was deleted')]);
                        } else {
                            tables\CustomFieldOptions::getTable()->rawDeleteById($request['id']);

                            return $this->renderJSON(['title' => $i18n->__('The option was deleted')]);
                        }
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => $i18n->__('Invalid id or type')]);
                    break;
            }
        }

        /**
         * Add or delete a custom type
         *
         * @param framework\Request $request
         */
        public function runConfigureIssuefieldsCustomTypeAction(framework\Request $request)
        {
            switch ($request['mode']) {
                case 'add':
                    if ($request['name'] != '') {
                        try {
                            if (!$request['name']) {
                                throw new Exception($this->getI18n()->__('Please provide a name'));
                            }

                            if (!$request['type']) {
                                throw new Exception($this->getI18n()->__('You have to pick the type of field you are adding'));
                            }

                            $customtype = new CustomDatatype();
                            $customtype->setName($request['name']);
                            $customtype->setType($request['type']);
                            $customtype->save();

                            return $this->renderJSON([
                                'item' => $customtype->toJSON(),
                                'content' => $this->getComponentHTML('configuration/issuefield', ['type_key' => $customtype->getKey(), 'type' => $customtype])
                            ]);
                        } catch (Exception $e) {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['error' => $e->getMessage() /* framework\Context::getI18n()->__('You need to provide a unique custom field name (key already exists)') */]);
                        }
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name')]);
                    break;
                case 'update':
                    if ($request['name'] != '') {
                        $customtype = CustomDatatype::getByKey($request['type']);
                        if ($customtype instanceof CustomDatatype) {
                            // $customtype->setDescription($request['description']);
                            $customtype->setInstructions($request['instructions']);
                            $customtype->setName($request['name']);
                            $customtype->save();

                            return $this->renderJSON(['title' => framework\Context::getI18n()->__('The custom field was updated'), 'description' => $customtype->getDescription(), 'instructions' => $customtype->getInstructions(), 'name' => $customtype->getName()]);
                        }
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => framework\Context::getI18n()->__('You need to provide a custom field key that already exists')]);
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('Please provide a valid name')]);
                    break;
                case 'delete':
                    $customtype = CustomDatatype::getByKey($request['type']);
                    if ($customtype instanceof CustomDatatype) {
                        $customtype->delete();

                        return $this->renderJSON(['title' => framework\Context::getI18n()->__('The custom field was deleted')]);
                    }
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => framework\Context::getI18n()->__('You need to provide a custom field key that already exists')]);
                    break;
            }
        }

        /**
         * Configure modules
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureModules(framework\Request $request)
        {
            $this->module_message = framework\Context::getMessageAndClear('module_message');
            $this->module_error = framework\Context::getMessageAndClear('module_error');
            $this->modules = framework\Context::getAllModules();
            $this->writable = is_writable(PACHNO_MODULES_PATH);
            $this->uninstalled_modules = framework\Context::getUninstalledModules();
            $this->outdated_modules = framework\Context::getOutdatedModules();
            $this->is_default_scope = framework\Context::getScope()->isDefault();
        }

        /**
         * Add a project (AJAX call)
         *
         * @param framework\Request $request The request object
         */
        public function runAddProject(framework\Request $request)
        {
            $i18n = framework\Context::getI18n();

            if (!framework\Context::getScope()->hasProjectsAvailable()) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(["error" => $i18n->__("There are no more projects available in this instance")]);
            }
            if ($this->access_level == framework\Settings::ACCESS_FULL) {
                if (($p_name = $request['p_name']) && trim($p_name) != '') {
                    try {
                        $project = new entities\Project();
                        $project->setName($p_name);
                        $project->setWorkflowSchemeID($request['workflow_scheme_id']);
                        $project->setIssuetypeSchemeID($request['issuetype_scheme_id']);
                        $project->save();

                        return $this->renderJSON(['message' => $i18n->__('The project has been added'), 'content' => $this->getComponentHTML('projectbox', ['project' => $project, 'access_level' => $this->access_level]), 'total_count' => entities\Project::getProjectsCount(), 'more_available' => framework\Context::getScope()->hasProjectsAvailable()]);
                    } catch (InvalidArgumentException $e) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(["error" => $i18n->__('A project with the same key already exists')]);
                    } catch (Exception $e) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(["error" => $i18n->__('An error occurred: ' . $e->getMessage())]);
                    }
                }
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(["error" => $i18n->__('Please specify a valid project name')]);
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(["error" => $i18n->__("You don't have access to add projects")]);
        }

        /**
         * Get edit form for user
         */
        public function runGetUserEditForm(framework\Request $request)
        {
            return $this->renderJSON(["content" => $this->getComponentHTML('finduser_row_editable', ['user' => entities\User::getB2DBTable()->selectByID($request['user_id'])])]);
        }

        /**
         * Delete a project
         *
         * @param framework\Request $request The request object
         */
        public function runDeleteProject(framework\Request $request)
        {
            $i18n = framework\Context::getI18n();

            if ($this->access_level == framework\Settings::ACCESS_FULL) {
                try {
                    $theProject = entities\Project::getB2DBTable()->selectByID($request['project_id']);
                    $theProject->setDeleted();
                    $theProject->save();

                    return $this->renderJSON(['title' => $i18n->__('The project was deleted'), 'total_count' => entities\Project::getProjectsCount(), 'more_available' => framework\Context::getScope()->hasProjectsAvailable()]);
                } catch (Exception $e) {
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => $i18n->__('An error occured') . ': ' . $e->getMessage()]);
                }
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(["error" => $i18n->__("You don't have access to remove projects")]);
        }

        /**
         * Archive
         *
         * @param framework\Request $request The request object
         */
        public function runArchiveProject(framework\Request $request)
        {
            return $this->_setArchived(true, $request);
        }

        /**
         * Handle archive functiions
         *
         * @param bool $archived Status
         * @param framework\Request $request The request object
         */
        protected function _setArchived($archived, framework\Request $request)
        {
            $i18n = framework\Context::getI18n();

            if ($this->access_level == framework\Settings::ACCESS_FULL) {
                try {
                    $theProject = entities\Project::getB2DBTable()->selectByID($request['project_id']);
                    $theProject->setArchived($archived);
                    $theProject->save();

                    $projectbox = $this->getComponentHTML('projectbox', ['project' => $theProject, 'access_level' => $this->access_level]);

                    return $this->renderJSON(['message' => $i18n->__('Project successfully updated'), 'parent_id' => $theProject->getParentID(), 'box' => $projectbox]);
                } catch (Exception $e) {
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => $i18n->__('An error occured') . ': ' . $e->getMessage()]);
                }
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(["error" => $i18n->__("You don't have access to archive projects")]);
        }

        /**
         * Unarchive
         *
         * @param framework\Request $request The request object
         */
        public function runUnarchiveProject(framework\Request $request)
        {
            // Don't unarchive if we will have too many projects
            if (!framework\Context::getScope()->hasProjectsAvailable()) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(["error" => $this->getI18n()->__("There are no more projects available in this instance")]);
            }

            return $this->_setArchived(false, $request);
        }

        /**
         * Perform an action on a module
         *
         * @param framework\Request $request The request object
         */
        public function runModuleAction(framework\Request $request)
        {
            $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);

            try {
                if ($request['mode'] == 'install' && file_exists(PACHNO_MODULES_PATH . $request['module_key'] . DS . ucfirst($request['module_key']) . '.php')) {
                    if (entities\Module::installModule($request['module_key'])) {
                        framework\Context::setMessage('module_message', framework\Context::getI18n()->__('The module "%module_name" was installed successfully', ['%module_name' => $request['module_key']]));
                    } else {
                        framework\Context::setMessage('module_error', framework\Context::getI18n()->__('There was an error during the installation of the module "%module_name"', ['%module_name' => $request['module_key']]));
                    }
                } else {
                    $module = framework\Context::getModule($request['module_key']);
                    if (!$module->isCore())
                        switch ($request['mode']) {
                            case 'disable':
                                if ($module->getType() !== framework\interfaces\ModuleInterface::MODULE_AUTH):
                                    $module->disable();
                                endif;
                                break;
                            case 'enable':
                                if ($module->getType() !== framework\interfaces\ModuleInterface::MODULE_AUTH):
                                    $module->enable();
                                endif;
                                break;
                            case 'uninstall':
                                $module->uninstall();
                                framework\Context::setMessage('module_message', framework\Context::getI18n()->__('The module "%module_name" was uninstalled successfully', ['%module_name' => $module->getName()]));
                                break;
                            case 'update':
                                try {
                                    $module->upgrade();
                                    framework\Context::setMessage('module_message', framework\Context::getI18n()->__('The module "%module_name" was successfully upgraded and can now be used again', ['%module_name' => $module->getName()]));
                                } catch (Exception $e) {
                                    framework\Context::setMessage('module_error', framework\Context::getI18n()->__('The module "%module_name" was not successfully upgraded', ['%module_name' => $module->getName()]));
                                    throw $e;
                                }
                                break;
                        }
                }
            } catch (Exception $e) {
                framework\Logging::log('Trying to run action ' . $request['mode'] . ' on module ' . $request['module_key'] . ' made an exception: ' . $e->getMessage(), framework\Logging::LEVEL_FATAL);
                framework\Context::setMessage('module_error', framework\Context::getI18n()->__('This module (%module_name) does not exist', ['%module_name' => $request['module_key']]));
                throw $e;
            }
            $this->forward(framework\Context::getRouting()->generate('configure_modules'));
        }

        /**
         * Configure the selected theme
         *
         * @param framework\Request $request
         * @Route(name="configuration_themes", url="/configure/themes")
         * @Parameters(config_module="core", section=19)
         */
        public function runConfigureThemes(framework\Request $request)
        {
            $this->themes = framework\Context::getThemes();
            $this->writable = is_writable(PACHNO_PATH . 'themes');
            $this->writable_link = is_writable(PACHNO_PATH . PACHNO_PUBLIC_FOLDER_NAME . DS . 'themes');
            $this->theme_message = framework\Context::getMessageAndClear('theme_message');
            $this->theme_error = framework\Context::getMessageAndClear('theme_error');
            $this->is_default_scope = framework\Context::getScope()->isDefault();
        }

        /**
         * Perform the module update for a specific module
         *
         * @param framework\Request $request
         * @Route(name="configuration_module_update", url="/configure/modules/:module_key/update")
         */
        public function runUpdateModule(framework\Request $request)
        {
            if (framework\Context::getScope()->isDefault()) {
                $module = framework\Context::getModule($request['module_key']);
                $module->upgrade();
                $module->enable();
                framework\Context::setMessage('module_message', $this->getI18n()->__('The module was updated'));
            }
            $this->forward($this->getRouting()->generate('configure_modules'));
        }

        /**
         * Enable a theme
         *
         * @param framework\Request $request
         * @Route(name="configuration_enable_theme", url="/configure/themes/:theme_key/enable/:csrf_token")
         *
         * @CsrfProtected
         */
        public function runEnableTheme(framework\Request $request)
        {
            $themes = framework\Context::getThemes();
            if (array_key_exists($request['theme_key'], $themes)) {
//                if (framework\Context::getScope()->isDefault())
//                {
//                    $theme_link_path = PACHNO_PATH . PACHNO_PUBLIC_FOLDER_NAME . DS . 'css' . DS . $request['theme_key'];
//                    $theme_path = '..' . DS . '..' . DS . 'themes' . DS . $request['theme_key'] . DS . 'css';
//                    if (file_exists($theme_link_path)) {
//                        unlink($theme_link_path);
//                    }
//                    symlink($theme_path, $theme_link_path);
//                }
                framework\Settings::saveSetting(framework\Settings::SETTING_THEME_NAME, $request['theme_key']);
                framework\Context::setMessage('theme_message', $this->getI18n()->__('The theme has been enabled'));
            } else {
                framework\Context::setMessage('theme_error', $this->getI18n()->__('This theme does not exist'));
            }

            return $this->forward($this->getRouting()->generate('configuration_themes'));
        }

        /**
         * Download the update file for a specific theme
         *
         * @param framework\Request $request
         * @Route(name="configuration_download_theme_update", url="/configure/themes/:theme_key/update/download")
         */
        public function runDownloadThemeUpdate(framework\Request $request)
        {
            try {
                entities\Module::downloadTheme($request['theme_key']);
                framework\Context::setMessage('theme_message', $this->getI18n()->__('The theme was updated'));
                $url = $this->getRouting()->generate('configuration_themes');
            } catch (framework\exceptions\ModuleDownloadException $e) {
                $url = $this->getRouting()->generate('configuration_themes');
                switch ($e->getCode()) {
                    case framework\exceptions\ModuleDownloadException::JSON_NOT_FOUND:
                        framework\Context::setMessage('theme_error', $this->getI18n()->__('An error occured when trying to retrieve the theme update data'));
                        break;
                    case framework\exceptions\ModuleDownloadException::FILE_NOT_FOUND:
                        framework\Context::setMessage('theme_error', $this->getI18n()->__('The theme update could not be downloaded'));
                        break;
                }
            } catch (Exception $e) {
                framework\Context::setMessage('module_error', $this->getI18n()->__('An error occured when trying to retrieve the theme'));
                $url = $this->getRouting()->generate('configuration_themes');
            }

            return $this->forward($url);
        }

        /**
         * Download the update file for a specific module
         *
         * @param framework\Request $request
         * @Route(name="configuration_download_module_update", url="/configure/modules/:module_key/update/download")
         */
        public function runDownloadModuleUpdate(framework\Request $request)
        {
            try {
                entities\Module::downloadModule($request['module_key']);
                $url = $this->getRouting()->generate('configuration_module_update', ['module_key' => $request['module_key']]);
            } catch (framework\exceptions\ModuleDownloadException $e) {
                $url = $this->getRouting()->generate('configure_modules');
                switch ($e->getCode()) {
                    case framework\exceptions\ModuleDownloadException::JSON_NOT_FOUND:
                        framework\Context::setMessage('module_error', $this->getI18n()->__('An error occured when trying to retrieve the module data'));
                        break;
                    case framework\exceptions\ModuleDownloadException::FILE_NOT_FOUND:
                        framework\Context::setMessage('module_error', $this->getI18n()->__('The module could not be downloaded'));
                        break;
                }
            } catch (Exception $e) {
                framework\Context::setMessage('module_error', $this->getI18n()->__('An error occured when trying to retrieve the module'));
                $url = $this->getRouting()->generate('configure_modules');
            }

            return $this->forward($url);
        }

        /**
         * Get permissions info for a single permission key
         *
         * @param framework\Request $request
         */
        public function runGetPermissionsInfo(framework\Request $request)
        {
            $i18n = framework\Context::getI18n();

            if ($this->access_level == framework\Settings::ACCESS_FULL) {
                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/permissionsblock', ['base_id' => $request['base_id'], 'permissions_list' => $request['permissions_list'], 'mode' => $request['mode'], 'target_id' => $request['target_id'], 'user_id' => $request['user_id'], 'team_id' => $request['team_id'], 'module' => $request['target_module'], 'access_level' => $this->access_level])]);
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(["error" => $i18n->__("You don't have access to modify permissions")]);
        }

        public function runSetPermission(framework\Request $request)
        {
            $i18n = framework\Context::getI18n();

            if ($this->access_level == framework\Settings::ACCESS_FULL) {
                $uid = 0;
                $gid = 0;
                $tid = 0;
                switch ($request['target_type']) {
                    case 'user':
                        $uid = $request['item_id'];
                        break;
                    case 'group':
                        $gid = $request['item_id'];
                        break;
                    case 'team':
                        $tid = $request['item_id'];
                        break;
                }

                switch ($request['mode']) {
                    case 'allowed':
                        framework\Context::setPermission($request['key'], $request['target_id'], $request['target_module'], $uid, $gid, $tid, true);
                        break;
                    case 'denied':
                        framework\Context::setPermission($request['key'], $request['target_id'], $request['target_module'], $uid, $gid, $tid, false);
                        break;
                    case 'unset':
                        framework\Context::removePermission($request['key'], $request['target_id'], $request['target_module'], $uid, $gid, $tid, true, null, 0);
                        break;
                }

                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/permissionsinfoitem', ['key' => $request['key'], 'target_id' => $request['target_id'], 'type' => $request['target_type'], 'mode' => $request['template_mode'], 'item_id' => $request['item_id'], 'module' => $request['target_module'], 'access_level' => $this->access_level, 'in_json' => 1])]);
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(["error" => $i18n->__("You don't have access to modify permissions")]);
        }

        /**
         * Configure a module
         *
         * @param framework\Request $request The request object
         */
        public function runConfigureModule(framework\Request $request)
        {
            $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);

            try {
                $module = framework\Context::getModule($request['config_module']);
                if (!$module->isEnabled()) {
                    throw new Exception('disabled');
                } elseif (!$module->hasConfigSettings()) {
                    throw new Exception('module not configurable');
                } else {
                    if ($request->isPost() && $this->access_level == framework\Settings::ACCESS_FULL) {
                        try {
                            $module->postConfigSettings($request);
                            if (!framework\Context::hasMessage('module_message')) {
                                framework\Context::setMessage('module_message', framework\Context::getI18n()->__('Settings saved successfully'));
                            }
                        } catch (Exception $e) {
                            framework\Context::setMessage('module_error', $e->getMessage());
                        }
                        $this->forward(framework\Context::getRouting()->generate('configure_module', ['config_module' => $request['config_module']]));
                    }
                    $this->module = $module;
                }
            } catch (Exception $e) {
                framework\Logging::log('Trying to configure module ' . $request['config_module'] . " which isn't configurable", 'main', framework\Logging::LEVEL_FATAL);
                framework\Context::setMessage('module_error', framework\Context::getI18n()->__('The module "%module_name" is not configurable', ['%module_name' => $request['config_module']]));
                $this->forward(framework\Context::getRouting()->generate('configure_modules'));
            }
            $this->module_message = framework\Context::getMessageAndClear('module_message');
            $this->module_error = framework\Context::getMessageAndClear('module_error');
            $this->module_error_details = framework\Context::getMessageAndClear('module_error_details');
        }

        public function runConfigurePermissions(framework\Request $request)
        {
            $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);
        }

        public function runConfigureUploads(framework\Request $request)
        {
            $this->uploads_enabled = framework\Context::getScope()->isUploadsEnabled();
            if ($this->uploads_enabled && $request->isPost()) {
                $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);
                if ($request['enable_uploads']) {
                    if (framework\Context::getScope()->isDefault()) {
                        $settings = ['upload_restriction_mode', 'upload_extensions_list', 'upload_storage', 'upload_localpath'];

                        if ($request['upload_storage'] == 'files' && (bool)$request['enable_uploads']) {
                            if (!is_dir($request['upload_localpath'])) {
                                mkdir($request['upload_localpath'], 0744, true);
                            }
                            if (!is_writable($request['upload_localpath'])) {
                                $this->getResponse()->setHttpStatus(400);

                                return $this->renderJSON(['error' => framework\Context::getI18n()->__("The upload path isn't writable")]);
                            }
                        }
                    } else {
                        $settings = ['upload_restriction_mode', 'upload_extensions_list'];
                        framework\Settings::copyDefaultScopeSetting('upload_localpath');
                    }

//                    if (!is_numeric($request['upload_max_file_size'])) {
//                        $this->getResponse()->setHttpStatus(400);
//
//                        return $this->renderJSON(['error' => framework\Context::getI18n()->__("The maximum file size must be a number")]);
//                    }

                    foreach ($settings as $setting) {
                        if (framework\Context::getRequest()->hasParameter($setting)) {
                            framework\Settings::saveSetting($setting, framework\Context::getRequest()->getParameter($setting));
                        }
                    }
                }

                framework\Settings::saveSetting('upload_allow_image_caching', framework\Context::getRequest()->getParameter('upload_allow_image_caching'));
                framework\Settings::saveSetting('upload_delivery_use_xsend', framework\Context::getRequest()->getParameter('upload_delivery_use_xsend'));
                framework\Settings::saveSetting('enable_uploads', framework\Context::getRequest()->getParameter('enable_uploads'));

                return $this->renderJSON(['title' => framework\Context::getI18n()->__('All settings saved')]);
            }
        }

        public function runConfigureAuthentication(framework\Request $request)
        {
            $modules = [];
            $allmods = framework\Context::getModules();
            foreach ($allmods as $mod) {
                if ($mod->getType() == framework\interfaces\ModuleInterface::MODULE_AUTH) {
                    $modules[] = $mod;
                }
            }
            $this->modules = $modules;
        }

        public function runSaveAuthentication(framework\Request $request)
        {
            if (framework\Context::getRequest()->isPost()) {
                $this->forward403unless($this->access_level == framework\Settings::ACCESS_FULL);
                $settings = [framework\Settings::SETTING_AUTH_BACKEND, 'register_message', 'forgot_message', 'changepw_message', 'changedetails_message'];

                foreach ($settings as $setting) {
                    if (framework\Context::getRequest()->getParameter($setting) !== null) {
                        $value = framework\Context::getRequest()->getParameter($setting);
                        framework\Settings::saveSetting($setting, $value);
                    }
                }
            }
        }

        public function runConfigureUsers(framework\Request $request)
        {
            $this->finduser = $request['finduser'];
            $this->groups = entities\Group::getAll();
            $this->teams = entities\Team::getAll();
            $this->number_of_users = tables\UserScopes::getTable()->countUsers();
        }

        public function runConfigureTeams(framework\Request $request)
        {
            $this->teams = entities\Team::getAll();
        }

        public function runConfigureClients(framework\Request $request)
        {
            $this->clients = entities\Client::getAll();
        }

        public function runDeleteGroup(framework\Request $request)
        {
            try {
                if (in_array($request['group_id'], framework\Settings::getDefaultGroupIDs())) {
                    throw new Exception(framework\Context::getI18n()->__("You cannot delete the default groups"));
                }

                try {
                    $group = entities\Group::getB2DBTable()->selectById($request['group_id']);
                } catch (Exception $e) {

                }
                if (!$group instanceof entities\Group) {
                    throw new Exception(framework\Context::getI18n()->__("You cannot delete this group"));
                }
                if ($group->isDefaultUserGroup()) {
                    throw new Exception(framework\Context::getI18n()->__("You cannot delete the group for the default user"));
                }
                $group->delete();

                return $this->renderJSON(['success' => true, 'message' => framework\Context::getI18n()->__('The group was deleted')]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runAddGroup(framework\Request $request)
        {
            try {
                $mode = $request['mode'];
                if ($group_name = $request['group_name']) {
                    if ($mode == 'clone') {
                        try {
                            $old_group = entities\Group::getB2DBTable()->selectById($request['group_id']);
                        } catch (Exception $e) {

                        }
                        if (!$old_group instanceof entities\Group) {
                            throw new Exception(framework\Context::getI18n()->__("You cannot clone this group"));
                        }
                    }
                    if (entities\Group::doesGroupNameExist(trim($group_name))) {
                        throw new Exception(framework\Context::getI18n()->__("Please enter a group name that doesn't already exist"));
                    }
                    $group = new entities\Group();
                    $group->setName($group_name);
                    $group->save();
                    if ($mode == 'clone') {
                        if ($request['clone_permissions']) {
                            tables\Permissions::getTable()->cloneGroupPermissions($old_group->getID(), $group->getID());
                        }
                        $message = framework\Context::getI18n()->__('The group was cloned');
                    } else {
                        $message = framework\Context::getI18n()->__('The group was added');
                    }

                    return $this->renderJSON(['message' => $message, 'content' => $this->getComponentHTML('configuration/groupbox', ['group' => $group])]);
                } else {
                    throw new Exception(framework\Context::getI18n()->__('Please enter a group name'));
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runGetGroupMembers(framework\Request $request)
        {
            try {
                $group = entities\Group::getB2DBTable()->selectById((int)$request['group_id']);
                $users = $group->getMembers();

                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/groupuserlist', ['users' => $users])]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runDeleteUser(framework\Request $request)
        {
            try {
                try {
                    $return_options = [];
                    $user = entities\User::getB2DBTable()->selectByID($request['user_id']);
                    if ($user->getGroup() instanceof entities\Group) {
                        $return_options['update_groups'] = ['ids' => [], 'membercounts' => []];
                        $group_id = $user->getGroup()->getID();
                        $return_options['update_groups']['ids'][] = $group_id;
                        $return_options['update_groups']['membercounts'][$group_id] = $user->getGroup()->getNumberOfMembers();
                    }
                    if (count($user->getTeams())) {
                        $return_options['update_teams'] = ['ids' => [], 'membercounts' => []];
                        foreach ($user->getTeams() as $team) {
                            $team_id = $team->getID();
                            $return_options['update_teams']['ids'][] = $team_id;
                            $return_options['update_teams']['membercounts'][$team_id] = $team->getNumberOfMembers();
                        }
                    }
                    if (in_array($user->getID(), [1, framework\Settings::getDefaultUserID()])) {
                        throw new Exception(framework\Context::getI18n()->__("You cannot delete this system user"));
                    }
                } catch (Exception $e) {

                }
                if (!$user instanceof entities\User) {
                    throw new Exception(framework\Context::getI18n()->__("You cannot delete this user"));
                }
                if (framework\Context::getScope()->isDefault()) {
                    $user->markAsDeleted();
                    $user->save();
                    $return_options['message'] = framework\Context::getI18n()->__('The user was deleted');
                } else {
                    $user->removeScope(framework\Context::getScope()->getID());
                    $return_options['message'] = framework\Context::getI18n()->__('The user has been removed from this scope');
                }
                $return_options['total_count'] = entities\User::getUsersCount();
                $return_options['more_available'] = framework\Context::getScope()->hasUsersAvailable();

                return $this->renderJSON($return_options);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runDeleteTeam(framework\Request $request)
        {
            try {
                try {
                    $team = entities\Team::getB2DBTable()->selectById($request['team_id']);
                } catch (Exception $e) {

                }
                if (!$team instanceof entities\Team) {
                    throw new Exception(framework\Context::getI18n()->__("You cannot delete this team"));
                }
                $team->delete();

                return $this->renderJSON(['success' => true, 'message' => framework\Context::getI18n()->__('The team was deleted'), 'total_count' => entities\Team::countAll(), 'more_available' => framework\Context::getScope()->hasTeamsAvailable()]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runAddTeam(framework\Request $request)
        {
            try {
                $mode = $request['mode'];
                if ($team_name = $request['team_name']) {
                    if ($mode == 'clone') {
                        try {
                            $old_team = entities\Team::getB2DBTable()->selectById($request['team_id']);
                        } catch (Exception $e) {

                        }
                        if (!$old_team instanceof entities\Team) {
                            throw new Exception(framework\Context::getI18n()->__("You cannot clone this team"));
                        }
                    }
                    if (entities\Team::doesTeamNameExist(trim($team_name))) {
                        throw new Exception(framework\Context::getI18n()->__("Please enter a team name that doesn't already exist"));
                    }
                    $team = new entities\Team();
                    $team->setName($team_name);
                    $team->save();
                    if ($mode == 'clone') {
                        if ($request['clone_permissions']) {
                            tables\Permissions::getTable()->cloneTeamPermissions($old_team->getID(), $team->getID());
                        }
                        if ($request['clone_memberships']) {
                            tables\TeamMembers::getTable()->cloneTeamMemberships($old_team->getID(), $team->getID());
                        }
                        $message = framework\Context::getI18n()->__('The team was cloned');
                    } else {
                        $message = framework\Context::getI18n()->__('The team was added');
                    }

                    return $this->renderJSON(['message' => $message, 'content' => $this->getComponentHTML('configuration/teambox', ['team' => $team]), 'total_count' => entities\Team::countAll(), 'more_available' => framework\Context::getScope()->hasTeamsAvailable()]);
                } else {
                    throw new Exception(framework\Context::getI18n()->__('Please enter a team name'));
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runGetTeamMembers(framework\Request $request)
        {
            try {
                $team = entities\Team::getB2DBTable()->selectById((int)$request['team_id']);
                $users = $team->getMembers();

                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/teamuserlist', compact('users', 'team'))]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runRemoveTeamMember(framework\Request $request)
        {
            try {
                $team = entities\Team::getB2DBTable()->selectById((int)$request['team_id']);
                $user = entities\User::getB2DBTable()->selectByID((int)$request['user_id']);

                $team->removeMember($user);

                return $this->renderJSON(['update_teams' => ['ids' => [$team->getID()], 'membercounts' => [$team->getID() => $team->getNumberOfMembers()]]]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runAddTeamMember(framework\Request $request)
        {
            try {
                $user_id = (int)$request['user_id'];
                $team = entities\Team::getB2DBTable()->selectById((int)$request['team_id']);
                $user = entities\User::getB2DBTable()->selectByID($user_id);

                $team->addMember($user);

                return $this->renderJSON(['teamlistitem' => $this->getComponentHTML('configuration/teamuserlistitem', compact('team', 'user_id', 'user')), 'update_teams' => ['ids' => [$team->getID()], 'membercounts' => [$team->getID() => $team->getNumberOfMembers()]]]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runFindUsers(framework\Request $request)
        {
            $options = [
                'findstring' => $request['findstring']
            ];

            if (mb_strlen($options['findstring']) >= 1) {
                $options['users'] = tables\Users::getTable()->findInConfig($options['findstring']);
                $options['total_results'] = count($options['users']);
            } else {
                $options['too_short'] = true;
            }
            switch ($options['findstring']) {
                case 'unactivated':
                    $options['findstring'] = framework\Context::getI18n()->__('Unactivated users');
                    break;
                case 'newusers':
                    $options['findstring'] = framework\Context::getI18n()->__('New users');
                    break;
                case 'all':
                    $options['findstring'] = framework\Context::getI18n()->__('All users');
                    break;
            }

            return $this->renderJSON([
                'content' => $this->getComponentHTML('configuration/usersresult', $options)
            ]);
        }

        public function runAddUser(framework\Request $request)
        {
            try {
                if (!framework\Context::getScope()->hasUsersAvailable()) {
                    throw new Exception($this->getI18n()->__('This instance of Pachno cannot add more users'));
                }

                if ($username = trim($request['username'])) {
                    if (!entities\User::isUsernameAvailable($username)) {
                        if ($request->getParameter('mode') == 'import') {
                            $user = entities\User::getByUsername($username);
                            $user->addScope(framework\Context::getScope());

                            return $this->renderJSON(['imported' => true, 'message' => $this->getI18n()->__('The user was successfully added to this scope (pending user confirmation)')]);
                        } elseif (framework\Context::getScope()->isDefault()) {
                            throw new Exception($this->getI18n()->__('This username already exists'));
                        } else {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['allow_import' => true]);
                        }
                    }

                    $user = new entities\User();
                    $user->setUsername($username);
                    $user->setRealname($request->getParameter('realname', $username));
                    $user->setBuddyname($request->getParameter('buddyname', $username));
                    $user->setEmail($request->getParameter('email'));
                    $group_id = ($request->getParameter('group_id')) ? $request->getParameter('group_id') : framework\Settings::get(framework\Settings::SETTING_USER_GROUP);
                    $user->setGroup($group_id);
                    if ($request->hasParameter('password') && !(empty($request['password']) && empty($request['password_repeat']))) {
                        if (empty($request['password']) || $request['password'] != $request['password_repeat']) {
                            throw new Exception($this->getI18n()->__('Please enter the same password twice'));
                        }
                        $password = $request['password'];
                        $user->setPassword($password);
                    } else {
                        $password = entities\User::createPassword();
                        $user->setPassword($password);
                    }
                    $user->save();
                    foreach ((array)$request['teams'] as $team_id) {
                        $user->addToTeam(entities\Team::getB2DBTable()->selectById((int)$team_id));
                    }
                    framework\Event::createNew('core', 'config.createuser.save', $user, ['password' => $password])->trigger();
                } else {
                    throw new Exception($this->getI18n()->__('Please enter a username'));
                }
                $this->getResponse()->setTemplate('configuration/findusers');
                $this->too_short = false;
                $this->created_user = true;
                $this->users = [$user];
                $this->total_results = 1;
                $this->title = $this->getI18n()->__('User %username created', ['%username' => $username]);
                $this->total_count = entities\User::getUsersCount();
                $this->more_available = framework\Context::getScope()->hasUsersAvailable();
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runUpdateUser(framework\Request $request)
        {
            try {
                $user = entities\User::getB2DBTable()->selectByID($request['user_id']);
                if ($user instanceof entities\User) {
                    if (!$user->isConfirmedMemberOfScope(framework\Context::getScope())) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => $this->getI18n()->__('This user is not a confirmed member of this scope')]);
                    }
                    if (!empty($request['username'])) {
                        $testuser = entities\User::getByUsername($request['username']);
                        if (!$testuser instanceof entities\User || $testuser->getID() == $user->getID()) {
                            $user->setUsername($request['username']);
                        } else {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['error' => $this->getI18n()->__('This username is already taken')]);
                        }
                    }
                    $password_changed = false;
                    if ($request['password_action'] == 'change' && $request['new_password_1'] && $request['new_password_2']) {
                        if ($request['new_password_1'] == $request['new_password_2']) {
                            $user->setPassword($request['new_password_1']);
                            $password_changed = true;
                        } else {
                            $this->getResponse()->setHttpStatus(400);

                            return $this->renderJSON(['error' => $this->getI18n()->__('Please enter the new password twice')]);
                        }
                    } elseif ($request['password_action'] == 'random') {
                        $random_password = entities\User::createPassword();
                        $user->setPassword($random_password);
                        $password_changed = true;
                    }
                    if (isset($request['realname'])) {
                        $user->setRealname($request['realname']);
                    }
                    $return_options = [];
                    try {
                        if ($group = entities\Group::getB2DBTable()->selectById($request['group'])) {
                            if ($user->getGroupID() != $group->getID()) {
                                $groups = [$user->getGroupID(), $group->getID()];
                                $return_options['update_groups'] = ['ids' => [], 'membercounts' => []];
                            }
                            $user->setGroup($group);
                        }
                    } catch (Exception $e) {
                        throw new Exception($this->getI18n()->__('Invalid user group'));
                    }

                    $existing_teams = array_keys($user->getTeams());
                    $new_teams = [];
                    $new_clients = [];
                    $user->clearTeams();
                    try {
                        foreach ($request->getParameter('teams', []) as $team_id => $team) {
                            if ($team = entities\Team::getB2DBTable()->selectById($team_id)) {
                                $new_teams[] = $team_id;
                                $user->addToTeam($team);
                            }
                        }
                    } catch (Exception $e) {
                        throw new Exception($this->getI18n()->__('One or more teams were invalid'));
                    }

                    try {
                        $user->clearClients();
                        foreach ($request->getParameter('clients', []) as $client_id => $client) {
                            if ($client = entities\Client::getB2DBTable()->selectById($client_id)) {
                                $new_clients[] = $client_id;
                                $user->addToClient($client);
                            }
                        }
                    } catch (Exception $e) {
                        throw new Exception($this->getI18n()->__('One or more clients were invalid'));
                    }
                    if (isset($request['nickname'])) {
                        $user->setBuddyname($request['nickname']);
                    }
                    if (isset($request['email'])) {
                        $user->setEmail($request['email']);
                    }
                    if (isset($request['homepage'])) {
                        $user->setHomepage($request['homepage']);
                    }
                    if (framework\Context::getScope()->isDefault()) {
                        $user->setActivated((bool)$request['activated']);
                        $user->setEnabled((bool)$request['enabled']);
                    }
                    $user->save();
                    if (isset($groups)) {
                        foreach ($groups as $group_id) {
                            if (!$group_id)
                                continue;
                            $return_options['update_groups']['ids'][] = $group_id;
                            $return_options['update_groups']['membercounts'][$group_id] = entities\Group::getB2DBTable()->selectById($group_id)->getNumberOfMembers();
                        }
                    }
                    if ($new_teams != $existing_teams) {
                        $new_team_ids = array_diff($new_teams, $existing_teams);
                        $existing_team_ids = array_diff($existing_teams, $new_teams);
                        $teams_to_update = array_merge($new_team_ids, $existing_team_ids);
                        $return_options['update_teams'] = ['ids' => [], 'membercounts' => []];
                        foreach ($teams_to_update as $team_id) {
                            $return_options['update_teams']['ids'][] = $team_id;
                            $return_options['update_teams']['membercounts'][$team_id] = entities\Team::getB2DBTable()->selectById($team_id)->getNumberOfMembers();
                        }
                    }
                    $template_options = ['user' => $user];
                    if (isset($random_password)) {
                        $template_options['random_password'] = $random_password;
                    }
                    $return_options['content'] = $this->getComponentHTML('configuration/finduser_row', $template_options);
                    $return_options['title'] = $this->getI18n()->__('User updated!');
                    if ($password_changed) {
                        $return_options['message'] = $this->getI18n()->__('The password was changed');
                    }

                    return $this->renderJSON($return_options);
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $this->getI18n()->__('This user could not be updated: %message', ['%message' => $e->getMessage()])]);
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(['error' => $this->getI18n()->__('This user could not be updated')]);
        }

        public function runUpdateUserScopes(framework\Request $request)
        {
            try {
                if (!framework\Context::getScope()->isDefault())
                    throw new Exception('This operation is not allowed');

                $user = entities\User::getB2DBTable()->selectByID($request['user_id']);
                if ($user instanceof entities\User) {
                    $return_options = ['message' => $this->getI18n()->__("The user's scope access was successfully updated")];
                    $scopes = $request->getParameter('scopes', []);
                    if (count($scopes) && !(count($scopes) == 1 && array_key_exists(framework\Settings::getDefaultScopeID(), $scopes))) {
                        foreach ($user->getScopes() as $scope_id => $scope) {
                            if (!$scope->isDefault() && !array_key_exists($scope_id, $scopes)) {
                                $user->removeScope($scope_id);
                            }
                        }
                        foreach ($scopes as $scope_id => $scope) {
                            try {
                                $scope = new entities\Scope((int)$scope_id);
                                if ($user->isMemberOfScope($scope))
                                    continue;

                                $user->addScope($scope);
                            } catch (Exception $e) {

                            }
                        }
                    }

                    return $this->renderJSON($return_options);
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $this->getI18n()->__('This user could not be updated: %message', ['%message' => $e->getMessage()])]);
            }
            $this->getResponse()->setHttpStatus(400);

            return $this->renderJSON(['error' => $this->getI18n()->__('This user could not be updated')]);
        }

        public function runGetPermissionsConfigurator(framework\Request $request)
        {
            return $this->renderComponent('configuration/permissionsconfigurator', ['access_level' => $this->access_level, 'user_id' => $request->getParameter('user_id', 0), 'team_id' => $request->getParameter('team_id', 0), 'base_id' => $request->getParameter('base_id', 0)]);
        }

        public function runConfigureWorkflowSchemes(framework\Request $request)
        {
            $this->schemes = entities\WorkflowScheme::getAll();
        }

        public function runConfigureWorkflows(framework\Request $request)
        {
            $this->workflows = tables\Workflows::getTable()->getAll();
        }

        public function runConfigureWorkflowPost(framework\Request $request)
        {
            try {
                if ($request['workflow_id']) {
                    $workflow = tables\Workflows::getTable()->selectById($request['workflow_id']);
                } else {
                    $workflow = new entities\Workflow();
                }

                if ($request->hasParameter('name')) {
                    $name = trim($request['name']);
                    if (!$name) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => $this->getI18n()->__('Please specify a name for this workflow')]);
                    }
                    $workflow->setName($name);
                }

                if ($request->hasParameter('description')) {
                    $description = trim($request['description']);
                    if (!$description) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => $this->getI18n()->__('Please specify a description for this workflow')]);
                    }
                    $workflow->setDescription($description);
                }

                $workflow->save();

                return $this->renderJSON([
                    'item' => $workflow->toJSON(),
                    'component' => $this->getComponentHTML('configuration/workflow', ['workflow' => $workflow])
                ]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runConfigureWorkflow(framework\Request $request)
        {
            $this->workflow = tables\Workflows::getTable()->selectById($request['workflow_id']);
        }

        public function runConfigureWorkflowSchemeDelete(framework\Request $request)
        {
            try {
                $workflow_scheme = entities\tables\WorkflowSchemes::getTable()->selectById($request['scheme_id']);
                $workflow_scheme->delete();

                return $this->renderJSON(['message' => $this->getI18n()->__('The workflow scheme was deleted'), 'item' => $workflow_scheme->toJSON()]);
            } catch (Exception $e) {

                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runConfigureWorkflowScheme(framework\Request $request)
        {
            try {
                if ($request['scheme_id'] && !$request->hasParameter('clone')) {
                    $workflow_scheme = entities\tables\WorkflowSchemes::getTable()->selectById($request['scheme_id']);
                } else {
                    $workflow_scheme = new entities\WorkflowScheme();
                }
                if (framework\Context::getScope()->isCustomWorkflowsEnabled()) {
                    if (trim($request['name']) == '') {
                        throw new Exception($this->getI18n()->__('Please give the scheme a name'));
                    }

                    $workflow_scheme->setName(trim($request['name']));
                    $workflow_scheme->setDescription(trim($request['description']));
                    $workflow_scheme->save();
                    foreach ($request->getParameter('workflow_id', []) as $issue_type_id => $workflow_id) {
                        $issue_type = entities\tables\Issuetypes::getTable()->selectById($issue_type_id);
                        if ($workflow_id) {
                            $workflow = entities\Workflow::getB2DBTable()->selectById($workflow_id);
                            $workflow_scheme->associateIssuetypeWithWorkflow($issue_type, $workflow);
                        } elseif ($workflow_scheme->getID()) {
                            $workflow_scheme->unassociateIssuetype($issue_type);
                        }
                    }

                    return $this->renderJSON([
                        'message' => $this->getI18n()->__('Workflow associations were updated'),
                        'item' => $workflow_scheme->toJSON(),
                        'content' => $this->getComponentHTML('configuration/workflowscheme', ['scheme' => $workflow_scheme])
                    ]);
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
//                if (framework\Context::getScope()->isCustomWorkflowsEnabled() && $this->mode == 'copy_scheme')
//                {
//                    if ($new_name = $request['new_name'])
//                    {
//                        $new_scheme = new entities\WorkflowScheme();
//                        $new_scheme->setName($new_name);
//                        $new_scheme->save();
//                        foreach ($this->issuetypes as $issuetype)
//                        {
//                            if ($this->workflow_scheme->hasWorkflowAssociatedWithIssuetype($issuetype))
//                            {
//                                $new_scheme->associateIssuetypeWithWorkflow($issuetype, $this->workflow_scheme->getWorkflowForIssuetype($issuetype));
//                            }
//                        }
//                        return $this->renderJSON(array('content' => $this->getComponentHTML('configuration/workflowscheme', array('scheme' => $new_scheme))));
//                    }
//                    else
//                    {
//                        $this->error = $this->getI18n()->__('Please enter a valid name');
//                    }
//                }
//                elseif (framework\Context::getScope()->isCustomWorkflowsEnabled() && $this->mode == 'delete_scheme')
//                {
//                    $this->workflow_scheme->delete();
//                    return $this->renderJSON(array('success' => true, 'message' => $this->getI18n()->__('The workflow scheme was deleted')));
//                }
        }

        public function runConfigureWorkflowSteps(framework\Request $request)
        {
            try {
                $workflow = tables\Workflows::getTable()->selectById($request['workflow_id']);
//                $transition = new entities\WorkflowTransition();
//                $step = tables\WorkflowSteps::getTable()->selectById(9);
//                $transition->setOutgoingStep($step);
//                $transition->setName('Initial transition');
//                $transition->setWorkflow($this->workflow);
//                $transition->setScope(framework\Context::getScope());
//                $transition->setDescription('This is the initial transition for issues using this workflow');
//                $transition->save();
//                $this->workflow->setInitialTransition($transition);
//                $this->workflow->save();
//                if ($this->mode == 'copy_workflow')
//                {
//                    if ($new_name = $request['new_name'])
//                    {
//                        $new_workflow = $this->workflow->copy($new_name);
//                        return $this->renderJSON(array('content' => $this->getComponentHTML('configuration/workflow', array('workflow' => $new_workflow)), 'total_count' => entities\Workflow::getCustomWorkflowsCount(), 'more_available' => framework\Context::getScope()->hasCustomWorkflowsAvailable()));
//                    }
//                    else
//                    {
//                        $this->error = $this->getI18n()->__('Please enter a valid name');
//                    }
//                }
//                elseif ($this->mode == 'delete_workflow')
//                {
//                    $this->workflow->delete();
//                    return $this->renderJSON(array('success' => true, 'message' => $this->getI18n()->__('The workflow was deleted'), 'total_count' => entities\Workflow::getCustomWorkflowsCount(), 'more_available' => framework\Context::getScope()->hasCustomWorkflowsAvailable()));
//                }
            } catch (Exception $e) {
                if ($request->getRequestedFormat() == 'json') {
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['success' => false, 'message' => $this->getI18n()->__('An error occured'), 'error' => $e->getMessage()]);
                } else {
                    $this->error = $this->getI18n()->__('This workflow does not exist');
                }
            }
        }

        public function runConfigureWorkflowStep(framework\Request $request)
        {
            $workflow = tables\Workflows::getTable()->selectById($request['workflow_id']);
            $step = tables\WorkflowSteps::getTable()->selectById($request['step_id']);

            return $this->renderJSON([
                'content' => $this->getComponentHTML('configuration/editworkflowstep', ['step' => $step])
            ]);
        }

        public function runConfigureWorkflowStepPost(framework\Request $request)
        {
            $this->workflow = null;
            $this->step = null;
            try {
                $this->workflow = entities\Workflow::getB2DBTable()->selectById($request['workflow_id']);
                if ($request['mode'] == 'edit' && !$request->hasParameter('step_id')) {
                    $this->step = new entities\WorkflowStep();
                    $this->step->setWorkflow($this->workflow);
                } else {
                    $this->step = entities\WorkflowStep::getB2DBTable()->selectById($request['step_id']);
                }
                if ($request->isPost() && $request['mode'] == 'delete_outgoing_transitions') {
                    $this->step->deleteOutgoingTransitions();
                    $this->forward(framework\Context::getRouting()->generate('configure_workflow_steps', ['workflow_id' => $this->workflow->getID()]));
                }
                if ($request->isPost() && $request['mode'] == 'delete' && !$this->step->hasIncomingTransitions()) {
                    $this->step->deleteOutgoingTransitions();
                    $this->step->delete();
                    $this->forward(framework\Context::getRouting()->generate('configure_workflow_steps', ['workflow_id' => $this->workflow->getID()]));
                } elseif ($request->isPost() && ($request->hasParameter('edit') || $request['mode'] == 'edit')) {
                    $this->step->setName($request['name']);
                    $this->step->setDescription($request['description']);
                    $this->step->setLinkedStatusID($request['status_id']);
                    $this->step->setIsEditable((bool)$request['is_editable']);
                    $this->step->setIsClosed((bool)($request['state'] == entities\Issue::STATE_CLOSED));
                    $this->step->save();


                    $this->forward(framework\Context::getRouting()->generate('configure_workflow_step', ['workflow_id' => $this->workflow->getID(), 'step_id' => $this->step->getID()]));
                }
            } catch (Exception $e) {
                $this->error = $this->getI18n()->__('This workflow / step does not exist');
            }
        }

        public function runConfigureWorkflowTransitionPost(framework\Request $request)
        {
        }

        public function runConfigureWorkflowTransition(framework\Request $request)
        {
            $this->workflow = null;
            $this->transition = null;

            try {
                $this->workflow = tables\Workflows::getTable()->selectById((int)$request['workflow_id']);
                if ($request->hasParameter('transition_id')) {
                    $mode = $request['mode'];
                    $this->transition = tables\WorkflowTransitions::getTable()->selectById((int)$request['transition_id']);
                    if ($request->isPost()) {
                        if ($mode == 'edit') {
                            if (!$this->transition->isInitialTransition()) {
                                $this->transition->setName($request['transition_name']);
                                $this->transition->setDescription($request['transition_description']);
                                if ($request['template']) {
                                    $this->transition->setTemplate($request['template']);
                                } else {
                                    $this->transition->setTemplate(null);
                                }
                            }
                            try {
                                $step = tables\WorkflowSteps::getTable()->selectById((int)$request['outgoing_step_id']);
                                $this->transition->setOutgoingStep($step);
                            } catch (Exception $e) {

                            }
                            $this->transition->save();
                            $transition = $this->transition;
                            $redirect_transition = true;
                        } elseif ($mode == 'delete') {
                            $this->transition->deleteTransition($request['direction'], $request['step_id']);
                            $this->forward(framework\Context::getRouting()->generate('configure_workflow_step', ['workflow_id' => $this->workflow->getID(), 'step_id' => $request['step_id']]));
                        } elseif ($mode == 'delete_action') {
                            $this->action = tables\WorkflowTransitionActions::getTable()->selectById((int)$request['action_id']);
                            $this->action->delete();

                            return $this->renderJSON(['message' => $this->getI18n()->__('The action has been deleted')]);
                        } elseif ($mode == 'new_action') {
                            $action = new entities\WorkflowTransitionAction();
                            $action->setActionType($request['action_type']);
                            $action->setTransition($this->transition);
                            $action->setWorkflow($this->workflow);
                            $action->setTargetValue('');
                            $action->save();

                            return $this->renderJSON(['content' => $this->getComponentHTML('configuration/workflowtransitionaction', ['action' => $action])]);
                        } elseif ($mode == 'update_action') {
                            $this->action = tables\WorkflowTransitionActions::getTable()->selectById((int)$request['action_id']);
                            $this->action->setTargetValue($request['target_value']);
                            $this->action->save();
                            $text = $request['target_value'];
                            switch ($this->action->getActionType()) {
                                case entities\WorkflowTransitionAction::ACTION_ASSIGN_ISSUE:
                                    if ($this->action->hasTargetValue()) {
                                        $target_details = explode('_', $this->action->getTargetValue());
                                        $text = ($target_details[0] == 'user') ? entities\User::getB2DBTable()->selectById((int)$target_details[1])->getNameWithUsername() : entities\Team::getB2DBTable()->selectById((int)$target_details[1])->getName();
                                    } else {
                                        $text = $this->getI18n()->__('User specified during transition');
                                    }
                                    break;
                                case entities\WorkflowTransitionAction::ACTION_SET_RESOLUTION:
                                    $text = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue())->getName() : $this->getI18n()->__('Resolution specified by user');
                                    break;
                                case entities\WorkflowTransitionAction::ACTION_SET_REPRODUCABILITY:
                                    $text = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue())->getName() : $this->getI18n()->__('Reproducability specified by user');
                                    break;
                                case entities\WorkflowTransitionAction::ACTION_SET_STATUS:
                                    $target = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                    $text = ($this->action->getTargetValue()) ? '<span class="status-badge" style="background-color: ' . $target->getColor() . '; color: ' . $target->getTextColor() . ';">' . $target->getName() . '</span>' : $this->getI18n()->__('Status provided by user');
                                    break;
                                case entities\WorkflowTransitionAction::ACTION_SET_PRIORITY:
                                    $text = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue())->getName() : $this->getI18n()->__('Priority specified by user');
                                    break;
                                case entities\WorkflowTransitionAction::ACTION_SET_MILESTONE:
                                    $target = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                    $text = ($this->action->getTargetValue()) ? $target->getProject()->getName() . ' - ' . $target->getName() : $this->getI18n()->__('Milestone specified by user');
                                    break;
                                case entities\WorkflowTransitionAction::CUSTOMFIELD_SET_PREFIX . $this->action->getCustomActionType():
                                    switch (CustomDatatype::getByKey($this->action->getCustomActionType())->getType()) {
                                        case CustomDatatype::INPUT_TEXTAREA_MAIN:
                                        case CustomDatatype::INPUT_TEXTAREA_SMALL:
                                            break;
                                        case CustomDatatype::DATE_PICKER:
                                        case CustomDatatype::DATETIME_PICKER:
                                            return $this->renderJSON(['content' => date('Y-m-d' . (CustomDatatype::getByKey($this->action->getCustomActionType())->getType() == CustomDatatype::DATETIME_PICKER ? ' H:i' : ''), (int)$text)]);
                                            break;
                                        case CustomDatatype::USER_CHOICE:
                                            return $this->renderJSON(['content' => $this->getComponentHTML('main/userdropdown', ['user' => $text])]);
                                            break;
                                        case CustomDatatype::TEAM_CHOICE:
                                            return $this->renderJSON(['content' => $this->getComponentHTML('main/teamdropdown', ['team' => $text])]);
                                            break;
                                        case CustomDatatype::CLIENT_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $text = ($this->action->getTargetValue()) ? Clients::getTable()->selectById((int)$this->action->getTargetValue())->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::RELEASES_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $target = ($this->action->getTargetValue()) ? Builds::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                                $text = ($this->action->getTargetValue()) ? $target->getProject()->getName() . ' - ' . $target->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::COMPONENTS_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $target = ($this->action->getTargetValue()) ? Components::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                                $text = ($this->action->getTargetValue()) ? $target->getProject()->getName() . ' - ' . $target->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::EDITIONS_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $target = ($this->action->getTargetValue()) ? Editions::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                                $text = ($this->action->getTargetValue()) ? $target->getProject()->getName() . ' - ' . $target->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::MILESTONE_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $target = ($this->action->getTargetValue()) ? Milestones::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                                $text = ($this->action->getTargetValue()) ? $target->getProject()->getName() . ' - ' . $target->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::STATUS_CHOICE:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $target = ($this->action->getTargetValue()) ? ListTypes::getTable()->selectById((int)$this->action->getTargetValue()) : null;
                                                $text = ($this->action->getTargetValue()) ? '<span class="status-badge" style="background-color: ' . $target->getColor() . '; color: ' . $target->getTextColor() . ';">' . $target->getName() . '</span>' : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                        case CustomDatatype::DROPDOWN_CHOICE_TEXT:
                                        default:
                                            if (is_numeric($this->action->getTargetValue())) {
                                                $text = ($this->action->getTargetValue()) ? tables\CustomFieldOptions::getTable()->selectById((int)$this->action->getTargetValue())->getName() : $this->getI18n()->__('Value provided by user');
                                            }
                                            break;
                                    }
                                    break;
                            }

                            return $this->renderJSON(['content' => $text]);
                        } elseif ($mode == 'delete_validation_rule') {
                            $this->rule = tables\WorkflowTransitionValidationRules::getTable()->selectById((int)$request['rule_id']);
                            $this->rule->delete();

                            return $this->renderJSON(['message' => $this->getI18n()->__('The validation rule has been deleted')]);
                        } elseif ($mode == 'new_validation_rule') {
                            if (!in_array($request['postorpre'], ['post', 'pre'])) {
                                throw new InvalidArgumentException($this->getI18n()->__('Invalid transition definition'));
                            }
                            $rule = new entities\WorkflowTransitionValidationRule();
                            if ($request['postorpre'] == 'post') {
                                $exists = (bool)($this->transition->hasPostValidationRule($request['rule']));
                                if (!$exists)
                                    $rule->setPost();
                            } elseif ($request['postorpre'] == 'pre') {
                                $exists = (bool)($this->transition->hasPreValidationRule($request['rule']));
                                if (!$exists)
                                    $rule->setPre();
                            }
                            if ($exists) {
                                $this->getResponse()->setHttpStatus(400);

                                return $this->renderJSON(['message' => $this->getI18n()->__('This validation rule already exist')]);
                            }
                            $rule->setRule($request['rule']);
                            $rule->setRuleValue('');
                            $rule->setTransition($this->transition);
                            $rule->setWorkflow($this->workflow);
                            $rule->save();

                            return $this->renderJSON(['content' => $this->getComponentHTML('configuration/workflowtransitionvalidationrule', ['rule' => $rule])]);
                        } elseif ($mode == 'update_validation_rule') {
                            $rule = tables\WorkflowTransitionValidationRules::getTable()->selectById((int)$request['rule_id']);
                            $text = null;
                            if ($rule->isCustom()) {
                                switch ($rule->getCustomType()) {
                                    case CustomDatatype::RADIO_CHOICE:
                                    case CustomDatatype::DROPDOWN_CHOICE_TEXT:
                                    case CustomDatatype::TEAM_CHOICE:
                                    case CustomDatatype::STATUS_CHOICE:
                                    case CustomDatatype::MILESTONE_CHOICE:
                                    case CustomDatatype::CLIENT_CHOICE:
                                    case CustomDatatype::COMPONENTS_CHOICE:
                                    case CustomDatatype::EDITIONS_CHOICE:
                                    case CustomDatatype::RELEASES_CHOICE:
                                        $rule->setRuleValue(join(',', $request['rule_value'] ?: []));
                                        $text = ($rule->getRuleValue()) ? $rule->getRuleValueAsJoinedString() : $this->getI18n()->__('Any valid value');
                                        break;
                                }
                            } else {
                                switch ($rule->getRule()) {
                                    case entities\WorkflowTransitionValidationRule::RULE_MAX_ASSIGNED_ISSUES:
                                        $rule->setRuleValue($request['rule_value']);
                                        $text = ($rule->getRuleValue()) ? $rule->getRuleValue() : $this->getI18n()->__('Unlimited');
                                        break;
                                    case entities\WorkflowTransitionValidationRule::RULE_PRIORITY_VALID:
                                    case entities\WorkflowTransitionValidationRule::RULE_REPRODUCABILITY_VALID:
                                    case entities\WorkflowTransitionValidationRule::RULE_RESOLUTION_VALID:
                                    case entities\WorkflowTransitionValidationRule::RULE_STATUS_VALID:
                                    case entities\WorkflowTransitionValidationRule::RULE_TEAM_MEMBERSHIP_VALID:
                                    case entities\WorkflowTransitionValidationRule::RULE_ISSUE_IN_MILESTONE_VALID:
                                        $rule->setRuleValue(join(',', $request['rule_value'] ?: []));
                                        $text = ($rule->getRuleValue()) ? $rule->getRuleValueAsJoinedString() : $this->getI18n()->__('Any valid value');
                                        break;
                                }
                            }
                            $rule->save();
                            $this->rule = $rule;

                            return $this->renderJSON(['content' => $text]);
                        }
                    }
                } elseif ($request->isPost() && $request->hasParameter('step_id')) {
                    $step = tables\WorkflowSteps::getTable()->selectById((int)$request['step_id']);
                    if ($request['add_transition_type'] == 'existing' && $request->hasParameter('existing_transition_id')) {
                        $transition = tables\WorkflowTransitions::getTable()->selectById((int)$request['existing_transition_id']);
                        $redirect_transition = false;
                    } else {
                        if ($request['transition_name'] && $request['outgoing_step_id'] && $request->hasParameter('template')) {
                            if (($outgoing_step = tables\WorkflowSteps::getTable()->selectById((int)$request['outgoing_step_id'])) && $step instanceof entities\WorkflowStep) {
                                if (!$request['template'] || array_key_exists($request['template'], entities\WorkflowTransition::getTemplates())) {
                                    $transition = new entities\WorkflowTransition();
                                    $transition->setWorkflow($this->workflow);
                                    $transition->setName($request['transition_name']);
                                    $transition->setDescription($request['transition_description']);
                                    $transition->setOutgoingStep($outgoing_step);
                                    $transition->setTemplate($request['template']);
                                    $transition->save();
                                    $step->addOutgoingTransition($transition);
                                    $redirect_transition = true;
                                } else {
                                    throw new InvalidArgumentException($this->getI18n()->__('Please select a valid template'));
                                }
                            } else {
                                throw new InvalidArgumentException($this->getI18n()->__('Please select a valid outgoing step'));
                            }
                        } else {
                            throw new InvalidArgumentException($this->getI18n()->__('Please fill in all required fields'));
                        }
                    }
                    $step->addOutgoingTransition($transition);
                } else {
                    throw new InvalidArgumentException('Invalid action');
                }
            } catch (InvalidArgumentException $e) {
                $this->error = $e->getMessage();
            } catch (Exception $e) {
                $this->error = $this->getI18n()->__('This workflow / transition does not exist');
            }
            if (isset($redirect_transition) && $redirect_transition) {
                $this->forward(framework\Context::getRouting()->generate('configure_workflow_transition', ['workflow_id' => $this->workflow->getID(), 'transition_id' => $transition->getID()]));
            } elseif (isset($redirect_transition)) {
                $this->forward(framework\Context::getRouting()->generate('configure_workflow_steps', ['workflow_id' => $this->workflow->getID()]));
            }
        }

        public function runAddClient(framework\Request $request)
        {
            try {
                $mode = $request['mode'];
                if ($client_name = $request['client_name']) {
                    if (entities\Client::doesClientNameExist(trim($request['client_name']))) {
                        throw new Exception($this->getI18n()->__("Please enter a client name that doesn't already exist"));
                    }
                    $client = new entities\Client();
                    $client->setName($request['client_name']);
                    $client->save();

                    $message = $this->getI18n()->__('The client was added');

                    return $this->renderJSON(['message' => $message, 'content' => $this->getComponentHTML('configuration/clientbox', ['client' => $client])]);
                } else {
                    throw new Exception($this->getI18n()->__('Please enter a client name'));
                }
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runDeleteClient(framework\Request $request)
        {
            try {
                try {
                    $client = entities\Client::getB2DBTable()->selectById($request['client_id']);
                } catch (Exception $e) {

                }
                if (!$client instanceof entities\Client) {
                    throw new Exception($this->getI18n()->__("You cannot delete this client"));
                }

                if (entities\Project::getAllByClientID($client->getID()) !== null) {
                    foreach (entities\Project::getAllByClientID($client->getID()) as $project) {
                        $project->setClient(null);
                        $project->save();
                    }
                }

                $client->delete();

                return $this->renderJSON(['success' => true, 'message' => $this->getI18n()->__('The client was deleted')]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runGetClientMembers(framework\Request $request)
        {
            try {
                $client = entities\Client::getB2DBTable()->selectById((int)$request['client_id']);
                $users = $client->getMembers();

                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/clientuserlist', compact('users', 'client'))]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runRemoveClientMember(framework\Request $request)
        {
            try {
                $client = Clients::getTable()->selectById((int)$request['client_id']);
                $user = entities\User::getB2DBTable()->selectByID((int)$request['user_id']);

                $client->removeMember($user);

                return $this->renderJSON(['update_clients' => ['ids' => [$client->getID()], 'membercounts' => [$client->getID() => $client->getNumberOfMembers()]]]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runAddClientMember(framework\Request $request)
        {
            try {
                $user_id = (int)$request['user_id'];
                $client = Clients::getTable()->selectById((int)$request['client_id']);
                $user = entities\User::getB2DBTable()->selectByID($user_id);

                $client->addMember($user);

                return $this->renderJSON(['clientlistitem' => $this->getComponentHTML('configuration/clientuserlistitem', compact('client', 'user_id', 'user')), 'update_clients' => ['ids' => [$client->getID()], 'membercounts' => [$client->getID() => $client->getNumberOfMembers()]]]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runEditClient(framework\Request $request)
        {
            try {
                try {
                    $client = entities\Client::getB2DBTable()->selectById($request['client_id']);
                } catch (Exception $e) {

                }
                if (!$client instanceof entities\Client) {
                    throw new Exception($this->getI18n()->__("You cannot edit this client"));
                }

                if (entities\Client::doesClientNameExist(trim($request['client_name'])) && strtolower($request['client_name']) != strtolower($client->getName())) {
                    throw new Exception($this->getI18n()->__("Please enter a client name that doesn't already exist"));
                }

                $client->setName($request['client_name']);
                $client->setEmail($request['client_email']);
                $client->setWebsite($request['client_website']);
                $client->setTelephone($request['client_telephone']);
                $client->setFax($request['client_fax']);
                $client->save();

                return $this->renderJSON(['success' => true, 'content' => $this->getComponentHTML('configuration/clientbox', ['client' => $client]), 'message' => $this->getI18n()->__('The client was saved')]);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $e->getMessage()]);
            }
        }

        public function runConfigureScopes(framework\Request $request)
        {
            if ($request->isPost()) {
                $hostname = $request['hostname'];
                $hostname = str_replace(['http://', 'https://'], ['', ''], $hostname);

                $scopename = $request['name'];
                if (!$hostname || tables\Scopes::getTable()->getByHostname($hostname) instanceof entities\Scope) {
                    $this->scope_hostname_error = true;
                } elseif (!$scopename) {
                    $this->scope_name_error = true;
                } else {
                    $scope = new entities\Scope();
                    $scope->addHostname($hostname);
                    $scope->setName($scopename);
                    $scope->setEnabled();
                    $scope->save();
                    $this->forward(framework\Context::getRouting()->generate('configure_scopes'));
                }
            }
            $this->scope_deleted = framework\Context::getMessageAndClear('scope_deleted');
            $this->scope_saved = framework\Context::getMessageAndClear('scope_saved');
            $pagination_scopes = tables\Scopes::getTable()->getPaginationItems();
            $pagination = new Pagination($pagination_scopes, $this->getRouting()->generate('configure_scopes'), $request);
            $this->scopes = tables\Scopes::getTable()->getByIds($pagination->getPageItems());
            $this->pagination = $pagination;
        }

        public function runScope(framework\Request $request)
        {
            $this->scope = new entities\Scope($request['id']);
            $modules = tables\Modules::getTable()->getModulesForScope($this->scope->getID());
            $this->modules = $modules;
            $this->scope_save_error = framework\Context::getMessageAndClear('scope_save_error');

            if ($request->isPost()) {
                try {
                    if ($request['scope_action'] == 'delete') {
                        if (!$this->scope->isDefault()) {
                            $this->scope->delete();
                            framework\Context::setMessage('scope_deleted', true);
                            $this->forward(make_url('configure_scopes'));
                        } else {
                            $this->scope_save_error = $this->getI18n()->__('You cannot delete the default scope');
                        }
                    } else {
                        if (!$request['name']) {
                            throw new Exception($this->getI18n()->__('Please specify a scope name'));
                        }
                        $this->scope->setName($request['name']);
                        $this->scope->setDescription($request['description']);
                        $this->scope->setCustomWorkflowsEnabled((bool)$request['custom_workflows_enabled']);
                        $this->scope->setMaxWorkflowsLimit((int)$request['workflow_limit']);
                        $this->scope->setUploadsEnabled((bool)$request['file_uploads_enabled']);
                        $this->scope->setMaxUploadLimit((int)$request['upload_limit']);
                        $this->scope->setMaxProjects((int)$request['project_limit']);
                        $this->scope->setMaxUsers((int)$request['user_limit']);
                        $this->scope->setMaxTeams((int)$request['team_limit']);
                        $this->scope->save();

                        $enabled_modules = $request['module_enabled'];
                        $prev_scope = framework\Context::getScope();
                        foreach ($enabled_modules as $module => $enabled) {
                            if (!framework\Context::getModule($module)->isCore() && !$enabled && array_key_exists($module, $modules)) {
                                $module = tables\Modules::getTable()->getModuleForScope($module, $this->scope->getID());
                                $module->uninstall($this->scope->getID());
                            } elseif (!framework\Context::getModule($module)->isCore() && $enabled && !array_key_exists($module, $modules)) {
                                framework\Context::setScope($this->scope);
                                entities\Module::installModule($module);
                                framework\Context::setScope($prev_scope);
                            }
                        }
                        framework\Context::setMessage('scope_saved', true);
                        $this->forward(make_url('configure_scopes'));
                    }
                } catch (Exception $e) {
                    framework\Context::setMessage('scope_save_error', $e->getMessage());
                }
            }
        }

        public function runConfigureRole(framework\Request $request)
        {
            try {
                $role = new entities\Role($request['role_id']);
            } catch (Exception $e) {
                $this->getResponse()->setHttpStatus(400);

                return $this->renderJSON(['error' => $this->getI18n()->__('This is not a valid role')]);
            }
            if ($role->isSystemRole()) {
                $access_level = $this->getAccessLevel($request['section'], 'core');
            } else {
                $access_level = ($this->getUser()->canManageProject($role->getProject())) ? framework\Settings::ACCESS_FULL : framework\Settings::ACCESS_READ;
            }

            switch ($request['mode']) {
                case 'edit':
                    if (!$access_level == framework\Settings::ACCESS_FULL) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => $this->getI18n()->__('You do not have access to edit these permissions')]);
                    }
                    if ($request->isPost()) {
                        $role->setName($request['name']);
                        $role->save();
                        $new_permissions = [];
                        foreach ($request['permissions'] ?: [] as $new_permission) {
                            $permission_details = explode(',', $new_permission);
                            $new_permissions[$permission_details[2]] = ['module' => $permission_details[0], 'target_id' => $permission_details[1]];
                        }
                        $existing_permissions = [];
                        foreach ($role->getPermissions() as $existing_permission) {
                            if (!array_key_exists($existing_permission->getPermission(), $new_permissions)) {
                                $role->removePermission($existing_permission);
                            } else {
                                $existing_permissions[$existing_permission->getPermission()] = $new_permissions[$existing_permission->getPermission()];
                                unset($new_permissions[$existing_permission->getPermission()]);
                            }
                        }
                        foreach ($new_permissions as $permission_key => $details) {
                            $p = new entities\RolePermission();
                            $p->setModule($details['module']);
                            $p->setPermission($permission_key);
                            if ($details['target_id'])
                                $p->setTargetID($details['target_id']);

                            $role->addPermission($p);
                        }
                        foreach ($existing_permissions as $permission_key => $details) {
                            $p = new entities\RolePermission();
                            $p->setModule($details['module']);
                            $p->setPermission($permission_key);
                            if ($details['target_id'])
                                $p->setTargetID($details['target_id']);

                            tables\Permissions::getTable()->addRolePermission($role, $p);
                        }
                        framework\Context::clearPermissionsCache();

                        framework\Context::cacheAllPermissions();

                        return $this->renderJSON(['message' => $this->getI18n()->__('Permissions updated'), 'permissions_count' => count($request['permissions']), 'role_name' => $role->getName()]);
                    }

                    return $this->renderComponent('configuration/rolepermissionsedit', ['role' => $role]);
                case 'delete':
                    if (!$access_level == framework\Settings::ACCESS_FULL || !$request->isPost()) {
                        $this->getResponse()->setHttpStatus(400);

                        return $this->renderJSON(['error' => $this->getI18n()->__('This role cannot be removed')]);
                    }
                    $role->delete();

                    return $this->renderJSON(['message' => $this->getI18n()->__('Role deleted')]);
            }
        }

        public function runConfigureRoles(framework\Request $request)
        {
            if ($request->isPost()) {
                if (trim($request['role_name']) == '') {
                    $this->getResponse()->setHttpStatus(400);

                    return $this->renderJSON(['error' => $this->getI18n()->__('You have to specify a name for this role')]);
                }
                $role = new entities\Role();
                $role->setName($request['role_name']);
                $role->save();

                return $this->renderJSON(['content' => $this->getComponentHTML('configuration/role', ['role' => $role])]);
            }
            $this->roles = entities\Role::getAll();
        }

        public function runSiteIcons(framework\Request $request)
        {
            if ($this->getAccessLevel($request['section'], 'core') == framework\Settings::ACCESS_FULL) {
                if ($request->isPost()) {
                    switch ($request['small_icon_action']) {
                        case 'upload_file':
                            $file = $request->handleUpload('small_icon');
                            framework\Settings::saveSetting(framework\Settings::SETTING_FAVICON_TYPE, framework\Settings::APPEARANCE_FAVICON_CUSTOM);
                            framework\Settings::saveSetting(framework\Settings::SETTING_FAVICON_ID, $file->getID());
                            break;
                        case 'clear_file':
                            framework\Settings::saveSetting(framework\Settings::SETTING_FAVICON_TYPE, framework\Settings::APPEARANCE_FAVICON_THEME);
                            break;
                    }
                    switch ($request['large_icon_action']) {
                        case 'upload_file':
                            $file = $request->handleUpload('large_icon');
                            framework\Settings::saveSetting(framework\Settings::SETTING_HEADER_ICON_TYPE, framework\Settings::APPEARANCE_HEADER_CUSTOM);
                            framework\Settings::saveSetting(framework\Settings::SETTING_HEADER_ICON_ID, $file->getID());
                            break;
                        case 'clear_file':
                            framework\Settings::saveSetting(framework\Settings::SETTING_HEADER_ICON_TYPE, framework\Settings::APPEARANCE_HEADER_THEME);
                            break;
                    }
                }
                $route = framework\Context::getRouting()->generate('configure_settings');
                if ($request->isAjaxCall()) {
                    return $this->renderJSON(['forward' => $route]);
                } else {
                    $this->forward($route);
                }
            }

            return $this->forward403($this->getI18n()->__("You don't have access to perform this action"));
        }

    }
