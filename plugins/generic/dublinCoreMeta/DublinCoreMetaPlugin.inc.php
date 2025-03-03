<?php

/**
 * @file plugins/generic/dublinCoreMeta/DublinCoreMetaPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DublinCoreMetaPlugin
 * @ingroup plugins_generic_dublinCoreMeta
 *
 * @brief Inject Dublin Core meta tags into monograph views to facilitate indexing.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use \APP\template\TemplateManager;

class DublinCoreMetaPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                HookRegistry::register('CatalogBookHandler::book', [&$this, 'monographView']);
                HookRegistry::register('CatalogBookHandler::view', [$this, 'monographFileView']);
            }
            return true;
        }
        return false;
    }

    /**
     * Get the name of the settings file to be installed on new context
     * creation.
     *
     * @return string
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Inject Dublin Core metadata into monograph view
     *
     * @param $hookName string
     * @param $args array
     *
     * @return boolean
     */
    public function monographView($hookName, $args)
    {
        $request = $args[0];
        $monograph = $args[1];
        $publication = $monograph->getCurrentPublication();
        $press = $request->getContext();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addHeader('dublinCoreSchema', '<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />');

        $i = 0;
        if ($sponsors = $publication->getData('supportingAgencies')) {
            foreach ($sponsors as $locale => $sponsor) {
                $templateMgr->addHeader('dublinCoreSponsor' . $i++, '<meta name="DC.Contributor.Sponsor" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($sponsor)) . '"/>');
            }
        }

        $i = 0;
        if ($coverages = $publication->getData('coverage')) {
            foreach ($coverages as $locale => $coverage) {
                $templateMgr->addHeader('dublinCoreCoverage' . $i++, '<meta name="DC.Coverage" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($coverage)) . '"/>');
            }
        }

        $i = 0;
        foreach ($publication->getData('authors') as $author) {
            $templateMgr->addHeader('dublinCoreAuthor' . $i++, '<meta name="DC.Creator.PersonalName" content="' . htmlspecialchars($author->getFullName(false)) . '"/>');
        }

        if ($datePublished = $publication->getData('datePublished')) {
            $templateMgr->addHeader('dublinCoreDateCreated', '<meta name="DC.Date.created" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($datePublished)) . '"/>');
        }
        $templateMgr->addHeader('dublinCoreDateSubmitted', '<meta name="DC.Date.dateSubmitted" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($monograph->getDateSubmitted())) . '"/>');
        if ($dateModified = $publication->getData('lastModified')) {
            $templateMgr->addHeader('dublinCoreDateModified', '<meta name="DC.Date.modified" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($dateModified)) . '"/>');
        }
        $i = 0;
        if ($abstracts = $publication->getData('abstract')) {
            foreach ($abstracts as $locale => $abstract) {
                $templateMgr->addHeader('dublinCoreAbstract' . $i++, '<meta name="DC.Description" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($abstract)) . '"/>');
            }
        }

        $templateMgr->addHeader('dublinCoreIdentifier', '<meta name="DC.Identifier" content="' . htmlspecialchars($monograph->getBestId()) . '"/>');

        foreach ((array) $templateMgr->getTemplateVars('pubIdPlugins') as $pubIdPlugin) {
            if ($pubId = $monograph->getStoredPubId($pubIdPlugin->getPubIdType())) {
                $templateMgr->addHeader('dublinCorePubId' . $pubIdPlugin->getPubIdDisplayType(), '<meta name="DC.Identifier.' . htmlspecialchars($pubIdPlugin->getPubIdDisplayType()) . '" content="' . htmlspecialchars($pubId) . '"/>');
            }
        }

        $templateMgr->addHeader('dublinCoreUri', '<meta name="DC.Identifier.URI" content="' . $request->url(null, 'catalog', 'book', [$monograph->getBestId()]) . '"/>');
        $templateMgr->addHeader('dublinCoreLanguage', '<meta name="DC.Language" scheme="ISO639-1" content="' . substr($publication->getData('locale'), 0, 2) . '"/>');
        $templateMgr->addHeader('dublinCoreCopyright', '<meta name="DC.Rights" content="' . htmlspecialchars(__('submission.copyrightStatement', ['copyrightHolder' => $monograph->getCopyrightHolder($publication->getData('locale')), 'copyrightYear' => $monograph->getCopyrightYear()])) . '"/>');
        $templateMgr->addHeader('dublinCorePagesLicenseUrl', '<meta name="DC.Rights" content="' . htmlspecialchars($monograph->getLicenseURL()) . '"/>');
        $templateMgr->addHeader('dublinCoreSource', '<meta name="DC.Source" content="' . htmlspecialchars($press->getName($press->getPrimaryLocale())) . '"/>');

        $templateMgr->addHeader('dublinCoreSourceUri', '<meta name="DC.Source.URI" content="' . $request->url($press->getPath()) . '"/>');

        $i = 0;
        $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
        $supportedLocales = array_keys(AppLocale::getSupportedFormLocales());
        if ($subjects = $submissionSubjectDao->getSubjects($publication->getId(), $supportedLocales)) {
            foreach ($subjects as $locale => $subjectLocale) {
                foreach ($subjectLocale as $subject) {
                    $templateMgr->addHeader('dublinCoreSubject' . $i++, '<meta name="DC.Subject" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($subject) . '"/>');
                }
            }
        }

        $i = 0;
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
        if ($keywords = $submissionKeywordDao->getKeywords($publication->getId(), $supportedLocales)) {
            foreach ($keywords as $locale => $keywordLocale) {
                foreach ($keywordLocale as $keyword) {
                    $templateMgr->addHeader('dublinCoreKeyword' . $i++, '<meta name="DC.Subject" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($keyword) . '"/>');
                }
            }
        }

        $templateMgr->addHeader('dublinCoreTitle', '<meta name="DC.Title" content="' . htmlspecialchars($publication->getData('title', $monograph->getLocale())) . '"/>');
        $i = 0;
        foreach ($publication->getData('title') as $locale => $title) {
            if ($locale == $monograph->getLocale()) {
                continue;
            }
            $templateMgr->addHeader('dublinCoreAltTitle' . $i++, '<meta name="DC.Title.Alternative" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($title) . '"/>');
        }

        $templateMgr->addHeader('dublinCoreType', '<meta name="DC.Type" content="Text.Book"/>');
        $i = 0;
        if ($types = $publication->getData('type')) {
            foreach ($types as $locale => $type) {
                $templateMgr->addHeader('dublinCoreType' . $i++, '<meta name="DC.Type" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($type)) . '"/>');
            }
        }

        return false;
    }

    /**
     * Inject Dublin Core metadata into monograph file view
     *
     * @param $hookName string
     * @param $args array
     *
     * @return boolean
     */
    public function monographFileView($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $monograph = $args[1];
        $publicationFormat = $args[2];
        $submissionFile = $args[3];
        $press = $request->getContext();

        $templateMgr = TemplateManager::getManager($request);
        $chapter = $templateMgr->getTemplateVars('chapter');
        $series = $templateMgr->getTemplateVars('series');

        $templateMgr->addHeader('dublinCoreSchema', '<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />');

        $i = 0;
        if ($sponsors = $monograph->getSponsor(null)) {
            foreach ($sponsors as $locale => $sponsor) {
                $templateMgr->addHeader('dublinCoreSponsor' . $i++, '<meta name="DC.Contributor.Sponsor" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($sponsor)) . '"/>');
            }
        }

        $i = 0;
        if ($coverages = $monograph->getCoverage(null)) {
            foreach ($coverages as $locale => $coverage) {
                $templateMgr->addHeader('dublinCoreCoverage' . $i++, '<meta name="DC.Coverage" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($coverage)) . '"/>');
            }
        }

        $i = 0;
        foreach ($chapter ? $chapter->getAuthors()->toArray() : $monograph->getAuthors() as $author) {
            $templateMgr->addHeader('dublinCoreAuthor' . $i++, '<meta name="DC.Creator.PersonalName" content="' . htmlspecialchars($author->getFullName(false)) . '"/>');
        }

        if (is_a($monograph, 'Submission') && ($datePublished = $monograph->getDatePublished())) {
            $templateMgr->addHeader('dublinCoreDateCreated', '<meta name="DC.Date.created" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($datePublished)) . '"/>');
        }
        $templateMgr->addHeader('dublinCoreDateSubmitted', '<meta name="DC.Date.dateSubmitted" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($monograph->getDateSubmitted())) . '"/>');
        if ($dateModified = $monograph->getData('dateLastActivity')) {
            $templateMgr->addHeader('dublinCoreDateModified', '<meta name="DC.Date.modified" scheme="ISO8601" content="' . strftime('%Y-%m-%d', strtotime($dateModified)) . '"/>');
        }
        $i = 0;
        if ($abstracts = $monograph->getAbstract(null)) {
            foreach ($abstracts as $locale => $abstract) {
                $templateMgr->addHeader('dublinCoreAbstract' . $i++, '<meta name="DC.Description" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($abstract)) . '"/>');
            }
        }

        $templateMgr->addHeader('dublinCoreIdentifier', '<meta name="DC.Identifier" content="' . htmlspecialchars($monograph->getBestId() . '/' . $publicationFormat->getId() . '/' . $submissionFile->getId()) . '"/>');

        if ($pages = $monograph->getPages()) {
            $templateMgr->addHeader('dublinCorePages', '<meta name="DC.Identifier.pageNumber" content="' . htmlspecialchars($pages) . '"/>');
        }

        foreach ((array) $templateMgr->getTemplateVars('pubIdPlugins') as $pubIdPlugin) {
            if ($pubId = $monograph->getStoredPubId($pubIdPlugin->getPubIdType())) {
                $templateMgr->addHeader('dublinCorePubId' . $pubIdPlugin->getPubIdDisplayType(), '<meta name="DC.Identifier.' . htmlspecialchars($pubIdPlugin->getPubIdDisplayType()) . '" content="' . htmlspecialchars($pubId) . '"/>');
            }
        }

        $templateMgr->addHeader('dublinCoreUri', '<meta name="DC.Identifier.URI" content="' . $request->url(null, 'catalog', 'book', [$monograph->getBestId(), $publicationFormat->getId(), $submissionFile->getId()]) . '"/>');
        $templateMgr->addHeader('dublinCoreLanguage', '<meta name="DC.Language" scheme="ISO639-1" content="' . substr($monograph->getLocale(), 0, 2) . '"/>');
        $templateMgr->addHeader('dublinCoreCopyright', '<meta name="DC.Rights" content="' . htmlspecialchars(__('submission.copyrightStatement', ['copyrightHolder' => $monograph->getCopyrightHolder($monograph->getLocale()), 'copyrightYear' => $monograph->getCopyrightYear()])) . '"/>');
        $templateMgr->addHeader('dublinCorePagesLicenseUrl', '<meta name="DC.Rights" content="' . htmlspecialchars($monograph->getLicenseURL()) . '"/>');
        $templateMgr->addHeader('dublinCoreSource', '<meta name="DC.Source" content="' . htmlspecialchars($press->getName($press->getPrimaryLocale())) . '"/>');
        if ($series && $issn = $series->getOnlineISSN()) {
            $templateMgr->addHeader('dublinCoreIssn', '<meta name="DC.Source.ISSN" content="' . htmlspecialchars($issn) . '"/>');
        }

        $templateMgr->addHeader('dublinCoreSourceUri', '<meta name="DC.Source.URI" content="' . $request->url($press->getPath()) . '"/>');

        $i = 0;
        $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
        $supportedLocales = array_keys(AppLocale::getSupportedFormLocales());
        if ($subjects = $submissionSubjectDao->getSubjects($monograph->getId(), $supportedLocales)) {
            foreach ($subjects as $locale => $subjectLocale) {
                foreach ($subjectLocale as $subject) {
                    $templateMgr->addHeader('dublinCoreSubject' . $i++, '<meta name="DC.Subject" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($subject) . '"/>');
                }
            }
        }

        $i = 0;
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
        if ($keywords = $submissionKeywordDao->getKeywords($monograph->getId(), $supportedLocales)) {
            foreach ($keywords as $locale => $keywordLocale) {
                foreach ($keywordLocale as $keyword) {
                    $templateMgr->addHeader('dublinCoreKeyword' . $i++, '<meta name="DC.Subject" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($keyword) . '"/>');
                }
            }
        }


        if ($chapter) {
            $templateMgr->addHeader('dublinCoreTitle', '<meta name="DC.Title" content="' . htmlspecialchars($chapter->getTitle($monograph->getLocale())) . '"/>');
            $i = 0;
            foreach ($chapter->getTitle(null) as $locale => $title) {
                if ($locale == $monograph->getLocale()) {
                    continue;
                }
                $templateMgr->addHeader('dublinCoreAltTitle' . $i++, '<meta name="DC.Title.Alternative" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($title) . '"/>');
            }
        } else {
            $templateMgr->addHeader('dublinCoreTitle', '<meta name="DC.Title" content="' . htmlspecialchars($monograph->getTitle($monograph->getLocale())) . '"/>');
            $i = 0;
            foreach ($monograph->getTitle(null) as $locale => $title) {
                if ($locale == $monograph->getLocale()) {
                    continue;
                }
                $templateMgr->addHeader('dublinCoreAltTitle' . $i++, '<meta name="DC.Title.Alternative" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars($title) . '"/>');
            }
        }

        $templateMgr->addHeader('dublinCoreType', '<meta name="DC.Type" content="Text.Chapter"/>');
        $i = 0;
        if ($types = $monograph->getType(null)) {
            foreach ($types as $locale => $type) {
                $templateMgr->addHeader('dublinCoreType' . $i++, '<meta name="DC.Type" xml:lang="' . htmlspecialchars(substr($locale, 0, 2)) . '" content="' . htmlspecialchars(strip_tags($type)) . '"/>');
            }
        }

        return false;
    }

    /**
     * Get the display name of this plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.dublinCoreMeta.name');
    }

    /**
     * Get the description of this plugin
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.dublinCoreMeta.description');
    }
}
