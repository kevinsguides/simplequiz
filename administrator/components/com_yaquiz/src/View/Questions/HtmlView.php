<?php
/*
 * @copyright   (C) 2023 KevinsGuides.com
 * @license     GNU General Public License version 2 or later;
*/


namespace KevinsGuides\Component\Yaquiz\Administrator\View\Questions;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
defined ( '_JEXEC' ) or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView{


    public function display($tpl = null)
    {
  
        //get model
        $model = $this->getModel();
        $app = Factory::getApplication();
        $wa = $app->getDocument()->getWebAssetManager();

        $cParams = ComponentHelper::getParams('com_yaquiz');
        if($cParams->get('load_mathjax')==='1'){
            $wa->registerAndUseScript('com_yaquiz.mathjax', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js');
        }
        $this->form = $model->getForm();
        //get all questions
        $this->items = $model->getItems();



        $toolbar = Toolbar::getInstance('toolbar');
        //if we're on the default layout, add the toolbar
        if ($this->getLayout() === 'default') {
            $toolbar->appendButton('Link', 'new', 'New Question', 'index.php?option=com_yaquiz&view=Question&layout=edit');
            if($app->getIdentity()->authorise('core.edit', 'com_yaquiz') == true){
                $toolbar->appendButton('Link', 'file', 'Import Excel Spreadsheet', 'index.php?option=com_yaquiz&view=Questions&layout=insertmulti');
            }
           
        }
        if ($this->getLayout() === 'insertmulti') {
            $toolbar->appendButton('Link', 'backward', 'Question Manager', 'index.php?option=com_yaquiz&view=Questions&layout=default');
        }
        //add component options
        $toolbar->appendButton('Link', 'options', 'GConfig', 'index.php?option=com_config&view=component&component=com_yaquiz');
        $toolbar->appendButton('Link', 'folder', 'Categories', 'index.php?option=com_categories&extension=com_yaquiz');
        ToolbarHelper::title('YAQuiz - Questions', 'list');
        ToolbarHelper::custom('Yaquizzes.display', 'list', 'list', 'Quiz Manager', false);
        //ToolbarHelper::addNew('Questions.newQuestion');

        //display the view
        return parent::display($tpl);

    }


}