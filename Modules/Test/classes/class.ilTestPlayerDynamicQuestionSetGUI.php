<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/Test/classes/class.ilTestPlayerAbstractGUI.php';

/**
 * Output class for assessment test execution
 *
 * The ilTestOutputGUI class creates the output for the ilObjTestGUI
 * class when learners execute a test. This saves some heap space because 
 * the ilObjTestGUI class will be much smaller then
 *
 * @extends ilTestPlayerAbstractGUI
 * 
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 * 
 * @package		Modules/Test
 *
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilAssGenFeedbackPageGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilAssSpecFeedbackPageGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilAssQuestionHintRequestGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilAssQuestionPageGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilTestDynamicQuestionSetStatisticTableGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilToolbarGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilTestSubmissionReviewGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilTestPasswordProtectionGUI
 */
class ilTestPlayerDynamicQuestionSetGUI extends ilTestPlayerAbstractGUI
{
	const CMD_SHOW_QUESTION_SELECTION = 'showQuestionSelection';
	const CMD_SHOW_QUESTION = 'showQuestion';
	const CMD_FROM_PASS_DELETION = 'fromPassDeletion';
		
	/**
	 * @var ilObjTestDynamicQuestionSetConfig
	 */
	private $dynamicQuestionSetConfig = null;

	/**
	 * @var ilTestSequenceDynamicQuestionSet
	 */
	protected $testSequence;

	/**
	 * @var ilTestSessionDynamicQuestionSet
	 */
	protected $testSession;
	
	/**
	 * execute command
	 */
	function executeCommand()
	{
		global $ilDB, $lng, $ilPluginAdmin, $ilTabs, $tree;

		$ilTabs->clearTargets();
		
		$this->ctrl->saveParameter($this, "sequence");
		$this->ctrl->saveParameter($this, "active_id");

		require_once 'Modules/Test/classes/class.ilObjTestDynamicQuestionSetConfig.php';
		$this->dynamicQuestionSetConfig = new ilObjTestDynamicQuestionSetConfig($tree, $ilDB, $ilPluginAdmin, $this->object);
		$this->dynamicQuestionSetConfig->loadFromDb();

		$testSessionFactory = new ilTestSessionFactory($this->object);
		$this->testSession = $testSessionFactory->getSession($_GET['active_id']);

		$this->ensureExistingTestSession($this->testSession);
		$this->initProcessLocker($this->testSession->getActiveId());
		
		$testSequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $ilPluginAdmin, $this->object);
		$this->testSequence = $testSequenceFactory->getSequence($this->testSession);
		$this->testSequence->loadFromDb();

		include_once 'Services/jQuery/classes/class.iljQueryUtil.php';
		iljQueryUtil::initjQuery();
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initConnectionWithAnimation();
		if( $this->object->getKioskMode() )
		{
			include_once 'Services/UIComponent/Overlay/classes/class.ilOverlayGUI.php';
			ilOverlayGUI::initJavascript();
		}
		
		$this->handlePasswordProtectionRedirect();
		
		$cmd = $this->ctrl->getCmd();
		$nextClass = $this->ctrl->getNextClass($this);
		
		switch($nextClass)
		{
			case 'ilassquestionpagegui':

				$questionId = $this->testSequence->getQuestionForSequence( $this->calculateSequence() );

				require_once "./Modules/TestQuestionPool/classes/class.ilAssQuestionPageGUI.php";
				$page_gui = new ilAssQuestionPageGUI($questionId);
				$ret = $this->ctrl->forwardCommand($page_gui);
				break;

			case 'ilassquestionhintrequestgui':
				
				$questionGUI = $this->object->createQuestionGUI(
					"", $this->testSequenceFactory->getSequence()->getQuestionForSequence( $this->calculateSequence() )
				);

				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
				$gui = new ilAssQuestionHintRequestGUI($this, self::CMD_SHOW_QUESTION, $this->testSession, $questionGUI);
				
				$this->ctrl->forwardCommand($gui);
				
				break;
				
			case 'ildynamicquestionsetstatistictablegui':
				
				$this->ctrl->forwardCommand( $this->buildQuestionSetFilteredStatisticTableGUI() );
				
				break;

			case 'iltestpasswordprotectiongui':
				require_once 'Modules/Test/classes/class.ilTestPasswordProtectionGUI.php';
				$gui = new ilTestPasswordProtectionGUI($this->ctrl, $this->tpl, $this->lng, $this, $this->passwordChecker);
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			
			default:
				
				$cmd .= 'Cmd';
				$ret =& $this->$cmd();
				break;
		}
		
		return $ret;
	}

	/**
	 * Resume a test at the last position
	 */
	protected function resumePlayerCmd()
	{
		if ($this->object->checkMaximumAllowedUsers() == FALSE)
		{
			return $this->showMaximumAllowedUsersReachedMessage();
		}
		
		$this->handleUserSettings();
		
		if( $this->dynamicQuestionSetConfig->isAnyQuestionFilterEnabled() )
		{
			$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION_SELECTION);
		}
		
		$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION);
	}
	
	protected function startTestCmd()
	{
		$this->testSession->setCurrentQuestionId(null); // no question "came up" yet
		
		$this->testSession->saveToDb();
		
		$this->ctrl->setParameter($this, 'active_id', $this->testSession->getActiveId());

		assQuestion::_updateTestPassResults($this->testSession->getActiveId(), $this->testSession->getPass(), false, null, $this->object->id);

		$_SESSION['active_time_id'] = $this->object->startWorkingTime(
				$this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		$this->ctrl->saveParameter($this, 'tst_javascript');
		
		if( $this->dynamicQuestionSetConfig->isAnyQuestionFilterEnabled() )
		{
			$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION_SELECTION);
		}
		
		$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION);
	}
	
	protected function showQuestionSelectionCmd()
	{
		$this->prepareSummaryPage();
		
		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getQuestionSetFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);

		$this->testSequence->saveToDb();
			
		require_once 'Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php';
		$toolbarGUI = new ilToolbarGUI();
		
		$toolbarGUI->addButton(
			$this->getEnterTestButtonLangVar(), $this->ctrl->getLinkTarget($this, self::CMD_SHOW_QUESTION),
			'', '', '', '', 'submit emphsubmit'
		);
		
		if( $this->object->isPassDeletionAllowed() )
		{
			require_once 'Modules/Test/classes/confirmations/class.ilTestPassDeletionConfirmationGUI.php';
			
			$toolbarGUI->addButton(
				$this->lng->txt('tst_dyn_test_pass_deletion_button'),
				$this->getPassDeletionTarget(ilTestPassDeletionConfirmationGUI::CONTEXT_DYN_TEST_PLAYER)
			);
		}
		
		$filteredData = array($this->buildQuestionSetAnswerStatisticRowArray(
			$this->testSequence->getFilteredQuestionsData(), $this->getMarkedQuestions()
		)); #vd($filteredData);
		$filteredTableGUI = $this->buildQuestionSetFilteredStatisticTableGUI();
		$filteredTableGUI->setData($filteredData);

		$completeData = array($this->buildQuestionSetAnswerStatisticRowArray(
			$this->testSequence->getCompleteQuestionsData(), $this->getMarkedQuestions()
		)); #vd($completeData);
		$completeTableGUI = $this->buildQuestionSetCompleteStatisticTableGUI();
		$completeTableGUI->setData($completeData);

		$content = $this->ctrl->getHTML($toolbarGUI);
		$content .= $this->ctrl->getHTML($filteredTableGUI);
		$content .= $this->ctrl->getHTML($completeTableGUI);

		$this->tpl->setVariable('TABLE_LIST_OF_QUESTIONS', $content);	

		if( $this->object->getEnableProcessingTime() )
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}
	}
	
	protected function filterQuestionSelectionCmd()
	{
		$tableGUI = $this->buildQuestionSetFilteredStatisticTableGUI();
		$tableGUI->writeFilterToSession();

		$taxFilterSelection = array();
		$answerStatusFilterSelection = ilAssQuestionList::ANSWER_STATUS_FILTER_ALL_NON_CORRECT;
		
		foreach( $tableGUI->getFilterItems() as $item )
		{
			if( strpos($item->getPostVar(), 'tax_') !== false )
			{
				$taxId = substr( $item->getPostVar(), strlen('tax_') );
				$taxFilterSelection[$taxId] = $item->getValue();
			}
			elseif( $item->getPostVar() == 'question_answer_status' )
			{
				$answerStatusFilterSelection = $item->getValue();
			}
		}
		
		$this->testSession->getQuestionSetFilterSelection()->setTaxonomySelection($taxFilterSelection);
		$this->testSession->getQuestionSetFilterSelection()->setAnswerStatusSelection($answerStatusFilterSelection);
		$this->testSession->saveToDb();
		
		$this->testSequence->resetTrackedQuestionList();
		$this->testSequence->saveToDb();
		
		$this->ctrl->redirect($this, 'showQuestionSelection');
	}
	
	protected function resetQuestionSelectionCmd()
	{
		$tableGUI = $this->buildQuestionSetFilteredStatisticTableGUI();
		$tableGUI->resetFilter();
		
		$this->testSession->getQuestionSetFilterSelection()->setTaxonomySelection( array() );
		$this->testSession->getQuestionSetFilterSelection()->setAnswerStatusSelection( null );
		$this->testSession->saveToDb();
		
		$this->testSequence->resetTrackedQuestionList();
		$this->testSequence->saveToDb();
		
		$this->ctrl->redirect($this, 'showQuestionSelection');
	}

	protected function showTrackedQuestionListCmd()
	{
		if( !$this->dynamicQuestionSetConfig->isPreviousQuestionsListEnabled() )
		{
			$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION);
		}
		
		$this->prepareSummaryPage();

		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getQuestionSetFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		$this->testSequence->saveToDb();
		
		$data = $this->buildQuestionsTableDataArray(
			$this->testSequence->getTrackedQuestionList( $this->testSession->getCurrentQuestionId() ),
			$this->getMarkedQuestions()
		);
		
		include_once "./Modules/Test/classes/tables/class.ilTrackedQuestionsTableGUI.php";
		$table_gui = new ilTrackedQuestionsTableGUI(
				$this, 'showTrackedQuestionList', $this->object->getSequenceSettings(), $this->object->getShowMarker()
		);
		
		$table_gui->setData($data);

		$this->tpl->setVariable('TABLE_LIST_OF_QUESTIONS', $table_gui->getHTML());	

		if( $this->object->getEnableProcessingTime() )
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}
	}

	protected function previousQuestionCmd()
	{
		
	}

	protected function fromPassDeletionCmd()
	{
		$this->resetCurrentQuestion();
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function nextQuestionCmd()
	{
		$questionId = $this->testSession->getCurrentQuestionId();

		$this->resetCurrentQuestion();
		
		$this->ctrl->redirect($this, ilTestPlayerCommands::SHOW_QUESTION);
	}
	
	protected function markQuestionCmd()
	{
		$questionId = $this->testSession->getCurrentQuestionId();

		global $ilUser;
		$this->object->setQuestionSetSolved(1, $this->testSession->getCurrentQuestionId(), $ilUser->getId());
		
		$this->ctrl->redirect($this, ilTestPlayerCommands::SHOW_QUESTION);
	}

	protected function unmarkQuestionCmd()
	{
		global $ilUser;
		$this->object->setQuestionSetSolved(0, $this->testSession->getCurrentQuestionId(), $ilUser->getId());
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function gotoQuestionCmd()
	{
		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getQuestionSetFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		if( isset($_GET['sequence']) && (int)$_GET['sequence'] )
		{
			$this->testSession->setCurrentQuestionId( (int)$_GET['sequence'] );
			$this->testSession->saveToDb();
			
			$this->ctrl->setParameter(
					$this, 'sequence', $this->testSession->getCurrentQuestionId()
			);
		}
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function editSolutionCmd()
	{
		
	}
	
	protected function submitSolutionCmd()
	{
		
	}

	protected function discardSolutionCmd()
	{

	}
	
	protected function showQuestionCmd()
	{
		$_SESSION["active_time_id"] = $this->object->startWorkingTime(
			$this->testSession->getActiveId(), $this->testSession->getPass()
		);

		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getQuestionSetFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		if( !$this->testSession->getCurrentQuestionId() )
		{
			$upComingQuestionId = $this->testSequence->getUpcomingQuestionId();
			
			$this->testSession->setCurrentQuestionId($upComingQuestionId);
			
			if( $this->testSequence->isQuestionChecked($upComingQuestionId) )
			{
				$this->testSequence->setQuestionUnchecked($upComingQuestionId);
			}
		}

		if( $this->testSession->getCurrentQuestionId() )
		{
			$questionGui = $this->getQuestionGuiInstance($this->testSession->getCurrentQuestionId());

			$questionGui->setQuestionCount(
				$this->testSequence->getLastPositionIndex()
			);
			$questionGui->setSequenceNumber(
				$this->testSequence->getCurrentPositionIndex($this->testSession->getCurrentQuestionId())
			);

			if( !($questionGui instanceof assQuestionGUI) )
			{
				$this->handleTearsAndAngerQuestionIsNull(
					$this->testSession->getCurrentQuestionId(), $this->testSession->getCurrentQuestionId()
				);
			}

			$presentationMode = $this->determinePresentationMode($questionGui->object);

			$instantResponse = $this->getInstantResponseParameter();

			$this->prepareTestPage($presentationMode,
				$this->testSession->getCurrentQuestionId(), $this->testSession->getCurrentQuestionId()
			);

			$this->ctrl->setParameter($this, 'sequence', $this->testSession->getCurrentQuestionId());
			$this->ctrl->setParameter($this, 'pmode', $presentationMode);
			$formAction = $this->ctrl->getFormAction($this);
			
			switch($presentationMode)
			{
				case ilTestPlayerAbstractGUI::PRESENTATION_MODE_EDIT:

					$this->showQuestionEditable($questionGui, $instantResponse, $formAction);
					break;

				case ilTestPlayerAbstractGUI::PRESENTATION_MODE_VIEW:

					$this->showQuestionViewable($questionGui, $formAction);
					break;

				default:

					echo "pmode missing:";
					vd($this->testSession->getLastPresentationMode());
					vd($this->testSession->getLastSequence());
					vd($this->testSession->getCurrentQuestionId());
					exit;
			}

			if ($instantResponse)
			{
				$this->populateInstantResponseBlocks(
					$questionGui, $presentationMode == ilTestPlayerAbstractGUI::PRESENTATION_MODE_VIEW
				);
			}

			$this->populateQuestionNavigation(
				$this->testSession->getCurrentQuestionId(),
				$presentationMode == ilTestPlayerAbstractGUI::PRESENTATION_MODE_EDIT
			);

			if( $this->dynamicQuestionSetConfig->isAnyQuestionFilterEnabled() )
			{
				$this->populateQuestionSelectionButtons();
			}
		}
		else
		{
			$this->outCurrentlyFinishedPage();
		}
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
	}
	
	protected function showInstantResponseCmd()
	{
		$questionId = $this->testSession->getCurrentQuestionId();

		if( $questionId && !$this->isParticipantsAnswerFixed($questionId) )
		{
			$this->updateWorkingTime();
			$this->saveQuestionSolution(false);
			$this->persistQuestionAnswerStatus();

			$this->testSequence->unsetQuestionPostponed($questionId);
			$this->testSequence->setQuestionChecked($questionId);
		}

		$filterSelection = $this->testSession->getQuestionSetFilterSelection();
		
		$filterSelection->setForcedQuestionIds(array($this->testSession->getCurrentQuestionId()));
		
		$this->testSequence->loadQuestions($this->dynamicQuestionSetConfig, $filterSelection);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		$this->ctrl->setParameter(
				$this, 'sequence', $this->testSession->getCurrentQuestionId()
		);

		$this->outTestPage(true);
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
	}
	
	protected function handleQuestionActionCmd()
	{
		$questionId = $this->testSession->getCurrentQuestionId();

		if( $questionId && !$this->isParticipantsAnswerFixed($questionId) )
		{
			$this->updateWorkingTime();
			$this->saveQuestionSolution(false);
			$this->persistQuestionAnswerStatus();

			$this->testSequence->unsetQuestionPostponed($questionId);
			$this->testSequence->saveToDb();
		}

		$this->ctrl->setParameter(
				$this, 'sequence', $this->testSession->getCurrentQuestionId()
		);
		
		$this->ctrl->redirect($this, 'showQuestion');
	}

	private function outCurrentlyFinishedPage()
	{
		$this->initTestPageTemplate();

		if( !$this->isFirstQuestionInSequence($this->testSession->getCurrentQuestionId()) )
		{
			$this->populatePreviousButtons();
		}
			
		if ($this->object->getKioskMode())
		{
			$this->populateKioskHead();
		}

		if ($this->object->getEnableProcessingTime())
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}

		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("FORM_TIMESTAMP", time());
		
		$this->tpl->setVariable("PAGETITLE", "- " . $this->object->getTitle());
		
		if ($this->object->isShowExamIdInTestPassEnabled() && !$this->object->getKioskMode())
		{
			$this->tpl->setCurrentBlock('exam_id');
			$this->tpl->setVariable('EXAM_ID', ilObjTest::lookupExamId(
				$this->testSession->getActiveId(), $this->testSession->getPass(), $this->object->getId()
			));
			$this->tpl->setVariable('EXAM_ID_TXT', $this->lng->txt('exam_id'));
			$this->tpl->parseCurrentBlock();
		}
		
		if ($this->object->getShowCancel()) 
		{
			$this->populateCancelButtonBlock();
		}
		
		if( $this->dynamicQuestionSetConfig->isAnyQuestionFilterEnabled() )
		{
			$this->populateQuestionSelectionButtons();
		}
		
		if( $this->testSequence->openQuestionExists() )
		{
			$message = $this->lng->txt('tst_dyn_test_msg_currently_finished_selection');
		}
		else
		{
			$message = $this->lng->txt('tst_dyn_test_msg_currently_finished_completely');
			$message .= "<br /><br />{$this->buildFinishPagePassDeletionLink()}";
		}
		
		$msgHtml = $this->tpl->getMessageHTML($message);
		
		$this->tpl->addBlockFile(
				'QUESTION_OUTPUT', 'test_currently_finished_msg_block',
				'tpl.test_currently_finished_msg.html', 'Modules/Test'
		);
		
		$this->tpl->setCurrentBlock('test_currently_finished_msg_block');
		$this->tpl->setVariable('TEST_CURRENTLY_FINISHED_MSG', $msgHtml);
		$this->tpl->parseCurrentBlock();

	}
	
	protected function isFirstQuestionInSequence($sequenceElement)
	{
		return !$this->testSequence->trackedQuestionExists();
	}

	protected function isLastQuestionInSequence($sequenceElement)
	{
		return false; // always
	}
	
	/**
	 * Returns TRUE if the answers of the current user could be saved
	 *
	 * @return boolean TRUE if the answers could be saved, FALSE otherwise
	 */
	 protected function canSaveResult() 
	 {
		 return !$this->object->endingTimeReached();
	 }
	 
	/**
	 * saves the user input of a question
	 */
	public function saveQuestionSolution($authorized = true, $force = false)
	{
		// what is this formtimestamp ??
		if (!$force)
		{
			$formtimestamp = $_POST["formtimestamp"];
			if (strlen($formtimestamp) == 0) $formtimestamp = $_GET["formtimestamp"];
			if ($formtimestamp != $_SESSION["formtimestamp"])
			{
				$_SESSION["formtimestamp"] = $formtimestamp;
			}
			else
			{
				return FALSE;
			}
		}
		
		// determine current question
		
		$qId = $this->testSession->getCurrentQuestionId();
		
		if( !$qId || $qId != $_GET["sequence"])
		{
			return false;
		}
		
		// save question solution
		
		$this->saveResult = FALSE;

		if ($this->canSaveResult($qId) || $force)
		{
				$questionGUI = $this->object->createQuestionGUI("", $qId);
				
				if( $this->object->getJavaScriptOutput() )
				{
					$questionGUI->object->setOutputType(OUTPUT_JAVASCRIPT);
				}
				
				$activeId = $this->testSession->getActiveId();
				
				$this->saveResult = $questionGUI->object->persistWorkingState(
						$activeId, $pass = null, $this->object->areObligationsEnabled(), $authorized
				);
		}
		
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "save_error", "1");
			$_SESSION["previouspost"] = $_POST;
		}
		
		return $this->saveResult;
	}
	
	private function isQuestionAnsweredCorrect($questionId, $activeId, $pass)
	{
		$questionGUI = $this->object->createQuestionGUI("", $questionId);

		$reachedPoints = assQuestion::_getReachedPoints($activeId, $questionId, $pass);
		$maxPoints = $questionGUI->object->getMaximumPoints();
		
		if($reachedPoints < $maxPoints)
		{
			return false;
		}
		
		return true;
	}


	protected function populatePreviousButtons()
	{
		if( !$this->dynamicQuestionSetConfig->isPreviousQuestionsListEnabled() )
		{
			return;
		}
		
		$this->populateUpperPreviousButtonBlock(
				'showTrackedQuestionList', "&lt;&lt; " . $this->lng->txt( "save_previous" )
		);
		$this->populateLowerPreviousButtonBlock(
				'showTrackedQuestionList', "&lt;&lt; " . $this->lng->txt( "save_previous" )
		);
	}
	
	protected function buildQuestionsTableDataArray($questions, $marked_questions)
	{
		$data = array();
		
		foreach($questions as $key => $value )
		{
			$this->ctrl->setParameter($this, 'sequence', $value['question_id']);
			$href = $this->ctrl->getLinkTarget($this, 'gotoQuestion');
			$this->ctrl->setParameter($this, 'sequence', '');
			
			$description = "";
			if( $this->object->getListOfQuestionsDescription() )
			{
				$description = $value["description"];
			}
			
			$marked = false;
			if( count($marked_questions) )
			{
				if( isset($marked_questions[$value["question_id"]]) )
				{
					if( $marked_questions[$value["question_id"]]["solved"] == 1 )
					{
						$marked = true;
					}
				} 
			}
			
			array_push($data, array(
				'href' => $href,
				'title' => $this->object->getQuestionTitle($value["title"]),
				'description' => $description,
				'worked_through' => $this->testSequence->isAnsweredQuestion($value["question_id"]),
				'postponed' => $this->testSequence->isPostponedQuestion($value["question_id"]),
				'marked' => $marked
			));
		}
		
		return $data;
	}

	protected function buildQuestionSetAnswerStatisticRowArray($questions, $marked_questions)
	{
		$questionAnswerStats = array(
			'total_all' => count($questions),
			'total_open' => 0,
			'non_answered' => 0,
			'wrong_answered' => 0,
			'correct_answered' => 0,
			'postponed' => 0,
			'marked' => 0
		);

		foreach($questions as $key => $value )
		{
			switch( $value['question_answer_status'] )
			{
				case ilAssQuestionList::QUESTION_ANSWER_STATUS_NON_ANSWERED:
					$questionAnswerStats['non_answered']++;
					$questionAnswerStats['total_open']++;
					break;
				case ilAssQuestionList::QUESTION_ANSWER_STATUS_WRONG_ANSWERED:
					$questionAnswerStats['wrong_answered']++;
					$questionAnswerStats['total_open']++;
					break;
				case ilAssQuestionList::QUESTION_ANSWER_STATUS_CORRECT_ANSWERED:
					$questionAnswerStats['correct_answered']++;
					break;
			}

			if( $this->testSequence->isPostponedQuestion($value["question_id"]) )
			{
				$questionAnswerStats['postponed']++;
			}

			if( isset($marked_questions[$value["question_id"]]) )
			{
				if( $marked_questions[$value["question_id"]]["solved"] == 1 )
				{
					$questionAnswerStats['marked']++;
				}
			}
		}

		return $questionAnswerStats;
	}

	private function buildQuestionSetCompleteStatisticTableGUI()
	{
		require_once 'Modules/Test/classes/tables/class.ilTestDynamicQuestionSetStatisticTableGUI.php';
		$gui = $this->buildQuestionSetStatisticTableGUI(
			ilTestDynamicQuestionSetStatisticTableGUI::COMPLETE_TABLE_ID
		);

		$gui->initTitle('tst_dynamic_question_set_complete');
		$gui->initColumns('tst_num_all_questions');

		return $gui;
	}
	
	private function buildQuestionSetFilteredStatisticTableGUI()
	{
		require_once 'Modules/Test/classes/tables/class.ilTestDynamicQuestionSetStatisticTableGUI.php';
		$gui = $this->buildQuestionSetStatisticTableGUI(
			ilTestDynamicQuestionSetStatisticTableGUI::FILTERED_TABLE_ID
		);

		$gui->initTitle('tst_dynamic_question_set_selection');
		$gui->initColumns('tst_num_selected_questions');

		require_once 'Services/Taxonomy/classes/class.ilObjTaxonomy.php';
		$gui->setTaxIds(ilObjTaxonomy::getUsageOfObject(
			$this->dynamicQuestionSetConfig->getSourceQuestionPoolId()
		));

		$gui->setTaxonomyFilterEnabled($this->dynamicQuestionSetConfig->isTaxonomyFilterEnabled());
		$gui->setAnswerStatusFilterEnabled($this->dynamicQuestionSetConfig->isAnswerStatusFilterEnabled());

		$gui->initFilter();
		$gui->setFilterCommand('filterQuestionSelection');
		$gui->setResetCommand('resetQuestionSelection');
		
		return $gui;
	}
		
	private function buildQuestionSetStatisticTableGUI($tableId)
	{
		require_once 'Modules/Test/classes/tables/class.ilTestDynamicQuestionSetStatisticTableGUI.php';
		$gui = new ilTestDynamicQuestionSetStatisticTableGUI(
				$this->ctrl, $this->lng, $this, 'showQuestionSelection', $tableId
		);
		
		$gui->setShowNumMarkedQuestionsEnabled($this->object->getShowMarker());
		$gui->setShowNumPostponedQuestionsEnabled($this->object->getSequenceSettings());

		return $gui;
	}
	
	private function getEnterTestButtonLangVar()
	{
		if( $this->testSequence->trackedQuestionExists() )
		{
			return $this->lng->txt('tst_resume_dyn_test_with_cur_quest_sel');
		}
		
		return $this->lng->txt('tst_start_dyn_test_with_cur_quest_sel');
	}

	protected function persistQuestionAnswerStatus()
	{
		$questionId = $this->testSession->getCurrentQuestionId();
		$activeId = $this->testSession->getActiveId();
		$pass = $this->testSession->getPass();

		if($this->isQuestionAnsweredCorrect($questionId, $activeId, $pass))
		{
			$this->testSequence->setQuestionAnsweredCorrect($questionId);
		}
		else
		{
			$this->testSequence->setQuestionAnsweredWrong($questionId);
		}
	}

	private function resetCurrentQuestion()
	{
		$this->testSession->setCurrentQuestionId(null);

		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();

		$this->ctrl->setParameter($this, 'sequence', $this->testSession->getCurrentQuestionId());
	}

	/**
	 * @return string
	 */
	private function buildFinishPagePassDeletionLink()
	{
		$href = $this->getPassDeletionTarget();

		$label = $this->lng->txt('tst_dyn_test_msg_pass_deletion_link');

		return "<a href=\"{$href}\">{$label}</a>";
	}

	/**
	 * @return string
	 */
	private function getPassDeletionTarget()
	{
		require_once 'Modules/Test/classes/confirmations/class.ilTestPassDeletionConfirmationGUI.php';
		
		$this->ctrl->setParameterByClass('ilTestEvaluationGUI', 'context', ilTestPassDeletionConfirmationGUI::CONTEXT_DYN_TEST_PLAYER);
		$this->ctrl->setParameterByClass('ilTestEvaluationGUI', 'active_id', $this->testSession->getActiveId());
		$this->ctrl->setParameterByClass('ilTestEvaluationGUI', 'pass', $this->testSession->getPass());

		return $this->ctrl->getLinkTargetByClass('ilTestEvaluationGUI', 'confirmDeletePass');
	}
}
