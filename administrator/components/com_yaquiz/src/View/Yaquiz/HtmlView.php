<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/


namespace KevinsGuides\Component\Yaquiz\Administrator\View\Yaquiz;


defined ( '_JEXEC' ) or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Administrator\Helper\ContentHelper;
use KevinsGuides\Component\Yaquiz\Administrator\Helper\YaquizHelper;

//this view for 1 quiz
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView

{

    public function display($tpl = null)

    {

        Log::add('HtmlView::display() called', Log::INFO, 'com_yaquiz');

        //all quiz, comp set, quiz set, prev, quest man
        //$toolbar = Toolbar::getInstance('toolbar');
        $toolbar = Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('toolbar');
        //add component options
        

        //get id from url
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        else{
            $id = 0;
        }
        
        //get quiz from the model
        $model = $this->getModel();
        $app = Factory::getApplication();
        $wa = $app->getDocument()->getWebAssetManager();

        $cParams = ComponentHelper::getParams('com_yaquiz');
        if($cParams->get('load_mathjax')==='1'){
            $wa->registerAndUseScript('com_yaquiz.mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js');
        }


        //if view is default
        if($this->getLayout() == 'default')
        {
            //set this item to that quiz
            $this->item = $model->getQuiz($id);
            $quizParams = $model->getParams($id);


            


            ToolbarHelper::back('COM_YAQUIZ_ALLQUIZZES', 'index.php?option=com_yaquiz&view=yaquizzes');

            if(YaquizHelper::canEditQuiz($id)){
                
                ToolbarHelper::custom('Yaquiz.redirectEdit', 'edit', 'edit', 'COM_YAQUIZ_QUIZSETTINGS', false);
            }
            

            //an external link with target blank
            ToolbarHelper::custom('Yaquiz.preview', 'link', 'preview', 'COM_YAQUIZ_PREVIEW', false);
            ToolbarHelper::custom('Questions.display', 'checkbox', 'checkbox', 'COM_YAQUIZ_QUESTION_MGR', false);
            if($quizParams['quiz_record_results'] > 1){
                ToolbarHelper::custom('Yaquiz.gotoResults', 'chart', 'results', 'COM_YAQUIZ_RESULTS', false);
            }
            if($quizParams['quiz_record_results'] == -1){
                if($cParams->get('quiz_record_results') > 1){
                    ToolbarHelper::custom('Yaquiz.gotoResults', 'chart', 'results', 'COM_YAQUIZ_RESULTS', false);
                }
            }

            if($app->getIdentity()->authorise('core.admin', 'com_yaquiz')){
                ToolbarHelper::preferences('com_yaquiz');
            }
            
            ToolbarHelper::title(Text::_('COM_YAQUIZ_PAGETITLE_QUIZEDITPREFIX').$this->item->title, 'yaquiz');
           
            $app->setUserState('com_yaquiz.redirectbackto', Uri::getInstance()->toString());



            return parent::display($tpl);
            
        }

        //if view is edit
        if($this->getLayout() == 'edit')
        {

            //hide the main menu
            $app->getInput()->set('hidemainmenu', true);

            //check if item is checked out
            if($model->isCheckedOut($model->getQuiz($id))){
                $app->enqueueMessage('This quiz is currently being edited by another user', 'error');
                $app->redirect('index.php?option=com_yaquiz&view=yaquizzes');
            }

            //we need a toolbar
            $this->addEditToolbar();

            //if id is not 0
            if($id != 0)
            {
                //get data for that quiz
                $data = $model->getQuiz($id);
                //get the form
                $formy = $model->getForm($data, false);
                if($formy == false){
                    $app->redirect('index.php?option=com_yaquiz&view=yaquizzes');
                }
                $this->form = $formy;
                return parent::display($tpl);
            }
            else{
                //get the form
                $this->form = $model->getForm(null, false);
                //hide the randomize_mchoice field by default
                return parent::display($tpl);
            }
        }

        //if view is results
        if($this->getLayout() == 'results'){
            //get the quiz
            $this->item = $model->getQuiz($id);
            $title = Text::_('COM_YAQUIZ_YAQUIZRESULTS');
            ToolbarHelper::title($title, 'yaquiz');
            ToolbarHelper::back('COM_YAQUIZ_QUIZOVERVIEW', 'index.php?option=com_yaquiz&view=yaquiz&id='.$id);
            return parent::display($tpl);
        }

        //go to results page for an individual user/attempt results
        if($this->getLayout() == 'detailresults'){
            $title = Text::_('COM_YAQUIZ_YAQUIZDETAILRESULTS');
            ToolbarHelper::title($title, 'yaquiz');
            ToolbarHelper::back('COM_YAQUIZ_BACKTOALLRESULTS', 'index.php?option=com_yaquiz&view=yaquiz&layout=results&id='.$id);
            return parent::display($tpl);
        }



    }

    protected function addEditToolbar(){
        
        //set controller to YaquizController
        $controller = 'YaquizController';

        $app = Factory::getApplication();
        $input = $app->input;
        $input->set('controller', $controller);
        //set title
        $title = Text::_('COM_YAQUIZ_YAQUIZ');

        //add title to toolbar
        ToolbarHelper::title($title, 'yaquiz');


        $toolbar = Toolbar::getInstance('toolbar');
        ToolbarHelper::cancel('Yaquiz.cancel');
        ToolbarHelper::save('Yaquiz.saveclose');
        ToolbarHelper::apply('Yaquiz.save');

    }

}