<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/


namespace KevinsGuides\Component\Yaquiz\Administrator\Model;

use Exception;
use JFactory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Table\Table;

defined('_JEXEC') or die;

//this is a model for a single quiz



class YaquizModel extends AdminModel
{

    public function __construct($config = [])
    {
        Log::add('YaquizModel::__construct() called', Log::INFO, 'com_yaquiz');
        parent::__construct($config);
    }


    //get the quiz
    public function getQuiz($qid)
    {
        Log::add('try get quiz with qid ' . $qid, Log::INFO, 'com_yaquiz');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__com_yaquiz_quizzes');
        Log::add('trynna open quiz with id: ' . $qid, Log::INFO, 'com_yaquiz');
        $query->where('id = ' . $qid);
        $db->setQuery($query);
        $item = $db->loadObject();
        return $item;
    }




    //get the quiz form
    // this is called by controller, and only happens when layout is edit
    public function getForm($data = [], $loadData = true)
    {

        $app = Factory::getApplication();
        $cParams = ComponentHelper::getParams('com_yaquiz');

        //the default access level
        $access = $cParams->get('access', 1);

        //make sure we have edit permissions
        if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true){
            $app->enqueueMessage('Edit permissions required to change question ordering', 'error');
            return;
        }

        //if layout is edit
        if ($app->input->get('layout') == 'edit') {
            // Get the form.
            $form = $this->loadForm('com_yaquiz.quiz', 'quiz', ['control' => 'jform', 'load_data' => $loadData]);
            if (empty($form)) {
                return false;
            }
            if(!isset($data->id)){
                $data = new \stdClass;
                $data->id = 0;
            }

            //if new quiz
            if ($data->id == 0) {
                //set created_by to current user
                $data->created_by = $app->getIdentity()->id;
                //set created to current time
                $data->created= date('Y-m-d H:i:s');
                //set modified_by to current user
                $data->modified_by = $app->getIdentity()->id;
                //set modified to current time
                $data->modified = date('Y-m-d H:i:s');
                //set checked out to this users id
                $data->checked_out = $app->getIdentity()->id;
                //set checked_out_time to current time
                $data->checked_out_time = date('Y-m-d H:i:s');
                $data->quiz_displaymode = 'default';
                $data->access = $access;
            } else {
                $params = $this->getParams($data->id);
                //get 'quiz_displaymode' from params
                Log::add('params: ' . $params['quiz_displaymode'], Log::INFO, 'com_yaquiz');
                $data->quiz_displaymode = $params['quiz_displaymode'];
                $data->quiz_showfeedback = $params['quiz_showfeedback'];
                $data->quiz_feedback_showcorrect = $params['quiz_feedback_showcorrect'];
                $data->quiz_question_numbering = $params['quiz_question_numbering'];
                $data->quiz_use_points = $params['quiz_use_points'];
                $data->passing_score = $params['passing_score'];
                $data->quiz_record_results = $params['quiz_record_results'];
                $data->quiz_record_guest_results = $params['quiz_record_guest_results'];
                $data->quiz_show_general_stats = $params['quiz_show_general_stats'];
                $data->max_attempts = $params['max_attempts'];
                $this->checkout($data->id);
            }



            $form->bind($data);
            return $form;
        } else {
            Log::add('try to get yaquiz default filter form', Log::INFO, 'com_yaquiz');
            //get the yaquiz form
            $form = $this->loadForm('com_yaquiz.yaquiz', 'yaquiz', ['control' => 'filters', 'load_data' => $loadData]);
            if (empty($form)) {
                return false;
            }
            return $form;



        }




    }

    public function save($data)
    {
        //see if is new quiz or update

        //if new quiz
        if ($data['id'] == 0 || $data['id'] == null) {

            //call insert
            $this->checkin($data['id']);
            return $this->insert($data);
        } else {
            //call update
            $this->checkin($data['id']);
            return $this->update($data);
        }

        //check item in
        
    }


    public function isCheckedOut($item = null)
    {
        if($item == null){
            return false;
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('checked_out');
        $query->from('#__com_yaquiz_quizzes');
        $query->where('id = ' . $item->id);
        $db->setQuery($query);
        $checkedOut = $db->loadResult();
        $userid = $this->getCurrentUser()->id;

        if ($checkedOut == $userid) {
            return false;
        }

        if ($checkedOut != 0) {
            return true;
        }

        return false;
    }

    public function insert($data)
    {
        Log::add('insert called in quizmodel', Log::INFO, 'com_yaquiz');
        //insert quiz
        $db = Factory::getContainer()->get('DatabaseDriver');
        $app = Factory::getApplication();
        $query = $db->getQuery(true);
        $query->insert('#__com_yaquiz_quizzes');
        $data['created_by'] = $app->getIdentity()->id;
        $data['modified_by'] = $app->getIdentity()->id;
        $data['checked_out'] = 0;
        $query->columns('title, description, published, created_by, created, modified_by, modified, params, access, hits, catid');
        $query->values($db->quote($data['title']) . ', ' . $db->quote($data['description']) . ', ' . $db->quote($data['published']) . ', ' . $db->quote($data['created_by']) . ', ' . $db->quote($data['created']) . ', ' . $db->quote($data['modified_by']) . ', ' . $db->quote($data['modified']) . ', ' . $db->quote($this->dataToParams($data)) . ', ' . $db->quote($data['access']) . ', ' . $db->quote($data['hits']) . ', ' . $db->quote($data['catid']));
        $db->setQuery($query);
        $db->execute();
        return $db->insertid();
    }

    public function update($data)
    {
        Log::add('update called in quizmodel', Log::INFO, 'com_yaquiz');
        //update quiz
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__com_yaquiz_quizzes');
        $query->set('title = ' . $db->quote($data['title']));
        $query->set('description = ' . $db->quote($data['description']));
        $query->set('published = ' . $db->quote($data['published']));
        $query->set('modified_by = ' . $app->getIdentity()->id);
        $query->set('modified = CURRENT_TIMESTAMP');
        $query->set('params = ' . $db->quote($this->dataToParams($data)));
        $query->set('catid = ' . $db->quote($data['catid']));
        $query->set('access = ' . $db->quote($data['access']));
        $query->where('id = ' . $data['id']);
        $db->setQuery($query);
        $db->execute();
        return $data['id'];

    }

    public function addQuestionsToQuiz($quizid, $questionids)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //check the __yaquiz_question_quiz_map table for existing questions for this quiz
        $query->select('question_id');
        $query->from('#__com_yaquiz_question_quiz_map');
        $query->where('quiz_id = ' . $quizid);
        $db->setQuery($query);
        $existingQuestions = $db->loadColumn();

        //return if no quiz id or questionids are given
        if ($quizid == null || $questionids == null) {
            return;
        }

        //if there are existing questions
        if (count($existingQuestions) > 0) {
            //loop through the existing questions
            foreach ($existingQuestions as $existingQuestion) {
                //check if this existingQuestion matches any of the questionids
                if (in_array($existingQuestion, $questionids)) {
                    //if it does, remove it from the questionids array
                    $key = array_search($existingQuestion, $questionids);
                    unset($questionids[$key]);
                }
            }
        }

        //if there are no questions to add, return
        if (count($questionids) == 0) {
            return;
        }

        //loop through the questionids
        foreach ($questionids as $questionid) {
            //insert the questionid and quizid into the __yaquiz_question_quiz_map table
            $query = $db->getQuery(true);
            $query->insert('#__com_yaquiz_question_quiz_map');
            $query->columns('quiz_id, question_id');
            $query->values($quizid . ', ' . $questionid);
            $db->setQuery($query);
            $db->execute();
        }

    }

    public function getQuestionsInQuiz($quiz_id)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //get the questions in this quiz
        $query->select('q.id, q.question, q.details, q.answers, q.correct, q.published, q.params, q.catid, qqm.ordering');
        $query->from('#__com_yaquiz_questions as q');
        $query->join('INNER', '#__com_yaquiz_question_quiz_map as qqm ON q.id = qqm.question_id');
        $query->where('qqm.quiz_id = ' . $quiz_id);
        $query->order('qqm.ordering ASC');
        $db->setQuery($query);
        $questions = $db->loadObjectList();

        //if the order is 0, set it to the highest order + 1
        
        foreach ($questions as $question) {
            if ($question->ordering == 0) {
                $highestOrder = $this->getHighestOrder($quiz_id);
                $question->ordering = $highestOrder + 1;
                $this->updateQuestionOrder($quiz_id, $question->id, $question->ordering);
                //move to end of array
                $key = array_search($question, $questions);
                unset($questions[$key]);
                $questions[] = $question;
                
            }
        }



        return $questions;
    }


    //get the highest ordering number
    public function getHighestOrder($quiz_id){

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //get the questions in this quiz
        $query->select('MAX(qqm.ordering)');
        $query->from('#__com_yaquiz_question_quiz_map as qqm');
        $query->where('qqm.quiz_id = ' . $quiz_id);
        $db->setQuery($query);
        $highestOrder = $db->loadResult();

        return $highestOrder;
    }

    public function updateQuestionOrder($quiz_id, $question_id, $newOrder)
    {


        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //update the question order
        $query->update('#__com_yaquiz_question_quiz_map');
        $query->set('ordering = ' . $newOrder);
        $query->where('quiz_id = ' . $quiz_id);
        $query->where('question_id = ' . $question_id);
        $db->setQuery($query);
        $db->execute();

    }

    public function moveQuestionOrderUp($quiz_id, $question_id){

        //user must have edit permissions to do this
        $app = Factory::getApplication();
        if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true){
            $app->enqueueMessage('Edit permissions required to change question ordering', 'error');
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        //get the current order of the question
        $query->select('qqm.ordering');
        $query->from('#__com_yaquiz_question_quiz_map as qqm');
        $query->where('qqm.quiz_id = ' . $quiz_id);
        $query->where('qqm.question_id = ' . $question_id);
        $db->setQuery($query);
        $currentOrder = $db->loadResult();

        //if the current order is 1, return
        if($currentOrder == 1){
            return;
        }

        //get the question with the order one less than the current order
        $query = $db->getQuery(true);
        $query->select('qqm.question_id');
        $query->from('#__com_yaquiz_question_quiz_map as qqm');
        $query->where('qqm.quiz_id = ' . $quiz_id);
        $query->where('qqm.ordering = ' . ($currentOrder - 1));
        $db->setQuery($query);
        $otherQuestionId = $db->loadResult();

        //update the order of the current question
        $query = $db->getQuery(true);
        $query->update('#__com_yaquiz_question_quiz_map');
        $query->set('ordering = ' . ($currentOrder - 1));
        $query->where('quiz_id = ' . $quiz_id);
        $query->where('question_id = ' . $question_id);
        $db->setQuery($query);
        $db->execute();

        //update the order of the other question
        $query = $db->getQuery(true);
        $query->update('#__com_yaquiz_question_quiz_map');
        $query->set('ordering = ' . ($currentOrder));
        $query->where('quiz_id = ' . $quiz_id);
        $query->where('question_id = ' . $otherQuestionId);
        $db->setQuery($query);
        $db->execute();

        //return the new order number
        return $currentOrder - 1;


    }


    public function moveQuestionOrderDown($quiz_id, $question_id){
                    //user must have edit permissions to do this
        $app = Factory::getApplication();
        if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true){
            $app->enqueueMessage('Edit permissions required to change question ordering', 'error');
            return;
        }
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
    
            //get the current order of the question
            $query->select('qqm.ordering');
            $query->from('#__com_yaquiz_question_quiz_map as qqm');
            $query->where('qqm.quiz_id = ' . $quiz_id);
            $query->where('qqm.question_id = ' . $question_id);
            $db->setQuery($query);
            $currentOrder = $db->loadResult();
    
            //get the highest order number
            $highestOrder = $this->getHighestOrder($quiz_id);
    
            //if the current order is the highest order, return
            if($currentOrder == $highestOrder){
                return;
            }
    
            //get the question with the order one more than the current order
            $query = $db->getQuery(true);
            $query->select('qqm.question_id');
            $query->from('#__com_yaquiz_question_quiz_map as qqm');
            $query->where('qqm.quiz_id = ' . $quiz_id);
            $query->where('qqm.ordering = ' . ($currentOrder + 1));
            $db->setQuery($query);
            $otherQuestionId = $db->loadResult();
    
            //update the order of the current question
            $query = $db->getQuery(true);
            $query->update('#__com_yaquiz_question_quiz_map');
            $query->set('ordering = ' . ($currentOrder + 1));
            $query->where('quiz_id = ' . $quiz_id);
            $query->where('question_id = ' . $question_id);
            $db->setQuery($query);
            $db->execute();
    
            //update the order of the other question
            $query = $db->getQuery(true);
            $query->update('#__com_yaquiz_question_quiz_map');
            $query->set('ordering = ' . ($currentOrder));
            $query->where('quiz_id = ' . $quiz_id);
            $query->where('question_id = ' . $otherQuestionId);
            $db->setQuery($query);
            $db->execute();
    
            //return the new order number
            return $currentOrder + 1;
    }

    /**
     * Removes question(s) from a quiz
     * @param $quiz_id int the quiz id
     * @param $question_id int|array the question id or array of question ids
     */
    public function removeQuestionFromQuiz($quiz_id, $question_id)
    {

        //user needs core.edit permissions
        $app = Factory::getApplication();
        if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true){
            $app->enqueueMessage('Edit permissions required to remove questions from quiz', 'error');
            return;
        }

        if(is_array($question_id)){
            foreach($question_id as $id){
                $this->removeQuestionFromQuiz($quiz_id, $id);
            }
            return;
        }
        else{
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            //delete the question from the quiz
            $query->delete('#__com_yaquiz_question_quiz_map');
            $query->where('quiz_id = ' . $quiz_id);
            $query->where('question_id = ' . $question_id);
            $db->setQuery($query);
            $db->execute();
        }


    }



    public function getParams($qid)
    {

        //get params from db
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params');
        $query->from('#__com_yaquiz_quizzes');
        $query->where('id = ' . $qid);
        $db->setQuery($query);
        $params = $db->loadResult();

        //decode params
        $params = json_decode($params);

        //turn params into array
        $params = (array) $params;

        return $params;

    }

    public function setParams($qid, $params)
    {

        //encode params
        $params = json_encode($params);

        //update params in db
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__com_yaquiz_quizzes');
        $query->set('params = ' . $db->quote($params));
        $query->where('id = ' . $qid);
        $db->setQuery($query);
        $db->execute();

    }

    /**
     * Takes data from form and converts it to params json
     */
    public function dataToParams($data)
    {
        $params = array();
        //set params
        $params['quiz_displaymode'] = $data['quiz_displaymode'];
        $params['quiz_showfeedback'] = $data['quiz_showfeedback'];
        $params['quiz_feedback_showcorrect'] = $data['quiz_feedback_showcorrect'];
        $params['quiz_question_numbering'] = $data['quiz_question_numbering'];
        $params['quiz_use_points'] = $data['quiz_use_points'];
        $params['passing_score'] = $data['passing_score'];
        $params['quiz_record_results'] = $data['quiz_record_results'];
        $params['quiz_record_guest_results'] = $data['quiz_record_guest_results'];
        $params['quiz_show_general_stats'] = $data['quiz_show_general_stats'];
        $params['max_attempts'] = $data['max_attempts'];
        //encode
        $params = json_encode($params);
        return $params;
    }

    public function getCategoryName($pk)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('title');
        $query->from('#__categories');
        $query->where('id = ' . $pk);
        $db->setQuery($query);
        $category = $db->loadResult();
        return $category;
    }

    //reorder the questions from 1 to n based on current order
    public function reorderQns($pk){

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('qqm.question_id');
        $query->from('#__com_yaquiz_question_quiz_map as qqm');
        $query->where('qqm.quiz_id = ' . $pk);
        $query->order('qqm.ordering');
        $db->setQuery($query);
        $qns = $db->loadColumn();

        $i = 1;
        foreach($qns as $qn){
            $query = $db->getQuery(true);
            $query->update('#__com_yaquiz_question_quiz_map');
            $query->set('ordering = ' . $i);
            $query->where('quiz_id = ' . $pk);
            $query->where('question_id = ' . $qn);
            $db->setQuery($query);
            $db->execute();
            $i++;
        }
    }

    public function removeAllQuestionsFromQuiz($pk){

        Log::add('removeAllQuestionsFromQuiz called with pk '. $pk, Log::INFO, 'com_yaquiz');
        //user needs core.edit permission
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.edit', 'com_yaquiz')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
        }


        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        //delete the question from the quiz
        $query->delete('#__com_yaquiz_question_quiz_map');
        $query->where('quiz_id = ' . $pk);
        $db->setQuery($query);
        $db->execute();
        return true;
    }

    public function delete(&$pks){
            
            //user needs core.delete permission
            $user = Factory::getApplication()->getIdentity();
            if (!$user->authorise('core.delete', 'com_yaquiz')) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
    
            //remove all questions from quiz
            $this->removeAllQuestionsFromQuiz($pks);

            //remove all references to this quiz id from __com_yaquiz_results_general and __com_yaquiz_results
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query->delete('#__com_yaquiz_results_general');
            $query->where('quiz_id = ' . $pks);
            $db->setQuery($query);
            $db->execute();
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query->delete('#__com_yaquiz_results');
            $query->where('quiz_id = ' . $pks);
            $db->setQuery($query);
            $db->execute();
    
            //delete quiz
            return parent::delete($pks);
    }

    /**
     * Get all quiz results that were saved to db
     * @param $pk int the quiz id
     * @return array the results
     */
    public function getAllSavedResults($pk = 0, $filters = null, $page = 1, $limit = 5){
        if($pk == 0){
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__com_yaquiz_results');
        
        if($filters != null){
            if(isset($filters['filterusername'])){
                //we need to find any matching user ids in #__users
                $query->join('INNER', '#__users as u ON u.id = #__com_yaquiz_results.user_id');
                $query->where('u.username LIKE "%' . $filters['filterusername'] . '%"');

            }
        }
        $query->where('quiz_id = ' . $pk);
        $db->setQuery($query, ($page - 1) * $limit, $limit);
        $results = $db->loadObjectList();
        return $results;

    }


    public function countTotalSavedResults($pk = 0, $filters = null){

        if($pk == 0){
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from('#__com_yaquiz_results');
        if($filters != null){
            if(isset($filters['filterusername'])){
                //we need to find any matching user ids in #__users
                $query->join('INNER', '#__users as u ON u.id = #__com_yaquiz_results.user_id');
                $query->where('u.username LIKE "%' . $filters['filterusername'] . '%"');

            }
        }
        $query->where('quiz_id = ' . $pk);
        $db->setQuery($query);
        $count = $db->loadResult();
        return $count;



    }

    public function getIndividualAttemptResult($pk = 0, $attempt_id = 0){
        if ($pk == 0 || $attempt_id == 0) {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__com_yaquiz_results');
        $query->where('quiz_id = ' . $pk);
        $query->where('id = ' . $attempt_id);
        $db->setQuery($query);
        $result = $db->loadObject();
        return $result;

    }

    //clears all general and individual results saved for a quiz
    public function resetAllStatsAndRecords($quiz_id){
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->delete('#__com_yaquiz_results_general');
        $query->where('quiz_id = ' . $quiz_id);
        $db->setQuery($query);
        $db->execute();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->delete('#__com_yaquiz_results');
        $query->where('quiz_id = ' . $quiz_id);
        $db->setQuery($query);
        $db->execute();

    }


}