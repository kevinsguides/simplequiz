<?php
/**
 * renders individual pages for each question with help of questionbuilder class
*/
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use KevinsGuides\Component\Yaquiz\Site\Helper\QuestionBuilderHelper;
use KevinsGuides\Component\Yaquiz\Site\Model\QuizModel;

defined ( '_JEXEC' ) or die;

$currPage = $this->currPage;

//if $this-> item is already set
if(isset($this->item)){
    $quiz = $this->item;
}
else{
    $quiz = $this->get('Item');
}

$app = Factory::getApplication();
$wam = $app->getDocument()->getWebAssetManager();
//get config from component
$globalParams = $app->getParams('com_yaquiz');
if ($globalParams->get('get_mathjax') === '1') {
    $wam->registerAndUseScript('com_yaquiz.mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js', [], ['defer' => true]);
}
if ($globalParams->get('get_mathjax') === '2') {
    Log::add('Loading local mathjax', Log::INFO, 'com_yaquiz');
    $wam->registerAndUseScript('com_yaquiz.mathjaxlocal', 'components/com_yaquiz/js/mathjax/es5/tex-svg.js', [], ['defer' => true]);
}

$theme = $globalParams->get('theme');
$stylefile = '/components/com_yaquiz/tmpl/' . $theme . '/style.css';
//if file exists
if (file_exists(JPATH_ROOT . $stylefile)) {
    $wam->registerAndUseStyle('com_yaquiz.quizstyle', $stylefile);
}


$model = new QuizModel();
//the total number of questions in quiz from the model...
$totalQuestions = $model->getTotalQuestions($quiz->id);
$quiz_params = $model->getQuizParams($quiz->id);

//if current page is greater than total questions, redirect to last page
if($currPage > $totalQuestions){
    $app->redirect('index.php?option=com_yaquiz&view=quiz&id='.$quiz->id.'&page='.$totalQuestions);
}



$qbHelper = new QuestionBuilderHelper();

JHtml::_('behavior.keepalive');

?>

<form action="<?php echo JURI::root(); ?>index.php?option=com_yaquiz&task=quiz.loadNextPage" method="POST">
    <input type="hidden" name="quiz_id" value="<?php echo $quiz->id; ?>" />
    <input type="hidden" name="page" value="<?php echo $currPage; ?>" />

    <?php if ($currPage == 0){
        //record hits
        if ($globalParams->get('record_hits') === '1') {
            $model->countAsHit($quiz->id);
        }
    } 
    ?>

    <?php
        $template = (JPATH_SITE . '/components/com_yaquiz/tmpl/quiz/' . $theme . '/multipage_pagewrapper.php');
        include($template);
    ?>




<?php echo JHtml::_('form.token'); ?>
</form>