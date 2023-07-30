<?php
namespace KevinsGuides\Component\Yaquiz\Site\View\Quiz;


defined ( '_JEXEC' ) or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;


//if $this-> item is already set
if (isset($this->item)) {
    $quiz = $this->item;
} else {
    $quiz = $this->get('Item');
}


$app = Factory::getApplication();
$globalParams = $app->getParams('com_yaquiz');
$theme = $globalParams->get('theme','default');

$error = new \stdClass();
$error->type = 'error';
$error->message = Text::_('COM_YAQ_MAX_ATTEMPTS_REACHED');
$error->title = $quiz->title;

$error_page = (JPATH_SITE . '/components/com_yaquiz/tmpl/quiz/' . $theme . '/error.php');
include($error_page);

?>
