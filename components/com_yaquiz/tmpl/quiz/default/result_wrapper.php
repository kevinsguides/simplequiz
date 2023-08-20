<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/

/**
 * This file wraps the results/feedback for a single question on the quiz feedback page after user takes quiz
 * Accessible vars include: $question, $iscorrect, $useranswer, $questionnum, $quiz_id
 */
defined ( '_JEXEC' ) or die;
use Joomla\CMS\Language\Text;


//check points
$quizParams = $model->getQuizParams($quiz_id);
$pointsFeedback = '';
if ($quizParams->get('quiz_use_points', 0) == 1) {
    if ($iscorrect) {
        $pointsFeedback = $question->params->points . ' / ' . $question->params->points . ' ' . Text::_('COM_YAQ_POINTS');
    } else {
        $pointsFeedback = '0 / ' . $question->params->points . ' ' . Text::_('COM_YAQ_POINTS');
    }
}

$feedback = '';

if($useranswer == ''){
    $useranswer = Text::_('COM_YAQ_NOANSWER');
}

$feedback .= '<p><strong>'.Text::_('COM_YAQ_YOURANSWER').'</strong> ' . $useranswer . '</p>';

if ($question->feedback_right != '' || $question->feedback_wrong != '') {
    if ($iscorrect) {
        $feedback .= $question->feedback_right;
    } else {
        $feedback .= $question->feedback_wrong;
    }
    $feedback .= '<br/>';
}

if ($quizParams->get('quiz_feedback_showcorrect', 1) == 1) {
    $feedback .= $question->correct_answer;
}

if ($iscorrect) {
    $icon = '<i class="fas fa-check-circle yaq-icocorrect"></i>';
} else {
    $icon = '<i class="fas fa-times-circle yaq-icofail"></i>';
}

//numbering
if ($questionnum != 0) {
    $questionnum = $questionnum . ') ';
} else {
    $questionnum = '';
}


$html = '
<div class="card">
    <h2 class="card-header"><span class="float-end">'. $icon .'</span>'.$questionnum.$question->question.'</h2>
    <div class="card-body">
        '.$question->details.$feedback.'
</div>
<div class="card-footer">
    <span class="float-end">'.$pointsFeedback.'</span>
</div>
</div>
<br/>
';

?>


