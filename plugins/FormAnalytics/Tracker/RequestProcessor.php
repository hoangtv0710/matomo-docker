<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\FormAnalytics\Tracker;

use Piwik\Common;
use Piwik\Date;
use Piwik\Plugins\FormAnalytics\Dao\LogForm;
use Piwik\Plugins\FormAnalytics\Dao\LogFormField;
use Piwik\Plugins\FormAnalytics\Dao\LogFormPage;
use Piwik\Tracker\Request;
use Piwik\Tracker;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Plugins\FormAnalytics\Actions\ActionForm;

class RequestProcessor extends Tracker\RequestProcessor
{
    const MAX_FORM_ID_VIEW_LENGTH = 6;
    /**
     * FORM CONSTANTS
     */
    const PARAM_FORM_ID_VIEW = 'fa_vid';
    const PARAM_FORM_ID = 'fa_id';
    const PARAM_FORM_NAME = 'fa_name';
    const PARAM_FORM_HESITATION_TIME = 'fa_ht';
    const PARAM_FORM_TIME_SPENT = 'fa_ts';
    const PARAM_FORM_TIME_TO_SUBMISSION = 'fa_tts';
    const PARAM_FORM_ENTRY_FIELD = 'fa_ef';
    const PARAM_FORM_EXIT_FIELD = 'fa_lf';
    const PARAM_FORM_IS_SUBMIT = 'fa_su';
    const PARAM_FORM_IS_CONVERSION = 'fa_co';
    const PARAM_FORM_IS_CONVERSION_ID = 'fa_cv';
    const PARAM_FORM_VIEW = 'fa_fv';
    const PARAM_FORM_START = 'fa_st';
    const PARAM_FORM_WITH_PAGEVIEW_REQUEST = 'fa_pv';
    const PARAM_FORMS_WITH_PAGEVIEW = 'fa_fp';

    const PARAM_PAGE_ID_VIEW = 'pv_id';

    /**
     * FORM FIELD CONSTANTS
     */
    const PARAM_FORM_FIELD_NAME = 'fa_fn';
    const PARAM_FORM_FIELD_HESITATION_TIME = 'fa_fht';
    const PARAM_FORM_FIELD_TIME_SPENT = 'fa_fts';
    const PARAM_FORM_FIELD_NUM_FOCUS= 'fa_ff';
    const PARAM_FORM_FIELD_NUM_CHANGES = 'fa_fch';
    const PARAM_FORM_FIELD_NUM_DELETES = 'fa_fd';
    const PARAM_FORM_FIELD_NUM_CURSOR = 'fa_fcu';
    const PARAM_FORM_FIELD_TYPE = 'fa_ft';
    const PARAM_FORM_FIELD_SIZE = 'fa_fs';
    const PARAM_FORM_FIELD_BLANK = 'fa_fb';
    const PARAM_FORM_FIELDS = 'fa_fields';

    const MYSQL_MAX_MEDIUM_UINT = 16777215;

    /**
     * @var LogForm
     */
    private $logForm;

    /**
     * @var LogFormField
     */
    private $logFormField;

    /**
     * @var LogFormPage
     */
    private $logFormPage;

    /**
     * @var FormManager
     */
    private $manager;

    public function __construct(LogForm $logForm, LogFormField $logFormField, LogFormPage $logFormPage, FormManager $formManager)
    {
        $this->logForm = $logForm;
        $this->logFormField = $logFormField;
        $this->logFormPage = $logFormPage;
        $this->manager = $formManager;
    }

    public function manipulateRequest(Request $request)
    {
        if (ActionForm::shouldHandle($request)) {
            $this->setIsFormRequest($request);
        }
    }

    public function afterRequestProcessed(VisitProperties $visitProperties, Request $request)
    {
        $action = $this->preprocessFormRequest($visitProperties, $request);

        if ($this->isFormRequest($request) && $action) {
            /** @var Tracker\Action $action */
            $pageUrl = $action->getActionUrl();
            $params = $request->getParams();
            $idSite = $request->getIdSite();

            $matchingForms = $this->findMatchingForms($idSite, $params, $pageUrl);

            if (empty($matchingForms)) {
                // we abort this tracking request as no matching form has been fount. this avoids creating or updating
                // a visit and looking for forms which don't exist etc.
                return true;
            }
        }
    }

    private function preprocessFormRequest(VisitProperties $visitProperties, Request $request)
    {
        $action = $request->getMetadata('Actions', 'action');

        if (!empty($action)) {
            // we set a copy here as we may unset it under Actions but need it later
            $request->setMetadata('FormAnalytics', 'action', $action);
        }

        if ($this->isFormRequest($request)) {
            $request->setMetadata('Actions', 'action', null);
            $request->setMetadata('Goals', 'goalsConverted', array());

            $request->setMetadata('FormAnalytics', 'doUpdateVisit', false);
            $lastActionTime = $visitProperties->getProperty('visit_last_action_time');
            if ($lastActionTime && is_numeric($lastActionTime)) {
                // it is only numeric when directly being called afterRequestProcessed() and not eg handleExistingVisit
                // because the VisitLastActionTime dimension will overwrite the original value of the visitor.
                // we want to make sure to work on the value from the DB
                $lastActionTimeDate = Date::factory($lastActionTime)->addPeriod(5, 'minutes');
                $dateVisitWillBeUpdatedTo = Date::factory($request->getCurrentTimestamp());
                if ($lastActionTimeDate->isEarlier($dateVisitWillBeUpdatedTo)) {
                    // we update visit_last_action_time only if visit_last_action_time was updated more than 5 min ago
                    // we do not update all the time or every minute as not needed and to save resources
                    $request->setMetadata('FormAnalytics', 'doUpdateVisit', true);
                }
            }
        }

        return $action;
    }

    // Actions and Goals metadata might be set after this plugin's afterRequestProcessed was called, make sure to unset it
    public function onNewVisit(VisitProperties $visitProperties, Request $request)
    {
        $this->preprocessFormRequest($visitProperties, $request);
    }

    public function onExistingVisit(&$valuesToUpdate, VisitProperties $visitProperties, Request $request)
    {
        if ($this->isFormRequest($request)) {
            // we want to make sure to not execute any updates on visit since there could be MANY requests and this could
            // add too much load to server.
            if (!$request->getMetadata('FormAnalytics', 'doUpdateVisit')) {
                $valuesToUpdate = array();
            }
        }

        $this->preprocessFormRequest($visitProperties, $request);
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {
        /**
         * @var ActionForm $action
         */
        $action = $request->getMetadata('FormAnalytics', 'action');

        if (empty($action)) {
            return;
        }

        $idSite = $request->getIdSite();
        $idVisit = $visitProperties->getProperty('idvisit');
        $pageUrl = $action->getActionUrl();
        $params = $request->getParams();

        // a value above the MEDIUMINT for fa_tts can cause an exception to be thrown when inserting a log. in this
        // case we ignore the log.
        $timeToSubmission = Common::getRequestVar(static::PARAM_FORM_TIME_TO_SUBMISSION, 0, 'int', $params);
        if ($timeToSubmission > self::MYSQL_MAX_MEDIUM_UINT) {
            return;
        }

        // we only convert forms when it is not a form request
        // for performance to not slow down form tracking requests and there is no way to match a conversion
        // by form name or ID so far anyway. Instead we will convert this eg on a pageview
        if ($action instanceof Tracker\ActionPageview) {
            $this->recordPossibleFormConversions($idSite, $idVisit, $pageUrl);
        }

        $idVisitor = $visitProperties->getProperty('idvisitor');

        if (!$this->isFormRequest($request)) {
            // we still continue processing if 1) it is a form request (identified by formIdView) or
            // 2) if it is a form request that was sent along a regular pageview request
            $isFormRequestButSentAlongWithPageview = Common::getRequestVar(static::PARAM_FORM_WITH_PAGEVIEW_REQUEST, 0, 'int', $params);

            $idFormView = Common::getRequestVar(static::PARAM_FORM_ID_VIEW, '', 'string', $params);

            if ($isFormRequestButSentAlongWithPageview && $idFormView) {
                // only there for BC for older JS tracker versions.. can be removed eventually.
                // The logic in JS tracker was changed in September 2017 so could remove this case around January to March 2018
                $this->processFormParams($idSite, $params,  $pageUrl, $idVisit, $idVisitor, $request, $action);
            } elseif ($isFormRequestButSentAlongWithPageview) {
                $originalParams = $params;

                $formsParams = Common::getRequestVar(self::PARAM_FORMS_WITH_PAGEVIEW, [], 'array', $params);
                if (!empty($formsParams)) {
                    foreach ($formsParams as $formParams) {
                        $formParams = array_merge($originalParams, $formParams);
                        $this->processFormParams($idSite, $formParams,  $pageUrl, $idVisit, $idVisitor, $request, $action);
                    }
                }
            }

            return;
        }

        $this->processFormParams($idSite, $params,  $pageUrl, $idVisit, $idVisitor, $request, $action);
    }

    private function processFormParams($idSite, $params, $pageUrl, $idVisit, $idVisitor, Request $request, Tracker\Action $action)
    {
        $matchingForms = $this->findMatchingForms($idSite, $params, $pageUrl);

        if (empty($matchingForms)) {
            // no matching form found, we won't track anything
            return;
        }

        $action->loadIdsFromLogActionTable();
        $idActionUrl = $action->getIdActionUrl();

        $serverTime = Date::getDatetimeFromTimestamp($request->getCurrentTimestamp());
        $isConverted = Common::getRequestVar(static::PARAM_FORM_IS_CONVERSION, 0, 'int', $params);

        foreach ($matchingForms as $matchingForm) {
            if (!empty($isConverted)) {
                // we do not want to update anything else to prevent using a new formidview. this way the last submitted
                // fields still stay accurate (otherwise we would update last_idformview)
                $this->logForm->recordConversion($idVisit, $matchingForm['idsiteform']);
            } else {
                $this->trackForm($matchingForm, $idVisitor, $idVisit, $idActionUrl, $serverTime, $params);
            }
        }
    }

    protected function trackForm($form, $idVisitor, $idVisit, $idActionUrl, $serverTime, $params)
    {
        $idSiteForm = $form['idsiteform'];
        $idSite = $form['idsite'];

        $isSubmitted = Common::getRequestVar(static::PARAM_FORM_IS_SUBMIT, 0, 'int', $params);
        $isViewed = Common::getRequestVar(static::PARAM_FORM_VIEW, 0, 'int', $params);
        $isStarted = Common::getRequestVar(static::PARAM_FORM_START, 0, 'int', $params);
        $timeHesitation = Common::getRequestVar(static::PARAM_FORM_HESITATION_TIME, 0, 'int', $params);
        $timeSpent = Common::getRequestVar(static::PARAM_FORM_TIME_SPENT, 0, 'int', $params);
        $timeToSubmission = Common::getRequestVar(static::PARAM_FORM_TIME_TO_SUBMISSION, 0, 'int', $params);
        $entryField = Common::getRequestVar(static::PARAM_FORM_ENTRY_FIELD, false, 'string', $params);
        $exitField = Common::getRequestVar(static::PARAM_FORM_EXIT_FIELD, false, 'string', $params);
        $idFormView = Common::getRequestVar(static::PARAM_FORM_ID_VIEW, false, 'string', $params);
        $idFormView = substr($idFormView, 0, self::MAX_FORM_ID_VIEW_LENGTH);

        if ($isSubmitted) {
            if ($timeSpent <= 0) {
                // we make sure to record at least 1ms as logAggregator sometimes goes to
                // time_spent and we need to make sure there is a value in time spent. otherwise could be edge case where user
                // calls trackFormSubmit directly without ever registering the form itself
                $timeSpent = 1;
            }
            if ($timeToSubmission <= 0) {
                // we make sure to track at least 1ms. It won't be possible to complete a form in under 1ms and allows us
                // to also know when a form was submitted or not
                $timeToSubmission = 1;
            }
        }

        $idLogForm = $this->logForm->findRecord($idVisit, $idSiteForm, $idFormView);

        if (!empty($idLogForm)) {
            $this->logForm->updateRecord($idLogForm, $idFormView, $isViewed, $isStarted, $isSubmitted, $serverTime, $timeHesitation, $timeSpent, $timeToSubmission);
        } else {
            $idLogForm = $this->logForm->addRecord($idVisitor, $idVisit, $idSite, $idSiteForm, $idFormView, $isViewed, $isStarted, $isSubmitted, $serverTime, $timeHesitation, $timeSpent, $timeToSubmission);
        }

        if (empty($idLogForm)) {
            return;
        }

        $idLogFormPage = $this->logFormPage->findIdLogFormPage($idLogForm, $idActionUrl);
        if (!empty($idLogFormPage)) {
            $this->logFormPage->updateRecord($idLogFormPage, $isViewed, $isStarted, $isSubmitted, $timeHesitation, $timeSpent, $timeToSubmission, $entryField, $exitField);
        } else {
            $idLogFormPage = $this->logFormPage->record($idLogForm, $idActionUrl, $isViewed, $isStarted, $isSubmitted, $timeHesitation, $timeSpent, $timeToSubmission, $entryField, $exitField);
        }

        $fields = Common::getRequestVar(static::PARAM_FORM_FIELDS, false, 'json', $params);
        if (!empty($fields) && !is_array($fields)) {
            // seen requests where for some reason fields were double json encoded or quotes are escaped...
            // refs DEV-1420
            $manuallyEncoded = @json_decode($params[static::PARAM_FORM_FIELDS]);
            if ($manuallyEncoded) {
                $params2 = array(static::PARAM_FORM_FIELDS => $manuallyEncoded);
                $fields = Common::getRequestVar(static::PARAM_FORM_FIELDS, false, 'json', $params2);
            }
        }

        if (!empty($idLogForm) && !empty($idLogFormPage) && !empty($fields)) {
            $this->manager->completeFormFieldsIfNeeded($form, $fields);

            $idPageView = Common::getRequestVar(self::PARAM_PAGE_ID_VIEW, '', 'string', $params);
            $idPageView = substr($idPageView, 0, RequestProcessor::MAX_FORM_ID_VIEW_LENGTH);

            foreach ($fields as $field) {
                if (!empty($field[static::PARAM_FORM_FIELD_NAME])) {
                    if ($this->logFormField->hasRecord($idLogForm, $field[static::PARAM_FORM_FIELD_NAME], $idFormView)) {
                        $this->logFormField->updateRecord(
                            $idLogForm,
                            $idFormView,
                            $isSubmitted,
                            $field[static::PARAM_FORM_FIELD_NAME],
                            $field[static::PARAM_FORM_FIELD_SIZE],
                            $field[static::PARAM_FORM_FIELD_BLANK],
                            $field[static::PARAM_FORM_FIELD_TIME_SPENT],
                            $field[static::PARAM_FORM_FIELD_HESITATION_TIME],
                            $field[static::PARAM_FORM_FIELD_NUM_CHANGES],
                            $field[static::PARAM_FORM_FIELD_NUM_FOCUS],
                            $field[static::PARAM_FORM_FIELD_NUM_DELETES],
                            $field[static::PARAM_FORM_FIELD_NUM_CURSOR]
                        );
                    } else {
                        $this->logFormField->record(
                            $idLogForm,
                            $idLogFormPage,
                            $idFormView,
                            $idPageView,
                            $isSubmitted,
                            $field[static::PARAM_FORM_FIELD_NAME],
                            $field[static::PARAM_FORM_FIELD_SIZE],
                            $field[static::PARAM_FORM_FIELD_BLANK],
                            $field[static::PARAM_FORM_FIELD_TIME_SPENT],
                            $field[static::PARAM_FORM_FIELD_HESITATION_TIME],
                            $field[static::PARAM_FORM_FIELD_NUM_CHANGES],
                            $field[static::PARAM_FORM_FIELD_NUM_FOCUS],
                            $field[static::PARAM_FORM_FIELD_NUM_DELETES],
                            $field[static::PARAM_FORM_FIELD_NUM_CURSOR]
                        );
                    }

                }
            }
        }
    }

    protected function findMatchingForms($idSite, $params, $pageUrl)
    {
        $formName = Common::getRequestVar(static::PARAM_FORM_NAME, '', 'string', $params);
        $formId = Common::getRequestVar(static::PARAM_FORM_ID, '', 'string', $params);

        $matchingForms = $this->manager->findForms($idSite, $formName, $formId, $pageUrl);

        if (empty($matchingForms)) {
            $autoCreatedForm = $this->manager->autoCreateForm($idSite, $formName, $formId, $pageUrl);
            if (!empty($autoCreatedForm)) {
                $matchingForms = array($autoCreatedForm);
            }
        }

        return $matchingForms;
    }

    protected function recordPossibleFormConversions($idSite, $idVisit, $pageUrl)
    {
        // we do this only for pageviews for performance reasons and because users actually define page URLs
        // in the UI and not eg media urls or download urls
        $forms = $this->manager->getCachedRunningForms($idSite);

        foreach ($forms as $form) {
            if ($this->manager->isConvertedForm($form, $pageUrl)) {
                $this->logForm->recordConversion($idVisit, $form['idsiteform']);
                // we should liekly only convert latest form as there might be several unconverted
                // also check Where num_submissions > 0 only a submitted form can be converted!
            }
        }
    }

    protected function setIsFormRequest(Request $request)
    {
        $request->setMetadata('FormAnalytics', 'form', true);
    }

    protected function isFormRequest(Request $request)
    {
        return (bool) $request->getMetadata('FormAnalytics', 'form');
    }

}
