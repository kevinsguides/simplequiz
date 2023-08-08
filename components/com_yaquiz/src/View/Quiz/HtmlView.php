<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/
 
namespace KevinsGuides\Component\Yaquiz\Site\View\Quiz;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

//use language helper
use Joomla\CMS\Language\Text;


class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {

        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $globalParams = Factory::getApplication()->getParams('com_yaquiz');
        $model = $this->getModel('Quiz');

        // try to get quiz from input
        $this->quiz_id = $app->input->get('id', 0);

        //if it didnt work, see if it's in the menu item params
        if($this->quiz_id == 0){
            Log::add('Quiz id not found in input, checking menu', Log::DEBUG, 'com_yaquiz');
            $this->quiz_id = $active->getParams()->get('id');
            //if that didn't work, check for the old quiz_id
            if($this->quiz_id == 0){
                Log::add('Quiz id not found in menu, checking for old quiz_id', Log::DEBUG, 'com_yaquiz');
                $this->quiz_id = $active->getParams()->get('quiz_id');
            }
            Log::add('found quiz id from menu its ' . $this->quiz_id, Log::DEBUG, 'com_yaquiz');
        }


        $this->item = $model->getItem($this->quiz_id);


        //set the title
        $this->document->setTitle($this->item->title);

        //check if quiz exists
        if(!$this->item){
            Log::add('Quiz not found', Log::ERROR, 'com_yaquiz');
            $app->enqueueMessage(Text::_('COM_YAQUIZ_VIEW_QUIZ_NOT_FOUND'), 'error');
            $app->redirect('index.php');
        }


        //check if quiz is published
        if($this->item->published == 0 && ($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true)){
            $app->enqueueMessage(Text::_('COM_YAQUIZ_VIEW_QUIZ_NOT_PUBLISHED'), 'error');
            $app->redirect('index.php');
        }
        elseif ($this->item->published == 0 && ($app->getIdentity()->authorise('core.edit', 'com_yaquiz') == true)){
            $app->enqueueMessage(Text::_('COM_YAQUIZ_VIEW_QUIZ_NOT_PUBLISHED_WARNING_EDITOR'), 'warning');
        }

        $quizparams = $model->getQuizParams($this->item->id);
        $quizAccess = $this->item->access;
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedViewLevels();
        if(!in_array($quizAccess, $userGroups)){
            $app->enqueueMessage(Text::_('COM_YAQUIZ_VIEW_QUIZ_DENIED'), 'error');
            $app->redirect('index.php');
        }

        //check reachedMaxAttempts
        if($model->reachedMaxAttempts($this->quiz_id)){
            //does not apply to results layout
            if ($this->getLayout() != 'results'){
                //and they don't have core.edit permission
                if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') != true){
                    $this->setLayout('max_attempt_reached');
                    return parent::display($tpl);
                }
            }
        }


        $wam = $app->getDocument()->getWebAssetManager();
        //load the style.css file for the template being used for this quiz, if it exists
        //the file is in this component's tmpl folder

        $styleFile =  'components/com_yaquiz/tmpl/quiz/' . $globalParams->get('theme','default') . '/style.css';
        Log::add('style file: ' . $styleFile, Log::INFO, 'com_yaquiz');
        if(file_exists($styleFile)){
            Log::add('style file exists', Log::INFO, 'com_yaquiz');
            $wam->registerAndUseStyle('com_yaquiz.quizstyle', $styleFile);
        }


        //if we're not on the results layout

        if ($this->getLayout() != 'results'){
        //if quiz displaymode is default 
        if($quizparams->quiz_displaymode === 'individual'){
            $this->setLayout('quiztype_oneperpage');
            $this->currPage = $app->input->get('page', 0);
        }
        elseif ($quizparams->quiz_displaymode === 'jsquiz'){
            $this->setLayout('quiztype_jsquiz');
        }
        else{
            $this->setLayout('quiztype_singlepage');
        }
        }



        
    
        parent::display($tpl);
        
    }


    public function getRoute($url = '')
    {
        Log::add('getRoute called', Log::INFO, 'com_yaquiz');
        $app = Factory::getApplication();
        $router = $app->getRouter();
        $uri = $router->build($url);
        return $uri->toString();
    }

}