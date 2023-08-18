<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/

namespace KevinsGuides\Component\Yaquiz\Site\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Language\Text;

class QuizModel extends ItemModel{

    public function __construct($config = array(), MVCFactoryInterface $factory = null)
    {
        parent::__construct($config, $factory);
    }

    protected function populateState()
    {
        $app = Factory::getApplication();
        $pk = $app->input->get('id');
        $this->setState('quiz.id', $pk);

    }

	/**
	 * Method to get an item.
	 *
	 * @param int|null $pk The id of the item
	 * @return object
	 */
	public function getItem($pk = null) {

        $app = Factory::getApplication();
        if($pk == null){
            $pk = $app->input->getInt('id');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__com_yaquiz_quizzes'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $quiz = $db->loadObject();
        return $quiz;
	}

    /**
     * @param $pk int the id of the quiz
     * @return object the params object
     */
    //TODO: Switch to using Registry for params
    public function getQuizParams($pk = null){


        $app = Factory::getApplication();
        if($pk == null){
            $pk = $app->input->getInt('id');
        } 

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params');
        $query->from($db->quoteName('#__com_yaquiz_quizzes'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $params = $db->loadResult();
        $params = json_decode($params);
        
        if(!isset($params->quiz_use_timer)){
            $params->quiz_use_timer = 0;
        }
        return $params;



    }



    /**
     * Gets all question * data for a quiz in the correct order
     * @param $pk int the id of the quiz
     * @return array of question objects
     */
    public function getQuestions($pk = null)
    {

        $app = Factory::getApplication();
        if($pk == null){
            $pk = $app->input->getInt('id');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->join('INNER', $db->quoteName('#__com_yaquiz_question_quiz_map') . ' ON (' . $db->quoteName('#__com_yaquiz_questions.id') . ' = ' . $db->quoteName('#__com_yaquiz_question_quiz_map.question_id') . ')');
        $query->where($db->quoteName('#__com_yaquiz_question_quiz_map.quiz_id') . ' = ' . $db->quote($pk));
        $query->order('ordering ASC');
        $db->setQuery($query);
        $questions = $db->loadObjectList();
        //decode params
        foreach($questions as $question){
            $question->params = json_decode($question->params);
            $question->id = $question->question_id;
        }

        return $questions;
    }


 

    public function getQuestionParams($question_id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $question_params = $db->loadObject();
        //decode
        $question_params = json_decode($question_params->params);
        return $question_params;
    }

    public function getQuestion($question_id)
    {

     
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $question = $db->loadObject();
        $question->params = json_decode($question->params);
        $question->correct_answer= $this->getCorrectAnswerText($question);
        return $question;
    }

    public static function getQuestionNumbering($question_id, $quiz_id){

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('numbering');
        $query->from($db->quoteName('#__com_yaquiz_question_quiz_map'));
        $query->where($db->quoteName('question_id') . ' = ' . $db->quote($question_id));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));

        $db->setQuery($query);
        $numbering = $db->loadResult();
        return $numbering;

    }

    public function getQuestionFromQuizOrdering($quiz_id, $order){
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('question_id');
        $query->from($db->quoteName('#__com_yaquiz_question_quiz_map'));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $query->where($db->quoteName('ordering') . ' = ' . $db->quote($order));
        $db->setQuery($query);
        $question_id = $db->loadResult();
        return $this->getQuestion($question_id);
    }

    /**
     * @param $question_id int the id of the question
     * @param $answer string the (form value) answer submitted by the user
     */
    public function checkAnswer($question_id, $answer)
    {

        //if question was unanswered, it is incorrect
        if($answer == ''){
            return 0;
        }

        $app = Factory::getApplication();
        $gConfig = $app->getParams('com_yaquiz');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('correct, answers');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $question = $db->loadObject();
        

        $params = $this->getQuestionParams($question_id);
        $type = $params->question_type;
        if ($type === 'multiple_choice') {
            $correct_answer = $question->correct;
            $answer = (int)$answer;
            if ($answer == $correct_answer) {
                return 1;
            } else {
                return 0;
            }
        }
        else if ($type ==='true_false'){
            $correct_answer = $question->correct;
            $answer = (int)$answer;
            if ($answer == $correct_answer) {
                return 1;
            } else {
                return 0;
            }
        }
        else if ($type==='fill_blank'){
            $possibleCorrectAnswers = json_decode($question->answers);
            $caseSensitive = $params->case_sensitive;
            $ignore_trailing = $gConfig->get('shortans_ignore_trailing', "1");
            if($ignore_trailing){
                $answer = rtrim($answer);
            }


            if($caseSensitive){
                if(in_array($answer, $possibleCorrectAnswers)){
                    return 1;
                }
                else{
                    return 0;
                }
            }
            else{
                $answer = strtolower($answer);
                $possibleCorrectAnswers = array_map('strtolower', $possibleCorrectAnswers);
                if(in_array($answer, $possibleCorrectAnswers)){
                    return 1;
                }
                else{
                    return 0;
                }
            }
            
        }
        else{
            return 0;
        }
    }

    public function getPossibleAnswers($question_id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('answers');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $possible_answers = $db->loadObject();
        $possible_answers = json_decode($possible_answers->answers);
        return $possible_answers;
    }


    /**
     * @param $question_id int the id of the question
     * @return string|null the text of the correct answer if mchoice, all possible answers if fillinblank
    */
    public function getCorrectAnswerText($question)
    {
        $question_type = $question->params->question_type;
        if ($question_type === 'multiple_choice'){
            $possible_answers = $this->getPossibleAnswers($question->id);
            $correct_answer = $question->correct;
            $correct_answer_text = $possible_answers[$correct_answer];
            return Text::sprintf('COM_YAQ_S_WAS_THE_CORRECT_ANS', $correct_answer_text);
        }
        if ($question_type === 'true_false'){
            if($question->correct === '1'){
                return Text::_('COM_YAQ_TF_CORRECT_ANS_WAS_TRUE');
            }
            else{
                return Text::_('COM_YAQ_TF_CORRECT_ANS_WAS_FALSE');
            }
        }
        if ($question_type === 'fill_blank'){
            $possible_answers = json_decode($question->answers);
            $answerList = '';
            foreach($possible_answers as $answer){
                $answerList .= '<li>' . $answer . '</li>';
            }
            $answerList = '<ul>' . $answerList . '</ul>';
            return Text::_('COM_YAQ_FILLBLANK_ANYCORRECT').$answerList;
        }

        return null;
    }


    /**
     * @param $question_id int the id of the question
     * @param $useranswer string the user's answer
     * @return string|null the text of the selected answer if mchoice, the user's answer if fillinblank
    */
    public function getSelectedAnswerText($question_id, $useranswer){
        $question_type = $this->getQuestionParams($question_id)->question_type;
        if ($question_type === 'multiple_choice'){
            $possible_answers = $this->getPossibleAnswers($question_id);
            $selected_answer_text = $possible_answers[$useranswer];
            return $selected_answer_text;
        }
        if ($question_type === 'true_false'){
            if($useranswer === '1'){
                return 'True';
            }
            else{
                return 'False';
            }
        }
        if ($question_type === 'fill_blank'){
            return $useranswer;
        }
        return null;
    }


    /**
     * @param $pk int the id of the quiz
     * @return int the number of questions in the quiz
     */
    public function getTotalQuestions($pk = null){
        if ($pk === null) {
            $active = Factory::getApplication()->getMenu()->getActive();
            //get params from the menu item
            $pk = $active->getParams()->get('quiz_id');
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from($db->quoteName('#__com_yaquiz_questions'));
        $query->join('INNER', $db->quoteName('#__com_yaquiz_question_quiz_map') . ' ON (' . $db->quoteName('#__com_yaquiz_questions.id') . ' = ' . $db->quoteName('#__com_yaquiz_question_quiz_map.question_id') . ')');
        $query->where($db->quoteName('#__com_yaquiz_question_quiz_map.quiz_id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $total_questions = $db->loadResult();
        return $total_questions;
    }

        /**
     * Increment the hit count by 1 for a given quiz
     * @pk - the quiz id
     */
    public function countAsHit($pk){
            
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->update('#__com_yaquiz_quizzes');
        $query->set('hits = hits + 1');
        $query->where('id = ' . $pk);
        $db->setQuery($query);
        $db->execute();

    }

        /**
     * Increment the submission count by 1 for a given quiz
     * @pk - the quiz id
     */
    public function countAsSubmission($pk){
                
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query->update('#__com_yaquiz_quizzes');
            $query->set('submissions = submissions + 1');
            $query->where('id = ' . $pk);
            $db->setQuery($query);
            $db->execute();
        
    }


    public function saveGeneralResults($quiz_id, $scorepercentage, $passfail){
        $app = Factory::getApplication();

        //see if $quiz_id exists in __com_yaquiz_results_general
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('quiz_id');
        $query->from($db->quoteName('#__com_yaquiz_results_general'));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $db->setQuery($query);
        $result = $db->loadResult();

        if($result){
            //get the existing total_average_score and submissions
            $query = $db->getQuery(true);
            $query->select('total_average_score, submissions');
            $query->from($db->quoteName('#__com_yaquiz_results_general'));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $db->setQuery($query);
            $results = $db->loadObject();
            $total_average_score = $results->total_average_score;
            $submissions = $results->submissions;

            $weighted_total_avg = ($total_average_score * $submissions) + $scorepercentage;
            $new_total_avg = $weighted_total_avg / ($submissions + 1);

            //update record
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__com_yaquiz_results_general'));
            $query->set($db->quoteName('submissions') . ' = ' . $db->quoteName('submissions') . ' + 1');
            if($passfail === 'pass'){
                $query->set($db->quoteName('total_times_passed') . ' = ' . $db->quoteName('total_times_passed') . ' + 1');
            }
            $query->set($db->quoteName('total_average_score') . ' = ' . $db->quote($new_total_avg));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $db->setQuery($query);
            $db->execute();
        }
        else{
            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__com_yaquiz_results_general'));
            $query->columns(array($db->quoteName('quiz_id'), $db->quoteName('submissions'), $db->quoteName('total_average_score'), $db->quoteName('total_times_passed')));
            if($passfail == 'pass'){
                $query->values($db->quote($quiz_id) . ', 1, ' . $db->quote($scorepercentage) . ', 1');
            }
            else{
                $query->values($db->quote($quiz_id) . ', 1, ' . $db->quote($scorepercentage) . ', 0');
            }
            $db->setQuery($query);
            $db->execute();
        }






    }


    /**
     * Save the results of an individual quiz attempt
     * @results - the results object
     */
    public function saveIndividualResults($results, $quiz_record_results){

        $userid = Factory::getApplication()->getIdentity()->id;
        if($results->passfail == 'fail'){
            $results->passed = 0;
        }
        else{
            $results->passed = 1;
        }
        $score = $results->correct / $results->total * 100;
        //trim to 1 decimal place
        $results->score = round($score, 1);

        if($quiz_record_results == 3){
            $results->full_results = json_encode($results->questions);
        }
        else{
            $results->full_results = '';
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__com_yaquiz_results'));
        $query->columns(array(
            $db->quoteName('quiz_id'),
            $db->quoteName('user_id'), 
            $db->quoteName('score'), 
            $db->quoteName('points'), 
            $db->quoteName('total_points'), 
            $db->quoteName('passed'),
            $db->quoteName('full_results')));
        $query->values(
            $db->quote($results->quiz_id) . ', ' 
            . $db->quote($userid) . ', ' 
            . $db->quote($results->score) . ', ' 
            . $db->quote($results->correct) . ', ' 
            . $db->quote($results->total) . ', ' 
            . $db->quote($results->passed) . ', '
            . $db->quote($results->full_results)
        );

        $db->setQuery($query);
        $db->execute();

        //check for a record in __com_yaquiz_user_quiz_map linking this user to this quiz
        $this->incrementAttemptCount($results->quiz_id, $userid);

        

        //return the result id (latest one there may be multiple)
        $query = $db->getQuery(true);
        $query->select('id');
        $query->from($db->quoteName('#__com_yaquiz_results'));
        $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($results->quiz_id));
        $query->order($db->quoteName('id') . ' DESC');
        $query->setLimit(1);
        $db->setQuery($query);
        $result_id = $db->loadResult();


        //create a result hash
        $verifyhash = substr(md5($userid . $result_id), 0, 8);

        //update the record with the hash
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__com_yaquiz_results'));
        $query->set($db->quoteName('verifyhash') . ' = ' . $db->quote($verifyhash));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($result_id));
        $db->setQuery($query);
        $db->execute();

        return $result_id;

    }


    public function incrementAttemptCount($quiz_id, $userid, $amount = 1){

        $db = Factory::getContainer()->get('DatabaseDriver');

        //check for a record in __com_yaquiz_user_quiz_map linking this user to this quiz
        $query = $db->getQuery(true);
        $query->select('user_id');
        $query->from($db->quoteName('#__com_yaquiz_user_quiz_map'));
        $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $db->setQuery($query);
        $result = $db->loadResult();

        if($result){
            //update record
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__com_yaquiz_user_quiz_map'));
            $query->set($db->quoteName('attempt_count') . ' = ' . $db->quoteName('attempt_count') . ' + ' . $amount);
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $db->setQuery($query);
            $db->execute();
        }
        else{
            //insert record
            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__com_yaquiz_user_quiz_map'));
            $query->columns(array($db->quoteName('user_id'), $db->quoteName('quiz_id'), $db->quoteName('attempt_count')));
            $query->values($db->quote($userid) . ', ' . $db->quote($quiz_id) . ', ' . $db->quote($amount));
            $db->setQuery($query);
            $db->execute();
        }


    }

    /**
     * Check if the user has reached the maximum number of attempts for this quiz
     * @quiz_id - the id of the quiz
     * @return - true if the user has reached the maximum number of attempts, false otherwise
     */
    public function reachedMaxAttempts($quiz_id){


        $max_attempts = (int)$this->getQuizParams($quiz_id)->max_attempts;

        if($max_attempts == 0){
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getApplication()->getIdentity();
        $userid = $user->id;

        //if they're a guest...
        if($user->guest){
            return false;
        }

        $query = $db->getQuery(true);
        $query->select('attempt_count');
        $query->from($db->quoteName('#__com_yaquiz_user_quiz_map'));
        $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $db->setQuery($query);
        $attempt_count = $db->loadResult();

        if(!$attempt_count){
            return false;
        }
        if($attempt_count >= $max_attempts){
            return true;
        }
        return false;

    }


    /**
     * Check if user is allowed to keep trying the quiz
     * @quiz_id - the id of the quiz
     * @return - the number of attempts left, 0 if none left, or -1 if unlimited
     */
    public function quizAttemptsLeft($quiz_id){
            
            $max_attempts = (int)$this->getQuizParams($quiz_id)->max_attempts;
            if($max_attempts == 0){
                return -1;
            }
    
            $db = Factory::getContainer()->get('DatabaseDriver');
            $user = Factory::getApplication()->getIdentity();
            $userid = $user->id;
    
            //if they're a guest...
            if($user->guest){
                return -1;
            }
    
            $query = $db->getQuery(true);
            $query->select('attempt_count');
            $query->from($db->quoteName('#__com_yaquiz_user_quiz_map'));
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $db->setQuery($query);
            $attempt_count = $db->loadResult();

            if($attempt_count == 0){
                return $max_attempts;
            }
    
            if(!$attempt_count){
                return -1;
            }
            if($attempt_count >= $max_attempts){
                return 0;
            }
            return $max_attempts - $attempt_count;

    }


    public function getAttemptCount($quiz_id, $userid){
            

            $db = Factory::getContainer()->get('DatabaseDriver');
    
            $query = $db->getQuery(true);
            $query->select('attempt_count');
            $query->from($db->quoteName('#__com_yaquiz_user_quiz_map'));
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($userid));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $db->setQuery($query);
            $attempt_count = $db->loadResult();
    
            if(!$attempt_count){
                return 0;
            }
            return $attempt_count;
    
    }


    public function getResultFromVerificationCode($certcode){

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        
        //join results and users table
        $query->select('r.score, r.submitted, u.name, q.title as quiz_title');
        $query->from($db->quoteName('#__com_yaquiz_results', 'r'));
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('r.user_id') . ' = ' . $db->quoteName('u.id') . ')');
        //join with quizzes too get the quiz name
        $query->join('LEFT', $db->quoteName('#__com_yaquiz_quizzes', 'q') . ' ON (' . $db->quoteName('r.quiz_id') . ' = ' . $db->quoteName('q.id') . ')');
        $query->where($db->quoteName('r.verifyhash') . ' = ' . $db->quote($certcode));
        $db->setQuery($query);
        $result = $db->loadObject();


        return $result;


    }



    //Stuff related to logging start and end times of quizzes, and setting time limits on quizzes

    /**
     * Create a new timer
     * @return - the id of the timer record
     */
    public function createNewTimer($user_id, $quiz_id){

            //the time allowed in minutes
            $time_allowed = $this->getQuizParams($quiz_id)->quiz_timer_limit;


    
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__com_yaquiz_user_quiz_times'));
            $query->columns($db->quoteName('user_id') . ', ' . $db->quoteName('quiz_id') . ', ' . $db->quoteName('start_time') . ', ' . $db->quoteName('limit_time'));
            //start with time allowed plus fifteen seconds
            $query->values($db->quote($user_id) . ', ' . $db->quote($quiz_id) . ', NOW(), DATE_ADD(NOW(), INTERVAL ' . ($time_allowed * 60 + 15) . ' SECOND)');
            $db->setQuery($query);
            $db->execute();
            $timer_id = $db->insertid();
    
            return $timer_id;
    
    }



    /**
     * check if user has already started a quiz
     * @return - the id of the timer record, or 0 if none exist, already expired, or already completed
     */
    public function getTimerId($user_id, $quiz_id){
                
            $db = Factory::getContainer()->get('DatabaseDriver');
    
            $query = $db->getQuery(true);
            $query->select('id');
            $query->from($db->quoteName('#__com_yaquiz_user_quiz_times'));
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $query->where($db->quoteName('completed') . ' = 0');
            $query->where($db->quoteName('result_id') . ' = 0');
            $query->where($db->quoteName('limit_time') . ' > NOW()');
            $db->setQuery($query);
            $timer_id = $db->loadResult();

            if(!$timer_id){
                return 0;
            }

            return $timer_id;

    }

    public function getTimeRemainingAsSeconds($user_id, $quiz_id){

        $timer_id = $this->getTimerId($user_id, $quiz_id);

        if(!$timer_id){
            return 0;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select('TIMESTAMPDIFF(SECOND, NOW(), limit_time) as time_remaining');
        $query->from($db->quoteName('#__com_yaquiz_user_quiz_times'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($timer_id));
        $db->setQuery($query);
        $time_remaining = $db->loadResult();

        return $time_remaining;

    }


    public function updateTimerOnSubitted($user_id, $quiz_id, $result_id){
            
            $timer_id = $this->getTimerId($user_id, $quiz_id);
    
            if(!$timer_id){
                return 0;
            }
    
            $db = Factory::getContainer()->get('DatabaseDriver');
    
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__com_yaquiz_user_quiz_times'));
            $query->set($db->quoteName('completed') . ' = 1');
            $query->set($db->quoteName('result_id') . ' = ' . $db->quote($result_id));
            $query->where($db->quoteName('id') . ' = ' . $db->quote($timer_id));
            $db->setQuery($query);
            $db->execute();
    
            return 1;
    
    }


    /**
     * Check for expired timers and mark attempts incomplete with grades of 0
     * Call this before starting a quiz or checking results
     */
    public function cleanupQuizTimer($user_id, $quiz_id){
                
        //count number of incomplete attempts
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from($db->quoteName('#__com_yaquiz_user_quiz_times'));
        $query->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $query->where($db->quoteName('completed') . ' = 0');
        $query->where($db->quoteName('result_id') . ' = 0');
        $query->where($db->quoteName('limit_time') . ' < NOW()');
        $db->setQuery($query);
        $incomplete_attempts = $db->loadResult();

        //if there are no incomplete attempts, do nothing
        if($incomplete_attempts == 0){
            return;
        }


        //set completed to -1 for all incomplete attempts
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__com_yaquiz_user_quiz_times'));
        $query->set($db->quoteName('completed') . ' = -1');
        $query->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $query->where($db->quoteName('completed') . ' = 0');
        $query->where($db->quoteName('result_id') . ' = 0');
        $query->where($db->quoteName('limit_time') . ' < NOW()');
        $db->setQuery($query);
        $db->execute();

        //create a new result record for each incomplete attempt
        $results = new \stdClass();
        $results->quiz_id = $quiz_id;
        $results->score = 0;
        $results->correct = 0;
        $results->total = 100;
        $results->passfail = 'fail';
        $results->fullresults = '';

        $result_records = array();

        for($i = 0; $i < $incomplete_attempts; $i++){
            $result_records[] = $this->saveIndividualResults($results, 2);
        }

        //update the timer records with the result ids
        for ($i = 0; $i < $incomplete_attempts; $i++){
            $query = $db->getQuery(true);
            $query->update($db->quoteName('#__com_yaquiz_user_quiz_times'));
            $query->set($db->quoteName('result_id') . ' = ' . $db->quote($result_records[$i]));
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
            $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
            $query->where($db->quoteName('completed') . ' = -1');
            $query->where($db->quoteName('result_id') . ' = 0');
            $query->where($db->quoteName('limit_time') . ' < NOW()');
            $db->setQuery($query);
            $db->execute();
        }
        
    }

    public function cleanupQuizTimers($user_id){

            //get the quiz ids
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);
            $query->select('quiz_id');
            $query->from($db->quoteName('#__com_yaquiz_user_quiz_times'));
            $query->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
            $query->where($db->quoteName('completed') . ' = 0');
            $query->where($db->quoteName('result_id') . ' = 0');
            $query->where($db->quoteName('limit_time') . ' < NOW()');
            $db->setQuery($query);
            $quiz_ids = $db->loadColumn();

            //if none
            if(!$quiz_ids){
                return;
            }

            //for each quiz id, call cleanupQuizTimer
            foreach($quiz_ids as $quiz_id){
                $this->cleanupQuizTimer($user_id, $quiz_id);
            }
    }


}

