<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/

 
namespace KevinsGuides\Component\Yaquiz\Administrator\Controller;



defined ( '_JEXEC' ) or die;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Joomla\CMS\Language\Text;
/**
 * Summary of QuestionsController
 */
class QuestionsController extends BaseController
{
    /**
     * Summary of display
     * @param mixed $cachable
     * @param mixed $urlparams
     * @return void
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('deleteQuestion', 'deleteQuestion');
        $this->registerTask('display', 'display');
        $this->registerTask('insertMultiSaveAll', 'insertMultiSaveAll');

    }

    public function display($cachable = false, $urlparams = false)
    {
        Log::add('QuestionsController::display() called', Log::INFO, 'com_yaquiz');
        $this->setRedirect('index.php?option=com_yaquiz&view=questions');
    }


    public function allowDelete()
    {

        $user = Factory::getApplication()->getIdentity();

        // Check edit
        if ($user->authorise('core.delete', 'com_yaquiz')) {
            return true;
        }

        return false;
    }

    public function allowAdd()
    {

        $user = Factory::getApplication()->getIdentity();

        // Check edit
        if ($user->authorise('core.create', 'com_yaquiz')) {
            return true;
        }

        return false;
    }



    public function deleteQuestion()
    {

        if(!$this->allowDelete()){
            $this->setMessage(Text::_('COM_YAQUIZ_PERM_REQUIRED_DELETE'), 'error');
            $this->setRedirect('index.php?option=com_yaquiz&view=questions');
            return;
        }

        $model = $this->getModel('Question');
        $delete = $this->input->get('delete', '0');
        if($model->deleteQuestion($delete)){
            $this->setMessage('Question deleted');
        }
        else{
            $this->setMessage('Error: Question not deleted');
        }
        $this->setRedirect('index.php?option=com_yaquiz&view=questions');
    }

    public function newQuestion(){
        $this->setRedirect('index.php?option=com_yaquiz&view=Question&layout=edit');
    }


    /**Copies the excel file to the temporary excelfiles directory
     * and then loads the questions into a table for user review
     */
    public function startInsertMulti(){

        if(!$this->allowAdd()){
            $this->setMessage(Text::_('COM_YAQUIZ_PERM_REQUIRED_CREATE'), 'error');
            $this->setRedirect('index.php?option=com_yaquiz&view=questions');
            return;
        }

        $app = Factory::getApplication();

        $file = $this->input->files->get('excelfile');
        //use joomla filesystem to check if file is valid
        $filesystem = new \Joomla\CMS\Filesystem\File();
        $filename = $filesystem->makeSafe($file['name']);
        $src = $file['tmp_name'];
        $dest=  JPATH_COMPONENT . '/excelfiles/' . $filename;
        //make sure filename ends with the .xlsx extension
        if(substr($filename, -5) !== '.xlsx'){
            $app->enqueueMessage('Error: File must be an Excel 2007+ file (.xlsx)');
            $this->setRedirect('index.php?option=com_yaquiz&view=Questions&layout=insertmulti');
            return;
        }

        $filesystem->upload($src, $dest);

        $excelHelper = new \KevinsGuides\Component\Yaquiz\Administrator\Helper\ExcelHelper();
        $questions = $excelHelper->loadQuestions($dest);
        if($questions === false){
            $app->enqueueMessage('Error: File could not be loaded. Please review the rules for the Excel file.');
            $this->setRedirect('index.php?option=com_yaquiz&view=Questions&layout=insertmulti');
            return;
        }
        $table_html = $excelHelper->getQuestionsPreview($questions);

        //save the preview table to user session
        $session = $app->getSession();
        $session->set('questions_preview', $table_html);
        //set the category id in session
        $session->set('insertmulti_catid', $this->input->get('catid', '0'));
        //the filename
        $session->set('insertmulti_filename', $dest);

        $this->setRedirect('index.php?option=com_yaquiz&view=Questions&layout=insertmulti_review');
    }

    public function insertMultiCancel(){
        //clear the session values
        $app = Factory::getApplication();
        $session = $app->getSession();
        $session->clear('questions_preview');
        $session->clear('insertmulti_catid');
        $session->clear('insertmulti_filename');
        $app->enqueueMessage('Insert Multi Cancelled');
        $this->setRedirect('index.php?option=com_yaquiz&view=Questions');
    }

    public function insertMultiSaveAll(){

        if(!$this->allowAdd()){
            $this->setMessage(Text::_('COM_YAQUIZ_PERM_REQUIRED_CREATE'), 'error');
            $this->setRedirect('index.php?option=com_yaquiz&view=questions');
            return;
        }


        //get the preview info from session and display
        $app = Factory::getApplication();
        $session = $app->getSession();

        //get the catid from post
        $catid = $session->get('insertmulti_catid');

        $filename = $session->get('insertmulti_filename');


        Log::add('attempt load file: ' . $filename, Log::INFO, 'com_yaquiz');

        //load the questions from the file
        $excelHelper = new \KevinsGuides\Component\Yaquiz\Administrator\Helper\ExcelHelper();
        $questions = $excelHelper->loadQuestions($filename);

        //insert the questions into the database
        $model = $this->getModel('Questions');
        $model->insertMultiQuestions($questions, $catid);

        //clear the session values
        $session->clear('questions_preview');
        $session->clear('insertmulti_catid');
        $session->clear('insertmulti_filename');

        //delete the excel file
        $filesystem = new \Joomla\CMS\Filesystem\File();
        $filesystem->delete($filename);


        $app->enqueueMessage('Questions inserted');
        $this->setRedirect('index.php?option=com_yaquiz&view=Questions');


    }


        //do something with multiple questions
        public function executeBatchOps(){

            if(!$this->allowDelete()){
                $this->setMessage(Text::_('COM_YAQUIZ_PERM_REQUIRED_DELETE'), 'error');
                $this->setRedirect('index.php?option=com_yaquiz&view=questions');
                return;
            }

            if(isset($_POST['batch_op']) && $_POST['batch_op'] == 'remove'){

                $questionIds = $_POST['selectedQuestions'];

                //if there are no ids
                if(empty($questionIds)){
                    $this->setMessage('No questions selected','error');
                    $this->setRedirect('index.php?option=com_yaquiz&view=yaquiz&id=' . $_POST['quiz_id']);
                    return;
                }

                $model = $this->getModel('Questions');
                $model->deleteQuestions($questionIds);
                $this->setMessage('Questions deleted permanently.');
                $this->setRedirect('index.php?option=com_yaquiz&view=questions');
            }else{
                $this->setMessage('No batch operation selected','error');
                $this->setRedirect('index.php?option=com_yaquiz&view=questions');
            }
        }


    

}