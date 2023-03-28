<?php
namespace KevinsGuides\Component\SimpleQuiz\Site\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\MVC\Model\ItemModel;

defined('_JEXEC') or die;


class QuizModel extends ItemModel{

    public function __construct($config = array(), MVCFactoryInterface $factory = null)
    {
        Log::add('QuizModel::__construct() called', Log::INFO, 'com_simplequiz');
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
        if($pk == null){
            $pk = $this->getState('quiz.id');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__simplequiz_quizzes'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $quiz = $db->loadObject();
        return $quiz;
	}

    public function getQuizParams($pk = null){
        


        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params');
        $query->from($db->quoteName('#__simplequiz_quizzes'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $params = $db->loadResult();
        return json_decode($params);
    }



    public function getQuestions($pk = null)
    {
        //the __simplequiz_question_quiz_map table has question_id and quiz_id cols
        //need to join with the questions table to get the questions for this quiz
        //get pk from GET
        if(isset($_GET['id'])){

            $pk = $_GET['id'];
        }
        else{
            $active = Factory::getApplication()->getMenu()->getActive();
            //get params from the menu item
            $pk = $active->getParams()->get('quiz_id');
        }
 
        Log::add('attempt get questions with quiz id'.$pk, Log::INFO, 'com_simplequiz');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__simplequiz_questions'));
        $query->join('INNER', $db->quoteName('#__simplequiz_question_quiz_map') . ' ON (' . $db->quoteName('#__simplequiz_questions.id') . ' = ' . $db->quoteName('#__simplequiz_question_quiz_map.question_id') . ')');
        $query->where($db->quoteName('#__simplequiz_question_quiz_map.quiz_id') . ' = ' . $db->quote($pk));
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
        $query->from($db->quoteName('#__simplequiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $question_params = $db->loadObject();
        //decode
        $question_params = json_decode($question_params->params);
        return $question_params;
    }

    public function getQuestion($question_id)
    {

        Log::add('attempt get question with id'.$question_id, Log::INFO, 'com_simplequiz');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__simplequiz_questions'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote($question_id));
        $db->setQuery($query);
        $question = $db->loadObject();
        $question->params = json_decode($question->params);
        $question->correct_answer= $this->getCorrectAnswerText($question);
        Log::add('attempt load question id '.$question->id, Log::INFO, 'com_simplequiz');
        return $question;
    }

    public function getQuestionFromQuizOrdering($quiz_id, $order){
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('question_id');
        $query->from($db->quoteName('#__simplequiz_question_quiz_map'));
        $query->where($db->quoteName('quiz_id') . ' = ' . $db->quote($quiz_id));
        $query->where($db->quoteName('ordering') . ' = ' . $db->quote($order));
        $db->setQuery($query);
        $question_id = $db->loadResult();
        return $this->getQuestion($question_id);
    }

    public function checkAnswer($question_id, $answer)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('correct, answers');
        $query->from($db->quoteName('#__simplequiz_questions'));
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
        $query->from($db->quoteName('#__simplequiz_questions'));
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
            return '"'. $correct_answer_text . '" was the correct answer.';
        }
        if ($question_type === 'true_false'){
            if($question->correct === '1'){
                return 'The correct answer was True.';
            }
            else{
                return 'The correct answer was False.';
            }
        }
        if ($question_type === 'fill_blank'){
            $possible_answers = json_decode($question->answers);
            $answerList = '';
            foreach($possible_answers as $answer){
                $answerList .= '<li>' . $answer . '</li>';
            }
            $answerList = '<ul>' . $answerList . '</ul>';
            return 'Any of the following would be counted as correct: '. $answerList;
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


    public function getTotalQuestions($pk = null){
        if ($pk === null) {
            $active = Factory::getApplication()->getMenu()->getActive();
            //get params from the menu item
            $pk = $active->getParams()->get('quiz_id');
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from($db->quoteName('#__simplequiz_questions'));
        $query->join('INNER', $db->quoteName('#__simplequiz_question_quiz_map') . ' ON (' . $db->quoteName('#__simplequiz_questions.id') . ' = ' . $db->quoteName('#__simplequiz_question_quiz_map.question_id') . ')');
        $query->where($db->quoteName('#__simplequiz_question_quiz_map.quiz_id') . ' = ' . $db->quote($pk));
        $db->setQuery($query);
        $total_questions = $db->loadResult();
        return $total_questions;
    }
}

