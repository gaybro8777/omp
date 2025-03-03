<?php

/**
 * @file controllers/grid/representations/PublicationFormatCategoryGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProofFilesGridDataProvider
 * @ingroup controllers_grid_files_final
 *
 * @brief Provide access to proof files management.
 */

use \PKP\submission\SubmissionFile;

import('lib.pkp.controllers.grid.files.SubmissionFilesCategoryGridDataProvider');

class PublicationFormatCategoryGridDataProvider extends SubmissionFilesCategoryGridDataProvider
{
    /** @var PublicationFormatGridHandler this data provider is used in */
    public $_gridHandler;

    /**
     * Constructor
     */
    public function __construct($gridHandler)
    {
        $this->_gridHandler = $gridHandler;
        import('lib.pkp.classes.submission.SubmissionFile');
        parent::__construct(SubmissionFile::SUBMISSION_FILE_PROOF);
        $this->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
    }


    //
    // Getters/setters
    //
    /**
     * Get the representation associated with this grid
     *
     * @return Representation
     */
    public function getRepresentation()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION);
    }

    /**
     * Get the submission associated with this grid
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the publication associated with this grid
     *
     * @return Publication
     */
    public function getPublication()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $representation = $this->getRepresentation();
        return array_merge(
            parent::getRequestArgs(),
            [
                'representationId' => $representation->getId(),
                'publicationId' => $this->getPublication()->getId(),
                'assocType' => ASSOC_TYPE_REPRESENTATION,
                'assocId' => $representation->getId(),
            ]
        );
    }

    /**
     * @copydoc GridHandler::loadData
     */
    public function loadData($filter = [])
    {
        return Application::getRepresentationDAO()
            ->getByPublicationId($this->getPublication()->getId())
            ->toAssociativeArray();
    }

    /**
     * @copydoc GridDataProvider::loadCategoryData()
     *
     * @param null|mixed $filter
     * @param null|mixed $reviewRound
     */
    public function loadCategoryData($request, $categoryDataElement, $filter = null, $reviewRound = null)
    {
        assert(is_a($categoryDataElement, 'Representation'));

        // Retrieve all submission files for the given file stage.
        /** @var Representation $categoryDataElement */
        assert(is_a($categoryDataElement, 'Representation'));

        $submissionFiles = Services::get('submissionFile')->getMany([
            'submissionIds' => [$this->getPublication()->getData('submissionId')],
            'assocTypes' => [ASSOC_TYPE_REPRESENTATION],
            'assocIds' => [$categoryDataElement->getId()],
            'fileStages' => [$this->getFileStage()],
        ]);

        // if it is a remotely hosted content, don't provide the files rows
        $remoteURL = $categoryDataElement->getRemoteURL();
        if ($remoteURL) {
            $this->_gridHandler->setEmptyCategoryRowText('grid.remotelyHostedItem');
            return [];
        }
        $this->_gridHandler->setEmptyCategoryRowText('grid.noItems');
        return $this->getDataProvider()->prepareSubmissionFileData($submissionFiles, false, $filter);
    }
}
